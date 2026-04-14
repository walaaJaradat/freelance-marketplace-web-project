<?php
require_once "includes/init.php";

$Title = "Reject Revision";
$activePage = "my-orders.php";

if (!isset($_SESSION["user"])) { header("Location: login.php"); exit; }

$userId = (string)($_SESSION["user"]["id"] ?? "");
$role   = (string)($_SESSION["user"]["role"] ?? "");
$revId  = trim((string)($_GET["id"] ?? ""));

if (!function_exists("h")) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }
}

if (!function_exists("str_len")) {
  function str_len($s){
    $s = (string)$s;
    return function_exists("mb_strlen") ? mb_strlen($s, "UTF-8") : strlen($s);
  }
}

if ($role !== "Freelancer" || $revId === "") {
  $_SESSION["flash_error"] = "Access denied.";
  header("Location: my-orders.php");
  exit;
}

try {
  $stmt = $pdo->prepare("
    SELECT r.*, o.freelancer_id, o.order_id, o.service_title, o.status
    FROM revision_requests r
    JOIN orders o ON o.order_id = r.order_id
    WHERE r.revision_id = ?
      AND r.request_status = 'Pending'
      AND o.status = 'Revision Requested'
    LIMIT 1
  ");
  $stmt->execute([$revId]);
  $r = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$r || (string)$r["freelancer_id"] !== (string)$userId) {
    $_SESSION["flash_error"] = "Access denied.";
    header("Location: my-orders.php");
    exit;
  }

} catch (Exception $e) {
  $_SESSION["flash_error"] = "Server error while loading revision.";
  header("Location: my-orders.php");
  exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

  $reason = trim((string)($_POST["reason"] ?? ""));

  if (str_len($reason) < 50 || str_len($reason) > 500) {
    $error = "Reason must be 50–500 characters.";
  } else {

    try {
      $pdo->beginTransaction();

      $pdo->prepare("
        UPDATE revision_requests
        SET request_status='Rejected',
            freelancer_response=?,
            response_date=NOW()
        WHERE revision_id=?
          AND request_status='Pending'
      ")->execute([$reason, $revId]);

      $pdo->prepare("
        UPDATE orders
        SET status='Delivered'
        WHERE order_id=?
          AND status='Revision Requested'
      ")->execute([(string)$r["order_id"]]);

      $pdo->commit();

      $_SESSION["flash_success"] = "Revision request rejected.";
      header("Location: order-details.php?id=" . urlencode((string)$r["order_id"]));
      exit;

    } catch (Exception $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $error = "Could not reject revision. Please try again.";
    }
  }
}

include "includes/header.php";
?>

<div class="wrapper">
  <div class="container">
    <?php include "includes/nav.php"; ?>
    <main class="main-content">

      <h1 class="heading-primary">Reject Revision</h1>

      <div class="create-service-card">
        <div class="text-muted">
          Order: <strong>#<?php echo h($r["order_id"]); ?></strong> — <?php echo h($r["service_title"] ?? ""); ?>
        </div>

        <?php if (!empty($error)): ?>
          <div class="alert alert-danger"><?php echo h($error); ?></div>
        <?php endif; ?>

        <form method="post" class="order-card">
          <div class="form-group">
            <label class="form-label">Rejection Reason (50–500 chars) <span class="req">*</span></label>
            <textarea name="reason" class="form-input" rows="5" required><?php echo h($_POST["reason"] ?? ""); ?></textarea>
          </div>

          <div class="action-row">
            <button class="btn btn-danger" type="submit">Reject</button>
            <a class="btn btn-secondary" href="order-details.php?id=<?php echo urlencode((string)$r["order_id"]); ?>">Cancel</a>
          </div>
        </form>
      </div>

    </main>
  </div>
  <?php include "includes/footer.php"; ?>
</div>