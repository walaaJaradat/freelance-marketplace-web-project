<?php
require_once "includes/init.php";

$Title = "Register";
$activePage = "register.php";

$message = "";
$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $full_name    = trim($_POST["full_name"] ?? "");
    $email        = trim($_POST["email"] ?? "");
    $password     = $_POST["password"] ?? "";
    $confirm      = $_POST["confirm_password"] ?? "";
    $phone        = trim($_POST["phone"] ?? "");
    $city         = $_POST["city"] ?? "";
    $account_type = $_POST["account_type"] ?? "";
    $bio          = trim($_POST["bio"] ?? "");
    $age_check    = isset($_POST["age_check"]);


    // Full Name: 2-50 letters and spaces only
    if (!preg_match("/^[A-Za-z\s]{2,50}$/", $full_name)) {
        $errors["full_name"] = "Full Name must be 2-50 letters and spaces only.";
    }

    // Email: valid format + unique
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors["email"] = "Please enter a valid email address.";
    } else {
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email=? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            $errors["email"] = "Email already exists.";
        }
    }

    // Password: strong
    if (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/", $password)) {
        $errors["password"] = "Password must be 8+ with upper, lower, number, special.";
    }

    // Confirm password: match
    if ($confirm === "" || $password !== $confirm) {
        $errors["confirm_password"] = "Passwords do not match.";
    }

    // Password must be unique in system
    if (!isset($errors["password"]) && $password !== "") {
        $stmt = $pdo->query("SELECT password FROM users");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (password_verify($password, $row["password"])) {
                $errors["password"] = "Password already used in system.";
                break;
            }
        }
    }

    // Phone: exactly 10 digits
    if (!preg_match("/^\d{10}$/", $phone)) {
        $errors["phone"] = "Phone must be exactly 10 digits.";
    }

    // City: must be selected
    if ($city == "") {
        $errors["city"] = "Please select a city.";
    }

    // Account Type: required
    if ($account_type == "") {
        $errors["account_type"] = "Please select account type.";
    }

    // Bio/About: optional for Client, required for Freelancer, max 500 chars
    if ($account_type == "Freelancer") {
        if ($bio == "" || strlen($bio) > 500) {
            $errors["bio"] = "Bio is required for freelancers (max 500 characters).";
        }
    } else {
        if ($bio != "" && strlen($bio) > 500) {
            $errors["bio"] = "Bio max 500 characters.";
        }
    }

    // Age checkbox: must be checked
    if (!$age_check) {
        $errors["age_check"] = "You must confirm you are 18+.";
    }

    if (empty($errors)) {

        // Generate unique 10-digit user_id
        do {
            $user_id = str_pad((string)rand(0, 9999999999), 10, "0", STR_PAD_LEFT);
            $check = $pdo->prepare("SELECT user_id FROM users WHERE user_id=?");
            $check->execute([$user_id]);
        } while ($check->rowCount() > 0);

        // Split full name into first and last
        $parts = preg_split("/\s+/", $full_name, 2);
        $first_name = $parts[0] ?? "";
        $last_name  = $parts[1] ?? "";

        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $status = "Active";
        $countryDefault = "Palestine"; 
        $defaultPhoto = "uploads/profiles/default.png";

        // rating: freelancers only (0.0), clients NULL
        $rating = null;
        if ($account_type == "Freelancer") {
            $rating = 0.0;
        }

        $stmt = $pdo->prepare("
            INSERT INTO users
            (user_id, first_name, last_name, email, password, phone, country, city, role, status, registration_date, profile_photo, rating)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");

        $stmt->execute([
            $user_id,
            $first_name,
            $last_name,
            $email,
            $hashed,
            $phone,
            $countryDefault,
            $city,
            $account_type,
            $status,
            $defaultPhoto,
            $rating
        ]);

        $message = "Account created successfully! Please login.";
        header("refresh:2; url=login.php");
    }
}

include "includes/header.php";
?>

<div class="wrapper">
  <div class="container">
    <?php include "includes/nav.php"; ?>

<main class="main-content">
<div class="form-container form-container-lg">
        <h2>Create Your Account</h2>

        <?php if ($message): ?>
          <div class="message-success"><?php echo htmlspecialchars($message); ?></div>
        <?php elseif (!empty($errors)): ?>
          <div class="message-error">Please correct the highlighted fields.</div>
        <?php endif; ?>

        <form method="post" class="form">

          <h3>Personal Information</h3>

          <div class="form-group">
            <label class="form-label">Full Name</label>
            <input class="form-input <?php echo isset($errors['full_name']) ? 'input-error' : ''; ?>"
                   type="text" name="full_name"
                   value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
            <span class="error"><?php echo $errors['full_name'] ?? ''; ?></span>
          </div>

          <div class="form-group">
            <label class="form-label">Email</label>
            <input class="form-input <?php echo isset($errors['email']) ? 'input-error' : ''; ?>"
                   type="email" name="email"
                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            <span class="error"><?php echo $errors['email'] ?? ''; ?></span>
          </div>

          <div class="form-group">
            <label class="form-label">Phone Number</label>
            <input class="form-input <?php echo isset($errors['phone']) ? 'input-error' : ''; ?>"
                   type="text" name="phone"
                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
            <span class="error"><?php echo $errors['phone'] ?? ''; ?></span>
          </div>

          <div class="form-group">
            <label class="form-label">City</label>
            <select class="form-select <?php echo isset($errors['city']) ? 'input-error' : ''; ?>" name="city">
              <option value="">Select City</option>
              <option value="Jenin" <?php echo (($_POST['city'] ?? '')=='Jenin')?'selected':''; ?>>Jenin</option>
              <option value="Tulkarm" <?php echo (($_POST['city'] ?? '')=='Tulkarm')?'selected':''; ?>>Tulkarm</option>
              <option value="Ramallah" <?php echo (($_POST['city'] ?? '')=='Ramallah')?'selected':''; ?>>Ramallah</option>
              <option value="Nablus" <?php echo (($_POST['city'] ?? '')=='Nablus')?'selected':''; ?>>Nablus</option>
              <option value="Hebron" <?php echo (($_POST['city'] ?? '')=='Hebron')?'selected':''; ?>>Hebron</option>
            </select>
            <span class="error"><?php echo $errors['city'] ?? ''; ?></span>
          </div>

          <h3>Account Security</h3>

          <div class="form-group">
            <label class="form-label">Password</label>
            <input class="form-input <?php echo isset($errors['password']) ? 'input-error' : ''; ?>"
                   type="password" name="password">
            <span class="error"><?php echo $errors['password'] ?? ''; ?></span>
          </div>

          <div class="form-group">
            <label class="form-label">Confirm Password</label>
            <input class="form-input <?php echo isset($errors['confirm_password']) ? 'input-error' : ''; ?>"
                   type="password" name="confirm_password">
            <span class="error"><?php echo $errors['confirm_password'] ?? ''; ?></span>
          </div>

          <h3>Account Type</h3>

          <div class="form-group">
            <label class="form-label">
              <input type="radio" name="account_type" value="Client"
                <?php echo (($_POST['account_type'] ?? '')=='Client')?'checked':''; ?>>
              Client
            </label>

            <label class="form-label">
              <input type="radio" name="account_type" value="Freelancer"
                <?php echo (($_POST['account_type'] ?? '')=='Freelancer')?'checked':''; ?>>
              Freelancer
            </label>

            <span class="error"><?php echo $errors['account_type'] ?? ''; ?></span>
          </div>

          <div class="form-group">
            <label class="form-label">Bio (Optional for Client, Required for Freelancer)</label>
            <textarea class="form-input <?php echo isset($errors['bio']) ? 'input-error' : ''; ?>"
                      name="bio" maxlength="500"><?php echo htmlspecialchars($_POST['bio'] ?? ''); ?></textarea>
            <span class="error"><?php echo $errors['bio'] ?? ''; ?></span>
          </div>

          <div class="form-group">
            <label class="form-label">
              <input type="checkbox" name="age_check" <?php echo isset($_POST['age_check'])?'checked':''; ?>>
              I am 18+ years old
            </label>
            <span class="error"><?php echo $errors['age_check'] ?? ''; ?></span>
          </div>

          <div class="form-actions">
            <input class="btn btn-primary" type="submit" value="Register">
          </div>
        </form>
      </div>
    </main>

    <aside class="sidebar"></aside>
  </div>

  <?php include "includes/footer.php"; ?>
</div>
