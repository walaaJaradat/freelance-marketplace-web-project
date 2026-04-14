<?php
require_once "includes/init.php";

if (!isset($_SESSION["user"])) { header("Location: login.php"); exit; }

$userId = (string)($_SESSION["user"]["id"] ?? "");
$role   = (string)($_SESSION["user"]["role"] ?? "");
$revId  = trim((string)($_GET["id"] ?? ""));

if ($role !== "Freelancer" || $revId === "") {
  $_SESSION["flash_error"] = "Access denied.";
  header("Location: my-orders.php");
  exit;
}

$stmt = $pdo->prepare("
  SELECT r.revision_id, r.order_id, r.request_status, o.freelancer_id, o.status
  FROM revision_requests r
  JOIN orders o ON o.order_id = r.order_id
  WHERE r.revision_id = ? AND r.request_status = 'Pending'
  LIMIT 1
");
$stmt->execute([$revId]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);

if (
  !$r ||
  (string)$r["freelancer_id"] !== $userId ||
  (string)$r["status"] !== "Revision Requested"
) {
  $_SESSION["flash_error"] = "Revision accept not allowed.";
  header("Location: my-orders.php");
  exit;
}

$pdo->prepare("
  UPDATE revision_requests
  SET request_status='Accepted',
      response_date=NOW()
  WHERE revision_id=?
")->execute([$revId]);

$_SESSION["flash_success"] = "Revision accepted. Please upload the revised work.";

header("Location: upload-delivery.php?id=" . urlencode((string)$r["order_id"]) . "&mode=revision&rev=" . urlencode((string)$revId));
exit;