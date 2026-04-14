<?php
require_once "includes/init.php";

$Title = "My Orders";
$activePage = "my-orders.php";

if (!isset($_SESSION["user"])) {
  $_SESSION["flash_error"] = "Please login to view orders.";
  header("Location: login.php");
  exit;
}

$userId = (string)($_SESSION["user"]["id"] ?? "");
$role   = (string)($_SESSION["user"]["role"] ?? "");

if (!isset($baseUrl) || (string)$baseUrl === "") {
  $baseUrl = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"])), "/");
  $baseUrl = ($baseUrl === "" ? "/" : $baseUrl . "/");
}

if (!function_exists("h")) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }
}

function avatar_url($path, $baseUrl){
  $path = trim((string)$path);

  if ($path === "") {
    return $baseUrl . "uploads/profiles/user-default.jpg";
  }

  if (preg_match('/^https?:\/\//i', $path)) {
    return $path;
  }

  return $baseUrl . ltrim($path, "/");
}

$filter = trim((string)($_GET["status"] ?? "All"));
$allowed = ["All","Pending","In Progress","Delivered","Completed","Cancelled","Revision Requested"];
if (!in_array($filter, $allowed, true)) $filter = "All";

if (!in_array($role, ["Client","Freelancer"], true)) {
  $_SESSION["flash_error"] = "Access denied.";
  header("Location: login.php");
  exit;
}

$whereRole   = ($role === "Client") ? "o.client_id = :uid" : "o.freelancer_id = :uid";
$whereStatus = ($filter === "All") ? "" : " AND o.status = :st";

$sql = "
  SELECT
    o.order_id, o.service_title, o.price, o.status, o.order_date, o.expected_delivery,
    u.user_id AS other_id,
    CONCAT(u.first_name,' ',u.last_name) AS other_name,
    u.profile_photo AS other_avatar
  FROM orders o
  JOIN users u ON u.user_id = " . (($role === "Client") ? "o.freelancer_id" : "o.client_id") . "
  WHERE $whereRole
  $whereStatus
  ORDER BY o.order_date DESC
";

$stmt = $pdo->prepare($sql);
$params = [":uid" => $userId];
if ($filter !== "All") $params[":st"] = $filter;
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

function badge_class($st){
  $s = strtolower(trim((string)$st));
  switch ($s) {
    case "pending": return "badge badge-status status-pending";
    case "in progress": return "badge badge-status status-in-progress";
    case "delivered": return "badge badge-status status-delivered";
    case "completed": return "badge badge-status status-completed";
    case "cancelled": return "badge badge-status status-cancelled";
    case "revision requested": return "badge badge-status status-revision";
    default: return "badge badge-status";
  }
}

include "includes/header.php";
?>

<div class="wrapper">
  <div class="container">
    <?php include "includes/nav.php"; ?>

    <main class="main-content">
      <h1 class="heading-primary">My Orders</h1>

      <?php if (!empty($_SESSION["flash_error"])): ?>
        <div class="alert alert-danger"><?php echo h($_SESSION["flash_error"]); ?></div>
        <?php unset($_SESSION["flash_error"]); ?>
      <?php endif; ?>

      <?php if (!empty($_SESSION["flash_success"])): ?>
        <div class="alert alert-success"><?php echo h($_SESSION["flash_success"]); ?></div>
        <?php unset($_SESSION["flash_success"]); ?>
      <?php endif; ?>

      <div class="orders-toolbar">
        <div class="text-muted">
          <?php echo ($role === "Client") ? "Orders you placed" : "Orders assigned to you"; ?>
        </div>

        <form method="get" action="my-orders.php" class="orders-filter-form">
          <select class="form-select" name="status">
            <?php foreach ($allowed as $opt): ?>
              <option value="<?php echo h($opt); ?>" <?php echo ($opt === $filter) ? "selected" : ""; ?>>
                <?php echo h($opt); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-primary btn-sm" type="submit">Apply</button>
        </form>
      </div>

      <?php if (empty($orders)): ?>
        <div class="alert alert-info">No orders found.</div>
      <?php else: ?>
        <div class="table-scroll">
          <table class="services-table">
            <thead>
              <tr>
                <th>Order ID</th>
                <th>Service</th>
                <th><?php echo ($role === "Client") ? "Freelancer" : "Client"; ?></th>
                <th>Price (Total)</th>
                <th>Status</th>
                <th>Order Date</th>
                <th>Expected Delivery</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($orders as $o): ?>
                <?php
                  $price = (float)($o["price"] ?? 0);
                  $fee   = round($price * 0.05, 2);
                  $total = round($price + $fee, 2);

                  $avatar = avatar_url($o["other_avatar"] ?? "", $baseUrl);
                ?>
                <tr>
                  <td>
                    <a class="action-link" href="order-details.php?id=<?php echo urlencode((string)$o["order_id"]); ?>">
                      <?php echo h($o["order_id"]); ?>
                    </a>
                  </td>

                  <td><?php echo h($o["service_title"]); ?></td>

                  <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                      <img
                        src="<?php echo h($avatar); ?>"
                        alt="User"
                        style="width:32px;height:32px;border-radius:50%;object-fit:cover;border:1px solid #ddd;"
                        onerror="this.onerror=null;this.src='<?php echo h($baseUrl . "uploads/profiles/user-default.jpg"); ?>';"
                      >
                      <a class="action-link" href="profile.php?id=<?php echo urlencode((string)$o["other_id"]); ?>">
                        <?php echo h($o["other_name"]); ?>
                      </a>
                    </div>
                  </td>

                  <td>$<?php echo number_format($total, 2); ?></td>

                  <td>
                    <span class="<?php echo h(badge_class($o["status"])); ?>">
                      <?php echo h($o["status"]); ?>
                    </span>
                  </td>

                  <td><?php echo h(date("M d, Y", strtotime((string)$o["order_date"]))); ?></td>
                  <td><?php echo h(date("M d, Y", strtotime((string)$o["expected_delivery"]))); ?></td>

                  <td>
                    <a class="btn btn-primary btn-sm" href="order-details.php?id=<?php echo urlencode((string)$o["order_id"]); ?>">
                      View Details
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

    </main>

    <aside class="sidebar">
      <h3 class="heading-secondary">Orders</h3>
      <p class="text-muted">Track, deliver, revise, and complete orders.</p>
    </aside>
  </div>

  <?php include "includes/footer.php"; ?>
</div>