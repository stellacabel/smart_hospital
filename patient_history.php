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
   FIND LOGGED-IN PATIENT
   users.email = patients.email
========================================================= */

$patient = null;

$patient_sql = "
    SELECT
        u.id AS user_id,
        u.full_name,
        u.email,

        p.id AS patient_id,
        p.patient_number,
        p.phone

    FROM users u

    LEFT JOIN patients p
        ON LOWER(TRIM(u.email)) = LOWER(TRIM(p.email))

    WHERE u.id = ?

    LIMIT 1
";

$stmt = $conn->prepare($patient_sql);

if ($stmt) {

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $patient = $result->fetch_assoc();
    }

    $stmt->close();
}


/* =========================================================
   PATIENT INFORMATION
========================================================= */

$patient_id = 0;

if ($patient && !empty($patient["patient_id"])) {
    $patient_id = (int) $patient["patient_id"];
}

if ($patient && !empty($patient["full_name"])) {
    $full_name = $patient["full_name"];
}


/* =========================================================
   HISTORY STATISTICS
========================================================= */

$total_visits = 0;
$completed_visits = 0;
$cancelled_visits = 0;


/* =========================================================
   TOTAL VISITS
========================================================= */

if ($patient_id > 0) {

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM queue
        WHERE patient_id = ?
        AND status IN ('completed', 'cancelled')
    ");

    if ($stmt) {

        $stmt->bind_param("i", $patient_id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result && $row = $result->fetch_assoc()) {
            $total_visits = (int) $row["total"];
        }

        $stmt->close();
    }
}


/* =========================================================
   COMPLETED VISITS
========================================================= */

if ($patient_id > 0) {

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM queue
        WHERE patient_id = ?
        AND status = 'completed'
    ");

    if ($stmt) {

        $stmt->bind_param("i", $patient_id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result && $row = $result->fetch_assoc()) {
            $completed_visits = (int) $row["total"];
        }

        $stmt->close();
    }
}


/* =========================================================
   CANCELLED VISITS
========================================================= */

if ($patient_id > 0) {

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM queue
        WHERE patient_id = ?
        AND status = 'cancelled'
    ");

    if ($stmt) {

        $stmt->bind_param("i", $patient_id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result && $row = $result->fetch_assoc()) {
            $cancelled_visits = (int) $row["total"];
        }

        $stmt->close();
    }
}


/* =========================================================
   HISTORY RECORDS
========================================================= */

$history = null;

if ($patient_id > 0) {

    $history_sql = "

        SELECT

            q.id,
            q.queue_number,
            q.status,
            q.priority,
            q.check_in_time,
            q.called_time,
            q.completed_time,
            q.notes,

            d.name AS department_name

        FROM queue q

        LEFT JOIN departments d
            ON q.department_id = d.id

        WHERE q.patient_id = ?

        AND q.status IN (
            'completed',
            'cancelled'
        )

        ORDER BY q.check_in_time DESC
    ";

    $stmt = $conn->prepare($history_sql);

    if ($stmt) {

        $stmt->bind_param("i", $patient_id);
        $stmt->execute();

        $history = $stmt->get_result();

        $stmt->close();

    } else {

        die(
            "History query failed: " .
            htmlspecialchars($conn->error)
        );
    }
}


/* =========================================================
   INITIAL
========================================================= */

$initial = strtoupper(
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
    My History | SmartHospital
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


.patient-info {

    line-height: 1.3;
}


.patient-info strong {

    display: block;

    font-size: 13px;
}


.patient-info span {

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

    font-size: 23px;
}


.page-heading p {

    margin-top: 6px;

    color: #8796a1;

    font-size: 13px;
}


/* =========================================================
   STATISTICS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 18px;

    margin-bottom: 25px;
}


.stat-card {

    background: white;

    border:
        1px solid
        #e9eff2;

    border-radius: 12px;

    padding: 20px;

    display: flex;

    align-items: center;

    gap: 15px;

    box-shadow:
        0 5px 18px
        rgba(23,50,77,0.04);
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
   HISTORY CARD
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

    justify-content: space-between;

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
   TABLE
========================================================= */

.table-wrapper {

    overflow-x: auto;
}


table {

    width: 100%;

    border-collapse: collapse;

    min-width: 900px;
}


th {

    background: #f7fafb;

    color: #71828e;

    font-size: 11px;

    text-transform: uppercase;

    letter-spacing: .5px;

    padding: 13px;

    text-align: left;

    border-bottom:
        1px solid
        #e8eef1;
}


td {

    padding: 14px 13px;

    border-bottom:
        1px solid
        #edf1f3;

    font-size: 12px;
}


/* =========================================================
   QUEUE NUMBER
========================================================= */

.queue-number {

    font-weight: 700;

    color: #087f8c;
}


/* =========================================================
   DEPARTMENT
========================================================= */

.department {

    font-weight: 600;

    color: #17324d;
}


/* =========================================================
   STATUS
========================================================= */

.badge {

    display: inline-block;

    padding:
        5px
        9px;

    border-radius: 20px;

    font-size: 10px;

    font-weight: 600;

    text-transform: capitalize;
}


.badge.completed {

    background: #e7f7ee;

    color: #16834d;
}


.badge.cancelled {

    background: #fff0ef;

    color: #b42318;
}


/* =========================================================
   PRIORITY
========================================================= */

.priority {

    display: inline-block;

    padding:
        5px
        9px;

    border-radius: 20px;

    font-size: 10px;

    font-weight: 600;

    text-transform: capitalize;
}


.priority.normal {

    background: #eef3f5;

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
   EMPTY
========================================================= */

.empty {

    text-align: center;

    padding: 55px 20px;

    color: #8796a1;
}


.empty-icon {

    width: 70px;
    height: 70px;

    margin:
        0
        auto
        18px;

    border-radius: 50%;

    background: #e8f7f8;

    color: #087f8c;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 30px;
}


.empty h3 {

    color: #17324d;

    font-size: 18px;

    margin-bottom: 7px;
}


.empty p {

    font-size: 13px;

    max-width: 430px;

    margin: auto;

    line-height: 1.5;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 900px) {

    .stats {

        grid-template-columns:
            1fr;

    }

}


@media (max-width: 700px) {

    .sidebar {

        width: 70px;

        padding:
            20px
            10px;
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

        padding:
            0
            20px;
    }


    .patient-info {

        display: none;
    }


    .content {

        padding:
            22px
            18px;
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

        <a
            href="patient_history.php"
            class="active"
        >

            <span class="menu-icon">
                📋
            </span>

            <span>
                My History
            </span>

        </a>


        <!-- PROFILE -->

        <a href="patient_profile.php">

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


</aside>


<!-- =========================================================
     MAIN
========================================================= -->

<div class="main">


    <!-- TOPBAR -->

    <header class="topbar">


        <div class="page-title">

            <h1>
                My History
            </h1>

            <p>
                SmartHospital Queue Management System
            </p>

        </div>


        <!-- PATIENT PROFILE -->

        <div class="patient-profile">


            <div class="avatar">

                <?= htmlspecialchars($initial) ?>

            </div>


            <div class="patient-info">

                <strong>

                    <?= htmlspecialchars($full_name) ?>

                </strong>

                <span>
                    Patient
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

            <h2>
                My Queue History
            </h2>

            <p>
                View your previous hospital queue visits and service records.
            </p>

        </div>


        <!-- =================================================
             STATISTICS
        ================================================= -->

        <section class="stats">


            <!-- TOTAL -->

            <div class="stat-card">

                <div class="stat-icon">
                    📋
                </div>

                <div>

                    <h3>
                        <?= $total_visits ?>
                    </h3>

                    <p>
                        Total Visits
                    </p>

                </div>

            </div>


            <!-- COMPLETED -->

            <div class="stat-card">

                <div class="stat-icon">
                    ✅
                </div>

                <div>

                    <h3>
                        <?= $completed_visits ?>
                    </h3>

                    <p>
                        Completed Visits
                    </p>

                </div>

            </div>


            <!-- CANCELLED -->

            <div class="stat-card">

                <div class="stat-icon">
                    ✕
                </div>

                <div>

                    <h3>
                        <?= $cancelled_visits ?>
                    </h3>

                    <p>
                        Cancelled Visits
                    </p>

                </div>

            </div>


        </section>


        <!-- =================================================
             HISTORY TABLE
        ================================================= -->

        <section class="card">


            <div class="card-header">

                <h3>
                    Previous Queue Visits
                </h3>

                <span>

                    <?= $total_visits ?>
                    record(s)

                </span>

            </div>


            <div class="table-wrapper">


                <table>


                    <thead>

                        <tr>

                            <th>
                                Queue No.
                            </th>

                            <th>
                                Department
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
                                Called
                            </th>

                            <th>
                                Completed
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (
                        $history &&
                        $history->num_rows > 0
                    ): ?>


                        <?php while (
                            $record = $history->fetch_assoc()
                        ): ?>


                            <tr>


                                <!-- QUEUE NUMBER -->

                                <td>

                                    <span class="queue-number">

                                        <?= htmlspecialchars(
                                            $record["queue_number"]
                                        ) ?>

                                    </span>

                                </td>


                                <!-- DEPARTMENT -->

                                <td>

                                    <span class="department">

                                        <?= htmlspecialchars(
                                            $record["department_name"]
                                            ?? "Not assigned"
                                        ) ?>

                                    </span>

                                </td>


                                <!-- PRIORITY -->

                                <td>

                                    <span
                                        class="priority <?= htmlspecialchars(
                                            $record["priority"]
                                        ) ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $record["priority"]
                                        ) ?>

                                    </span>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <span
                                        class="badge <?= htmlspecialchars(
                                            $record["status"]
                                        ) ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $record["status"]
                                        ) ?>

                                    </span>

                                </td>


                                <!-- CHECK-IN -->

                                <td>

                                    <?php if (
                                        !empty(
                                            $record["check_in_time"]
                                        )
                                    ): ?>

                                        <?= htmlspecialchars(
                                            date(
                                                "d M Y, H:i",
                                                strtotime(
                                                    $record["check_in_time"]
                                                )
                                            )
                                        ) ?>

                                    <?php else: ?>

                                        —

                                    <?php endif; ?>

                                </td>


                                <!-- CALLED -->

                                <td>

                                    <?php if (
                                        !empty(
                                            $record["called_time"]
                                        )
                                    ): ?>

                                        <?= htmlspecialchars(
                                            date(
                                                "d M Y, H:i",
                                                strtotime(
                                                    $record["called_time"]
                                                )
                                            )
                                        ) ?>

                                    <?php else: ?>

                                        —

                                    <?php endif; ?>

                                </td>


                                <!-- COMPLETED -->

                                <td>

                                    <?php if (
                                        !empty(
                                            $record["completed_time"]
                                        )
                                    ): ?>

                                        <?= htmlspecialchars(
                                            date(
                                                "d M Y, H:i",
                                                strtotime(
                                                    $record["completed_time"]
                                                )
                                            )
                                        ) ?>

                                    <?php else: ?>

                                        —

                                    <?php endif; ?>

                                </td>


                            </tr>


                        <?php endwhile; ?>


                    <?php else: ?>


                        <tr>

                            <td colspan="7">


                                <div class="empty">


                                    <div class="empty-icon">
                                        📋
                                    </div>


                                    <h3>
                                        No History Yet
                                    </h3>


                                    <p>

                                        You do not have any completed
                                        or cancelled queue visits yet.
                                        Your previous queue records
                                        will appear here after your
                                        hospital visits.

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