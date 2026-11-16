<?php

session_start();

require_once "db.php";


/* =========================
   SECURITY
========================= */

if (!isset($_SESSION["user_id"])) {

    header("Location: login.php");
    exit;

}

if ($_SESSION["role"] !== "admin") {

    header("Location: login.php");
    exit;

}


$full_name = $_SESSION["full_name"] ?? "Administrator";

$message = "";
$error = "";


/* =========================
   ADD DOCTOR
========================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["add_doctor"])
) {

    $user_id = intval($_POST["user_id"] ?? 0);
    $department_id = intval($_POST["department_id"] ?? 0);
    $specialization = trim($_POST["specialization"] ?? "");
    $license_number = trim($_POST["license_number"] ?? "");
    $status = $_POST["status"] ?? "offline";


    $allowed_statuses = [
        "available",
        "busy",
        "offline"
    ];


    if (
        $user_id <= 0
        || $department_id <= 0
    ) {

        $error = "Please select a doctor and department.";

    } elseif (!in_array($status, $allowed_statuses, true)) {

        $error = "Invalid doctor status.";

    } else {

        /* Check selected user */

        $check_user = $conn->prepare(
            "SELECT id
             FROM users
             WHERE id = ?
             AND role = 'doctor'
             AND status = 'active'"
        );

        $check_user->bind_param(
            "i",
            $user_id
        );

        $check_user->execute();

        $user_result = $check_user->get_result();

        $check_user->close();


        if ($user_result->num_rows === 0) {

            $error = "Selected user is not an active doctor.";

        } else {

            /* Check if doctor already exists */

            $check_doctor = $conn->prepare(
                "SELECT id
                 FROM doctors
                 WHERE user_id = ?"
            );

            $check_doctor->bind_param(
                "i",
                $user_id
            );

            $check_doctor->execute();

            $doctor_result =
                $check_doctor->get_result();

            $check_doctor->close();


            if ($doctor_result->num_rows > 0) {

                $error =
                    "This doctor has already been registered.";

            } else {

                $stmt = $conn->prepare(
                    "INSERT INTO doctors
                    (
                        user_id,
                        department_id,
                        specialization,
                        license_number,
                        status
                    )
                    VALUES (?, ?, ?, ?, ?)"
                );


                $stmt->bind_param(
                    "iisss",
                    $user_id,
                    $department_id,
                    $specialization,
                    $license_number,
                    $status
                );


                if ($stmt->execute()) {

                    $message =
                        "Doctor added successfully.";

                } else {

                    $error =
                        "Unable to add doctor.";

                }


                $stmt->close();

            }

        }

    }

}


/* =========================
   EDIT DOCTOR
========================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["edit_doctor"])
) {

    $id = intval($_POST["id"] ?? 0);

    $user_id = intval($_POST["user_id"] ?? 0);

    $department_id =
        intval($_POST["department_id"] ?? 0);

    $specialization =
        trim($_POST["specialization"] ?? "");

    $license_number =
        trim($_POST["license_number"] ?? "");

    $status =
        $_POST["status"] ?? "offline";


    $allowed_statuses = [
        "available",
        "busy",
        "offline"
    ];


    if (
        $id <= 0
        || $user_id <= 0
        || $department_id <= 0
    ) {

        $error =
            "Please provide valid doctor information.";

    } elseif (!in_array($status, $allowed_statuses, true)) {

        $error =
            "Invalid doctor status.";

    } else {

        /* Make sure selected user is a doctor */

        $check_user = $conn->prepare(
            "SELECT id
             FROM users
             WHERE id = ?
             AND role = 'doctor'
             AND status = 'active'"
        );

        $check_user->bind_param(
            "i",
            $user_id
        );

        $check_user->execute();

        $user_result =
            $check_user->get_result();

        $check_user->close();


        if ($user_result->num_rows === 0) {

            $error =
                "Selected user is not an active doctor.";

        } else {

            /* Make sure another doctor
               does not use the same user */

            $check_duplicate = $conn->prepare(
                "SELECT id
                 FROM doctors
                 WHERE user_id = ?
                 AND id != ?"
            );

            $check_duplicate->bind_param(
                "ii",
                $user_id,
                $id
            );

            $check_duplicate->execute();

            $duplicate_result =
                $check_duplicate->get_result();

            $check_duplicate->close();


            if ($duplicate_result->num_rows > 0) {

                $error =
                    "This doctor is already registered.";

            } else {

                $stmt = $conn->prepare(
                    "UPDATE doctors
                     SET
                        user_id = ?,
                        department_id = ?,
                        specialization = ?,
                        license_number = ?,
                        status = ?
                     WHERE id = ?"
                );


                $stmt->bind_param(
                    "iisssi",
                    $user_id,
                    $department_id,
                    $specialization,
                    $license_number,
                    $status,
                    $id
                );


                if ($stmt->execute()) {

                    $message =
                        "Doctor updated successfully.";

                } else {

                    $error =
                        "Unable to update doctor.";

                }


                $stmt->close();

            }

        }

    }

}


/* =========================
   DELETE DOCTOR
========================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["delete_doctor"])
) {

    $id = intval($_POST["id"] ?? 0);


    if ($id > 0) {

        $stmt = $conn->prepare(
            "DELETE FROM doctors
             WHERE id = ?"
        );

        $stmt->bind_param(
            "i",
            $id
        );


        if ($stmt->execute()) {

            $message =
                "Doctor removed successfully.";

        } else {

            $error =
                "Unable to remove doctor.";

        }


        $stmt->close();

    }

}


/* =========================
   CHANGE DOCTOR STATUS
========================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["change_status"])
) {

    $id = intval($_POST["id"] ?? 0);

    $new_status =
        $_POST["new_status"] ?? "offline";


    $allowed_statuses = [
        "available",
        "busy",
        "offline"
    ];


    if (
        $id <= 0
        || !in_array(
            $new_status,
            $allowed_statuses,
            true
        )
    ) {

        $error =
            "Invalid doctor status.";

    } else {

        $stmt = $conn->prepare(
            "UPDATE doctors
             SET status = ?
             WHERE id = ?"
        );

        $stmt->bind_param(
            "si",
            $new_status,
            $id
        );


        if ($stmt->execute()) {

            $message =
                "Doctor status updated.";

        } else {

            $error =
                "Unable to update doctor status.";

        }


        $stmt->close();

    }

}


/* =========================
   SEARCH
========================= */

$search = trim(
    $_GET["search"] ?? ""
);


if ($search !== "") {

    $stmt = $conn->prepare(
        "SELECT
            d.*,
            u.full_name,
            u.email,
            u.status AS user_status,
            dep.name AS department_name
         FROM doctors d
         INNER JOIN users u
            ON d.user_id = u.id
         INNER JOIN departments dep
            ON d.department_id = dep.id
         WHERE
            u.full_name LIKE ?
            OR u.email LIKE ?
            OR d.specialization LIKE ?
            OR d.license_number LIKE ?
            OR dep.name LIKE ?
         ORDER BY d.id DESC"
    );


    $search_value =
        "%" . $search . "%";


    $stmt->bind_param(
        "sssss",
        $search_value,
        $search_value,
        $search_value,
        $search_value,
        $search_value
    );


    $stmt->execute();


    $doctors =
        $stmt
            ->get_result()
            ->fetch_all(MYSQLI_ASSOC);


    $stmt->close();

} else {

    $result = $conn->query(
        "SELECT
            d.*,
            u.full_name,
            u.email,
            u.status AS user_status,
            dep.name AS department_name
         FROM doctors d
         INNER JOIN users u
            ON d.user_id = u.id
         INNER JOIN departments dep
            ON d.department_id = dep.id
         ORDER BY d.id DESC"
    );


    $doctors =
        $result
            ? $result->fetch_all(MYSQLI_ASSOC)
            : [];

}


/* =========================
   AVAILABLE DOCTOR USERS
========================= */

$user_result = $conn->query(
    "SELECT
        u.id,
        u.full_name,
        u.email
     FROM users u
     LEFT JOIN doctors d
        ON u.id = d.user_id
     WHERE
        u.role = 'doctor'
        AND u.status = 'active'
        AND d.id IS NULL
     ORDER BY u.full_name ASC"
);


$doctor_users =
    $user_result
        ? $user_result->fetch_all(MYSQLI_ASSOC)
        : [];


/* =========================
   ALL DOCTOR USERS
   FOR EDIT MODAL
========================= */

$all_doctor_users_result = $conn->query(
    "SELECT
        id,
        full_name,
        email
     FROM users
     WHERE role = 'doctor'
     AND status = 'active'
     ORDER BY full_name ASC"
);


$all_doctor_users =
    $all_doctor_users_result
        ? $all_doctor_users_result->fetch_all(MYSQLI_ASSOC)
        : [];


/* =========================
   DEPARTMENTS
========================= */

$department_result = $conn->query(
    "SELECT
        id,
        name
     FROM departments
     WHERE status = 'active'
     ORDER BY name ASC"
);


$departments =
    $department_result
        ? $department_result->fetch_all(MYSQLI_ASSOC)
        : [];


/* =========================
   STATISTICS
========================= */

$total_doctors = count($doctors);

$available_doctors = 0;
$busy_doctors = 0;
$offline_doctors = 0;


foreach ($doctors as $doctor) {

    if ($doctor["status"] === "available") {

        $available_doctors++;

    } elseif ($doctor["status"] === "busy") {

        $busy_doctors++;

    } else {

        $offline_doctors++;

    }

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
    Doctors | SmartHospital
</title>


<style>

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

    background: #f5f9fa;

    color: #17324d;

}


/* =========================
   SIDEBAR
========================= */

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

    background:
        rgba(255,255,255,0.08);

    color: #dce7ed;

    text-decoration: none;

    font-size: 13px;

}


.logout a:hover {

    background: #b42318;

    color: white;

}


/* =========================
   MAIN
========================= */

.main {

    margin-left: 245px;

    min-height: 100vh;

}


/* =========================
   TOPBAR
========================= */

.topbar {

    height: 76px;

    background: white;

    border-bottom:
        1px solid #e8eef1;

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


/* =========================
   CONTENT
========================= */

.content {

    padding: 32px 35px;

}


.page-heading {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 22px;

}


.page-heading h2 {

    font-size: 24px;

    margin-bottom: 5px;

}


.page-heading p {

    color: #8494a0;

    font-size: 13px;

}


.add-btn {

    background: #087f8c;

    color: white;

    border: none;

    border-radius: 8px;

    padding: 12px 18px;

    font-size: 13px;

    font-weight: 600;

    cursor: pointer;

}


.add-btn:hover {

    background: #066b76;

}


/* =========================
   ALERTS
========================= */

.alert {

    padding: 12px 15px;

    border-radius: 8px;

    margin-bottom: 20px;

    font-size: 13px;

}


.success {

    background: #eaf8f1;

    color: #137a4b;

    border: 1px solid #c9ead9;

}


.error {

    background: #fff1f1;

    color: #b42318;

    border: 1px solid #f3c7c7;

}


/* =========================
   STATISTICS
========================= */

.doctor-stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 16px;

    margin-bottom: 20px;

}


.doctor-stat {

    background: white;

    border: 1px solid #e9eff2;

    border-radius: 11px;

    padding: 17px 18px;

}


.doctor-stat-number {

    font-size: 25px;

    font-weight: 700;

    margin-bottom: 4px;

}


.doctor-stat-label {

    color: #8494a0;

    font-size: 12px;

}


.available-number {

    color: #137a4b;

}


.busy-number {

    color: #a66a00;

}


.offline-number {

    color: #8494a0;

}


/* =========================
   TOOLBAR
========================= */

.toolbar {

    background: white;

    border: 1px solid #e9eff2;

    border-radius: 12px;

    padding: 18px;

    margin-bottom: 18px;

}


.search-form {

    display: flex;

    gap: 10px;

}


.search-input {

    flex: 1;

    height: 43px;

    border: 1px solid #dce6ea;

    border-radius: 7px;

    padding: 0 13px;

    outline: none;

    font-family: inherit;

}


.search-input:focus {

    border-color: #087f8c;

}


.search-btn {

    height: 43px;

    padding: 0 20px;

    border: none;

    border-radius: 7px;

    background: #102d44;

    color: white;

    cursor: pointer;

}


.clear-btn {

    display: flex;

    align-items: center;

    padding: 0 15px;

    border-radius: 7px;

    border: 1px solid #dce6ea;

    color: #647789;

    text-decoration: none;

    font-size: 13px;

}


/* =========================
   TABLE
========================= */

.table-card {

    background: white;

    border: 1px solid #e9eff2;

    border-radius: 12px;

    overflow: hidden;

}


.table-header {

    padding: 20px 22px;

    border-bottom:
        1px solid #edf1f3;

    display: flex;

    justify-content: space-between;

}


.table-header h3 {

    font-size: 16px;

}


.table-header span {

    color: #8494a0;

    font-size: 12px;

}


.table-wrapper {

    overflow-x: auto;

}


table {

    width: 100%;

    border-collapse: collapse;

    min-width: 950px;

}


th {

    text-align: left;

    background: #f8fafb;

    color: #71818d;

    font-size: 11px;

    text-transform: uppercase;

    letter-spacing: .5px;

    padding: 14px 18px;

}


td {

    padding: 16px 18px;

    border-top:
        1px solid #edf1f3;

    font-size: 13px;

    vertical-align: middle;

}


.doctor-name {

    font-weight: 600;

    color: #17324d;

    margin-bottom: 3px;

}


.doctor-email {

    color: #8494a0;

    font-size: 11px;

}


.department-name {

    font-weight: 600;

    color: #087f8c;

}


.specialization {

    color: #526b7c;

}


.license {

    color: #6d7e89;

    font-family: monospace;

    font-size: 12px;

}


/* =========================
   STATUS
========================= */

.status {

    display: inline-block;

    padding: 5px 9px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: 600;

}


.status.available {

    background: #eaf8f1;

    color: #137a4b;

}


.status.busy {

    background: #fff7e6;

    color: #a66a00;

}


.status.offline {

    background: #eef1f3;

    color: #687985;

}


/* =========================
   ACTIONS
========================= */

.actions-cell {

    white-space: nowrap;

}


.action-btn {

    border: none;

    border-radius: 6px;

    padding: 7px 10px;

    font-size: 11px;

    cursor: pointer;

    margin-right: 4px;

}


.edit-btn {

    background: #e8f7f8;

    color: #087f8c;

}


.status-btn {

    background: #fff7e6;

    color: #a66a00;

}


.delete-btn {

    background: #fff1f1;

    color: #b42318;

}


/* =========================
   EMPTY
========================= */

.empty {

    text-align: center;

    padding: 45px;

    color: #8494a0;

}


/* =========================
   MODAL
========================= */

.modal {

    display: none;

    position: fixed;

    inset: 0;

    background:
        rgba(16,45,68,0.55);

    z-index: 500;

    align-items: center;

    justify-content: center;

    padding: 20px;

}


.modal.show {

    display: flex;

}


.modal-box {

    width: 100%;

    max-width: 520px;

    max-height: 90vh;

    overflow-y: auto;

    background: white;

    border-radius: 14px;

    padding: 28px;

    box-shadow:
        0 20px 60px
        rgba(0,0,0,.2);

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

    font-size: 24px;

    color: #8494a0;

    cursor: pointer;

}


.form-group {

    margin-bottom: 18px;

}


.form-group label {

    display: block;

    margin-bottom: 7px;

    font-size: 12px;

    font-weight: 600;

    color: #334d62;

}


.form-group input,
.form-group select {

    width: 100%;

    height: 43px;

    border: 1px solid #dce6ea;

    border-radius: 7px;

    padding: 0 12px;

    outline: none;

    font-family: inherit;

    font-size: 13px;

    background: white;

}


.form-group input:focus,
.form-group select:focus {

    border-color: #087f8c;

}


.help-text {

    margin-top: 6px;

    color: #8494a0;

    font-size: 11px;

}


.modal-actions {

    display: flex;

    justify-content: flex-end;

    gap: 10px;

    margin-top: 22px;

}


.cancel-btn {

    border: 1px solid #dce6ea;

    background: white;

    color: #647789;

    padding: 10px 17px;

    border-radius: 7px;

    cursor: pointer;

}


.save-btn {

    border: none;

    background: #087f8c;

    color: white;

    padding: 10px 18px;

    border-radius: 7px;

    cursor: pointer;

    font-weight: 600;

}


/* =========================
   STATUS MODAL
========================= */

.status-options {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 10px;

}


.status-option {

    border: 1px solid #dce6ea;

    border-radius: 8px;

    padding: 13px 8px;

    background: white;

    cursor: pointer;

    text-align: center;

    font-size: 12px;

}


.status-option:hover {

    border-color: #087f8c;

}


.status-option.available-option {

    color: #137a4b;

}


.status-option.busy-option {

    color: #a66a00;

}


.status-option.offline-option {

    color: #687985;

}


/* =========================
   MOBILE
========================= */

@media (max-width: 1000px) {

    .doctor-stats {

        grid-template-columns:
            repeat(2, 1fr);

    }

}


@media (max-width: 800px) {

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

        padding: 22px 15px;

    }


    .page-heading {

        align-items: flex-start;

        gap: 15px;

        flex-direction: column;

    }


    .search-form {

        flex-wrap: wrap;

    }


    .search-input {

        width: 100%;

        flex-basis: 100%;

    }


    .doctor-stats {

        grid-template-columns: 1fr 1fr;

    }


    .modal-box {

        padding: 22px;

    }

}


@media (max-width: 500px) {

    .doctor-stats {

        grid-template-columns: 1fr;

    }


    .status-options {

        grid-template-columns: 1fr;

    }

}

</style>

</head>


<body>


<!-- =========================
     SIDEBAR
========================= -->

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


        <a
            href="doctors.php"
            class="active"
        >

            <span class="menu-icon">
                🩺
            </span>

            <span>
                Doctors
            </span>

        </a>


        <a href="#">

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


        <a href="#">

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


<!-- =========================
     MAIN
========================= -->

<div class="main">


    <!-- TOPBAR -->

    <header class="topbar">


        <div class="page-title">

            <h1>
                Doctor Management
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

                    <?= htmlspecialchars(
                        $full_name
                    ) ?>

                </strong>

                <span>
                    System Administrator
                </span>

            </div>


        </div>


    </header>


    <!-- CONTENT -->

    <main class="content">


        <!-- PAGE HEADING -->

        <section class="page-heading">


            <div>

                <h2>
                    Doctors
                </h2>

                <p>
                    Register and manage hospital doctors.
                </p>

            </div>


            <button
                class="add-btn"
                onclick="openAddModal()"
            >

                + Add Doctor

            </button>


        </section>


        <!-- ALERTS -->

        <?php if ($message !== ""): ?>

            <div class="alert success">

                <?= htmlspecialchars($message) ?>

            </div>

        <?php endif; ?>


        <?php if ($error !== ""): ?>

            <div class="alert error">

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <!-- STATISTICS -->

        <section class="doctor-stats">


            <div class="doctor-stat">

                <div class="doctor-stat-number">

                    <?= $total_doctors ?>

                </div>

                <div class="doctor-stat-label">

                    Total Doctors

                </div>

            </div>


            <div class="doctor-stat">

                <div
                    class="doctor-stat-number available-number"
                >

                    <?= $available_doctors ?>

                </div>

                <div class="doctor-stat-label">

                    Available

                </div>

            </div>


            <div class="doctor-stat">

                <div
                    class="doctor-stat-number busy-number"
                >

                    <?= $busy_doctors ?>

                </div>

                <div class="doctor-stat-label">

                    Busy

                </div>

            </div>


            <div class="doctor-stat">

                <div
                    class="doctor-stat-number offline-number"
                >

                    <?= $offline_doctors ?>

                </div>

                <div class="doctor-stat-label">

                    Offline

                </div>

            </div>


        </section>


        <!-- SEARCH -->

        <section class="toolbar">


            <form
                method="GET"
                class="search-form"
            >


                <input
                    type="text"
                    name="search"
                    class="search-input"
                    placeholder="Search doctor, email, department, specialization or license..."
                    value="<?= htmlspecialchars($search) ?>"
                >


                <button
                    type="submit"
                    class="search-btn"
                >

                    Search

                </button>


                <?php if ($search !== ""): ?>

                    <a
                        href="doctors.php"
                        class="clear-btn"
                    >

                        Clear

                    </a>

                <?php endif; ?>


            </form>


        </section>


        <!-- TABLE -->

        <section class="table-card">


            <div class="table-header">

                <h3>
                    Hospital Doctors
                </h3>

                <span>

                    <?= $total_doctors ?>

                    doctor(s)

                </span>

            </div>


            <?php if (count($doctors) > 0): ?>


                <div class="table-wrapper">


                    <table>


                        <thead>

                            <tr>

                                <th>
                                    Doctor
                                </th>

                                <th>
                                    Department
                                </th>

                                <th>
                                    Specialization
                                </th>

                                <th>
                                    License
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Actions
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach (
                            $doctors
                            as $doctor
                        ): ?>


                            <tr>


                                <td>

                                    <div class="doctor-name">

                                        🩺

                                        <?= htmlspecialchars(
                                            $doctor["full_name"]
                                        ) ?>

                                    </div>

                                    <div class="doctor-email">

                                        <?= htmlspecialchars(
                                            $doctor["email"]
                                        ) ?>

                                    </div>

                                </td>


                                <td>

                                    <div class="department-name">

                                        🏥

                                        <?= htmlspecialchars(
                                            $doctor["department_name"]
                                        ) ?>

                                    </div>

                                </td>


                                <td>

                                    <div class="specialization">

                                        <?= htmlspecialchars(
                                            $doctor["specialization"]
                                            ?: "Not specified"
                                        ) ?>

                                    </div>

                                </td>


                                <td>

                                    <div class="license">

                                        <?= htmlspecialchars(
                                            $doctor["license_number"]
                                            ?: "Not provided"
                                        ) ?>

                                    </div>

                                </td>


                                <td>

                                    <span
                                        class="status
                                        <?= htmlspecialchars(
                                            $doctor["status"]
                                        ) ?>"
                                    >

                                        <?= ucfirst(
                                            $doctor["status"]
                                        ) ?>

                                    </span>

                                </td>


                                <td class="actions-cell">


                                    <!-- EDIT -->

                                    <button
                                        type="button"
                                        class="action-btn edit-btn"
                                        onclick='openEditModal(
                                            <?= json_encode(
                                                $doctor,
                                                JSON_HEX_TAG |
                                                JSON_HEX_APOS |
                                                JSON_HEX_QUOT |
                                                JSON_HEX_AMP
                                            ) ?>
                                        )'
                                    >

                                        Edit

                                    </button>


                                    <!-- STATUS -->

                                    <button
                                        type="button"
                                        class="action-btn status-btn"
                                        onclick='openStatusModal(
                                            <?= (int)$doctor["id"] ?>,
                                            <?= json_encode(
                                                $doctor["full_name"],
                                                JSON_HEX_TAG |
                                                JSON_HEX_APOS |
                                                JSON_HEX_QUOT |
                                                JSON_HEX_AMP
                                            ) ?>
                                        )'
                                    >

                                        Status

                                    </button>


                                    <!-- DELETE -->

                                    <form
                                        method="POST"
                                        style="display:inline;"
                                        onsubmit="
                                            return confirm(
                                                'Are you sure you want to remove this doctor?'
                                            );
                                        "
                                    >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)$doctor["id"] ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="delete_doctor"
                                            class="action-btn delete-btn"
                                        >

                                            Delete

                                        </button>

                                    </form>


                                </td>


                            </tr>


                        <?php endforeach; ?>


                        </tbody>


                    </table>


                </div>


            <?php else: ?>


                <div class="empty">

                    No doctors found.

                </div>


            <?php endif; ?>


        </section>


    </main>


</div>


<!-- =========================
     ADD DOCTOR MODAL
========================= -->

<div
    class="modal"
    id="addModal"
>


    <div class="modal-box">


        <div class="modal-header">

            <h3>
                Add Doctor
            </h3>

            <button
                type="button"
                class="close"
                onclick="closeAddModal()"
            >

                ×

            </button>

        </div>


        <form method="POST">


            <div class="form-group">

                <label>
                    Doctor User
                </label>

                <select
                    name="user_id"
                    required
                >

                    <option value="">
                        Select doctor
                    </option>


                    <?php foreach (
                        $doctor_users
                        as $user
                    ): ?>

                        <option
                            value="<?= (int)$user["id"] ?>"
                        >

                            <?= htmlspecialchars(
                                $user["full_name"]
                            ) ?>

                            —
                            <?= htmlspecialchars(
                                $user["email"]
                            ) ?>

                        </option>

                    <?php endforeach; ?>


                </select>


                <div class="help-text">

                    Only active users with the doctor role are shown.

                </div>

            </div>


            <div class="form-group">

                <label>
                    Department
                </label>

                <select
                    name="department_id"
                    required
                >

                    <option value="">
                        Select department
                    </option>


                    <?php foreach (
                        $departments
                        as $department
                    ): ?>

                        <option
                            value="<?= (int)$department["id"] ?>"
                        >

                            <?= htmlspecialchars(
                                $department["name"]
                            ) ?>

                        </option>

                    <?php endforeach; ?>


                </select>

            </div>


            <div class="form-group">

                <label>
                    Specialization
                </label>

                <input
                    type="text"
                    name="specialization"
                    placeholder="e.g. Cardiologist"
                >

            </div>


            <div class="form-group">

                <label>
                    License Number
                </label>

                <input
                    type="text"
                    name="license_number"
                    placeholder="e.g. MED-2026-001"
                >

            </div>


            <div class="form-group">

                <label>
                    Status
                </label>

                <select
                    name="status"
                    required
                >

                    <option value="offline">
                        Offline
                    </option>

                    <option value="available">
                        Available
                    </option>

                    <option value="busy">
                        Busy
                    </option>

                </select>

            </div>


            <div class="modal-actions">


                <button
                    type="button"
                    class="cancel-btn"
                    onclick="closeAddModal()"
                >

                    Cancel

                </button>


                <button
                    type="submit"
                    name="add_doctor"
                    class="save-btn"
                >

                    Add Doctor

                </button>


            </div>


        </form>


    </div>


</div>


<!-- =========================
     EDIT DOCTOR MODAL
========================= -->

<div
    class="modal"
    id="editModal"
>


    <div class="modal-box">


        <div class="modal-header">

            <h3>
                Edit Doctor
            </h3>

            <button
                type="button"
                class="close"
                onclick="closeEditModal()"
            >

                ×

            </button>

        </div>


        <form method="POST">


            <input
                type="hidden"
                name="id"
                id="edit_id"
            >


            <div class="form-group">

                <label>
                    Doctor User
                </label>

                <select
                    name="user_id"
                    id="edit_user_id"
                    required
                >

                    <option value="">
                        Select doctor
                    </option>


                    <?php foreach (
                        $all_doctor_users
                        as $user
                    ): ?>

                        <option
                            value="<?= (int)$user["id"] ?>"
                        >

                            <?= htmlspecialchars(
                                $user["full_name"]
                            ) ?>

                            —
                            <?= htmlspecialchars(
                                $user["email"]
                            ) ?>

                        </option>

                    <?php endforeach; ?>


                </select>

            </div>


            <div class="form-group">

                <label>
                    Department
                </label>

                <select
                    name="department_id"
                    id="edit_department_id"
                    required
                >

                    <option value="">
                        Select department
                    </option>


                    <?php foreach (
                        $departments
                        as $department
                    ): ?>

                        <option
                            value="<?= (int)$department["id"] ?>"
                        >

                            <?= htmlspecialchars(
                                $department["name"]
                            ) ?>

                        </option>

                    <?php endforeach; ?>


                </select>

            </div>


            <div class="form-group">

                <label>
                    Specialization
                </label>

                <input
                    type="text"
                    name="specialization"
                    id="edit_specialization"
                >

            </div>


            <div class="form-group">

                <label>
                    License Number
                </label>

                <input
                    type="text"
                    name="license_number"
                    id="edit_license_number"
                >

            </div>


            <div class="form-group">

                <label>
                    Status
                </label>

                <select
                    name="status"
                    id="edit_status"
                    required
                >

                    <option value="offline">
                        Offline
                    </option>

                    <option value="available">
                        Available
                    </option>

                    <option value="busy">
                        Busy
                    </option>

                </select>

            </div>


            <div class="modal-actions">


                <button
                    type="button"
                    class="cancel-btn"
                    onclick="closeEditModal()"
                >

                    Cancel

                </button>


                <button
                    type="submit"
                    name="edit_doctor"
                    class="save-btn"
                >

                    Save Changes

                </button>


            </div>


        </form>


    </div>


</div>


<!-- =========================
     STATUS MODAL
========================= -->

<div
    class="modal"
    id="statusModal"
>


    <div class="modal-box">


        <div class="modal-header">

            <h3>
                Change Doctor Status
            </h3>

            <button
                type="button"
                class="close"
                onclick="closeStatusModal()"
            >

                ×

            </button>

        </div>


        <p
            id="statusDoctorName"
            style="
                color:#8494a0;
                font-size:13px;
                margin-bottom:20px;
            "
        ></p>


        <form method="POST">


            <input
                type="hidden"
                name="id"
                id="status_id"
            >


            <div class="status-options">


                <button
                    type="submit"
                    name="change_status"
                    value="1"
                    class="
                        status-option
                        available-option
                    "
                    onclick="
                        document.getElementById(
                            'new_status'
                        ).value = 'available';
                    "
                >

                    🟢<br>

                    Available

                </button>


                <button
                    type="submit"
                    name="change_status"
                    value="1"
                    class="
                        status-option
                        busy-option
                    "
                    onclick="
                        document.getElementById(
                            'new_status'
                        ).value = 'busy';
                    "
                >

                    🟠<br>

                    Busy

                </button>


                <button
                    type="submit"
                    name="change_status"
                    value="1"
                    class="
                        status-option
                        offline-option
                    "
                    onclick="
                        document.getElementById(
                            'new_status'
                        ).value = 'offline';
                    "
                >

                    ⚫<br>

                    Offline

                </button>


            </div>


            <input
                type="hidden"
                name="new_status"
                id="new_status"
                value="offline"
            >


        </form>


    </div>


</div>


<script>

/* =========================
   ADD MODAL
========================= */

function openAddModal() {

    document
        .getElementById("addModal")
        .classList.add("show");

}


function closeAddModal() {

    document
        .getElementById("addModal")
        .classList.remove("show");

}


/* =========================
   EDIT MODAL
========================= */

function openEditModal(doctor) {

    document.getElementById("edit_id").value =
        doctor.id;

    document.getElementById("edit_user_id").value =
        doctor.user_id;

    document.getElementById("edit_department_id").value =
        doctor.department_id;

    document.getElementById("edit_specialization").value =
        doctor.specialization || "";

    document.getElementById("edit_license_number").value =
        doctor.license_number || "";

    document.getElementById("edit_status").value =
        doctor.status;


    document
        .getElementById("editModal")
        .classList.add("show");

}


function closeEditModal() {

    document
        .getElementById("editModal")
        .classList.remove("show");

}


/* =========================
   STATUS MODAL
========================= */

function openStatusModal(id, name) {

    document.getElementById("status_id").value =
        id;

    document.getElementById("statusDoctorName").textContent =
        "Choose the new status for " + name + ".";

    document
        .getElementById("statusModal")
        .classList.add("show");

}


function closeStatusModal() {

    document
        .getElementById("statusModal")
        .classList.remove("show");

}


/* =========================
   CLOSE OUTSIDE MODALS
========================= */

window.addEventListener(
    "click",
    function(event) {

        const addModal =
            document.getElementById("addModal");

        const editModal =
            document.getElementById("editModal");

        const statusModal =
            document.getElementById("statusModal");


        if (event.target === addModal) {

            closeAddModal();

        }


        if (event.target === editModal) {

            closeEditModal();

        }


        if (event.target === statusModal) {

            closeStatusModal();

        }

    }
);


/* =========================
   ESCAPE KEY
========================= */

window.addEventListener(
    "keydown",
    function(event) {

        if (event.key === "Escape") {

            closeAddModal();

            closeEditModal();

            closeStatusModal();

        }

    }
);

</script>


</body>

</html>