```php
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


/* =========================================================
   MESSAGE VARIABLES
========================================================= */

$success_message = "";
$error_message = "";


/* =========================================================
   HANDLE CONSULTATION SAVE
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    $queue_id = (int) ($_POST["queue_id"] ?? 0);

    $diagnosis = trim($_POST["diagnosis"] ?? "");

    $treatment = trim($_POST["treatment"] ?? "");

    $notes = trim($_POST["notes"] ?? "");

    /*
     * Consultation fee
     *
     * Stored as DECIMAL(12,2) in database.
     */
    $consultation_fee = isset($_POST["consultation_fee"])
        ? (float) $_POST["consultation_fee"]
        : 0.00;


    /* =====================================================
       VALIDATE CONSULTATION FEE
    ===================================================== */

    if ($consultation_fee < 0) {
        $consultation_fee = 0.00;
    }

    if ($consultation_fee > 9999999999.99) {
        $error_message =
            "Consultation fee is too high.";
    }


    /* =====================================================
       SAVE CONSULTATION
    ===================================================== */

    if (
        $action === "save_consultation"
        && $error_message === ""
    ) {

        if ($queue_id <= 0) {

            $error_message = "Invalid queue record.";

        } else {

            /*
             * Make sure this queue belongs
             * to the logged-in doctor.
             */

            $stmt = $conn->prepare("
                SELECT
                    id,
                    patient_id,
                    department_id,
                    status
                FROM queue
                WHERE id = ?
                AND doctor_id = ?
                AND department_id = ?
                LIMIT 1
            ");

            $stmt->bind_param(
                "iii",
                $queue_id,
                $doctor_id,
                $department_id
            );

            $stmt->execute();

            $queue_result = $stmt->get_result();

            if ($queue_result->num_rows !== 1) {

                $error_message =
                    "This patient is not assigned to you.";

            } else {

                $queue = $queue_result->fetch_assoc();

                $patient_id = (int) $queue["patient_id"];

                $queue_status = $queue["status"];

                if (
                    $queue_status !== "serving" &&
                    $queue_status !== "called"
                ) {

                    $error_message =
                        "This patient is not currently available for consultation.";

                } else {

                    /*
                     * Check if consultation already exists.
                     */

                    $check = $conn->prepare("
                        SELECT id
                        FROM consultations
                        WHERE queue_id = ?
                        AND doctor_id = ?
                        LIMIT 1
                    ");

                    $check->bind_param(
                        "ii",
                        $queue_id,
                        $doctor_id
                    );

                    $check->execute();

                    $existing = $check->get_result();

                    if ($existing->num_rows > 0) {

                        $consultation =
                            $existing->fetch_assoc();

                        $consultation_id =
                            (int) $consultation["id"];


                        /*
                         * Update existing consultation.
                         */

                        $update = $conn->prepare("
                            UPDATE consultations
                            SET
                                consultation_fee = ?,
                                diagnosis = ?,
                                treatment = ?,
                                notes = ?,
                                status = 'draft'
                            WHERE id = ?
                            AND doctor_id = ?
                        ");

                        $update->bind_param(
                            "dsssii",
                            $consultation_fee,
                            $diagnosis,
                            $treatment,
                            $notes,
                            $consultation_id,
                            $doctor_id
                        );

                        if ($update->execute()) {

                            $success_message =
                                "Consultation saved successfully.";

                        } else {

                            $error_message =
                                "Unable to save consultation.";

                        }

                        $update->close();

                    } else {

                        /*
                         * Create new consultation.
                         */

                        $insert = $conn->prepare("
                            INSERT INTO consultations
                            (
                                queue_id,
                                patient_id,
                                doctor_id,
                                department_id,
                                consultation_fee,
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
                                ?,
                                'draft',
                                NOW()
                            )
                        ");

                        $insert->bind_param(
                            "iiiidsss",
                            $queue_id,
                            $patient_id,
                            $doctor_id,
                            $department_id,
                            $consultation_fee,
                            $diagnosis,
                            $treatment,
                            $notes
                        );

                        if ($insert->execute()) {

                            $success_message =
                                "Consultation saved successfully.";

                        } else {

                            $error_message =
                                "Unable to save consultation.";

                        }

                        $insert->close();
                    }

                    $check->close();
                }
            }

            $stmt->close();
        }
    }


    /* =====================================================
       COMPLETE CONSULTATION
    ===================================================== */

    if (
        $action === "complete_consultation"
        && $error_message === ""
    ) {

        if ($queue_id <= 0) {

            $error_message =
                "Invalid queue record.";

        } else {

            /*
             * Verify active queue.
             */

            $stmt = $conn->prepare("
                SELECT
                    id,
                    patient_id
                FROM queue
                WHERE id = ?
                AND doctor_id = ?
                AND department_id = ?
                AND status = 'serving'
                LIMIT 1
            ");

            $stmt->bind_param(
                "iii",
                $queue_id,
                $doctor_id,
                $department_id
            );

            $stmt->execute();

            $queue_result = $stmt->get_result();

            if ($queue_result->num_rows !== 1) {

                $error_message =
                    "This patient is not currently being served.";

            } else {

                $queue = $queue_result->fetch_assoc();

                $patient_id =
                    (int) $queue["patient_id"];


                /*
                 * Check consultation.
                 */

                $check = $conn->prepare("
                    SELECT id
                    FROM consultations
                    WHERE queue_id = ?
                    AND doctor_id = ?
                    LIMIT 1
                ");

                $check->bind_param(
                    "ii",
                    $queue_id,
                    $doctor_id
                );

                $check->execute();

                $consultation_result =
                    $check->get_result();


                if ($consultation_result->num_rows === 1) {

                    $consultation =
                        $consultation_result->fetch_assoc();

                    $consultation_id =
                        (int) $consultation["id"];


                    /*
                     * Complete consultation.
                     */

                    $update = $conn->prepare("
                        UPDATE consultations
                        SET
                            consultation_fee = ?,
                            diagnosis = ?,
                            treatment = ?,
                            notes = ?,
                            status = 'completed',
                            completed_at = NOW()
                        WHERE id = ?
                        AND doctor_id = ?
                    ");

                    $update->bind_param(
                        "dsssii",
                        $consultation_fee,
                        $diagnosis,
                        $treatment,
                        $notes,
                        $consultation_id,
                        $doctor_id
                    );

                    if (!$update->execute()) {

                        $error_message =
                            "Unable to complete consultation.";

                    }

                    $update->close();

                } else {

                    /*
                     * Create completed consultation
                     * if one does not already exist.
                     */

                    $insert = $conn->prepare("
                        INSERT INTO consultations
                        (
                            queue_id,
                            patient_id,
                            doctor_id,
                            department_id,
                            consultation_fee,
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
                            ?,
                            'completed',
                            NOW(),
                            NOW()
                        )
                    ");

                    $insert->bind_param(
                        "iiiidsss",
                        $queue_id,
                        $patient_id,
                        $doctor_id,
                        $department_id,
                        $consultation_fee,
                        $diagnosis,
                        $treatment,
                        $notes
                    );

                    if (!$insert->execute()) {

                        $error_message =
                            "Unable to create completed consultation.";

                    }

                    $insert->close();
                }


                /*
                 * Complete queue only if consultation
                 * was successfully saved.
                 */

                if ($error_message === "") {

                    $queue_update = $conn->prepare("
                        UPDATE queue
                        SET
                            status = 'completed',
                            completed_time = NOW(),
                            notes = ?
                        WHERE id = ?
                        AND doctor_id = ?
                        AND status = 'serving'
                    ");

                    $queue_update->bind_param(
                        "sii",
                        $notes,
                        $queue_id,
                        $doctor_id
                    );

                    if ($queue_update->execute()) {

                        $success_message =
                            "Consultation completed successfully.";

                    } else {

                        $error_message =
                            "Consultation saved, but queue could not be completed.";

                    }

                    $queue_update->close();
                }
            }

            $stmt->close();
        }
    }
}


/* =========================================================
   GET ACTIVE PATIENTS
========================================================= */

$stmt = $conn->prepare("
    SELECT

        q.id AS queue_id,

        q.queue_number,

        q.status AS queue_status,

        q.priority,

        q.check_in_time,

        q.called_time,

        p.id AS patient_id,

        p.patient_number,

        p.full_name AS patient_name,

        p.gender,

        p.date_of_birth,

        c.id AS consultation_id,

        c.consultation_fee,

        c.diagnosis,

        c.treatment,

        c.notes AS consultation_notes,

        c.status AS consultation_status,

        c.started_at,

        c.completed_at

    FROM queue q

    INNER JOIN patients p
        ON q.patient_id = p.id

    LEFT JOIN consultations c
        ON c.queue_id = q.id
        AND c.doctor_id = ?

    WHERE q.doctor_id = ?

    AND q.department_id = ?

    AND q.status IN ('called', 'serving')

    ORDER BY

        CASE q.status

            WHEN 'serving' THEN 1

            WHEN 'called' THEN 2

            ELSE 3

        END,

        q.check_in_time ASC
");

$stmt->bind_param(
    "iii",
    $doctor_id,
    $doctor_id,
    $department_id
);

$stmt->execute();

$patients_result = $stmt->get_result();


/* =========================================================
   STATISTICS
========================================================= */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM consultations
    WHERE doctor_id = ?
    AND status = 'completed'
    AND DATE(completed_at) = CURDATE()
");

$stmt->bind_param("i", $doctor_id);

$stmt->execute();

$completed_today =
    $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();


$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM consultations
    WHERE doctor_id = ?
    AND status = 'draft'
");

$stmt->bind_param("i", $doctor_id);

$stmt->execute();

$draft_count =
    $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();

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
    Consultations | SmartHospital
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

body {

    font-family:
        "Segoe UI",
        Arial,
        sans-serif;

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
   HEADER
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
        repeat(2, 1fr);

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
   CONSULTATION CARD
========================================================= */

.consultation-card {

    background: white;

    border:
        1px solid #e8eef1;

    border-radius: 14px;

    padding: 24px;

    margin-bottom: 20px;
}

.patient-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 22px;

    padding-bottom: 18px;

    border-bottom:
        1px solid #edf1f3;
}

.patient-name {

    font-size: 17px;

    font-weight: 700;
}

.patient-number {

    color: #8a99a3;

    font-size: 11px;

    margin-top: 4px;
}

.patient-meta {

    color: #71818c;

    font-size: 12px;

    margin-top: 6px;
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
   FORM
========================================================= */

.form-grid {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 18px;
}

.form-group {

    display: flex;

    flex-direction: column;

    gap: 7px;
}

.form-group.full {

    grid-column:
        1 / -1;
}

.form-group label {

    font-size: 12px;

    font-weight: 600;

    color: #536774;
}

.form-group textarea {

    width: 100%;

    min-height: 110px;

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


/* =========================================================
   FEE INPUT
========================================================= */

.fee-group {

    grid-column:
        1 / -1;
}

.fee-input-wrapper {

    position: relative;

    width: 100%;
}

.fee-input-wrapper span {

    position: absolute;

    left: 13px;

    top: 50%;

    transform: translateY(-50%);

    color: #087f8c;

    font-size: 13px;

    font-weight: 700;

    pointer-events: none;
}

.fee-input {

    width: 100%;

    border:
        1px solid #dce6ea;

    border-radius: 8px;

    padding: 12px 12px 12px 70px;

    font-family: inherit;

    font-size: 14px;

    font-weight: 600;

    outline: none;
}

.fee-input:focus {

    border-color: #087f8c;
}

.fee-help {

    color: #81909b;

    font-size: 10px;

    margin-top: 3px;
}


.form-actions {

    margin-top: 20px;

    display: flex;

    gap: 10px;

    flex-wrap: wrap;
}

.btn {

    border: none;

    border-radius: 7px;

    padding: 10px 15px;

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
   ALERTS
========================================================= */

.alert {

    padding: 13px 16px;

    border-radius: 8px;

    margin-bottom: 20px;

    font-size: 12px;

    font-weight: 600;
}

.alert-success {

    background: #e6f7f0;

    color: #16734b;
}

.alert-error {

    background: #ffe9e9;

    color: #b42318;
}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    background: white;

    border:
        1px solid #e8eef1;

    border-radius: 14px;

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

@media (max-width: 800px) {

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

    .form-grid {

        grid-template-columns: 1fr;
    }

    .form-group.full,
    .fee-group {

        grid-column: auto;
    }

    .stats {

        grid-template-columns: 1fr;
    }

    .topbar {

        padding: 0 18px;
    }

    .content {

        padding: 18px;
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

        <a href="doctor_dashboard.php">
            Dashboard
        </a>

        <a href="doctor_queue.php">
            My Queue
        </a>

        <a href="doctor_patients.php">
            Patients
        </a>

        <a
            href="doctor_consultations.php"
            class="active"
        >
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


    <header class="topbar">

        <div>

            <h1>
                Consultations
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


        <div class="page-header">

            <h2>
                Patient Consultations
            </h2>

            <p>

                Manage consultations for

                <span class="department">

                    <?= e(
                        $doctor["department_name"]
                    ) ?>

                </span>

            </p>

        </div>


        <?php if ($success_message !== ""): ?>

            <div class="alert alert-success">

                <?= e($success_message) ?>

            </div>

        <?php endif; ?>


        <?php if ($error_message !== ""): ?>

            <div class="alert alert-error">

                <?= e($error_message) ?>

            </div>

        <?php endif; ?>


        <div class="stats">


            <div class="stat">

                <div class="stat-label">
                    Draft Consultations
                </div>

                <div class="stat-value">
                    <?= $draft_count ?>
                </div>

                <div class="stat-info">
                    Active consultations
                </div>

            </div>


            <div class="stat">

                <div class="stat-label">
                    Completed Today
                </div>

                <div class="stat-value">
                    <?= $completed_today ?>
                </div>

                <div class="stat-info">
                    Today's consultations
                </div>

            </div>


        </div>


        <?php if ($patients_result->num_rows > 0): ?>


            <?php while (
                $patient =
                    $patients_result->fetch_assoc()
            ): ?>


                <div class="consultation-card">


                    <div class="patient-header">


                        <div>

                            <div class="patient-name">

                                <?= e(
                                    $patient["patient_name"]
                                ) ?>

                            </div>


                            <div class="patient-number">

                                Patient No:

                                <?= e(
                                    $patient["patient_number"]
                                ) ?>

                                · Queue:

                                <?= e(
                                    $patient["queue_number"]
                                ) ?>

                            </div>


                            <div class="patient-meta">

                                <?= e(
                                    $patient["gender"]
                                ) ?>

                                <?php if (
                                    !empty(
                                        $patient["date_of_birth"]
                                    )
                                ): ?>

                                    · DOB:

                                    <?= e(
                                        $patient["date_of_birth"]
                                    ) ?>

                                <?php endif; ?>

                            </div>

                        </div>


                        <div>

                            <span
                                class="badge <?= e(
                                    $patient["queue_status"]
                                ) ?>"
                            >

                                <?= ucfirst(
                                    e(
                                        $patient["queue_status"]
                                    )
                                ) ?>

                            </span>


                            <br>


                            <span
                                class="badge <?= e(
                                    $patient["priority"]
                                ) ?>"
                            >

                                <?= ucfirst(
                                    e(
                                        $patient["priority"]
                                    )
                                ) ?>

                            </span>

                        </div>


                    </div>


                    <form method="POST">


                        <input
                            type="hidden"
                            name="queue_id"
                            value="<?= (int) $patient["queue_id"] ?>"
                        >


                        <div class="form-grid">


                            <!-- =================================================
                                 CONSULTATION FEE
                            ================================================== -->

                            <div class="form-group fee-group">

                                <label>
                                    Consultation Fee
                                </label>

                                <div class="fee-input-wrapper">

                                    <span>
                                        RWF
                                    </span>

                                    <input
                                        type="number"
                                        name="consultation_fee"
                                        class="fee-input"
                                        min="0"
                                        max="9999999999.99"
                                        step="0.01"
                                        value="<?= e(
                                            $patient["consultation_fee"] ?? "0.00"
                                        ) ?>"
                                        placeholder="Enter consultation fee"
                                        required
                                    >

                                </div>

                                <div class="fee-help">
                                    Enter the consultation charge in Rwandan Francs.
                                </div>

                            </div>


                            <div class="form-group full">

                                <label>
                                    Diagnosis
                                </label>

                                <textarea
                                    name="diagnosis"
                                    placeholder="Enter patient diagnosis..."
                                ><?= e(
                                    $patient["diagnosis"] ?? ""
                                ) ?></textarea>

                            </div>


                            <div class="form-group">

                                <label>
                                    Treatment
                                </label>

                                <textarea
                                    name="treatment"
                                    placeholder="Enter treatment plan..."
                                ><?= e(
                                    $patient["treatment"] ?? ""
                                ) ?></textarea>

                            </div>


                            <div class="form-group">

                                <label>
                                    Notes
                                </label>

                                <textarea
                                    name="notes"
                                    placeholder="Enter consultation notes..."
                                ><?= e(
                                    $patient["consultation_notes"] ?? ""
                                ) ?></textarea>

                            </div>


                        </div>


                        <div class="form-actions">


                            <button
                                type="submit"
                                name="action"
                                value="save_consultation"
                                class="btn btn-save"
                            >

                                Save Consultation

                            </button>


                            <?php if (
                                $patient["queue_status"]
                                === "serving"
                            ): ?>


                                <button
                                    type="submit"
                                    name="action"
                                    value="complete_consultation"
                                    class="btn btn-complete"
                                    onclick="
                                        return confirm(
                                            'Complete this consultation and patient?'
                                        );
                                    "
                                >

                                    Complete Patient

                                </button>


                            <?php endif; ?>


                        </div>


                    </form>


                </div>


            <?php endwhile; ?>


        <?php else: ?>


            <div class="empty">

                <div class="empty-title">

                    No Active Consultations

                </div>

                There are currently no patients
                being served or called by you.

            </div>


        <?php endif; ?>


    </section>


</main>


</body>

</html>

