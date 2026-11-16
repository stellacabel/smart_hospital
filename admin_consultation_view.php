<?php

session_start();

require_once "db.php";

/* =========================================================
   ADMIN ACCESS CHECK
========================================================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}


/* =========================================================
   HELPER
========================================================= */

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}


/* =========================================================
   GET CONSULTATION ID
========================================================= */

$consultation_id = isset($_GET["id"])
    ? (int)$_GET["id"]
    : 0;

if ($consultation_id <= 0) {
    header("Location: admin_consultations.php");
    exit;
}


/* =========================================================
   GET CONSULTATION
========================================================= */

$sql = "
    SELECT

        c.id,
        c.queue_id,
        c.patient_id,
        c.doctor_id,
        c.department_id,

        c.diagnosis,
        c.treatment,
        c.notes,

        c.status,
        c.started_at,
        c.completed_at,
        c.created_at,

        p.full_name AS patient_name,
        p.patient_number,

        u.full_name AS doctor_name,

        dep.name AS department_name,

        q.queue_number,
        q.priority

    FROM consultations c

    INNER JOIN patients p
        ON c.patient_id = p.id

    INNER JOIN doctors d
        ON c.doctor_id = d.id

    INNER JOIN users u
        ON d.user_id = u.id

    INNER JOIN departments dep
        ON c.department_id = dep.id

    LEFT JOIN queue q
        ON c.queue_id = q.id

    WHERE c.id = ?

    LIMIT 1
";


$stmt = $conn->prepare($sql);

if (!$stmt) {
    die(
        "Database query error: " .
        e($conn->error)
    );
}

$stmt->bind_param(
    "i",
    $consultation_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();

    header("Location: admin_consultations.php");
    exit;
}

$consultation = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   ADMIN INFORMATION
========================================================= */

$full_name =
    $_SESSION["full_name"]
    ?? "Administrator";

$first_letter =
    strtoupper(
        substr(
            trim($full_name),
            0,
            1
        )
    );


/* =========================================================
   HOSPITAL INFORMATION
========================================================= */

$hospital_name =
    "SMART HOSPITAL";

$hospital_subtitle =
    "SmartHospital Healthcare & Medical Services";

$hospital_location =
    "Kigali, Rwanda";

$hospital_phone =
    "+250 783 835 525";

$hospital_email =
    "info@smarthospital.com";


/* =========================================================
   REPORT NUMBER
========================================================= */

$report_number =
    "SH-" .
    str_pad(
        (int)$consultation["id"],
        5,
        "0",
        STR_PAD_LEFT
    );


/* =========================================================
   REPORT DATE
========================================================= */

$report_date = !empty(
    $consultation["created_at"]
)
    ? date(
        "d M Y",
        strtotime(
            $consultation["created_at"]
        )
    )
    : "—";


/* =========================================================
   GENERATED DATE
========================================================= */

$generated_date =
    date("d M Y H:i");

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
    Consultation Report |
    SmartHospital
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

    overflow-y: auto;
}


/* =========================================================
   LOGO
========================================================= */

.logo {

    display: flex;

    align-items: center;

    gap: 10px;

    padding:
        0 10px 30px;

    border-bottom:
        1px solid
        rgba(255,255,255,.10);

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

    font-weight: 800;
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

    transition: .2s;
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

    background:
        rgba(255,255,255,.08);

    color: #dce7ed;

    text-decoration: none;

    font-size: 13px;

    transition: .2s;
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
        1px solid #e8eef1;

    padding:
        0 35px;

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

    padding:
        32px 35px;
}


/* =========================================================
   BACK BUTTON
========================================================= */

.back-button {

    display: inline-block;

    margin-bottom: 20px;

    color: #087f8c;

    font-size: 13px;

    font-weight: 600;

    text-decoration: none;
}


.back-button:hover {

    text-decoration: underline;
}


/* =========================================================
   REPORT HEADER
========================================================= */

.report-header {

    background: white;

    border:
        1px solid #e8eef1;

    border-radius: 14px;

    padding: 25px;

    margin-bottom: 20px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;
}


.report-header h2 {

    font-size: 22px;

    margin-bottom: 6px;
}


.report-header p {

    color: #82919b;

    font-size: 12px;
}


/* =========================================================
   STATUS
========================================================= */

.status {

    display: inline-block;

    padding:
        7px 13px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: 700;

    text-transform: capitalize;
}


.status.completed {

    background: #e8f7ef;

    color: #18794e;
}


.status.draft {

    background: #fff5df;

    color: #9a6700;
}


.status.pending {

    background: #fff5df;

    color: #9a6700;
}


/* =========================================================
   REPORT GRID
========================================================= */

.report-grid {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 20px;

    margin-bottom: 20px;
}


/* =========================================================
   CARD
========================================================= */

.card {

    background: white;

    border:
        1px solid #e8eef1;

    border-radius: 14px;

    padding: 23px;
}


.card h3 {

    font-size: 16px;

    margin-bottom: 20px;

    padding-bottom: 12px;

    border-bottom:
        1px solid #edf1f3;
}


/* =========================================================
   INFORMATION ROW
========================================================= */

.info-row {

    display: grid;

    grid-template-columns:
        145px 1fr;

    gap: 15px;

    padding:
        10px 0;

    border-bottom:
        1px solid #f0f3f5;
}


.info-row:last-child {

    border-bottom: none;
}


.info-label {

    color: #82919b;

    font-size: 11px;

    font-weight: 600;

    text-transform: uppercase;
}


.info-value {

    color: #17324d;

    font-size: 13px;

    font-weight: 500;
}


/* =========================================================
   CONSULTATION DETAILS
========================================================= */

.detail-card {

    background: white;

    border:
        1px solid #e8eef1;

    border-radius: 14px;

    padding: 23px;

    margin-bottom: 20px;
}


.detail-card h3 {

    font-size: 16px;

    margin-bottom: 18px;
}


.detail-box {

    background: #f8fafb;

    border:
        1px solid #edf1f3;

    border-radius: 9px;

    padding: 15px;

    margin-bottom: 15px;
}


.detail-box:last-child {

    margin-bottom: 0;
}


.detail-label {

    color: #087f8c;

    font-size: 11px;

    font-weight: 700;

    text-transform: uppercase;

    margin-bottom: 7px;
}


.detail-text {

    color: #536774;

    font-size: 13px;

    line-height: 1.6;

    white-space: pre-wrap;

    min-height: 20px;
}


/* =========================================================
   PRIORITY
========================================================= */

.priority {

    display: inline-block;

    padding:
        5px 10px;

    border-radius: 15px;

    background: #f1f5f7;

    color: #536774;

    font-size: 10px;

    font-weight: 600;

    text-transform: capitalize;
}


/* =========================================================
   ACTION BUTTONS
========================================================= */

.actions {

    display: flex;

    gap: 10px;

    flex-wrap: wrap;

    margin-bottom: 20px;
}


.btn {

    display: inline-block;

    padding:
        10px 16px;

    border-radius: 7px;

    font-size: 12px;

    font-weight: 600;

    text-decoration: none;

    border: none;

    cursor: pointer;
}


.btn-primary {

    background: #087f8c;

    color: white;
}


.btn-primary:hover {

    background: #066b76;
}


.btn-secondary {

    background: #eef3f5;

    color: #17324d;
}


.btn-secondary:hover {

    background: #dce8ec;
}


/* =========================================================
   PRINT-ONLY ELEMENTS
========================================================= */

.print-hospital-header,
.print-signatures,
.hospital-footer {

    display: none;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 900px) {

    .report-grid {

        grid-template-columns: 1fr;
    }

}


@media (max-width: 700px) {

    .sidebar {

        width: 70px;

        padding:
            20px 10px;
    }


    .logo {

        justify-content: center;

        padding:
            0 0 20px;
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

        padding:
            0 20px;
    }


    .admin-info {

        display: none;
    }


    .content {

        padding:
            22px 18px;
    }


    .report-header {

        flex-direction: column;

        align-items: flex-start;
    }


    .info-row {

        grid-template-columns: 1fr;

        gap: 4px;
    }

}


/* =========================================================
   PROFESSIONAL A4 PRINT VERSION
========================================================= */

@media print {

    @page {

        size: A4;

        margin: 14mm 15mm 16mm 15mm;
    }


    * {

        -webkit-print-color-adjust: exact;

        print-color-adjust: exact;
    }


    html,
    body {

        width: 100%;

        background: white !important;

        color: #17324d;
    }


    body {

        font-family:
            "Segoe UI",
            Arial,
            sans-serif;
    }


    /* =====================================================
       HIDE WEBSITE
    ===================================================== */

    .sidebar,
    .topbar,
    .back-button,
    .actions {

        display: none !important;
    }


    /* =====================================================
       MAIN RESET
    ===================================================== */

    .main {

        margin-left: 0;

        min-height: auto;

        width: 100%;
    }


    .content {

        padding: 0;

        width: 100%;
    }


    /* =====================================================
       HOSPITAL HEADER
    ===================================================== */

    .print-hospital-header {

        display: block;

        text-align: center;

        border-bottom:
            2px solid #17324d;

        padding-bottom: 13px;

        margin-bottom: 18px;
    }


    .print-hospital-name {

        font-size: 25px;

        font-weight: 800;

        letter-spacing: 1.2px;

        color: #17324d;
    }


    .print-hospital-subtitle {

        font-size: 11px;

        color: #536774;

        margin-top: 3px;

        font-weight: 500;
    }


    .print-hospital-contact {

        font-size: 9px;

        color: #536774;

        margin-top: 6px;
    }


    .print-report-title {

        margin-top: 13px;

        font-size: 17px;

        font-weight: 800;

        letter-spacing: .8px;

        text-transform: uppercase;
    }


    .print-report-number {

        font-size: 9px;

        color: #536774;

        margin-top: 4px;
    }


    /* =====================================================
       REPORT HEADER
    ===================================================== */

    .report-header {

        border: none;

        padding: 0;

        margin-bottom: 12px;

        background: white;

        min-height: 22px;
    }


    .report-header h2,
    .report-header p {

        display: none;
    }


    .status {

        border:
            1px solid #17324d;

        background: white !important;

        color: #17324d !important;

        border-radius: 3px;

        padding:
            4px 9px;

        font-size: 8px;
    }


    /* =====================================================
       INFORMATION GRID
    ===================================================== */

    .report-grid {

        display: grid;

        grid-template-columns:
            1fr 1fr;

        gap: 12px;

        margin-bottom: 13px;
    }


    .card,
    .detail-card {

        background: white;

        border:
            1px solid #cfd9de;

        border-radius: 0;

        box-shadow: none;

        padding: 12px;

        break-inside: avoid;
    }


    .card h3,
    .detail-card h3 {

        font-size: 10px;

        text-transform: uppercase;

        letter-spacing: .4px;

        border-bottom:
            1px solid #cfd9de;

        padding-bottom: 7px;

        margin-bottom: 7px;

        color: #17324d;
    }


    .info-row {

        padding: 5px 0;

        grid-template-columns:
            105px 1fr;

        gap: 8px;
    }


    .info-label {

        font-size: 7.5px;
    }


    .info-value {

        font-size: 9px;
    }


    /* =====================================================
       MEDICAL DETAILS
    ===================================================== */

    .detail-card {

        margin-bottom: 13px;
    }


    .detail-box {

        background: white;

        border: none;

        border-bottom:
            1px solid #e0e6e9;

        border-radius: 0;

        padding:
            8px 0;

        margin-bottom: 3px;

        break-inside: avoid;
    }


    .detail-box:last-child {

        border-bottom: none;
    }


    .detail-label {

        color: #17324d;

        font-size: 8px;

        margin-bottom: 4px;
    }


    .detail-text {

        color: #273f50;

        font-size: 9px;

        line-height: 1.45;

        min-height: 15px;
    }


    /* =====================================================
       PRIORITY
    ===================================================== */

    .priority {

        border:
            1px solid #cfd9de;

        background: white;

        border-radius: 3px;

        padding:
            3px 6px;

        font-size: 7px;
    }


    /* =====================================================
       SIGNATURE AREA
    ===================================================== */

    .print-signatures {

        display: flex;

        justify-content: space-between;

        align-items: flex-start;

        gap: 50px;

        margin-top: 38px;

        page-break-inside: avoid;
    }


    .signature-box {

        width: 45%;

        text-align: center;
    }


    .signature-space {

        height: 35px;

        position: relative;
    }


    .signature-line {

        position: absolute;

        left: 0;

        right: 0;

        bottom: 0;

        border-bottom:
            1px solid #17324d;
    }


    .signature-label {

        font-size: 8px;

        font-weight: 700;

        margin-top: 7px;
    }


    .signature-name {

        font-size: 8px;

        color: #536774;

        margin-top: 3px;
    }


    /* =====================================================
       HOSPITAL STAMP
    ===================================================== */

    .stamp {

        width: 62px;

        height: 62px;

        border:
            1.5px dashed #607683;

        border-radius: 50%;

        margin: 0 auto 7px;

        display: flex;

        align-items: center;

        justify-content: center;

        text-align: center;

        color: #607683;

        font-size: 7px;

        line-height: 1.2;

        font-weight: 700;

        text-transform: uppercase;
    }


    /* =====================================================
       FOOTER
    ===================================================== */

    .hospital-footer {

        display: block;

        text-align: center;

        border-top:
            1px solid #cfd9de;

        margin-top: 27px;

        padding-top: 8px;

        font-size: 7.5px;

        line-height: 1.45;

        color: #71818c;

        page-break-inside: avoid;
    }


    .footer-title {

        font-weight: 700;

        color: #536774;

        margin-bottom: 2px;
    }

}


/* =========================================================
   PRINT SIGNATURE HTML DEFAULT
========================================================= */

.signature-space {

    position: relative;
}


/* =========================================================
   PRINT STAMP DEFAULT
========================================================= */

.stamp {

    display: none;
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


        <a href="admin_dashboard.php">

            <span class="menu-icon">
                ⌂
            </span>

            <span>
                Dashboard
            </span>

        </a>


        <a href="users.php">

            <span class="menu-icon">
                👥
            </span>

            <span>
                Users
            </span>

        </a>


        <a href="departments.php">

            <span class="menu-icon">
                🏥
            </span>

            <span>
                Departments
            </span>

        </a>


        <a href="doctors.php">

            <span class="menu-icon">
                🩺
            </span>

            <span>
                Doctors
            </span>

        </a>


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


        <a href="queue.php">

            <span class="menu-icon">
                🎫
            </span>

            <span>
                Queue
            </span>

        </a>


        <a
            href="admin_consultations.php"
            class="active"
        >

            <span class="menu-icon">
                📋
            </span>

            <span>
                Consultation Reports
            </span>

        </a>


        <a href="reports.php">

            <span class="menu-icon">
                📊
            </span>

            <span>
                Reports
            </span>

        </a>


        <a href="#">

            <span class="menu-icon">
                ⚙
            </span>

            <span>
                Settings
            </span>

        </a>


    </div>


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


<!-- =========================================================
     TOPBAR
========================================================= -->

<header class="topbar">

    <div class="page-title">

        <h1>
            Consultation Report
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


<!-- =========================================================
     CONTENT
========================================================= -->

<main class="content">


<!-- =========================================================
     PRINT-ONLY HOSPITAL HEADER
========================================================= -->

<div class="print-hospital-header">


    <div class="print-hospital-name">

        <?= e($hospital_name) ?>

    </div>


    <div class="print-hospital-subtitle">

        <?= e($hospital_subtitle) ?>

    </div>


    <div class="print-hospital-contact">

        <?= e($hospital_location) ?>

        &nbsp; | &nbsp;

        Tel:
        <?= e($hospital_phone) ?>

        &nbsp; | &nbsp;

        Email:
        <?= e($hospital_email) ?>

    </div>


    <div class="print-report-title">

        Consultation Report

    </div>


    <div class="print-report-number">

        Report No:
        <?= e($report_number) ?>

        &nbsp; | &nbsp;

        Date:
        <?= e($report_date) ?>

        &nbsp; | &nbsp;

        Generated:
        <?= e($generated_date) ?>

    </div>


</div>


<!-- =========================================================
     BACK
========================================================= -->

<a
    href="admin_consultations.php"
    class="back-button"
>

    ← Back to Consultation Reports

</a>


<!-- =========================================================
     REPORT HEADER
========================================================= -->

<section class="report-header">


    <div>

        <h2>
            Consultation Report
        </h2>


        <p>

            Report #

            <?= (int)$consultation["id"] ?>

            &nbsp; • &nbsp;

            <?= !empty($consultation["created_at"])
                ? date(
                    "d M Y H:i",
                    strtotime(
                        $consultation["created_at"]
                    )
                )
                : "—"
            ?>

        </p>

    </div>


    <div>

        <span
            class="status <?= e(
                $consultation["status"]
            ) ?>"
        >

            <?= ucfirst(
                e(
                    $consultation["status"]
                )
            ) ?>

        </span>

    </div>


</section>


<!-- =========================================================
     PATIENT + CONSULTATION INFORMATION
========================================================= -->

<section class="report-grid">


<!-- =========================================================
     PATIENT INFORMATION
========================================================= -->

<div class="card">


    <h3>
        Patient Information
    </h3>


    <div class="info-row">

        <div class="info-label">
            Full Name
        </div>

        <div class="info-value">

            <?= e(
                $consultation["patient_name"]
            ) ?>

        </div>

    </div>


    <div class="info-row">

        <div class="info-label">
            Patient Number
        </div>

        <div class="info-value">

            <?= e(
                $consultation["patient_number"]
            ) ?>

        </div>

    </div>


    <div class="info-row">

        <div class="info-label">
            Queue Number
        </div>

        <div class="info-value">

            <?php if (
                !empty(
                    $consultation["queue_number"]
                )
            ): ?>

                <?= e(
                    $consultation["queue_number"]
                ) ?>

            <?php else: ?>

                —

            <?php endif; ?>

        </div>

    </div>


    <div class="info-row">

        <div class="info-label">
            Priority
        </div>

        <div class="info-value">

            <?php if (
                !empty(
                    $consultation["priority"]
                )
            ): ?>

                <span class="priority">

                    <?= e(
                        $consultation["priority"]
                    ) ?>

                </span>

            <?php else: ?>

                —

            <?php endif; ?>

        </div>

    </div>


</div>


<!-- =========================================================
     CONSULTATION INFORMATION
========================================================= -->

<div class="card">


    <h3>
        Consultation Information
    </h3>


    <div class="info-row">

        <div class="info-label">
            Doctor
        </div>

        <div class="info-value">

            <?= e(
                $consultation["doctor_name"]
            ) ?>

        </div>

    </div>


    <div class="info-row">

        <div class="info-label">
            Department
        </div>

        <div class="info-value">

            <?= e(
                $consultation["department_name"]
            ) ?>

        </div>

    </div>


    <div class="info-row">

        <div class="info-label">
            Started
        </div>

        <div class="info-value">

            <?= !empty(
                $consultation["started_at"]
            )
                ? date(
                    "d M Y H:i",
                    strtotime(
                        $consultation["started_at"]
                    )
                )
                : "—"
            ?>

        </div>

    </div>


    <div class="info-row">

        <div class="info-label">
            Completed
        </div>

        <div class="info-value">

            <?= !empty(
                $consultation["completed_at"]
            )
                ? date(
                    "d M Y H:i",
                    strtotime(
                        $consultation["completed_at"]
                    )
                )
                : "—"
            ?>

        </div>

    </div>


</div>


</section>


<!-- =========================================================
     MEDICAL DETAILS
========================================================= -->

<section class="detail-card">


    <h3>
        Medical Consultation Details
    </h3>


    <div class="detail-box">


        <div class="detail-label">
            Diagnosis
        </div>


        <div class="detail-text">

            <?= !empty(
                trim(
                    $consultation["diagnosis"] ?? ""
                )
            )
                ? e(
                    $consultation["diagnosis"]
                )
                : "No diagnosis recorded."
            ?>

        </div>


    </div>


    <div class="detail-box">


        <div class="detail-label">
            Treatment
        </div>


        <div class="detail-text">

            <?= !empty(
                trim(
                    $consultation["treatment"] ?? ""
                )
            )
                ? e(
                    $consultation["treatment"]
                )
                : "No treatment recorded."
            ?>

        </div>


    </div>


    <div class="detail-box">


        <div class="detail-label">
            Doctor's Notes
        </div>


        <div class="detail-text">

            <?= !empty(
                trim(
                    $consultation["notes"] ?? ""
                )
            )
                ? e(
                    $consultation["notes"]
                )
                : "No additional notes recorded."
            ?>

        </div>


    </div>


</section>


<!-- =========================================================
     PRINT SIGNATURES
========================================================= -->

<div class="print-signatures">


    <!-- DOCTOR SIGNATURE -->

    <div class="signature-box">

        <div class="signature-space">

            <div class="signature-line"></div>

        </div>


        <div class="signature-label">

            Attending Doctor's Signature

        </div>


        <div class="signature-name">

            <?= e(
                $consultation["doctor_name"]
            ) ?>

        </div>

    </div>


    <!-- HOSPITAL SIGNATURE -->

    <div class="signature-box">


        <div class="stamp">

            SMART<br>
            HOSPITAL<br>
            OFFICIAL<br>
            STAMP

        </div>


        <div class="signature-space">

            <div class="signature-line"></div>

        </div>


        <div class="signature-label">

            Hospital Stamp / Authorized Signature

        </div>


        <div class="signature-name">

            SmartHospital Administration

        </div>


    </div>


</div>


<!-- =========================================================
     PRINT FOOTER
========================================================= -->

<div class="hospital-footer">


    <div class="footer-title">

        <?= e($hospital_name) ?>

        —
        <?= e($hospital_subtitle) ?>

    </div>


    <?= e($hospital_location) ?>

    &nbsp; | &nbsp;

    <?= e($hospital_phone) ?>

    &nbsp; | &nbsp;

    <?= e($hospital_email) ?>


    <br>


    This document is an official medical consultation record.


    <br>


    Please keep this document confidential and secure.


</div>


<!-- =========================================================
     SCREEN ACTIONS
========================================================= -->

<div class="actions">


    <a
        href="admin_consultations.php"
        class="btn btn-secondary"
    >

        ← Back to Reports

    </a>


    <button
        type="button"
        onclick="window.print()"
        class="btn btn-primary"
    >

        🖨 Print Report

    </button>


</div>


</main>


</div>


</body>

</html>