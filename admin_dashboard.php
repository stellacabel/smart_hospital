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

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}

$full_name = $_SESSION["full_name"] ?? "Administrator";


/* =========================================================
   DASHBOARD STATISTICS
========================================================= */

$total_users        = 0;
$total_patients     = 0;
$total_doctors      = 0;
$total_nurses       = 0;

$total_queue_today  = 0;
$waiting_patients   = 0;
$called_patients    = 0;
$serving_patients   = 0;
$completed_today    = 0;
$urgent_patients    = 0;
$emergency_patients = 0;

$current_queue_number = "—";
$current_patient_name = "No patient currently serving";


/* =========================================================
   TOTAL USERS
========================================================= */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM users
");

if ($result) {
    $row = $result->fetch_assoc();
    $total_users = (int)$row["total"];
}


/* =========================================================
   TOTAL PATIENTS
========================================================= */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'patient'
");

if ($result) {
    $row = $result->fetch_assoc();
    $total_patients = (int)$row["total"];
}


/* =========================================================
   TOTAL DOCTORS
========================================================= */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'doctor'
");

if ($result) {
    $row = $result->fetch_assoc();
    $total_doctors = (int)$row["total"];
}


/* =========================================================
   TOTAL NURSES
========================================================= */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM nurses
");

if ($result) {
    $row = $result->fetch_assoc();
    $total_nurses = (int)$row["total"];
}


/* =========================================================
   TODAY'S TOTAL QUEUE
========================================================= */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM queue
    WHERE DATE(check_in_time) = CURDATE()
");

if ($result) {
    $row = $result->fetch_assoc();
    $total_queue_today = (int)$row["total"];
}


/* =========================================================
   TODAY'S WAITING PATIENTS
========================================================= */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM queue
    WHERE status = 'waiting'
    AND DATE(check_in_time) = CURDATE()
");

if ($result) {
    $row = $result->fetch_assoc();
    $waiting_patients = (int)$row["total"];
}


/* =========================================================
   TODAY'S CALLED PATIENTS
========================================================= */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM queue
    WHERE status = 'called'
    AND DATE(check_in_time) = CURDATE()
");

if ($result) {
    $row = $result->fetch_assoc();
    $called_patients = (int)$row["total"];
}


/* =========================================================
   TODAY'S SERVING PATIENTS
========================================================= */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM queue
    WHERE status = 'serving'
    AND DATE(check_in_time) = CURDATE()
");

if ($result) {
    $row = $result->fetch_assoc();
    $serving_patients = (int)$row["total"];
}


/* =========================================================
   TODAY'S COMPLETED PATIENTS
========================================================= */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM queue
    WHERE status = 'completed'
    AND DATE(check_in_time) = CURDATE()
");

if ($result) {
    $row = $result->fetch_assoc();
    $completed_today = (int)$row["total"];
}


/* =========================================================
   TODAY'S URGENT CASES
========================================================= */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM queue
    WHERE priority = 'urgent'
    AND DATE(check_in_time) = CURDATE()
");

if ($result) {
    $row = $result->fetch_assoc();
    $urgent_patients = (int)$row["total"];
}


/* =========================================================
   TODAY'S EMERGENCY CASES
========================================================= */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM queue
    WHERE priority = 'emergency'
    AND DATE(check_in_time) = CURDATE()
");

if ($result) {
    $row = $result->fetch_assoc();
    $emergency_patients = (int)$row["total"];
}


/* =========================================================
   CURRENTLY SERVING PATIENT
========================================================= */

$result = $conn->query("
    SELECT
        q.queue_number,
        p.full_name AS patient_name
    FROM queue q
    INNER JOIN patients p
        ON q.patient_id = p.id
    WHERE q.status = 'serving'
    AND DATE(q.check_in_time) = CURDATE()
    ORDER BY q.check_in_time ASC
    LIMIT 1
");

if ($result && $result->num_rows > 0) {

    $row = $result->fetch_assoc();

    $current_queue_number = $row["queue_number"];
    $current_patient_name = $row["patient_name"];
}


/* =========================================================
   ADMIN INITIAL
========================================================= */

$first_letter = strtoupper(
    substr(trim($full_name), 0, 1)
);


/* =========================================================
   SAFE OUTPUT
========================================================= */

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Admin Dashboard | SmartHospital</title>


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
    font-family: "Segoe UI", Arial, sans-serif;
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
    overflow-y: auto;
}


.logo {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 10px 30px;
    border-bottom: 1px solid rgba(255,255,255,0.10);
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
    margin-top: 35px;
    padding-bottom: 10px;
}


.logout a {
    display: block;
    text-align: center;
    padding: 11px;
    border-radius: 8px;
    background: rgba(255,255,255,0.08);
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
    border-bottom: 1px solid #e8eef1;
    padding: 0 35px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}


.page-title h1 {
    font-size: 21px;
}


.page-title p {
    margin-top: 3px;
    color: #8494a0;
    font-size: 12px;
}


.admin-profile {
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
}


.admin-info strong {
    display: block;
    font-size: 13px;
}


.admin-info span {
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
   WELCOME
========================================================= */

.welcome {
    background: linear-gradient(135deg, #087f8c, #066b76);
    color: white;
    border-radius: 14px;
    padding: 25px 28px;
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}


.welcome h2 {
    font-size: 21px;
    margin-bottom: 7px;
}


.welcome p {
    font-size: 13px;
    opacity: 0.85;
}


.welcome-icon {
    font-size: 45px;
    opacity: 0.25;
}


/* =========================================================
   MAIN STATISTICS
========================================================= */

.stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-bottom: 25px;
}


.stat-card {
    background: white;
    border: 1px solid #e9eff2;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 5px 18px rgba(23,50,77,0.04);
}


.stat-icon {
    width: 45px;
    height: 45px;
    border-radius: 10px;
    background: #e8f7f8;
    color: #087f8c;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 19px;
}


.stat-card h3 {
    font-size: 23px;
    margin-bottom: 3px;
}


.stat-card p {
    color: #8796a1;
    font-size: 12px;
}


/* =========================================================
   QUEUE STATISTICS
========================================================= */

.queue-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-bottom: 25px;
}


.queue-stat {
    background: white;
    border: 1px solid #e9eff2;
    border-radius: 12px;
    padding: 18px 20px;
    box-shadow: 0 5px 18px rgba(23,50,77,0.04);
}


.queue-stat h4 {
    font-size: 12px;
    color: #687781;
    margin-bottom: 7px;
}


.queue-stat strong {
    font-size: 25px;
    color: #087f8c;
}


.queue-stat p {
    margin-top: 4px;
    color: #8796a1;
    font-size: 11px;
}


/* =========================================================
   DASHBOARD GRID
========================================================= */

.dashboard-grid {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 20px;
}


.card {
    background: white;
    border: 1px solid #e9eff2;
    border-radius: 12px;
    padding: 23px;
    box-shadow: 0 5px 18px rgba(23,50,77,0.04);
}


.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}


.card-header h3 {
    font-size: 16px;
}


.card-header span {
    color: #8b9aa4;
    font-size: 11px;
}


/* =========================================================
   QUICK ACTIONS
========================================================= */

.actions {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}


.action {
    border: 1px solid #e6edef;
    border-radius: 9px;
    padding: 15px;
    text-decoration: none;
    color: #17324d;
    transition: 0.2s;
}


.action:hover {
    border-color: #087f8c;
    transform: translateY(-2px);
}


.action-icon {
    width: 35px;
    height: 35px;
    border-radius: 8px;
    background: #e8f7f8;
    color: #087f8c;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 10px;
}


.action strong {
    display: block;
    font-size: 13px;
    margin-bottom: 4px;
}


.action span {
    color: #8a99a4;
    font-size: 11px;
}


/* =========================================================
   QUEUE BOX
========================================================= */

.queue-box {
    text-align: center;
    padding: 10px 0;
}


.queue-number {
    font-size: 42px;
    font-weight: 700;
    color: #087f8c;
    margin-bottom: 5px;
}


.queue-box p {
    color: #8796a1;
    font-size: 12px;
}


.queue-status {
    margin-top: 18px;
    padding: 12px;
    background: #f4fafb;
    border-radius: 7px;
    color: #087f8c;
    font-size: 12px;
}


.current-patient {
    margin-top: 12px;
    padding: 12px;
    border: 1px solid #e6edef;
    border-radius: 8px;
    text-align: left;
}


.current-patient small {
    display: block;
    color: #8796a1;
    font-size: 10px;
    margin-bottom: 4px;
}


.current-patient strong {
    font-size: 13px;
}


/* =========================================================
   BUTTONS
========================================================= */

.queue-button,
.report-button {
    display: inline-block;
    margin-top: 15px;
    padding: 10px 18px;
    border-radius: 7px;
    text-decoration: none;
    font-size: 12px;
    transition: 0.2s;
}


.queue-button {
    background: #087f8c;
    color: white;
}


.queue-button:hover {
    background: #066b76;
}


.report-button {
    margin-top: 10px;
    background: #eef3f5;
    color: #17324d;
}


.report-button:hover {
    background: #dce8ec;
}


/* =========================================================
   CONSULTATION REPORT LINK
========================================================= */

.consultation-link {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 12px;
    padding: 10px 13px;
    border-radius: 8px;
    background: #e8f7f8;
    color: #087f8c;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
    transition: 0.2s;
}


.consultation-link:hover {
    background: #d7eff1;
}


/* =========================================================
   AI BUTTON
========================================================= */

.ai-button {
    position: fixed;
    right: 25px;
    bottom: 25px;
    width: 58px;
    height: 58px;
    border: none;
    border-radius: 50%;
    background: #087f8c;
    color: white;
    font-size: 25px;
    cursor: pointer;
    box-shadow: 0 8px 25px rgba(8,127,140,0.30);
    z-index: 999;
}


.ai-button:hover {
    background: #066b76;
    transform: scale(1.05);
}


/* =========================================================
   AI CHAT
========================================================= */

.ai-chat {
    position: fixed;
    right: 25px;
    bottom: 95px;
    width: 350px;
    max-width: calc(100vw - 30px);
    background: white;
    border-radius: 15px;
    box-shadow: 0 15px 45px rgba(23,50,77,0.20);
    border: 1px solid #e4ecef;
    overflow: hidden;
    display: none;
    z-index: 998;
}


.ai-chat.active {
    display: block;
}


/* =========================================================
   AI HEADER
========================================================= */

.ai-header {
    background: #102d44;
    color: white;
    padding: 15px 17px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}


.ai-header-left {
    display: flex;
    align-items: center;
    gap: 10px;
}


.ai-icon {
    width: 35px;
    height: 35px;
    border-radius: 9px;
    background: #087f8c;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
}


.ai-header strong {
    display: block;
    font-size: 13px;
}


.ai-header span {
    font-size: 10px;
    opacity: 0.7;
}


.ai-close {
    background: transparent;
    border: none;
    color: white;
    font-size: 20px;
    cursor: pointer;
}


/* =========================================================
   AI MESSAGES
========================================================= */

.ai-messages {
    height: 290px;
    padding: 15px;
    overflow-y: auto;
    background: #f7fafb;
}


.ai-message {
    max-width: 88%;
    padding: 10px 12px;
    border-radius: 10px;
    margin-bottom: 10px;
    font-size: 12px;
    line-height: 1.5;
}


.ai-message.bot {
    background: white;
    border: 1px solid #e6edef;
    color: #17324d;
}


.ai-message.user {
    margin-left: auto;
    background: #087f8c;
    color: white;
}


/* =========================================================
   AI QUICK QUESTIONS
========================================================= */

.ai-quick {
    padding: 10px 12px;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    border-top: 1px solid #e8eef1;
    background: white;
}


.ai-quick button {
    border: 1px solid #dce7eb;
    background: #f6fafb;
    color: #17324d;
    border-radius: 15px;
    padding: 6px 9px;
    font-size: 10px;
    cursor: pointer;
}


.ai-quick button:hover {
    border-color: #087f8c;
    color: #087f8c;
}


/* =========================================================
   AI INPUT
========================================================= */

.ai-input {
    display: flex;
    padding: 10px;
    border-top: 1px solid #e8eef1;
    background: white;
}


.ai-input input {
    flex: 1;
    border: 1px solid #dce7eb;
    border-radius: 8px;
    padding: 10px;
    outline: none;
    font-size: 12px;
}


.ai-input input:focus {
    border-color: #087f8c;
}


.ai-input button {
    width: 42px;
    margin-left: 7px;
    border: none;
    border-radius: 8px;
    background: #087f8c;
    color: white;
    cursor: pointer;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1100px) {

    .stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .queue-stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .dashboard-grid {
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
        padding: 0 0 20px;
    }

    .logo span:last-child {
        display: none;
    }

    .menu-title {
        display: none;
    }

    .menu a span:not(.menu-icon) {
        display: none;
    }

    .menu a {
        justify-content: center;
    }

    .logout a {
        font-size: 0;
    }

    .logout a::after {
        content: "↪";
        font-size: 18px;
    }

    .main {
        margin-left: 70px;
    }

    .topbar {
        padding: 0 20px;
    }

    .admin-info {
        display: none;
    }

    .content {
        padding: 22px 18px;
    }

    .stats,
    .queue-stats {
        grid-template-columns: 1fr;
    }

    .welcome {
        padding: 20px;
    }

    .actions {
        grid-template-columns: 1fr;
    }

    .ai-chat {
        right: 15px;
        bottom: 85px;
    }

    .ai-button {
        right: 15px;
        bottom: 15px;
    }

}

</style>

</head>


<body>


<!-- =========================================================
     SIDEBAR
========================================================= -->

<aside class="sidebar">

    <a
        href="admin_dashboard.php"
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
            Main Menu
        </div>


        <!-- DASHBOARD -->

        <a
            href="admin_dashboard.php"
            class="active"
        >

            <span class="menu-icon">
                ⌂
            </span>

            <span>
                Dashboard
            </span>

        </a>


        <!-- USERS -->

        <a href="users.php">

            <span class="menu-icon">
                👥
            </span>

            <span>
                Users
            </span>

        </a>


        <!-- DEPARTMENTS -->

        <a href="departments.php">

            <span class="menu-icon">
                🏥
            </span>

            <span>
                Departments
            </span>

        </a>


        <!-- DOCTORS -->

        <a href="doctors.php">

            <span class="menu-icon">
                🩺
            </span>

            <span>
                Doctors
            </span>

        </a>


        <!-- NURSES -->

        <a href="nurses.php">

            <span class="menu-icon">
                🧑‍⚕️
            </span>

            <span>
                Nurses
            </span>

        </a>


        <div
            class="menu-title"
            style="margin-top:25px;"
        >
            Management
        </div>


        <!-- QUEUE -->

        <a href="queue.php">

            <span class="menu-icon">
                🎫
            </span>

            <span>
                Queue
            </span>

        </a>


        <!-- PAYMENTS
             IMPORTANT:
             THIS MUST BE admin_payments.php
        -->

        <a href="admin_payments.php">

            <span class="menu-icon">
                💳
            </span>

            <span>
                Payments
            </span>

        </a>


        <!-- CONSULTATION REPORTS -->

        <a href="admin_consultations.php">

            <span class="menu-icon">
                📋
            </span>

            <span>
                Consultation Reports
            </span>

        </a>


        <!-- GENERAL REPORTS -->

        <a href="reports.php">

            <span class="menu-icon">
                📊
            </span>

            <span>
                Reports
            </span>

        </a>


        <!-- SETTINGS -->

        <a href="#">

            <span class="menu-icon">
                ⚙
            </span>

            <span>
                Settings
            </span>

        </a>

    </div>


    <!-- LOGOUT -->

    <div class="logout">

        <a href="logout.php">
            Logout
        </a>

    </div>

</aside>



<!-- =========================================================
     MAIN
========================================================= -->

<div class="main">


    <!-- TOPBAR -->

    <header class="topbar">

        <div class="page-title">

            <h1>
                Admin Dashboard
            </h1>

            <p>
                SmartHospital Queue Management System
            </p>

        </div>


        <div class="admin-profile">

            <div class="avatar">

                <?= e($first_letter) ?>

            </div>


            <div class="admin-info">

                <strong>
                    <?= e($full_name) ?>
                </strong>

                <span>
                    System Administrator
                </span>

            </div>

        </div>

    </header>



    <!-- =====================================================
         CONTENT
    ====================================================== -->

    <main class="content">


        <!-- WELCOME -->

        <section class="welcome">

            <div>

                <h2>

                    Welcome,
                    <?= e($full_name) ?>
                    👋

                </h2>

                <p>
                    Here's an overview of your
                    SmartHospital system.
                </p>

            </div>


            <div class="welcome-icon">
                🏥
            </div>

        </section>



        <!-- =================================================
             MAIN STATISTICS
        ================================================== -->

        <section class="stats">


            <!-- USERS -->

            <div class="stat-card">

                <div class="stat-icon">
                    👥
                </div>

                <div>

                    <h3>
                        <?= $total_users ?>
                    </h3>

                    <p>
                        Total Users
                    </p>

                </div>

            </div>


            <!-- PATIENTS -->

            <div class="stat-card">

                <div class="stat-icon">
                    🧑‍🦽
                </div>

                <div>

                    <h3>
                        <?= $total_patients ?>
                    </h3>

                    <p>
                        Patients
                    </p>

                </div>

            </div>


            <!-- DOCTORS -->

            <div class="stat-card">

                <div class="stat-icon">
                    🩺
                </div>

                <div>

                    <h3>
                        <?= $total_doctors ?>
                    </h3>

                    <p>
                        Doctors
                    </p>

                </div>

            </div>


            <!-- NURSES -->

            <div class="stat-card">

                <div class="stat-icon">
                    👩‍⚕️
                </div>

                <div>

                    <h3>
                        <?= $total_nurses ?>
                    </h3>

                    <p>
                        Nurses
                    </p>

                </div>

            </div>

        </section>



        <!-- =================================================
             QUEUE STATISTICS
        ================================================== -->

        <section class="queue-stats">


            <!-- TODAY -->

            <div class="queue-stat">

                <h4>
                    Today's Queue
                </h4>

                <strong>
                    <?= $total_queue_today ?>
                </strong>

                <p>
                    Total patients checked in today
                </p>

            </div>


            <!-- WAITING -->

            <div class="queue-stat">

                <h4>
                    Waiting
                </h4>

                <strong>
                    <?= $waiting_patients ?>
                </strong>

                <p>
                    Patients waiting
                </p>

            </div>


            <!-- SERVING -->

            <div class="queue-stat">

                <h4>
                    Serving
                </h4>

                <strong>
                    <?= $serving_patients ?>
                </strong>

                <p>
                    Patients currently being served
                </p>

            </div>


            <!-- COMPLETED -->

            <div class="queue-stat">

                <h4>
                    Completed
                </h4>

                <strong>
                    <?= $completed_today ?>
                </strong>

                <p>
                    Patients completed today
                </p>

            </div>

        </section>



        <!-- =================================================
             DASHBOARD GRID
        ================================================== -->

        <section class="dashboard-grid">


            <!-- =================================================
                 QUICK ACTIONS
            ================================================== -->

            <div class="card">


                <div class="card-header">

                    <h3>
                        Quick Actions
                    </h3>

                    <span>
                        Administration
                    </span>

                </div>


                <div class="actions">


                    <!-- USERS -->

                    <a
                        href="users.php"
                        class="action"
                    >

                        <div class="action-icon">
                            👥
                        </div>

                        <strong>
                            Manage Users
                        </strong>

                        <span>
                            View and manage system accounts
                        </span>

                    </a>


                    <!-- DEPARTMENTS -->

                    <a
                        href="departments.php"
                        class="action"
                    >

                        <div class="action-icon">
                            🏥
                        </div>

                        <strong>
                            Departments
                        </strong>

                        <span>
                            Manage hospital departments
                        </span>

                    </a>


                    <!-- DOCTORS -->

                    <a
                        href="doctors.php"
                        class="action"
                    >

                        <div class="action-icon">
                            🩺
                        </div>

                        <strong>
                            Doctors
                        </strong>

                        <span>
                            Manage hospital doctors
                        </span>

                    </a>


                    <!-- NURSES -->

                    <a
                        href="nurses.php"
                        class="action"
                    >

                        <div class="action-icon">
                            🧑‍⚕️
                        </div>

                        <strong>
                            Manage Nurses
                        </strong>

                        <span>
                            Manage hospital nurses
                        </span>

                    </a>


                    <!-- QUEUE -->

                    <a
                        href="queue.php"
                        class="action"
                    >

                        <div class="action-icon">
                            🎫
                        </div>

                        <strong>
                            Manage Queue
                        </strong>

                        <span>
                            View and manage patient queue
                        </span>

                    </a>


                    <!-- PAYMENTS -->

                    <a
                        href="admin_payments.php"
                        class="action"
                    >

                        <div class="action-icon">
                            💳
                        </div>

                        <strong>
                            Payments
                        </strong>

                        <span>
                            Manage patient payments and transactions
                        </span>

                    </a>


                    <!-- CONSULTATIONS -->

                    <a
                        href="admin_consultations.php"
                        class="action"
                    >

                        <div class="action-icon">
                            📋
                        </div>

                        <strong>
                            Consultation Reports
                        </strong>

                        <span>
                            View doctor consultation records
                        </span>

                    </a>


                    <!-- REPORTS -->

                    <a
                        href="reports.php"
                        class="action"
                    >

                        <div class="action-icon">
                            📊
                        </div>

                        <strong>
                            Reports
                        </strong>

                        <span>
                            View hospital queue reports
                        </span>

                    </a>


                </div>

            </div>



            <!-- =================================================
                 LIVE QUEUE
            ================================================== -->

            <div class="card">


                <div class="card-header">

                    <h3>
                        Today's Queue
                    </h3>

                    <span>
                        Live
                    </span>

                </div>


                <div class="queue-box">


                    <div class="queue-number">

                        <?= $waiting_patients ?>

                    </div>


                    <p>
                        Patients currently waiting
                    </p>


                    <div class="queue-status">

                        <?php if ($serving_patients > 0): ?>

                            <?= $serving_patients ?>

                            patient(s) currently
                            being served.

                        <?php else: ?>

                            No patient is currently
                            being served.

                        <?php endif; ?>

                    </div>


                    <div class="current-patient">

                        <small>
                            CURRENTLY SERVING
                        </small>


                        <strong>

                            <?php if ($current_queue_number !== "—"): ?>

                                Queue
                                <?= e($current_queue_number) ?>

                                —

                                <?= e($current_patient_name) ?>

                            <?php else: ?>

                                No active patient

                            <?php endif; ?>

                        </strong>

                    </div>


                    <a
                        href="queue.php"
                        class="queue-button"
                    >
                        Open Queue
                    </a>


                    <br>


                    <a
                        href="reports.php"
                        class="report-button"
                    >
                        View Reports
                    </a>


                    <a
                        href="admin_consultations.php"
                        class="consultation-link"
                    >
                        📋
                        View Consultation Reports
                    </a>


                </div>

            </div>

        </section>

    </main>

</div>



<!-- =========================================================
     AI BUTTON
========================================================= -->

<button
    class="ai-button"
    id="aiButton"
    title="SmartHospital AI Help"
>
    🤖
</button>



<!-- =========================================================
     AI CHAT
========================================================= -->

<div
    class="ai-chat"
    id="aiChat"
>


    <!-- AI HEADER -->

    <div class="ai-header">


        <div class="ai-header-left">


            <div class="ai-icon">
                🤖
            </div>


            <div>

                <strong>
                    SmartHospital AI
                </strong>

                <span>
                    Admin Assistant
                </span>

            </div>

        </div>


        <button
            class="ai-close"
            id="aiClose"
        >
            ×
        </button>

    </div>



    <!-- AI MESSAGES -->

    <div
        class="ai-messages"
        id="aiMessages"
    >


        <div class="ai-message bot">

            Hello
            <?= e($full_name) ?>
            👋

            <br><br>

            I'm your SmartHospital
            Admin Assistant.

            <br><br>

            I can help you understand
            the dashboard, queue statistics,
            users, patients, doctors,
            nurses, payments and consultation
            reports.

        </div>

    </div>



    <!-- QUICK QUESTIONS -->

    <div class="ai-quick">


        <button
            onclick="askAI('What is the queue status?')"
        >
            Queue status
        </button>


        <button
            onclick="askAI('How many patients are waiting?')"
        >
            Waiting patients
        </button>


        <button
            onclick="askAI('How many consultations are there?')"
        >
            Consultations
        </button>


        <button
            onclick="askAI('What can I manage?')"
        >
            Admin help
        </button>


        <button
            onclick="askAI('Where are payments?')"
        >
            Payments
        </button>


    </div>



    <!-- AI INPUT -->

    <div class="ai-input">


        <input
            type="text"
            id="aiInput"
            placeholder="Ask SmartHospital AI..."
            autocomplete="off"
        >


        <button
            id="aiSend"
        >
            ➤
        </button>


    </div>

</div>



<script>

/* =========================================================
   AI ELEMENTS
========================================================= */

const aiButton =
    document.getElementById("aiButton");

const aiChat =
    document.getElementById("aiChat");

const aiClose =
    document.getElementById("aiClose");

const aiMessages =
    document.getElementById("aiMessages");

const aiInput =
    document.getElementById("aiInput");

const aiSend =
    document.getElementById("aiSend");


/* =========================================================
   OPEN AI
========================================================= */

aiButton.addEventListener(
    "click",
    function () {

        aiChat.classList.toggle("active");

        if (
            aiChat.classList.contains("active")
        ) {

            aiInput.focus();

        }

    }
);


/* =========================================================
   CLOSE AI
========================================================= */

aiClose.addEventListener(
    "click",
    function () {

        aiChat.classList.remove("active");

    }
);


/* =========================================================
   ADD MESSAGE
========================================================= */

function addMessage(message, type) {

    const div =
        document.createElement("div");

    div.className =
        "ai-message " + type;

    div.textContent =
        message;

    aiMessages.appendChild(div);

    aiMessages.scrollTop =
        aiMessages.scrollHeight;
}


/* =========================================================
   AI RESPONSE
========================================================= */

function getAIResponse(question) {

    const q =
        question.toLowerCase();


    /* PAYMENTS */

    if (
        q.includes("payment") ||
        q.includes("payments") ||
        q.includes("billing") ||
        q.includes("bill")
    ) {

        return "You can manage patient payments and transactions from the Payments section. Click Payments to open the administrator payment page.";

    }


    /* CONSULTATIONS */

    if (
        q.includes("consultation") ||
        q.includes("consultations") ||
        q.includes("medical report")
    ) {

        return "Consultation reports are available to administrators. Open Consultation Reports from the Management menu to review doctor consultation records.";

    }


    /* QUEUE */

    if (
        q.includes("queue") ||
        q.includes("status")
    ) {

        return "Today's queue has <?= $total_queue_today ?> patient(s). <?= $waiting_patients ?> are waiting, <?= $called_patients ?> have been called, <?= $serving_patients ?> are being served, and <?= $completed_today ?> have completed their visit.";

    }


    /* WAITING */

    if (
        q.includes("how many") &&
        q.includes("patient") &&
        q.includes("waiting")
    ) {

        return "There are currently <?= $waiting_patients ?> patient(s) waiting in today's queue.";

    }


    /* SERVING */

    if (
        q.includes("serving")
    ) {

        <?php if ($serving_patients > 0): ?>

            return "There are <?= $serving_patients ?> patient(s) currently being served. The current queue number is <?= e($current_queue_number) ?>.";

        <?php else: ?>

            return "There is currently no patient being served.";

        <?php endif; ?>

    }


    /* COMPLETED */

    if (
        q.includes("completed") ||
        q.includes("finished")
    ) {

        return "<?= $completed_today ?> patient(s) have completed their visit today.";

    }


    /* USERS */

    if (
        q.includes("user") ||
        q.includes("account")
    ) {

        return "SmartHospital currently has <?= $total_users ?> registered user account(s). You can manage them from the Users section.";

    }


    /* PATIENTS */

    if (
        q.includes("patient")
    ) {

        return "There are <?= $total_patients ?> patient account(s) in the system. You can manage patient-related accounts from the Users section.";

    }


    /* DOCTORS */

    if (
        q.includes("doctor")
    ) {

        return "SmartHospital currently has <?= $total_doctors ?> doctor account(s). You can manage doctors from the Doctors section.";

    }


    /* NURSES */

    if (
        q.includes("nurse")
    ) {

        return "SmartHospital currently has <?= $total_nurses ?> nurse record(s). You can manage nurses from the Nurses section.";

    }


    /* MANAGEMENT */

    if (
        q.includes("manage") ||
        q.includes("admin") ||
        q.includes("help")
    ) {

        return "As an administrator, you can manage users, departments, doctors, nurses, the patient queue, payments, consultation reports and hospital reports.";

    }


    /* URGENT */

    if (
        q.includes("urgent")
    ) {

        return "There are <?= $urgent_patients ?> urgent case(s) recorded in today's queue.";

    }


    /* EMERGENCY */

    if (
        q.includes("emergency")
    ) {

        return "There are <?= $emergency_patients ?> emergency case(s) recorded in today's queue.";

    }


    /* REPORTS */

    if (
        q.includes("report")
    ) {

        return "You can open the Reports section for hospital queue reports, or Consultation Reports to review doctor consultation records.";

    }


    /* GREETING */

    if (
        q.includes("hello") ||
        q.includes("hi") ||
        q.includes("hey")
    ) {

        return "Hello! 👋 I'm ready to help you manage SmartHospital.";

    }


    /* DEFAULT */

    return "I can help with queue status, waiting patients, serving patients, completed patients, users, doctors, nurses, payments, consultation reports, urgent cases, emergency cases, reports and general administration.";

}


/* =========================================================
   ASK AI
========================================================= */

function askAI(question) {

    if (!question.trim()) {
        return;
    }


    addMessage(
        question,
        "user"
    );


    setTimeout(
        function () {

            const response =
                getAIResponse(question);

            addMessage(
                response,
                "bot"
            );

        },
        350
    );

}


/* =========================================================
   SEND BUTTON
========================================================= */

aiSend.addEventListener(
    "click",
    function () {

        const question =
            aiInput.value.trim();

        if (!question) {
            return;
        }

        askAI(question);

        aiInput.value = "";

    }
);


/* =========================================================
   ENTER KEY
========================================================= */

aiInput.addEventListener(
    "keydown",
    function (event) {

        if (event.key === "Enter") {

            event.preventDefault();

            aiSend.click();

        }

    }
);

</script>


</body>

</html>