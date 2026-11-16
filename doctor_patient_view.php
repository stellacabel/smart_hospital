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

if (!$stmt) {
    die("Doctor profile query failed: " . $conn->error);
}

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
   GET PATIENT ID
========================================================= */

$patient_id = (int) ($_GET["id"] ?? 0);

if ($patient_id <= 0) {
    header("Location: doctor_patients.php");
    exit;
}


/* =========================================================
   GET PATIENT
========================================================= */

$stmt = $conn->prepare("
    SELECT DISTINCT
        p.id,
        p.patient_number,
        p.full_name,
        p.gender,
        p.date_of_birth,
        p.phone,
        p.email,
        p.address,
        p.insurance_provider,
        p.emergency_contact,
        p.emergency_phone

    FROM patients p

    INNER JOIN queue q
        ON q.patient_id = p.id

    WHERE p.id = ?
    AND q.department_id = ?

    LIMIT 1
");

if (!$stmt) {
    die("Patient query failed: " . $conn->error);
}

$stmt->bind_param(
    "ii",
    $patient_id,
    $department_id
);

$stmt->execute();

$patient_result = $stmt->get_result();

if ($patient_result->num_rows !== 1) {
    die("Patient not found or patient does not belong to your department.");
}

$patient = $patient_result->fetch_assoc();

$stmt->close();


/* =========================================================
   GET CURRENT QUEUE RECORD
========================================================= */

$stmt = $conn->prepare("
    SELECT
        q.id,
        q.queue_number,
        q.status,
        q.priority,
        q.check_in_time,
        q.called_time,
        q.completed_time,
        q.notes,
        q.doctor_id

    FROM queue q

    WHERE q.patient_id = ?
    AND q.department_id = ?

    AND (
        q.doctor_id = ?
        OR q.doctor_id IS NULL
    )

    ORDER BY
        CASE
            WHEN q.status = 'serving' THEN 1
            WHEN q.status = 'called' THEN 2
            WHEN q.status = 'waiting' THEN 3
            ELSE 4
        END,
        q.id DESC

    LIMIT 1
");

if (!$stmt) {
    die("Queue query failed: " . $conn->error);
}

$stmt->bind_param(
    "iii",
    $patient_id,
    $department_id,
    $doctor_id
);

$stmt->execute();

$queue_result = $stmt->get_result();

$queue = $queue_result->num_rows > 0
    ? $queue_result->fetch_assoc()
    : null;

$stmt->close();


/* =========================================================
   MESSAGE
========================================================= */

$message = "";
$message_type = "";


/* =========================================================
   SAVE CONSULTATION
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["save_consultation"])
) {

    $diagnosis = trim($_POST["diagnosis"] ?? "");
    $treatment = trim($_POST["treatment"] ?? "");
    $notes = trim($_POST["notes"] ?? "");


    if ($diagnosis === "") {

        $message = "Please enter the diagnosis.";
        $message_type = "error";

    } elseif (!$queue) {

        $message = "No queue record was found for this patient.";
        $message_type = "error";

    } else {

        $queue_id = (int) $queue["id"];


        /*
         * Insert consultation using the
         * actual consultations table structure.
         */

        $stmt = $conn->prepare("
            INSERT INTO consultations
            (
                queue_id,
                patient_id,
                doctor_id,
                department_id,
                diagnosis,
                treatment,
                notes,
                status,
                started_at
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                'draft',
                NOW()
            )
        ");


        if (!$stmt) {

            $message =
                "Unable to prepare consultation: "
                . $conn->error;

            $message_type = "error";

        } else {

            $stmt->bind_param(
                "iiiisss",
                $queue_id,
                $patient_id,
                $doctor_id,
                $department_id,
                $diagnosis,
                $treatment,
                $notes
            );


            if ($stmt->execute()) {

                $message =
                    "Consultation saved successfully.";

                $message_type = "success";

            } else {

                $message =
                    "Unable to save consultation: "
                    . $stmt->error;

                $message_type = "error";
            }


            $stmt->close();
        }
    }
}


/* =========================================================
   COMPLETE PATIENT
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["complete_patient"])
) {

    $diagnosis = trim($_POST["diagnosis"] ?? "");
    $treatment = trim($_POST["treatment"] ?? "");
    $notes = trim($_POST["notes"] ?? "");


    if ($diagnosis === "") {

        $message =
            "Please enter the diagnosis before completing the patient.";

        $message_type = "error";

    } elseif (!$queue) {

        $message =
            "No queue record was found for this patient.";

        $message_type = "error";

    } elseif (
        !in_array(
            $queue["status"],
            ["called", "serving"],
            true
        )
    ) {

        $message =
            "This patient cannot be completed from the current queue status.";

        $message_type = "error";

    } else {

        $queue_id = (int) $queue["id"];


        /*
         * Save completed consultation.
         */

        $stmt = $conn->prepare("
            INSERT INTO consultations
            (
                queue_id,
                patient_id,
                doctor_id,
                department_id,
                diagnosis,
                treatment,
                notes,
                status,
                started_at,
                completed_at
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                'completed',
                NOW(),
                NOW()
            )
        ");


        if (!$stmt) {

            $message =
                "Unable to prepare consultation: "
                . $conn->error;

            $message_type = "error";

        } else {

            $stmt->bind_param(
                "iiiisss",
                $queue_id,
                $patient_id,
                $doctor_id,
                $department_id,
                $diagnosis,
                $treatment,
                $notes
            );


            if ($stmt->execute()) {

                $stmt->close();


                /*
                 * Complete queue.
                 */

                $stmt = $conn->prepare("
                    UPDATE queue

                    SET
                        status = 'completed',
                        doctor_id = ?,
                        completed_time = NOW(),
                        notes = ?

                    WHERE id = ?

                    AND doctor_id = ?

                    AND status IN ('called', 'serving')
                ");


                if (!$stmt) {

                    $message =
                        "Consultation saved, but queue update failed: "
                        . $conn->error;

                    $message_type = "error";

                } else {

                    $stmt->bind_param(
                        "isii",
                        $doctor_id,
                        $notes,
                        $queue_id,
                        $doctor_id
                    );


                    if ($stmt->execute()) {

                        $message =
                            "Patient consultation completed successfully.";

                        $message_type = "success";

                        $queue["status"] = "completed";

                    } else {

                        $message =
                            "Consultation saved, but patient could not be completed: "
                            . $stmt->error;

                        $message_type = "error";
                    }


                    $stmt->close();
                }

            } else {

                $message =
                    "Unable to save consultation: "
                    . $stmt->error;

                $message_type = "error";

                $stmt->close();
            }
        }
    }
}


/* =========================================================
   HELPER
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
    Patient Details | SmartHospital
</title>


<style>

/* =========================================================
   GLOBAL
========================================================= */

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


/* =========================================================
   CONTENT
========================================================= */

.content {

    padding: 30px;
}


/* =========================================================
   BACK
========================================================= */

.back {

    display: inline-block;

    color: #087f8c;

    font-size: 13px;

    font-weight: 600;

    margin-bottom: 18px;
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
   PATIENT HEADER
========================================================= */

.patient-header {

    background: white;

    border:
        1px solid #e8eef1;

    border-radius: 14px;

    padding: 25px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 20px;
}

.patient-title h2 {

    font-size: 24px;

    margin-bottom: 6px;
}

.patient-number {

    color: #087f8c;

    font-size: 12px;

    font-weight: 600;
}

.patient-meta {

    color: #7d8c97;

    font-size: 12px;

    margin-top: 7px;
}


/* =========================================================
   STATUS BADGES
========================================================= */

.badge {

    display: inline-block;

    padding: 7px 11px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: 600;

    text-transform: capitalize;

    margin-left: 5px;
}

.badge.waiting {

    background: #fff5df;

    color: #9a6700;
}

.badge.called {

    background: #eaf4ff;

    color: #1769aa;
}

.badge.serving {

    background: #e6f7f0;

    color: #16734b;
}

.badge.completed {

    background: #eef2f5;

    color: #667783;
}

.badge.emergency {

    background: #ffe9e9;

    color: #b42318;
}

.badge.urgent {

    background: #fff1df;

    color: #b15c00;
}

.badge.normal {

    background: #eef2f5;

    color: #667783;
}


/* =========================================================
   GRID
========================================================= */

.grid {

    display: grid;

    grid-template-columns: 1fr 1.5fr;

    gap: 20px;
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

    margin-bottom: 20px;
}

.card h3 {

    font-size: 16px;

    margin-bottom: 20px;
}


/* =========================================================
   PATIENT INFORMATION
========================================================= */

.info-row {

    display: flex;

    justify-content: space-between;

    gap: 15px;

    padding: 12px 0;

    border-bottom:
        1px solid #edf1f3;
}

.info-row:last-child {

    border-bottom: none;
}

.info-label {

    color: #84929d;

    font-size: 12px;
}

.info-value {

    text-align: right;

    font-size: 12px;

    font-weight: 600;

    word-break: break-word;
}


/* =========================================================
   CONSULTATION FORM
========================================================= */

.form-group {

    margin-bottom: 17px;
}

.form-group label {

    display: block;

    font-size: 12px;

    font-weight: 600;

    margin-bottom: 7px;
}

.form-group textarea {

    width: 100%;

    min-height: 100px;

    resize: vertical;

    border:
        1px solid #dce6ea;

    border-radius: 8px;

    padding: 12px;

    font-family: inherit;

    font-size: 13px;

    outline: none;
}

.form-group textarea:focus {

    border-color: #087f8c;
}

.actions {

    display: flex;

    gap: 10px;

    margin-top: 20px;
}

.btn {

    border: none;

    border-radius: 8px;

    padding: 11px 17px;

    font-family: inherit;

    font-size: 12px;

    font-weight: 600;

    cursor: pointer;
}

.btn-save {

    background: #087f8c;

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
   QUEUE
========================================================= */

.queue-number {

    font-size: 25px;

    font-weight: 700;

    color: #087f8c;

    margin-bottom: 10px;
}

.queue-info {

    display: flex;

    gap: 5px;

    flex-wrap: wrap;

    margin-bottom: 15px;
}

.patient-muted {

    color: #8a99a3;

    font-size: 12px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 900px) {

    .grid {

        grid-template-columns: 1fr;
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

    .patient-header {

        flex-direction: column;

        align-items: flex-start;

        gap: 15px;
    }

    .content {

        padding: 18px;
    }

    .actions {

        flex-direction: column;
    }

    .btn {

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

        <a href="doctor_dashboard.php">
            Dashboard
        </a>

        <a href="doctor_queue.php">
            My Queue
        </a>

        <a
            href="doctor_patients.php"
            class="active"
        >
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


<!-- =========================================================
     MAIN
========================================================= -->

<main class="main">


<header class="topbar">

    <div>

        <h1>
            Patient Details
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


<section class="content">


<a
    href="doctor_patients.php"
    class="back"
>
    ← Back to Patients
</a>


<?php if ($message !== ""): ?>

    <div class="message <?= e($message_type) ?>">

        <?= e($message) ?>

    </div>

<?php endif; ?>


<!-- =========================================================
     PATIENT HEADER
========================================================= -->

<div class="patient-header">


    <div class="patient-title">

        <h2>
            <?= e($patient["full_name"]) ?>
        </h2>


        <div class="patient-number">

            Patient No:
            <?= e($patient["patient_number"]) ?>


            <?php if ($queue): ?>

                · Queue:
                <?= e($queue["queue_number"]) ?>

            <?php endif; ?>

        </div>


        <div class="patient-meta">

            <?= !empty($patient["gender"])
                ? e($patient["gender"])
                : "Gender not provided"
            ?>

            ·

            <?= !empty($patient["date_of_birth"])
                ? date(
                    "d M Y",
                    strtotime($patient["date_of_birth"])
                )
                : "DOB not provided"
            ?>

        </div>

    </div>


    <div>

        <?php if ($queue): ?>

            <span class="badge <?= e($queue["status"]) ?>">

                <?= ucfirst(
                    e($queue["status"])
                ) ?>

            </span>


            <span class="badge <?= e($queue["priority"]) ?>">

                <?= ucfirst(
                    e($queue["priority"])
                ) ?>

            </span>

        <?php endif; ?>

    </div>

</div>


<div class="grid">


<!-- =========================================================
     LEFT COLUMN
========================================================= -->

<div>


    <!-- PATIENT INFORMATION -->

    <div class="card">

        <h3>
            Patient Information
        </h3>


        <div class="info-row">

            <span class="info-label">
                Full Name
            </span>

            <span class="info-value">
                <?= e($patient["full_name"]) ?>
            </span>

        </div>


        <div class="info-row">

            <span class="info-label">
                Patient Number
            </span>

            <span class="info-value">
                <?= e($patient["patient_number"]) ?>
            </span>

        </div>


        <div class="info-row">

            <span class="info-label">
                Gender
            </span>

            <span class="info-value">

                <?= !empty($patient["gender"])
                    ? e($patient["gender"])
                    : "Not provided"
                ?>

            </span>

        </div>


        <div class="info-row">

            <span class="info-label">
                Date of Birth
            </span>

            <span class="info-value">

                <?= !empty($patient["date_of_birth"])
                    ? date(
                        "d M Y",
                        strtotime($patient["date_of_birth"])
                    )
                    : "Not provided"
                ?>

            </span>

        </div>


        <div class="info-row">

            <span class="info-label">
                Phone
            </span>

            <span class="info-value">

                <?= !empty($patient["phone"])
                    ? e($patient["phone"])
                    : "Not provided"
                ?>

            </span>

        </div>


        <div class="info-row">

            <span class="info-label">
                Email
            </span>

            <span class="info-value">

                <?= !empty($patient["email"])
                    ? e($patient["email"])
                    : "Not provided"
                ?>

            </span>

        </div>


        <div class="info-row">

            <span class="info-label">
                Address
            </span>

            <span class="info-value">

                <?= !empty($patient["address"])
                    ? e($patient["address"])
                    : "Not provided"
                ?>

            </span>

        </div>


        <div class="info-row">

            <span class="info-label">
                Insurance
            </span>

            <span class="info-value">

                <?= !empty($patient["insurance_provider"])
                    ? e($patient["insurance_provider"])
                    : "Not provided"
                ?>

            </span>

        </div>

    </div>


    <!-- EMERGENCY CONTACT -->

    <div class="card">

        <h3>
            Emergency Contact
        </h3>


        <div class="info-row">

            <span class="info-label">
                Contact
            </span>

            <span class="info-value">

                <?= !empty($patient["emergency_contact"])
                    ? e($patient["emergency_contact"])
                    : "Not provided"
                ?>

            </span>

        </div>


        <div class="info-row">

            <span class="info-label">
                Phone
            </span>

            <span class="info-value">

                <?= !empty($patient["emergency_phone"])
                    ? e($patient["emergency_phone"])
                    : "Not provided"
                ?>

            </span>

        </div>

    </div>


</div>


<!-- =========================================================
     RIGHT COLUMN
========================================================= -->

<div>


    <!-- QUEUE INFORMATION -->

    <div class="card">

        <h3>
            Queue Information
        </h3>


        <?php if ($queue): ?>

            <div class="queue-number">

                <?= e(
                    $queue["queue_number"]
                ) ?>

            </div>


            <div class="queue-info">

                <span class="badge <?= e(
                    $queue["status"]
                ) ?>">

                    <?= ucfirst(
                        e($queue["status"])
                    ) ?>

                </span>


                <span class="badge <?= e(
                    $queue["priority"]
                ) ?>">

                    <?= ucfirst(
                        e($queue["priority"])
                    ) ?>

                </span>

            </div>


            <div class="info-row">

                <span class="info-label">
                    Check-in
                </span>

                <span class="info-value">

                    <?= !empty($queue["check_in_time"])
                        ? date(
                            "d M Y H:i",
                            strtotime(
                                $queue["check_in_time"]
                            )
                        )
                        : "-"
                    ?>

                </span>

            </div>


            <div class="info-row">

                <span class="info-label">
                    Called
                </span>

                <span class="info-value">

                    <?= !empty($queue["called_time"])
                        ? date(
                            "H:i",
                            strtotime(
                                $queue["called_time"]
                            )
                        )
                        : "-"
                    ?>

                </span>

            </div>


            <?php if (!empty($queue["completed_time"])): ?>

                <div class="info-row">

                    <span class="info-label">
                        Completed
                    </span>

                    <span class="info-value">

                        <?= date(
                            "d M Y H:i",
                            strtotime(
                                $queue["completed_time"]
                            )
                        ) ?>

                    </span>

                </div>

            <?php endif; ?>


        <?php else: ?>

            <p class="patient-muted">
                No queue record found.
            </p>

        <?php endif; ?>

    </div>


    <!-- =====================================================
         CONSULTATION
    ====================================================== -->

    <div class="card">

        <h3>
            Consultation
        </h3>


        <?php if (
            $queue
            && in_array(
                $queue["status"],
                ["called", "serving"],
                true
            )
        ): ?>


            <form method="POST">


                <!-- DIAGNOSIS -->

                <div class="form-group">

                    <label>
                        Diagnosis
                    </label>

                    <textarea
                        name="diagnosis"
                        placeholder="Enter the patient's diagnosis..."
                        required
                    ></textarea>

                </div>


                <!-- TREATMENT -->

                <div class="form-group">

                    <label>
                        Treatment
                    </label>

                    <textarea
                        name="treatment"
                        placeholder="Enter prescribed treatment, medication or recommendations..."
                    ></textarea>

                </div>


                <!-- NOTES -->

                <div class="form-group">

                    <label>
                        Notes
                    </label>

                    <textarea
                        name="notes"
                        placeholder="Additional consultation notes..."
                    ></textarea>

                </div>


                <!-- ACTIONS -->

                <div class="actions">


                    <button
                        type="submit"
                        name="save_consultation"
                        class="btn btn-save"
                    >
                        Save Consultation
                    </button>


                    <button
                        type="submit"
                        name="complete_patient"
                        class="btn btn-complete"
                        onclick="
                            return confirm(
                                'Save consultation and complete this patient?'
                            );
                        "
                    >
                        Complete Patient
                    </button>


                </div>


            </form>


        <?php elseif ($queue && $queue["status"] === "completed"): ?>


            <div class="message success">

                This patient's consultation has already been completed.

            </div>


        <?php elseif ($queue && $queue["status"] === "waiting"): ?>


            <div class="message">

                This patient is currently waiting in the queue.

                The consultation form will become available
                when the patient is called or being served.

            </div>


        <?php else: ?>


            <div class="message">

                No active consultation is available for this patient.

            </div>


        <?php endif; ?>

    </div>


</div>


</div>


</section>


</main>


</body>

</html>