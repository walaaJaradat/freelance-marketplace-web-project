<?php
require_once "includes/init.php";

$Title = "Upload Delivery";
$activePage = "my-orders.php";

if (!isset($_SESSION["user"])) { header("Location: login.php"); exit; }

$userId  = (string)($_SESSION["user"]["id"] ?? "");
$role    = (string)($_SESSION["user"]["role"] ?? "");
$orderId = trim((string)($_GET["id"] ?? ""));

$mode = trim((string)($_GET["mode"] ?? ""));
$revId = trim((string)($_GET["rev"] ?? ""));

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }
function str_len($s){
  $s = (string)$s;
  return function_exists("mb_strlen") ? mb_strlen($s, "UTF-8") : strlen($s);
}
function ensure_dir($p){
  if (is_dir($p)) return true;
  return @mkdir($p, 0775, true);
}

if ($role !== "Freelancer" || $orderId === "") {
  $_SESSION["flash_error"] = "Access denied.";
  header("Location: my-orders.php");
  exit;
}

$stmt = $pdo->prepare("
  SELECT order_id, freelancer_id, status, service_title
  FROM orders
  WHERE order_id=? AND freelancer_id=?
  LIMIT 1
");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order || !in_array((string)$order["status"], ["In Progress","Revision Requested"], true)) {
  $_SESSION["flash_error"] = "Upload not allowed.";
  header("Location: my-orders.php");
  exit;
}

$isRevisionUpload = ($mode === "revision");

$revisionRow = null;
if ($isRevisionUpload) {
  if ((string)$order["status"] !== "Revision Requested" || $revId === "") {
    $_SESSION["flash_error"] = "Revision upload not allowed.";
    header("Location: order-details.php?id=" . urlencode($orderId));
    exit;
  }

  $rs = $pdo->prepare("
    SELECT revision_id, order_id, request_status
    FROM revision_requests
    WHERE revision_id=? AND order_id=? AND request_status='Accepted'
    LIMIT 1
  ");
  $rs->execute([$revId, $orderId]);
  $revisionRow = $rs->fetch(PDO::FETCH_ASSOC);

  if (!$revisionRow) {
    $_SESSION["flash_error"] = "Revision upload not allowed (no accepted revision).";
    header("Location: order-details.php?id=" . urlencode($orderId));
    exit;
  }
}

$error = "";
$message = "";
$notes = "";

$MAX_FILES = 5;
$MAX_SIZE  = 50 * 1024 * 1024; // 50MB

$projectRootAbs = rtrim(__DIR__, "/\\");
$uploadsAbs     = $projectRootAbs . "/uploads";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

  $message = trim((string)($_POST["message"] ?? ""));
  $notes   = trim((string)($_POST["notes"] ?? ""));

  if (str_len($message) < 50 || str_len($message) > 500) {
    $error = "Delivery message must be 50–500 characters.";
  } else {

    $names = $_FILES["files"]["name"] ?? [];
    $tmpn  = $_FILES["files"]["tmp_name"] ?? [];
    $errs  = $_FILES["files"]["error"] ?? [];
    $sizes = $_FILES["files"]["size"] ?? [];

    if (!is_array($names)) $names = [];
    if (!is_array($tmpn))  $tmpn  = [];
    if (!is_array($errs))  $errs  = [];
    if (!is_array($sizes)) $sizes = [];

    $selected = 0;
    for ($i=0; $i<count($names); $i++) {
      if (!empty($names[$i])) $selected++;
    }

    if ($selected < 1) {
      $error = "At least one file is required.";
    } elseif ($selected > $MAX_FILES) {
      $error = "Maximum $MAX_FILES files allowed.";
    } else {

      $baseWeb = "/uploads/orders/" . $orderId . ($isRevisionUpload ? "/revisions" : "/deliverables");

      $baseDisk = $uploadsAbs . "/orders/" . $orderId . ($isRevisionUpload ? "/revisions" : "/deliverables");

      if (!ensure_dir($baseDisk)) {
        $error = "Server cannot create upload folder. Check permissions for: " . $baseDisk;
      } else {

        $fileType = $isRevisionUpload ? "revision" : "deliverable";

        for ($i=0; $i<count($names); $i++) {
          if (empty($names[$i])) continue;

          if (($errs[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $error = "File upload failed.";
            break;
          }

          $size = (int)($sizes[$i] ?? 0);
          if ($size > $MAX_SIZE) {
            $error = "Each file must be ≤ 50MB.";
            break;
          }

          $orig = (string)$names[$i];
          $safe = preg_replace('/[^a-zA-Z0-9._-]+/','_',$orig);

          // random_bytes ممكن تكون مشكلة نادرة، بس غالبًا شغالة. نخلي fallback.
          $rand = function_exists("random_bytes") ? bin2hex(random_bytes(3)) : bin2hex(openssl_random_pseudo_bytes(3));
          $unique = time() . "_" . $rand . "_" . $safe;

          $destDisk = rtrim($baseDisk, "/\\") . "/" . $unique;
          $destWeb  = rtrim($baseWeb, "/") . "/" . $unique;

          if (!move_uploaded_file($tmpn[$i], $destDisk)) {
            $error = "Could not save uploaded file.";
            break;
          }

          $pdo->prepare("
            INSERT INTO file_attachments
              (order_id, file_path, original_filename, file_size, file_type, upload_timestamp)
            VALUES
              (?, ?, ?, ?, ?, NOW())
          ")->execute([$orderId, $destWeb, $orig, $size, $fileType]);
        }

        if ($error === "") {

          $pdo->prepare("UPDATE orders SET status='Delivered' WHERE order_id=?")->execute([$orderId]);

          if ($isRevisionUpload) {
            $resp = "Accepted. Revised work uploaded.";
            $resp .= "\nMessage: " . $message;
            if ($notes !== "") $resp .= "\nNotes: " . $notes;

            $pdo->prepare("
              UPDATE revision_requests
              SET freelancer_response = ?,
                  response_date = NOW()
              WHERE revision_id=? AND order_id=? AND request_status='Accepted'
            ")->execute([$resp, $revId, $orderId]);
          }

          $_SESSION["flash_success"] = $isRevisionUpload ? "Revision uploaded successfully." : "Delivery uploaded successfully.";
          header("Location: order-details.php?id=" . urlencode($orderId));
          exit;
        }
      }
    }
  }
}

include "includes/header.php";
?>

<div class="wrapper">
  <div class="container">
    <?php include "includes/nav.php"; ?>

    <main class="main-content">
      <h1 class="heading-primary"><?php echo $isRevisionUpload ? "Upload Revision" : "Upload Delivery"; ?></h1>

      <?php if (!empty($error)): ?>
        <div class="message-error"><?php echo h($error); ?></div>
      <?php endif; ?>

      <div class="create-service-card">
        <div class="text-muted uc11-upload-hint">
          Order: <b>#<?php echo h($orderId); ?></b> — <?php echo h($order["service_title"] ?? ""); ?><br>
          <?php echo $isRevisionUpload ? "This upload will be saved as a <b>Revision</b>." : "This upload will be saved as a <b>Delivery</b>."; ?>
        </div>

        <form method="post" enctype="multipart/form-data">
          <div class="form-group">
            <label class="form-label">Delivery Message <span class="req">*</span></label>
            <textarea class="form-input" name="message" rows="4" required
              placeholder="Write a delivery message (50–500 characters)"><?php echo h($message); ?></textarea>
          </div>

          <div class="form-group">
            <label class="form-label">Files <span class="req">*</span></label>
            <input class="form-input" type="file" name="files[]" multiple required>
            <div class="form-hint">Upload 1–5 files. Max size: 50MB per file. Any format.</div>
          </div>

          <div class="form-group">
            <label class="form-label">Optional Notes <span class="optional">(optional)</span></label>
            <textarea class="form-input" name="notes" rows="3" placeholder="Additional notes (optional)"><?php echo h($notes); ?></textarea>
          </div>

          <div class="action-row">
            <button class="btn btn-primary" type="submit"><?php echo $isRevisionUpload ? "Upload Revision" : "Upload Delivery"; ?></button>
            <a class="btn btn-secondary" href="order-details.php?id=<?php echo urlencode($orderId); ?>">Cancel</a>
          </div>
        </form>

      </div>
    </main>
  </div>

  <?php include "includes/footer.php"; ?>
</div>