<nav class="navigation side-nav">
  <ul class="nav-list">
    <?php if (!$isLoggedIn): ?>
      <!-- Guest navigation -->
      <li class="nav-item">
        <a class="nav-link <?php echo ($activePage == 'home.php') ? 'nav-link-active' : ''; ?>" href="home.php">Home</a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?php echo ($activePage == 'browse-services.php') ? 'nav-link-active' : ''; ?>" href="browse-services.php">Browse Services</a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?php echo ($activePage == 'login.php') ? 'nav-link-active' : ''; ?>" href="login.php">Login</a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?php echo ($activePage == 'register.php') ? 'nav-link-active' : ''; ?>" href="register.php">Sign Up</a>
      </li>

    <?php elseif ($userRole == "Client"): ?>
      <!-- Client navigation -->
      <li class="nav-item">
        <a class="nav-link <?php echo ($activePage == 'index.html') ? 'nav-link-active' : ''; ?>" href="home.php">Home</a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo ($activePage == 'browse-services.php') ? 'nav-link-active' : ''; ?>" href="browse-services.php">Browse Services</a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo ($activePage == 'cart.php') ? 'nav-link-active' : ''; ?>" href="cart.php">Shopping Cart</a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo ($activePage == 'my-orders.php') ? 'nav-link-active' : ''; ?>" href="my-orders.php">My Orders</a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo ($activePage == 'profile.php') ? 'nav-link-active' : ''; ?>" href="profile.php">My Profile</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="logout.php">Logout</a>
      </li>

    <?php elseif ($userRole == "Freelancer"): ?>
      <!-- Freelancer navigation -->
      <li class="nav-item">
        <a class="nav-link <?php echo ($activePage == 'dashboard.php') ? 'nav-link-active' : ''; ?>" href="dashboard.php">Home</a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo ($activePage == 'browse-services.php') ? 'nav-link-active' : ''; ?>" href="browse-services.php">Browse Services</a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?php echo ($activePage == 'my-services.php') ? 'nav-link-active' : ''; ?>" href="my-services.php">My Services</a>
      </li>


      <li class="nav-item">
        <a class="nav-link <?php echo ($activePage == 'my-orders.php') ? 'nav-link-active' : ''; ?>" href="my-orders.php">My Orders</a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo ($activePage == 'profile.php') ? 'nav-link-active' : ''; ?>" href="profile.php">My Profile</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="logout.php">Logout</a>
      </li>
    <?php endif; ?>
  </ul>
</nav>
