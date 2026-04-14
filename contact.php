<?php
require_once "includes/init.php";
$Title = "Contact Us";
$activePage = "contact.php";
include "includes/header.php";
?>

<div class="page-wrap no-sidebar">
  <div class="layout">
    <?php include "includes/nav.php"; ?>

    <main class="main-content">
      <h1>Contact Us</h1>

      <p class="text-muted">
        We’re here to help. If you have any questions, feedback, or need support, feel free to reach out.
      </p>

      <h3>Support Channels</h3>
      <p><strong>Email:</strong> freelanceMarketplace@email.com</p>
      <p><strong>Phone:</strong> +972 56 942 4046</p>

      <h3>Working Hours</h3>
      <p>Sunday – Thursday: 9:00 AM – 5:00 PM</p>
      <p>Friday – Saturday: Closed</p>

      <h3>Common Topics</h3>
      <ul>
        <li>Account access / login issues</li>
        <li>Orders, payments, and refunds</li>
        <li>Service publishing and approval</li>
        <li>Reporting a user or a service</li>
        <li>Technical issues (bugs, pages not loading)</li>
      </ul>

      <p class="text-muted">
        Please include your username (or email) and a short description of the issue to help us assist you faster.
      </p>
    </main>

    <aside class="sidebar"></aside>
  </div>

  <?php include "includes/footer.php"; ?>
</div>