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
   SEARCH
========================================================= */

$search = trim($_GET["search"] ?? "");


/* =========================================================
   PATIENT STATISTICS
========================================================= */

/*
   Total patients registered in the doctor's department
   through queue records.
*/

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT q.patient_id) AS total
    FROM queue q
    WHERE q.department_id = ?
");

$stmt->bind_param("i", $department_id);
$stmt->execute();

$total_patients =
    $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();


/*
   Patients currently waiting.
*/

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT q.patient_id) AS total
    FROM queue q
    WHERE q.department_id = ?
    AND q.status = 'waiting'
");

$stmt->bind_param("i", $department_id);
$stmt->execute();

$waiting_patients =
    $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();


/*
   Patients currently being served by this doctor.
*/

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT q.patient_id) AS total
    FROM queue q
    WHERE q.doctor_id = ?
    AND q.status = 'serving'
");

$stmt->bind_param("i", $doctor_id);
$stmt->execute();

$serving_patients =
    $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();


/*
   Completed consultations today.
*/

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT q.patient_id) AS total
    FROM queue q
    WHERE q.doctor_id = ?
    AND q.status = 'completed'
    AND DATE(q.completed_time) = CURDATE()
");

$stmt->bind_param("i", $doctor_id);
$stmt->execute();

$completed_today =
    $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();


/* =========================================================
   GET PATIENTS
========================================================= */

if ($search !== "") {

    $search_value = "%" . $search . "%";

    $stmt = $conn->prepare("
        SELECT DISTINCT
            p.id,
            p.patient_number,
            p.full_name,
            p.date_of_birth,
            p.gender,
            p.phone,
            p.email,
            p.address,
            p.insurance_provider,
            p.emergency_contact,
            p.emergency_phone

        FROM patients p

        INNER JOIN queue q
            ON q.patient_id = p.id

        WHERE q.department_id = ?

        AND (
            p.full_name LIKE ?
            OR p.patient_number LIKE ?
            OR p.phone LIKE ?
            OR p.email LIKE ?
        )

        ORDER BY p.full_name ASC
    ");

    $stmt->bind_param(
        "issss",
        $department_id,
        $search_value,
        $search_value,
        $search_value,
        $search_value
    );

} else {

    $stmt = $conn->prepare("
        SELECT DISTINCT
            p.id,
            p.patient_number,
            p.full_name,
            p.date_of_birth,
            p.gender,
            p.phone,
            p.email,
            p.address,
            p.insurance_provider,
            p.emergency_contact,
            p.emergency_phone

        FROM patients p

        INNER JOIN queue q
            ON q.patient_id = p.id

        WHERE q.department_id = ?

        ORDER BY p.full_name ASC
    ");

    $stmt->bind_param(
        "i",
        $department_id
    );
}

$stmt->execute();

$patients_result = $stmt->get_result();


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
    Patients | SmartHospital
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
   PAGE HEADER
========================================================= */

.page-header {

    background: white;

    border:
        1px solid #e8eef1;

    border-radius: 14px;

    padding: 24px;

    margin-bottom: 22px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;
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
   SEARCH
========================================================= */

.search-form {

    display: flex;

    gap: 8px;
}

.search-input {

    width: 250px;

    padding: 10px 13px;

    border:
        1px solid #dce6ea;

    border-radius: 7px;

    outline: none;

    font-family: inherit;

    font-size: 12px;
}

.search-input:focus {

    border-color: #087f8c;
}

.search-button {

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

.clear-button {

    display: flex;

    align-items: center;

    padding: 0 10px;

    font-size: 12px;

    color: #7d8c97;
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
   PATIENT CARD
========================================================= */

.patient-card {

    background: white;

    border:
        1px solid #e8eef1;

    border-radius: 14px;

    overflow: hidden;
}

.patient-header {

    padding: 20px 22px;

    border-bottom:
        1px solid #edf1f3;

    display: flex;

    align-items: center;

    justify-content: space-between;
}

.patient-header h3 {

    font-size: 16px;
}

.patient-header span {

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

    color: #17324d;
}

.patient-number {

    color: #8a99a3;

    font-size: 10px;

    margin-top: 3px;
}

.patient-contact {

    color: #536774;

    font-size: 12px;
}

.patient-muted {

    color: #8a99a3;

    font-size: 11px;
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

    transition: .2s;
}

.view-btn:hover {

    opacity: .88;
}


/* =========================================================
   GENDER BADGE
========================================================= */

.gender-badge {

    display: inline-block;

    padding: 5px 9px;

    border-radius: 20px;

    background: #eef2f5;

    color: #667783;

    font-size: 10px;

    font-weight: 600;
}


/* =========================================================
   EMPTY
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

    .page-header {

        flex-direction: column;

        align-items: flex-start;
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

    .search-form {

        width: 100%;

        flex-wrap: wrap;
    }

    .search-input {

        width: 100%;
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


        <a
            href="doctor_patients.php"
            class="active"
        >

            Patients

        </a>


        <a href="#">

            Consultations

        </a>


        <a href="#">

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
                Patients
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


        <!-- =================================================
             PAGE HEADER
        ================================================== -->

        <div class="page-header">


            <div>

                <h2>
                    My Patients
                </h2>

                <p>

                    Patients in

                    <span class="department">

                        <?= e(
                            $doctor["department_name"]
                        ) ?>

                    </span>

                </p>

            </div>


            <!-- SEARCH -->

            <form
                method="GET"
                class="search-form"
            >

                <input
                    type="text"
                    name="search"
                    class="search-input"
                    placeholder="Search patient..."
                    value="<?= e($search) ?>"
                >


                <button
                    type="submit"
                    class="search-button"
                >

                    Search

                </button>


                <?php if ($search !== ""): ?>

                    <a
                        href="doctor_patients.php"
                        class="clear-button"
                    >

                        Clear

                    </a>

                <?php endif; ?>

            </form>


        </div>


        <!-- =================================================
             STATISTICS
        ================================================== -->

        <div class="stats">


            <div class="stat">

                <div class="stat-label">
                    Total Patients
                </div>

                <div class="stat-value">
                    <?= $total_patients ?>
                </div>

                <div class="stat-info">
                    Department patients
                </div>

            </div>


            <div class="stat">

                <div class="stat-label">
                    Waiting
                </div>

                <div class="stat-value">
                    <?= $waiting_patients ?>
                </div>

                <div class="stat-info">
                    Currently in queue
                </div>

            </div>


            <div class="stat">

                <div class="stat-label">
                    Currently Serving
                </div>

                <div class="stat-value">
                    <?= $serving_patients ?>
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
                    <?= $completed_today ?>
                </div>

                <div class="stat-info">
                    Today's consultations
                </div>

            </div>


        </div>


        <!-- =================================================
             PATIENT TABLE
        ================================================== -->

        <div class="patient-card">


            <div class="patient-header">

                <h3>
                    Patient List
                </h3>

                <span>

                    <?= e(
                        $doctor["department_name"]
                    ) ?>

                </span>

            </div>


            <?php if (
                $patients_result->num_rows > 0
            ): ?>


                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Patient
                                </th>

                                <th>
                                    Gender
                                </th>

                                <th>
                                    Date of Birth
                                </th>

                                <th>
                                    Phone
                                </th>

                                <th>
                                    Email
                                </th>

                                <th>
                                    Action
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php while (
                            $patient =
                            $patients_result->fetch_assoc()
                        ): ?>


                            <tr>


                                <!-- PATIENT -->

                                <td>

                                    <div class="patient-name">

                                        <?= e(
                                            $patient["full_name"]
                                        ) ?>

                                    </div>


                                    <div class="patient-number">

                                        <?= e(
                                            $patient["patient_number"]
                                        ) ?>

                                    </div>

                                </td>


                                <!-- GENDER -->

                                <td>

                                    <?php if (
                                        !empty(
                                            $patient["gender"]
                                        )
                                    ): ?>

                                        <span class="gender-badge">

                                            <?= e(
                                                $patient["gender"]
                                            ) ?>

                                        </span>

                                    <?php else: ?>

                                        <span class="patient-muted">
                                            Not provided
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- DOB -->

                                <td>

                                    <?php if (
                                        !empty(
                                            $patient["date_of_birth"]
                                        )
                                    ): ?>

                                        <?= date(
                                            "d M Y",
                                            strtotime(
                                                $patient["date_of_birth"]
                                            )
                                        ) ?>

                                    <?php else: ?>

                                        <span class="patient-muted">
                                            Not provided
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- PHONE -->

                                <td>

                                    <?php if (
                                        !empty(
                                            $patient["phone"]
                                        )
                                    ): ?>

                                        <span class="patient-contact">

                                            <?= e(
                                                $patient["phone"]
                                            ) ?>

                                        </span>

                                    <?php else: ?>

                                        <span class="patient-muted">
                                            Not provided
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- EMAIL -->

                                <td>

                                    <?php if (
                                        !empty(
                                            $patient["email"]
                                        )
                                    ): ?>

                                        <span class="patient-contact">

                                            <?= e(
                                                $patient["email"]
                                            ) ?>

                                        </span>

                                    <?php else: ?>

                                        <span class="patient-muted">
                                            Not provided
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- ACTION -->

                                <td>

                                    <a
                                        href="doctor_patient_view.php?id=<?= (int) $patient["id"] ?>"
                                        class="view-btn"
                                    >

                                        View Patient

                                    </a>

                                </td>


                            </tr>


                        <?php endwhile; ?>


                        </tbody>

                    </table>

                </div>


            <?php else: ?>


                <div class="empty">

                    <div class="empty-title">

                        <?php if ($search !== ""): ?>

                            No patients found

                        <?php else: ?>

                            No patients yet

                        <?php endif; ?>

                    </div>


                    <?php if ($search !== ""): ?>

                        No patient matched
                        "<strong><?= e($search) ?></strong>".

                    <?php else: ?>

                        There are currently no patients
                        registered in your department.

                    <?php endif; ?>

                </div>


            <?php endif; ?>


        </div>


    </section>


</main>


</body>

</html>