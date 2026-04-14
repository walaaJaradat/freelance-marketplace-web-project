<?php

require_once __DIR__ . "/Service.php";

session_start();

if (isset($_SESSION["LAST_ACTIVITY"]) && (time() - $_SESSION["LAST_ACTIVITY"] > 86400)) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION["LAST_ACTIVITY"] = time();

require_once __DIR__ . "/db.php.inc";

if (!function_exists("h")) {
    function h($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8");
    }
}

if (!function_exists("str_len")) {
    function str_len($s) {
        $s = (string)$s;
        if (function_exists("mb_strlen")) return mb_strlen($s, "UTF-8");
        return strlen($s);
    }
}

$isLoggedIn = isset($_SESSION["user"]);
$userRole   = $isLoggedIn ? ($_SESSION["user"]["role"] ?? "Guest") : "Guest";