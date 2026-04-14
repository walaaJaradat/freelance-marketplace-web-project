<?php
require_once "includes/init.php";

$Title = "My Profile";
$activePage = "profile.php";

if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}

if (!isset($baseUrl) || (string)$baseUrl === "") {
  $baseUrl = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"])), "/");
  $baseUrl = ($baseUrl === "" ? "/" : $baseUrl . "/");
}

$viewer_id = (string)($_SESSION["user"]["id"] ?? "");

$profile_id = trim((string)($_GET["id"] ?? ""));
if ($profile_id === "") {
    $profile_id = $viewer_id; 
}

$isOwnProfile = ((string)$profile_id === (string)$viewer_id);

$message = "";
$errors = [];

if (isset($_SESSION["flash_success"])) {
    $message = $_SESSION["flash_success"];
    unset($_SESSION["flash_success"]);
}

function oldValue($key, $dbRow) {
    if (isset($_POST[$key])) return trim((string)$_POST[$key]);
    return isset($dbRow[$key]) ? (string)$dbRow[$key] : "";
}
function hasError($errors, $key) {
    return isset($errors[$key]) ? "input-error" : "";
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id=?");
$stmt->execute([$profile_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION["flash_error"] = "User not found.";
    header("Location: home.php");
    exit;
}

$countries = ["Jordan", "Palestine", "Saudi Arabia", "UAE", "Egypt", "USA"];

$stats = ["services"=>0, "active"=>0, "featured"=>0, "orders"=>0];
$FEATURED_LIMIT = 3;

if (($user["role"] ?? "") === "Freelancer") {
    try {
        $q = $pdo->prepare("SELECT COUNT(*) FROM services WHERE freelancer_id=?");
        $q->execute([$profile_id]);
        $stats["services"] = (int)$q->fetchColumn();

        $q = $pdo->prepare("SELECT COUNT(*) FROM services WHERE freelancer_id=? AND status='Active'");
        $q->execute([$profile_id]);
        $stats["active"] = (int)$q->fetchColumn();

        $q = $pdo->prepare("SELECT COUNT(*) FROM services WHERE freelancer_id=? AND featured_status='Yes'");
        $q->execute([$profile_id]);
        $stats["featured"] = (int)$q->fetchColumn();

        $q = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE freelancer_id=? AND status='Completed'");
        $q->execute([$profile_id]);
        $stats["orders"] = (int)$q->fetchColumn();
    } catch (Exception $e) {}
}


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!$isOwnProfile) {
        $_SESSION["flash_error"] = "You cannot edit another user's profile.";
        header("Location: profile.php?id=" . urlencode($profile_id));
        exit;
    }

    $email   = trim((string)($_POST["email"] ?? ""));
    $first   = trim((string)($_POST["first_name"] ?? ""));
    $last    = trim((string)($_POST["last_name"] ?? ""));
    $phone   = trim((string)($_POST["phone"] ?? ""));
    $country = trim((string)($_POST["country"] ?? ""));
    $city    = trim((string)($_POST["city"] ?? ""));

    /* Freelancer bio  */
    $bio = trim((string)($_POST["bio"] ?? ""));

    $professional_title = trim((string)($_POST["professional_title"] ?? ""));
    $skills             = trim((string)($_POST["skills"] ?? ""));
    $years_experience   = trim((string)($_POST["years_experience"] ?? ""));

    /* password fields */
    $current = (string)($_POST["current_password"] ?? "");
    $new     = (string)($_POST["new_password"] ?? "");
    $confirm = (string)($_POST["confirm_new_password"] ?? "");

    /* Email validation */
    if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors["email"] = "Email is required and must be valid.";
    } else {
        $check = $pdo->prepare("SELECT user_id FROM users WHERE email=? AND user_id<>?");
        $check->execute([$email, $viewer_id]);
        if ($check->rowCount() > 0) $errors["email"] = "Email already in use.";
    }

    if ($first === "" || !preg_match("/^[A-Za-z]{2,50}$/", $first)) {
        $errors["first_name"] = "First name must be 2-50 letters.";
    }
    if ($last === "" || !preg_match("/^[A-Za-z]{2,50}$/", $last)) {
        $errors["last_name"] = "Last name must be 2-50 letters.";
    }

    /* Phone validation */
    if (!preg_match("/^\d{10}$/", $phone)) {
        $errors["phone"] = "Phone must be exactly 10 digits.";
    }

    /* Country validation */
    if ($country === "" || !in_array($country, $countries, true)) {
        $errors["country"] = "Please select a valid country.";
    }

    /* City validation */
    if ($city === "" || strlen($city) < 2 || strlen($city) > 50) {
        $errors["city"] = "City must be 2-50 characters.";
    }

    /* Bio validation  */
    if (($user["role"] ?? "") === "Freelancer") {
        $len = strlen($bio);
        if ($bio === "" || $len < 50 || $len > 500) {
            $errors["bio"] = "Bio must be between 50 and 500 characters.";
        }
    } else {
        $bio = $user["bio"] ?? "";
    }

    /* Password change validation */
    $wantsChange = ($new !== "" || $confirm !== "");
    if ($wantsChange) {

        if ($current === "") {
            $errors["current_password"] = "Current password is required.";
        } else if (!password_verify($current, (string)$user["password"])) {
            $errors["current_password"] = "Current password is incorrect.";
        }

        if ($new === "") {
            $errors["new_password"] = "New password is required.";
        } else if (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/", $new)) {
            $errors["new_password"] = "New password is weak (8+, upper, lower, number, special).";
        }

        if ($confirm === "") {
            $errors["confirm_new_password"] = "Confirm new password is required.";
        } else if ($new !== $confirm) {
            $errors["confirm_new_password"] = "New passwords do not match.";
        }
    }

    /* Photo validation */
    $newPhotoPath = null;

    if (!empty($_FILES["profile_photo"]["name"])) {

        $file = $_FILES["profile_photo"];

        if (($file["error"] ?? 0) != 0) {
            $errors["profile_photo"] = "Upload failed.";
        } else if (($file["size"] ?? 0) > 2 * 1024 * 1024) {
            $errors["profile_photo"] = "Photo max size is 2MB.";
        } else {
            $imgInfo = @getimagesize($file["tmp_name"]);
            if ($imgInfo == false) {
                $errors["profile_photo"] = "Invalid image file.";
            } else {
                $w = (int)$imgInfo[0];
                $h = (int)$imgInfo[1];
                $mime = (string)($imgInfo["mime"] ?? "");

                if (!in_array($mime, ["image/jpeg", "image/png"], true)) {
                    $errors["profile_photo"] = "Only JPG/JPEG or PNG allowed.";
                }
                if ($w < 300 || $h < 300) {
                    $errors["profile_photo"] = "Photo must be at least 300x300.";
                }
            }
        }

        if (!isset($errors["profile_photo"])) {
            $dir = "uploads/profiles/$viewer_id/";
            if (!is_dir($dir)) mkdir($dir, 0777, true);

            $newPhotoPath = $dir . "profile_photo.jpg";

            if (!move_uploaded_file($file["tmp_name"], $newPhotoPath)) {
                $errors["profile_photo"] = "Could not save photo.";
                $newPhotoPath = null;
            }
        }
    }

    if (empty($errors)) {

        $finalPhoto = $newPhotoPath
            ? $newPhotoPath
            : ($user["profile_photo"] ?? "uploads/profiles/user-default.jpg");

        $finalBio = (($user["role"] ?? "") === "Freelancer")
            ? $bio
            : ($user["bio"] ?? "");

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE users
                SET email=?, first_name=?, last_name=?, phone=?, country=?, city=?, profile_photo=?, bio=?
                WHERE user_id=?
            ");
            $stmt->execute([$email, $first, $last, $phone, $country, $city, $finalPhoto, $finalBio, $viewer_id]);

            if ($wantsChange) {
                $hashed = password_hash($new, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password=? WHERE user_id=?")
                    ->execute([$hashed, $viewer_id]);
            }

            $pdo->commit();

            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id=?");
            $stmt->execute([$viewer_id]);
            $viewerRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($viewerRow) {
                $_SESSION["user"]["first_name"]    = $viewerRow["first_name"];
                $_SESSION["user"]["last_name"]     = $viewerRow["last_name"];
                $_SESSION["user"]["email"]         = $viewerRow["email"];
                $_SESSION["user"]["phone"]         = $viewerRow["phone"];
                $_SESSION["user"]["country"]       = $viewerRow["country"];
                $_SESSION["user"]["city"]          = $viewerRow["city"];
                $_SESSION["user"]["role"]          = $viewerRow["role"];
                $_SESSION["user"]["profile_photo"] = $viewerRow["profile_photo"] ?? "uploads/profiles/user-default.jpg";
                $_SESSION["user"]["bio"]           = $viewerRow["bio"] ?? "";
            }

            $_SESSION["flash_success"] = "Profile updated successfully.";
            header("Location: profile.php");
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors["general"] = "Something went wrong. Please try again.";
        }
    }

    $user["email"] = $email;
    $user["first_name"] = $first;
    $user["last_name"] = $last;
    $user["phone"] = $phone;
    $user["country"] = $country;
    $user["city"] = $city;
    $user["bio"] = $bio;
}

include "includes/header.php";
?>

<div class="wrapper">
  <div class="container">
    <?php include "includes/nav.php"; ?>

    <main class="main-content">

      <?php if (!empty($_SESSION["flash_error"])): ?>
        <div class="message-error"><?php echo htmlspecialchars($_SESSION["flash_error"]); ?></div>
        <?php unset($_SESSION["flash_error"]); ?>
      <?php endif; ?>

      <div class="profile-container">

        <div class="left-column">
          <div class="card">

            <?php
              $photoDb = $user["profile_photo"] ?? "uploads/profiles/user-default.jpg";
              $photoDb = ltrim((string)$photoDb, "/");

              $photoFs = __DIR__ . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $photoDb);
              if (!file_exists($photoFs)) {
                  $photoDb = "uploads/profiles/user-default.jpg";
              }
              $photoUrl = $baseUrl . $photoDb;
            ?>

            <img class="profile-photo"
                 src="<?php echo htmlspecialchars($photoUrl); ?>"
                 width="150" height="150" alt="Profile Photo">

            <h3><?php echo htmlspecialchars((string)$user["first_name"] . " " . (string)$user["last_name"]); ?></h3>
            <p><?php echo htmlspecialchars((string)$user["email"]); ?></p>

            <span class="badge <?php echo (($user["role"] ?? "") == "Client") ? "badge-client" : "badge-freelancer"; ?>">
              <?php echo htmlspecialchars((string)$user["role"]); ?>
            </span>

            <p class="member-since">
              Member since: <?php echo htmlspecialchars(date("Y-m-d", strtotime((string)$user["registration_date"]))); ?>
            </p>

            <?php if ($isOwnProfile): ?>
              <label class="change-photo-link" for="profile_photo" role="button" tabindex="0">
                Change Photo
              </label>
            <?php endif; ?>

          </div>

          <?php if (($user["role"] ?? "") == "Freelancer"): ?>
            <div class="stats-card">
              <div class="stats-grid">

                <div class="stat-box">
                  <div class="stat-number"><?php echo (int)$stats["services"]; ?></div>
                  <div class="stat-label">Total Services</div>
                </div>

                <div class="stat-box stat-active">
                  <div class="stat-number"><?php echo (int)$stats["active"]; ?></div>
                  <div class="stat-label">Active Services</div>
                </div>

                <?php
                  $isFeaturedAtLimit = ((int)$stats["featured"] >= (int)$FEATURED_LIMIT);
                  $featuredClass = $isFeaturedAtLimit ? "stat-featured" : "";
                ?>
                <div class="stat-box <?php echo $featuredClass; ?>">
                  <div class="stat-number"><?php echo (int)$stats["featured"]; ?>/<?php echo (int)$FEATURED_LIMIT; ?></div>
                  <div class="stat-label">Featured Services</div>
                </div>

                <div class="stat-box">
                  <div class="stat-number"><?php echo (int)$stats["orders"]; ?></div>
                  <div class="stat-label">Total Orders</div>
                </div>

              </div>
            </div>
          <?php endif; ?>
        </div>

        <div class="right-column">
          <?php if ($isOwnProfile): ?>
            <div class="form-container profile-form-container" id="edit-form">
              <h2>Edit Profile</h2>

              <?php if (!empty($errors["general"])): ?>
                <div class="message-error"><?php echo htmlspecialchars($errors["general"]); ?></div>
              <?php endif; ?>

              <?php if ($message): ?>
                <div class="message-success"><?php echo htmlspecialchars($message); ?></div>
              <?php endif; ?>

              <form method="post" enctype="multipart/form-data" class="form" novalidate>

                <h3>Account Information</h3>

                <div class="form-group">
                  <label class="form-label" for="email">Email *</label>
                  <input id="email"
                         class="form-input <?php echo hasError($errors,'email'); ?>"
                         type="email" name="email"
                         placeholder="name@example.com"
                         value="<?php echo htmlspecialchars(oldValue("email", $user)); ?>">
                  <span class="error"><?php echo $errors["email"] ?? ""; ?></span>
                </div>

                <div class="form-group">
                  <label class="form-label" for="current_password">Current Password (required if changing password)</label>
                  <input id="current_password"
                         class="form-input <?php echo hasError($errors,'current_password'); ?>"
                         type="password" name="current_password"
                         placeholder="Enter current password">
                  <span class="error"><?php echo $errors["current_password"] ?? ""; ?></span>
                </div>

                <div class="form-group">
                  <label class="form-label" for="new_password">New Password (optional)</label>
                  <input id="new_password"
                         class="form-input <?php echo hasError($errors,'new_password'); ?>"
                         type="password" name="new_password"
                         placeholder="Min 8 chars, upper/lower/number/special">
                  <span class="error"><?php echo $errors["new_password"] ?? ""; ?></span>
                </div>

                <div class="form-group">
                  <label class="form-label" for="confirm_new_password">Confirm New Password (optional)</label>
                  <input id="confirm_new_password"
                         class="form-input <?php echo hasError($errors,'confirm_new_password'); ?>"
                         type="password" name="confirm_new_password"
                         placeholder="Re-enter new password">
                  <span class="error"><?php echo $errors["confirm_new_password"] ?? ""; ?></span>
                </div>

                <h3>Personal Information</h3>

                <div class="form-group">
                  <label class="form-label" for="first_name">First Name *</label>
                  <input id="first_name"
                         class="form-input <?php echo hasError($errors,'first_name'); ?>"
                         type="text" name="first_name"
                         placeholder="e.g., Ahmad"
                         value="<?php echo htmlspecialchars(oldValue("first_name", $user)); ?>">
                  <span class="error"><?php echo $errors["first_name"] ?? ""; ?></span>
                </div>

                <div class="form-group">
                  <label class="form-label" for="last_name">Last Name *</label>
                  <input id="last_name"
                         class="form-input <?php echo hasError($errors,'last_name'); ?>"
                         type="text" name="last_name"
                         placeholder="e.g., Ali"
                         value="<?php echo htmlspecialchars(oldValue("last_name", $user)); ?>">
                  <span class="error"><?php echo $errors["last_name"] ?? ""; ?></span>
                </div>

                <div class="form-group">
                  <label class="form-label" for="phone">Phone Number *</label>
                  <input id="phone"
                         class="form-input <?php echo hasError($errors,'phone'); ?>"
                         type="tel" name="phone"
                         placeholder="10 digits (e.g., 0791234567)"
                         value="<?php echo htmlspecialchars(oldValue("phone", $user)); ?>">
                  <span class="error"><?php echo $errors["phone"] ?? ""; ?></span>
                </div>

                <div class="form-group">
                  <label class="form-label" for="country">Country *</label>
                  <select id="country"
                          class="form-select <?php echo hasError($errors,'country'); ?>"
                          name="country">
                    <option value="">Select Country</option>
                    <?php
                      $selectedCountry = oldValue("country", $user);
                      foreach ($countries as $c) {
                        $sel = ($selectedCountry == $c) ? "selected" : "";
                        echo "<option value='".htmlspecialchars($c)."' $sel>".htmlspecialchars($c)."</option>";
                      }
                    ?>
                  </select>
                  <span class="error"><?php echo $errors["country"] ?? ""; ?></span>
                </div>

                <div class="form-group">
                  <label class="form-label" for="city">City *</label>
                  <input id="city"
                         class="form-input <?php echo hasError($errors,'city'); ?>"
                         type="text" name="city"
                         placeholder="e.g., Amman"
                         value="<?php echo htmlspecialchars(oldValue("city", $user)); ?>">
                  <span class="error"><?php echo $errors["city"] ?? ""; ?></span>
                </div>

                <div class="form-group">
                  <label class="form-label" for="profile_photo">Profile Photo (optional)</label>
                  <input id="profile_photo"
                         class="form-input <?php echo hasError($errors,'profile_photo'); ?>"
                         type="file" name="profile_photo" accept=".jpg,.jpeg,.png">
                  <span class="error"><?php echo $errors["profile_photo"] ?? ""; ?></span>
                </div>

                <?php if (($user["role"] ?? "") === "Freelancer"): ?>
                  <h3>Professional Information</h3>

                  <div class="form-group">
                    <label class="form-label" for="professional_title">Professional Title (optional)</label>
                    <input id="professional_title"
                           class="form-input"
                           type="text" name="professional_title"
                           placeholder="(Optional) Placeholder only - not saved"
                           value="<?php echo htmlspecialchars(oldValue("professional_title", $user)); ?>">
                    <span class="error"></span>
                  </div>

                  <div class="form-group">
                    <label class="form-label" for="bio">Bio/Description *</label>
                    <textarea id="bio"
                              class="form-input <?php echo hasError($errors,'bio'); ?>"
                              name="bio"
                              placeholder="Write 50–500 characters about your experience..."
                              rows="6"><?php echo htmlspecialchars(oldValue("bio", $user)); ?></textarea>
                    <span class="error"><?php echo $errors["bio"] ?? ""; ?></span>
                  </div>

                  <div class="form-group">
                    <label class="form-label" for="skills">Skills (optional)</label>
                    <input id="skills"
                           class="form-input"
                           type="text" name="skills"
                           placeholder="(Optional) Placeholder only - not saved"
                           value="<?php echo htmlspecialchars(oldValue("skills", $user)); ?>">
                    <span class="error"></span>
                  </div>

                  <div class="form-group">
                    <label class="form-label" for="years_experience">Years of Experience (optional)</label>
                    <input id="years_experience"
                           class="form-input"
                           type="number" name="years_experience"
                           min="0" max="50"
                           placeholder="(Optional) Placeholder only - not saved"
                           value="<?php echo htmlspecialchars(oldValue("years_experience", $user)); ?>">
                    <span class="error"></span>
                  </div>

                <?php endif; ?>

                <div class="form-actions form-actions-split">
                  <a class="btn btn-secondary" href="profile.php">Cancel</a>
                  <input class="btn btn-primary" type="submit" value="Save Changes">
                </div>

              </form>
            </div>
          <?php else: ?>
            <div class="create-service-card">
              <h2 class="heading-secondary">Profile</h2>
              <p class="text-muted">You are viewing this user's profile.</p>
            </div>
          <?php endif; ?>
        </div>

      </div>

    </main>

    <aside class="sidebar"></aside>
  </div>

  <?php include "includes/footer.php"; ?>
</div>