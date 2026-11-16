
<?php

session_start();

require_once "db.php";

/* =========================
   SECURITY
========================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "patient") {
    header("Location: login.php");
    exit;
}

$code = trim($_GET["code"] ?? "");

$full_name = $_SESSION["full_name"] ?? "Patient";

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>QR Result | SmartHospital</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: "Segoe UI", Arial, sans-serif;
    background: #f5f9fa;
    color: #17324d;
    min-height: 100vh;

    display: flex;
    align-items: center;
    justify-content: center;

    padding: 20px;
}

.card {
    width: 100%;
    max-width: 500px;

    background: white;

    border: 1px solid #e9eff2;

    border-radius: 16px;

    padding: 35px;

    text-align: center;

    box-shadow:
        0 8px 25px
        rgba(23,50,77,0.06);
}

.icon {
    width: 65px;
    height: 65px;

    margin: 0 auto 20px;

    border-radius: 15px;

    background: #e8f7f8;
    color: #087f8c;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 30px;
}

h1 {
    font-size: 23px;
    margin-bottom: 10px;
}

p {
    color: #8796a1;
    font-size: 13px;
    line-height: 1.6;
}

.code {
    margin: 20px 0;

    padding: 14px;

    background: #f7fafb;

    border-radius: 8px;

    color: #087f8c;

    font-size: 12px;

    word-break: break-all;
}

.button {
    display: inline-block;

    margin-top: 10px;

    padding: 12px 20px;

    background: #087f8c;

    color: white;

    text-decoration: none;

    border-radius: 8px;

    font-size: 13px;

    font-weight: 600;
}

.button:hover {
    background: #066b76;
}

</style>

</head>

<body>

<div class="card">

    <div class="icon">
        ✓
    </div>

    <h1>
        QR Code Scanned
    </h1>

    <p>
        Hello
        <strong>
            <?= htmlspecialchars($full_name) ?>
        </strong>.
        Your QR code was successfully detected.
    </p>

    <?php if ($code !== ""): ?>

        <div class="code">

            <?= htmlspecialchars($code) ?>

        </div>

    <?php endif; ?>

    <a
        href="patient_queue.php"
        class="button"
    >
        🎫 View My Queue
    </a>

    <a
        href="patient_dashboard.php"
        class="button"
        style="background:#eef3f5;color:#17324d;margin-left:5px;"
    >
        Dashboard
    </a>

</div>

</body>

</html>
```
