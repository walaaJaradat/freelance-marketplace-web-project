<?php
require_once "includes/init.php";

$Title = "Order Details";
$activePage = "my-orders.php";

if (!isset($_SESSION["user"])) {
  $_SESSION["flash_error"] = "Please login.";
  header("Location: login.php");
  exit;
}

$userId  = (string)($_SESSION["user"]["id"] ?? "");
$role    = (string)($_SESSION["user"]["role"] ?? "");
$orderId = trim((string)($_GET["id"] ?? ""));

if ($orderId === "") {
  $_SESSION["flash_error"] = "Missing order id.";
  header("Location: my-orders.php");
  exit;
}

if (!isset($baseUrl) || (string)$baseUrl === "") {
  $baseUrl = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"])), "/");
  $baseUrl = ($baseUrl === "" ? "/" : $baseUrl . "/");
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }
function money_fmt($n){ return "$" . number_format((float)$n, 2); }

function avatar_url($path, $baseUrl){
  $path = trim((string)$path);

  if ($path === "") {
    return rtrim($baseUrl, "/") . "/uploads/profiles/user-default.jpg";
  }

  if (preg_match('/^https?:\/\//i', $path)) {
    return $path;
  }

  return rtrim($baseUrl, "/") . "/" . ltrim($path, "/");
}

function badge_class($st){
  $s = strtolower(trim((string)$st));

  switch ($s) {
    case "pending":
      return "badge badge-status status-pending";
    case "in progress":
      return "badge badge-status status-in-progress";
    case "delivered":
      return "badge badge-status status-delivered";
    case "completed":
      return "badge badge-status status-completed";
    case "cancelled":
      return "badge badge-status status-cancelled";
    case "revision requested":
      return "badge badge-status status-revision";
    default:
      return "badge badge-status";
  }
}

function extract_block($text, $blockTitle){
  $text = (string)$text;
  $pattern = '/=====\\s*' . preg_quote($blockTitle, '/') . '\\s*\\(.*?\\)\\s*=====\\n(.*?)\\n=====\\s*END\\s*' . preg_quote($blockTitle, '/') . '\\s*=====/s';
  if (preg_match($pattern, $text, $m)) {
    return (string)$m[1];
  }
  return "";
}

function parse_kv_lines($blockText){
  $lines = preg_split("/\\r\\n|\\r|\\n/", (string)$blockText);
  $out = [];
  foreach ($lines as $ln) {
    $ln = trim($ln);
    if ($ln === "") continue;
    $pos = strpos($ln, ":");
    if ($pos === false) continue;
    $k = strtolower(trim(substr($ln, 0, $pos)));
    $v = trim(substr($ln, $pos + 1));
    $out[$k] = $v;
  }
  return $out;
}

$stmt = $pdo->prepare("
  SELECT
    o.*,
    s.category AS service_category,
    s.subcategory AS service_subcategory,
    s.delivery_time AS service_delivery_time,
    s.revisions_included AS service_revisions_included,
    s.image_1 AS service_image
  FROM orders o
  LEFT JOIN services s ON s.service_id = o.service_id
  WHERE o.order_id = :oid
    AND (o.client_id = :uid OR o.freelancer_id = :uid)
  LIMIT 1
");
$stmt->execute([":oid" => $orderId, ":uid" => $userId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
  $_SESSION["flash_error"] = "Order not found or access denied.";
  header("Location: my-orders.php");
  exit;
}

$status = (string)($order["status"] ?? "");
$isClientOwner = ((string)$order["client_id"] === $userId);
$isFreelancerOwner = ((string)$order["freelancer_id"] === $userId);

$otherUserId = $isClientOwner ? (string)$order["freelancer_id"] : (string)$order["client_id"];
$otherRoleLabel = $isClientOwner ? "Freelancer" : "Client";

$u = $pdo->prepare("SELECT user_id, first_name, last_name, profile_photo FROM users WHERE user_id=? LIMIT 1");
$u->execute([$otherUserId]);
$other = $u->fetch(PDO::FETCH_ASSOC) ?: [];
$otherName = trim(($other["first_name"] ?? "") . " " . ($other["last_name"] ?? ""));

$otherPhoto = avatar_url((string)($other["profile_photo"] ?? ""), $baseUrl);

/* pricing */
$servicePrice = (float)($order["price"] ?? 0);
$fee = round($servicePrice * 0.05, 2);
$total = round($servicePrice + $fee, 2);

/* dates */
$orderDate = $order["order_date"] ?? null;
$expectedDelivery = $order["expected_delivery"] ?? null;
$completionDate = $order["completion_date"] ?? null;

$dd = $pdo->prepare("
  SELECT MAX(upload_timestamp)
  FROM file_attachments
  WHERE order_id=? AND file_type='deliverable'
");
$dd->execute([$orderId]);
$deliveredAt = $dd->fetchColumn();

/* attachments */
$filesStmt = $pdo->prepare("
  SELECT file_id, file_path, original_filename, file_size, file_type, upload_timestamp
  FROM file_attachments
  WHERE order_id=?
  ORDER BY upload_timestamp DESC
");
$filesStmt->execute([$orderId]);
$attachments = $filesStmt->fetchAll(PDO::FETCH_ASSOC);

$requirementsFiles = [];
$deliverableFiles  = [];
$revisionFiles     = [];
foreach ($attachments as $f) {
  $t = (string)($f["file_type"] ?? "");
  if ($t === "requirement") $requirementsFiles[] = $f;
  if ($t === "deliverable") $deliverableFiles[]  = $f;
  if ($t === "revision") $revisionFiles[]         = $f;
}

/* revisions */
$revAllowed = (int)($order["service_revisions_included"] ?? $order["revisions_included"] ?? 0);
$unlimited = ($revAllowed === 999);

$revStmt = $pdo->prepare("
  SELECT revision_id, revision_notes, request_status, freelancer_response, request_date, response_date
  FROM revision_requests
  WHERE order_id=?
  ORDER BY request_date ASC
");
$revStmt->execute([$orderId]);
$revisions = $revStmt->fetchAll(PDO::FETCH_ASSOC);

$totalReq = count($revisions);
$accepted = 0; $rejected = 0; $pending = 0;
foreach ($revisions as $r) {
  $st = (string)($r["request_status"] ?? "Pending");
  if ($st === "Accepted") $accepted++;
  elseif ($st === "Rejected") $rejected++;
  else $pending++;
}
$usedCount = $accepted + $rejected;
$remaining = $unlimited ? null : max(0, $revAllowed - $usedCount);
$remainingText = $unlimited ? "Unlimited" : ($remaining . "/" . $revAllowed);

$canRequestRevision = false;
if ($isClientOwner && $status === "Delivered") {
  $canRequestRevision = $unlimited ? true : ($usedCount < $revAllowed);
}

$latestPending = null;
foreach (array_reverse($revisions) as $r) {
  if ((string)($r["request_status"] ?? "") === "Pending") { $latestPending = $r; break; }
}

$cancelBlock = extract_block((string)($order["deliverable_notes"] ?? ""), "UC11_CANCEL");
$cancelInfo = $cancelBlock !== "" ? parse_kv_lines($cancelBlock) : null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if (isset($_POST["start_working"])) {
    if ($isFreelancerOwner && $status === "Pending") {
      $pdo->prepare("UPDATE orders SET status='In Progress' WHERE order_id=?")->execute([$orderId]);
      $_SESSION["flash_success"] = "Order status updated to In Progress.";
    } else {
      $_SESSION["flash_error"] = "Action not allowed.";
    }
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
      <h1 class="heading-primary">Order #<?php echo h($orderId); ?></h1>

      <?php if (!empty($_SESSION["flash_error"])): ?>
        <div class="alert alert-danger"><?php echo h($_SESSION["flash_error"]); ?></div>
        <?php unset($_SESSION["flash_error"]); ?>
      <?php endif; ?>

      <?php if (!empty($_SESSION["flash_success"])): ?>
        <div class="alert alert-success"><?php echo h($_SESSION["flash_success"]); ?></div>
        <?php unset($_SESSION["flash_success"]); ?>
      <?php endif; ?>

      <?php if ($status === "Cancelled"): ?>
        <div class="alert alert-warning">
          This order is cancelled. No further actions are available.
          <?php if (is_array($cancelInfo)): ?>
            <div class="text-muted">
              <?php if (!empty($cancelInfo["date"])): ?>
                Cancelled at: <strong><?php echo h($cancelInfo["date"]); ?></strong><br>
              <?php endif; ?>
              <?php if (!empty($cancelInfo["reason"])): ?>
                Reason: <strong><?php echo h($cancelInfo["reason"]); ?></strong>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- Order Information -->
      <div class="create-service-card">
        <div class="kv">
          <div class="k">Service</div>
          <div class="v"><?php echo h($order["service_title"] ?? ""); ?></div>

          <div class="k">Category</div>
          <div class="v">
            <?php echo h($order["service_category"] ?? "-"); ?>
            <?php if (!empty($order["service_subcategory"])): ?>
              <span class="text-muted"> &gt; <?php echo h($order["service_subcategory"]); ?></span>
            <?php endif; ?>
          </div>

          <div class="k">Status</div>
          <div class="v"><span class="<?php echo h(badge_class($status)); ?>"><?php echo h($status); ?></span></div>

          <div class="k">Order Date</div>
          <div class="v"><?php echo $orderDate ? h(date("M d, Y", strtotime($orderDate))) : "-"; ?></div>

          <div class="k">Expected Delivery</div>
          <div class="v"><?php echo $expectedDelivery ? h(date("M d, Y", strtotime($expectedDelivery))) : "-"; ?></div>

          <div class="k">Actual Delivery</div>
          <div class="v"><?php echo $deliveredAt ? h(date("M d, Y H:i", strtotime($deliveredAt))) : "-"; ?></div>

          <div class="k">Completion Date</div>
          <div class="v"><?php echo $completionDate ? h(date("M d, Y H:i", strtotime($completionDate))) : "-"; ?></div>

          <div class="k">Service Price</div>
          <div class="v"><?php echo h(money_fmt($servicePrice)); ?></div>

          <div class="k">Service Fee (5%)</div>
          <div class="v"><?php echo h(money_fmt($fee)); ?></div>

          <div class="k"><strong>Total</strong></div>
          <div class="v"><strong><?php echo h(money_fmt($total)); ?></strong></div>

          <div class="k">Delivery Time</div>
          <div class="v"><?php echo (int)($order["service_delivery_time"] ?? $order["delivery_time"] ?? 0); ?> days</div>

          <div class="k">Revisions Included</div>
          <div class="v"><?php echo $unlimited ? "Unlimited" : h($revAllowed); ?></div>
        </div>
      </div>

      <div class="user-card user-card-service">
        <img
          class="user-photo"
          src="<?php echo h($otherPhoto); ?>"
          alt="User Photo"
          onerror="this.onerror=null;this.src='<?php echo h(rtrim($baseUrl,'/') . '/uploads/profiles/user-default.jpg'); ?>';"
        >
        <div class="user-card-info">
          <div class="user-meta"><?php echo h($otherRoleLabel); ?></div>
          <div class="user-name"><?php echo h($otherName ?: ("User #" . $otherUserId)); ?></div>
          <a class="action-link" href="profile.php?id=<?php echo urlencode($otherUserId); ?>">View Profile</a>
        </div>
      </div>

      <div class="create-service-card card-section">
        <h2 class="heading-secondary">Service Requirements</h2>
        <div class="requirements-block">
          <div class="requirements-title">Requirements</div>
          <div class="requirements-text"><?php echo nl2br(h($order["requirements"] ?? "")); ?></div>
        </div>

        <h3 class="heading-secondary section-subtitle">Uploaded Requirement Files</h3>
        <?php if (empty($requirementsFiles)): ?>
          <p class="text-muted">No requirement files.</p>
        <?php else: ?>
          <div class="table-scroll">
            <table class="services-table">
              <thead>
                <tr>
                  <th>File</th>
                  <th>Size</th>
                  <th>Date</th>
                  <th>Download</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($requirementsFiles as $f): ?>
                  <tr>
                    <td><a class="action-link" href="<?php echo h($f["file_path"]); ?>" target="_blank"><?php echo h($f["original_filename"]); ?></a></td>
                    <td><?php echo h(round(((int)$f["file_size"]) / 1024, 1)); ?> KB</td>
                    <td><?php echo h(date("M d, Y", strtotime((string)$f["upload_timestamp"]))); ?></td>
                    <td><a class="action-link" href="<?php echo h($f["file_path"]); ?>" download>Download</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <!-- History -->
      <div class="create-service-card card-section">
        <h2 class="heading-secondary">Order History</h2>

        <h3 class="heading-secondary section-subtitle">Delivery Files</h3>
        <?php if (empty($deliverableFiles)): ?>
          <p class="text-muted">No delivery files uploaded yet.</p>
        <?php else: ?>
          <div class="table-scroll">
            <table class="services-table">
              <thead><tr><th>File</th><th>Size</th><th>Date</th><th>Download</th></tr></thead>
              <tbody>
                <?php foreach ($deliverableFiles as $f): ?>
                  <tr>
                    <td><a class="action-link" href="<?php echo h($f["file_path"]); ?>" target="_blank"><?php echo h($f["original_filename"]); ?></a></td>
                    <td><?php echo h(round(((int)$f["file_size"]) / 1024, 1)); ?> KB</td>
                    <td><?php echo h(date("M d, Y", strtotime((string)$f["upload_timestamp"]))); ?></td>
                    <td><a class="action-link" href="<?php echo h($f["file_path"]); ?>" download>Download</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

        <h3 class="heading-secondary section-subtitle">Revision Files</h3>
        <?php if (empty($revisionFiles)): ?>
          <p class="text-muted">No revision files.</p>
        <?php else: ?>
          <div class="table-scroll">
            <table class="services-table">
              <thead><tr><th>File</th><th>Size</th><th>Date</th><th>Download</th></tr></thead>
              <tbody>
                <?php foreach ($revisionFiles as $f): ?>
                  <tr>
                    <td><a class="action-link" href="<?php echo h($f["file_path"]); ?>" target="_blank"><?php echo h($f["original_filename"]); ?></a></td>
                    <td><?php echo h(round(((int)$f["file_size"]) / 1024, 1)); ?> KB</td>
                    <td><?php echo h(date("M d, Y", strtotime((string)$f["upload_timestamp"]))); ?></td>
                    <td><a class="action-link" href="<?php echo h($f["file_path"]); ?>" download>Download</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <!-- Actions -->
      <div class="create-service-card card-section">
        <h2 class="heading-secondary">Actions</h2>

        <?php if ($status === "Pending"): ?>

          <?php if ($isClientOwner): ?>
            <a class="btn btn-danger" href="cancel-order.php?id=<?php echo urlencode($orderId); ?>">Cancel Order</a>
          <?php endif; ?>

          <?php if ($isFreelancerOwner): ?>
            <form method="post" class="action-form">
              <button class="btn btn-primary" type="submit" name="start_working">Start Working</button>
            </form>
          <?php endif; ?>

        <?php elseif ($status === "In Progress"): ?>

          <?php if ($isFreelancerOwner): ?>
            <a class="btn btn-primary" href="upload-delivery.php?id=<?php echo urlencode($orderId); ?>">Upload Delivery</a>
          <?php else: ?>
            <div class="alert alert-info">No actions available. Waiting for delivery.</div>
          <?php endif; ?>

        <?php elseif ($status === "Delivered"): ?>

          <?php if ($isClientOwner): ?>
            <a class="btn btn-primary" href="confirm-complete.php?id=<?php echo urlencode($orderId); ?>">Mark as Completed</a>

            <?php if ($canRequestRevision): ?>
              <a class="btn btn-secondary" href="request-revision.php?id=<?php echo urlencode($orderId); ?>">Request Revision</a>
            <?php else: ?>
              <div class="alert alert-warning">You have used all <?php echo (int)$revAllowed; ?> revision requests for this order.</div>
            <?php endif; ?>

          <?php else: ?>
            <div class="alert alert-info">No actions available. Waiting for client approval or revision request.</div>
          <?php endif; ?>

        <?php elseif ($status === "Revision Requested"): ?>

          <?php if ($isClientOwner): ?>
            <div class="alert alert-info">Revision request submitted. Waiting for freelancer response.</div>
          <?php else: ?>

            <?php if ($latestPending): ?>
              <div class="alert alert-warning">
                <strong>New Revision Request</strong><br>
                Client Feedback: "<?php echo h($latestPending["revision_notes"] ?? ""); ?>"
              </div>

              <div class="actions-row">
                <a class="btn btn-primary" href="accept-revision-upload.php?id=<?php echo urlencode((string)$latestPending["revision_id"]); ?>">
                  Accept & Upload Revision
                </a>
                <a class="btn btn-danger" href="reject-revision.php?id=<?php echo urlencode((string)$latestPending["revision_id"]); ?>">
                  Reject Request
                </a>
              </div>
            <?php else: ?>
              <div class="alert alert-info">No new revision requests.</div>
            <?php endif; ?>

          <?php endif; ?>

        <?php elseif ($status === "Completed"): ?>

          <div class="alert alert-success">Order is completed. You can view delivery files and order history.</div>
          <a class="btn btn-secondary" href="leave-review.php?order_id=<?php echo urlencode($orderId); ?>">Leave Review</a>

        <?php elseif ($status === "Cancelled"): ?>

          <div class="alert alert-warning">Order is cancelled. View only.</div>

        <?php else: ?>

          <div class="alert alert-info">No actions available.</div>

        <?php endif; ?>
      </div>

      <!-- Revision History -->
      <div class="create-service-card card-section">
        <h2 class="heading-secondary">Revision History</h2>

        <div class="revision-summary">
          <div class="stat-box"><div class="stat-number"><?php echo (int)$totalReq; ?></div><div class="stat-label">Total Requests</div></div>
          <div class="stat-box stat-accepted"><div class="stat-number"><?php echo (int)$accepted; ?></div><div class="stat-label">Accepted</div></div>
          <div class="stat-box stat-rejected"><div class="stat-number"><?php echo (int)$rejected; ?></div><div class="stat-label">Rejected</div></div>
          <div class="stat-box stat-pending"><div class="stat-number"><?php echo (int)$pending; ?></div><div class="stat-label">Pending</div></div>
          <div class="stat-box"><div class="stat-number"><?php echo h($remainingText); ?></div><div class="stat-label">Revisions Remaining</div></div>
        </div>

        <?php if (empty($revisions)): ?>
          <p class="text-muted">No revision requests.</p>
        <?php else: ?>
          <div class="table-scroll">
            <table class="services-table revision-table">
              <thead>
                <tr>
                  <th>Request #</th>
                  <th>Date</th>
                  <th>Description</th>
                  <th>Status</th>
                  <th>Response</th>
                  <th>Response Date</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($revisions as $i => $r): ?>
                  <?php
                    $st = (string)($r["request_status"] ?? "Pending");
                    $ucSt = ($st === "Pending") ? "New" : $st;
                  ?>
                  <tr>
                    <td><?php echo (int)($i + 1); ?></td>
                    <td><?php echo !empty($r["request_date"]) ? h(date("M d, Y", strtotime((string)$r["request_date"]))) : "-"; ?></td>
                    <td><?php echo h($r["revision_notes"] ?? ""); ?></td>
                    <td><?php echo h($ucSt); ?></td>
                    <td><?php echo h(($r["freelancer_response"] ?? "") !== "" ? $r["freelancer_response"] : "-"); ?></td>
                    <td><?php echo !empty($r["response_date"]) ? h(date("M d, Y", strtotime((string)$r["response_date"]))) : "-"; ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

    </main>

    <aside class="sidebar">
      <h3 class="heading-secondary">Order Timeline</h3>
      <p class="text-muted"></p>
    </aside>

  </div>

  <?php include "includes/footer.php"; ?>
</div>