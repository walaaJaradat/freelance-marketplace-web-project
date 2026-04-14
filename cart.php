<?php
require_once "includes/init.php";

$Title = "Shopping Cart";
$activePage = "cart.php";

if (!isset($baseUrl) || (string)$baseUrl === "") {
  $baseUrl = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"])), "/");
  $baseUrl = ($baseUrl === "" ? "/" : $baseUrl . "/");
}

if (!isset($_SESSION["user"])) {
  $_SESSION["flash_error"] = "Please login to view cart.";
  header("Location: login.php");
  exit;
}

$cart = $_SESSION["cart"] ?? [];
if (!is_array($cart)) $cart = [];

$cartIds = [];
foreach ($cart as $x) {
  if (is_string($x) || is_int($x)) $cartIds[] = (string)$x;
}
$cartIds = array_values(array_unique(array_filter($cartIds)));
$_SESSION["cart"] = $cartIds;
$cart = $cartIds;

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8"); }
function money_fmt($n){ return "$" . number_format((float)$n, 2); }

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["remove_service_id"])) {
  $removeId = trim((string)$_POST["remove_service_id"]);
  $new = [];
  foreach ($cart as $sid) {
    if ((string)$sid !== (string)$removeId) $new[] = (string)$sid;
  }
  $_SESSION["cart"] = array_values(array_unique(array_filter($new)));
  $_SESSION["flash_success"] = "Service removed from cart.";
  header("Location: cart.php");
  exit;
}

/* Proceed to checkout validation */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["proceed_to_checkout"])) {

  $cart = $_SESSION["cart"] ?? [];
  if (!is_array($cart) || count($cart) === 0) {
    $_SESSION["flash_error"] = "Your cart is empty.";
    header("Location: browse-services.php");
    exit;
  }

  $warnings = [];
  $validIds = [];

  foreach ($cart as $sid) {
    $sid = (string)$sid;
    if ($sid === "") continue;

    $stmt = $pdo->prepare("SELECT title, status FROM services WHERE service_id = ? LIMIT 1");
    $stmt->execute([$sid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || ($row["status"] ?? "") !== "Active") {
      $title = $row["title"] ?? "Unknown";
      $warnings[] = "Service '{$title}' is no longer available and has been removed";
      continue;
    }

    $validIds[] = $sid;
  }

  $_SESSION["cart"] = array_values(array_unique(array_filter($validIds)));

  if (count($warnings) > 0) $_SESSION["flash_warning_list"] = $warnings;

  if (count($_SESSION["cart"]) === 0) {
    $_SESSION["flash_error"] = "Your cart is empty.";
    header("Location: browse-services.php");
    exit;
  }

  header("Location: checkout.php");
  exit;
}

$cartServices = [];
if (count($cart) > 0) {
  $placeholders = implode(",", array_fill(0, count($cart), "?"));
  $sql = "
    SELECT s.service_id, s.title, s.category, s.price, s.delivery_time, s.revisions_included, s.image_1,
           u.user_id AS freelancer_id,
           CONCAT(u.first_name,' ',u.last_name) AS freelancer_name
    FROM services s
    JOIN users u ON u.user_id = s.freelancer_id
    WHERE s.service_id IN ($placeholders) AND s.status='Active'
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($cart);
  $cartServices = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* Totals */
$subtotal = 0.0;
foreach ($cartServices as $r) $subtotal += (float)($r["price"] ?? 0);
$serviceFee = round($subtotal * 0.05, 2);
$total = round($subtotal + $serviceFee, 2);

/* Recently viewed cookie */
$recentCards = [];
$cookie = $_COOKIE["recent_services"] ?? "";
$recentIds = [];

if ($cookie !== "") {
  foreach (explode(",", $cookie) as $p) {
    $id = trim($p);
    if ($id !== "" && !in_array($id, $recentIds, true)) $recentIds[] = $id;
  }
  if (count($recentIds) > 4) $recentIds = array_slice($recentIds, -4);
}

if (count($recentIds) > 0) {
  $ph = [];
  $params = [];
  foreach ($recentIds as $i => $rid) {
    $k = ":id" . $i;
    $ph[] = $k;
    $params[$k] = $rid;
  }

  $sql = "SELECT s.service_id, s.title, s.category, s.price, s.image_1, s.featured_status,
                 u.user_id AS freelancer_id,
                 CONCAT(u.first_name,' ',u.last_name) AS freelancer_name,
                 u.profile_photo
          FROM services s
          JOIN users u ON u.user_id = s.freelancer_id
          WHERE s.status = 'Active' AND s.service_id IN (" . implode(",", $ph) . ")";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $recentCards = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include "includes/header.php";
?>

<div class="wrapper">
  <div class="container cart-page">
    <?php include "includes/nav.php"; ?>

    <main class="main-content">
      <h1 class="heading-primary">Shopping Cart</h1>

      <?php if (!empty($_SESSION["flash_error"])): ?>
        <div class="message-error"><?php echo h($_SESSION["flash_error"]); ?></div>
        <?php unset($_SESSION["flash_error"]); ?>
      <?php endif; ?>

      <?php if (!empty($_SESSION["flash_success"])): ?>
        <div class="message-success"><?php echo h($_SESSION["flash_success"]); ?></div>
        <?php unset($_SESSION["flash_success"]); ?>
      <?php endif; ?>

      <?php if (!empty($_SESSION["flash_warning_list"]) && is_array($_SESSION["flash_warning_list"])): ?>
        <div class="message-warning">
          <?php foreach ($_SESSION["flash_warning_list"] as $w): ?>
            <div><?php echo h($w); ?></div>
          <?php endforeach; ?>
        </div>
        <?php unset($_SESSION["flash_warning_list"]); ?>
      <?php endif; ?>

      <?php if (count($cart) === 0): ?>

        <div class="empty-cart">
          <?php $emptyImg = $baseUrl . "uploads/empty-bag.png"; ?>
          <img class="empty-cart-icon" src="<?php echo h($emptyImg); ?>" alt="Empty cart">

          <div class="empty-cart-msg">Your cart is empty</div>
          <a class="btn btn-primary" href="browse-services.php">Browse Services</a>
        </div>

        <?php if (count($recentCards) > 0): ?>
          <section class="recently-viewed">
            <h2 class="heading-secondary">Recently Viewed Services</h2>

            <div class="services-grid-featured">
              <?php foreach ($recentCards as $r): ?>
                <?php
                  $imgRel = !empty($r["image_1"]) ? (string)$r["image_1"] : "/uploads/services/placeholder-service.png";
                  $img = $baseUrl . ltrim($imgRel, "/");

                  $phRel = !empty($r["profile_photo"]) ? (string)$r["profile_photo"] : "/uploads/profiles/user-default.png";
                  $phImg = $baseUrl . ltrim($phRel, "/");
                ?>

                <a class="card service-card" href="service-detail.php?id=<?php echo urlencode($r["service_id"]); ?>">
                  <div class="card-header">
                    <?php if (($r["featured_status"] ?? "") === "Yes"): ?>
                      <span class="badge badge-featured service-card-badge">Featured</span>
                    <?php endif; ?>
                    <img class="service-card-image" src="<?php echo h($img); ?>" alt="Service Image">
                  </div>

                  <div class="card-body">
                    <div class="service-card-title"><?php echo h($r["title"]); ?></div>

                    <div class="service-card-freelancer">
                      <img class="service-card-avatar" src="<?php echo h($phImg); ?>" alt="Freelancer">
                      <span><?php echo h($r["freelancer_name"] ?? ""); ?></span>
                    </div>

                    <div class="service-card-category"><?php echo h($r["category"] ?? ""); ?></div>
                    <div class="service-card-price"><?php echo h(money_fmt($r["price"] ?? 0)); ?></div>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>

      <?php else: ?>

        <table class="services-table">
          <thead>
            <tr>
              <th>Thumbnail</th>
              <th>Title</th>
              <th>Freelancer</th>
              <th>Category</th>
              <th>Delivery</th>
              <th>Revisions</th>
              <th>Price</th>
              <th>Remove</th>
            </tr>
          </thead>

          <tbody>
            <?php foreach ($cartServices as $r): ?>
              <?php
                $sid = (string)$r["service_id"];
                $imgRel = !empty($r["image_1"]) ? (string)$r["image_1"] : "/uploads/services/placeholder-service.png";
                $img = $baseUrl . ltrim($imgRel, "/");

                $deliveryDays = (int)($r["delivery_time"] ?? 0);
                $deliveryText = $deliveryDays > 0 ? ($deliveryDays . " days") : "-";

                $revs = (int)($r["revisions_included"] ?? 0);
                $revText = ($revs === 999) ? "Unlimited" : (string)$revs;
              ?>
              <tr>
                <td>
                  <a class="action-link" href="service-detail.php?id=<?php echo urlencode($sid); ?>">
                    <img class="cart-thumb" src="<?php echo h($img); ?>" alt="Thumb">
                  </a>
                </td>

                <td>
                  <a class="action-link cart-title-link" href="service-detail.php?id=<?php echo urlencode($sid); ?>">
                    <?php echo h($r["title"] ?? ""); ?>
                  </a>
                </td>

                <td>
                  <a class="action-link" href="browse-services.php?freelancer_id=<?php echo urlencode((string)($r["freelancer_id"] ?? "")); ?>">
                    <?php echo h($r["freelancer_name"] ?? ""); ?>
                  </a>
                </td>

                <td><span class="text-muted"><?php echo h($r["category"] ?? ""); ?></span></td>
                <td><?php echo h($deliveryText); ?></td>
                <td><?php echo h($revText); ?></td>
                <td><?php echo h(money_fmt($r["price"] ?? 0)); ?></td>

                <td>
                  <form method="post" action="cart.php">
                    <input type="hidden" name="remove_service_id" value="<?php echo h($sid); ?>">
                    <button type="submit" class="btn btn-danger btn-sm cart-remove-btn">Remove</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

      <?php endif; ?>
    </main>

    <?php if (count($cart) > 0): ?>
      <aside class="sidebar">
        <div class="order-card">
          <h2 class="heading-secondary">Order Summary</h2>

          <p class="text-muted"><span>Subtotal:</span><strong><?php echo h(money_fmt($subtotal)); ?></strong></p>
          <p class="text-muted"><span>Service Fee (5%) :</span><strong><?php echo h(money_fmt($serviceFee)); ?></strong></p>
          <p><span><strong>Total</strong></span><strong><?php echo h(money_fmt($total)); ?></strong></p>

          <form method="post" action="cart.php">
            <button type="submit" name="proceed_to_checkout" class="btn btn-primary checkout-btn">
              Proceed to Checkout
            </button>
          </form>
        </div>
      </aside>
    <?php endif; ?>

  </div>

  <?php include "includes/footer.php"; ?>
</div>