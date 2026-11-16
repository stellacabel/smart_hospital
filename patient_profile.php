<?php

session_start();

require_once "db.php";

/* =========================================================
   SECURITY CHECK
========================================================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "patient") {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];
$full_name = $_SESSION["full_name"] ?? "Patient";

/* =========================================================
   FIND PATIENT RECORD
========================================================= */

$patient = null;

/*
   The users table contains the login account.
   The patients table contains the patient's information.

   We first try to match the patient's email with
   the logged-in user's email.
*/

$stmt = $conn->prepare("
    SELECT
        p.id,
        p.patient_number,
        p.full_name,
        p.date_of_birth,
        p.gender,
        p.phone,
        p.email,
        p.address,
        p.insurance_provider,
        p.emergency_contact,
        p.emergency_phone,
        p.created_at
    FROM patients p
    INNER JOIN users u
        ON p.email = u.email
    WHERE u.id = ?
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $patient = $result->fetch_assoc();
}

$stmt->close();

/* =========================================================
   IF PATIENT RECORD IS NOT FOUND
========================================================= */

if (!$patient) {
    $patient = [
        "patient_number" => "Not available",
        "full_name" => $full_name,
        "date_of_birth" => null,
        "gender" => null,
        "phone" => null,
        "email" => null,
        "address" => null,
        "insurance_provider" => null,
        "emergency_contact" => null,
        "emergency_phone" => null,
        "created_at" => null
    ];
}

/* =========================================================
   HELPER
========================================================= */

function displayValue($value)
{
    if ($value === null || trim((string)$value) === "") {
        return "Not provided";
    }

    return htmlspecialchars($value);
}

/* =========================================================
   AGE CALCULATION
========================================================= */

$age = "Not available";

if (!empty($patient["date_of_birth"])) {

    try {

        $birth_date = new DateTime($patient["date_of_birth"]);
        $today = new DateTime();

        $age = $today->diff($birth_date)->y . " years";

    } catch (Exception $e) {

        $age = "Not available";
    }
}

/* =========================================================
   INITIAL
========================================================= */

$profile_name = $patient["full_name"] ?: $full_name;

$initial = strtoupper(
    substr(
        trim($profile_name),
        0,
        1
    )
);

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0"

>

<title>
    My Profile | SmartHospital
</title>

<style>

/* =========================================================
   RESET
========================================================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

/* =========================================================
   BODY
========================================================= */

body {

    font-family:
        "Segoe UI",
        Arial,
        sans-serif;

    background: #f5f9fa;

    color: #17324d;
}

/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    position: fixed;

    left: 0;
    top: 0;

    width: 245px;
    height: 100vh;

    background: #102d44;

    color: white;

    padding: 25px 18px;

    z-index: 100;
}

/* =========================================================
   LOGO
========================================================= */

.logo {

    display: flex;

    align-items: center;

    gap: 10px;

    padding:
        0
        10px
        30px;

    border-bottom:
        1px solid
        rgba(255,255,255,0.10);

    color: white;

    text-decoration: none;

    font-size: 20px;

    font-weight: 700;
}

.logo-icon {

    width: 38px;
    height: 38px;

    background: #087f8c;

    border-radius: 10px;

    display: flex;

    align-items: center;
    justify-content: center;

    font-size: 21px;
}

/* =========================================================
   MENU
========================================================= */

.menu {

    margin-top: 30px;
}

.menu-title {

    color: #8fa5b5;

    font-size: 11px;

    text-transform: uppercase;

    letter-spacing: 1px;

    padding: 0 12px;

    margin-bottom: 12px;
}

.menu a {

    display: flex;

    align-items: center;

    gap: 12px;

    color: #d9e4ea;

    text-decoration: none;

    padding: 13px 12px;

    margin-bottom: 5px;

    border-radius: 8px;

    font-size: 14px;

    transition: 0.2s;
}

.menu a:hover,
.menu a.active {

    background: #087f8c;

    color: white;
}

.menu-icon {

    width: 22px;

    text-align: center;

    font-size: 16px;
}

/* =========================================================
   LOGOUT
========================================================= */

.logout {

    position: absolute;

    bottom: 25px;

    left: 18px;
    right: 18px;
}

.logout a {

    display: block;

    text-align: center;

    padding: 11px;

    border-radius: 8px;

    background:
        rgba(255,255,255,0.08);

    color: #dce7ed;

    text-decoration: none;

    font-size: 13px;

    transition: 0.2s;
}

.logout a:hover {

    background: #b42318;

    color: white;
}

/* =========================================================
   MAIN
========================================================= */

.main {

    margin-left: 245px;

    min-height: 100vh;
}

/* =========================================================
   TOPBAR
========================================================= */

.topbar {

    height: 76px;

    background: white;

    border-bottom:
        1px solid
        #e8eef1;

    padding: 0 35px;

    display: flex;

    align-items: center;

    justify-content: space-between;
}

.page-title h1 {

    font-size: 21px;

    color: #17324d;
}

.page-title p {

    margin-top: 3px;

    color: #8494a0;

    font-size: 12px;
}

.patient-profile {

    display: flex;

    align-items: center;

    gap: 11px;
}

.avatar {

    width: 38px;
    height: 38px;

    border-radius: 50%;

    background: #e8f7f8;

    color: #087f8c;

    display: flex;

    align-items: center;
    justify-content: center;

    font-weight: 700;

    font-size: 14px;
}

.profile-info {

    line-height: 1.3;
}

.profile-info strong {

    display: block;

    font-size: 13px;
}

.profile-info span {

    color: #8a99a4;

    font-size: 11px;
}

/* =========================================================
   CONTENT
========================================================= */

.content {

    padding: 32px 35px;
}

/* =========================================================
   PAGE HEADING
========================================================= */

.page-heading {

    margin-bottom: 25px;
}

.page-heading h2 {

    font-size: 22px;
}

.page-heading p {

    margin-top: 5px;

    color: #8796a1;

    font-size: 13px;
}

/* =========================================================
   PROFILE HEADER
========================================================= */

.profile-header {

    background:
        linear-gradient(
            135deg,
            #087f8c,
            #066b76
        );

    color: white;

    border-radius: 14px;

    padding: 28px;

    display: flex;

    align-items: center;

    gap: 20px;

    margin-bottom: 22px;
}

.large-avatar {

    width: 72px;
    height: 72px;

    border-radius: 50%;

    background:
        rgba(255,255,255,0.18);

    border:
        2px solid
        rgba(255,255,255,0.35);

    display: flex;

    align-items: center;
    justify-content: center;

    font-size: 28px;

    font-weight: 700;
}

.profile-header h2 {

    font-size: 22px;

    margin-bottom: 5px;
}

.profile-header p {

    font-size: 13px;

    opacity: 0.85;
}

.patient-number {

    display: inline-block;

    margin-top: 9px;

    padding: 5px 10px;

    border-radius: 20px;

    background:
        rgba(255,255,255,0.15);

    font-size: 11px;
}

/* =========================================================
   GRID
========================================================= */

.profile-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 20px;
}

/* =========================================================
   CARD
========================================================= */

.card {

    background: white;

    border:
        1px solid
        #e9eff2;

    border-radius: 12px;

    padding: 23px;

    box-shadow:
        0 5px 18px
        rgba(23,50,77,0.04);
}

.card-header {

    display: flex;

    align-items: center;

    gap: 10px;

    margin-bottom: 20px;

    padding-bottom: 14px;

    border-bottom:
        1px solid
        #edf1f3;
}

.card-icon {

    width: 35px;
    height: 35px;

    border-radius: 8px;

    background: #e8f7f8;

    color: #087f8c;

    display: flex;

    align-items: center;
    justify-content: center;

    font-size: 16px;
}

.card-header h3 {

    font-size: 15px;
}

/* =========================================================
   INFORMATION
========================================================= */

.info-list {

    display: grid;

    gap: 17px;
}

.info-row {

    display: flex;

    justify-content: space-between;

    gap: 20px;

    padding-bottom: 12px;

    border-bottom:
        1px solid
        #f0f3f4;
}

.info-row:last-child {

    border-bottom: none;

    padding-bottom: 0;
}

.info-label {

    color: #8796a1;

    font-size: 11px;

    min-width: 130px;
}

.info-value {

    text-align: right;

    color: #17324d;

    font-size: 12px;

    font-weight: 600;

    word-break: break-word;
}

/* =========================================================
   SECURITY CARD
========================================================= */

.security-card {

    margin-top: 20px;
}

.security-message {

    background: #f4fafb;

    border-radius: 8px;

    padding: 14px;

    color: #58707d;

    font-size: 12px;

    line-height: 1.6;
}

/* =========================================================
   BACK BUTTON
========================================================= */

.back-button {

    display: inline-block;

    margin-top: 22px;

    padding: 11px 18px;

    background: #087f8c;

    color: white;

    text-decoration: none;

    border-radius: 8px;

    font-size: 12px;

    font-weight: 600;

    transition: 0.2s;
}

.back-button:hover {

    background: #066b76;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 950px) {

    .profile-grid {

        grid-template-columns: 1fr;
    }
}

@media (max-width: 700px) {

    .sidebar {

        width: 70px;

        padding: 20px 10px;
    }

    .logo {

        justify-content: center;

        padding:
            0
            0
            20px;
    }

    .logo span:last-child {

        display: none;
    }

    .menu-title,
    .menu a span:not(.menu-icon),
    .logout a {

        display: none;
    }

    .menu a {

        justify-content: center;
    }

    .main {

        margin-left: 70px;
    }

    .topbar {

        padding: 0 20px;
    }

    .profile-info {

        display: none;
    }

    .content {

        padding:
            22px
            18px;
    }

    .profile-header {

        padding: 22px;

        flex-direction: column;

        text-align: center;
    }

    .info-row {

        flex-direction: column;

        gap: 5px;
    }

    .info-value {

        text-align: left;
    }
}

</style>

</head>

<body>

<!-- =========================================================
     SIDEBAR
========================================================= -->

<aside class="sidebar">

```
<a
    href="patient_dashboard.php"
    class="logo"
>

    <span class="logo-icon">
        +
    </span>

    <span>
        SmartHospital
    </span>

</a>

<div class="menu">

    <div class="menu-title">
        Patient Menu
    </div>

    <!-- DASHBOARD -->

    <a href="patient_dashboard.php">

        <span class="menu-icon">
            ⌂
        </span>

        <span>
            Dashboard
        </span>

    </a>

    <!-- MY QUEUE -->

    <a href="patient_queue.php">

        <span class="menu-icon">
            🎫
        </span>

        <span>
            My Queue
        </span>

    </a>

    <!-- HISTORY -->

    <a href="patient_history.php">

        <span class="menu-icon">
            📋
        </span>

        <span>
            My History
        </span>

    </a>

    <!-- PROFILE -->

    <a
        href="patient_profile.php"
        class="active"
    >

        <span class="menu-icon">
            👤
        </span>

        <span>
            My Profile
        </span>

    </a>

</div>

<!-- LOGOUT -->

<div class="logout">

    <a href="logout.php">
        Logout
    </a>

</div>
```

</aside>

<!-- =========================================================
     MAIN
========================================================= -->

<div class="main">

```
<!-- TOPBAR -->

<header class="topbar">

    <div class="page-title">

        <h1>
            My Profile
        </h1>

        <p>
            SmartHospital Queue Management System
        </p>

    </div>

    <div class="patient-profile">

        <div class="avatar">
            <?= htmlspecialchars($initial) ?>
        </div>

        <div class="profile-info">

            <strong>
                <?= htmlspecialchars($profile_name) ?>
            </strong>

            <span>
                Patient
            </span>

        </div>

    </div>

</header>

<!-- CONTENT -->

<main class="content">

    <div class="page-heading">

        <h2>
            Personal Information
        </h2>

        <p>
            View your registered patient information.
        </p>

    </div>

    <!-- PROFILE HEADER -->

    <section class="profile-header">

        <div class="large-avatar">
            <?= htmlspecialchars($initial) ?>
        </div>

        <div>

            <h2>
                <?= displayValue($patient["full_name"]) ?>
            </h2>

            <p>
                Registered SmartHospital Patient
            </p>

            <span class="patient-number">

                Patient No:
                <?= displayValue($patient["patient_number"]) ?>

            </span>

        </div>

    </section>

    <!-- PROFILE INFORMATION -->

    <section class="profile-grid">

        <!-- PERSONAL INFORMATION -->

        <div class="card">

            <div class="card-header">

                <div class="card-icon">
                    👤
                </div>

                <h3>
                    Personal Details
                </h3>

            </div>

            <div class="info-list">

                <div class="info-row">

                    <span class="info-label">
                        Full Name
                    </span>

                    <span class="info-value">
                        <?= displayValue($patient["full_name"]) ?>
                    </span>

                </div>

                <div class="info-row">

                    <span class="info-label">
                        Patient Number
                    </span>

                    <span class="info-value">
                        <?= displayValue($patient["patient_number"]) ?>
                    </span>

                </div>

                <div class="info-row">

                    <span class="info-label">
                        Date of Birth
                    </span>

                    <span class="info-value">

                        <?php if (!empty($patient["date_of_birth"])): ?>

                            <?= htmlspecialchars(
                                date(
                                    "d M Y",
                                    strtotime($patient["date_of_birth"])
                                )
                            ) ?>

                        <?php else: ?>

                            Not provided

                        <?php endif; ?>

                    </span>

                </div>

                <div class="info-row">

                    <span class="info-label">
                        Age
                    </span>

                    <span class="info-value">
                        <?= htmlspecialchars($age) ?>
                    </span>

                </div>

                <div class="info-row">

                    <span class="info-label">
                        Gender
                    </span>

                    <span class="info-value">
                        <?= displayValue($patient["gender"]) ?>
                    </span>

                </div>

            </div>

        </div>

        <!-- CONTACT INFORMATION -->

        <div class="card">

            <div class="card-header">

                <div class="card-icon">
                    📞
                </div>

                <h3>
                    Contact Information
                </h3>

            </div>

            <div class="info-list">

                <div class="info-row">

                    <span class="info-label">
                        Phone
                    </span>

                    <span class="info-value">
                        <?= displayValue($patient["phone"]) ?>
                    </span>

                </div>

                <div class="info-row">

                    <span class="info-label">
                        Email
                    </span>

                    <span class="info-value">
                        <?= displayValue($patient["email"]) ?>
                    </span>

                </div>

                <div class="info-row">

                    <span class="info-label">
                        Address
                    </span>

                    <span class="info-value">
                        <?= displayValue($patient["address"]) ?>
                    </span>

                </div>

            </div>

        </div>

        <!-- INSURANCE -->

        <div class="card">

            <div class="card-header">

                <div class="card-icon">
                    🛡️
                </div>

                <h3>
                    Insurance Information
                </h3>

            </div>

            <div class="info-list">

                <div class="info-row">

                    <span class="info-label">
                        Insurance Provider
                    </span>

                    <span class="info-value">
                        <?= displayValue(
                            $patient["insurance_provider"]
                        ) ?>
                    </span>

                </div>

            </div>

        </div>

        <!-- EMERGENCY CONTACT -->

        <div class="card">

            <div class="card-header">

                <div class="card-icon">
                    🚨
                </div>

                <h3>
                    Emergency Contact
                </h3>

            </div>

            <div class="info-list">

                <div class="info-row">

                    <span class="info-label">
                        Contact Name
                    </span>

                    <span class="info-value">
                        <?= displayValue(
                            $patient["emergency_contact"]
                        ) ?>
                    </span>

                </div>

                <div class="info-row">

                    <span class="info-label">
                        Contact Phone
                    </span>

                    <span class="info-value">
                        <?= displayValue(
                            $patient["emergency_phone"]
                        ) ?>
                    </span>

                </div>

            </div>

        </div>

    </section>

    <!-- SECURITY -->

    <section class="card security-card">

        <div class="card-header">

            <div class="card-icon">
                🔒
            </div>

            <h3>
                Account Security
            </h3>

        </div>

        <div class="security-message">

            Your account information is protected by
            SmartHospital. If any of your personal information
            is incorrect, please contact the hospital
            administration for assistance.

        </div>

    </section>

    <a
        href="patient_dashboard.php"
        class="back-button"
    >
        ← Back to Dashboard
    </a>

</main>
```

</div>

</body>

</html>
