<?php
require_once "includes/init.php";

$Title = "Request Revision";
$activePage = "my-orders.php";

if (!isset($_SESSION["user"])) { header("Location: login.php"); exit; }

$userId  = (string)($_SESSION["user"]["id"] ?? "");
$role    = (string)($_SESSION["user"]["role"] ?? "");
$orderId = trim((string)($_GET["id"] ?? ""));

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }
function str_len($s){
  $s = (string)$s;
  return function_exists("mb_strlen") ? mb_strlen($s, "UTF-8") : strlen($s);
}

if ($role !== "Client" || $orderId === "") {
  $_SESSION["flash_error"] = "Access denied.";
  header("Location: my-orders.php");
  exit;
}

$stmt = $pdo->prepare("
  SELECT order_id, client_id, status, revisions_included, service_title
  FROM orders
  WHERE order_id=? AND client_id=?
  LIMIT 1
");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order || (string)$order["status"] !== "Delivered") {
  $_SESSION["flash_error"] = "Revision not allowed.";
  header("Location: my-orders.php");
  exit;
}

$allowed = (int)($order["revisions_included"] ?? 0);
$unlimited = ($allowed === 999);

$cnt = $pdo->prepare("
  SELECT
    SUM(CASE WHEN request_status='Accepted' THEN 1 ELSE 0 END) AS acc,
    SUM(CASE WHEN request_status='Rejected' THEN 1 ELSE 0 END) AS rej
  FROM revision_requests
  WHERE order_id=?
");
$cnt->execute([$orderId]);
$row = $cnt->fetch(PDO::FETCH_ASSOC) ?: ["acc"=>0,"rej"=>0];

$used = (int)($row["acc"] ?? 0) + (int)($row["rej"] ?? 0);
$remaining = $unlimited ? null : max(0, $allowed - $used);
$canRequest = $unlimited ? true : ($used < $allowed);

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

  if (!$canRequest) {
    $error = "You have used all $allowed revision requests for this order.";
  } else {
    $desc = trim((string)($_POST["desc"] ?? ""));
    $confirm = isset($_POST["confirm"]);

    if (str_len($desc) < 50 || str_len($desc) > 500) {
      $error = "Description must be 50–500 characters.";
    } elseif (!$confirm) {
      $error = "You must confirm that this request counts toward your revision limit.";
    } else {

      $pdo->prepare("
        INSERT INTO revision_requests (order_id, revision_notes, request_status, request_date)
        VALUES (?, ?, 'Pending', NOW())
      ")->execute([$orderId, $desc]);

      $pdo->prepare("
        UPDATE orders
        SET status='Revision Requested'
        WHERE order_id=? AND client_id=?
      ")->execute([$orderId, $userId]);

      $_SESSION["flash_success"] = "Revision request submitted.";
      header("Location: order-details.php?id=" . urlencode($orderId));
      exit;
    }
  }
}

include "includes/header.php";
?>

<div class="wrapper">
  <div class="container">
    <?php include "includes/nav.php"; ?>
    <main class="main-content">

      <h1 class="heading-primary">Request Revision</h1>

      <div class="create-service-card">
        <div class="text-muted">
          Order: <strong>#<?php echo h($orderId); ?></strong> — <?php echo h($order["service_title"] ?? ""); ?><br>
          Revisions: <?php echo $unlimited ? "Unlimited" : (h($remaining) . "/" . h($allowed) . " remaining"); ?>
        </div>

        <?php if (!$canRequest): ?>
          <div class="alert alert-warning">
            You have used all <?php echo (int)$allowed; ?> revision requests for this order.
          </div>
          <a class="btn btn-secondary" href="order-details.php?id=<?php echo urlencode($orderId); ?>">Back to Order</a>
        <?php else: ?>

          <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo h($error); ?></div>
          <?php endif; ?>

          <form method="post" class="revision-form">
            <div class="form-group">
              <label class="form-label">Describe what to revise (50–500 characters) <span class="req">*</span></label>
              <textarea name="desc" class="form-input" rows="5" required><?php echo h($_POST["desc"] ?? ""); ?></textarea>
            </div>

            <div class="form-group">
              <label class="form-label">
                <input type="checkbox" name="confirm" <?php echo isset($_POST["confirm"]) ? "checked" : ""; ?>>
                I understand this request counts toward my revision limit.
              </label>
            </div>

            <div class="action-row">
              <button class="btn btn-primary" type="submit">Submit</button>
              <a class="btn btn-secondary" href="order-details.php?id=<?php echo urlencode($orderId); ?>">Cancel</a>
            </div>
          </form>

        <?php endif; ?>
      </div>

    </main>
  </div>
  <?php include "includes/footer.php"; ?>
</div>