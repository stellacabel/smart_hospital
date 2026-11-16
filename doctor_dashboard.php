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
   HANDLE POST ACTIONS
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /* =====================================================
       UPDATE DOCTOR AVAILABILITY
    ===================================================== */

    if (isset($_POST["update_status"])) {

        $new_status = $_POST["status"] ?? "";

        $allowed_statuses = [
            "available",
            "busy",
            "offline"
        ];

        if (in_array($new_status, $allowed_statuses, true)) {

            $stmt = $conn->prepare("
                UPDATE doctors
                SET status = ?
                WHERE id = ?
            ");

            $stmt->bind_param(
                "si",
                $new_status,
                $doctor_id
            );

            $stmt->execute();
            $stmt->close();
        }

        header("Location: doctor_dashboard.php");
        exit;
    }


    /* =====================================================
       CALL PATIENT
       Assigns this doctor to the queue entry.
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

        header("Location: doctor_dashboard.php");
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

        header("Location: doctor_dashboard.php");
        exit;
    }
}


/* =========================================================
   REFRESH DOCTOR STATUS
========================================================= */

$stmt = $conn->prepare("
    SELECT status
    FROM doctors
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $doctor_id);
$stmt->execute();

$status_result = $stmt->get_result();
$status_row = $status_result->fetch_assoc();

$doctor["doctor_status"] = $status_row["status"] ?? "offline";

$stmt->close();


/* =========================================================
   WAITING PATIENTS
========================================================= */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM queue
    WHERE department_id = ?
    AND status = 'waiting'
");

$stmt->bind_param(
    "i",
    $department_id
);

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

$stmt->bind_param(
    "i",
    $doctor_id
);

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

$stmt->bind_param(
    "i",
    $doctor_id
);

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

$stmt->bind_param(
    "i",
    $doctor_id
);

$stmt->execute();

$completed_count = (int) $stmt
    ->get_result()
    ->fetch_assoc()["total"];

$stmt->close();


/* =========================================================
   CURRENT DOCTOR QUEUE
========================================================= */

$stmt = $conn->prepare("
    SELECT
        q.id,
        q.patient_id,
        q.doctor_id,
        q.queue_number,
        q.status,
        q.priority,
        q.check_in_time,
        q.called_time,

        p.patient_number,
        p.full_name AS patient_name,
        p.gender,
        p.date_of_birth,

        dep.name AS department_name

    FROM queue q

    INNER JOIN patients p
        ON q.patient_id = p.id

    INNER JOIN departments dep
        ON q.department_id = dep.id

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
    Doctor Dashboard | SmartHospital
</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

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
}

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


/* =========================================================
   PROFILE
========================================================= */

.profile {
    padding: 18px 10px;
    border-top: 1px solid rgba(255,255,255,.1);
    border-bottom: 1px solid rgba(255,255,255,.1);
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
    padding: 12px 13px;
    border-radius: 8px;
    color: #cbd9e1;
    font-size: 13px;
    transition: .2s;
}

.nav a:hover,
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
    border-bottom: 1px solid #e8eef1;
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


/* =========================================================
   CONTENT
========================================================= */

.content {
    padding: 30px;
}


/* =========================================================
   WELCOME
========================================================= */

.welcome {
    background: white;
    border: 1px solid #e8eef1;
    border-radius: 14px;
    padding: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 22px;
}

.welcome h2 {
    font-size: 22px;
    margin-bottom: 5px;
}

.welcome p {
    color: #7c8d99;
    font-size: 13px;
}

.department {
    color: #087f8c;
    font-weight: 600;
}


/* =========================================================
   STATUS
========================================================= */

.status-box {
    display: flex;
    align-items: center;
    gap: 10px;
}

.status-box label {
    font-size: 12px;
    color: #71818c;
}

.status-box select {
    border: 1px solid #dce6ea;
    padding: 9px 12px;
    border-radius: 7px;
    outline: none;
    font-family: inherit;
    font-size: 12px;
}


/* =========================================================
   STATISTICS
========================================================= */

.stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 22px;
}

.stat {
    background: white;
    border: 1px solid #e8eef1;
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
    border: 1px solid #e8eef1;
    border-radius: 14px;
    overflow: hidden;
}

.queue-header {
    padding: 20px 22px;
    border-bottom: 1px solid #edf1f3;
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
    border-top: 1px solid #edf1f3;
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
    display: inline-block;
}

.btn-call {
    background: #087f8c;
    color: white;
}

.btn-serve {
    background: #1769aa;
    color: white;
}

.btn-consult {
    background: #087f8c;
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
    padding: 45px 20px;
    color: #82919b;
    font-size: 13px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1000px) {

    .stats {
        grid-template-columns: repeat(2, 1fr);
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

    .welcome {
        flex-direction: column;
        align-items: flex-start;
        gap: 18px;
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

    <div class="logo">

        <span class="logo-icon">
            +
        </span>

        SmartHospital

    </div>


    <div class="profile">

        <div class="profile-name">
            <?= e($doctor["full_name"]) ?>
        </div>

        <div class="profile-role">
            Doctor
        </div>

    </div>


    <nav class="nav">

        <a
            href="doctor_dashboard.php"
            class="active"
        >
            Dashboard
        </a>

        <a href="doctor_queue.php">
            My Queue
        </a>

        <a href="doctor_patients.php">
            Patients
        </a>

        <a href="doctor_consultations.php">
            Consultations
        </a>

        <a href="doctor_profile.php">
            My Profile
        </a>

    </nav>

</aside>


<!-- =====================================================
     MAIN
===================================================== -->

<main class="main">


    <!-- TOPBAR -->

    <header class="topbar">

        <div>

            <h1>
                Doctor Dashboard
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


    <!-- CONTENT -->

    <section class="content">


        <!-- WELCOME -->

        <div class="welcome">

            <div>

                <h2>

                    Welcome,
                    <?= e($doctor["full_name"]) ?>

                </h2>

                <p>

                    <?= e($doctor["specialization"]) ?>

                    ·

                    <span class="department">

                        <?= e($doctor["department_name"]) ?>

                    </span>

                </p>

            </div>


            <!-- AVAILABILITY -->

            <form
                method="POST"
                class="status-box"
            >

                <label>
                    Availability
                </label>

                <select
                    name="status"
                    onchange="this.form.submit()"
                >

                    <option
                        value="available"
                        <?= $doctor["doctor_status"] === "available"
                            ? "selected"
                            : "" ?>
                    >
                        Available
                    </option>

                    <option
                        value="busy"
                        <?= $doctor["doctor_status"] === "busy"
                            ? "selected"
                            : "" ?>
                    >
                        Busy
                    </option>

                    <option
                        value="offline"
                        <?= $doctor["doctor_status"] === "offline"
                            ? "selected"
                            : "" ?>
                    >
                        Offline
                    </option>

                </select>

                <input
                    type="hidden"
                    name="update_status"
                    value="1"
                >

            </form>

        </div>


        <!-- =================================================
             STATISTICS
        ================================================== -->

        <div class="stats">


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
             QUEUE
        ================================================== -->

        <div class="queue-card">


            <div class="queue-header">

                <h3>
                    My Department Queue
                </h3>

                <span>
                    <?= e($doctor["department_name"]) ?>
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


                        <?php while ($row = $queue_result->fetch_assoc()): ?>


                            <tr>


                                <!-- QUEUE NUMBER -->

                                <td>

                                    <strong>
                                        <?= e($row["queue_number"]) ?>
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
                                            e($row["priority"])
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
                                            e($row["status"])
                                        ) ?>

                                    </span>

                                </td>


                                <!-- ACTION -->

                                <td>


                                    <?php if (
                                        $row["status"] === "waiting"
                                    ): ?>


                                        <!-- CALL PATIENT -->

                                        <form
                                            method="POST"
                                            class="action-form"
                                        >

                                            <input
                                                type="hidden"
                                                name="queue_id"
                                                value="<?= (int)$row["id"] ?>"
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


                                        <!-- START SERVING -->

                                        <form
                                            method="POST"
                                            class="action-form"
                                        >

                                            <input
                                                type="hidden"
                                                name="queue_id"
                                                value="<?= (int)$row["id"] ?>"
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


                                        <!-- =================================================
                                             CONSULT PATIENT
                                        ================================================== -->

                                        <a
                                            href="doctor_consultations.php?queue_id=<?= (int)$row["id"] ?>"
                                            class="btn btn-consult"
                                        >

                                            🩺 Consult Patient

                                        </a>


                                    <?php endif; ?>


                                </td>


                            </tr>


                        <?php endwhile; ?>


                        </tbody>

                    </table>

                </div>


            <?php else: ?>


                <div class="empty">

                    No patients are currently waiting
                    in your department.

                </div>


            <?php endif; ?>


        </div>


    </section>


</main>


</body>

</html>