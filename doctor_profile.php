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
        u.status AS account_status,

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
    My Profile | SmartHospital
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
}


/* =========================================================
   LOGO
========================================================= */

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
   PROFILE IN SIDEBAR
========================================================= */

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
   PROFILE HEADER
========================================================= */

.profile-header {

    background: white;

    border:
        1px solid #e8eef1;

    border-radius: 14px;

    padding: 25px;

    display: flex;

    align-items: center;

    gap: 20px;

    margin-bottom: 22px;
}


/* =========================================================
   AVATAR
========================================================= */

.avatar {

    width: 70px;

    height: 70px;

    border-radius: 50%;

    background: #087f8c;

    color: white;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 26px;

    font-weight: 700;
}


.profile-header h2 {

    font-size: 22px;

    margin-bottom: 5px;
}


.profile-header p {

    color: #7c8d99;

    font-size: 13px;
}


.department {

    color: #087f8c;

    font-weight: 600;
}


/* =========================================================
   STATUS BADGES
========================================================= */

.status-row {

    margin-left: auto;

    display: flex;

    flex-direction: column;

    align-items: flex-end;

    gap: 7px;
}


.status-label {

    color: #7d8c97;

    font-size: 11px;
}


.status-badge {

    display: inline-block;

    padding: 6px 11px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: 600;
}


.available {

    background: #e6f7f0;

    color: #16734b;
}


.busy {

    background: #fff1df;

    color: #b15c00;
}


.offline {

    background: #eef2f5;

    color: #667783;
}


.active {

    background: #e6f7f0;

    color: #16734b;
}


.inactive {

    background: #ffe9e9;

    color: #b42318;
}


/* =========================================================
   INFORMATION CARD
========================================================= */

.card {

    background: white;

    border:
        1px solid #e8eef1;

    border-radius: 14px;

    overflow: hidden;

    margin-bottom: 22px;
}


.card-header {

    padding: 20px 22px;

    border-bottom:
        1px solid #edf1f3;
}


.card-header h3 {

    font-size: 16px;
}


.card-header p {

    color: #84929c;

    font-size: 12px;

    margin-top: 4px;
}


/* =========================================================
   INFORMATION GRID
========================================================= */

.info-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);
}


.info-item {

    padding: 20px 22px;

    border-bottom:
        1px solid #edf1f3;
}


.info-item:nth-child(odd) {

    border-right:
        1px solid #edf1f3;
}


.info-label {

    color: #84929c;

    font-size: 11px;

    text-transform: uppercase;

    margin-bottom: 7px;
}


.info-value {

    font-size: 14px;

    font-weight: 600;

    color: #17324d;
}


/* =========================================================
   SECURITY CARD
========================================================= */

.security-card {

    background: #f8fbfc;

    border:
        1px solid #e3ecef;

    border-radius: 10px;

    padding: 18px 20px;

    margin: 20px 22px;
}


.security-title {

    font-size: 13px;

    font-weight: 600;

    margin-bottom: 5px;
}


.security-text {

    color: #7d8c97;

    font-size: 12px;

    line-height: 1.6;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 800px) {

    .profile-header {

        align-items: flex-start;

        flex-wrap: wrap;
    }


    .status-row {

        margin-left: 0;

        align-items: flex-start;
    }


    .info-grid {

        grid-template-columns: 1fr;
    }


    .info-item:nth-child(odd) {

        border-right: none;
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


    <!-- DOCTOR -->

    <div class="profile">

        <div class="profile-name">

            <?= e($doctor["full_name"]) ?>

        </div>

        <div class="profile-role">

            Doctor

        </div>

    </div>


    <!-- NAVIGATION -->

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


        <a href="doctor_consultations.php">

            Consultations

        </a>


        <a
            href="doctor_profile.php"
            class="active"
        >

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

                My Profile

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


        <!-- =================================================
             PROFILE HEADER
        ================================================== -->

        <div class="profile-header">


            <div class="avatar">

                <?= e(
                    strtoupper(
                        substr(
                            $doctor["full_name"],
                            0,
                            1
                        )
                    )
                ) ?>

            </div>


            <div>

                <h2>

                    <?= e(
                        $doctor["full_name"]
                    ) ?>

                </h2>


                <p>

                    <?= e(
                        $doctor["specialization"]
                    ) ?>

                    ·

                    <span class="department">

                        <?= e(
                            $doctor["department_name"]
                        ) ?>

                    </span>

                </p>

            </div>


            <!-- DOCTOR STATUS -->

            <div class="status-row">


                <span class="status-label">

                    Doctor Status

                </span>


                <span
                    class="status-badge <?= e(
                        $doctor["doctor_status"]
                    ) ?>"
                >

                    <?= ucfirst(
                        e($doctor["doctor_status"])
                    ) ?>

                </span>


            </div>


        </div>



        <!-- =================================================
             PROFESSIONAL INFORMATION
        ================================================== -->

        <div class="card">


            <div class="card-header">

                <h3>

                    Professional Information

                </h3>

                <p>

                    Your medical and professional details

                </p>

            </div>


            <div class="info-grid">


                <!-- FULL NAME -->

                <div class="info-item">

                    <div class="info-label">

                        Full Name

                    </div>

                    <div class="info-value">

                        <?= e(
                            $doctor["full_name"]
                        ) ?>

                    </div>

                </div>


                <!-- EMAIL -->

                <div class="info-item">

                    <div class="info-label">

                        Email Address

                    </div>

                    <div class="info-value">

                        <?= e(
                            $doctor["email"]
                        ) ?>

                    </div>

                </div>


                <!-- DEPARTMENT -->

                <div class="info-item">

                    <div class="info-label">

                        Department

                    </div>

                    <div class="info-value">

                        <?= e(
                            $doctor["department_name"]
                        ) ?>

                    </div>

                </div>


                <!-- SPECIALIZATION -->

                <div class="info-item">

                    <div class="info-label">

                        Specialization

                    </div>

                    <div class="info-value">

                        <?= e(
                            $doctor["specialization"]
                        ) ?>

                    </div>

                </div>


                <!-- LICENSE -->

                <div class="info-item">

                    <div class="info-label">

                        License Number

                    </div>

                    <div class="info-value">

                        <?= e(
                            $doctor["license_number"]
                        ) ?>

                    </div>

                </div>


                <!-- DOCTOR STATUS -->

                <div class="info-item">

                    <div class="info-label">

                        Doctor Status

                    </div>

                    <div class="info-value">

                        <?= ucfirst(
                            e($doctor["doctor_status"])
                        ) ?>

                    </div>

                </div>


                <!-- ACCOUNT STATUS -->

                <div class="info-item">

                    <div class="info-label">

                        Account Status

                    </div>

                    <div class="info-value">

                        <span
                            class="status-badge <?= e(
                                $doctor["account_status"]
                            ) ?>"
                        >

                            <?= ucfirst(
                                e($doctor["account_status"])
                            ) ?>

                        </span>

                    </div>

                </div>


            </div>


            <!-- SECURITY -->

            <div class="security-card">


                <div class="security-title">

                    Account Information

                </div>


                <div class="security-text">

                    Your doctor account is managed through
                    the SmartHospital system. Contact the
                    system administrator if you need your
                    professional information updated.

                </div>


            </div>


        </div>


    </section>


</main>


</body>

</html>