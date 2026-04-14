<?php
require_once "includes/init.php";
$Title = "Privacy Policy";
$activePage = "privacy.php";
include "includes/header.php";
?>

<div class="page-wrap no-sidebar">
  <div class="layout">
    <?php include "includes/nav.php"; ?>

    <main class="main-content">
      <h1>Privacy Policy</h1>

      <p class="text-muted">
        This Privacy Policy explains how we collect, use, and protect your information when you use our marketplace.
      </p>

      <h3>1. Information We Collect</h3>
      <ul>
        <li><strong>Account information:</strong> name, email, phone (if provided), role (Client/Freelancer).</li>
        <li><strong>Profile data:</strong> profile photo, bio, skills, and other details you choose to share.</li>
        <li><strong>Service and order data:</strong> services you publish, items in cart, orders, and transaction records.</li>
        <li><strong>Technical data:</strong> device/browser info, IP address, and basic usage analytics (for security and performance).</li>
      </ul>

      <h3>2. How We Use Your Information</h3>
      <ul>
        <li>To create and manage your account.</li>
        <li>To display services, profiles, and marketplace content.</li>
        <li>To process orders and provide customer support.</li>
        <li>To improve site security, prevent fraud, and fix bugs.</li>
        <li>To communicate important updates related to your account or orders.</li>
      </ul>

      <h3>3. Cookies</h3>
      <p>
        We may use cookies to enhance user experience (e.g., keeping you logged in, remembering preferences, and recently viewed items).
        You can control cookies from your browser settings.
      </p>

      <h3>4. Sharing of Information</h3>
      <p>
        We do not sell your personal information. We only share data when necessary to:
      </p>
      <ul>
        <li>Provide core platform functionality (e.g., showing a freelancer profile to a client).</li>
        <li>Comply with legal requirements or requests.</li>
        <li>Protect the safety, rights, and security of users and the platform.</li>
      </ul>

      <h3>5. Data Security</h3>
      <p>
        We take reasonable security measures to protect your information. However, no method of transmission over the internet is 100% secure.
      </p>

      <h3>6. Your Choices</h3>
      <ul>
        <li>You can update your profile information from your account settings.</li>
        <li>You can request account deletion by contacting support.</li>
        <li>You can disable cookies via browser settings (some features may not work correctly).</li>
      </ul>

      <h3>7. Contact</h3>
      <p>
        If you have questions about this Privacy Policy, contact us at:
        <strong>freelanceMarketplace@gmail.com</strong>
      </p>

      <p class="text-muted">Last updated: <?= date("F Y"); ?></p>
    </main>

    <aside class="sidebar"></aside>
  </div>

  <?php include "includes/footer.php"; ?>
</div>