<?php
if (!isset($Title))  $Title = "Freelance Marketplace";
if (!isset($activePage)) $activePage = "";

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$isLoggedIn = isset($_SESSION["user"]);
$userRole   = $isLoggedIn ? ($_SESSION["user"]["role"] ?? "Guest") : "Guest";

$projectRoot = realpath(__DIR__ . "/..");

$homeHref   = ($isLoggedIn && $userRole === "Freelancer") ? "dashboard.php" : "home.php";
$homeActive = ($activePage == "home.php" || $activePage == "dashboard.php") ? "nav-link-active" : "";
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title><?php echo htmlspecialchars($Title); ?></title>
  <link rel="stylesheet" href="css/styles.css">
</head>

<body>

<header class="header">
  <div class="header-left">
    <a class="brand" href="<?php echo htmlspecialchars($homeHref); ?>">Freelance Marketplace</a>
  </div>

  <nav class="navigation top-nav">
    <ul class="nav-links">
      <li class="nav-item">
        <a class="nav-link <?php echo $homeActive; ?>" href="<?php echo htmlspecialchars($homeHref); ?>">Home</a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?php echo ($activePage == 'browse-services.php') ? 'nav-link-active' : ''; ?>"
           href="browse-services.php">Browse Services</a>
      </li>

      <?php if ($isLoggedIn && $userRole == "Client"): ?>

        <li class="nav-item">
          <a class="nav-link <?php echo ($activePage == 'cart.php') ? 'nav-link-active' : ''; ?>"
             href="cart.php">Shopping Cart</a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?php echo ($activePage == 'my-orders.php') ? 'nav-link-active' : ''; ?>"
             href="my-orders.php">My Orders</a>
        </li>

      <?php elseif ($isLoggedIn && $userRole == "Freelancer"): ?>

        <li class="nav-item">
          <a class="nav-link <?php echo ($activePage == 'my-orders.php') ? 'nav-link-active' : ''; ?>"
             href="my-orders.php">My Orders</a>
        </li>

      <?php endif; ?>

      <?php if ($isLoggedIn): ?>
        <li class="nav-item">
          <a class="nav-link <?php echo ($activePage == 'profile.php') ? 'nav-link-active' : ''; ?>"
             href="profile.php">My Profile</a>
        </li>
      <?php endif; ?>
    </ul>
  </nav>

  <div class="header-right">
    <?php if (!$isLoggedIn): ?>

      <a class="btn-link <?php echo ($activePage == 'login.php') ? 'nav-link-active' : ''; ?>"
         href="login.php">Login</a>

      <a class="btn-link <?php echo ($activePage == 'register.php') ? 'nav-link-active' : ''; ?>"
         href="register.php">Sign Up</a>

    <?php else: ?>

      <?php
      $first = $_SESSION["user"]["first_name"] ?? "";
      $last  = $_SESSION["user"]["last_name"] ?? "";
      $fullName = trim($first . " " . $last);

      $photoDb = $_SESSION["user"]["profile_photo"] ?? "uploads/profiles/user-default.jpg";
      $photoDb = ltrim((string)$photoDb, "/"); 

      $photoFs = $projectRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $photoDb);
      if (!$photoFs || !file_exists($photoFs)) {
        $photoDb = "uploads/profiles/user-default.jpg";
      }

      $photoUrl = $photoDb; // مهم: بدون baseUrl

      $roleClass = ($userRole == "Client") ? "role-client" : "role-freelancer";

      $cartCount = (isset($_SESSION["cart"]) && is_array($_SESSION["cart"])) ? count($_SESSION["cart"]) : 0;
      $showCart  = ($userRole === "Client") || ($userRole === "Freelancer" && $cartCount > 0);
      ?>

      <?php if ($showCart): ?>
        <a class="cart-link" href="cart.php" aria-label="Shopping Cart">
          <img class="cart-icon" src="uploads/icons/icon.png" alt="Shopping Cart">
          <?php if ($cartCount > 0): ?>
            <span class="cart-badge"><?php echo (int)$cartCount; ?></span>
          <?php endif; ?>
        </a>
      <?php endif; ?>

      <a class="profile-card <?php echo htmlspecialchars($roleClass); ?>" href="profile.php">
        <img class="user-photo" src="<?php echo htmlspecialchars($photoUrl); ?>" alt="User Photo">
        <span class="user-name"><?php echo htmlspecialchars($fullName); ?></span>
      </a>

      <a class="logout-link" href="logout.php">Logout</a>

    <?php endif; ?>
  </div>
</header>