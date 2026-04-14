<?php
require_once "includes/init.php";
$Title = "Terms of Service";
$activePage = "terms.php";
include "includes/header.php";
?>

<div class="page-wrap no-sidebar">
  <div class="layout">
    <?php include "includes/nav.php"; ?>

    <main class="main-content">
      <h1>Terms of Service</h1>

      <p class="text-muted">
        By using this platform, you agree to the following terms. If you do not agree, please stop using the website.
      </p>

      <h3>1. Platform Overview</h3>
      <p>
        This website is a freelance marketplace that connects Clients with Freelancers. Users can publish services, browse services,
        add to cart, and place orders.
      </p>

      <h3>2. User Accounts</h3>
      <ul>
        <li>You must provide accurate information when creating an account.</li>
        <li>You are responsible for keeping your login credentials private.</li>
        <li>We may suspend accounts that violate platform rules or cause harm.</li>
      </ul>

      <h3>3. Services and Content</h3>
      <ul>
        <li>Freelancers are responsible for the accuracy of their service details, pricing, and delivery time.</li>
        <li>Clients must review service information carefully before ordering.</li>
        <li>Users must not post illegal, harmful, or misleading content.</li>
      </ul>

      <h3>4. Orders and Payments</h3>
      <ul>
        <li>Orders are considered confirmed once placed through the platform.</li>
        <li>Any disputes should be reported to support with order details.</li>
        <li>Refund rules (if enabled in your project) depend on platform policies and the specific case.</li>
      </ul>

      <h3>5. Communication and Behavior</h3>
      <ul>
        <li>Respectful communication is required between all users.</li>
        <li>Harassment, spam, and abuse are not allowed.</li>
        <li>We may take action (warnings, restrictions, suspension) for violations.</li>
      </ul>

      <h3>6. Limitation of Liability</h3>
      <p>
        The platform provides a marketplace environment. We are not responsible for the final outcome of services provided between users.
        We do our best to maintain a safe and stable platform but do not guarantee uninterrupted service.
      </p>

      <h3>7. Changes to Terms</h3>
      <p>
        We may update these Terms from time to time. Continued use of the platform means you accept the latest version.
      </p>

      <h3>8. Contact</h3>
      <p>
        For questions about these Terms, contact:
        <strong>freelanceMarketplace@gmail.com</strong>
      </p>

      <p class="text-muted">Last updated: <?= date("F Y"); ?></p>
    </main>

    <aside class="sidebar"></aside>
  </div>

  <?php include "includes/footer.php"; ?>
</div>