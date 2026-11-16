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

if (
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "patient"
) {

    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];


/* =========================================================
   GET LOGGED-IN USER
========================================================= */

$user_sql = "
    SELECT
        id,
        full_name,
        email,
        role,
        status
    FROM users
    WHERE id = ?
    LIMIT 1
";

$user_stmt = $conn->prepare($user_sql);

if (!$user_stmt) {

    die(
        "User query failed: " .
        htmlspecialchars($conn->error)
    );
}

$user_stmt->bind_param(
    "i",
    $user_id
);

$user_stmt->execute();

$user_result =
    $user_stmt->get_result();

if ($user_result->num_rows === 0) {

    session_destroy();

    header("Location: login.php");

    exit;
}

$user =
    $user_result->fetch_assoc();

$full_name =
    $user["full_name"];

$email =
    $user["email"];


/* =========================================================
   GET PATIENT RECORD
========================================================= */

$patient_sql = "
    SELECT
        id,
        patient_number,
        full_name,
        date_of_birth,
        gender,
        phone,
        email,
        address,
        insurance_provider,
        emergency_contact,
        emergency_phone
    FROM patients
    WHERE email = ?
    LIMIT 1
";

$patient_stmt =
    $conn->prepare($patient_sql);

if (!$patient_stmt) {

    die(
        "Patient query failed: " .
        htmlspecialchars($conn->error)
    );
}

$patient_stmt->bind_param(
    "s",
    $email
);

$patient_stmt->execute();

$patient_result =
    $patient_stmt->get_result();

if ($patient_result->num_rows === 0) {

    die("
        <div style='
            font-family: Arial;
            padding: 40px;
            text-align: center;
        '>

            <h2>Patient profile not found</h2>

            <p>
                Your login account exists, but it is not
                connected to a patient record.
            </p>

            <a href='logout.php'>
                Logout
            </a>

        </div>
    ");
}

$patient =
    $patient_result->fetch_assoc();

$patient_id =
    (int) $patient["id"];


/* =========================================================
   CURRENT QUEUE
========================================================= */

$current_queue = null;

$queue_sql = "
    SELECT
        q.id,
        q.queue_number,
        q.status,
        q.priority,
        q.check_in_time,
        q.called_time,
        q.completed_time,
        q.notes,

        d.name AS department_name,

        u.full_name AS nurse_name

    FROM queue q

    INNER JOIN departments d
        ON q.department_id = d.id

    LEFT JOIN nurses n
        ON q.nurse_id = n.id

    LEFT JOIN users u
        ON n.user_id = u.id

    WHERE q.patient_id = ?

      AND q.status IN (
          'waiting',
          'called',
          'serving'
      )

    ORDER BY q.check_in_time DESC

    LIMIT 1
";

$queue_stmt =
    $conn->prepare($queue_sql);

if ($queue_stmt) {

    $queue_stmt->bind_param(
        "i",
        $patient_id
    );

    $queue_stmt->execute();

    $queue_result =
        $queue_stmt->get_result();

    if (
        $queue_result &&
        $queue_result->num_rows > 0
    ) {

        $current_queue =
            $queue_result->fetch_assoc();
    }
}


/* =========================================================
   QUEUE POSITION
========================================================= */

$queue_position = null;

if (
    $current_queue &&
    $current_queue["status"] === "waiting"
) {

    $priority =
        $current_queue["priority"];

    $check_in_time =
        $current_queue["check_in_time"];


    $position_sql = "

        SELECT COUNT(*) AS position

        FROM queue q

        WHERE q.department_id = (

            SELECT department_id

            FROM queue

            WHERE id = ?

            LIMIT 1

        )

        AND q.status = 'waiting'

        AND (

            CASE

                WHEN q.priority = 'emergency'
                    THEN 1

                WHEN q.priority = 'urgent'
                    THEN 2

                ELSE 3

            END

            <

            CASE

                WHEN ? = 'emergency'
                    THEN 1

                WHEN ? = 'urgent'
                    THEN 2

                ELSE 3

            END

            OR

            (

                CASE

                    WHEN q.priority = 'emergency'
                        THEN 1

                    WHEN q.priority = 'urgent'
                        THEN 2

                    ELSE 3

                END

                =

                CASE

                    WHEN ? = 'emergency'
                        THEN 1

                    WHEN ? = 'urgent'
                        THEN 2

                    ELSE 3

                END

                AND q.check_in_time < ?

            )

        )
    ";


    $position_stmt =
        $conn->prepare(
            $position_sql
        );


    if ($position_stmt) {

        $position_stmt->bind_param(
            "isssss",
            $current_queue["id"],
            $priority,
            $priority,
            $priority,
            $priority,
            $check_in_time
        );

        $position_stmt->execute();

        $position_result =
            $position_stmt->get_result();

        if ($position_result) {

            $position_row =
                $position_result->fetch_assoc();

            $queue_position =
                (int) $position_row["position"] + 1;
        }
    }
}


/* =========================================================
   PATIENT HISTORY COUNT
========================================================= */

$history_count = 0;

$history_sql = "

    SELECT COUNT(*) AS total

    FROM queue

    WHERE patient_id = ?

      AND status = 'completed'
";

$history_stmt =
    $conn->prepare(
        $history_sql
    );

if ($history_stmt) {

    $history_stmt->bind_param(
        "i",
        $patient_id
    );

    $history_stmt->execute();

    $history_result =
        $history_stmt->get_result();

    if ($history_result) {

        $history_row =
            $history_result->fetch_assoc();

        $history_count =
            (int) $history_row["total"];
    }
}


/* =========================================================
   PATIENT INITIAL
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
    Patient Dashboard | SmartHospital
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
   CALLED ALERT
========================================================= */

.called-alert {

    position: fixed;

    top: 25px;
    right: 25px;

    width: 360px;

    background: white;

    border-left:
        6px solid #b42318;

    border-radius: 14px;

    padding: 20px;

    box-shadow:
        0 15px 40px
        rgba(0,0,0,.20);

    z-index: 9999;

    display: none;

    animation:
        alertSlide .4s ease;
}


.called-alert.show {

    display: block;
}


@keyframes alertSlide {

    from {

        transform:
            translateX(120%);

        opacity: 0;
    }

    to {

        transform:
            translateX(0);

        opacity: 1;
    }
}


/* =========================================================
   ALERT HEADER
========================================================= */

.called-alert-header {

    display: flex;

    align-items: center;

    gap: 12px;

    margin-bottom: 10px;
}


.called-alert-icon {

    width: 45px;
    height: 45px;

    background: #fff0ef;

    color: #b42318;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 21px;
}


.called-alert-header h3 {

    color: #b42318;

    font-size: 17px;
}


.called-alert p {

    color: #647481;

    font-size: 13px;

    line-height: 1.6;

    margin-bottom: 15px;
}


/* =========================================================
   ALERT BUTTONS
========================================================= */

.alert-buttons {

    display: flex;

    gap: 10px;
}


.alert-btn {

    border: none;

    cursor: pointer;

    padding: 10px 15px;

    border-radius: 8px;

    font-size: 12px;

    font-weight: 600;
}


.stop-alarm-btn {

    background: #b42318;

    color: white;
}


.close-alert-btn {

    background: #eef3f5;

    color: #17324d;
}


/* =========================================================
   SOUND ENABLE BUTTON
========================================================= */

.sound-enable {

    position: fixed;

    bottom: 25px;
    right: 25px;

    background: #087f8c;

    color: white;

    border: none;

    padding: 12px 18px;

    border-radius: 10px;

    font-size: 12px;

    font-weight: 600;

    cursor: pointer;

    box-shadow:
        0 8px 25px
        rgba(8,127,140,.25);

    z-index: 9998;

    transition: .2s;
}


.sound-enable:hover {

    transform:
        translateY(-2px);

    background: #066b76;
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
        0 10px 30px;

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
        1px solid
        #e8eef1;

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


.profile {

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
   WELCOME
========================================================= */

.welcome {

    background:
        linear-gradient(
            135deg,
            #087f8c,
            #066b76
        );

    color: white;

    border-radius: 14px;

    padding: 27px 30px;

    margin-bottom: 25px;

    display: flex;

    align-items: center;

    justify-content: space-between;
}


.welcome h2 {

    font-size: 22px;

    margin-bottom: 7px;
}


.welcome p {

    font-size: 13px;

    opacity: .85;
}


.welcome-icon {

    font-size: 48px;

    opacity: .25;
}


/* =========================================================
   STATISTICS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

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

    font-size: 22px;

    margin-bottom: 3px;
}


.stat-card p {

    color: #8796a1;

    font-size: 12px;
}


/* =========================================================
   DASHBOARD GRID
========================================================= */

.dashboard-grid {

    display: grid;

    grid-template-columns:
        1.45fr 1fr;

    gap: 20px;
}


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
   CURRENT QUEUE
========================================================= */

.current-queue {

    text-align: center;

    padding: 8px 0 5px;
}


.queue-label {

    color: #8796a1;

    font-size: 12px;

    margin-bottom: 8px;
}


.queue-number {

    font-size: 43px;

    font-weight: 700;

    color: #087f8c;

    margin-bottom: 10px;
}


.department {

    font-size: 14px;

    font-weight: 600;

    margin-bottom: 12px;
}


.queue-details {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 10px;

    margin-top: 20px;
}


.queue-detail {

    background: #f7fafb;

    border-radius: 8px;

    padding: 12px;
}


.queue-detail span {

    display: block;

    color: #8a99a4;

    font-size: 10px;

    margin-bottom: 4px;
}


.queue-detail strong {

    font-size: 12px;
}


/* =========================================================
   STATUS
========================================================= */

.status {

    display: inline-block;

    padding: 6px 12px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: 600;

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


/* =========================================================
   PRIORITY
========================================================= */

.priority {

    display: inline-block;

    padding: 5px 9px;

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
   POSITION
========================================================= */

.position-box {

    margin-top: 18px;

    background: #f4fafb;

    border-radius: 8px;

    padding: 12px;

    text-align: center;
}


.position-box strong {

    color: #087f8c;

    font-size: 18px;
}


.position-box p {

    color: #8796a1;

    font-size: 11px;

    margin-top: 3px;
}


/* =========================================================
   NO QUEUE
========================================================= */

.no-queue {

    text-align: center;

    padding: 35px 15px;

    color: #8796a1;
}


.no-queue-icon {

    font-size: 38px;

    margin-bottom: 10px;
}


.no-queue strong {

    display: block;

    color: #17324d;

    font-size: 14px;

    margin-bottom: 5px;
}


.no-queue p {

    font-size: 12px;
}


/* =========================================================
   QUICK LINKS
========================================================= */

.quick-links {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 12px;
}


.quick-link {

    display: block;

    text-decoration: none;

    color: #17324d;

    border:
        1px solid
        #e6edef;

    border-radius: 9px;

    padding: 16px;

    transition: .2s;
}


.quick-link:hover {

    border-color: #087f8c;

    transform:
        translateY(-2px);
}


.quick-icon {

    width: 36px;
    height: 36px;

    border-radius: 8px;

    background: #e8f7f8;

    color: #087f8c;

    display: flex;

    align-items: center;

    justify-content: center;

    margin-bottom: 10px;
}


.quick-link strong {

    display: block;

    font-size: 13px;

    margin-bottom: 4px;
}


.quick-link span {

    color: #8a99a4;

    font-size: 11px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1050px) {

    .stats {

        grid-template-columns:
            repeat(2, 1fr);
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

        padding: 22px 18px;
    }

    .stats {

        grid-template-columns: 1fr;
    }

    .welcome {

        padding: 20px;
    }

    .quick-links {

        grid-template-columns: 1fr;
    }

    .queue-details {

        grid-template-columns: 1fr;
    }

    .called-alert {

        top: 15px;

        left: 15px;

        right: 15px;

        width: auto;
    }

    .sound-enable {

        right: 15px;

        bottom: 15px;
    }
}

</style>

</head>


<body>


<!-- =========================================================
     CALLED ALERT
========================================================= -->

<div
    id="calledAlert"
    class="called-alert"
>

    <div class="called-alert-header">

        <div class="called-alert-icon">
            🔔
        </div>

        <div>

            <h3>
                You Have Been Called!
            </h3>

        </div>

    </div>


    <p id="calledAlertMessage">

        Your queue number has been called.

        Please proceed to the assigned service area.

    </p>


    <div class="alert-buttons">

        <button
            type="button"
            class="alert-btn stop-alarm-btn"
            onclick="stopAlarmAndClose()"
        >
            🔕 Stop Alarm
        </button>


        <button
            type="button"
            class="alert-btn close-alert-btn"
            onclick="closeCalledAlert()"
        >
            Close
        </button>

    </div>

</div>


<!-- =========================================================
     ENABLE SOUND
========================================================= -->

<button
    type="button"
    id="soundEnable"
    class="sound-enable"
    onclick="enablePatientSound()"
>
    🔊 Enable Alarm
</button>


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

        <a
            href="patient_dashboard.php"
            class="active"
        >

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


        <!-- MY PRESCRIPTIONS -->

        <a href="patient_prescriptions.php">

            <span class="menu-icon">
                💊
            </span>

            <span>
                My Prescriptions
            </span>

        </a>


        <!-- MY PAYMENTS -->

        <a href="patient_payments.php">

            <span class="menu-icon">
                💳
            </span>

            <span>
                Payments
            </span>

        </a>


        <!-- MY HISTORY -->

        <a href="patient_history.php">

            <span class="menu-icon">
                📋
            </span>

            <span>
                My History
            </span>

        </a>


        <!-- MY PROFILE -->

        <a href="patient_profile.php">

            <span class="menu-icon">
                👤
            </span>

            <span>
                My Profile
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
                Patient Dashboard
            </h1>

            <p>
                SmartHospital Queue Management System
            </p>

        </div>


        <div class="profile">

            <div class="avatar">

                <?= htmlspecialchars($initial) ?>

            </div>


            <div class="profile-info">

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


        <!-- =================================================
             WELCOME
        ================================================= -->

        <section class="welcome">

            <div>

                <h2>

                    Welcome,

                    <?= htmlspecialchars($full_name) ?>

                    👋

                </h2>


                <p>

                    Here you can monitor your hospital queue,

                    view your prescriptions, history and manage
                    your profile.

                </p>

            </div>


            <div class="welcome-icon">
                🏥
            </div>

        </section>


        <!-- =================================================
             STATISTICS
        ================================================= -->

        <section class="stats">


            <!-- PATIENT NUMBER -->

            <div class="stat-card">

                <div class="stat-icon">
                    🪪
                </div>

                <div>

                    <h3>

                        <?= htmlspecialchars(
                            $patient["patient_number"]
                        ) ?>

                    </h3>

                    <p>
                        Patient Number
                    </p>

                </div>

            </div>


            <!-- CURRENT QUEUE -->

            <div class="stat-card">

                <div class="stat-icon">
                    🎫
                </div>

                <div>

                    <h3>

                        <?php if ($current_queue): ?>

                            <?= htmlspecialchars(
                                $current_queue["queue_number"]
                            ) ?>

                        <?php else: ?>

                            —

                        <?php endif; ?>

                    </h3>

                    <p>
                        Current Queue
                    </p>

                </div>

            </div>


            <!-- QUEUE POSITION -->

            <div class="stat-card">

                <div class="stat-icon">
                    ⏳
                </div>

                <div>

                    <h3>

                        <?php if (
                            $queue_position !== null
                        ): ?>

                            <?= $queue_position ?>

                        <?php elseif (
                            $current_queue &&
                            $current_queue["status"] === "called"
                        ): ?>

                            Called

                        <?php elseif (
                            $current_queue &&
                            $current_queue["status"] === "serving"
                        ): ?>

                            Now

                        <?php else: ?>

                            —

                        <?php endif; ?>

                    </h3>

                    <p>
                        Queue Position
                    </p>

                </div>

            </div>


            <!-- HISTORY -->

            <div class="stat-card">

                <div class="stat-icon">
                    📋
                </div>

                <div>

                    <h3>
                        <?= $history_count ?>
                    </h3>

                    <p>
                        Completed Visits
                    </p>

                </div>

            </div>


        </section>


        <!-- =================================================
             DASHBOARD GRID
        ================================================= -->

        <section class="dashboard-grid">


            <!-- =================================================
                 CURRENT QUEUE
            ================================================= -->

            <div class="card">

                <div class="card-header">

                    <h3>
                        My Current Queue
                    </h3>

                    <span>
                        Live
                    </span>

                </div>


                <?php if ($current_queue): ?>


                    <div class="current-queue">


                        <div class="queue-label">
                            Your Queue Number
                        </div>


                        <div class="queue-number">

                            <?= htmlspecialchars(
                                $current_queue["queue_number"]
                            ) ?>

                        </div>


                        <div class="department">

                            <?= htmlspecialchars(
                                $current_queue["department_name"]
                            ) ?>

                        </div>


                        <span
                            class="status <?= htmlspecialchars(
                                $current_queue["status"]
                            ) ?>"
                        >

                            <?= htmlspecialchars(
                                $current_queue["status"]
                            ) ?>

                        </span>


                        <div style="margin-top:10px;">

                            <span
                                class="priority <?= htmlspecialchars(
                                    $current_queue["priority"]
                                ) ?>"
                            >

                                <?= htmlspecialchars(
                                    $current_queue["priority"]
                                ) ?>

                            </span>

                        </div>


                        <div class="queue-details">


                            <div class="queue-detail">

                                <span>
                                    Check-in
                                </span>

                                <strong>

                                    <?= htmlspecialchars(
                                        date(
                                            "d M Y, H:i",
                                            strtotime(
                                                $current_queue[
                                                    "check_in_time"
                                                ]
                                            )
                                        )
                                    ) ?>

                                </strong>

                            </div>


                            <div class="queue-detail">

                                <span>
                                    Nurse
                                </span>

                                <strong>

                                    <?= htmlspecialchars(
                                        $current_queue[
                                            "nurse_name"
                                        ] ?? "Not assigned"
                                    ) ?>

                                </strong>

                            </div>


                        </div>


                        <?php if (
                            $queue_position !== null
                        ): ?>


                            <div class="position-box">

                                <strong>
                                    <?= $queue_position ?>
                                </strong>

                                <p>
                                    Your current position
                                    in the waiting queue
                                </p>

                            </div>


                        <?php elseif (
                            $current_queue["status"] === "called"
                        ): ?>


                            <div class="position-box">

                                <strong>
                                    You have been called
                                </strong>

                                <p>
                                    Please proceed to the
                                    assigned service area.
                                </p>

                            </div>


                        <?php elseif (
                            $current_queue["status"] === "serving"
                        ): ?>


                            <div class="position-box">

                                <strong>
                                    You are being served
                                </strong>

                                <p>
                                    Please remain available
                                    for your consultation.
                                </p>

                            </div>


                        <?php endif; ?>


                    </div>


                <?php else: ?>


                    <div class="no-queue">

                        <div class="no-queue-icon">
                            🎫
                        </div>

                        <strong>
                            You have no active queue
                        </strong>

                        <p>
                            You currently do not have a
                            waiting, called or serving queue.
                        </p>

                    </div>


                <?php endif; ?>


            </div>


            <!-- =================================================
                 QUICK LINKS
            ================================================= -->

            <div class="card">

                <div class="card-header">

                    <h3>
                        Quick Access
                    </h3>

                    <span>
                        Patient Services
                    </span>

                </div>


                <div class="quick-links">


                    <!-- MY QUEUE -->

                    <a
                        href="patient_queue.php"
                        class="quick-link"
                    >

                        <div class="quick-icon">
                            🎫
                        </div>

                        <strong>
                            My Queue
                        </strong>

                        <span>
                            Check your current
                            queue status.
                        </span>

                    </a>


                    <!-- MY PRESCRIPTIONS -->

                    <a
                        href="patient_prescriptions.php"
                        class="quick-link"
                    >

                        <div class="quick-icon">
                            💊
                        </div>

                        <strong>
                            My Prescriptions
                        </strong>

                        <span>
                            View medicines prescribed
                            by your doctor.
                        </span>

                    </a>


                    <!-- MY PAYMENTS -->

                    <a
                        href="patient_payments.php"
                        class="quick-link"
                    >

                        <div class="quick-icon">
                            💳
                        </div>

                        <strong>
                            Payments
                        </strong>

                        <span>
                            View your bills and make
                            hospital payments.
                        </span>

                    </a>


                    <!-- MY HISTORY -->

                    <a
                        href="patient_history.php"
                        class="quick-link"
                    >

                        <div class="quick-icon">
                            📋
                        </div>

                        <strong>
                            My History
                        </strong>

                        <span>
                            View your previous
                            hospital visits.
                        </span>

                    </a>


                    <!-- MY PROFILE -->

                    <a
                        href="patient_profile.php"
                        class="quick-link"
                    >

                        <div class="quick-icon">
                            👤
                        </div>

                        <strong>
                            My Profile
                        </strong>

                        <span>
                            View your personal
                            information.
                        </span>

                    </a>


                </div>

            </div>


        </section>


    </main>

</div>


<script>

/* =========================================================
   SMART HOSPITAL PATIENT QUEUE ALARM
   WEB AUDIO API
========================================================= */


/* =========================================================
   INITIAL QUEUE STATE
========================================================= */

let lastQueueId =
    <?= $current_queue
        ? (int)$current_queue["id"]
        : "null"
    ?>;


let lastQueueStatus =
    <?= $current_queue
        ? json_encode($current_queue["status"])
        : "null"
    ?>;


/* =========================================================
   ALARM VARIABLES
========================================================= */

let alarmInterval = null;

let soundEnabled = false;

let audioContext = null;


/* =========================================================
   GET AUDIO CONTEXT
========================================================= */

function getAudioContext() {

    if (!audioContext) {

        const AudioContext =
            window.AudioContext ||
            window.webkitAudioContext;


        if (!AudioContext) {

            console.error(
                "Web Audio API is not supported."
            );

            return null;
        }


        audioContext =
            new AudioContext();
    }


    return audioContext;
}


/* =========================================================
   ENABLE PATIENT SOUND
========================================================= */

async function enablePatientSound() {

    try {

        const ctx =
            getAudioContext();


        if (!ctx) {

            alert(
                "Your browser does not support audio alerts."
            );

            return;
        }


        if (
            ctx.state === "suspended"
        ) {

            await ctx.resume();
        }


        playBeep();

        soundEnabled = true;


        const button =
            document.getElementById(
                "soundEnable"
            );


        if (button) {

            button.innerHTML =
                "🔊 Alarm Enabled";

            button.style.background =
                "#16834d";


            setTimeout(
                function() {

                    button.style.display =
                        "none";

                },
                2500
            );
        }


        console.log(
            "✅ Patient alarm enabled."
        );

    }

    catch (error) {

        console.error(
            "Sound enable error:",
            error
        );


        alert(
            "Could not enable the alarm. Please click the button again."
        );
    }
}


/* =========================================================
   PLAY BEEP
========================================================= */

function playBeep() {

    const ctx =
        getAudioContext();


    if (!ctx) {
        return;
    }


    if (
        ctx.state === "suspended"
    ) {

        ctx.resume();
    }


    const oscillator =
        ctx.createOscillator();


    const gain =
        ctx.createGain();


    oscillator.frequency.value =
        880;


    oscillator.type =
        "sine";


    gain.gain.setValueAtTime(
        0.0001,
        ctx.currentTime
    );


    gain.gain.exponentialRampToValueAtTime(
        0.7,
        ctx.currentTime + 0.03
    );


    gain.gain.exponentialRampToValueAtTime(
        0.0001,
        ctx.currentTime + 0.45
    );


    oscillator.connect(gain);

    gain.connect(
        ctx.destination
    );


    oscillator.start();


    oscillator.stop(
        ctx.currentTime + 0.5
    );
}


/* =========================================================
   START PATIENT ALARM
========================================================= */

function startPatientAlarm() {

    if (!soundEnabled) {

        console.log(
            "Alarm waiting for sound permission."
        );


        const button =
            document.getElementById(
                "soundEnable"
            );


        if (button) {

            button.style.display =
                "block";

            button.innerHTML =
                "🔊 Click to Enable Alarm";

            button.style.background =
                "#087f8c";
        }


        return;
    }


    stopPatientAlarm();


    playBeep();


    alarmInterval =
        setInterval(
            function() {

                playBeep();

            },
            1500
        );


    console.log(
        "🚨 PATIENT ALARM STARTED"
    );
}


/* =========================================================
   STOP PATIENT ALARM
========================================================= */

function stopPatientAlarm() {

    if (
        alarmInterval !== null
    ) {

        clearInterval(
            alarmInterval
        );

        alarmInterval = null;
    }


    console.log(
        "🔕 Patient alarm stopped."
    );
}


/* =========================================================
   SHOW CALLED ALERT
========================================================= */

function showCalledAlert(
    queueNumber
) {

    const alertBox =
        document.getElementById(
            "calledAlert"
        );


    const message =
        document.getElementById(
            "calledAlertMessage"
        );


    if (message) {

        message.innerHTML =
            "Queue number <strong>" +
            escapeHtml(queueNumber) +
            "</strong> has been called. " +
            "Please proceed to the assigned service area.";
    }


    if (alertBox) {

        alertBox.classList.add(
            "show"
        );
    }


    startPatientAlarm();


    document.title =
        "🔔 CALLED - SmartHospital";


    console.log(
        "🚨 PATIENT CALLED:",
        queueNumber
    );
}


/* =========================================================
   CLOSE ALERT
========================================================= */

function closeCalledAlert() {

    const alertBox =
        document.getElementById(
            "calledAlert"
        );


    if (alertBox) {

        alertBox.classList.remove(
            "show"
        );
    }


    document.title =
        "Patient Dashboard | SmartHospital";
}


/* =========================================================
   STOP ALARM AND CLOSE
========================================================= */

function stopAlarmAndClose() {

    stopPatientAlarm();

    closeCalledAlert();
}


/* =========================================================
   ESCAPE HTML
========================================================= */

function escapeHtml(value) {

    const div =
        document.createElement(
            "div"
        );


    div.textContent =
        value;


    return div.innerHTML;
}


/* =========================================================
   CHECK QUEUE STATUS
========================================================= */

async function checkQueueStatus() {

    try {

        const response =
            await fetch(
                "patient_queue_status.php?_=" +
                Date.now(),
                {
                    method: "GET",

                    cache: "no-store",

                    credentials:
                        "same-origin",

                    headers: {
                        "X-Requested-With":
                            "XMLHttpRequest"
                    }
                }
            );


        if (!response.ok) {

            throw new Error(
                "HTTP " +
                response.status
            );
        }


        const data =
            await response.json();


        console.log(
            "Queue status:",
            data
        );


        if (!data.success) {

            console.log(
                "Queue API error:",
                data.message
            );

            return;
        }


        const newQueueId =
            data.queue_id !== null &&
            data.queue_id !== undefined
                ? parseInt(data.queue_id)
                : null;


        const newStatus =
            data.status || null;


        const newQueueNumber =
            data.queue_number || null;


        console.log(
            "Queue ID:",
            newQueueId,

            "| Status:",
            newStatus,

            "| Number:",
            newQueueNumber
        );


        const newlyCalled =
            newStatus === "called"
            &&
            (
                lastQueueStatus !== "called"
                ||
                lastQueueId !== newQueueId
            );


        if (newlyCalled) {

            console.log(
                "🚨🚨 PATIENT HAS BEEN CALLED 🚨🚨"
            );


            showCalledAlert(
                newQueueNumber
            );
        }


        const queueChanged =
            lastQueueId !== newQueueId
            ||
            lastQueueStatus !== newStatus;


        lastQueueId =
            newQueueId;


        lastQueueStatus =
            newStatus;


        if (
            newStatus !== "called"
            &&
            alarmInterval !== null
        ) {

            stopPatientAlarm();
        }


        if (
            queueChanged &&
            newStatus !== "called"
        ) {

            setTimeout(
                function() {

                    window.location.reload();

                },
                700
            );
        }

    }

    catch (error) {

        console.error(
            "Queue monitoring error:",
            error
        );
    }
}


/* =========================================================
   MONITOR EVERY 3 SECONDS
========================================================= */

setInterval(
    checkQueueStatus,
    3000
);


/* =========================================================
   FIRST CHECK
========================================================= */

setTimeout(
    checkQueueStatus,
    1000
);


/* =========================================================
   PAGE VISIBILITY
========================================================= */

document.addEventListener(
    "visibilitychange",
    function() {

        if (
            document.visibilityState ===
            "visible"
        ) {

            checkQueueStatus();
        }
    }
);


/* =========================================================
   CLEANUP
========================================================= */

window.addEventListener(
    "beforeunload",
    function() {

        stopPatientAlarm();

    }
);

</script>


</body>

</html>
```
