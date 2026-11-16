<?php

session_start();

require_once "db.php";

/* =========================================================
   DOCTOR ACCESS CHECK
========================================================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "doctor") {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];


/* =========================================================
   GET DOCTOR PROFILE
========================================================= */

$stmt = $conn->prepare("
    SELECT
        d.id AS doctor_id,
        d.user_id,
        d.department_id,
        d.specialization,
        d.license_number,
        d.status AS doctor_status,
        u.full_name,
        u.email,
        dep.name AS department_name
    FROM doctors d
    INNER JOIN users u
        ON d.user_id = u.id
    INNER JOIN departments dep
        ON d.department_id = dep.id
    WHERE d.user_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die("Doctor profile not found.");
}

$doctor = $result->fetch_assoc();

$stmt->close();

$doctor_id = (int) $doctor["doctor_id"];
$department_id = (int) $doctor["department_id"];


/* =========================================================
   HANDLE QUEUE ACTIONS
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /* =====================================================
       CALL PATIENT
    ===================================================== */

    if (isset($_POST["call_patient"])) {

        $queue_id = (int) ($_POST["queue_id"] ?? 0);

        if ($queue_id > 0) {

            $stmt = $conn->prepare("
                UPDATE queue
                SET
                    doctor_id = ?,
                    status = 'called',
                    called_time = NOW()
                WHERE id = ?
                AND department_id = ?
                AND status = 'waiting'
            ");

            $stmt->bind_param(
                "iii",
                $doctor_id,
                $queue_id,
                $department_id
            );

            $stmt->execute();
            $stmt->close();
        }

        header("Location: doctor_queue.php");
        exit;
    }


    /* =====================================================
       START SERVING
    ===================================================== */

    if (isset($_POST["start_serving"])) {

        $queue_id = (int) ($_POST["queue_id"] ?? 0);

        if ($queue_id > 0) {

            $stmt = $conn->prepare("
                UPDATE queue
                SET
                    doctor_id = ?,
                    status = 'serving'
                WHERE id = ?
                AND doctor_id = ?
                AND status = 'called'
            ");

            $stmt->bind_param(
                "iii",
                $doctor_id,
                $queue_id,
                $doctor_id
            );

            $stmt->execute();
            $stmt->close();
        }

        header("Location: doctor_queue.php");
        exit;
    }


    /* =====================================================
       COMPLETE PATIENT
    ===================================================== */

    if (isset($_POST["complete_patient"])) {

        $queue_id = (int) ($_POST["queue_id"] ?? 0);

        $notes = trim($_POST["notes"] ?? "");

        if ($queue_id > 0) {

            $stmt = $conn->prepare("
                UPDATE queue
                SET
                    status = 'completed',
                    completed_time = NOW(),
                    notes = ?
                WHERE id = ?
                AND doctor_id = ?
                AND status = 'serving'
            ");

            $stmt->bind_param(
                "sii",
                $notes,
                $queue_id,
                $doctor_id
            );

            $stmt->execute();
            $stmt->close();
        }

        header("Location: doctor_queue.php");
        exit;
    }
}


/* =========================================================
   WAITING PATIENTS
========================================================= */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM queue
    WHERE department_id = ?
    AND status = 'waiting'
");

$stmt->bind_param("i", $department_id);
$stmt->execute();

$waiting_count = (int) $stmt
    ->get_result()
    ->fetch_assoc()["total"];

$stmt->close();


/* =========================================================
   CALLED PATIENTS
========================================================= */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM queue
    WHERE doctor_id = ?
    AND status = 'called'
");

$stmt->bind_param("i", $doctor_id);
$stmt->execute();

$called_count = (int) $stmt
    ->get_result()
    ->fetch_assoc()["total"];

$stmt->close();


/* =========================================================
   CURRENTLY SERVING
========================================================= */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM queue
    WHERE doctor_id = ?
    AND status = 'serving'
");

$stmt->bind_param("i", $doctor_id);
$stmt->execute();

$serving_count = (int) $stmt
    ->get_result()
    ->fetch_assoc()["total"];

$stmt->close();


/* =========================================================
   COMPLETED TODAY
========================================================= */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM queue
    WHERE doctor_id = ?
    AND status = 'completed'
    AND DATE(completed_time) = CURDATE()
");

$stmt->bind_param("i", $doctor_id);
$stmt->execute();

$completed_count = (int) $stmt
    ->get_result()
    ->fetch_assoc()["total"];

$stmt->close();


/* =========================================================
   GET DOCTOR QUEUE
========================================================= */

$stmt = $conn->prepare("
    SELECT
        q.id,
        q.queue_number,
        q.status,
        q.priority,
        q.check_in_time,
        q.called_time,

        p.patient_number,
        p.full_name AS patient_name,
        p.gender,
        p.date_of_birth

    FROM queue q

    INNER JOIN patients p
        ON q.patient_id = p.id

    WHERE
        (
            q.department_id = ?
            AND q.status = 'waiting'
        )

        OR

        (
            q.doctor_id = ?
            AND q.status IN ('called', 'serving')
        )

    ORDER BY

        CASE q.priority
            WHEN 'emergency' THEN 1
            WHEN 'urgent' THEN 2
            ELSE 3
        END,

        q.check_in_time ASC
");

$stmt->bind_param(
    "ii",
    $department_id,
    $doctor_id
);

$stmt->execute();

$queue_result = $stmt->get_result();


/* =========================================================
   HELPER FUNCTION
========================================================= */

function e($value)
{
    return htmlspecialchars(
        (string) $value,
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

<title>
    My Queue | SmartHospital
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

    font-family: "Segoe UI", Arial, sans-serif;

    background: #f5f8fa;

    color: #17324d;
}

a {
    text-decoration: none;
    color: inherit;
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

    overflow-y: auto;
}


/* LOGO */

.logo {

    display: flex;

    align-items: center;

    gap: 10px;

    font-size: 20px;

    font-weight: 700;

    padding: 0 10px 30px;
}


.logo-icon {

    width: 38px;

    height: 38px;

    border-radius: 10px;

    background: #087f8c;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 21px;
}


/* PROFILE */

.profile {

    padding: 18px 10px;

    border-top:
        1px solid rgba(255,255,255,.1);

    border-bottom:
        1px solid rgba(255,255,255,.1);

    margin-bottom: 20px;
}


.profile-name {

    font-weight: 600;

    font-size: 14px;
}


.profile-role {

    color: #9db3c2;

    font-size: 12px;

    margin-top: 4px;
}


/* =========================================================
   NAVIGATION
========================================================= */

.nav {

    display: flex;

    flex-direction: column;

    gap: 7px;
}


.nav a {

    display: block;

    padding: 12px 13px;

    border-radius: 8px;

    color: #cbd9e1;

    font-size: 13px;

    transition: .2s;
}


.nav a:hover {

    background: #087f8c;

    color: white;
}


.nav a.active {

    background: #087f8c;

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

    padding: 0 32px;

    display: flex;

    align-items: center;

    justify-content: space-between;
}


.topbar h1 {

    font-size: 21px;
}


.topbar p {

    color: #81909b;

    font-size: 12px;

    margin-top: 4px;
}


.logout {

    color: #b42318;

    font-size: 13px;

    font-weight: 600;
}


.logout:hover {

    text-decoration: underline;
}


/* =========================================================
   CONTENT
========================================================= */

.content {

    padding: 30px;
}


/* =========================================================
   PAGE HEADER
========================================================= */

.page-header {

    background: white;

    border:
        1px solid #e8eef1;

    border-radius: 14px;

    padding: 24px;

    margin-bottom: 22px;
}


.page-header h2 {

    font-size: 22px;

    margin-bottom: 5px;
}


.page-header p {

    color: #7c8d99;

    font-size: 13px;
}


.department {

    color: #087f8c;

    font-weight: 600;
}


/* =========================================================
   STATISTICS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 16px;

    margin-bottom: 22px;
}


.stat {

    background: white;

    border:
        1px solid #e8eef1;

    border-radius: 12px;

    padding: 20px;
}


.stat-label {

    color: #7d8c97;

    font-size: 12px;

    margin-bottom: 9px;
}


.stat-value {

    font-size: 27px;

    font-weight: 700;
}


.stat-info {

    color: #087f8c;

    font-size: 11px;

    margin-top: 6px;
}


/* =========================================================
   QUEUE CARD
========================================================= */

.queue-card {

    background: white;

    border:
        1px solid #e8eef1;

    border-radius: 14px;

    overflow: hidden;
}


.queue-header {

    padding: 20px 22px;

    border-bottom:
        1px solid #edf1f3;

    display: flex;

    align-items: center;

    justify-content: space-between;
}


.queue-header h3 {

    font-size: 16px;
}


.queue-header span {

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
}


th {

    background: #f8fafb;

    text-align: left;

    padding: 13px 18px;

    font-size: 11px;

    color: #71818c;

    text-transform: uppercase;
}


td {

    padding: 15px 18px;

    border-top:
        1px solid #edf1f3;

    font-size: 12px;
}


.patient-name {

    font-weight: 600;
}


.patient-number {

    color: #8a99a3;

    font-size: 10px;

    margin-top: 3px;
}


/* =========================================================
   BADGES
========================================================= */

.badge {

    display: inline-block;

    padding: 5px 9px;

    border-radius: 20px;

    font-size: 10px;

    font-weight: 600;
}


.waiting {

    background: #fff5df;

    color: #9a6700;
}


.called {

    background: #eaf4ff;

    color: #1769aa;
}


.serving {

    background: #e6f7f0;

    color: #16734b;
}


.emergency {

    background: #ffe9e9;

    color: #b42318;
}


.urgent {

    background: #fff1df;

    color: #b15c00;
}


.normal {

    background: #eef2f5;

    color: #667783;
}


/* =========================================================
   BUTTONS
========================================================= */

.action-form {

    display: inline;
}


.btn {

    border: none;

    border-radius: 6px;

    padding: 7px 10px;

    font-family: inherit;

    font-size: 11px;

    font-weight: 600;

    cursor: pointer;
}


.btn-call {

    background: #087f8c;

    color: white;
}


.btn-serve {

    background: #1769aa;

    color: white;
}


.btn-complete {

    background: #16734b;

    color: white;
}


.btn:hover {

    opacity: .88;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty {

    text-align: center;

    padding: 55px 20px;

    color: #82919b;

    font-size: 13px;
}


.empty-title {

    font-size: 16px;

    color: #536774;

    font-weight: 600;

    margin-bottom: 7px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1000px) {

    .stats {

        grid-template-columns:
            repeat(2, 1fr);
    }

}


@media (max-width: 700px) {

    .sidebar {

        position: relative;

        width: 100%;

        height: auto;
    }


    .main {

        margin-left: 0;
    }


    .nav {

        flex-direction: row;

        overflow-x: auto;
    }


    .topbar {

        padding: 0 18px;
    }


    .content {

        padding: 18px;
    }


    .stats {

        grid-template-columns: 1fr;
    }

}

</style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside class="sidebar">


    <!-- LOGO -->

    <div class="logo">

        <span class="logo-icon">
            +
        </span>

        SmartHospital

    </div>


    <!-- DOCTOR PROFILE -->

    <div class="profile">

        <div class="profile-name">

            <?= e($doctor["full_name"]) ?>

        </div>

        <div class="profile-role">

            Doctor

        </div>

    </div>


    <!-- =================================================
         CORRECT DOCTOR NAVIGATION
    ================================================== -->

    <nav class="nav">

        <a
            href="doctor_dashboard.php"
        >
            Dashboard
        </a>


        <a
            href="doctor_queue.php"
            class="active"
        >
            My Queue
        </a>


        <a
            href="doctor_patients.php"
        >
            Patients
        </a>


        <a
            href="doctor_consultations.php"
        >
            Consultations
        </a>


        <a
            href="doctor_profile.php"
        >
            My Profile
        </a>

    </nav>


</aside>


<!-- =====================================================
     MAIN
===================================================== -->

<main class="main">


    <!-- =================================================
         TOPBAR
    ================================================== -->

    <header class="topbar">

        <div>

            <h1>
                My Queue
            </h1>

            <p>
                SmartHospital Queue Management System
            </p>

        </div>


        <a
            href="logout.php"
            class="logout"
        >
            Logout
        </a>

    </header>


    <!-- =================================================
         CONTENT
    ================================================== -->

    <section class="content">


        <!-- =================================================
             PAGE HEADER
        ================================================== -->

        <div class="page-header">

            <h2>
                My Queue
            </h2>


            <p>

                Department:

                <span class="department">

                    <?= e($doctor["department_name"]) ?>

                </span>

                &nbsp;·&nbsp;

                Doctor:

                <span class="department">

                    <?= e($doctor["full_name"]) ?>

                </span>

            </p>

        </div>


        <!-- =================================================
             STATISTICS
        ================================================== -->

        <div class="stats">


            <!-- WAITING -->

            <div class="stat">

                <div class="stat-label">
                    Waiting Patients
                </div>

                <div class="stat-value">
                    <?= $waiting_count ?>
                </div>

                <div class="stat-info">
                    In your department
                </div>

            </div>


            <!-- CALLED -->

            <div class="stat">

                <div class="stat-label">
                    Called
                </div>

                <div class="stat-value">
                    <?= $called_count ?>
                </div>

                <div class="stat-info">
                    Waiting for consultation
                </div>

            </div>


            <!-- SERVING -->

            <div class="stat">

                <div class="stat-label">
                    Currently Serving
                </div>

                <div class="stat-value">
                    <?= $serving_count ?>
                </div>

                <div class="stat-info">
                    Your active patients
                </div>

            </div>


            <!-- COMPLETED -->

            <div class="stat">

                <div class="stat-label">
                    Completed Today
                </div>

                <div class="stat-value">
                    <?= $completed_count ?>
                </div>

                <div class="stat-info">
                    Consultations completed
                </div>

            </div>


        </div>


        <!-- =================================================
             QUEUE CARD
        ================================================== -->

        <div class="queue-card">


            <div class="queue-header">

                <h3>
                    Department Queue
                </h3>

                <span>

                    <?= e(
                        $doctor["department_name"]
                    ) ?>

                </span>

            </div>


            <?php if ($queue_result->num_rows > 0): ?>


                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Queue
                                </th>

                                <th>
                                    Patient
                                </th>

                                <th>
                                    Priority
                                </th>

                                <th>
                                    Check-in
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
                            $row = $queue_result->fetch_assoc()
                        ): ?>


                            <tr>


                                <!-- QUEUE NUMBER -->

                                <td>

                                    <strong>

                                        <?= e(
                                            $row["queue_number"]
                                        ) ?>

                                    </strong>

                                </td>


                                <!-- PATIENT -->

                                <td>

                                    <div class="patient-name">

                                        <?= e(
                                            $row["patient_name"]
                                        ) ?>

                                    </div>


                                    <div class="patient-number">

                                        <?= e(
                                            $row["patient_number"]
                                        ) ?>

                                    </div>

                                </td>


                                <!-- PRIORITY -->

                                <td>

                                    <span
                                        class="badge <?= e(
                                            $row["priority"]
                                        ) ?>"
                                    >

                                        <?= ucfirst(
                                            e(
                                                $row["priority"]
                                            )
                                        ) ?>

                                    </span>

                                </td>


                                <!-- CHECK-IN -->

                                <td>

                                    <?= date(
                                        "H:i",
                                        strtotime(
                                            $row["check_in_time"]
                                        )
                                    ) ?>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <span
                                        class="badge <?= e(
                                            $row["status"]
                                        ) ?>"
                                    >

                                        <?= ucfirst(
                                            e(
                                                $row["status"]
                                            )
                                        ) ?>

                                    </span>

                                </td>


                                <!-- ACTION -->

                                <td>


                                    <?php if (
                                        $row["status"] === "waiting"
                                    ): ?>


                                        <form
                                            method="POST"
                                            class="action-form"
                                        >

                                            <input
                                                type="hidden"
                                                name="queue_id"
                                                value="<?= (int) $row["id"] ?>"
                                            >


                                            <button
                                                type="submit"
                                                name="call_patient"
                                                class="btn btn-call"
                                            >

                                                Call Patient

                                            </button>

                                        </form>


                                    <?php elseif (
                                        $row["status"] === "called"
                                    ): ?>


                                        <form
                                            method="POST"
                                            class="action-form"
                                        >

                                            <input
                                                type="hidden"
                                                name="queue_id"
                                                value="<?= (int) $row["id"] ?>"
                                            >


                                            <button
                                                type="submit"
                                                name="start_serving"
                                                class="btn btn-serve"
                                            >

                                                Start Serving

                                            </button>

                                        </form>


                                    <?php elseif (
                                        $row["status"] === "serving"
                                    ): ?>


                                        <form
                                            method="POST"
                                            class="action-form"
                                            onsubmit="return confirm('Complete this patient?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="queue_id"
                                                value="<?= (int) $row["id"] ?>"
                                            >


                                            <input
                                                type="hidden"
                                                name="notes"
                                                value=""
                                            >


                                            <button
                                                type="submit"
                                                name="complete_patient"
                                                class="btn btn-complete"
                                            >

                                                Complete

                                            </button>

                                        </form>


                                    <?php endif; ?>


                                </td>


                            </tr>


                        <?php endwhile; ?>


                        </tbody>

                    </table>

                </div>


            <?php else: ?>


                <div class="empty">

                    <div class="empty-title">

                        No patients in your queue

                    </div>

                    There are currently no waiting patients
                    assigned to your department.

                </div>


            <?php endif; ?>


        </div>


    </section>


</main>


</body>

</html>