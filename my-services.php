<?php
require_once "includes/init.php";

$Title = "My Services";
$activePage = "my-services.php";

if (!isset($_SESSION["user"])) { header("Location: login.php"); exit; }

if (($_SESSION["user"]["role"] ?? "") !== "Freelancer") {
  $_SESSION["flash_error"] = "Only freelancers can access My Services.";
  header("Location: index.php");
  exit;
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$freelancerId = $_SESSION["user"]["id"];

if (!isset($baseUrl) || (string)$baseUrl === "") {
  $baseUrl = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"])), "/");
  $baseUrl = ($baseUrl === "" ? "/" : $baseUrl . "/");
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

function normalizeRelPath($p){
  $p = trim((string)$p);
  if ($p === "" || strtolower($p) === "null") return "";
  $p = str_replace("\\", "/", $p);
  if (preg_match('#^https?://#i', $p) || strpos($p, "data:") === 0) return $p;
  if (($pos = stripos($p, "uploads/")) !== false) return ltrim(substr($p, $pos), "/");
  return ltrim($p, "/");
}

function serviceImgSrc($baseUrl, $dbPath, $fallback){
  $rel = normalizeRelPath($dbPath);
  if ($rel === "") $rel = normalizeRelPath($fallback);

  if ($rel !== "" && (preg_match('#^https?://#i', $rel) || strpos($rel, "data:") === 0)) return $rel;

  $rel = ltrim($rel, "/");
  if ($rel !== "" && !is_file(__DIR__ . "/" . $rel)) {
    $fb = normalizeRelPath($fallback);
    if ($fb !== "" && (preg_match('#^https?://#i', $fb) || strpos($fb, "data:") === 0)) return $fb;
    $fb = ltrim($fb, "/");
    if ($fb !== "") $rel = $fb;
  }

  return rtrim($baseUrl, "/") . "/" . ltrim($rel, "/");
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"], $_POST["id"])) {
  $id = trim((string) $_POST["id"]);
  $action = (string) $_POST["action"];

  $check = $pdo->prepare("SELECT status FROM services WHERE service_id=:id AND freelancer_id=:fid");
  $check->execute([":id" => $id, ":fid" => $freelancerId]);
  $service = $check->fetch(PDO::FETCH_ASSOC);

  if (!$service) {
    $_SESSION["flash_error"] = "Access denied.";
    header("Location: my-services.php");
    exit;
  }

  if ($action === "deactivate") {
    $upd = $pdo->prepare("UPDATE services SET status='Inactive', featured_status='No' WHERE service_id=:id AND freelancer_id=:fid");
    $upd->execute([":id" => $id, ":fid" => $freelancerId]);
    $_SESSION["flash_success"] = "Service updated successfully.";
    header("Location: my-services.php");
    exit;
  }

  if ($action === "activate") {
    $upd = $pdo->prepare("UPDATE services SET status='Active' WHERE service_id=:id AND freelancer_id=:fid");
    $upd->execute([":id" => $id, ":fid" => $freelancerId]);
    $_SESSION["flash_success"] = "Service updated successfully.";
    header("Location: my-services.php");
    exit;
  }

  $_SESSION["flash_error"] = "Invalid action.";
  header("Location: my-services.php");
  exit;
}

$allowedSort = ["price", "created_date"];
$sort = $_GET["sort"] ?? "created_date";
$dir = strtolower($_GET["dir"] ?? "desc");

if (!in_array($sort, $allowedSort, true)) $sort = "created_date";
if ($dir !== "asc" && $dir !== "desc") $dir = "desc";

$makeSortLink = function ($label, $col) use ($sort, $dir) {
  $isActive = ($sort === $col);
  $nextDir = ($isActive && $dir === "asc") ? "desc" : "asc";

  $classes = "sort-link";
  if ($isActive) $classes .= " sort-active " . ($dir === "asc" ? "sort-asc" : "sort-desc");
  else $classes .= ($nextDir === "asc") ? " sort-next-asc" : " sort-next-desc";

  $href = "my-services.php?sort=" . urlencode($col) . "&dir=" . urlencode($nextDir);
  return "<a class='$classes' href='$href'>" . htmlspecialchars($label) . "</a>";
};

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE freelancer_id=:fid");
$totalStmt->execute([":fid" => $freelancerId]);
$total = (int) $totalStmt->fetchColumn();

$activeStmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE freelancer_id=:fid AND status='Active'");
$activeStmt->execute([":fid" => $freelancerId]);
$activeCount = (int) $activeStmt->fetchColumn();

$featuredStmt = $pdo->prepare("
  SELECT COUNT(*) 
  FROM services 
  WHERE freelancer_id=:fid 
    AND status='Active'
    AND (LOWER(featured_status)='yes' OR featured_status='1')
");
$featuredStmt->execute([":fid" => $freelancerId]);
$featuredCount = (int) $featuredStmt->fetchColumn();

$ordersCompleted = 0;

include "includes/header.php";
?>

<div class="wrapper">
  <div class="container">
    <?php include "includes/nav.php"; ?>

    <main class="main-content">
      <h1 class="heading-primary">My Services</h1>

      <?php if (isset($_GET["created"], $_GET["id"]) && $_GET["created"] === "1" && $_GET["id"] !== ""): ?>
        <div class="message-success">
          Service created successfully! Service ID: <?= h($_GET["id"]) ?><br>
          <a class="action-link" href="service-detail.php?id=<?= urlencode($_GET["id"]) ?>">View Service</a>
          &nbsp;|&nbsp;
          <a class="action-link" href="create-service.php">Create Another Service</a>
        </div>
      <?php endif; ?>

      <?php if (!empty($_SESSION["flash_success"])): ?>
        <div class="message-success"><?= h($_SESSION["flash_success"]); ?></div>
        <?php unset($_SESSION["flash_success"]); ?>
      <?php endif; ?>

      <?php if (!empty($_SESSION["flash_error"])): ?>
        <div class="message-error"><?= h($_SESSION["flash_error"]); ?></div>
        <?php unset($_SESSION["flash_error"]); ?>
      <?php endif; ?>

      <div class="stats-card">
        <div class="stats-grid">
          <div class="stat-box">
            <div class="stat-number"><?= $total ?></div>
            <div class="stat-label">Total Services</div>
          </div>
          <div class="stat-box stat-active">
            <div class="stat-number"><?= $activeCount ?></div>
            <div class="stat-label">Active Services</div>
          </div>
          <div class="stat-box stat-featured">
            <div class="stat-number"><?= $featuredCount ?>/3</div>
            <div class="stat-label">Featured Services</div>
          </div>
          <div class="stat-box">
            <div class="stat-number"><?= $ordersCompleted ?></div>
            <div class="stat-label">Orders (Completed)</div>
          </div>
        </div>
      </div>

      <p class="my-services-create">
        <a class="btn btn-primary" href="create-service.php">Create New Service</a>
      </p>

      <?php
      $sql = "SELECT service_id, title, category, price, status, featured_status, created_date, image_1
              FROM services
              WHERE freelancer_id = :fid
              ORDER BY $sort " . ($dir === "asc" ? "ASC" : "DESC");

      $stmt = $pdo->prepare($sql);
      $stmt->execute([":fid" => $freelancerId]);
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

      if ($rows):
      ?>
        <table class="services-table">
          <thead>
            <tr>
              <th>Image</th>
              <th>Service Title</th>
              <th>Category</th>
              <th><?= $makeSortLink("Price", "price") ?></th>
              <th>Status</th>
              <th>Featured</th>
              <th><?= $makeSortLink("Created Date", "created_date") ?></th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <?php
                $imgPath = (string)($row["image_1"] ?? "");
                $imgSrc = serviceImgSrc($baseUrl, $imgPath, "uploads/services/placeholder-service.png");

                $isActive = (strtolower((string) $row["status"]) === "active");
                $isFeatured = (strtolower((string) $row["featured_status"]) === "yes" || (string) $row["featured_status"] === "1");
                $featuredText = $isFeatured ? "<span class='featured-indicator'>Featured</span>" : "-";
              ?>
              <tr>
                <td>
                  <img class="service-thumb" src="<?= h($imgSrc) ?>" alt="Service">
                </td>
                <td>
                  <a class="action-link" href="service-detail.php?id=<?= urlencode($row["service_id"]) ?>">
                    <?= h($row["title"]) ?>
                  </a>
                </td>
                <td><?= h($row["category"]) ?></td>
                <td>$<?= h($row["price"]) ?></td>
                <td><?= h($row["status"]) ?></td>
                <td><?= $featuredText ?></td>
                <td><?= h($row["created_date"]) ?></td>
                <td>
                  <a class="action-link" href="edit-service.php?id=<?= urlencode($row["service_id"]) ?>">Edit</a>

                  <form method="post" action="my-services.php" class="inline-form">
                    <input type="hidden" name="id" value="<?= h($row["service_id"]) ?>">
                    <?php if ($isActive): ?>
                      <button class="btn btn-secondary btn-sm" type="submit" name="action" value="deactivate">Deactivate</button>
                    <?php else: ?>
                      <button class="btn btn-primary btn-sm" type="submit" name="action" value="activate">Activate</button>
                    <?php endif; ?>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="text-muted">No services yet. Click “Create New Service”.</p>
      <?php endif; ?>
    </main>

    <aside class="sidebar">
      <h3 class="heading-secondary">Sidebar</h3>
    </aside>
  </div>

  <?php include "includes/footer.php"; ?>
</div>
