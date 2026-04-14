<?php
require_once __DIR__ . "/includes/init.php";

$Title = "Service Details";
$activePage = "";

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


if (!isset($baseUrl) || (string)$baseUrl === "") {
  $baseUrl = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"])), "/");
  $baseUrl = ($baseUrl === "" ? "/" : $baseUrl . "/");
}

$serviceId = trim((string)($_GET["id"] ?? ""));
if ($serviceId === "") {
  $_SESSION["flash_error"] = "Missing service id.";
  header("Location: browse-services.php");
  exit;
}

function isExternalOrData($p): bool {
  $p = (string)$p;
  return (preg_match('#^https?://#i', $p) || strpos($p, "data:") === 0);
}

function mediaRel($path, string $filenameDir = ""): string {
  $p = trim((string)$path);
  if ($p === "" || strtolower($p) === "null") return "";

  $p = str_replace("\\", "/", $p);

  if (isExternalOrData($p)) return $p;

  if (($pos = stripos($p, "uploads/")) !== false) return ltrim(substr($p, $pos), "/");
  if (($pos = stripos($p, "assets/"))  !== false) return ltrim(substr($p, $pos), "/");

  if ($filenameDir !== "" && strpos($p, "/") === false) {
    return trim($filenameDir, "/") . "/" . ltrim($p, "/");
  }

  return ltrim($p, "/");
}

function pickExistingRel(array $rels): string {
  foreach ($rels as $r) {
    $r = ltrim((string)$r, "/");
    if ($r !== "" && is_file(__DIR__ . "/" . $r)) return $r;
  }
  return "";
}

function img(string $baseUrl, $dbPath, $fallbackRel, string $filenameDir = "", bool $swapProfileDirs = false): string {
  $rel = mediaRel($dbPath, $filenameDir);
  if ($rel === "") $rel = mediaRel($fallbackRel);

  if ($rel !== "" && isExternalOrData($rel)) return $rel;

  $rel = ltrim($rel, "/");

  if ($swapProfileDirs && $rel !== "" && !is_file(__DIR__ . "/" . $rel)) {
    if (strpos($rel, "uploads/profile-photo/") === 0) {
      $alt = "uploads/profiles/" . substr($rel, strlen("uploads/profile-photo/"));
      if (is_file(__DIR__ . "/" . $alt)) $rel = $alt;
    } elseif (strpos($rel, "uploads/profiles/") === 0) {
      $alt = "uploads/profile-photo/" . substr($rel, strlen("uploads/profiles/"));
      if (is_file(__DIR__ . "/" . $alt)) $rel = $alt;
    }
  }

  if ($rel !== "" && !is_file(__DIR__ . "/" . $rel)) {
    $fb = mediaRel($fallbackRel);
    if ($fb !== "" && isExternalOrData($fb)) return $fb;
    $fb = ltrim($fb, "/");
    if ($fb !== "") $rel = $fb;
  }

  $baseUrl = trim((string)$baseUrl);
  if ($baseUrl === "") {
    return $rel;
  }

  return rtrim($baseUrl, "/") . "/" . ltrim($rel, "/");
}

$servicePlaceholderRel = pickExistingRel([
  "uploads/services/placeholder-service.png"
]);

if ($servicePlaceholderRel === "") {
  $servicePlaceholderRel = 'data:image/svg+xml;utf8,' . rawurlencode('
    <svg xmlns="http://www.w3.org/2000/svg" width="600" height="400">
      <rect width="600" height="400" fill="#F1F3F5"/>
      <rect x="40" y="40" width="520" height="320" rx="18" fill="#E9ECEF"/>
      <circle cx="220" cy="190" r="55" fill="#CED4DA"/>
      <path d="M160 305l90-85 65 55 95-110 90 140H160z" fill="#ADB5BD"/>
    </svg>
  ');
}

$userPlaceholderRel = pickExistingRel([
  "uploads/profile-photo/user-default.jpg",
  "uploads/profiles/user-default.jpg",
]);

if ($userPlaceholderRel === "") {
  $userPlaceholderRel = 'data:image/svg+xml;utf8,' . rawurlencode('
    <svg xmlns="http://www.w3.org/2000/svg" width="120" height="120">
      <rect width="120" height="120" rx="60" fill="#E9ECEF"/>
      <circle cx="60" cy="48" r="22" fill="#ADB5BD"/>
      <path d="M20 110c7-22 26-34 40-34s33 12 40 34" fill="#ADB5BD"/>
    </svg>
  ');
}

$cookieName = "recent_services";
$recent = [];

if (!empty($_COOKIE[$cookieName])) {
  $parts = explode(",", (string)$_COOKIE[$cookieName]);
  foreach ($parts as $p) {
    $p = trim($p);
    if ($p !== "" && !in_array($p, $recent, true)) $recent[] = $p;
  }
}

$recent = array_values(array_filter($recent, function($x) use ($serviceId) {
  return $x !== $serviceId;
}));$recent[] = $serviceId;

if (count($recent) > 8) $recent = array_slice($recent, -8);
setcookie($cookieName, implode(",", $recent), time() + (30 * 24 * 60 * 60), "/");

$stmt = $pdo->prepare("
  SELECT
    s.*,
    CONCAT(u.first_name,' ',u.last_name) AS freelancer_name,
    u.profile_photo AS freelancer_photo,
    u.registration_date AS freelancer_since
  FROM services s
  LEFT JOIN users u ON u.user_id = s.freelancer_id
  WHERE s.service_id = :id
");
$stmt->execute([":id" => $serviceId]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
  $_SESSION["flash_error"] = "Service not found.";
  header("Location: browse-services.php");
  exit;
}

$status = strtolower((string)$service["status"]);
$isLoggedIn = isset($_SESSION["user"]);
$role = $isLoggedIn ? (string)($_SESSION["user"]["role"] ?? "") : "Guest";

$isOwner = false;
if ($isLoggedIn && $role === "Freelancer") {
  $isOwner = ((string)($_SESSION["user"]["id"] ?? "") === (string)$service["freelancer_id"]);
}

if ($status !== "active" && !$isOwner) {
  $_SESSION["flash_error"] = "Service no longer available.";
  header("Location: browse-services.php");
  exit;
}

try {
  $col = $pdo->prepare("
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'services'
      AND COLUMN_NAME = 'view_count'
  ");
  $col->execute();
  $hasViewCount = ((int)$col->fetchColumn() > 0);

  if ($hasViewCount) {
    $inc = $pdo->prepare("UPDATE services SET view_count = view_count + 1 WHERE service_id = :id");
    $inc->execute([":id" => $serviceId]);
  }
} catch (Exception $e) {}

$isFeatured = ($status === "active" && strtolower((string)$service["featured_status"]) === "yes");
$desc = (string) ($service["description"] ?? "");
if (strlen($desc) > 1000) $desc = substr($desc, 0, 1000) . "...";

$imgs = [];
if (!empty($service["image_1"])) $imgs[] = $service["image_1"];
if (!empty($service["image_2"])) $imgs[] = $service["image_2"];
if (!empty($service["image_3"])) $imgs[] = $service["image_3"];

$canBuy = false;
if ($isLoggedIn) {
  if ($role === "Client") $canBuy = true;
  if ($role === "Freelancer" && !$isOwner) $canBuy = true;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $action = (string)($_POST["action"] ?? "");

  if ($action === "add_to_cart" || $action === "order_now") {

    if (!$isLoggedIn) {
      $_SESSION["flash_error"] = "Please login to add services to cart.";
      header("Location: login.php");
      exit;
    }

    if (!$canBuy) {
      $_SESSION["flash_error"] = "You cannot order this service.";
      header("Location: service-detail.php?id=" . urlencode($serviceId));
      exit;
    }

    if (strtolower((string)$service["status"]) !== "active") {
      $_SESSION["flash_error"] = "Service is inactive and cannot be added to cart.";
      header("Location: service-detail.php?id=" . urlencode($serviceId));
      exit;
    }

    if (!isset($_SESSION["cart"]) || !is_array($_SESSION["cart"])) $_SESSION["cart"] = [];

    $ids = [];
    foreach ($_SESSION["cart"] as $x) {
      if (is_string($x) || is_int($x)) $ids[] = (string)$x;
    }
    $_SESSION["cart"] = array_values(array_unique(array_filter($ids)));

    if (in_array((string)$serviceId, $_SESSION["cart"], true)) {
      $_SESSION["flash_error"] = "Service already in cart.";
      header("Location: service-detail.php?id=" . urlencode($serviceId));
      exit;
    }

    $_SESSION["cart"][] = (string)$serviceId;
    $_SESSION["cart"] = array_values(array_unique(array_filter($_SESSION["cart"])));

    $_SESSION["flash_success"] = "Service added to cart successfully!";

    if ($action === "order_now") {
      header("Location: cart.php");
      exit;
    }

    header("Location: service-detail.php?id=" . urlencode($serviceId));
    exit;
  }
}

$recentToShow = array_values(array_filter($recent, function($x) use ($serviceId) {
  return $x !== $serviceId;
}));
if (count($recentToShow) > 4) $recentToShow = array_slice($recentToShow, -4);

$recentServices = [];
if (!empty($recentToShow)) {
  $placeholders = implode(",", array_fill(0, count($recentToShow), "?"));
  $orderField = implode(",", array_fill(0, count($recentToShow), "?"));

  $sqlRecent = "
    SELECT service_id, title, category, price, image_1, featured_status
    FROM services
    WHERE status = 'Active'
      AND service_id IN ($placeholders)
    ORDER BY FIELD(service_id, $orderField)
  ";

  $params = array_merge($recentToShow, $recentToShow);
  $rstmt = $pdo->prepare($sqlRecent);
  $rstmt->execute($params);
  $recentServices = $rstmt->fetchAll(PDO::FETCH_ASSOC);
}

include __DIR__ . "/includes/header.php";
?>

<div class="wrapper">
  <div class="container">
    <?php include __DIR__ . "/includes/nav.php"; ?>

    <main class="main-content">

      <?php if (!empty($_SESSION["flash_success"])): ?>
        <div class="message-success"><?= htmlspecialchars($_SESSION["flash_success"]); ?></div>
        <?php unset($_SESSION["flash_success"]); ?>
      <?php endif; ?>

      <?php if (!empty($_SESSION["flash_error"])): ?>
        <div class="message-error"><?= htmlspecialchars($_SESSION["flash_error"]); ?></div>
        <?php unset($_SESSION["flash_error"]); ?>
      <?php endif; ?>

      <?php if ($status !== "active"): ?>
        <div class="message-error">This service is currently inactive and not visible to clients.</div>
      <?php endif; ?>

      <div class="create-service-card">
        <div class="service-detail-layout">

          <div class="service-detail-left">

            <h1 class="service-detail-title"><?= htmlspecialchars($service["title"]) ?></h1>

            <div class="service-detail-breadcrumb">
              <?= htmlspecialchars($service["category"]) ?><span class="sep">&gt;</span><?= htmlspecialchars($service["subcategory"]) ?>
            </div>

            <div class="gallery-wrap">
              <?php if (empty($imgs)): ?>
                <div class="gallery-panel" id="img1">
                  <img class="gallery-main-img"
                       src="<?= htmlspecialchars(img($baseUrl, "", $servicePlaceholderRel)) ?>"
                       alt="Service image">
                </div>
              <?php else: ?>
                <?php foreach ($imgs as $i => $rel): ?>
                  <?php $panelId = "img" . ($i + 1); ?>
                  <div class="gallery-panel" id="<?= htmlspecialchars($panelId) ?>">
                    <img class="gallery-main-img"
                         src="<?= htmlspecialchars(img($baseUrl, $rel, $servicePlaceholderRel)) ?>"
                         alt="Service image">
                  </div>
                <?php endforeach; ?>

                <div class="gallery-thumbs">
                  <?php foreach ($imgs as $i => $rel): ?>
                    <?php $panelId = "img" . ($i + 1); ?>
                    <a class="gallery-thumb-link" href="#<?= htmlspecialchars($panelId) ?>" title="View image <?= ($i + 1) ?>">
                      <img class="gallery-thumb-img"
                           src="<?= htmlspecialchars(img($baseUrl, $rel, $servicePlaceholderRel)) ?>"
                           alt="Thumbnail">
                    </a>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <?php
              $photoSrc = img(
                $baseUrl,
                $service["freelancer_photo"] ?? "",
                $userPlaceholderRel,
                "uploads/profile-photo",
                true
              );

              $since = !empty($service["freelancer_since"]) ? date("M Y", strtotime((string)$service["freelancer_since"])) : "";
            ?>
            <div class="user-card user-card-service">
              <img class="user-photo" src="<?= htmlspecialchars($photoSrc) ?>" alt="Freelancer photo">

              <div class="user-card-info">
                <a class="user-name" href="profile.php?id=<?= urlencode((string)$service["freelancer_id"]) ?>">
                  <?= htmlspecialchars((string)$service["freelancer_name"] ?: ("User #" . $service["freelancer_id"])) ?>
                </a>

                <?php if ($since !== ""): ?>
                  <div class="user-meta">Member since <?= htmlspecialchars($since) ?></div>
                <?php endif; ?>

                <a class="action-link" href="profile.php?id=<?= urlencode((string)$service["freelancer_id"]) ?>">View Profile</a>
              </div>
            </div>

            <h3 class="about-service-title">About This Service</h3>
            <div class="about-service-text"><?= nl2br(htmlspecialchars($desc)) ?></div>

          </div>

          <div class="service-detail-right">

            <div class="booking-card">
              <div class="booking-starting">Starting at</div>
              <div class="booking-price">$<?= htmlspecialchars(number_format((float)$service["price"], 2)) ?></div>

              <?php
                $deliveryDays = (int)($service["delivery_time"] ?? 0);
                $deliveryText = ($deliveryDays === 1) ? "Delivery in 1 day" : "Delivery in {$deliveryDays} days";

                $revNum = (int)($service["revisions_included"] ?? 0);
                if ($revNum === 999) $revText2 = "Unlimited revisions included";
                elseif ($revNum === 1) $revText2 = "1 revision included";
                else $revText2 = $revNum . " revisions included";

                $iconTime = rtrim($baseUrl, "/") . "/uploads/icons/time.png";
                $iconRev  = rtrim($baseUrl, "/") . "/uploads/icons/Revisions.png";
              ?>

              <div class="booking-item">
                <img class="booking-icon" src="<?= htmlspecialchars($iconTime) ?>" alt="Delivery">
                <?= htmlspecialchars($deliveryText) ?>
              </div>

              <div class="booking-item">
                <img class="booking-icon" src="<?= htmlspecialchars($iconRev) ?>" alt="Revisions">
                <?= htmlspecialchars($revText2) ?>
              </div>

              <?php if ($isFeatured): ?>
                <div class="text-muted">★ Featured</div>
              <?php endif; ?>

              <div class="booking-actions">

                <?php if (!$isLoggedIn): ?>
                  <a class="btn btn-primary" href="login.php">Login to Order</a>

                <?php else: ?>
                  <?php if ($isOwner): ?>
                    <a class="btn btn-primary" href="edit-service.php?id=<?= urlencode($serviceId) ?>">Edit Service</a>
                  <?php else: ?>
                    <?php if ($canBuy): ?>
                      <form method="post" action="service-detail.php?id=<?= urlencode($serviceId) ?>">
                        <input type="hidden" name="action" value="add_to_cart">
                        <button class="btn btn-primary" type="submit">Add to Cart</button>
                      </form>

                      <form method="post" action="service-detail.php?id=<?= urlencode($serviceId) ?>">
                        <input type="hidden" name="action" value="order_now">
                        <button class="btn btn-secondary" type="submit">Order Now</button>
                      </form>
                    <?php endif; ?>
                  <?php endif; ?>
                <?php endif; ?>

                <a class="btn btn-secondary" href="profile.php?id=<?= urlencode((string)$service["freelancer_id"]) ?>">
                  Contact Freelancer
                </a>

              </div>
            </div>

          </div>
        </div>

        <div class="recently-viewed-section">
          <div class="recently-viewed-title">Recently Viewed Services</div>

          <?php if (empty($recentServices)): ?>
            <p class="text-muted">No recently viewed services yet.</p>
          <?php else: ?>
            <div class="services-grid services-grid-featured">
              <?php foreach ($recentServices as $rs): ?>
                <?php $imgCard = img($baseUrl, $rs["image_1"] ?? "", $servicePlaceholderRel); ?>
                <a class="service-card" href="service-detail.php?id=<?= urlencode((string)$rs["service_id"]) ?>">
                  <div class="service-card-image-wrap">
                    <img class="service-card-image" src="<?= htmlspecialchars($imgCard) ?>" alt="Service image">
                  </div>
                  <div class="card-body">
                    <div class="service-card-title"><?= htmlspecialchars((string)$rs["title"]) ?></div>
                    <div class="service-card-category"><?= htmlspecialchars((string)$rs["category"]) ?></div>
                    <div class="service-card-price">$<?= htmlspecialchars(number_format((float)$rs["price"], 2)) ?></div>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

      </div>
    </main>

    <aside class="sidebar">
      <h3 class="heading-secondary">Sidebar</h3>
    </aside>
  </div>

  <?php include __DIR__ . "/includes/footer.php"; ?>
</div>