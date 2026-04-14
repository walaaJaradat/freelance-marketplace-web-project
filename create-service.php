<?php
require_once "includes/init.php";

$Title = "Create New Service";
$activePage = "create-service.php";

if (!isset($_SESSION["user"])) { header("Location: login.php"); exit; }

if (($_SESSION["user"]["role"] ?? "") !== "Freelancer") {
  $_SESSION["flash_error"] = "Only freelancers can create services.";
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

if (!function_exists("h")) {
  function h($str){ return htmlspecialchars((string)$str, ENT_QUOTES, "UTF-8"); }
}

if (!function_exists("str_len")) {
  function str_len($s){
    $s = (string)$s;
    return function_exists("mb_strlen") ? mb_strlen($s, "UTF-8") : strlen($s);
  }
}

function sessionCreateKey(){ return "service_create"; }
function getCreateSession(){
  $k = sessionCreateKey();
  return (isset($_SESSION[$k]) && is_array($_SESSION[$k])) ? $_SESSION[$k] : [];
}
function setCreateSession($data){ $_SESSION[sessionCreateKey()] = $data; }

function clearCreateSessionAndTemp(){
  $k = sessionCreateKey();
  if (isset($_SESSION[$k]["temp_dir_abs"]) && is_dir($_SESSION[$k]["temp_dir_abs"])) {
    $dir = $_SESSION[$k]["temp_dir_abs"];
    $items = @scandir($dir);
    if ($items) {
      foreach ($items as $it) {
        if ($it === "." || $it === "..") continue;
        @unlink($dir . DIRECTORY_SEPARATOR . $it);
      }
    }
    @rmdir($dir);
  }
  unset($_SESSION[$k]);
}

function cleanupOldTempUploads($baseTempDirAbs, $maxAgeSeconds = 7200){
  if (!is_dir($baseTempDirAbs)) return;
  $users = @scandir($baseTempDirAbs);
  if (!$users) return;

  $now = time();
  foreach ($users as $u) {
    if ($u === "." || $u === "..") continue;
    $userDir = $baseTempDirAbs . DIRECTORY_SEPARATOR . $u;
    if (!is_dir($userDir)) continue;

    $files = @scandir($userDir);
    if (!$files) continue;

    foreach ($files as $f) {
      if ($f === "." || $f === "..") continue;
      $path = $userDir . DIRECTORY_SEPARATOR . $f;
      if (!is_file($path)) continue;

      $mtime = @filemtime($path);
      if ($mtime && ($now - $mtime) > $maxAgeSeconds) @unlink($path);
    }

    $left = @scandir($userDir);
    if ($left && count($left) <= 2) @rmdir($userDir);
  }
}

function isValidCategory($category, $categories){ return isset($categories[$category]); }
function isValidSubcategory($category, $subcategory, $categories){
  if (!isset($categories[$category])) return false;
  return in_array($subcategory, $categories[$category], true);
}

function normalizeMoney($val){
  $val = trim((string)$val);
  $val = str_replace([",", "$"], "", $val);
  return $val;
}

function validateStep1($post, $categories, $pdo, $freelancerId){
  $errors = [];

  $title = trim($post["title"] ?? "");
  $category = trim($post["category"] ?? "");
  $subcategory = trim($post["subcategory"] ?? "");
  $description = trim($post["description"] ?? "");
  $delivery_time = trim($post["delivery_time"] ?? "");
  $revisions_included = trim($post["revisions_included"] ?? "");
  $price = normalizeMoney($post["price"] ?? "");

  $status = ((string)($post["status"] ?? "Active") === "Inactive") ? "Inactive" : "Active";
  $featuredWanted = isset($post["featured_status"]) ? "Yes" : "No";

  if ($title === "") $errors["title"] = "Service title is required.";
  elseif (str_len($title) < 10 || str_len($title) > 100) $errors["title"] = "Title must be 10-100 characters.";

  if ($category === "") $errors["category"] = "Category is required.";
  elseif (!isValidCategory($category, $categories)) $errors["category"] = "Must be a valid category.";

  if ($subcategory === "") $errors["subcategory"] = "Subcategory is required.";
  elseif ($category !== "" && !isValidSubcategory($category, $subcategory, $categories)) {
    $errors["subcategory"] = "Must be a valid subcategory for selected category.";
  }

  if ($description === "") $errors["description"] = "Description is required.";
  elseif (str_len($description) < 100 || str_len($description) > 2000) $errors["description"] = "Description must be 100-2000 characters.";

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

  $stmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE freelancer_id = :fid AND status='Active'");
  $stmt->execute([":fid" => $freelancerId]);
  if ((int)$stmt->fetchColumn() >= 50) $errors["limit"] = "Maximum 50 active services allowed.";

  if (!isset($errors["title"])) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE freelancer_id=:fid AND title=:t");
    $stmt->execute([":fid"=>$freelancerId, ":t"=>$title]);
    if ((int)$stmt->fetchColumn() > 0) $errors["title"] = "Service title must be unique for this freelancer.";
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
      ");
      $cstmt->execute([":fid" => $freelancerId]);
      if ((int)$cstmt->fetchColumn() >= 3) $errors["featured_status"] = "Maximum of 3 featured services allowed.";
    }
  }

  $data = [
    "title" => $title,
    "category" => $category,
    "subcategory" => $subcategory,
    "description" => $description,
    "delivery_time" => $delivery_time,
    "revisions_included" => $revisions_included,
    "price" => $price,
    "status" => $status,
    "featured_status" => $featuredWanted,
  ];

  return [$errors, $data];
}

function uploadErrorText($code){
  $map = [
    UPLOAD_ERR_INI_SIZE => "File is too large (server limit).",
    UPLOAD_ERR_FORM_SIZE => "File is too large (form limit).",
    UPLOAD_ERR_PARTIAL => "Upload was interrupted. Please try again.",
    UPLOAD_ERR_NO_FILE => "No file selected.",
    UPLOAD_ERR_NO_TMP_DIR => "Server missing temp folder.",
    UPLOAD_ERR_CANT_WRITE => "Server cannot write file.",
    UPLOAD_ERR_EXTENSION => "Upload stopped by server extension.",
  ];
  return $map[$code] ?? "Unknown upload error.";
}

function validateAndStoreImagesStep2($files, $post, $tempDirAbs, $tempDirRelBase, $existingImages = []){
  $errors = [];
  $allowedExt = ["jpg", "jpeg", "png"];
  $allowedMime = ["image/jpeg", "image/png"];
  $maxSize = 5 * 1024 * 1024;
  $minW = 800;
  $minH = 600;

  $main = (int)($post["main_image"] ?? 1);
  if ($main < 1 || $main > 3) $main = 1;

  $inputs = [1 => "image_1", 2 => "image_2", 3 => "image_3"];

  if (!is_dir($tempDirAbs)) @mkdir($tempDirAbs, 0755, true);

  $finalImages = is_array($existingImages) ? $existingImages : [];
  $replacedOldAbs = [];
  $newlyUploadedAbs = [];

  $image1NoFile = (!isset($files["image_1"]) || ($files["image_1"]["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE);
  $hasExisting1 = (!empty($finalImages[1]["abs"]) && is_file($finalImages[1]["abs"]));
  if ($image1NoFile && !$hasExisting1) {
    $errors["image_1"] = "Service Image 1 is required.";
    return [$errors, $finalImages, $main, $replacedOldAbs, $newlyUploadedAbs];
  }

  foreach ($inputs as $idx => $key) {
    if (!isset($files[$key])) continue;
    $f = $files[$key];

    $err = (int)($f["error"] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_NO_FILE) continue;

    if ($err !== UPLOAD_ERR_OK) {
      $errors[$key] = "Image {$idx}: " . uploadErrorText($err);
      continue;
    }

    if (($f["size"] ?? 0) > $maxSize) {
      $errors[$key] = "Image {$idx} must be 5MB or less.";
      continue;
    }

    $original = $f["name"] ?? ("image_" . $idx);
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
      $errors[$key] = "Image {$idx} must be JPG, JPEG, or PNG.";
      continue;
    }

    $tmpName = (string)($f["tmp_name"] ?? "");
    if ($tmpName === "" || !is_uploaded_file($tmpName)) {
      $errors[$key] = "Image {$idx}: upload source is invalid.";
      continue;
    }

    $imgInfo = @getimagesize($tmpName);
    if (!$imgInfo) {
      $errors[$key] = "Invalid image file for image {$idx}.";
      continue;
    }

    $mime = (string)($imgInfo["mime"] ?? "");
    if ($mime !== "" && !in_array($mime, $allowedMime, true)) {
      $errors[$key] = "Image {$idx} must be a real JPG/PNG image.";
      continue;
    }

    $w = (int)$imgInfo[0];
    $h = (int)$imgInfo[1];
    if ($w < $minW || $h < $minH) {
      $errors[$key] = "Image {$idx} must be at least {$minW}x{$minH}px.";
      continue;
    }

    $safeToken = bin2hex(random_bytes(6));
    $filename = "tmp_" . time() . "_" . $idx . "_" . $safeToken . "." . $ext;
    $destAbs = rtrim($tempDirAbs, "/\\") . DIRECTORY_SEPARATOR . $filename;

    if (!@move_uploaded_file($tmpName, $destAbs)) {
      $errors[$key] = "Failed to save image {$idx}.";
      continue;
    }

    $destRel = rtrim($tempDirRelBase, "/") . "/" . $filename;

    if (!empty($finalImages[$idx]["abs"]) && is_file($finalImages[$idx]["abs"])) $replacedOldAbs[] = $finalImages[$idx]["abs"];

    $finalImages[$idx] = ["abs"=>$destAbs, "rel"=>$destRel, "ext"=>$ext, "original"=>$original];
    $newlyUploadedAbs[] = $destAbs;
  }

  $countAny = 0;
  foreach ([1,2,3] as $i) if (!empty($finalImages[$i]["abs"]) && is_file($finalImages[$i]["abs"])) $countAny++;
  if ($countAny < 1) $errors["images"] = "Minimum 1 image required.";

  if (empty($finalImages[$main]["abs"]) || !is_file($finalImages[$main]["abs"])) {
    foreach ([1,2,3] as $i) {
      if (!empty($finalImages[$i]["abs"]) && is_file($finalImages[$i]["abs"])) { $main = $i; break; }
    }
  }

  return [$errors, $finalImages, $main, $replacedOldAbs, $newlyUploadedAbs];
}

function generateUniqueServiceId($pdo){
  for ($i=0; $i<30; $i++) {
    $id = (string)random_int(1000000000, 9999999999);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE service_id = :id");
    $stmt->execute([":id" => $id]);
    if ((int)$stmt->fetchColumn() === 0) return $id;
  }
  return (string)time() . (string)random_int(10, 99);
}

$projectRootAbs = rtrim(__DIR__, "/\\");
$uploadsBaseAbs = $projectRootAbs . "/uploads";
$tempBaseAbs = $uploadsBaseAbs . "/tmp/services_create";
cleanupOldTempUploads($tempBaseAbs, 7200);


$step = (int)($_GET["step"] ?? 1);
if ($step < 1 || $step > 3) $step = 1;

$message = "";
$errors = [];
$create = getCreateSession();

$showCancelConfirm = false;
if (isset($_GET["cancel"]) && $_GET["cancel"] === "1") {
  $showCancelConfirm = true;
  if ($_SERVER["REQUEST_METHOD"] === "POST" && (($_POST["confirm_cancel"] ?? "") === "yes")) {
    clearCreateSessionAndTemp();
    $_SESSION["flash_success"] = "Service creation cancelled.";
    header("Location: my-services.php");
    exit;
  }
}

if (!$showCancelConfirm) {
  if ($step > 1 && empty($create["step1"])) {
    $step = 1;
    $message = "Your creation session was cleared. Please start again.";
  }
  if ($step > 2 && empty($create["step2"])) {
    $step = 2;
    $message = "Please upload images to continue.";
  }
}

if (!$showCancelConfirm && $_SERVER["REQUEST_METHOD"] === "POST") {
  $action = $_POST["action"] ?? "";

  if ($action === "back_to_1") { header("Location: create-service.php?step=1"); exit; }
  if ($action === "back_to_2") { header("Location: create-service.php?step=2"); exit; }

  if ($action === "step1_submit") {
    [$errors, $data] = validateStep1($_POST, $categories, $pdo, $freelancerId);

    if (empty($errors)) {
      $create["step1"] = $data;

      $userTempAbs = $tempBaseAbs . "/" . $freelancerId;
      $create["temp_dir_abs"] = $userTempAbs;
$create["temp_dir_rel_base"] = "uploads/tmp/services_create/" . $freelancerId;

      setCreateSession($create);
      header("Location: create-service.php?step=2");
      exit;
    }
  }

  if ($action === "step2_submit") {
    if (empty($create["step1"])) { header("Location: create-service.php?step=1"); exit; }

    $tempDirAbs = $create["temp_dir_abs"] ?? ($tempBaseAbs . "/" . $freelancerId);
    $tempDirRel = $create["temp_dir_rel_base"] ?? ("/uploads/tmp/services_create/" . $freelancerId);

    if (isset($_POST["remove_image"])) {
      $idx = (int)$_POST["remove_image"];
      if ($idx >= 1 && $idx <= 3 && !empty($create["step2"]["images"][$idx]["abs"])) {
        $abs = $create["step2"]["images"][$idx]["abs"];
        if (is_file($abs)) @unlink($abs);
        unset($create["step2"]["images"][$idx]);

        $currentMain = (int)($create["step2"]["main"] ?? 1);
        if ($currentMain === $idx) {
          $newMain = 1;
          foreach ([1,2,3] as $i) {
            if (!empty($create["step2"]["images"][$i]["abs"]) && is_file($create["step2"]["images"][$i]["abs"])) { $newMain = $i; break; }
          }
          $create["step2"]["main"] = $newMain;
        }
        setCreateSession($create);
      }
      header("Location: create-service.php?step=2");
      exit;
    }

    $existingImages = $create["step2"]["images"] ?? [];

    [$errors, $finalImages, $main, $replacedOldAbs, $newUploadedAbs] =
      validateAndStoreImagesStep2($_FILES, $_POST, $tempDirAbs, $tempDirRel, $existingImages);

    if (empty($errors)) {
      foreach ($replacedOldAbs as $oldAbs) if (is_file($oldAbs)) @unlink($oldAbs);
      $create["step2"] = ["images" => $finalImages, "main" => $main];
      setCreateSession($create);
      header("Location: create-service.php?step=3");
      exit;
    } else {
      foreach ($newUploadedAbs as $abs) if (is_file($abs)) @unlink($abs);
    }
  }

  if ($action === "step3_confirm") {
    if (empty($create["step1"]) || empty($create["step2"]["images"])) {
      header("Location: create-service.php?step=1");
      exit;
    }

    try {
      $pdo->beginTransaction();

      $serviceId = generateUniqueServiceId($pdo);

      $finalDirAbs = $uploadsBaseAbs . "/services/" . $serviceId;
$finalDirRel = "uploads/services/" . $serviceId;

      if (!is_dir($finalDirAbs)) {
        if (!@mkdir($finalDirAbs, 0755, true)) throw new Exception("Failed to create service folder.");
      }

      $images = $create["step2"]["images"];
      $main = (int)($create["step2"]["main"] ?? 1);

      $ordered = [];
      if (isset($images[$main])) $ordered[] = $images[$main];
      $keys = array_keys($images); sort($keys);
      foreach ($keys as $k) { if ((int)$k === $main) continue; $ordered[] = $images[$k]; }

      $paths = [null, null, null];
      for ($i=0; $i<count($ordered) && $i<3; $i++) {
        $ext = $ordered[$i]["ext"];
        $srcAbs = $ordered[$i]["abs"];

        $name = "image_" . str_pad((string)($i+1), 2, "0", STR_PAD_LEFT) . "." . $ext;
        $destAbs = $finalDirAbs . "/" . $name;
        $destRel = $finalDirRel . "/" . $name;

        if (!is_file($srcAbs)) throw new Exception("Temporary image missing. Please re-upload.");

        if (!@rename($srcAbs, $destAbs)) {
          if (!@copy($srcAbs, $destAbs)) throw new Exception("Failed to move images.");
          @unlink($srcAbs);
        }

        $paths[$i] = $destRel;
      }

      if (!$paths[0]) throw new Exception("Main image missing after processing.");

      $s1 = $create["step1"];

      $finalStatus = ((string)($s1["status"] ?? "Active") === "Inactive") ? "Inactive" : "Active";
      $finalFeatured = ((string)($s1["featured_status"] ?? "No") === "Yes") ? "Yes" : "No";
      if ($finalStatus === "Inactive") $finalFeatured = "No";

      $stmt = $pdo->prepare("
        INSERT INTO services
        (service_id, freelancer_id, title, category, subcategory, description, price, delivery_time, revisions_included,
         image_1, image_2, image_3, status, featured_status)
        VALUES
        (:id, :fid, :t, :cat, :sub, :d, :p, :dt, :r, :i1, :i2, :i3, :st, :fs)
      ");
      $stmt->execute([
        ":id" => $serviceId,
        ":fid" => $freelancerId,
        ":t"   => $s1["title"],
        ":cat" => $s1["category"],
        ":sub" => $s1["subcategory"],
        ":d"   => $s1["description"],
        ":p"   => (float)$s1["price"],
        ":dt"  => (int)$s1["delivery_time"],
        ":r"   => (int)$s1["revisions_included"],
        ":i1"  => $paths[0],
        ":i2"  => $paths[1],
        ":i3"  => $paths[2],
        ":st"  => $finalStatus,
        ":fs"  => $finalFeatured,
      ]);

      $pdo->commit();
      clearCreateSessionAndTemp();

      $_SESSION["flash_success"] = "Service created successfully!";
      header("Location: my-services.php?created=1&id=" . urlencode($serviceId));
      exit;

    } catch (Exception $ex) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $errors["save"] = "Could not create service. " . $ex->getMessage();
    }
  }
}

$step1 = $create["step1"] ?? [];
function oldStep1($key, $step1){
  if (isset($_POST[$key])) return trim((string)$_POST[$key]);
  return isset($step1[$key]) ? (string)$step1[$key] : "";
}
$step2 = $create["step2"] ?? [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title><?= h($Title) ?></title>
  <link rel="stylesheet" href="<?= h($baseUrl) ?>css/styles.css">
</head>
<body>
<div class="wrapper">
  <?php require_once "includes/header.php"; ?>

  <div class="container">
    <?php require_once "includes/nav.php"; ?>

    <main class="main-content">
      <div class="create-service-container">

        <div class="breadcrumb">
          <a href="dashboard.php">Home</a><span class="breadcrumb-sep">></span>
          <a href="my-services.php">My Services</a><span class="breadcrumb-sep">></span>
          <span>Create New Service</span>
        </div>

        <?php
          $cls1 = ($step === 1) ? "progress-step-active" : ($step > 1 ? "progress-step-done" : "");
          $cls2 = ($step === 2) ? "progress-step-active" : ($step > 2 ? "progress-step-done" : "");
          $cls3 = ($step === 3) ? "progress-step-active" : "";
        ?>

        <div class="progress-nav">
          <?php if ($step > 1): ?>
            <a class="progress-step <?= $cls1 ?> progress-step-link" href="create-service.php?step=1">
              <span class="step-left">
                <span class="step-number">1</span>
                <span class="step-title">Basic Info</span>
              </span>
              <span class="step-right">Step 1</span>
            </a>
          <?php else: ?>
            <div class="progress-step <?= $cls1 ?>">
              <span class="step-left">
                <span class="step-number">1</span>
                <span class="step-title">Basic Info</span>
              </span>
              <span class="step-right">Step 1</span>
            </div>
          <?php endif; ?>

          <?php if ($step > 2): ?>
            <a class="progress-step <?= $cls2 ?> progress-step-link" href="create-service.php?step=2">
              <span class="step-left">
                <span class="step-number">2</span>
                <span class="step-title">Upload Images</span>
              </span>
              <span class="step-right">Step 2</span>
            </a>
          <?php else: ?>
            <div class="progress-step <?= $cls2 ?>">
              <span class="step-left">
                <span class="step-number">2</span>
                <span class="step-title">Upload Images</span>
              </span>
              <span class="step-right">Step 2</span>
            </div>
          <?php endif; ?>

          <div class="progress-step <?= $cls3 ?>">
            <span class="step-left">
              <span class="step-number">3</span>
              <span class="step-title">Review</span>
            </span>
            <span class="step-right">Step 3</span>
          </div>
        </div>

        <?php if ($message): ?>
          <div class="message-error"><?= h($message) ?></div>
        <?php endif; ?>

        <?php if ($showCancelConfirm): ?>
          <div class="create-service-card">
            <h2>Cancel Service Creation</h2>
            <p class="text-muted cancel-hint">This will clear your saved data and temporary images. Are you sure?</p>

            <form class="form" method="post" action="create-service.php?cancel=1">
              <input type="hidden" name="confirm_cancel" value="yes">
              <div class="form-actions">
                <a class="btn btn-secondary" href="create-service.php?step=<?= (int)($_GET["back_step"] ?? $step) ?>">Back</a>
                <button class="btn btn-primary" type="submit">Confirm Cancel</button>
              </div>
            </form>
          </div>

        <?php elseif ($step === 1): ?>
          <div class="create-service-card">
            <h2>Create New Service - Step 1</h2>

            <?php if (!empty($errors["limit"])): ?>
              <div class="message-error"><?= h($errors["limit"]) ?></div>
            <?php endif; ?>

            <form class="form" method="post" action="create-service.php?step=1" novalidate>
              <input type="hidden" name="action" value="step1_submit">

              <div class="form-group">
                <label class="form-label" for="title">Service Title <span class="req">*</span></label>
                <input class="form-input <?= isset($errors["title"]) ? "input-error" : "" ?>"
                       type="text" id="title" name="title"
                       value="<?= h(oldStep1("title", $step1)) ?>"
                       minlength="10" maxlength="100" required
                       placeholder="e.g. I will build your WordPress website">
                <?php if (isset($errors["title"])): ?><div class="error"><?= h($errors["title"]) ?></div><?php endif; ?>
              </div>

              <div class="form-group">
                <label class="form-label" for="category">Category <span class="req">*</span></label>
                <select class="form-select <?= isset($errors["category"]) ? "input-error" : "" ?>"
                        id="category" name="category" required>
                  <option value="">-- Select Category --</option>
                  <?php foreach ($categories as $cat => $subs): ?>
                    <option value="<?= h($cat) ?>" <?= oldStep1("category", $step1) === $cat ? "selected" : "" ?>>
                      <?= h($cat) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <?php if (isset($errors["category"])): ?><div class="error"><?= h($errors["category"]) ?></div><?php endif; ?>
              </div>

              <div class="form-group">
                <label class="form-label" for="subcategory">Subcategory <span class="req">*</span></label>
                <select class="form-select <?= isset($errors["subcategory"]) ? "input-error" : "" ?>"
                        id="subcategory" name="subcategory" required>
                  <option value="">-- Select Subcategory --</option>
                  <?php foreach ($categories as $cat => $subs): ?>
                    <optgroup label="<?= h($cat) ?>">
                      <?php foreach ($subs as $sub): ?>
                        <option value="<?= h($sub) ?>" <?= oldStep1("subcategory", $step1) === $sub ? "selected" : "" ?>>
                          <?= h($sub) ?>
                        </option>
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
                          minlength="100" maxlength="2000" required rows="6"
                          placeholder="Write a detailed description (100–2000 chars)"><?= h(oldStep1("description", $step1)) ?></textarea>
                <?php if (isset($errors["description"])): ?><div class="error"><?= h($errors["description"]) ?></div><?php endif; ?>
              </div>

              <div class="form-group">
                <label class="form-label" for="delivery_time">Delivery Time (days) <span class="req">*</span></label>
                <input class="form-input <?= isset($errors["delivery_time"]) ? "input-error" : "" ?>"
                       type="number" id="delivery_time" name="delivery_time"
                       min="1" max="90" step="1" required
                       value="<?= h(oldStep1("delivery_time", $step1)) ?>"
                       placeholder="1 - 90">
                <?php if (isset($errors["delivery_time"])): ?><div class="error"><?= h($errors["delivery_time"]) ?></div><?php endif; ?>
              </div>

              <div class="form-group">
                <label class="form-label" for="revisions_included">Revisions Included <span class="req">*</span></label>
                <input class="form-input <?= isset($errors["revisions_included"]) ? "input-error" : "" ?>"
                       type="number" id="revisions_included" name="revisions_included"
                       min="0" max="999" step="1" required
                       value="<?= h(oldStep1("revisions_included", $step1)) ?>"
                       placeholder="0 - 999 (999 = unlimited)">
                <?php if (isset($errors["revisions_included"])): ?><div class="error"><?= h($errors["revisions_included"]) ?></div><?php endif; ?>
              </div>

              <div class="form-group">
                <label class="form-label" for="price">Price (USD) <span class="req">*</span></label>
                <input class="form-input <?= isset($errors["price"]) ? "input-error" : "" ?>"
                       type="number" id="price" name="price"
                       min="5" max="10000" step="0.01" required
                       value="<?= h(oldStep1("price", $step1)) ?>"
                       placeholder="5 - 10000">
                <?php if (isset($errors["price"])): ?><div class="error"><?= h($errors["price"]) ?></div><?php endif; ?>
              </div>

              <div class="form-group">
                <label class="form-label">Service Status <span class="req">*</span></label>
                <label class="inline-choice">
                  <input type="radio" name="status" value="Active" <?= (oldStep1("status",$step1) !== "Inactive") ? "checked" : "" ?>>
                  Active
                </label>
                <label class="inline-choice">
                  <input type="radio" name="status" value="Inactive" <?= (oldStep1("status",$step1) === "Inactive") ? "checked" : "" ?>>
                  Inactive
                </label>
                <div class="form-hint">Inactive services are hidden from browse and cannot be featured.</div>
              </div>

              <div class="form-group">
                <label class="form-label">Featured</label>
                <label class="inline-choice">
                  <input type="checkbox" name="featured_status" value="Yes"
                    <?= (oldStep1("featured_status",$step1) === "Yes" || (isset($_POST["featured_status"]) && $_POST["featured_status"] === "Yes")) ? "checked" : "" ?>>
                  Mark as Featured
                </label>
                <?php if (isset($errors["featured_status"])): ?><div class="error"><?= h($errors["featured_status"]) ?></div><?php endif; ?>
                <div class="form-hint">Max 3 featured services per freelancer. Only Active services can be featured.</div>
              </div>

              <div class="form-actions">
                <a class="btn btn-secondary" href="create-service.php?cancel=1&back_step=1">Cancel</a>
                <button class="btn btn-primary" type="submit">Next: Upload Images</button>
              </div>
            </form>
          </div>

        <?php elseif ($step === 2): ?>
          <div class="create-service-card">
            <h2>Create New Service - Step 2</h2>

            <?php if (!empty($errors["images"])): ?>
              <div class="message-error"><?= h($errors["images"]) ?></div>
            <?php endif; ?>

            <form class="form" method="post" action="create-service.php?step=2" enctype="multipart/form-data" novalidate>
              <div class="image-upload-grid">
                <?php for ($i=1; $i<=3; $i++):
                  $key = "image_" . $i;
                  $isRequired = ($i === 1);
                  $hasImg = !empty($step2["images"][$i]["rel"]);
                  $isMain = ((int)($step2["main"] ?? 1) === $i);
                ?>
                <div class="image-upload-box">

                  <label class="form-label" for="<?= h($key) ?>">
                    Service Image <?= $i ?>
                    <?php if ($isRequired): ?><span class="req">*</span>
                    <?php else: ?><span class="optional">(optional)</span>
                    <?php endif; ?>
                  </label>

                  <input class="file-input-hidden <?= isset($errors[$key]) ? "input-error" : "" ?>"
                         type="file" id="<?= h($key) ?>" name="<?= h($key) ?>"
                         accept=".jpg,.jpeg,.png"
                         <?= (!$hasImg && $isRequired) ? "required" : "" ?>>

                  <div class="upload-feedback" id="fb_<?= h($key) ?>"></div>

                  <?php if (isset($errors[$key])): ?>
                    <div class="error"><?= h($errors[$key]) ?></div>
                  <?php endif; ?>

                  <label class="btn btn-primary upload-btn" for="<?= h($key) ?>">
                    <span class="upload-icon" aria-hidden="true">⭳</span>
                    Upload Image
                  </label>

                  <?php if ($hasImg): ?>
                    <div class="thumb-wrap" aria-label="Uploaded image <?= $i ?>">
                      <img class="thumb-preview"
                           src="<?= h($baseUrl) . ltrim($step2["images"][$i]["rel"], "/") ?>"
                           alt="Service image <?= $i ?>">

                      <?php if ($isMain): ?><div class="main-star" title="Main Image">★</div><?php endif; ?>

                      <button class="thumb-remove" type="submit" name="remove_image" value="<?= $i ?>" aria-label="Remove image <?= $i ?>">×</button>

                      <div class="thumb-radio">
                        <input type="radio" name="main_image" value="<?= $i ?>" <?= $isMain ? "checked" : "" ?>>
                        <span>Main</span>
                      </div>
                    </div>
                  <?php else: ?>
                    <div class="form-hint">JPG/JPEG/PNG, ≤5MB, min 800×600.</div>
                  <?php endif; ?>

                </div>
                <?php endfor; ?>
              </div>

              <div class="form-hint">
                Upload 1–3 images. One must be selected as main (default is 1).
              </div>

              <div class="form-actions">
                <a class="btn btn-secondary" href="create-service.php?cancel=1&back_step=2">Cancel</a>
                <button class="btn btn-secondary" type="submit" name="action" value="back_to_1">Back</button>
                <button class="btn btn-primary" type="submit" name="action" value="step2_submit">Next: Review</button>
              </div>
            </form>
          </div>

          <script>
            (function(){
              var maxSize = 5 * 1024 * 1024;
              var minW = 800, minH = 600;

              function humanSize(bytes){
                var units = ["B","KB","MB","GB"];
                var i = 0;
                var b = bytes;
                while (b >= 1024 && i < units.length-1) { b = b/1024; i++; }
                return (Math.round(b*10)/10) + " " + units[i];
              }

              function setMsg(el, msg, type){
                el.textContent = msg;
                el.className = "upload-feedback " + (type ? ("upload-" + type) : "");
              }

              function handleInput(inputId){
                var inp = document.getElementById(inputId);
                var fb = document.getElementById("fb_" + inputId);
                if (!inp || !fb) return;

                inp.addEventListener("change", function(){
                  if (!inp.files || !inp.files[0]) {
                    setMsg(fb, "", "");
                    return;
                  }

                  var f = inp.files[0];
                  var name = (f.name || "").toLowerCase();
                  var okExt = (name.endsWith(".jpg") || name.endsWith(".jpeg") || name.endsWith(".png"));
                  if (!okExt) { setMsg(fb, "Invalid file type. Please upload JPG/JPEG/PNG.", "error"); inp.value = ""; return; }
                  if (f.size > maxSize) { setMsg(fb, "File is too large: " + humanSize(f.size) + " (max 5MB).", "error"); inp.value = ""; return; }

                  var url = URL.createObjectURL(f);
                  var img = new Image();
                  img.onload = function(){
                    if (img.width < minW || img.height < minH) {
                      setMsg(fb, "Image is too small: " + img.width + "×" + img.height + " (min 800×600).", "error");
                      inp.value = "";
                      URL.revokeObjectURL(url);
                      return;
                    }
                    setMsg(fb, "Selected: " + f.name + " (" + humanSize(f.size) + "), " + img.width + "×" + img.height, "ok");
                    URL.revokeObjectURL(url);
                  };
                  img.onerror = function(){
                    setMsg(fb, "Cannot read this file as an image.", "error");
                    inp.value = "";
                    URL.revokeObjectURL(url);
                  };
                  img.src = url;
                });
              }

              handleInput("image_1");
              handleInput("image_2");
              handleInput("image_3");
            })();
          </script>

        <?php else: ?>
          <div class="create-service-card">
            <h2>Create New Service - Step 3</h2>

            <?php if (!empty($errors["save"])): ?>
              <div class="message-error"><?= h($errors["save"]) ?></div>
            <?php endif; ?>

            <?php
              $s1 = $create["step1"];
              $imgs = $create["step2"]["images"] ?? [];
              $main = (int)($create["step2"]["main"] ?? 1);
            ?>

            <div class="review-grid">
              <div class="review-box">
                <h3 class="review-title">Service Details</h3>
                <div class="kv">
                  <div class="k">Title</div><div class="v"><?= h($s1["title"]) ?></div>
                  <div class="k">Category</div><div class="v"><?= h($s1["category"]) ?></div>
                  <div class="k">Subcategory</div><div class="v"><?= h($s1["subcategory"]) ?></div>
                  <div class="k">Delivery</div><div class="v"><?= h($s1["delivery_time"]) ?> days</div>
                  <div class="k">Revisions</div><div class="v"><?= ((int)$s1["revisions_included"] === 999) ? "Unlimited" : h($s1["revisions_included"]) ?></div>
                  <div class="k">Price</div><div class="v">$<?= h(number_format((float)$s1["price"], 2)) ?></div>
                  <div class="k">Status</div><div class="v"><?= h($s1["status"] ?? "Active") ?></div>
                  <div class="k">Featured</div><div class="v"><?= h($s1["featured_status"] ?? "No") ?></div>
                </div>

                <div class="review-desc">
                  <div class="review-subtitle">Description</div>
                  <div class="review-text"><?= nl2br(h($s1["description"])) ?></div>
                </div>
              </div>

              <div class="review-box">
                <h3 class="review-title">Images</h3>
                <div class="thumbnail-row">
                  <?php
                    $keys = array_keys($imgs);
                    sort($keys);
                    foreach ($keys as $k):
                      $rel = $imgs[$k]["rel"] ?? "";
                      if (!$rel) continue;
                      $isMain = ((int)$k === $main);
                  ?>
                    <div class="thumbnail-item">
                      <div class="thumb-wrap">
                        <img src="<?= h($baseUrl) . ltrim($rel, "/") ?>" alt="Service image">
                        <?php if ($isMain): ?><div class="main-star">★</div><?php endif; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

            <form class="form" method="post" action="create-service.php?step=3" novalidate>
              <div class="form-actions">
                <a class="btn btn-secondary" href="create-service.php?cancel=1&back_step=3">Cancel</a>
                <button class="btn btn-secondary" type="submit" name="action" value="back_to_2">Back</button>
                <button class="btn btn-primary" type="submit" name="action" value="step3_confirm">Publish Service</button>
              </div>
            </form>
          </div>
        <?php endif; ?>

      </div>
    </main>
  </div>

  <?php require_once "includes/footer.php"; ?>
</div>
</body>
</html>
