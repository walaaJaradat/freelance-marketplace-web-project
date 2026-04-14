<?php
require_once "includes/init.php";

$Title = "Dashboard";
$activePage = "dashboard.php";

if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}

if (($_SESSION["user"]["role"] ?? "") !== "Freelancer") {
    header("Location: home.php");
    exit;
}

if (!isset($baseUrl) || (string)$baseUrl === "") {
    $baseUrl = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"])), "/");
    $baseUrl = ($baseUrl === "" ? "/" : $baseUrl . "/");
}

include "includes/header.php";
?>

<div class="wrapper">
  <div class="container">
    <?php include "includes/nav.php"; ?>

    <main class="main-content">
      <h1 class="heading-primary">Freelancer Dashboard</h1>

      <div class="form-container dashboard-card">
        <p class="text-muted">Welcome back! Choose where to go:</p>

        <div class="dashboard-actions">
          <a class="btn btn-primary" href="<?php echo $baseUrl; ?>create-service.php">Create New Service</a>
          <a class="btn btn-secondary" href="<?php echo $baseUrl; ?>browse-services.php">Browse Services</a>
          <a class="btn btn-secondary" href="<?php echo $baseUrl; ?>my-services.php">My Services</a>
          <a class="btn btn-secondary" href="<?php echo $baseUrl; ?>my-orders.php">My Orders</a>
        </div>
      </div>

    </main>

    <aside class="sidebar"></aside>
  </div>

  <?php include "includes/footer.php"; ?>
</div>
