```php
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
   FILTERS
========================================================= */

$selected_date   = trim($_GET["date"] ?? "");
$selected_status = trim($_GET["status"] ?? "");


/* =========================================================
   DATE / STATUS CONDITIONS
========================================================= */

$date_condition   = "";
$status_condition = "";

if ($selected_date !== "") {

    $safe_date = $conn->real_escape_string($selected_date);

    $date_condition =
        " AND DATE(q.check_in_time) = '$safe_date' ";
}

if ($selected_status !== "") {

    $safe_status =
        $conn->real_escape_string($selected_status);

    $status_condition =
        " AND q.status = '$safe_status' ";
}


/* =========================================================
   STATISTICS
========================================================= */

$total_queue       = 0;
$waiting_patients  = 0;
$called_patients   = 0;
$serving_patients  = 0;
$completed_patients = 0;
$urgent_patients   = 0;
$emergency_patients = 0;


/* =========================================================
   TOTAL QUEUE
========================================================= */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM queue q
     WHERE 1=1
     $date_condition
     $status_condition"
);

if ($result) {

    $row = $result->fetch_assoc();

    $total_queue = (int)$row["total"];
}


/* =========================================================
   WAITING
========================================================= */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM queue q
     WHERE q.status = 'waiting'
     " .
    ($selected_date !== ""
        ? " AND DATE(q.check_in_time) = '$safe_date' "
        : "")
);

if ($result) {

    $row = $result->fetch_assoc();

    $waiting_patients = (int)$row["total"];
}


/* =========================================================
   CALLED
========================================================= */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM queue q
     WHERE q.status = 'called'
     " .
    ($selected_date !== ""
        ? " AND DATE(q.check_in_time) = '$safe_date' "
        : "")
);

if ($result) {

    $row = $result->fetch_assoc();

    $called_patients = (int)$row["total"];
}


/* =========================================================
   SERVING
========================================================= */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM queue q
     WHERE q.status = 'serving'
     " .
    ($selected_date !== ""
        ? " AND DATE(q.check_in_time) = '$safe_date' "
        : "")
);

if ($result) {

    $row = $result->fetch_assoc();

    $serving_patients = (int)$row["total"];
}


/* =========================================================
   COMPLETED
========================================================= */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM queue q
     WHERE q.status = 'completed'
     " .
    ($selected_date !== ""
        ? " AND DATE(q.check_in_time) = '$safe_date' "
        : "")
);

if ($result) {

    $row = $result->fetch_assoc();

    $completed_patients = (int)$row["total"];
}


/* =========================================================
   URGENT
========================================================= */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM queue q
     WHERE q.priority = 'urgent'
     " .
    ($selected_date !== ""
        ? " AND DATE(q.check_in_time) = '$safe_date' "
        : "")
);

if ($result) {

    $row = $result->fetch_assoc();

    $urgent_patients = (int)$row["total"];
}


/* =========================================================
   EMERGENCY
========================================================= */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM queue q
     WHERE q.priority = 'emergency'
     " .
    ($selected_date !== ""
        ? " AND DATE(q.check_in_time) = '$safe_date' "
        : "")
);

if ($result) {

    $row = $result->fetch_assoc();

    $emergency_patients = (int)$row["total"];
}


/* =========================================================
   QUEUE REPORT
========================================================= */

$report_sql = "

    SELECT

        q.id,
        q.patient_id,
        q.department_id,
        q.nurse_id,
        q.queue_number,
        q.status,
        q.priority,
        q.check_in_time,
        q.called_time,
        q.completed_time,
        q.notes,

        p.patient_number,
        p.full_name AS patient_name,

        d.name AS department_name,

        n.id AS nurse_profile_id,

        u.full_name AS nurse_name

    FROM queue q

    INNER JOIN patients p
        ON q.patient_id = p.id

    INNER JOIN departments d
        ON q.department_id = d.id

    LEFT JOIN nurses n
        ON q.nurse_id = n.id

    LEFT JOIN users u
        ON n.user_id = u.id

    WHERE 1=1

    $date_condition

    $status_condition

    ORDER BY q.check_in_time DESC
";


$reports = $conn->query($report_sql);


/* =========================================================
   QUERY CHECK
========================================================= */

if (!$reports) {

    die(
        "Reports query failed: " .
        htmlspecialchars($conn->error)
    );
}


/* =========================================================
   TODAY
========================================================= */

$today = date("Y-m-d");


/* =========================================================
   AVATAR
========================================================= */

$avatar_letter =
    strtoupper(
        substr(
            trim($full_name),
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
    Reports | SmartHospital
</title>


<style>

/* =========================================================
   GLOBAL RESET
========================================================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {

    font-family:
        "Segoe UI",
        Arial,
        Helvetica,
        sans-serif;

    background: #f4f7f9;

    color: #17324d;

    min-height: 100vh;
}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    position: fixed;

    left: 0;
    top: 0;

    width: 250px;

    height: 100vh;

    background:
        linear-gradient(
            180deg,
            #102d44 0%,
            #0c263a 100%
        );

    color: white;

    padding: 24px 17px;

    z-index: 1000;

    overflow-y: auto;

    box-shadow:
        5px 0 25px
        rgba(16,45,68,.08);
}


/* =========================================================
   LOGO
========================================================= */

.logo {

    display: flex;

    align-items: center;

    gap: 11px;

    padding:
        3px 10px 25px;

    border-bottom:
        1px solid
        rgba(255,255,255,.10);

    color: white;

    text-decoration: none;

    font-size: 20px;

    font-weight: 700;

    letter-spacing: -.3px;
}

.logo-icon {

    width: 40px;
    height: 40px;

    flex-shrink: 0;

    background:
        linear-gradient(
            135deg,
            #0796a3,
            #08737f
        );

    border-radius: 11px;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 23px;

    font-weight: 800;

    box-shadow:
        0 5px 15px
        rgba(8,127,140,.25);
}


/* =========================================================
   MENU
========================================================= */

.menu {
    margin-top: 27px;
}

.menu-title {

    color: #7891a2;

    font-size: 10px;

    text-transform: uppercase;

    letter-spacing: 1.4px;

    font-weight: 700;

    padding:
        0 13px;

    margin-bottom: 10px;
}

.menu a {

    display: flex;

    align-items: center;

    gap: 12px;

    color: #d7e2e8;

    text-decoration: none;

    padding:
        12px 13px;

    margin-bottom: 4px;

    border-radius: 9px;

    font-size: 13px;

    transition:
        background .2s ease,
        color .2s ease,
        transform .2s ease;
}

.menu a:hover {

    background:
        rgba(255,255,255,.07);

    color: white;

    transform: translateX(2px);
}

.menu a.active {

    background:
        linear-gradient(
            135deg,
            #087f8c,
            #066b76
        );

    color: white;

    box-shadow:
        0 5px 15px
        rgba(8,127,140,.18);
}

.menu-icon {

    width: 22px;

    min-width: 22px;

    text-align: center;

    font-size: 16px;
}


/* =========================================================
   LOGOUT
========================================================= */

.logout {

    position: absolute;

    bottom: 20px;

    left: 17px;

    right: 17px;
}

.logout a {

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 11px;

    border-radius: 9px;

    background:
        rgba(255,255,255,.06);

    color: #dce7ed;

    text-decoration: none;

    font-size: 12px;

    font-weight: 600;

    border:
        1px solid
        rgba(255,255,255,.05);

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

    margin-left: 250px;

    min-height: 100vh;
}


/* =========================================================
   TOPBAR
========================================================= */

.topbar {

    height: 78px;

    background: rgba(255,255,255,.96);

    border-bottom:
        1px solid #e6edf0;

    padding:
        0 38px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    position: sticky;

    top: 0;

    z-index: 500;

    box-shadow:
        0 2px 12px
        rgba(23,50,77,.025);
}

.page-title h1 {

    font-size: 20px;

    font-weight: 700;

    color: #17324d;

    letter-spacing: -.3px;
}

.page-title p {

    margin-top: 4px;

    color: #8797a2;

    font-size: 11px;
}

.admin-profile {

    display: flex;

    align-items: center;

    gap: 11px;
}

.avatar {

    width: 40px;
    height: 40px;

    border-radius: 50%;

    background:
        linear-gradient(
            135deg,
            #e7f8f9,
            #d8f1f3
        );

    color: #087f8c;

    display: flex;

    align-items: center;

    justify-content: center;

    font-weight: 800;

    font-size: 14px;

    border:
        1px solid
        #cdecef;
}

.admin-info {
    line-height: 1.35;
}

.admin-info strong {

    display: block;

    font-size: 12px;

    font-weight: 700;
}

.admin-info span {

    color: #8a99a4;

    font-size: 10px;
}


/* =========================================================
   CONTENT
========================================================= */

.content {

    padding:
        32px 38px 45px;

    max-width: 1700px;
}


/* =========================================================
   PAGE HEADING
========================================================= */

.page-heading {

    display: flex;

    align-items: flex-end;

    justify-content: space-between;

    margin-bottom: 24px;
}

.page-heading h2 {

    font-size: 24px;

    font-weight: 700;

    color: #17324d;

    letter-spacing: -.5px;
}

.page-heading p {

    margin-top: 6px;

    color: #7f909b;

    font-size: 12px;
}


/* =========================================================
   FILTER CARD
========================================================= */

.filter-card {

    background: white;

    border:
        1px solid #e5ecef;

    border-radius: 14px;

    padding: 21px;

    margin-bottom: 24px;

    box-shadow:
        0 5px 20px
        rgba(23,50,77,.035);
}

.filter-form {

    display: grid;

    grid-template-columns:
        minmax(180px,1fr)
        minmax(180px,1fr)
        auto
        auto;

    gap: 14px;

    align-items: end;
}

.filter-group label {

    display: block;

    font-size: 10px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: .4px;

    margin-bottom: 7px;

    color: #60727e;
}

.filter-group input,
.filter-group select {

    width: 100%;

    height: 42px;

    padding:
        0 12px;

    border:
        1px solid #dbe5e9;

    border-radius: 8px;

    outline: none;

    font-family: inherit;

    font-size: 12px;

    color: #30495b;

    background: #fbfcfd;

    transition: .2s;
}

.filter-group input:hover,
.filter-group select:hover {

    border-color: #b9cbd2;
}

.filter-group input:focus,
.filter-group select:focus {

    border-color: #087f8c;

    background: white;

    box-shadow:
        0 0 0 3px
        rgba(8,127,140,.08);
}

.filter-btn,
.clear-btn {

    height: 42px;

    padding:
        0 19px;

    border-radius: 8px;

    font-family: inherit;

    font-size: 12px;

    font-weight: 700;

    cursor: pointer;

    text-decoration: none;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    transition: .2s;
}

.filter-btn {

    border: none;

    background:
        linear-gradient(
            135deg,
            #087f8c,
            #066f7a
        );

    color: white;

    box-shadow:
        0 5px 12px
        rgba(8,127,140,.16);
}

.filter-btn:hover {

    transform: translateY(-1px);

    box-shadow:
        0 7px 16px
        rgba(8,127,140,.22);
}

.clear-btn {

    background: #f0f4f6;

    color: #4f6472;

    border:
        1px solid #e1e9ec;
}

.clear-btn:hover {

    background: #e5ecef;

    color: #17324d;
}


/* =========================================================
   MAIN STATISTICS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 17px;

    margin-bottom: 18px;
}

.stat-card {

    position: relative;

    overflow: hidden;

    background: white;

    border:
        1px solid #e5ecef;

    border-radius: 14px;

    padding:
        21px;

    display: flex;

    align-items: center;

    gap: 15px;

    box-shadow:
        0 5px 20px
        rgba(23,50,77,.035);

    transition:
        transform .2s ease,
        box-shadow .2s ease;
}

.stat-card:hover {

    transform: translateY(-2px);

    box-shadow:
        0 10px 25px
        rgba(23,50,77,.07);
}

.stat-card::after {

    content: "";

    position: absolute;

    right: -20px;

    top: -20px;

    width: 75px;

    height: 75px;

    border-radius: 50%;

    background:
        rgba(8,127,140,.035);
}

.stat-icon {

    width: 47px;
    height: 47px;

    flex-shrink: 0;

    border-radius: 11px;

    background: #e9f7f8;

    color: #087f8c;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 19px;

    position: relative;

    z-index: 1;
}

.stat-card:nth-child(2) .stat-icon {

    background: #fff5df;

    color: #a66a00;
}

.stat-card:nth-child(3) .stat-icon {

    background: #eaf1ff;

    color: #315c9b;
}

.stat-card:nth-child(4) .stat-icon {

    background: #e7f7ee;

    color: #16834d;
}

.stat-card h3 {

    font-size: 25px;

    line-height: 1;

    margin-bottom: 5px;

    font-weight: 750;

    color: #17324d;
}

.stat-card p {

    color: #8796a1;

    font-size: 11px;

    font-weight: 500;
}


/* =========================================================
   SECONDARY STATISTICS
========================================================= */

.secondary-stats {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 17px;

    margin-bottom: 24px;
}

.secondary-card {

    background: white;

    border:
        1px solid #e5ecef;

    border-radius: 14px;

    padding:
        18px 20px;

    box-shadow:
        0 5px 20px
        rgba(23,50,77,.035);

    position: relative;

    overflow: hidden;
}

.secondary-card::before {

    content: "";

    position: absolute;

    left: 0;

    top: 0;

    bottom: 0;

    width: 3px;

    background: #087f8c;
}

.secondary-card:nth-child(2)::before {

    background: #d99a16;
}

.secondary-card:nth-child(3)::before {

    background: #c53a32;
}

.secondary-card h4 {

    font-size: 11px;

    font-weight: 700;

    color: #536774;

    margin-bottom: 7px;

    text-transform: uppercase;

    letter-spacing: .3px;
}

.secondary-number {

    font-size: 26px;

    font-weight: 750;

    color: #17324d;

    line-height: 1;

    margin-bottom: 5px;
}

.secondary-card p {

    color: #8a99a4;

    font-size: 10px;
}


/* =========================================================
   REPORT CARD
========================================================= */

.card {

    background: white;

    border:
        1px solid #e5ecef;

    border-radius: 14px;

    padding: 23px;

    box-shadow:
        0 5px 20px
        rgba(23,50,77,.035);
}

.card-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom: 18px;

    padding-bottom: 15px;

    border-bottom:
        1px solid #edf2f4;
}

.card-header h3 {

    font-size: 15px;

    font-weight: 700;

    color: #17324d;
}

.card-header span {

    color: #8a99a4;

    font-size: 10px;

    background: #f3f6f7;

    padding:
        6px 10px;

    border-radius: 20px;

    font-weight: 600;
}


/* =========================================================
   TABLE
========================================================= */

.table-wrapper {

    overflow-x: auto;

    border:
        1px solid #edf1f3;

    border-radius: 10px;
}

table {

    width: 100%;

    border-collapse: separate;

    border-spacing: 0;

    min-width: 1050px;
}

thead th {

    background: #f7fafb;

    color: #6f818d;

    font-size: 9px;

    text-transform: uppercase;

    letter-spacing: .7px;

    padding:
        13px 12px;

    text-align: left;

    font-weight: 700;

    border-bottom:
        1px solid #e4ecef;

    white-space: nowrap;
}

thead th:first-child {

    border-top-left-radius: 10px;
}

thead th:last-child {

    border-top-right-radius: 10px;
}

tbody tr {

    transition: background .15s ease;
}

tbody tr:hover {

    background: #f9fcfc;
}

td {

    padding:
        13px 12px;

    border-bottom:
        1px solid #edf2f4;

    font-size: 11px;

    color: #536774;

    vertical-align: middle;
}

tbody tr:last-child td {

    border-bottom: none;
}


/* =========================================================
   PATIENT
========================================================= */

.patient-name {

    font-weight: 700;

    color: #17324d;

    font-size: 11px;

    white-space: nowrap;
}

.patient-number {

    color: #98a5ad;

    font-size: 9px;

    margin-top: 3px;
}


/* =========================================================
   QUEUE NUMBER
========================================================= */

.queue-number {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    min-width: 54px;

    padding:
        6px 9px;

    border-radius: 6px;

    background: #edf8f9;

    color: #087f8c;

    font-size: 10px;

    font-weight: 800;
}


/* =========================================================
   BADGES
========================================================= */

.badge,
.priority {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    min-width: 65px;

    padding:
        5px 9px;

    border-radius: 20px;

    font-size: 9px;

    font-weight: 700;

    text-transform: capitalize;

    white-space: nowrap;
}


/* STATUS */

.badge.waiting {

    background: #fff5df;

    color: #a66a00;
}

.badge.called {

    background: #e8f7f8;

    color: #087f8c;
}

.badge.serving {

    background: #e7f0ff;

    color: #315c9b;
}

.badge.completed {

    background: #e7f7ee;

    color: #16834d;
}

.badge.cancelled {

    background: #fff0ef;

    color: #b42318;
}


/* PRIORITY */

.priority.normal {

    background: #f0f3f5;

    color: #687781;
}

.priority.urgent {

    background: #fff5df;

    color: #a66a00;
}

.priority.emergency {

    background: #fff0ef;

    color: #b42318;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty {

    text-align: center;

    padding:
        55px 20px;

    color: #8796a1;
}

.empty-icon {

    width: 60px;

    height: 60px;

    margin:
        0 auto 13px;

    border-radius: 50%;

    background: #f0f5f6;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 26px;
}

.empty strong {

    color: #536774;

    font-size: 13px;
}

.empty p {

    font-size: 11px;
}


/* =========================================================
   SCROLLBAR
========================================================= */

::-webkit-scrollbar {

    width: 7px;

    height: 7px;
}

::-webkit-scrollbar-track {

    background: #eef2f4;
}

::-webkit-scrollbar-thumb {

    background: #b8c8cf;

    border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {

    background: #087f8c;
}


/* =========================================================
   RESPONSIVE - 1200
========================================================= */

@media (max-width: 1200px) {

    .stats {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .filter-form {

        grid-template-columns:
            1fr
            1fr;
    }

    .secondary-stats {

        grid-template-columns:
            repeat(3, 1fr);
    }
}


/* =========================================================
   RESPONSIVE - 850
========================================================= */

@media (max-width: 850px) {

    .sidebar {

        width: 75px;

        padding:
            20px 10px;
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

    .menu-title {

        display: none;
    }

    .menu a {

        justify-content: center;

        padding:
            12px 8px;
    }

    .menu a span:not(.menu-icon) {

        display: none;
    }

    .logout {

        left: 10px;

        right: 10px;
    }

    .logout a {

        font-size: 0;

        padding: 12px;
    }

    .logout a::after {

        content: "↪";

        font-size: 18px;
    }

    .main {

        margin-left: 75px;
    }

    .topbar {

        padding:
            0 22px;
    }

    .content {

        padding:
            25px 22px 35px;
    }

    .secondary-stats {

        grid-template-columns:
            1fr;
    }
}


/* =========================================================
   RESPONSIVE - 600
========================================================= */

@media (max-width: 600px) {

    .topbar {

        height: 70px;
    }

    .page-title h1 {

        font-size: 17px;
    }

    .page-title p {

        display: none;
    }

    .admin-info {

        display: none;
    }

    .content {

        padding:
            20px 15px 30px;
    }

    .page-heading h2 {

        font-size: 20px;
    }

    .page-heading p {

        font-size: 11px;

        line-height: 1.5;
    }

    .stats {

        grid-template-columns: 1fr;
    }

    .filter-form {

        grid-template-columns: 1fr;
    }

    .filter-card {

        padding: 16px;
    }

    .card {

        padding: 16px;
    }

    .card-header {

        align-items: flex-start;

        gap: 10px;
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
            href="reports.php"
            class="active"
        >

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


    <!-- =====================================================
         TOPBAR
    ===================================================== -->

    <header class="topbar">


        <div class="page-title">

            <h1>
                Reports
            </h1>

            <p>
                SmartHospital Queue Management System
            </p>

        </div>


        <div class="admin-profile">


            <div class="avatar">

                <?= htmlspecialchars(
                    $avatar_letter,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </div>


            <div class="admin-info">

                <strong>

                    <?= htmlspecialchars(
                        $full_name,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>

                </strong>

                <span>
                    System Administrator
                </span>

            </div>


        </div>


    </header>


    <!-- =====================================================
         CONTENT
    ===================================================== -->

    <main class="content">


        <!-- PAGE HEADING -->

        <div class="page-heading">

            <div>

                <h2>
                    Queue Reports
                </h2>

                <p>
                    Monitor hospital queue activity and patient service progress.
                </p>

            </div>

        </div>


        <!-- =================================================
             FILTERS
        ================================================= -->

        <section class="filter-card">


            <form
                method="GET"
                class="filter-form"
            >


                <div class="filter-group">

                    <label>
                        Queue Date
                    </label>

                    <input
                        type="date"
                        name="date"
                        value="<?= htmlspecialchars(
                            $selected_date,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                    >

                </div>


                <div class="filter-group">

                    <label>
                        Queue Status
                    </label>

                    <select name="status">

                        <option value="">
                            All statuses
                        </option>

                        <option
                            value="waiting"
                            <?= $selected_status === "waiting"
                                ? "selected"
                                : "" ?>
                        >
                            Waiting
                        </option>

                        <option
                            value="called"
                            <?= $selected_status === "called"
                                ? "selected"
                                : "" ?>
                        >
                            Called
                        </option>

                        <option
                            value="serving"
                            <?= $selected_status === "serving"
                                ? "selected"
                                : "" ?>
                        >
                            Serving
                        </option>

                        <option
                            value="completed"
                            <?= $selected_status === "completed"
                                ? "selected"
                                : "" ?>
                        >
                            Completed
                        </option>

                    </select>

                </div>


                <button
                    type="submit"
                    class="filter-btn"
                >
                    Apply Filter
                </button>


                <a
                    href="reports.php"
                    class="clear-btn"
                >
                    Clear
                </a>


            </form>


        </section>


        <!-- =================================================
             MAIN STATISTICS
        ================================================= -->

        <section class="stats">


            <div class="stat-card">

                <div class="stat-icon">
                    🎫
                </div>

                <div>

                    <h3>
                        <?= $total_queue ?>
                    </h3>

                    <p>
                        Total Queue
                    </p>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon">
                    ⏳
                </div>

                <div>

                    <h3>
                        <?= $waiting_patients ?>
                    </h3>

                    <p>
                        Waiting
                    </p>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon">
                    🩺
                </div>

                <div>

                    <h3>
                        <?= $serving_patients ?>
                    </h3>

                    <p>
                        Currently Serving
                    </p>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon">
                    ✅
                </div>

                <div>

                    <h3>
                        <?= $completed_patients ?>
                    </h3>

                    <p>
                        Completed
                    </p>

                </div>

            </div>


        </section>


        <!-- =================================================
             SECONDARY STATISTICS
        ================================================= -->

        <section class="secondary-stats">


            <div class="secondary-card">

                <h4>
                    Called Patients
                </h4>

                <div class="secondary-number">

                    <?= $called_patients ?>

                </div>

                <p>
                    Patients who have been called
                </p>

            </div>


            <div class="secondary-card">

                <h4>
                    Urgent Cases
                </h4>

                <div class="secondary-number">

                    <?= $urgent_patients ?>

                </div>

                <p>
                    Patients marked as urgent
                </p>

            </div>


            <div class="secondary-card">

                <h4>
                    Emergency Cases
                </h4>

                <div class="secondary-number">

                    <?= $emergency_patients ?>

                </div>

                <p>
                    Patients requiring emergency priority
                </p>

            </div>


        </section>


        <!-- =================================================
             REPORT TABLE
        ================================================= -->

        <section class="card">


            <div class="card-header">

                <h3>
                    Queue Activity
                </h3>

                <span>

                    <?= $total_queue ?>

                    record<?= $total_queue == 1 ? "" : "s" ?>

                </span>

            </div>


            <div class="table-wrapper">


                <table>


                    <thead>

                        <tr>

                            <th>
                                Patient
                            </th>

                            <th>
                                Queue No.
                            </th>

                            <th>
                                Department
                            </th>

                            <th>
                                Nurse
                            </th>

                            <th>
                                Priority
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Check-in
                            </th>

                            <th>
                                Completed
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if ($reports->num_rows > 0): ?>


                        <?php while (
                            $report =
                            $reports->fetch_assoc()
                        ): ?>


                            <tr>


                                <!-- PATIENT -->

                                <td>

                                    <div class="patient-name">

                                        <?= htmlspecialchars(
                                            $report["patient_name"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </div>

                                    <div class="patient-number">

                                        <?= htmlspecialchars(
                                            $report["patient_number"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </div>

                                </td>


                                <!-- QUEUE -->

                                <td>

                                    <span class="queue-number">

                                        <?= htmlspecialchars(
                                            $report["queue_number"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </span>

                                </td>


                                <!-- DEPARTMENT -->

                                <td>

                                    <?= htmlspecialchars(
                                        $report["department_name"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </td>


                                <!-- NURSE -->

                                <td>

                                    <?= htmlspecialchars(
                                        $report["nurse_name"]
                                            ?? "Not assigned",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </td>


                                <!-- PRIORITY -->

                                <td>

                                    <span
                                        class="priority <?= htmlspecialchars(
                                            $report["priority"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $report["priority"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </span>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <span
                                        class="badge <?= htmlspecialchars(
                                            $report["status"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $report["status"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </span>

                                </td>


                                <!-- CHECK-IN -->

                                <td>

                                    <?= htmlspecialchars(
                                        date(
                                            "d M Y, H:i",
                                            strtotime(
                                                $report["check_in_time"]
                                            )
                                        ),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </td>


                                <!-- COMPLETED -->

                                <td>

                                    <?php if (
                                        !empty(
                                            $report["completed_time"]
                                        )
                                    ): ?>

                                        <?= htmlspecialchars(
                                            date(
                                                "d M Y, H:i",
                                                strtotime(
                                                    $report[
                                                        "completed_time"
                                                    ]
                                                )
                                            ),
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    <?php else: ?>

                                        <span
                                            style="
                                                color:#a2adb3;
                                            "
                                        >
                                            —
                                        </span>

                                    <?php endif; ?>

                                </td>


                            </tr>


                        <?php endwhile; ?>


                    <?php else: ?>


                        <tr>

                            <td colspan="8">

                                <div class="empty">

                                    <div class="empty-icon">
                                        📊
                                    </div>

                                    <strong>
                                        No queue records found
                                    </strong>

                                    <p style="margin-top:6px;">
                                        Try another date or status filter.
                                    </p>

                                </div>

                            </td>

                        </tr>


                    <?php endif; ?>


                    </tbody>


                </table>


            </div>


        </section>


    </main>


</div>


</body>

</html>
```
