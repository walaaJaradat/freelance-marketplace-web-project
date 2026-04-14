<?php
require_once "includes/init.php";

$Title = "Checkout";
$activePage = "checkout.php";

if (!isset($_SESSION["user"])) { header("Location: login.php"); exit; }

$role = $_SESSION["user"]["role"] ?? "Guest";
if (!in_array($role, ["Client","Freelancer"], true)) {
  $_SESSION["flash_error"] = "Only registered users can access checkout.";
  header("Location: index.html"); exit;
}

$clientId = $_SESSION["user"]["id"];

if (!isset($baseUrl) || (string)$baseUrl === "") {
  $baseUrl = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"])), "/");
  $baseUrl = ($baseUrl === "" ? "/" : $baseUrl . "/");
}

$cart = $_SESSION["cart"] ?? [];
if (!is_array($cart)) $cart = [];

$cartClean = [];
foreach ($cart as $x) {
  $x = (string)$x;
  $x = trim($x);
  if ($x !== "") $cartClean[] = $x;
}
$cart = array_values(array_unique($cartClean));

if (count($cart) < 1) {
  $_SESSION["flash_error"]="Your cart is empty.";
  header("Location: cart.php"); exit;
}

function normalize_ext($name){ $e=strtolower(pathinfo((string)$name, PATHINFO_EXTENSION)); return $e ?: "file"; }
function allowed_ext($e){ return in_array($e, ["pdf","doc","docx","txt","zip","jpg","jpeg","png"], true); }
function bytes_to_human($b){
  $b=(int)$b;
  if($b>=1048576) return number_format($b/1048576,1)." MB";
  if($b>=1024) return number_format($b/1024,1)." KB";
  return $b." B";
}
function ensure_dir($p){ if(!is_dir($p)) mkdir($p,0775,true); }
function mask_card($num){ $num=preg_replace('/\D+/', '', (string)$num); return "**** **** **** ".substr($num,-4); }

$placeholders = implode(",", array_fill(0, count($cart), "?"));
$stmt = $pdo->prepare("
  SELECT s.service_id, s.title, s.price, s.delivery_time, s.revisions_included,
         s.freelancer_id,
         CONCAT(u.first_name,' ',u.last_name) AS freelancer_name
  FROM services s
  JOIN users u ON u.user_id = s.freelancer_id
  WHERE s.service_id IN ($placeholders) AND s.status='Active'
");
$stmt->execute($cart);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($rows) !== count($cart)) {
  $_SESSION["flash_error"]="Some services are unavailable.";
  header("Location: cart.php"); exit;
}

$map=[]; foreach($rows as $r) $map[$r["service_id"]]=$r;
$services=[]; foreach($cart as $sid) $services[]=$map[$sid];

foreach ($services as $sv) {
  if ((string)($sv["freelancer_id"] ?? "") === (string)$clientId) {
    $_SESSION["flash_error"] = "You cannot checkout your own service(s). Please remove them from the cart.";
    header("Location: cart.php");
    exit;
  }
}

if (!isset($_SESSION["checkout"])) {
  $_SESSION["checkout"] = ["step"=>1,"requirements"=>[],"payment"=>null,"terms"=>false];
}
$checkout = &$_SESSION["checkout"];

$errors = [];
$step = (int)($checkout["step"] ?? 1);
if ($step<1 || $step>3) $step=1;

$tmpBaseWeb  = "/uploads/tmp/checkout/".$clientId;
$tmpBaseDisk = rtrim(__DIR__, "/\\") . $tmpBaseWeb;

if (isset($_GET["goto"])) {
  $goto=(int)$_GET["goto"];
  if ($goto===1) $checkout["step"]=1;
  if ($goto===2 && !empty($checkout["requirements"])) $checkout["step"]=2;
  if ($goto===3 && !empty($checkout["requirements"]) && !empty($checkout["payment"])) $checkout["step"]=3;
  header("Location: checkout.php"); exit;
}

$step2Sticky = [
  "payment_method" => "",
  "addr1" => "", "addr2" => "", "city" => "", "state" => "", "zip" => "", "country" => "",
  "card_number" => "", "card_name" => "", "exp" => "", "cvv" => ""
];

if ($_SERVER["REQUEST_METHOD"]==="POST") {

  if (isset($_POST["to_step2"])) {
    $reqData = $_POST["req"] ?? [];
    $instData = $_POST["inst"] ?? [];
    $deadlineData = $_POST["deadline"] ?? [];

    $newReq = [];
    $today = new DateTime("today");

    foreach ($services as $sv) {
      $sid=(string)$sv["service_id"];
      $deliveryDays=(int)$sv["delivery_time"];

      $reqTxt=trim((string)($reqData[$sid] ?? ""));
      $instTxt=trim((string)($instData[$sid] ?? ""));
      $deadline=trim((string)($deadlineData[$sid] ?? ""));

      if ($reqTxt==="" || str_len($reqTxt)<50 || str_len($reqTxt)>1000) {
        $errors["req_$sid"]="Service requirements must be 50–1000 characters.";
      }
      if ($instTxt!=="" && str_len($instTxt)>500) {
        $errors["inst_$sid"]="Special instructions must be up to 500 characters.";
      }

      $deadlineDate=null;
      if ($deadline!=="") {
        $d=DateTime::createFromFormat("Y-m-d",$deadline);
        if(!$d) $errors["deadline_$sid"]="Invalid deadline date.";
        else{
          $min=(clone $today)->modify("+{$deliveryDays} days");
          if($d<$min) $errors["deadline_$sid"]="Deadline must be at least {$deliveryDays} day(s) from today.";
          else $deadlineDate=$deadline;
        }
      }

      $newReq[$sid]=[
        "requirements"=>$reqTxt,
        "instructions"=>$instTxt,
        "deadline"=>$deadlineDate,
        "files"=>$checkout["requirements"][$sid]["files"] ?? []
      ];
    }

    if (isset($_FILES["files"]) && isset($_FILES["files"]["name"])) {
      foreach($services as $sv){
        $sid=(string)$sv["service_id"];
        $names=$_FILES["files"]["name"][$sid] ?? [];
        $types=$_FILES["files"]["type"][$sid] ?? [];
        $tmpns=$_FILES["files"]["tmp_name"][$sid] ?? [];
        $sizes=$_FILES["files"]["size"][$sid] ?? [];
        $errs =$_FILES["files"]["error"][$sid] ?? [];

        if(!is_array($names)) continue;

        $existingCount=count($newReq[$sid]["files"] ?? []);
        $newCount=0; for($i=0;$i<count($names);$i++) if(($names[$i] ?? "")!=="") $newCount++;
        if($existingCount+$newCount>3){ $errors["files_$sid"]="Max 3 files per service."; continue; }

        for($i=0;$i<count($names);$i++){
          if(($names[$i] ?? "")==="") continue;
          if(($errs[$i] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK){ $errors["files_$sid"]="File upload failed."; continue; }
          $size=(int)($sizes[$i] ?? 0);
          if($size>10*1024*1024){ $errors["files_$sid"]="Each file must be ≤ 10MB."; continue; }

          $orig=(string)$names[$i];
          $ext=normalize_ext($orig);
          if(!allowed_ext($ext)){ $errors["files_$sid"]="Invalid file type."; continue; }

          $serviceTmpDisk=$tmpBaseDisk."/".$sid;
          $serviceTmpWeb =$tmpBaseWeb."/".$sid;
          ensure_dir($serviceTmpDisk);

          $safe=preg_replace('/[^a-zA-Z0-9._-]+/','_',$orig);
          $unique=time()."_".bin2hex(random_bytes(3))."_".$safe;

          $destDisk=$serviceTmpDisk."/".$unique;
          if(!move_uploaded_file($tmpns[$i], $destDisk)){ $errors["files_$sid"]="Could not save file."; continue; }

          $newReq[$sid]["files"][]=[
            "original"=>$orig,
            "stored_web"=>$serviceTmpWeb."/".$unique,
            "stored_disk"=>$destDisk,
            "size"=>$size,
            "mime"=>(string)($types[$i] ?? ""),
            "ext"=>$ext,
            "uploaded_at"=>date("Y-m-d")
          ];
        }
      }
    }

    $checkout["requirements"]=$newReq;
    if (empty($errors)) { $checkout["step"]=2; header("Location: checkout.php"); exit; }
    $checkout["step"]=1;
    $step=1;
  }

  if (isset($_POST["to_step3"])) {
    $method=trim((string)($_POST["payment_method"] ?? "Credit Card"));
    $allowed=["Credit Card","PayPal","Bank Transfer"];
    if(!in_array($method,$allowed,true)) $method="Credit Card";

    $step2Sticky["payment_method"] = $method;
    $step2Sticky["addr1"] = trim((string)($_POST["addr1"] ?? ""));
    $step2Sticky["addr2"] = trim((string)($_POST["addr2"] ?? ""));
    $step2Sticky["city"]  = trim((string)($_POST["city"] ?? ""));
    $step2Sticky["state"] = trim((string)($_POST["state"] ?? ""));
    $step2Sticky["zip"]   = trim((string)($_POST["zip"] ?? ""));
    $step2Sticky["country"]=trim((string)($_POST["country"] ?? ""));

    if($step2Sticky["addr1"]==="") $errors["addr1"]="Address Line 1 is required.";
    if($step2Sticky["city"]==="") $errors["city"]="City is required.";
    if($step2Sticky["state"]==="") $errors["state"]="State/Province is required.";
    if($step2Sticky["zip"]==="") $errors["zip"]="Postal Code is required.";
    if($step2Sticky["country"]==="") $errors["country"]="Country is required.";

    $cardMasked=null; $exp=null;

    if($method==="Credit Card"){
      $cardRaw = (string)($_POST["card_number"] ?? "");
      $name    = trim((string)($_POST["card_name"] ?? ""));
      $exp     = trim((string)($_POST["exp"] ?? ""));
      $cvvRaw  = (string)($_POST["cvv"] ?? "");

      $step2Sticky["card_number"] = $cardRaw;
      $step2Sticky["card_name"]   = $name;
      $step2Sticky["exp"]         = $exp;
      $step2Sticky["cvv"]         = $cvvRaw;

      $card=preg_replace('/\D+/', '', $cardRaw);
      $cvv=preg_replace('/\D+/', '', $cvvRaw);

      if(!preg_match('/^\d{16}$/',$card)) $errors["card_number"]="Card number must be 16 digits.";
      if(!preg_match('/^[a-zA-Z ]{2,100}$/',$name)) $errors["card_name"]="Cardholder name must be 2–100 letters.";
      if(!preg_match('/^\d{2}\/\d{2}$/',$exp)) $errors["exp"]="Expiration must be MM/YY.";
      if(!preg_match('/^\d{3}$/',$cvv)) $errors["cvv"]="CVV must be 3 digits.";

      if(!isset($errors["exp"]) && preg_match('/^(\d{2})\/(\d{2})$/',$exp,$m)){
        $mm=(int)$m[1]; $yy=(int)$m[2]+2000;
        if($mm<1 || $mm>12) $errors["exp"]="Invalid expiration month.";
        else{
          $expDate=DateTime::createFromFormat("Y-m-d",$yy."-".str_pad($mm,2,"0",STR_PAD_LEFT)."-01");
          $expDate->modify("last day of this month");
          $today2=new DateTime("today");
          if($expDate<$today2) $errors["exp"]="Card expiration must be a future date.";
        }
      }

      $cardMasked=mask_card($card);
    }

    if(empty($checkout["requirements"])) $errors["step"]="Please complete requirements first.";

    if(empty($errors)){
      $billing = $step2Sticky["addr1"];
      if($step2Sticky["addr2"]!=="") $billing.="\n".$step2Sticky["addr2"];
      $billing.="\n".$step2Sticky["city"].", ".$step2Sticky["state"]." ".$step2Sticky["zip"]."\n".$step2Sticky["country"];

      $checkout["payment"]=[
        "method"=>$method,
        "billing"=>$billing,
        "card_masked"=>$cardMasked,
        "exp"=>$exp
      ];

      $checkout["step"]=3;
      header("Location: checkout.php"); exit;
    }

    $checkout["step"]=2;
    $step=2;
  }

  if (isset($_POST["place_order"])) {
    $checkout["terms"]=isset($_POST["terms"]);

    if(!$checkout["terms"]) $errors["terms"]="You must agree to the terms to place the order.";
    if(empty($checkout["requirements"])) $errors["step"]="Missing requirements.";
    if(empty($checkout["payment"])) $errors["step"]="Missing payment info.";

    if(empty($errors)){
      $txn="TXN".time().strtoupper(bin2hex(random_bytes(2)));

      $subtotal=0; foreach($services as $sv) $subtotal+=(float)$sv["price"];
      $serviceFee=round($subtotal*0.05,2);
      $grand=round($subtotal+$serviceFee,2);

      $pdo->beginTransaction();
      try{
        $created=[];

        foreach($services as $sv){
          $sid=(string)$sv["service_id"];
          $req=$checkout["requirements"][$sid] ?? null;
          if(!$req || trim((string)$req["requirements"])==="") throw new Exception("missing req");

          do{
            $orderId=(string)random_int(1000000000, 9999999999);
            $chk=$pdo->prepare("SELECT 1 FROM orders WHERE order_id=? LIMIT 1");
            $chk->execute([$orderId]);
            $exists=$chk->fetchColumn();
          }while($exists);

          $price=(float)$sv["price"];
          $fee=round($price*0.05,2);

          if (!empty($req["deadline"])) {
            $expected = (string)$req["deadline"];
          } else {
            $expected=(new DateTime("today"))->modify("+".((int)$sv["delivery_time"])." days")->format("Y-m-d");
          }

          $packed = [
            "transaction_id"=>$txn,
            "service_fee"=>$fee,
            "total"=>round($price+$fee,2),
            "billing_address"=>$checkout["payment"]["billing"],
            "card_masked"=>$checkout["payment"]["card_masked"],
            "card_exp"=>$checkout["payment"]["exp"],
            "special_instructions"=>$req["instructions"],
            "requested_deadline"=>$req["deadline"],
            "grand_subtotal"=>$subtotal,
            "grand_service_fee"=>$serviceFee,
            "grand_total"=>$grand
          ];
          $notes = json_encode($packed, JSON_UNESCAPED_UNICODE);

          $ins=$pdo->prepare("
            INSERT INTO orders (
              order_id, client_id, freelancer_id, service_id,
              service_title, price, delivery_time, revisions_included,
              requirements, deliverable_notes, status, payment_method, expected_delivery
            ) VALUES (
              ?, ?, ?, ?,
              ?, ?, ?, ?,
              ?, ?, 'Pending', ?, ?
            )
          ");
          $ins->execute([
            $orderId, $clientId, $sv["freelancer_id"], $sid,
            $sv["title"], $price, (int)$sv["delivery_time"], (int)$sv["revisions_included"],
            (string)$req["requirements"], $notes,
            (string)$checkout["payment"]["method"], $expected
          ]);

          $finalWeb="/uploads/orders/".$orderId."/requirements";
          $finalDisk=rtrim(__DIR__, "/\\") . $finalWeb;
          ensure_dir($finalDisk);

          foreach(($req["files"] ?? []) as $f){
            $src=(string)($f["stored_disk"] ?? "");
            if(!is_file($src)) continue;

            $orig=(string)($f["original"] ?? "file");
            $safe=preg_replace('/[^a-zA-Z0-9._-]+/','_',$orig);
            $destName=time()."_".bin2hex(random_bytes(3))."_".$safe;

            $destDisk=$finalDisk."/".$destName;
            $destWeb =$finalWeb."/".$destName;

            if(!rename($src,$destDisk)){ copy($src,$destDisk); unlink($src); }

            $fin=$pdo->prepare("
              INSERT INTO file_attachments (order_id, file_path, original_filename, file_size, file_type)
              VALUES (?, ?, ?, ?, 'requirement')
            ");
            $fin->execute([$orderId, $destWeb, $orig, (int)($f["size"] ?? 0)]);
          }

          $created[]=$orderId;
        }

        $_SESSION["last_order_ids"]=$created;
        $_SESSION["last_txn"]=$txn;

        unset($_SESSION["cart"]);
        unset($_SESSION["checkout"]);

        $pdo->commit();
        header("Location: order-success.php"); exit;

      }catch(Exception $e){
        $pdo->rollBack();
        $errors["place"]="Order placement failed. Please try again.";
      }
    }

    $checkout["step"]=3;
    $step=3;
  }
}

$subtotal=0; foreach($services as $sv) $subtotal+=(float)$sv["price"];
$serviceFee=round($subtotal*0.05,2);
$grandTotal=round($subtotal+$serviceFee,2);

include "includes/header.php";
?>

<div class="wrapper">
  <div class="container">
    <?php include "includes/nav.php"; ?>

    <main class="main-content checkout-page">

      <h1 class="heading-primary">Checkout</h1>

      <?php if (!empty($errors["place"]) || !empty($errors["step"])): ?>
        <div class="message-error"><?php echo h($errors["place"] ?? $errors["step"]); ?></div>
      <?php endif; ?>

      <?php
        $hasStep1 = !empty($checkout["requirements"]);
        $hasStep2 = !empty($checkout["payment"]);
        $s1 = ($step===1) ? "active" : ($hasStep1 ? "completed":"pending");
        $s2 = ($step===2) ? "active" : ($hasStep2 ? "completed":"pending");
        $s3 = ($step===3) ? "active" : "pending";
      ?>

      <div class="checkout-progress">
        <ul class="checkout-steps">
          <li class="checkout-step" data-state="<?php echo h($s1); ?>">
            <?php if ($s1 === "completed"): ?>
              <a class="checkout-step-link" href="checkout.php?goto=1">
                <div class="checkout-step-icon" data-step="1"></div>
                <div class="checkout-step-label">Service Requirements</div>
              </a>
            <?php else: ?>
              <div class="checkout-step-link">
                <div class="checkout-step-icon" data-step="1"></div>
                <div class="checkout-step-label">Service Requirements</div>
              </div>
            <?php endif; ?>
          </li>

          <li class="checkout-step" data-state="<?php echo h($s2); ?>">
            <?php if ($s2 === "completed"): ?>
              <a class="checkout-step-link" href="checkout.php?goto=2">
                <div class="checkout-step-icon" data-step="2"></div>
                <div class="checkout-step-label">Payment Information</div>
              </a>
            <?php else: ?>
              <div class="checkout-step-link">
                <div class="checkout-step-icon" data-step="2"></div>
                <div class="checkout-step-label">Payment Information</div>
              </div>
            <?php endif; ?>
          </li>

          <li class="checkout-step" data-state="<?php echo h($s3); ?>">
            <div class="checkout-step-link">
              <div class="checkout-step-icon" data-step="3"></div>
              <div class="checkout-step-label">Review & Confirmation</div>
            </div>
          </li>
        </ul>
      </div>


      <!-- STEP 1 -->
      <?php if ($step===1): ?>
        <form method="post" enctype="multipart/form-data">
          <div class="checkout-services">
            <?php foreach ($services as $sv):
              $sid=(string)$sv["service_id"];
              $saved=$checkout["requirements"][$sid] ?? [];
            ?>
              <div class="checkout-card">
                <div class="checkout-service-header">
                  <div>
                    <p class="checkout-service-title"><?php echo h($sv["title"]); ?></p>
                    <div class="checkout-service-meta">
                      by <?php echo h($sv["freelancer_name"]); ?> • $<?php echo number_format((float)$sv["price"],2); ?><br>
                      Delivery: <?php echo (int)$sv["delivery_time"]; ?> Day(s)
                    </div>
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label">Service Requirements <span class="req">*</span></label>
                  <textarea class="form-input <?php echo isset($errors["req_$sid"]) ? "input-error":""; ?>"
                            name="req[<?php echo h($sid); ?>]" rows="4"
                            placeholder="Describe what you need for this service (50–1000 chars)"><?php echo h($saved["requirements"] ?? ""); ?></textarea>
                  <?php if(isset($errors["req_$sid"])): ?><div class="error"><?php echo h($errors["req_$sid"]); ?></div><?php endif; ?>
                </div>

                <div class="form-group">
                  <label class="form-label">Special Instructions <span class="optional">(optional)</span></label>
                  <textarea class="form-input <?php echo isset($errors["inst_$sid"]) ? "input-error":""; ?>"
                            name="inst[<?php echo h($sid); ?>]" rows="3"
                            placeholder="Additional notes or preferences (up to 500 chars)"><?php echo h($saved["instructions"] ?? ""); ?></textarea>
                  <?php if(isset($errors["inst_$sid"])): ?><div class="error"><?php echo h($errors["inst_$sid"]); ?></div><?php endif; ?>
                </div>

                <div class="form-group">
                  <label class="form-label">Preferred Deadline <span class="optional">(optional)</span></label>
                  <input class="form-input <?php echo isset($errors["deadline_$sid"]) ? "input-error":""; ?>"
                         type="date" name="deadline[<?php echo h($sid); ?>]"
                         value="<?php echo h($saved["deadline"] ?? ""); ?>">
                  <?php if(isset($errors["deadline_$sid"])): ?><div class="error"><?php echo h($errors["deadline_$sid"]); ?></div><?php endif; ?>
                </div>

                <div class="form-group">
                  <label class="form-label">Requirement Files <span class="optional">(optional, max 3)</span></label>
                  <input class="form-input <?php echo isset($errors["files_$sid"]) ? "input-error":""; ?>"
                         type="file"
                         accept=".pdf,.doc,.docx,.txt,.zip,.jpg,.jpeg,.png"
                         name="files[<?php echo h($sid); ?>][]" multiple>
                  <div class="form-hint">Allowed: PDF/DOC/DOCX/TXT/ZIP/JPG/PNG, max 10MB each.</div>
                  <?php if(isset($errors["files_$sid"])): ?><div class="error"><?php echo h($errors["files_$sid"]); ?></div><?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="checkout-actions">
            <a class="btn btn-secondary" href="cart.php">Edit Cart</a>
            <button class="btn btn-primary" type="submit" name="to_step2">Continue to Payment →</button>
          </div>
        </form>
      <?php endif; ?>

      <!-- STEP 2 -->
      <?php if ($step===2): ?>
        <form method="post">
          <div class="checkout-card">
            <h2 class="heading-secondary">Payment Information</h2>

            <?php
              $selectedMethod = $step2Sticky["payment_method"] ?: (($checkout["payment"]["method"] ?? "") ?: "Credit Card");
              $idCC  = "pm_cc";
              $idPP  = "pm_pp";
              $idBT  = "pm_bt";
            ?>

            <div class="payment-methods">
              <div class="pm-options">
                <label class="pm-option">
                  <input type="radio" id="<?php echo h($idCC); ?>" name="payment_method" value="Credit Card"
                    <?php echo ($selectedMethod==="Credit Card") ? "checked":""; ?>>
                  Credit Card
                </label>

                <label class="pm-option">
                  <input type="radio" id="<?php echo h($idPP); ?>" name="payment_method" value="PayPal"
                    <?php echo ($selectedMethod==="PayPal") ? "checked":""; ?>>
                  PayPal
                </label>

                <label class="pm-option">
                  <input type="radio" id="<?php echo h($idBT); ?>" name="payment_method" value="Bank Transfer"
                    <?php echo ($selectedMethod==="Bank Transfer") ? "checked":""; ?>>
                  Bank Transfer
                </label>
              </div>

              <div class="pm-panels">
                <div class="pm-panel pm-cc">
                  <h3 class="heading-secondary">Credit Card (Simulation)</h3>

                  <div class="form-group">
                    <label class="form-label">Card Number <span class="req">*</span></label>
                    <input class="form-input <?php echo isset($errors["card_number"]) ? "input-error":""; ?>"
                           name="card_number" value="<?php echo h($step2Sticky["card_number"]); ?>" placeholder="16 digits">
                    <?php if(isset($errors["card_number"])): ?><div class="error"><?php echo h($errors["card_number"]); ?></div><?php endif; ?>
                  </div>

                  <div class="form-group">
                    <label class="form-label">Cardholder Name <span class="req">*</span></label>
                    <input class="form-input <?php echo isset($errors["card_name"]) ? "input-error":""; ?>"
                           name="card_name" value="<?php echo h($step2Sticky["card_name"]); ?>" placeholder="Name on card">
                    <?php if(isset($errors["card_name"])): ?><div class="error"><?php echo h($errors["card_name"]); ?></div><?php endif; ?>
                  </div>

                  <div class="form-group">
                    <label class="form-label">Expiration (MM/YY) <span class="req">*</span></label>
                    <input class="form-input <?php echo isset($errors["exp"]) ? "input-error":""; ?>"
                           name="exp" value="<?php echo h($step2Sticky["exp"]); ?>" placeholder="MM/YY">
                    <?php if(isset($errors["exp"])): ?><div class="error"><?php echo h($errors["exp"]); ?></div><?php endif; ?>
                  </div>

                  <div class="form-group">
                    <label class="form-label">CVV <span class="req">*</span></label>
                    <input class="form-input <?php echo isset($errors["cvv"]) ? "input-error":""; ?>"
                           name="cvv" value="<?php echo h($step2Sticky["cvv"]); ?>" placeholder="3 digits">
                    <?php if(isset($errors["cvv"])): ?><div class="error"><?php echo h($errors["cvv"]); ?></div><?php endif; ?>
                  </div>
                </div>

                

               
              </div>
            </div>

            <h2 class="heading-secondary">Billing Address</h2>

            <div class="form-group">
              <label class="form-label">Address Line 1 <span class="req">*</span></label>
              <input class="form-input <?php echo isset($errors["addr1"]) ? "input-error":""; ?>"
                     name="addr1" value="<?php echo h($step2Sticky["addr1"]); ?>">
              <?php if(isset($errors["addr1"])): ?><div class="error"><?php echo h($errors["addr1"]); ?></div><?php endif; ?>
            </div>

            <div class="form-group">
              <label class="form-label">Address Line 2 <span class="optional">(optional)</span></label>
              <input class="form-input" name="addr2" value="<?php echo h($step2Sticky["addr2"]); ?>">
            </div>

            <div class="form-group">
              <label class="form-label">City <span class="req">*</span></label>
              <input class="form-input <?php echo isset($errors["city"]) ? "input-error":""; ?>"
                     name="city" value="<?php echo h($step2Sticky["city"]); ?>">
              <?php if(isset($errors["city"])): ?><div class="error"><?php echo h($errors["city"]); ?></div><?php endif; ?>
            </div>

            <div class="form-group">
              <label class="form-label">State/Province <span class="req">*</span></label>
              <input class="form-input <?php echo isset($errors["state"]) ? "input-error":""; ?>"
                     name="state" value="<?php echo h($step2Sticky["state"]); ?>">
              <?php if(isset($errors["state"])): ?><div class="error"><?php echo h($errors["state"]); ?></div><?php endif; ?>
            </div>

            <div class="form-group">
              <label class="form-label">Postal Code <span class="req">*</span></label>
              <input class="form-input <?php echo isset($errors["zip"]) ? "input-error":""; ?>"
                     name="zip" value="<?php echo h($step2Sticky["zip"]); ?>">
              <?php if(isset($errors["zip"])): ?><div class="error"><?php echo h($errors["zip"]); ?></div><?php endif; ?>
            </div>

            <div class="form-group">
              <label class="form-label">Country <span class="req">*</span></label>
              <input class="form-input <?php echo isset($errors["country"]) ? "input-error":""; ?>"
                     name="country" value="<?php echo h($step2Sticky["country"]); ?>">
              <?php if(isset($errors["country"])): ?><div class="error"><?php echo h($errors["country"]); ?></div><?php endif; ?>
            </div>

          </div>

          <div class="checkout-actions">
            <a class="btn btn-secondary" href="checkout.php?goto=1">Edit Requirements</a>
            <button class="btn btn-primary" type="submit" name="to_step3">Continue to Review</button>
          </div>
        </form>
      <?php endif; ?>

      <!-- STEP 3 -->
      <?php if ($step===3): ?>
        <form method="post">
          <div class="checkout-review-layout">

            <div class="checkout-review-main">
              <div class="checkout-card">
                <h2 class="heading-secondary">Review & Confirmation</h2>

                <?php foreach($services as $sv):
                  $sid=(string)$sv["service_id"];
                  $req=$checkout["requirements"][$sid] ?? ["requirements"=>"","instructions"=>"","deadline"=>null,"files"=>[]];
                ?>
                  <details class="review-service" open>
                    <summary>
                      <span><?php echo h($sv["title"]); ?> — <?php echo h($sv["freelancer_name"]); ?></span>
                      <span>$<?php echo number_format((float)$sv["price"],2); ?></span>
                    </summary>

                    <div class="review-service-body">
                      <div class="review-kv">
                        <div class="k">Requirements</div>
                        <div class="v"><?php echo nl2br(h($req["requirements"])); ?></div>

                        <div class="k">Special Instructions</div>
                        <div class="v"><?php echo ($req["instructions"]!=="") ? nl2br(h($req["instructions"])) : "<span class='text-muted'>None</span>"; ?></div>

                        <div class="k">Preferred Deadline</div>
                        <div class="v"><?php echo ($req["deadline"] ? h($req["deadline"]) : "<span class='text-muted'>Not set</span>"); ?></div>
                      </div>

                      <h3 class="heading-secondary review-files-title">Files</h3>

                      <?php $files=$req["files"] ?? []; ?>
                      <?php if(count($files) < 1): ?>
                        <div class="files-empty">No files uploaded</div>
                      <?php else: ?>
                        <div class="files-list">
                          <?php foreach($files as $f):
                            $ext = $f["ext"] ?? "file";
                            if (!in_array($ext, ["pdf","doc","docx","jpg","jpeg","png","gif","zip","rar"], true)) $ext="file";
                            $date = !empty($f["uploaded_at"]) ? date("M d, Y", strtotime($f["uploaded_at"])) : date("M d, Y");
                          ?>
                            <div class="file-item" data-filetype="<?php echo h($ext); ?>">
                              <div class="file-icon"></div>
                              <div class="file-info">
                                <a class="file-name" href="<?php echo h($f["stored_web"]); ?>" target="_blank"><?php echo h($f["original"]); ?></a>
                                <div class="file-size"><?php echo h(bytes_to_human((int)$f["size"])); ?></div>
                                <div class="file-date"><?php echo h($date); ?></div>
                              </div>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>

                    </div>
                  </details>
                <?php endforeach; ?>

                <div class="checkout-card review-payment-card">
                  <h2 class="heading-secondary">Payment Summary (Simulation)</h2>
                  <p class="text-muted review-payment-text">
                    Method: <strong><?php echo h($checkout["payment"]["method"]); ?></strong><br>
                    Billing Address:<br>
                    <?php echo nl2br(h($checkout["payment"]["billing"])); ?><br>
                    <?php if(!empty($checkout["payment"]["card_masked"])): ?>
                      Card: <?php echo h($checkout["payment"]["card_masked"]); ?> (Exp: <?php echo h($checkout["payment"]["exp"]); ?>)
                    <?php endif; ?>
                  </p>
                </div>

                <div class="form-group">
                  <label class="form-label">
                    <input type="checkbox" name="terms" required <?php echo !empty($checkout["terms"]) ? "checked":""; ?>>
                    I agree to the <a class="action-link" href="terms.php">Terms of Service</a> and
                    <a class="action-link" href="privacy.php">Privacy Policy</a>
                  </label>
                  <?php if(isset($errors["terms"])): ?><div class="error"><?php echo h($errors["terms"]); ?></div><?php endif; ?>
                </div>

                <div class="checkout-actions">
                  <a class="btn btn-secondary" href="checkout.php?goto=1">Edit Service Requirements</a>
                  <a class="btn btn-secondary" href="checkout.php?goto=2">Edit Payment Information</a>
                </div>

              </div>
            </div>

            <aside class="checkout-review-sidebar">
              <div class="summary-card">
                <div class="summary-count">You will place <?php echo count($services); ?> orders</div>

                <div class="summary-services">
                  <?php foreach($services as $sv):
                    $price = (float)$sv["price"];
                    $fee = round($price * 0.05, 2);
                    $total = round($price + $fee, 2);
                  ?>
                    <div class="summary-service">
                      <div>
                        <div class="summary-service-title">
                          <?php echo h($sv["title"]); ?>
                          <span class="summary-check">✓</span>
                        </div>
                        <div class="summary-service-freelancer">
                          <a class="action-link" href="profile.php?id=<?php echo urlencode((string)$sv["freelancer_id"]); ?>">
                            <?php echo h($sv["freelancer_name"]); ?>
                          </a>
                        </div>
                        <div class="summary-service-price">Price: $<?php echo number_format($price,2); ?></div>
                        <div class="summary-service-fee">Service Fee (5%): $<?php echo number_format($fee,2); ?></div>
                      </div>
                      <div class="summary-service-total">$<?php echo number_format($total,2); ?></div>
                    </div>
                  <?php endforeach; ?>
                </div>

                <div class="summary-totals">
                  <div class="summary-row"><span>Subtotal</span><strong>$<?php echo number_format($subtotal,2); ?></strong></div>
                  <div class="summary-row"><span>Service fee (5%)</span><strong>$<?php echo number_format($serviceFee,2); ?></strong></div>
                  <div class="summary-row summary-grand"><span>Grand total</span><span>$<?php echo number_format($grandTotal,2); ?></span></div>
                </div>

                <button class="btn place-order-btn" type="submit" name="place_order">Place Order</button>
              </div>
            </aside>

          </div>
        </form>
      <?php endif; ?>

    </main>
  </div>
</div>

<?php include "includes/footer.php"; ?>