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
   MESSAGE
========================================================= */

$message = "";
$message_type = "";


/* =========================================================
   ADD PATIENT TO QUEUE
========================================================= */

if (isset($_POST["add_queue"])) {

    $patient_id = intval($_POST["patient_id"] ?? 0);
    $department_id = intval($_POST["department_id"] ?? 0);
    $doctor_id = intval($_POST["doctor_id"] ?? 0);

    $priority = $_POST["priority"] ?? "normal";
    $notes = trim($_POST["notes"] ?? "");


    if ($patient_id <= 0 || $department_id <= 0) {

        $message = "Please select a patient and department.";
        $message_type = "error";

    } else {

        /* -----------------------------------------------------
           CHECK WHETHER PATIENT IS ALREADY ACTIVE
        ----------------------------------------------------- */

        $check = $conn->prepare(
            "SELECT id
             FROM queue
             WHERE patient_id = ?
             AND status IN ('waiting', 'called', 'serving')
             LIMIT 1"
        );

        $check->bind_param("i", $patient_id);
        $check->execute();

        $existing = $check->get_result();


        if ($existing->num_rows > 0) {

            $message = "This patient is already in the active queue.";
            $message_type = "error";

        } else {

            /* -------------------------------------------------
               VERIFY DOCTOR
            ------------------------------------------------- */

            $doctor_value = null;

            if ($doctor_id > 0) {

                $doctor_check = $conn->prepare(
                    "SELECT id
                     FROM doctors
                     WHERE id = ?
                     AND department_id = ?
                     AND status IN ('available', 'busy')
                     LIMIT 1"
                );

                $doctor_check->bind_param(
                    "ii",
                    $doctor_id,
                    $department_id
                );

                $doctor_check->execute();

                $doctor_result = $doctor_check->get_result();


                if ($doctor_result->num_rows === 1) {

                    $doctor_value = $doctor_id;

                } else {

                    $message =
                        "The selected doctor is not available for this department.";

                    $message_type = "error";
                }

                $doctor_check->close();
            }


            /* -------------------------------------------------
               ONLY CONTINUE IF NO ERROR
            ------------------------------------------------- */

            if ($message_type !== "error") {

                /* ---------------------------------------------
                   GENERATE QUEUE NUMBER
                --------------------------------------------- */

                $today = date("Y-m-d");

                $count_stmt = $conn->prepare(
                    "SELECT COUNT(*) AS total
                     FROM queue
                     WHERE DATE(created_at) = ?"
                );

                $count_stmt->bind_param(
                    "s",
                    $today
                );

                $count_stmt->execute();

                $count_result = $count_stmt->get_result();

                $count_row = $count_result->fetch_assoc();

                $next_number =
                    intval($count_row["total"]) + 1;

                $queue_number =
                    "Q-" .
                    str_pad(
                        $next_number,
                        3,
                        "0",
                        STR_PAD_LEFT
                    );

                $count_stmt->close();


                /* ---------------------------------------------
                   INSERT QUEUE
                --------------------------------------------- */

                $stmt = $conn->prepare(
                    "INSERT INTO queue
                    (
                        patient_id,
                        department_id,
                        doctor_id,
                        queue_number,
                        status,
                        priority,
                        notes
                    )
                    VALUES (?, ?, ?, ?, 'waiting', ?, ?)"
                );


                $stmt->bind_param(
                    "iiisss",
                    $patient_id,
                    $department_id,
                    $doctor_value,
                    $queue_number,
                    $priority,
                    $notes
                );


                if ($stmt->execute()) {

                    $message =
                        "Patient added to queue successfully. Queue number: "
                        . $queue_number;

                    $message_type = "success";

                } else {

                    $message =
                        "Unable to add patient to queue: "
                        . $stmt->error;

                    $message_type = "error";
                }


                $stmt->close();
            }
        }


        $check->close();
    }
}


/* =========================================================
   CALL PATIENT
========================================================= */

if (isset($_GET["call"])) {

    $queue_id = intval($_GET["call"]);


    if ($queue_id > 0) {

        $stmt = $conn->prepare(
            "UPDATE queue
             SET
                status = 'called',
                called_time = NOW()
             WHERE id = ?
             AND status = 'waiting'"
        );


        $stmt->bind_param(
            "i",
            $queue_id
        );


        if (
            $stmt->execute()
            &&
            $stmt->affected_rows > 0
        ) {

            $message =
                "Patient has been called.";

            $message_type =
                "success";

        } else {

            $message =
                "Patient could not be called. The queue may have changed.";

            $message_type =
                "error";
        }


        $stmt->close();
    }
}


/* =========================================================
   START SERVING
========================================================= */

if (isset($_GET["serve"])) {

    $queue_id =
        intval($_GET["serve"]);


    if ($queue_id > 0) {

        $stmt = $conn->prepare(
            "UPDATE queue
             SET status = 'serving'
             WHERE id = ?
             AND status = 'called'"
        );


        $stmt->bind_param(
            "i",
            $queue_id
        );


        if (
            $stmt->execute()
            &&
            $stmt->affected_rows > 0
        ) {

            $message =
                "Patient is now being served.";

            $message_type =
                "success";

        } else {

            $message =
                "Patient could not be moved to serving.";

            $message_type =
                "error";
        }


        $stmt->close();
    }
}


/* =========================================================
   COMPLETE QUEUE
========================================================= */

if (isset($_GET["complete"])) {

    $queue_id =
        intval($_GET["complete"]);


    if ($queue_id > 0) {

        $stmt = $conn->prepare(
            "UPDATE queue
             SET
                status = 'completed',
                completed_time = NOW()
             WHERE id = ?
             AND status IN ('called', 'serving')"
        );


        $stmt->bind_param(
            "i",
            $queue_id
        );


        if (
            $stmt->execute()
            &&
            $stmt->affected_rows > 0
        ) {

            $message =
                "Patient visit completed successfully.";

            $message_type =
                "success";

        } else {

            $message =
                "Patient could not be completed.";

            $message_type =
                "error";
        }


        $stmt->close();
    }
}


/* =========================================================
   CANCEL QUEUE
========================================================= */

if (isset($_GET["cancel"])) {

    $queue_id =
        intval($_GET["cancel"]);


    if ($queue_id > 0) {

        $stmt = $conn->prepare(
            "UPDATE queue
             SET status = 'cancelled'
             WHERE id = ?
             AND status IN ('waiting', 'called')"
        );


        $stmt->bind_param(
            "i",
            $queue_id
        );


        if (
            $stmt->execute()
            &&
            $stmt->affected_rows > 0
        ) {

            $message =
                "Queue entry cancelled.";

            $message_type =
                "success";

        } else {

            $message =
                "Queue entry could not be cancelled.";

            $message_type =
                "error";
        }


        $stmt->close();
    }
}


/* =========================================================
   STATISTICS
========================================================= */

$total_today = 0;
$waiting = 0;
$called = 0;
$serving = 0;
$completed = 0;


/* TOTAL TODAY */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM queue
     WHERE DATE(created_at) = CURDATE()"
);

if ($result) {

    $row =
        $result->fetch_assoc();

    $total_today =
        intval($row["total"]);
}


/* WAITING */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM queue
     WHERE DATE(created_at) = CURDATE()
     AND status = 'waiting'"
);

if ($result) {

    $row =
        $result->fetch_assoc();

    $waiting =
        intval($row["total"]);
}


/* CALLED */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM queue
     WHERE DATE(created_at) = CURDATE()
     AND status = 'called'"
);

if ($result) {

    $row =
        $result->fetch_assoc();

    $called =
        intval($row["total"]);
}


/* SERVING */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM queue
     WHERE DATE(created_at) = CURDATE()
     AND status = 'serving'"
);

if ($result) {

    $row =
        $result->fetch_assoc();

    $serving =
        intval($row["total"]);
}


/* COMPLETED */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM queue
     WHERE DATE(created_at) = CURDATE()
     AND status = 'completed'"
);

if ($result) {

    $row =
        $result->fetch_assoc();

    $completed =
        intval($row["total"]);
}


/* =========================================================
   SEARCH
========================================================= */

$search =
    trim($_GET["search"] ?? "");


/* =========================================================
   QUEUE LIST
========================================================= */

if ($search !== "") {

    $stmt = $conn->prepare(
        "SELECT
            q.id,
            q.patient_id,
            q.department_id,
            q.doctor_id,
            q.queue_number,
            q.status,
            q.priority,
            q.check_in_time,
            q.called_time,
            q.completed_time,
            q.notes,

            p.patient_number,
            p.full_name AS patient_name,
            p.phone,

            d.name AS department_name,

            u.full_name AS doctor_name

         FROM queue q

         INNER JOIN patients p
             ON q.patient_id = p.id

         INNER JOIN departments d
             ON q.department_id = d.id

         LEFT JOIN doctors doc
             ON q.doctor_id = doc.id

         LEFT JOIN users u
             ON doc.user_id = u.id

         WHERE
            p.full_name LIKE ?
            OR p.patient_number LIKE ?
            OR p.phone LIKE ?
            OR q.queue_number LIKE ?
            OR d.name LIKE ?
            OR u.full_name LIKE ?
            OR q.status LIKE ?
            OR q.priority LIKE ?

         ORDER BY q.id DESC"
    );


    $search_value =
        "%" . $search . "%";


    $stmt->bind_param(
        "ssssssss",
        $search_value,
        $search_value,
        $search_value,
        $search_value,
        $search_value,
        $search_value,
        $search_value,
        $search_value
    );


    $stmt->execute();

    $queue_list =
        $stmt->get_result();

} else {

    $queue_list = $conn->query(
        "SELECT
            q.id,
            q.patient_id,
            q.department_id,
            q.doctor_id,
            q.queue_number,
            q.status,
            q.priority,
            q.check_in_time,
            q.called_time,
            q.completed_time,
            q.notes,

            p.patient_number,
            p.full_name AS patient_name,
            p.phone,

            d.name AS department_name,

            u.full_name AS doctor_name

         FROM queue q

         INNER JOIN patients p
             ON q.patient_id = p.id

         INNER JOIN departments d
             ON q.department_id = d.id

         LEFT JOIN doctors doc
             ON q.doctor_id = doc.id

         LEFT JOIN users u
             ON doc.user_id = u.id

         ORDER BY q.id DESC"
    );
}


if (!$queue_list) {

    die(
        "Queue query failed: "
        . htmlspecialchars($conn->error)
    );
}


/* =========================================================
   PATIENTS
========================================================= */

$patients = $conn->query(
    "SELECT
        id,
        patient_number,
        full_name,
        phone
     FROM patients
     ORDER BY full_name ASC"
);


if (!$patients) {

    die(
        "Patients query failed: "
        . htmlspecialchars($conn->error)
    );
}


/* =========================================================
   DEPARTMENTS
========================================================= */

$departments = $conn->query(
    "SELECT
        id,
        name
     FROM departments
     WHERE status = 'active'
     ORDER BY name ASC"
);


if (!$departments) {

    die(
        "Departments query failed: "
        . htmlspecialchars($conn->error)
    );
}


/* =========================================================
   DOCTORS
========================================================= */

$doctors = $conn->query(
    "SELECT
        doc.id,
        u.full_name,
        doc.department_id,
        doc.specialization,
        doc.status

     FROM doctors doc

     INNER JOIN users u
        ON doc.user_id = u.id

     WHERE doc.status IN ('available', 'busy')

     ORDER BY u.full_name ASC"
);


if (!$doctors) {

    die(
        "Doctors query failed: "
        . htmlspecialchars($conn->error)
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
    Queue Management | SmartHospital
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
    background: rgba(255,255,255,0.08);
    color: #dce7ed;
    text-decoration: none;
    font-size: 13px;
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
   PAGE HEADING
========================================================= */

.page-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
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


.add-btn {
    background: #087f8c;
    color: white;
    border: none;
    padding: 12px 18px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
}


/* =========================================================
   MESSAGE
========================================================= */

.message {
    padding: 13px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 13px;
}


.message.success {
    background: #e8f7ef;
    color: #18794e;
    border: 1px solid #c9ecd9;
}


.message.error {
    background: #fff0ef;
    color: #b42318;
    border: 1px solid #f4c7c3;
}


/* =========================================================
   STATISTICS
========================================================= */

.stats {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}


.stat-card {
    background: white;
    border: 1px solid #e9eff2;
    border-radius: 12px;
    padding: 18px;
    display: flex;
    align-items: center;
    gap: 13px;
}


.stat-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: #e8f7f8;
    color: #087f8c;
    display: flex;
    align-items: center;
    justify-content: center;
}


.stat-card h3 {
    font-size: 21px;
}


.stat-card p {
    color: #8796a1;
    font-size: 11px;
}


/* =========================================================
   CARD
========================================================= */

.card {
    background: white;
    border: 1px solid #e9eff2;
    border-radius: 12px;
    padding: 23px;
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
   SEARCH
========================================================= */

.search-box {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}


.search-box input {
    flex: 1;
    padding: 11px 13px;
    border: 1px solid #dce6ea;
    border-radius: 8px;
    outline: none;
    font-size: 13px;
}


.search-box button {
    padding: 11px 18px;
    background: #17324d;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
}


.search-box a {
    padding: 11px 15px;
    background: #eef3f5;
    color: #17324d;
    text-decoration: none;
    border-radius: 8px;
    font-size: 13px;
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
    min-width: 1100px;
}


th {
    background: #f7fafb;
    color: #71828e;
    font-size: 11px;
    text-transform: uppercase;
    padding: 13px;
    text-align: left;
}


td {
    padding: 14px 13px;
    border-bottom: 1px solid #edf1f3;
    font-size: 13px;
}


.patient-name {
    font-weight: 600;
}


.patient-number,
.doctor-specialization {
    color: #087f8c;
    font-size: 11px;
    margin-top: 3px;
}


.phone {
    color: #8796a1;
    font-size: 11px;
    margin-top: 2px;
}


/* =========================================================
   BADGES
========================================================= */

.badge,
.priority {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
    text-transform: capitalize;
}


.badge.waiting {
    background: #fff5df;
    color: #a66a00;
}


.badge.called {
    background: #e8f7f8;
    color: #087f8c;
}


.badge.serving {
    background: #e8f7ef;
    color: #16834d;
}


.badge.completed {
    background: #eef1f3;
    color: #687781;
}


.badge.cancelled {
    background: #fff0ef;
    color: #b42318;
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
   QUEUE NUMBER
========================================================= */

.queue-number {
    font-weight: 700;
    font-size: 15px;
    color: #087f8c;
}


/* =========================================================
   ACTION BUTTONS
========================================================= */

.action-btn {
    display: inline-block;
    padding: 6px 8px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 10px;
    margin: 2px;
    font-weight: 600;
}


.call-btn {
    background: #e8f7f8;
    color: #087f8c;
}


.serve-btn {
    background: #e8f7ef;
    color: #16834d;
}


.complete-btn {
    background: #eef3f5;
    color: #17324d;
}


.cancel-btn {
    background: #fff0ef;
    color: #b42318;
}


/* =========================================================
   EMPTY
========================================================= */

.empty {
    text-align: center;
    padding: 45px 20px;
    color: #8796a1;
}


/* =========================================================
   MODAL
========================================================= */

.modal {
    display: none;
    position: fixed;
    z-index: 500;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(16,45,68,0.55);
    align-items: center;
    justify-content: center;
    padding: 20px;
}


.modal-box {
    background: white;
    width: 100%;
    max-width: 550px;
    border-radius: 14px;
    padding: 25px;
    max-height: 90vh;
    overflow-y: auto;
}


.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 22px;
}


.modal-header h3 {
    font-size: 18px;
}


.close {
    border: none;
    background: none;
    font-size: 25px;
    color: #8796a1;
    cursor: pointer;
}


.form-group {
    margin-bottom: 16px;
}


.form-group label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 7px;
}


.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 11px 12px;
    border: 1px solid #dce6ea;
    border-radius: 8px;
    outline: none;
    font-size: 13px;
    background: white;
}


.form-group textarea {
    resize: vertical;
    min-height: 80px;
}


.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}


.modal-cancel {
    border: none;
    background: #eef3f5;
    color: #17324d;
    padding: 11px 17px;
    border-radius: 8px;
    cursor: pointer;
}


.save-btn {
    border: none;
    background: #087f8c;
    color: white;
    padding: 11px 18px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1200px) {

    .stats {
        grid-template-columns: repeat(3, 1fr);
    }

}


@media (max-width: 900px) {

    .stats {
        grid-template-columns: repeat(2, 1fr);
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

    .admin-info {
        display: none;
    }

    .content {
        padding: 22px 18px;
    }

    .page-heading {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

    .stats {
        grid-template-columns: 1fr;
    }

    .search-box {
        flex-direction: column;
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


        <a
            href="queue.php"
            class="active"
        >

            <span class="menu-icon">
                🎫
            </span>

            <span>
                Queue
            </span>

        </a>


        <a href="#">

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


<header class="topbar">

    <div class="page-title">

        <h1>
            Queue Management
        </h1>

        <p>
            SmartHospital Queue Management System
        </p>

    </div>


    <div class="admin-profile">

        <div class="avatar">

            <?= htmlspecialchars(
                strtoupper(
                    substr(
                        $full_name,
                        0,
                        1
                    )
                )
            ) ?>

        </div>


        <div class="admin-info">

            <strong>
                <?= htmlspecialchars($full_name) ?>
            </strong>

            <span>
                System Administrator
            </span>

        </div>

    </div>

</header>


<main class="content">


<div class="page-heading">

    <div>

        <h2>
            Patient Queue
        </h2>

        <p>
            Assign patients to departments and doctors.
        </p>

    </div>


    <button
        type="button"
        class="add-btn"
        onclick="openQueueModal()"
    >
        + Add To Queue
    </button>

</div>


<?php if ($message !== ""): ?>

    <div class="message <?= htmlspecialchars($message_type) ?>">

        <?= htmlspecialchars($message) ?>

    </div>

<?php endif; ?>


<!-- =========================================================
     STATISTICS
========================================================= -->

<section class="stats">


    <div class="stat-card">

        <div class="stat-icon">
            🎫
        </div>

        <div>

            <h3>
                <?= $total_today ?>
            </h3>

            <p>
                Today's Queue
            </p>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-icon">
            🟡
        </div>

        <div>

            <h3>
                <?= $waiting ?>
            </h3>

            <p>
                Waiting
            </p>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-icon">
            📢
        </div>

        <div>

            <h3>
                <?= $called ?>
            </h3>

            <p>
                Called
            </p>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-icon">
            🩺
        </div>

        <div>

            <h3>
                <?= $serving ?>
            </h3>

            <p>
                Serving
            </p>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-icon">
            ✅
        </div>

        <div>

            <h3>
                <?= $completed ?>
            </h3>

            <p>
                Completed
            </p>

        </div>

    </div>


</section>


<!-- =========================================================
     QUEUE TABLE
========================================================= -->

<section class="card">


<div class="card-header">

    <h3>
        Today's Patient Queue
    </h3>

    <span>
        <?= $total_today ?> patient(s)
    </span>

</div>


<form
    method="GET"
    class="search-box"
>

    <input
        type="text"
        name="search"
        value="<?= htmlspecialchars($search) ?>"
        placeholder="Search patient, doctor, queue number, department or status..."
    >


    <button type="submit">
        Search
    </button>


    <?php if ($search !== ""): ?>

        <a href="queue.php">
            Clear
        </a>

    <?php endif; ?>

</form>


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
        Department
    </th>

    <th>
        Doctor
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
        Actions
    </th>

</tr>

</thead>


<tbody>


<?php if ($queue_list->num_rows > 0): ?>


<?php while ($item = $queue_list->fetch_assoc()): ?>


<tr>


<td>

    <div class="queue-number">

        <?= htmlspecialchars(
            $item["queue_number"]
        ) ?>

    </div>

</td>


<td>

    <div class="patient-name">

        <?= htmlspecialchars(
            $item["patient_name"]
        ) ?>

    </div>

    <div class="patient-number">

        <?= htmlspecialchars(
            $item["patient_number"]
        ) ?>

    </div>


    <?php if (!empty($item["phone"])): ?>

        <div class="phone">

            <?= htmlspecialchars(
                $item["phone"]
            ) ?>

        </div>

    <?php endif; ?>

</td>


<td>

    <?= htmlspecialchars(
        $item["department_name"]
    ) ?>

</td>


<td>

    <?php if (!empty($item["doctor_name"])): ?>

        <strong>
            <?= htmlspecialchars(
                $item["doctor_name"]
            ) ?>
        </strong>

    <?php else: ?>

        <span style="color:#b42318;">
            Not assigned
        </span>

    <?php endif; ?>

</td>


<td>

    <span
        class="priority <?= htmlspecialchars(
            $item["priority"]
        ) ?>"
    >

        <?= htmlspecialchars(
            $item["priority"]
        ) ?>

    </span>

</td>


<td>

    <span
        class="badge <?= htmlspecialchars(
            $item["status"]
        ) ?>"
    >

        <?= htmlspecialchars(
            $item["status"]
        ) ?>

    </span>

</td>


<td>

    <?= date(
        "H:i",
        strtotime(
            $item["check_in_time"]
        )
    ) ?>

</td>


<td>


<?php if ($item["status"] === "waiting"): ?>


    <a
        href="queue.php?call=<?= (int)$item["id"] ?>"
        class="action-btn call-btn"
        onclick="return confirm('Call this patient?');"
    >
        Call
    </a>


    <a
        href="queue.php?cancel=<?= (int)$item["id"] ?>"
        class="action-btn cancel-btn"
        onclick="return confirm('Cancel this queue entry?');"
    >
        Cancel
    </a>


<?php elseif ($item["status"] === "called"): ?>


    <a
        href="queue.php?serve=<?= (int)$item["id"] ?>"
        class="action-btn serve-btn"
        onclick="return confirm('Start serving this patient?');"
    >
        Serve
    </a>


    <a
        href="queue.php?cancel=<?= (int)$item["id"] ?>"
        class="action-btn cancel-btn"
        onclick="return confirm('Cancel this queue entry?');"
    >
        Cancel
    </a>


<?php elseif ($item["status"] === "serving"): ?>


    <a
        href="queue.php?complete=<?= (int)$item["id"] ?>"
        class="action-btn complete-btn"
        onclick="return confirm('Complete this patient visit?');"
    >
        Complete
    </a>


<?php else: ?>

    —

<?php endif; ?>


</td>


</tr>


<?php endwhile; ?>


<?php else: ?>


<tr>

<td colspan="8">

<div class="empty">

    🎫

    <br><br>

    <strong>
        No queue entries found.
    </strong>

    <p style="margin-top:6px;">
        Add a patient to the queue to get started.
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


<!-- =========================================================
     ADD TO QUEUE MODAL
========================================================= -->

<div
    class="modal"
    id="queueModal"
>

<div class="modal-box">


<div class="modal-header">

    <h3>
        Add Patient To Queue
    </h3>


    <button
        type="button"
        class="close"
        onclick="closeQueueModal()"
    >
        ×
    </button>

</div>


<form method="POST">


<!-- PATIENT -->

<div class="form-group">

    <label>
        Patient
    </label>


    <select
        name="patient_id"
        required
    >

        <option value="">
            Select patient
        </option>


        <?php if ($patients->num_rows > 0): ?>


        <?php while (
            $patient = $patients->fetch_assoc()
        ): ?>


        <option
            value="<?= (int)$patient["id"] ?>"
        >

            <?= htmlspecialchars(
                $patient["patient_number"]
            ) ?>

            —

            <?= htmlspecialchars(
                $patient["full_name"]
            ) ?>


            <?php if (!empty($patient["phone"])): ?>

                —
                <?= htmlspecialchars(
                    $patient["phone"]
                ) ?>

            <?php endif; ?>


        </option>


        <?php endwhile; ?>


        <?php else: ?>


        <option value="" disabled>

            No patients registered

        </option>


        <?php endif; ?>


    </select>

</div>


<!-- DEPARTMENT -->

<div class="form-group">

    <label>
        Department
    </label>


    <select
        name="department_id"
        required
        id="departmentSelect"
    >

        <option value="">
            Select department
        </option>


        <?php if ($departments->num_rows > 0): ?>


        <?php while (
            $department =
            $departments->fetch_assoc()
        ): ?>


        <option
            value="<?= (int)$department["id"] ?>"
        >

            <?= htmlspecialchars(
                $department["name"]
            ) ?>

        </option>


        <?php endwhile; ?>


        <?php else: ?>


        <option value="" disabled>

            No active departments

        </option>


        <?php endif; ?>


    </select>

</div>


<!-- DOCTOR -->

<div class="form-group">

    <label>
        Assign Doctor
    </label>


    <select
        name="doctor_id"
        id="doctorSelect"
    >

        <option value="">
            Not assigned
        </option>


        <?php if ($doctors->num_rows > 0): ?>


        <?php while (
            $doctor =
            $doctors->fetch_assoc()
        ): ?>


        <option
            value="<?= (int)$doctor["id"] ?>"
            data-department="<?= (int)$doctor["department_id"] ?>"
        >

            <?= htmlspecialchars(
                $doctor["full_name"]
            ) ?>

            —

            <?= htmlspecialchars(
                $doctor["specialization"]
            ) ?>

            —

            <?= htmlspecialchars(
                $doctor["status"]
            ) ?>

        </option>


        <?php endwhile; ?>


        <?php endif; ?>


    </select>

</div>


<!-- PRIORITY -->

<div class="form-group">

    <label>
        Priority
    </label>


    <select
        name="priority"
        required
    >

        <option value="normal">
            Normal
        </option>

        <option value="urgent">
            Urgent
        </option>

        <option value="emergency">
            Emergency
        </option>

    </select>

</div>


<!-- NOTES -->

<div class="form-group">

    <label>
        Notes
    </label>


    <textarea
        name="notes"
        placeholder="Optional queue notes..."
    ></textarea>

</div>


<!-- ACTIONS -->

<div class="form-actions">

    <button
        type="button"
        class="modal-cancel"
        onclick="closeQueueModal()"
    >
        Cancel
    </button>


    <button
        type="submit"
        name="add_queue"
        class="save-btn"
    >
        Add To Queue
    </button>

</div>


</form>


</div>

</div>


<script>

/* =========================================================
   MODAL
========================================================= */

function openQueueModal() {

    document.getElementById(
        "queueModal"
    ).style.display = "flex";

}


function closeQueueModal() {

    document.getElementById(
        "queueModal"
    ).style.display = "none";

}


/* =========================================================
   CLOSE MODAL OUTSIDE
========================================================= */

window.onclick = function(event) {

    const modal =
        document.getElementById(
            "queueModal"
        );

    if (event.target === modal) {

        closeQueueModal();

    }

};


/* =========================================================
   FILTER DOCTORS BY DEPARTMENT
========================================================= */

const departmentSelect =
    document.getElementById(
        "departmentSelect"
    );


const doctorSelect =
    document.getElementById(
        "doctorSelect"
    );


departmentSelect.addEventListener(
    "change",
    function() {

        const departmentId =
            this.value;


        const options =
            doctorSelect.querySelectorAll(
                "option"
            );


        doctorSelect.value = "";


        options.forEach(
            function(option, index) {

                if (index === 0) {

                    option.style.display =
                        "";

                    return;
                }


                const doctorDepartment =
                    option.getAttribute(
                        "data-department"
                    );


                if (
                    departmentId !== ""
                    &&
                    doctorDepartment ===
                    departmentId
                ) {

                    option.style.display =
                        "";

                } else {

                    option.style.display =
                        "none";
                }

            }
        );

    }
);

</script>


</body>

</html>