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


/* =========================================================
   ADMIN INITIAL
========================================================= */

$first_letter = strtoupper(
    substr(trim($full_name), 0, 1)
);


/* =========================================================
   SEARCH & FILTERS
========================================================= */

$search = trim($_GET["search"] ?? "");

$status = trim($_GET["status"] ?? "");

$department_id = (int)($_GET["department_id"] ?? 0);


/* =========================================================
   DEFAULT STATISTICS
========================================================= */

$total_consultations     = 0;
$completed_consultations = 0;
$draft_consultations     = 0;
$today_consultations     = 0;


/* =========================================================
   CHECK CONSULTATIONS TABLE
========================================================= */

$consultations_table_exists = false;

$table_check = $conn->query("
    SHOW TABLES LIKE 'consultations'
");

if ($table_check && $table_check->num_rows > 0) {

    $consultations_table_exists = true;
}


/* =========================================================
   STATISTICS
========================================================= */

if ($consultations_table_exists) {

    /* TOTAL */

    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM consultations
    ");

    if ($result) {

        $row = $result->fetch_assoc();

        $total_consultations =
            (int)$row["total"];
    }


    /* COMPLETED */

    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM consultations
        WHERE status = 'completed'
    ");

    if ($result) {

        $row = $result->fetch_assoc();

        $completed_consultations =
            (int)$row["total"];
    }


    /* DRAFT */

    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM consultations
        WHERE status = 'draft'
    ");

    if ($result) {

        $row = $result->fetch_assoc();

        $draft_consultations =
            (int)$row["total"];
    }


    /* TODAY */

    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM consultations
        WHERE DATE(created_at) = CURDATE()
    ");

    if ($result) {

        $row = $result->fetch_assoc();

        $today_consultations =
            (int)$row["total"];
    }
}


/* =========================================================
   DEPARTMENTS
========================================================= */

$departments = [];

$department_result = $conn->query("
    SELECT
        id,
        name
    FROM departments
    ORDER BY name ASC
");

if ($department_result) {

    while ($row = $department_result->fetch_assoc()) {

        $departments[] = $row;
    }
}


/* =========================================================
   CONSULTATION RESULTS
========================================================= */

$consultations_result = null;


/* =========================================================
   BUILD QUERY
========================================================= */

if ($consultations_table_exists) {

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

        WHERE 1 = 1

    ";


    $params = [];

    $types = "";


    /* =====================================================
       SEARCH
    ===================================================== */

    if ($search !== "") {

        $sql .= "

            AND (

                p.full_name LIKE ?

                OR p.patient_number LIKE ?

                OR u.full_name LIKE ?

                OR c.diagnosis LIKE ?

            )

        ";

        $search_value =
            "%" . $search . "%";


        $params[] = $search_value;
        $params[] = $search_value;
        $params[] = $search_value;
        $params[] = $search_value;

        $types .= "ssss";
    }


    /* =====================================================
       STATUS
    ===================================================== */

    if (
        $status !== ""
        &&
        in_array(
            $status,
            ["draft", "completed"],
            true
        )
    ) {

        $sql .= "

            AND c.status = ?

        ";

        $params[] = $status;

        $types .= "s";
    }


    /* =====================================================
       DEPARTMENT
    ===================================================== */

    if ($department_id > 0) {

        $sql .= "

            AND c.department_id = ?

        ";

        $params[] = $department_id;

        $types .= "i";
    }


    /* =====================================================
       ORDER
    ===================================================== */

    $sql .= "

        ORDER BY c.created_at DESC

    ";


    /* =====================================================
       PREPARE
    ===================================================== */

    $stmt = $conn->prepare($sql);


    if ($stmt) {

        if (!empty($params)) {

            $stmt->bind_param(
                $types,
                ...$params
            );
        }


        $stmt->execute();

        $consultations_result =
            $stmt->get_result();

    }
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

<title>
    Consultation Reports | SmartHospital
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

    padding: 0 10px 30px;

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

    margin-top: 35px;

    padding-bottom: 10px;
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
        1px solid #e8eef1;

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


/* =========================================================
   ADMIN PROFILE
========================================================= */

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
   PAGE HEADER
========================================================= */

.page-header {

    background: white;

    border:
        1px solid #e9eff2;

    border-radius: 14px;

    padding: 24px;

    margin-bottom: 22px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    box-shadow:
        0 5px 18px
        rgba(23,50,77,0.04);
}


.page-header h2 {

    font-size: 20px;

    margin-bottom: 6px;
}


.page-header p {

    color: #8494a0;

    font-size: 12px;
}


/* =========================================================
   FILTERS
========================================================= */

.filters {

    display: flex;

    align-items: center;

    gap: 8px;

    flex-wrap: wrap;
}


.search-input,
.filter-select {

    padding: 10px 12px;

    border:
        1px solid #dce6ea;

    border-radius: 7px;

    background: white;

    outline: none;

    font-family: inherit;

    font-size: 12px;
}


.search-input {

    width: 230px;
}


.filter-select {

    min-width: 140px;
}


.search-input:focus,
.filter-select:focus {

    border-color: #087f8c;
}


.filter-button {

    border: none;

    border-radius: 7px;

    background: #087f8c;

    color: white;

    padding: 10px 15px;

    font-family: inherit;

    font-size: 12px;

    font-weight: 600;

    cursor: pointer;
}


.filter-button:hover {

    background: #066b76;
}


.clear-button {

    color: #7d8c97;

    font-size: 12px;

    padding: 10px;

    text-decoration: none;
}


/* =========================================================
   STATS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 18px;

    margin-bottom: 22px;
}


.stat {

    background: white;

    border:
        1px solid #e9eff2;

    border-radius: 12px;

    padding: 20px;

    box-shadow:
        0 5px 18px
        rgba(23,50,77,0.04);
}


.stat-label {

    color: #7d8c97;

    font-size: 12px;

    margin-bottom: 8px;
}


.stat-value {

    font-size: 27px;

    font-weight: 700;

    color: #17324d;
}


.stat-info {

    color: #087f8c;

    font-size: 11px;

    margin-top: 6px;
}


/* =========================================================
   TABLE CARD
========================================================= */

.table-card {

    background: white;

    border:
        1px solid #e9eff2;

    border-radius: 14px;

    overflow: hidden;

    box-shadow:
        0 5px 18px
        rgba(23,50,77,0.04);
}


.table-header {

    padding: 20px 22px;

    border-bottom:
        1px solid #edf1f3;

    display: flex;

    align-items: center;

    justify-content: space-between;
}


.table-header h3 {

    font-size: 16px;
}


.table-header span {

    color: #7d8c97;

    font-size: 12px;
}


/* =========================================================
   TABLE
========================================================= */

.table-wrapper {

    overflow-x: auto;
}


table {

    width: 100%;

    border-collapse: collapse;

    min-width: 1000px;
}


th {

    background: #f8fafb;

    text-align: left;

    padding: 13px 18px;

    font-size: 10px;

    color: #71818c;

    text-transform: uppercase;

    letter-spacing: .4px;
}


td {

    padding: 15px 18px;

    border-top:
        1px solid #edf1f3;

    font-size: 12px;

    vertical-align: middle;
}


tr:hover td {

    background: #fbfdfe;
}


/* =========================================================
   PATIENT
========================================================= */

.patient-name {

    font-weight: 600;

    color: #17324d;
}


.patient-number {

    color: #8a99a3;

    font-size: 10px;

    margin-top: 3px;
}


/* =========================================================
   DOCTOR
========================================================= */

.doctor-name {

    font-weight: 600;
}


/* =========================================================
   DEPARTMENT
========================================================= */

.department {

    color: #7d8c97;

    font-size: 11px;
}


/* =========================================================
   QUEUE
========================================================= */

.queue-number {

    color: #087f8c;

    font-weight: 700;
}


/* =========================================================
   DIAGNOSIS
========================================================= */

.diagnosis {

    max-width: 220px;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;

    color: #536774;
}


/* =========================================================
   STATUS
========================================================= */

.status {

    display: inline-block;

    padding: 5px 9px;

    border-radius: 20px;

    font-size: 10px;

    font-weight: 600;

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


/* =========================================================
   VIEW BUTTON
========================================================= */

.view-btn {

    display: inline-block;

    background: #087f8c;

    color: white;

    padding: 7px 11px;

    border-radius: 6px;

    font-size: 11px;

    font-weight: 600;

    text-decoration: none;
}


.view-btn:hover {

    background: #066b76;
}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    text-align: center;

    padding: 65px 20px;

    color: #82919b;

    font-size: 13px;
}


.empty-icon {

    font-size: 35px;

    margin-bottom: 10px;
}


.empty-title {

    color: #536774;

    font-size: 17px;

    font-weight: 600;

    margin-bottom: 7px;
}


/* =========================================================
   ERROR NOTICE
========================================================= */

.database-notice {

    background: #fff5df;

    border:
        1px solid #f0d48a;

    color: #7a5700;

    padding: 16px 18px;

    border-radius: 10px;

    margin-bottom: 20px;

    font-size: 12px;

    line-height: 1.6;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1100px) {

    .stats {

        grid-template-columns:
            repeat(2, 1fr);
    }


    .page-header {

        flex-direction: column;

        align-items: flex-start;
    }


    .filters {

        width: 100%;
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


    .stats {

        grid-template-columns: 1fr;
    }


    .filters {

        flex-direction: column;

        align-items: stretch;
    }


    .search-input {

        width: 100%;
    }


    .filter-select {

        width: 100%;
    }
}

</style>

</head>


<body>


<!-- =========================================================
     SIDEBAR
========================================================= -->

<aside class="sidebar">


    <!-- LOGO -->

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


    <!-- MENU -->

    <div class="menu">


        <div class="menu-title">
            Main Menu
        </div>


        <!-- DASHBOARD -->

        <a
            href="admin_dashboard.php"
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


        <!-- CONSULTATION REPORTS -->

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


    <!-- =====================================================
         TOPBAR
    ====================================================== -->

    <header class="topbar">


        <div class="page-title">

            <h1>
                Consultation Reports
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


        <?php if (!$consultations_table_exists): ?>

            <div class="database-notice">

                <strong>
                    Consultation module is not ready yet.
                </strong>

                <br>

                The
                <strong>consultations</strong>
                table does not currently exist in the
                <strong>smart_hospital</strong>
                database.

                Create the consultation table before doctors
                can submit consultation reports.

            </div>

        <?php endif; ?>


        <!-- =================================================
             PAGE HEADER
        ================================================== -->

        <section class="page-header">


            <div>

                <h2>
                    All Consultation Reports
                </h2>

                <p>
                    View consultation records submitted by doctors.
                </p>

            </div>


            <form
                method="GET"
                class="filters"
            >


                <input
                    type="text"
                    name="search"
                    class="search-input"
                    placeholder="Search patient, doctor, diagnosis..."
                    value="<?= e($search) ?>"
                >


                <select
                    name="status"
                    class="filter-select"
                >

                    <option value="">
                        All Status
                    </option>


                    <option
                        value="completed"
                        <?= $status === "completed"
                            ? "selected"
                            : "" ?>
                    >

                        Completed

                    </option>


                    <option
                        value="draft"
                        <?= $status === "draft"
                            ? "selected"
                            : "" ?>
                    >

                        Draft

                    </option>

                </select>


                <select
                    name="department_id"
                    class="filter-select"
                >

                    <option value="">
                        All Departments
                    </option>


                    <?php foreach (
                        $departments
                        as $department
                    ): ?>


                        <option
                            value="<?= (int)$department["id"] ?>"
                            <?= $department_id ===
                                (int)$department["id"]
                                ? "selected"
                                : "" ?>
                        >

                            <?= e(
                                $department["name"]
                            ) ?>

                        </option>


                    <?php endforeach; ?>


                </select>


                <button
                    type="submit"
                    class="filter-button"
                >

                    Search

                </button>


                <?php if (
                    $search !== ""
                    ||
                    $status !== ""
                    ||
                    $department_id > 0
                ): ?>


                    <a
                        href="admin_consultations.php"
                        class="clear-button"
                    >

                        Clear

                    </a>


                <?php endif; ?>


            </form>


        </section>


        <!-- =================================================
             STATISTICS
        ================================================== -->

        <section class="stats">


            <!-- TOTAL -->

            <div class="stat">

                <div class="stat-label">
                    Total Reports
                </div>

                <div class="stat-value">

                    <?= $total_consultations ?>

                </div>

                <div class="stat-info">
                    All consultations
                </div>

            </div>


            <!-- COMPLETED -->

            <div class="stat">

                <div class="stat-label">
                    Completed
                </div>

                <div class="stat-value">

                    <?= $completed_consultations ?>

                </div>

                <div class="stat-info">
                    Completed consultations
                </div>

            </div>


            <!-- DRAFT -->

            <div class="stat">

                <div class="stat-label">
                    Draft
                </div>

                <div class="stat-value">

                    <?= $draft_consultations ?>

                </div>

                <div class="stat-info">
                    Uncompleted records
                </div>

            </div>


            <!-- TODAY -->

            <div class="stat">

                <div class="stat-label">
                    Today
                </div>

                <div class="stat-value">

                    <?= $today_consultations ?>

                </div>

                <div class="stat-info">
                    Today's consultations
                </div>

            </div>


        </section>


        <!-- =================================================
             TABLE
        ================================================== -->

        <section class="table-card">


            <div class="table-header">

                <h3>
                    Consultation Records
                </h3>


                <span>

                    <?=
                        $consultations_result
                        ? $consultations_result->num_rows
                        : 0
                    ?>

                    record(s)

                </span>

            </div>


            <?php if (!$consultations_table_exists): ?>


                <div class="empty">

                    <div class="empty-icon">
                        📋
                    </div>

                    <div class="empty-title">

                        No consultation module yet

                    </div>

                    <p>

                        Consultation reports will appear here
                        after the consultations table is created
                        and doctors begin recording consultations.

                    </p>

                </div>


            <?php elseif (
                $consultations_result
                &&
                $consultations_result->num_rows > 0
            ): ?>


                <div class="table-wrapper">


                    <table>


                        <thead>

                            <tr>

                                <th>
                                    Patient
                                </th>

                                <th>
                                    Doctor
                                </th>

                                <th>
                                    Department
                                </th>

                                <th>
                                    Queue
                                </th>

                                <th>
                                    Diagnosis
                                </th>

                                <th>
                                    Date
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Action
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php while (
                            $consultation =
                            $consultations_result->fetch_assoc()
                        ): ?>


                            <tr>


                                <!-- PATIENT -->

                                <td>

                                    <div class="patient-name">

                                        <?= e(
                                            $consultation[
                                                "patient_name"
                                            ]
                                        ) ?>

                                    </div>


                                    <div class="patient-number">

                                        <?= e(
                                            $consultation[
                                                "patient_number"
                                            ]
                                        ) ?>

                                    </div>

                                </td>


                                <!-- DOCTOR -->

                                <td>

                                    <div class="doctor-name">

                                        <?= e(
                                            $consultation[
                                                "doctor_name"
                                            ]
                                        ) ?>

                                    </div>

                                </td>


                                <!-- DEPARTMENT -->

                                <td>

                                    <div class="department">

                                        <?= e(
                                            $consultation[
                                                "department_name"
                                            ]
                                        ) ?>

                                    </div>

                                </td>


                                <!-- QUEUE -->

                                <td>

                                    <?php if (
                                        !empty(
                                            $consultation[
                                                "queue_number"
                                            ]
                                        )
                                    ): ?>

                                        <span
                                            class="queue-number"
                                        >

                                            <?= e(
                                                $consultation[
                                                    "queue_number"
                                                ]
                                            ) ?>

                                        </span>

                                    <?php else: ?>

                                        —

                                    <?php endif; ?>

                                </td>


                                <!-- DIAGNOSIS -->

                                <td>

                                    <div class="diagnosis">

                                        <?= e(
                                            $consultation[
                                                "diagnosis"
                                            ]
                                        ) ?>

                                    </div>

                                </td>


                                <!-- DATE -->

                                <td>

                                    <?php if (
                                        !empty(
                                            $consultation[
                                                "created_at"
                                            ]
                                        )
                                    ): ?>

                                        <?= date(
                                            "d M Y H:i",
                                            strtotime(
                                                $consultation[
                                                    "created_at"
                                                ]
                                            )
                                        ) ?>

                                    <?php else: ?>

                                        —

                                    <?php endif; ?>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <span
                                        class="status <?= e(
                                            $consultation[
                                                "status"
                                            ]
                                        ) ?>"
                                    >

                                        <?= ucfirst(
                                            e(
                                                $consultation[
                                                    "status"
                                                ]
                                            )
                                        ) ?>

                                    </span>

                                </td>


                                <!-- ACTION -->

                                <td>

                                    <a
                                        href="admin_consultation_view.php?id=<?= (int)$consultation["id"] ?>"
                                        class="view-btn"
                                    >

                                        View Report

                                    </a>

                                </td>


                            </tr>


                        <?php endwhile; ?>


                        </tbody>


                    </table>


                </div>


            <?php else: ?>


                <div class="empty">


                    <div class="empty-icon">
                        📋
                    </div>


                    <div class="empty-title">

                        No consultation reports found

                    </div>


                    <?php if (
                        $search !== ""
                        ||
                        $status !== ""
                        ||
                        $department_id > 0
                    ): ?>

                        <p>

                            Try changing your search
                            or filters.

                        </p>

                    <?php else: ?>

                        <p>

                            No consultations have been
                            recorded yet.

                        </p>

                    <?php endif; ?>


                </div>


            <?php endif; ?>


        </section>


    </main>


</div>


</body>

</html>