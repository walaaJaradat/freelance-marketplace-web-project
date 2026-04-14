<?php
require_once "includes/init.php";

$Title = "Cancel Order";
$activePage = "my-orders.php";

if (!isset($_SESSION["user"])) { header("Location: login.php"); exit; }

$userId  = (string)($_SESSION["user"]["id"] ?? "");
$role    = (string)($_SESSION["user"]["role"] ?? "");
$orderId = trim((string)($_GET["id"] ?? ""));

if (!function_exists("h")) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }
}

if (!function_exists("str_len")) {
  function str_len($s){
    $s = (string)$s;
    return function_exists("mb_strlen") ? mb_strlen($s, "UTF-8") : strlen($s);
  }
}

function append_notes_block($existing, $blockTitle, $lines){
  $existing = (string)$existing;
  $stamp = date("Y-m-d H:i:s");

  $out  = "\n\n";
  $out .= "===== {$blockTitle} ({$stamp}) =====\n";
  foreach ($lines as $k => $v) {
    $v = str_replace(["\r\n","\r"], "\n", (string)$v);
    $out .= strtoupper($k) . ": " . $v . "\n";
  }
  $out .= "===== END {$blockTitle} =====\n";

  return $existing . $out;
}

if ($role !== "Client" || $orderId === "") {
  $_SESSION["flash_error"] = "Access denied.";
  header("Location: my-orders.php");
  exit;
}

try {
  $stmt = $pdo->prepare("
    SELECT order_id, client_id, status, service_title, deliverable_notes, order_date
    FROM orders
    WHERE order_id=? AND client_id=?
    LIMIT 1
  ");
  $stmt->execute([$orderId, $userId]);
  $order = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$order || (string)$order["status"] !== "Pending") {
    $_SESSION["flash_error"] = "Cancellation not allowed.";
    header("Location: my-orders.php");
    exit;
  }

} catch (Exception $e) {
  $_SESSION["flash_error"] = "Server error while loading order.";
  header("Location: my-orders.php");
  exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $confirm = isset($_POST["confirm"]);
  $reason  = trim((string)($_POST["reason"] ?? ""));

  if (!$confirm) {
    $error = "You must confirm cancellation.";
  } elseif ($reason !== "" && (str_len($reason) < 10 || str_len($reason) > 500)) {
    $error = "Cancellation reason must be 10–500 characters (or leave it empty).";
  } else {

    $cancelAt = date("Y-m-d H:i:s");

    $newNotes = append_notes_block(
      (string)($order["deliverable_notes"] ?? ""),
      "UC11_CANCEL",
      [
        "date"   => $cancelAt,
        "reason" => ($reason !== "" ? $reason : "N/A")
      ]
    );

    try {
      $pdo->prepare("
        UPDATE orders
        SET status='Cancelled',
            deliverable_notes=?
        WHERE order_id=? AND client_id=? AND status='Pending'
      ")->execute([$newNotes, $orderId, $userId]);

      $_SESSION["flash_success"] = "Order cancelled successfully (refund simulated).";
      header("Location: my-orders.php");
      exit;

    } catch (Exception $e) {
      $error = "Could not cancel order. Please try again.";
    }
  }
}

include "includes/header.php";
?>

<div class="wrapper">
  <div class="container">
    <?php include "includes/nav.php"; ?>

    <main class="main-content">
      <h1 class="heading-primary">Cancel Order</h1>

      <div class="alert alert-warning">
        You are about to cancel an order. This action is only allowed while the order is <strong>Pending</strong>.
      </div>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo h($error); ?></div>
      <?php endif; ?>

      <div class="create-service-card">
        <div class="kv">
          <div class="k">Order</div>
          <div class="v">#<?php echo h($orderId); ?></div>

          <div class="k">Service</div>
          <div class="v"><?php echo h($order["service_title"] ?? ""); ?></div>

          <div class="k">Order Date</div>
          <div class="v"><?php echo !empty($order["order_date"]) ? h(date("M d, Y", strtotime((string)$order["order_date"]))) : "-"; ?></div>
        </div>

        <form method="post" class="cancel-form">
          <div class="form-group">
            <label class="form-label">Cancellation Reason <span class="optional">(optional)</span></label>
            <textarea class="form-input" name="reason" rows="4" placeholder="Optional reason (10–500 chars)"><?php echo h($_POST["reason"] ?? ""); ?></textarea>
          </div>

          <div class="form-group">
            <label class="form-label">
              <input type="checkbox" name="confirm" <?php echo isset($_POST["confirm"]) ? "checked" : ""; ?>>
              I confirm that I want to cancel this order
            </label>
          </div>

          <div class="action-row">
            <button class="btn btn-danger" type="submit">Cancel Order</button>
            <a class="btn btn-secondary" href="order-details.php?id=<?php echo urlencode($orderId); ?>">Back</a>
          </div>
        </form>
      </div>

    </main>
  </div>

  <?php include "includes/footer.php"; ?>
</div>