<?php

require_once "db.php";

$email = "admin@smarthospital.com";
$new_password = "Admin@12345";

$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

$stmt = $conn->prepare(
    "UPDATE users
     SET password = ?
     WHERE email = ?"
);

$stmt->bind_param(
    "ss",
    $hashed_password,
    $email
);

if ($stmt->execute()) {

    if ($stmt->affected_rows > 0) {

        echo "Admin password updated successfully.<br><br>";
        echo "Email: admin@smarthospital.com<br>";
        echo "Password: Admin@12345";

    } else {

        echo "Admin account was found, but the password was not changed.";

    }

} else {

    echo "Error: " . $stmt->error;

}

$stmt->close();
$conn->close();

?>