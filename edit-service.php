<?php
require_once "includes/init.php";

$Title = "Edit Service";
$activePage = "my-services.php";

if (!isset($_SESSION["user"])) {
  header("Location: login.php");
  exit;
}

if (($_SESSION["user"]["role"] ?? "") !== "Freelancer") {
  $_SESSION["flash_error"] = "Only freelancers can edit services.";
  header("Location: index.php");
  exit;
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$freelancerId = $_SESSION["user"]["id"];

if (!isset($baseUrl) || (string)$baseUrl === "") {
  $baseUrl = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"])), "/");
  $baseUrl = ($baseUrl === "" ? "/" : $baseUrl . "/");
}

$categories = [
  "Web Development" => [
    "Frontend Development", "Backend Development", "Full Stack Development", "WordPress Development",
    "E-commerce Development", "Bug Fixes", "Mobile App Development"
  ],
  "Graphic Design" => [
    "Logo Design", "Brand Identity", "Print Design", "Illustration", "UI/UX Design", "Web Design", "Graphic Design"
  ],
  "Writing & Translation" => [
    "Article Writing", "Copywriting", "Proofreading", "Translation", "Technical Writing"
  ],
  "Digital Marketing" => [
    "SEO", "Social Media Marketing", "Email Marketing", "Content Marketing", "PPC Advertising"
  ],
  "Video & Animation" => [
    "Video Editing", "Animation", "Whiteboard Animation", "Video Production"
  ],
  "Music & Audio" => [
    "Voice Over", "Mixing & Mastering", "Music Production", "Audio Editing"
  ],
  "Business Consulting" => [
    "Business Planning", "Financial Consulting", "Legal Consulting", "HR Consulting"
  ],
  "Tutoring & Education" => [
    "Homework Help", "Language Tutoring", "Exam Preparation", "Programming Tutoring"
  ],
];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }
function normalizeMoney($val) {
  $val = trim((string)$val);
  $val = str_replace([",", "$"], "", $val);
  return $val;
}
function isValidCategory($category, $categories) { return isset($categories[$category]); }
function isValidSubcategory($category, $subcategory, $categories) {
  if (!isset($categories[$category])) return false;
  return in_array($subcategory, $categories[$category], true);
}

$serviceId = trim((string)($_GET["id"] ?? ""));
if ($serviceId === "") {
  $_SESSION["flash_error"] = "Missing service id.";
  header("Location: my-services.php");
  exit;
}

// Load service 
$stmt = $pdo->prepare("SELECT * FROM services WHERE service_id=:id AND freelancer_id=:fid");
$stmt->execute([":id"=>$serviceId, ":fid"=>$freelancerId]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
  $_SESSION["flash_error"] = "Access denied.";
  header("Location: my-services.php");
  exit;
}

$errors = [];
$message = "";

$uploadsBaseAbs = rtrim(__DIR__, "/\\") . "/uploads";
$serviceDirAbs  = $uploadsBaseAbs . "/services/" . $serviceId;

$allowedExt = ["jpg","jpeg","png"];
$maxSize = 5 * 1024 * 1024;
$minW = 800;
$minH = 600;

function absFromRel($rel){
  $rel = ltrim((string)$rel, "/");
  return rtrim(__DIR__, "/\\") . "/" . $rel;
}

function validateImageFile($file, $allowedExt, $maxSize, $minW, $minH) {
  if (!isset($file) || ($file["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return [null, null];
  if (($file["error"] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) return ["Upload failed.", null];
  if (($file["size"] ?? 0) > $maxSize) return ["Image must be 5MB or less.", null];

  $original = $file["name"] ?? "image";
  $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
  if (!in_array($ext, $allowedExt, true)) return ["Only JPG/JPEG/PNG allowed.", null];

  $tmp = $file["tmp_name"] ?? "";
  $info = @getimagesize($tmp);
  if (!$info) return ["Invalid image file.", null];

  $w = (int)$info[0];
  $h = (int)$info[1];
  if ($w < $minW || $h < $minH) return ["Minimum {$minW}x{$minH}px required.", null];

  return [null, ["ext"=>$ext, "tmp"=>$tmp, "original"=>$original]];
}

function countExistingImages($service) {
  $c = 0;
  if (!empty($service["image_1"])) $c++;
  if (!empty($service["image_2"])) $c++;
  if (!empty($service["image_3"])) $c++;
  return $c;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

  if (isset($_POST["remove_image"])) {
    $removeIdx = (int)$_POST["remove_image"];

    if (!in_array($removeIdx, [2,3], true)) {
      $_SESSION["flash_error"] = "Invalid image slot.";
      header("Location: edit-service.php?id=" . urlencode($serviceId));
      exit;
    }

    // reload latest service before remove
    $stmt = $pdo->prepare("SELECT * FROM services WHERE service_id=:id AND freelancer_id=:fid");
    $stmt->execute([":id"=>$serviceId, ":fid"=>$freelancerId]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$service) {
      $_SESSION["flash_error"] = "Access denied.";
      header("Location: my-services.php");
      exit;
    }

    // Must keep at least one image
    if (countExistingImages($service) <= 1) {
      $_SESSION["flash_error"] = "At least one image must remain.";
      header("Location: edit-service.php?id=" . urlencode($serviceId));
      exit;
    }

    $col = "image_" . $removeIdx;
    $oldRel = $service[$col] ?? "";

    try {
      $pdo->beginTransaction();

      $upd = $pdo->prepare("UPDATE services SET {$col}=NULL WHERE service_id=:id AND freelancer_id=:fid");
      $upd->execute([":id"=>$serviceId, ":fid"=>$freelancerId]);

      $pdo->commit();

      if (!empty($oldRel)) {
        $oldAbs = absFromRel($oldRel);
        if (is_file($oldAbs)) @unlink($oldAbs);
      }

      $_SESSION["flash_success"] = "Service updated successfully.";
      header("Location: edit-service.php?id=" . urlencode($serviceId));
      exit;

    } catch (Exception $ex) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $_SESSION["flash_error"] = "Remove failed. " . $ex->getMessage();
      header("Location: edit-service.php?id=" . urlencode($serviceId));
      exit;
    }
  }

  $title = trim($_POST["title"] ?? "");
  $category = trim($_POST["category"] ?? "");
  $subcategory = trim($_POST["subcategory"] ?? "");
  $description = trim($_POST["description"] ?? "");
  $delivery_time = trim($_POST["delivery_time"] ?? "");
  $revisions_included = trim($_POST["revisions_included"] ?? "");
  $price = normalizeMoney($_POST["price"] ?? "");

  $status = ($_POST["status"] ?? "Active") === "Inactive" ? "Inactive" : "Active";
  $featuredWanted = isset($_POST["featured_status"]) ? "Yes" : "No";

  if ($title === "") $errors["title"] = "Service title is required.";
  elseif (mb_strlen($title) < 10 || mb_strlen($title) > 100) $errors["title"] = "Title must be 10-100 characters.";

  if ($category === "") $errors["category"] = "Category is required.";
  elseif (!isValidCategory($category, $categories)) $errors["category"] = "Must be a valid category.";

  if ($subcategory === "") $errors["subcategory"] = "Subcategory is required.";
  elseif ($category !== "" && !isValidSubcategory($category, $subcategory, $categories)) {
    $errors["subcategory"] = "Must be a valid subcategory for selected category.";
  }

  if ($description === "") $errors["description"] = "Description is required.";
  elseif (mb_strlen($description) < 100 || mb_strlen($description) > 2000) {
    $errors["description"] = "Description must be 100-2000 characters.";
  }

  if ($delivery_time === "") $errors["delivery_time"] = "Delivery time is required.";
  elseif (!ctype_digit($delivery_time)) $errors["delivery_time"] = "Delivery time must be 1-90 days.";
  else {
    $d = (int)$delivery_time;
    if ($d < 1 || $d > 90) $errors["delivery_time"] = "Delivery time must be 1-90 days.";
  }

  if ($revisions_included === "") $errors["revisions_included"] = "Number of revisions is required.";
  elseif (!ctype_digit($revisions_included)) $errors["revisions_included"] = "Revisions must be 0-999.";
  else {
    $r = (int)$revisions_included;
    if ($r < 0 || $r > 999) $errors["revisions_included"] = "Revisions must be 0-999.";
  }

  if ($price === "") $errors["price"] = "Price is required.";
  elseif (!is_numeric($price)) $errors["price"] = "Price must be between $5 and $10,000.";
  else {
    $p = (float)$price;
    if ($p < 5 || $p > 10000) $errors["price"] = "Price must be between $5 and $10,000.";
  }

  if (!isset($errors["title"]) && $title !== "") {
    $tstmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE freelancer_id=:fid AND title=:t AND service_id<>:id");
    $tstmt->execute([":fid"=>$freelancerId, ":t"=>$title, ":id"=>$serviceId]);
    if ((int)$tstmt->fetchColumn() > 0) $errors["title"] = "Service title must be unique for this freelancer.";
  }

  if ($status === "Inactive") {
    $featuredWanted = "No"; 
  } else {
    if ($featuredWanted === "Yes") {
      $cstmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM services
        WHERE freelancer_id=:fid
          AND status='Active'
          AND LOWER(featured_status)='yes'
          AND service_id<>:id
      ");
      $cstmt->execute([":fid"=>$freelancerId, ":id"=>$serviceId]);
      $countFeaturedOther = (int)$cstmt->fetchColumn();

      $alreadyFeatured = (strtolower((string)$service["featured_status"]) === "yes");

      if (!$alreadyFeatured && $countFeaturedOther >= 3) {
        $errors["featured_status"] = "Maximum of 3 featured services allowed.";
      }
    }
  }

  $newRel = [
    1 => $service["image_1"],
    2 => $service["image_2"],
    3 => $service["image_3"],
  ];

  $toDeleteAbs = []; 
  if (!is_dir($serviceDirAbs)) @mkdir($serviceDirAbs, 0755, true);

  $uploads = [1=>"image_1", 2=>"image_2", 3=>"image_3"];
  $newFiles = [];

  foreach ($uploads as $i => $key) {
    [$err, $data] = validateImageFile($_FILES[$key] ?? null, $allowedExt, $maxSize, $minW, $minH);
    if ($err) $errors[$key] = $err;
    if ($data) $newFiles[$i] = $data;
  }

  // At least one image must remain (existing or new)
  $countRemain = 0;
  for ($i=1; $i<=3; $i++) {
    $hasExisting = !empty($newRel[$i]);
    $hasNew = isset($newFiles[$i]);
    if ($hasExisting || $hasNew) $countRemain++;
  }
  if ($countRemain < 1) $errors["images"] = "At least one image is required.";

  if (empty($errors)) {
    try {
      $pdo->beginTransaction();

      foreach ($newFiles as $i => $data) {
        $ext = $data["ext"];
        $tmp = $data["tmp"];

        $filename = "image_" . str_pad((string)$i, 2, "0", STR_PAD_LEFT) . "." . $ext;
        $destAbs = $serviceDirAbs . "/" . $filename;
        $destRel = "/uploads/services/" . $serviceId . "/" . $filename;

        if (is_file($destAbs)) {
          @unlink($destAbs);
        }

        if (!empty($newRel[$i]) && $newRel[$i] !== $destRel) {
          $oldAbs = absFromRel($newRel[$i]);
          if (is_file($oldAbs)) $toDeleteAbs[] = $oldAbs;
        }

        if (!@move_uploaded_file($tmp, $destAbs)) {
          throw new Exception("Failed to save image {$i}.");
        }

        $newRel[$i] = $destRel;
      }

      if (empty($newRel[1]) && empty($newRel[2]) && empty($newRel[3])) {
        throw new Exception("At least one image is required.");
      }

      $upd = $pdo->prepare("
        UPDATE services SET
          title=:t,
          category=:cat,
          subcategory=:sub,
          description=:d,
          price=:p,
          delivery_time=:dt,
          revisions_included=:r,
          image_1=:i1,
          image_2=:i2,
          image_3=:i3,
          status=:st,
          featured_status=:fs
        WHERE service_id=:id AND freelancer_id=:fid
      ");

      $upd->execute([
        ":t"=>$title,
        ":cat"=>$category,
        ":sub"=>$subcategory,
        ":d"=>$description,
        ":p"=>(float)$price,
        ":dt"=>(int)$delivery_time,
        ":r"=>(int)$revisions_included,
        ":i1"=>$newRel[1],
        ":i2"=>$newRel[2],
        ":i3"=>$newRel[3],
        ":st"=>$status,
        ":fs"=>$featuredWanted,
        ":id"=>$serviceId,
        ":fid"=>$freelancerId,
      ]);

      $pdo->commit();

      foreach ($toDeleteAbs as $p) {
        if (is_file($p)) @unlink($p);
      }

      $_SESSION["flash_success"] = "Service updated successfully.";
      header("Location: my-services.php");
      exit;

    } catch (Exception $ex) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $errors["save"] = "Update failed. " . $ex->getMessage();
    }
  }

  $service["title"] = $title;
  $service["category"] = $category;
  $service["subcategory"] = $subcategory;
  $service["description"] = $description;
  $service["delivery_time"] = $delivery_time;
  $service["revisions_included"] = $revisions_included;
  $service["price"] = $price;
  $service["status"] = $status;
  $service["featured_status"] = $featuredWanted;
  $service["image_1"] = $newRel[1];
  $service["image_2"] = $newRel[2];
  $service["image_3"] = $newRel[3];
}

include "includes/header.php";
?>

<div class="wrapper">
  <div class="container">
    <?php include "includes/nav.php"; ?>

    <main class="main-content">
      <div class="create-service-container">
        <div class="breadcrumb">
          <a href="index.php">Home</a><span class="breadcrumb-sep">></span>
          <a href="my-services.php">My Services</a><span class="breadcrumb-sep">></span>
          <span>Edit Service</span>
        </div>

        <div class="create-service-card">
          <h2>Edit Service</h2>

          <?php if (!empty($errors["save"])): ?>
            <div class="message-error"><?= h($errors["save"]) ?></div>
          <?php endif; ?>

          <?php if (!empty($errors["images"])): ?>
            <div class="message-error"><?= h($errors["images"]) ?></div>
          <?php endif; ?>

          <form class="form" method="post" enctype="multipart/form-data" novalidate>

            <div class="form-group">
              <label class="form-label" for="title">Service Title <span class="req">*</span></label>
              <input class="form-input <?= isset($errors["title"]) ? "input-error" : "" ?>"
                     type="text" id="title" name="title"
                     value="<?= h($service["title"]) ?>" minlength="10" maxlength="100" required>
              <?php if (isset($errors["title"])): ?><div class="error"><?= h($errors["title"]) ?></div><?php endif; ?>
            </div>

            <div class="form-group">
              <label class="form-label" for="category">Category <span class="req">*</span></label>
              <select class="form-select <?= isset($errors["category"]) ? "input-error" : "" ?>" id="category" name="category" required>
                <option value="">-- Select Category --</option>
                <?php foreach ($categories as $cat => $subs): ?>
                  <option value="<?= h($cat) ?>" <?= ($service["category"] === $cat) ? "selected" : "" ?>><?= h($cat) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if (isset($errors["category"])): ?><div class="error"><?= h($errors["category"]) ?></div><?php endif; ?>
            </div>

            <div class="form-group">
              <label class="form-label" for="subcategory">Subcategory <span class="req">*</span></label>
              <select class="form-select <?= isset($errors["subcategory"]) ? "input-error" : "" ?>" id="subcategory" name="subcategory" required>
                <option value="">-- Select Subcategory --</option>
                <?php foreach ($categories as $cat => $subs): ?>
                  <optgroup label="<?= h($cat) ?>">
                    <?php foreach ($subs as $sub): ?>
                      <option value="<?= h($sub) ?>" <?= ($service["subcategory"] === $sub) ? "selected" : "" ?>><?= h($sub) ?></option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endforeach; ?>
              </select>
              <?php if (isset($errors["subcategory"])): ?><div class="error"><?= h($errors["subcategory"]) ?></div><?php endif; ?>
            </div>

            <div class="form-group">
              <label class="form-label" for="description">Description <span class="req">*</span></label>
              <textarea class="form-input <?= isset($errors["description"]) ? "input-error" : "" ?>"
                        id="description" name="description"
                        minlength="100" maxlength="2000" required rows="6"><?= h($service["description"]) ?></textarea>
              <?php if (isset($errors["description"])): ?><div class="error"><?= h($errors["description"]) ?></div><?php endif; ?>
            </div>

            <div class="form-group">
              <label class="form-label" for="delivery_time">Delivery Time (days) <span class="req">*</span></label>
              <input class="form-input <?= isset($errors["delivery_time"]) ? "input-error" : "" ?>"
                     type="number" id="delivery_time" name="delivery_time"
                     min="1" max="90" step="1" required value="<?= h($service["delivery_time"]) ?>">
              <?php if (isset($errors["delivery_time"])): ?><div class="error"><?= h($errors["delivery_time"]) ?></div><?php endif; ?>
            </div>

            <div class="form-group">
              <label class="form-label" for="revisions_included">Revisions Included <span class="req">*</span></label>
              <input class="form-input <?= isset($errors["revisions_included"]) ? "input-error" : "" ?>"
                     type="number" id="revisions_included" name="revisions_included"
                     min="0" max="999" step="1" required value="<?= h($service["revisions_included"]) ?>">
              <?php if (isset($errors["revisions_included"])): ?><div class="error"><?= h($errors["revisions_included"]) ?></div><?php endif; ?>
            </div>

            <div class="form-group">
              <label class="form-label" for="price">Price (USD) <span class="req">*</span></label>
              <input class="form-input <?= isset($errors["price"]) ? "input-error" : "" ?>"
                     type="number" id="price" name="price"
                     min="5" max="10000" step="0.01" required value="<?= h($service["price"]) ?>">
              <?php if (isset($errors["price"])): ?><div class="error"><?= h($errors["price"]) ?></div><?php endif; ?>
            </div>

            <!-- Status -->
            <div class="form-group">
              <label class="form-label">Service Status <span class="req">*</span></label>
              <label style="display:inline-flex;align-items:center;gap:8px;margin-right:14px;">
                <input type="radio" name="status" value="Active" <?= (strtolower((string)$service["status"])==="active") ? "checked" : "" ?>>
                Active
              </label>
              <label style="display:inline-flex;align-items:center;gap:8px;">
                <input type="radio" name="status" value="Inactive" <?= (strtolower((string)$service["status"])==="inactive") ? "checked" : "" ?>>
                Inactive
              </label>
              <div class="form-hint">Inactive services are hidden from browse and cannot be featured.</div>
            </div>

            <!-- Featured -->
            <div class="form-group">
              <label class="form-label">Featured</label>
              <label style="display:inline-flex;align-items:center;gap:8px;">
                <input type="checkbox" name="featured_status" value="Yes"
                  <?= (strtolower((string)$service["featured_status"])==="yes") ? "checked" : "" ?>>
                Mark as Featured
              </label>
              <?php if (isset($errors["featured_status"])): ?><div class="error"><?= h($errors["featured_status"]) ?></div><?php endif; ?>
              <div class="form-hint">Max 3 featured services per freelancer. Only Active services can be featured.</div>
            </div>

            <!-- Images -->
            <div class="form-group">
              <label class="form-label">Images</label>

              <?php if (!empty($errors["images"])): ?>
                <div class="error"><?= h($errors["images"]) ?></div>
              <?php endif; ?>

              <div class="image-upload-grid">
                <?php for ($i=1; $i<=3; $i++):
                  $key = "image_" . $i;
                  $current = $service[$key] ?? "";
                ?>
                  <div class="image-upload-box">
                    <label class="form-label" for="<?= h($key) ?>">Replace Image <?= $i ?>
                      <?= ($i===1) ? "<span class='req'>*</span>" : "<span class='optional'>(optional)</span>" ?>
                    </label>

                    <input class="file-input-hidden <?= isset($errors[$key]) ? "input-error" : "" ?>"
                           type="file" id="<?= h($key) ?>" name="<?= h($key) ?>"
                           accept=".jpg,.jpeg,.png">

                    <?php if (isset($errors[$key])): ?><div class="error"><?= h($errors[$key]) ?></div><?php endif; ?>

                    <label class="btn btn-primary upload-btn" for="<?= h($key) ?>">
                      <span class="upload-icon" aria-hidden="true">⭳</span>
                      Upload Image
                    </label>

                    <?php if (!empty($current)): ?>
                      <div class="thumb-wrap">
                        <!-- ✅ FIX: safe join baseUrl + relative path -->
                        <img class="thumb-preview" src="<?= h(rtrim($baseUrl, "/") . "/" . ltrim($current, "/")) ?>" alt="Current image">

                        <?php if ($i !== 1): ?>
                          <button class="thumb-remove" type="submit" name="remove_image" value="<?= $i ?>"
                                  aria-label="Remove image <?= $i ?>">×</button>
                        <?php endif; ?>
                      </div>
                    <?php else: ?>
                      <div class="form-hint">No image in slot <?= $i ?>.</div>
                    <?php endif; ?>

                    <div class="form-hint">JPG/JPEG/PNG, ≤5MB, min 800×600.</div>
                  </div>
                <?php endfor; ?>
              </div>

              <div class="form-hint">At least one image must remain.</div>
            </div>

            <div class="form-actions">
              <a class="btn btn-secondary" href="my-services.php">Cancel</a>
              <button class="btn btn-primary" type="submit">Update Service</button>
            </div>
          </form>
        </div>
      </div>
    </main>

    <aside class="sidebar">
      <h3 class="heading-secondary">Sidebar</h3>
    </aside>
  </div>

  <?php include "includes/footer.php"; ?>
</div>