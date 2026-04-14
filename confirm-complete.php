<?php
require_once "includes/init.php";

$Title = "Confirm Completion";
$activePage = "my-orders.php";

if (!isset($_SESSION["user"])) { header("Location: login.php"); exit; }

$userId  = (string)($_SESSION["user"]["id"] ?? "");
$role    = (string)($_SESSION["user"]["role"] ?? "");
$orderId = trim((string)($_GET["id"] ?? ""));

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

if ($role !== "Client" || $orderId === "") {
  $_SESSION["flash_error"] = "Access denied.";
  header("Location: my-orders.php");
  exit;
}

$stmt = $pdo->prepare("SELECT order_id, client_id, status, service_title FROM orders WHERE order_id=? AND client_id=? LIMIT 1");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order || (string)$order["status"] !== "Delivered") {
  $_SESSION["flash_error"] = "Mark as completed is not allowed.";
  header("Location: my-orders.php");
  exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $confirm = isset($_POST["confirm"]);
  if (!$confirm) {
    $error = "You must confirm to proceed.";
  } else {
    $pdo->prepare("UPDATE orders SET status='Completed', completion_date=NOW() WHERE order_id=? AND client_id=? AND status='Delivered'")
        ->execute([$orderId, $userId]);

    $_SESSION["flash_success"] = "Order marked as Completed.";
    header("Location: order-details.php?id=" . urlencode($orderId));
    exit;
  }
}

include "includes/header.php";
?>

<div class="wrapper">
  <div class="container">
    <?php include "includes/nav.php"; ?>

    <main class="main-content">
      <h1 class="heading-primary">Confirm Completion</h1>

      <div class="alert alert-warning">
        Are you sure you want to mark this order as completed? This action cannot be undone.
      </div>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo h($error); ?></div>
      <?php endif; ?>

      <div class="create-service-card">
        <div class="text-muted">
          Order: <strong>#<?php echo h($orderId); ?></strong> — <?php echo h($order["service_title"] ?? ""); ?>
        </div>

        <form method="post" class="confirm-form">
          <div class="form-group">
            <label class="form-label">
              <input type="checkbox" name="confirm">
              I confirm I want to mark this order as completed
            </label>
          </div>

          <div class="action-row">
            <button class="btn btn-primary" type="submit">Yes, Mark Completed</button>
            <a class="btn btn-secondary" href="order-details.php?id=<?php echo urlencode($orderId); ?>">Cancel</a>
          </div>
        </form>
      </div>

    </main>
  </div>

  <?php include "includes/footer.php"; ?>
</div>