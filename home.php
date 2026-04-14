<?php
require_once "includes/init.php";

$Title = "Home";
$activePage = "home.php";

include "includes/header.php";
?>

<div class="wrapper">
  <div class="container">
    <?php include "includes/nav.php"; ?>

    <main class="main-content">
      <h1 class="heading-primary">Welcome</h1>
      <p class="text-muted">Welcome to Freelance Marketplace.</p>
    </main>

    <aside class="sidebar"></aside>
  </div>

  <?php include "includes/footer.php"; ?>
</div>
