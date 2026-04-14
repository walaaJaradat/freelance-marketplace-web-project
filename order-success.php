<?php
require_once "includes/init.php";

$Title = "Order Success";
$activePage = "order-success.php";

if (!isset($_SESSION["user"])) { header("Location: login.php"); exit; }

/* Allow Client + Freelancer */
$role = $_SESSION["user"]["role"] ?? "Guest";
if (!in_array($role, ["Client","Freelancer"], true)) {
  $_SESSION["flash_error"] = "Only registered users can access this page.";
  header("Location: index.html"); exit;
}

$orderIds = $_SESSION["last_order_ids"] ?? [];
$orderIds = array_values(array_unique(array_filter($orderIds)));

if (count($orderIds) < 1) {
  $_SESSION["flash_error"] = "No recent orders found.";
  header("Location: my-orders.php");
  exit;
}

$placeholders = implode(",", array_fill(0, count($orderIds), "?"));
$stmt = $pdo->prepare("
  SELECT o.order_id, o.service_title, o.status, o.expected_delivery, o.price,
         o.freelancer_id,
         CONCAT(u.first_name,' ',u.last_name) AS freelancer_name
  FROM orders o
  JOIN users u ON u.user_id = o.freelancer_id
  WHERE o.order_id IN ($placeholders)
  ORDER BY o.order_date DESC
");
$stmt->execute($orderIds);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = 0;
foreach ($orders as $o) {
  $fee = round(((float)$o["price"]) * 0.05, 2);
  $total += round(((float)$o["price"]) + $fee, 2);
}

include "includes/header.php";
?>

<div class="wrapper">
  <div class="container">
    <?php include "includes/nav.php"; ?>

    <main class="main-content">
      <div class="success-banner">
        <div class="success-title">Orders Placed Successfully!</div>
        <p class="success-sub">You have placed <?php echo count($orders); ?> orders • Total: $<?php echo number_format($total,2); ?></p>
      </div>

      <div class="success-wrap">
        <?php foreach($orders as $o):
          $fee = round(((float)$o["price"]) * 0.05, 2);
          $orderTotal = round(((float)$o["price"]) + $fee, 2);
        ?>
          <div class="order-success-card">
            <div class="order-id">Order #<?php echo htmlspecialchars($o["order_id"]); ?></div>
            <div><strong><?php echo htmlspecialchars($o["service_title"]); ?></strong></div>
            <div>
              Freelancer:
              <a class="action-link" href="profile.php?uid=<?php echo urlencode($o["freelancer_id"]); ?>">
                <?php echo htmlspecialchars($o["freelancer_name"]); ?>
              </a>
            </div>

            <div class="success-meta">
              <span class="status-badge"><?php echo htmlspecialchars($o["status"]); ?></span>
            </div>

            <p class="success-totals">
              Total Amount: <strong>$<?php echo number_format($orderTotal,2); ?></strong><br>
              Expected Delivery: <span class="text-muted"><?php echo htmlspecialchars($o["expected_delivery"]); ?></span>
            </p>

            <a class="btn btn-primary btn-sm" href="order-details.php?oid=<?php echo urlencode($o["order_id"]); ?>">View Order Details</a>
          </div>
        <?php endforeach; ?>

        <div class="success-actions">
          <a class="btn btn-primary" href="my-orders.php">View All Orders</a>
          <a class="btn btn-secondary" href="browse-services.php">Browse More Services</a>
        </div>
      </div>

    </main>
  </div>
</div>

<?php include "includes/footer.php"; ?>
