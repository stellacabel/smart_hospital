<?php

session_start();
require_once "db.php";

/* =========================================================
   PATIENT SECURITY CHECK
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
   FIND PATIENT
   Match the logged-in user's email with patients.email
========================================================= */

$patient = null;

$user_sql = "
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

$stmt = $conn->prepare($user_sql);

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
   PATIENT ID CHECK
========================================================= */

$patient_id = 0;

if ($patient && !empty($patient["patient_id"])) {
    $patient_id = (int) $patient["patient_id"];
}

if ($patient && !empty($patient["full_name"])) {
    $full_name = $patient["full_name"];
}


/* =========================================================
   CURRENT QUEUE
========================================================= */

$current_queue = null;

if ($patient_id > 0) {

    $queue_sql = "
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

            d.name AS department_name

        FROM queue q

        LEFT JOIN departments d
            ON q.department_id = d.id

        WHERE q.patient_id = ?

        AND q.status IN (
            'waiting',
            'called',
            'serving'
        )

        ORDER BY q.check_in_time DESC

        LIMIT 1
    ";

    $stmt = $conn->prepare($queue_sql);

    if ($stmt) {

        $stmt->bind_param("i", $patient_id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $current_queue = $result->fetch_assoc();
        }

        $stmt->close();
    }
}


/* =========================================================
   QUEUE POSITION
========================================================= */

$queue_position = null;

if ($current_queue && $current_queue["status"] === "waiting") {

    $queue_id = (int) $current_queue["id"];

    $position_sql = "
        SELECT COUNT(*) AS position
        FROM queue q

        WHERE q.department_id = ?
        AND q.status = 'waiting'
        AND q.id <= ?
        AND DATE(q.check_in_time) = DATE(?)
    ";

    $stmt = $conn->prepare($position_sql);

    if ($stmt) {

        $stmt->bind_param(
            "iis",
            $current_queue["department_id"],
            $queue_id,
            $current_queue["check_in_time"]
        );

        $stmt->execute();

        $result = $stmt->get_result();

        if ($result && $row = $result->fetch_assoc()) {
            $queue_position = (int) $row["position"];
        }

        $stmt->close();
    }
}


/* =========================================================
   STATUS LABEL
========================================================= */

$status_label = "No Active Queue";
$status_class = "none";

if ($current_queue) {

    $status_label = ucfirst(
        $current_queue["status"]
    );

    $status_class = $current_queue["status"];
}


/* =========================================================
   PRIORITY LABEL
========================================================= */

$priority = "";

if ($current_queue) {
    $priority = ucfirst(
        $current_queue["priority"]
    );
}


/* =========================================================
   DATE / TIME
========================================================= */

$check_in_display = "";

if ($current_queue && !empty($current_queue["check_in_time"])) {

    $check_in_display = date(
        "d M Y, H:i",
        strtotime(
            $current_queue["check_in_time"]
        )
    );
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
   QUEUE CARD
========================================================= */

.queue-card {

    background: white;

    border:
        1px solid
        #e9eff2;

    border-radius: 15px;

    padding: 30px;

    box-shadow:
        0 5px 18px
        rgba(23,50,77,0.05);

    margin-bottom: 22px;

}


/* =========================================================
   QUEUE HEADER
========================================================= */

.queue-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom: 25px;

}


.queue-header h3 {

    font-size: 17px;

}


.queue-header p {

    color: #8796a1;

    font-size: 12px;

    margin-top: 4px;

}


/* =========================================================
   STATUS BADGE
========================================================= */

.status {

    display: inline-block;

    padding:
        7px
        12px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: 700;

    text-transform: capitalize;

}


.status.waiting {

    background: #fff5df;

    color: #a66a00;

}


.status.called {

    background: #e8f7f8;

    color: #087f8c;

}


.status.serving {

    background: #e7f0ff;

    color: #315c9b;

}


.status.none {

    background: #eef3f5;

    color: #687781;

}


/* =========================================================
   QUEUE NUMBER
========================================================= */

.ticket {

    text-align: center;

    background:
        linear-gradient(
            135deg,
            #f1fbfc,
            #e8f7f8
        );

    border-radius: 14px;

    padding: 35px 20px;

    margin-bottom: 25px;

}


.ticket-label {

    color: #6f838d;

    font-size: 12px;

    margin-bottom: 8px;

}


.ticket-number {

    font-size: 55px;

    font-weight: 800;

    color: #087f8c;

    letter-spacing: 2px;

}


.ticket-subtitle {

    color: #71828e;

    font-size: 12px;

    margin-top: 6px;

}


/* =========================================================
   INFORMATION GRID
========================================================= */

.info-grid {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;

}


.info-box {

    background: #f8fafb;

    border:
        1px solid
        #e9eff2;

    border-radius: 10px;

    padding: 17px;

}


.info-box label {

    display: block;

    color: #8a99a4;

    font-size: 10px;

    text-transform: uppercase;

    letter-spacing: .4px;

    margin-bottom: 6px;

}


.info-box strong {

    display: block;

    font-size: 13px;

    color: #17324d;

}


/* =========================================================
   POSITION
========================================================= */

.position-card {

    background: white;

    border:
        1px solid
        #e9eff2;

    border-radius: 15px;

    padding: 25px;

    box-shadow:
        0 5px 18px
        rgba(23,50,77,0.05);

    margin-bottom: 22px;

}


.position-content {

    display: flex;

    align-items: center;

    gap: 20px;

}


.position-icon {

    width: 52px;
    height: 52px;

    border-radius: 12px;

    background: #e8f7f8;

    color: #087f8c;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 23px;

}


.position-text h3 {

    font-size: 15px;

    margin-bottom: 4px;

}


.position-text p {

    color: #8796a1;

    font-size: 12px;

}


.position-number {

    margin-left: auto;

    font-size: 32px;

    font-weight: 800;

    color: #087f8c;

}


/* =========================================================
   NO QUEUE
========================================================= */

.no-queue {

    background: white;

    border:
        1px solid
        #e9eff2;

    border-radius: 15px;

    padding: 55px 30px;

    text-align: center;

    box-shadow:
        0 5px 18px
        rgba(23,50,77,0.04);

}


.no-queue-icon {

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


.no-queue h3 {

    font-size: 18px;

    margin-bottom: 7px;

}


.no-queue p {

    color: #8796a1;

    font-size: 13px;

    max-width: 450px;

    margin: auto;

}


.dashboard-button {

    display: inline-block;

    margin-top: 20px;

    padding:
        11px
        18px;

    background: #087f8c;

    color: white;

    text-decoration: none;

    border-radius: 8px;

    font-size: 12px;

    font-weight: 600;

}


.dashboard-button:hover {

    background: #066b76;

}


/* =========================================================
   NOTES
========================================================= */

.notes {

    background: #fffaf0;

    border:
        1px solid
        #f1e4c8;

    border-radius: 10px;

    padding: 15px;

    margin-top: 20px;

}


.notes strong {

    font-size: 12px;

}


.notes p {

    color: #796b52;

    font-size: 12px;

    margin-top: 5px;

    line-height: 1.5;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1050px) {

    .info-grid {

        grid-template-columns:
            repeat(2, 1fr);

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


    .queue-card {

        padding:
            20px;

    }


    .queue-header {

        align-items:
            flex-start;

        flex-direction:
            column;

        gap: 12px;

    }


    .info-grid {

        grid-template-columns:
            1fr;

    }


    .position-content {

        align-items:
            flex-start;

    }


    .position-number {

        margin-left:
            auto;

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

        <a
            href="patient_queue.php"
            class="active"
        >

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
                My Queue
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
                My Queue
            </h2>

            <p>
                Track your current position and service status.
            </p>

        </div>


        <?php if ($current_queue): ?>


            <!-- =================================================
                 CURRENT QUEUE
            ================================================= -->

            <section class="queue-card">


                <div class="queue-header">


                    <div>

                        <h3>
                            Current Queue Ticket
                        </h3>

                        <p>
                            Your active hospital queue information
                        </p>

                    </div>


                    <span
                        class="status <?= htmlspecialchars($status_class) ?>"
                    >

                        <?= htmlspecialchars($status_label) ?>

                    </span>


                </div>


                <!-- TICKET -->

                <div class="ticket">


                    <div class="ticket-label">

                        Your Queue Number

                    </div>


                    <div class="ticket-number">

                        <?= htmlspecialchars(
                            $current_queue["queue_number"]
                        ) ?>

                    </div>


                    <div class="ticket-subtitle">

                        Please wait for your number to be called.

                    </div>


                </div>


                <!-- INFORMATION -->

                <div class="info-grid">


                    <!-- DEPARTMENT -->

                    <div class="info-box">

                        <label>
                            Department
                        </label>

                        <strong>

                            <?= htmlspecialchars(
                                $current_queue["department_name"]
                                ?? "Not assigned"
                            ) ?>

                        </strong>

                    </div>


                    <!-- PRIORITY -->

                    <div class="info-box">

                        <label>
                            Priority
                        </label>

                        <strong>

                            <?= htmlspecialchars(
                                $priority
                            ) ?>

                        </strong>

                    </div>


                    <!-- CHECK IN -->

                    <div class="info-box">

                        <label>
                            Check-in Time
                        </label>

                        <strong>

                            <?= htmlspecialchars(
                                $check_in_display
                            ) ?>

                        </strong>

                    </div>


                    <!-- PATIENT NUMBER -->

                    <div class="info-box">

                        <label>
                            Patient Number
                        </label>

                        <strong>

                            <?= htmlspecialchars(
                                $patient["patient_number"]
                                ?? "N/A"
                            ) ?>

                        </strong>

                    </div>


                </div>


                <!-- NOTES -->

                <?php if (!empty($current_queue["notes"])): ?>

                    <div class="notes">

                        <strong>
                            Queue Note
                        </strong>

                        <p>

                            <?= nl2br(
                                htmlspecialchars(
                                    $current_queue["notes"]
                                )
                            ) ?>

                        </p>

                    </div>

                <?php endif; ?>


            </section>


            <!-- =================================================
                 POSITION
            ================================================= -->

            <?php if (
                $current_queue["status"] === "waiting"
            ): ?>


                <section class="position-card">


                    <div class="position-content">


                        <div class="position-icon">
                            ⏳
                        </div>


                        <div class="position-text">

                            <h3>
                                Your Position
                            </h3>

                            <p>
                                Patients ahead of you in this department
                            </p>

                        </div>


                        <div class="position-number">

                            <?php
                            if ($queue_position !== null) {
                                echo $queue_position;
                            } else {
                                echo "—";
                            }
                            ?>

                        </div>


                    </div>


                </section>


            <?php elseif (
                $current_queue["status"] === "called"
            ): ?>


                <section class="position-card">


                    <div class="position-content">


                        <div class="position-icon">
                            🔔
                        </div>


                        <div class="position-text">

                            <h3>
                                Your Number Has Been Called
                            </h3>

                            <p>
                                Please proceed to the assigned service area.
                            </p>

                        </div>


                    </div>


                </section>


            <?php elseif (
                $current_queue["status"] === "serving"
            ): ?>


                <section class="position-card">


                    <div class="position-content">


                        <div class="position-icon">
                            🩺
                        </div>


                        <div class="position-text">

                            <h3>
                                You Are Being Served
                            </h3>

                            <p>
                                Please remain available while your service is in progress.
                            </p>

                        </div>


                    </div>


                </section>


            <?php endif; ?>


        <?php else: ?>


            <!-- =================================================
                 NO ACTIVE QUEUE
            ================================================= -->

            <section class="no-queue">


                <div class="no-queue-icon">
                    🎫
                </div>


                <h3>
                    You Have No Active Queue
                </h3>


                <p>

                    You currently do not have a waiting,
                    called, or serving queue ticket.

                    When you join a hospital queue,
                    your queue number and status will appear here.

                </p>


                <a
                    href="patient_dashboard.php"
                    class="dashboard-button"
                >

                    Back to Dashboard

                </a>


            </section>


        <?php endif; ?>


    </main>


</div>


</body>

</html>