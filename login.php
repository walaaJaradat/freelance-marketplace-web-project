<?php
require_once "includes/init.php";

$Title = "Login";
$activePage = "login.php";

$message = "";
$emailValue = "";

if (!isset($_SESSION["login_attempts"])) $_SESSION["login_attempts"] = [];
if (!isset($_SESSION["login_lock"])) $_SESSION["login_lock"] = [];

function cleanOldAttempts($arr) {
    $new = [];
    foreach ($arr as $t) {
        if ($t > time() - 900) $new[] = $t;
    }
    return $new;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $emailValue = $email;

    $lockUntil = $_SESSION["login_lock"][$email] ?? 0;

    if (time() < $lockUntil) {
        $minutes = ceil(($lockUntil - time()) / 60);
        $message = "Account temporarily locked. Please try again in $minutes minutes.";
    } else {

        $genericError = "Invalid email or password.";

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = $genericError;
        } else {

            $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user["password"]) && $user["status"] == "Active") {

                session_regenerate_id(true);

                $_SESSION["user"] = [
                    "id" => $user["user_id"],
                    "first_name" => $user["first_name"],
                    "last_name" => $user["last_name"],
                    "role" => $user["role"],
                    "profile_photo" => (!empty($user["profile_photo"]))
                        ? $user["profile_photo"]
                        : "uploads/profiles/user-default.jpg"
                ];

                unset($_SESSION["login_attempts"][$email]);
                unset($_SESSION["login_lock"][$email]);

                if ($user["role"] == "Client") {
                    header("Location: browse-services.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit;

            } else {

                $attempts = $_SESSION["login_attempts"][$email] ?? [];
                $attempts = cleanOldAttempts($attempts);
                $attempts[] = time();
                $_SESSION["login_attempts"][$email] = $attempts;

                $count = count($attempts);
                $remaining = 5 - $count;

                if ($remaining <= 0) {
                    $_SESSION["login_lock"][$email] = time() + (30 * 60);
                    $message = "Account temporarily locked. Please try again in 30 minutes.";
                } else {
                    $message = $genericError;
                    if ($count >= 3) {
                        $message .= " Remaining attempts: $remaining";
                    }
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
      <div class="form-container form-container-sm">
        <h2>Login to Your Account</h2>

        <?php if ($message): ?>
          <div class="message-error"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form method="post" class="form">
          <div class="form-group">
            <label class="form-label">Email <span class="req">*</span></label>
            <input class="form-input" type="email" name="email" required
                   value="<?php echo htmlspecialchars($emailValue); ?>">
          </div>

          <div class="form-group">
            <label class="form-label">Password <span class="req">*</span></label>
            <input class="form-input" type="password" name="password" required>
          </div>

          <div class="form-group">
            <label class="form-label">
              <input type="checkbox" name="remember_me">
              Remember Me <span class="optional">(optional)</span>
            </label>
          </div>

          <div class="form-actions">
            <input class="btn btn-primary" type="submit" value="Login">
          </div>
        </form>

        <p><a class="action-link" href="#">Forgot password?</a></p>
        <p>Don't have an account? <a class="action-link" href="register.php">Sign up</a></p>
      </div>
    </main>

    <aside class="sidebar"></aside>
  </div>

  <?php include "includes/footer.php"; ?>
</div>
