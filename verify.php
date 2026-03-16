<?php
require_once "config.php";

if (isset($_GET["email"]) && isset($_GET["token"])) {

    $email = $_GET["email"];
    $token = $_GET["token"];

    $stmt = $link->prepare("SELECT verification_token FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($dbToken);
    $stmt->fetch();
    $stmt->close();

    if ($dbToken === $token) {

        $update = $link->prepare("UPDATE users SET is_verified=1, verification_token=NULL WHERE email=?");
        $update->bind_param("s", $email);
        $update->execute();
        $update->close();

        echo "Email verified successfully.";

    } else {
        echo "Invalid or expired verification link.";
    }
}