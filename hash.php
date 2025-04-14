<?php
$password = 'password'; // Replace with your password
$hash = '$2y$10$RHko17TEpSLB.Y6kszF4VOpyFPB/8M.3aN8emCPoNRa...'; // Replace with password_hash from database
if (password_verify($password, $hash)) {
    echo "Password matches!";
} else {
    echo "Password does NOT match.";
}
?>