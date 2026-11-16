<?php

session_start();

require_once "db.php";


/* =========================================================
   ADMIN SECURITY
========================================================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}


/* =========================================================
   CSRF PROTECTION
========================================================= */

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION["csrf_token"];


/* =========================================================
   ALLOWED VALUES
========================================================= */

$allowed_roles = [
    "admin",
    "receptionist",
    "doctor",
    "nurse",
    "patient"
];

$allowed_statuses = [
    "active",
    "inactive"
];


/* =========================================================
   VARIABLES
========================================================= */

$message = "";
$error = "";

$edit_user = null;


/* =========================================================
   VERIFY CSRF TOKEN
========================================================= */

function verify_csrf_token()
{
    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals(
            $_SESSION["csrf_token"],
            $_POST["csrf_token"]
        )
    ) {
        die("Invalid security token. Please refresh the page and try again.");
    }
}


/* =========================================================
   ADD USER
========================================================= */

if (isset($_POST["add_user"])) {

    verify_csrf_token();

    $full_name = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $role = $_POST["role"] ?? "";
    $status = $_POST["status"] ?? "active";


    if (
        empty($full_name) ||
        empty($email) ||
        empty($password) ||
        empty($role)
    ) {

        $error = "Please complete all required fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif (!in_array($role, $allowed_roles, true)) {

        $error = "Invalid user role selected.";

    } elseif (!in_array($status, $allowed_statuses, true)) {

        $error = "Invalid account status selected.";

    } elseif (strlen($password) < 6) {

        $error = "Password must contain at least 6 characters.";

    } else {

        /* Check duplicate email */

        $check = $conn->prepare(
            "SELECT id
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        $check->bind_param("s", $email);
        $check->execute();

        $check_result = $check->get_result();


        if ($check_result->num_rows > 0) {

            $error = "A user with this email already exists.";

        } else {

            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );


            $stmt = $conn->prepare(
                "INSERT INTO users
                (
                    full_name,
                    email,
                    password,
                    role,
                    status
                )
                VALUES (?, ?, ?, ?, ?)"
            );


            $stmt->bind_param(
                "sssss",
                $full_name,
                $email,
                $hashed_password,
                $role,
                $status
            );


            if ($stmt->execute()) {

                $message = "User created successfully.";

            } else {

                $error = "Unable to create user.";

            }


            $stmt->close();
        }


        $check->close();
    }
}


/* =========================================================
   UPDATE USER
========================================================= */

if (isset($_POST["update_user"])) {

    verify_csrf_token();

    $id = intval($_POST["id"] ?? 0);

    $full_name = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $role = $_POST["role"] ?? "";
    $status = $_POST["status"] ?? "active";


    if (
        $id <= 0 ||
        empty($full_name) ||
        empty($email) ||
        empty($role)
    ) {

        $error = "Please complete all required fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif (!in_array($role, $allowed_roles, true)) {

        $error = "Invalid user role selected.";

    } elseif (!in_array($status, $allowed_statuses, true)) {

        $error = "Invalid account status selected.";

    } else {

        /* Get current user information */

        $current_stmt = $conn->prepare(
            "SELECT id, role, status
             FROM users
             WHERE id = ?
             LIMIT 1"
        );

        $current_stmt->bind_param("i", $id);
        $current_stmt->execute();

        $current_result = $current_stmt->get_result();

        $current_user = $current_result->fetch_assoc();

        $current_stmt->close();


        if (!$current_user) {

            $error = "User not found.";

        } else {

            /*
             * Prevent logged-in admin from changing
             * their own role or status.
             */

            if ($id == $_SESSION["user_id"]) {

                if ($role !== "admin") {

                    $error = "You cannot change your own administrator role.";

                } elseif ($status !== "active") {

                    $error = "You cannot deactivate your own administrator account.";

                }

            }


            if (empty($error)) {

                /* Check duplicate email */

                $check = $conn->prepare(
                    "SELECT id
                     FROM users
                     WHERE email = ?
                     AND id != ?
                     LIMIT 1"
                );


                $check->bind_param(
                    "si",
                    $email,
                    $id
                );


                $check->execute();

                $check_result = $check->get_result();


                if ($check_result->num_rows > 0) {

                    $error = "Another user already uses this email.";

                } else {

                    $stmt = $conn->prepare(
                        "UPDATE users
                         SET
                            full_name = ?,
                            email = ?,
                            role = ?,
                            status = ?
                         WHERE id = ?"
                    );


                    $stmt->bind_param(
                        "ssssi",
                        $full_name,
                        $email,
                        $role,
                        $status,
                        $id
                    );


                    if ($stmt->execute()) {

                        $message = "User updated successfully.";

                    } else {

                        $error = "Unable to update user.";

                    }


                    $stmt->close();
                }


                $check->close();
            }
        }
    }
}


/* =========================================================
   CHANGE PASSWORD
========================================================= */

if (isset($_POST["change_password"])) {

    verify_csrf_token();

    $id = intval($_POST["id"] ?? 0);

    $new_password = $_POST["new_password"] ?? "";


    if ($id <= 0 || empty($new_password)) {

        $error = "Please enter a new password.";

    } elseif (strlen($new_password) < 6) {

        $error = "Password must contain at least 6 characters.";

    } else {

        $hashed_password = password_hash(
            $new_password,
            PASSWORD_DEFAULT
        );


        $stmt = $conn->prepare(
            "UPDATE users
             SET password = ?
             WHERE id = ?"
        );


        $stmt->bind_param(
            "si",
            $hashed_password,
            $id
        );


        if ($stmt->execute()) {

            $message = "Password updated successfully.";

        } else {

            $error = "Unable to update password.";

        }


        $stmt->close();
    }
}


/* =========================================================
   ACTIVATE / DEACTIVATE USER
   POST ONLY
========================================================= */

if (isset($_POST["toggle_user"])) {

    verify_csrf_token();

    $id = intval($_POST["id"] ?? 0);


    if ($id <= 0) {

        $error = "Invalid user selected.";

    } elseif ($id == $_SESSION["user_id"]) {

        $error = "You cannot deactivate your own administrator account.";

    } else {

        /*
         * Get current status first.
         */

        $stmt = $conn->prepare(
            "SELECT status
             FROM users
             WHERE id = ?
             LIMIT 1"
        );


        $stmt->bind_param("i", $id);

        $stmt->execute();

        $result = $stmt->get_result();

        $user = $result->fetch_assoc();

        $stmt->close();


        if (!$user) {

            $error = "User not found.";

        } else {

            $new_status =
                $user["status"] === "active"
                ? "inactive"
                : "active";


            $stmt = $conn->prepare(
                "UPDATE users
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
                    $new_status === "active"
                    ? "User activated successfully."
                    : "User deactivated successfully.";

            } else {

                $error = "Unable to change user status.";

            }


            $stmt->close();
        }
    }
}


/* =========================================================
   DELETE USER
   POST ONLY
========================================================= */

if (isset($_POST["delete_user"])) {

    verify_csrf_token();

    $id = intval($_POST["id"] ?? 0);


    if ($id <= 0) {

        $error = "Invalid user selected.";

    } elseif ($id == $_SESSION["user_id"]) {

        $error = "You cannot delete your own administrator account.";

    } else {

        /*
         * Prevent accidental deletion of the last admin.
         */

        $stmt = $conn->prepare(
            "SELECT role
             FROM users
             WHERE id = ?
             LIMIT 1"
        );


        $stmt->bind_param("i", $id);

        $stmt->execute();

        $result = $stmt->get_result();

        $target_user = $result->fetch_assoc();

        $stmt->close();


        if (!$target_user) {

            $error = "User not found.";

        } else {

            if ($target_user["role"] === "admin") {

                $admin_count_result = $conn->query(
                    "SELECT COUNT(*) AS total
                     FROM users
                     WHERE role = 'admin'"
                );


                $admin_count =
                    $admin_count_result->fetch_assoc()["total"];


                if ($admin_count <= 1) {

                    $error =
                        "The last administrator account cannot be deleted.";

                }
            }


            if (empty($error)) {

                $stmt = $conn->prepare(
                    "DELETE FROM users
                     WHERE id = ?"
                );


                $stmt->bind_param(
                    "i",
                    $id
                );


                if ($stmt->execute()) {

                    $message =
                        "User deleted successfully.";

                } else {

                    $error =
                        "Unable to delete user.";

                }


                $stmt->close();
            }
        }
    }
}


/* =========================================================
   GET USER FOR EDIT
========================================================= */

if (isset($_GET["edit"])) {

    $id = intval($_GET["edit"]);


    $stmt = $conn->prepare(
        "SELECT
            id,
            full_name,
            email,
            role,
            status
         FROM users
         WHERE id = ?
         LIMIT 1"
    );


    $stmt->bind_param(
        "i",
        $id
    );


    $stmt->execute();

    $result = $stmt->get_result();


    if ($result->num_rows === 1) {

        $edit_user = $result->fetch_assoc();

    }


    $stmt->close();
}


/* =========================================================
   SEARCH + FILTER
========================================================= */

$search = trim(
    $_GET["search"] ?? ""
);

$filter_role =
    $_GET["role"] ?? "";

$filter_status =
    $_GET["status"] ?? "";


/*
 * Validate filters.
 */

if (
    !empty($filter_role) &&
    !in_array(
        $filter_role,
        $allowed_roles,
        true
    )
) {

    $filter_role = "";
}


if (
    !empty($filter_status) &&
    !in_array(
        $filter_status,
        $allowed_statuses,
        true
    )
) {

    $filter_status = "";
}


$sql = "
    SELECT
        id,
        full_name,
        email,
        role,
        status,
        created_at
    FROM users
    WHERE 1=1
";


$params = [];
$types = "";


/* Search */

if (!empty($search)) {

    $sql .= "
        AND (
            full_name LIKE ?
            OR email LIKE ?
        )
    ";


    $search_value =
        "%" . $search . "%";


    $params[] =
        $search_value;

    $params[] =
        $search_value;


    $types .= "ss";
}


/* Role */

if (!empty($filter_role)) {

    $sql .= " AND role = ?";


    $params[] =
        $filter_role;


    $types .= "s";
}


/* Status */

if (!empty($filter_status)) {

    $sql .= " AND status = ?";


    $params[] =
        $filter_status;


    $types .= "s";
}


$sql .= " ORDER BY id DESC";


$stmt = $conn->prepare($sql);


if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );
}


$stmt->execute();

$users_result =
    $stmt->get_result();

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
    User Management | SmartHospital
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


/* =========================================================
   MAIN
========================================================= */

.main {

    margin-left: 245px;

    min-height: 100vh;
}


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


/* =========================================================
   CONTENT
========================================================= */

.content {

    padding: 32px 35px;
}


.content-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom: 25px;
}


.content-header h2 {

    font-size: 22px;

    margin-bottom: 5px;
}


.content-header p {

    color: #8494a0;

    font-size: 13px;
}


/* =========================================================
   BUTTONS
========================================================= */

.btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 7px;

    border: none;

    border-radius: 7px;

    padding: 11px 16px;

    font-family: inherit;

    font-size: 13px;

    font-weight: 600;

    text-decoration: none;

    cursor: pointer;

    transition: 0.2s;
}


.btn-primary {

    background: #087f8c;

    color: white;
}


.btn-primary:hover {

    background: #066b76;
}


.btn-secondary {

    background: #eef4f5;

    color: #315568;
}


.btn-secondary:hover {

    background: #e1eaec;
}


.btn-danger {

    background: #fff1f1;

    color: #b42318;
}


.btn-danger:hover {

    background: #ffe2e2;
}


.btn-warning {

    background: #fff8e8;

    color: #9a6700;
}


.btn-warning:hover {

    background: #ffefc7;
}


/* =========================================================
   ALERTS
========================================================= */

.alert {

    padding: 12px 15px;

    border-radius: 8px;

    margin-bottom: 20px;

    font-size: 13px;
}


.alert-success {

    background: #eaf8f0;

    color: #18794e;

    border: 1px solid #c8ead8;
}


.alert-error {

    background: #fff1f1;

    color: #b42318;

    border: 1px solid #f3c7c7;
}


/* =========================================================
   SEARCH
========================================================= */

.filter-card {

    background: white;

    border:
        1px solid #e8eef1;

    border-radius: 12px;

    padding: 18px;

    margin-bottom: 20px;
}


.filters {

    display: grid;

    grid-template-columns:
        2fr 1fr 1fr auto;

    gap: 12px;

    align-items: end;
}


.field label {

    display: block;

    margin-bottom: 6px;

    color: #536a79;

    font-size: 12px;

    font-weight: 600;
}


.field input,
.field select {

    width: 100%;

    height: 42px;

    padding: 0 11px;

    border:
        1px solid #dce6ea;

    border-radius: 7px;

    outline: none;

    font-family: inherit;

    color: #17324d;

    background: white;
}


.field input:focus,
.field select:focus {

    border-color: #087f8c;

    box-shadow:
        0 0 0 3px
        rgba(8,127,140,0.08);
}


/* =========================================================
   TABLE
========================================================= */

.table-card {

    background: white;

    border:
        1px solid #e8eef1;

    border-radius: 12px;

    overflow: hidden;

    box-shadow:
        0 5px 18px
        rgba(23,50,77,0.04);
}


.table-wrapper {

    overflow-x: auto;
}


table {

    width: 100%;

    border-collapse: collapse;

    min-width: 900px;
}


thead {

    background: #f7fafb;
}


th {

    padding: 14px 16px;

    text-align: left;

    color: #657987;

    font-size: 11px;

    text-transform: uppercase;

    letter-spacing: .4px;

    border-bottom:
        1px solid #e7edef;
}


td {

    padding: 15px 16px;

    border-bottom:
        1px solid #edf1f3;

    font-size: 13px;

    vertical-align: middle;
}


tbody tr:hover {

    background: #fbfdfd;
}


.user-name {

    font-weight: 600;

    color: #17324d;
}


.email {

    color: #758793;

    font-size: 12px;
}


/* =========================================================
   BADGES
========================================================= */

.badge {

    display: inline-block;

    padding: 5px 9px;

    border-radius: 20px;

    font-size: 10px;

    font-weight: 700;

    text-transform: capitalize;
}


.badge-admin {

    background: #eeeaff;

    color: #5b43a8;
}


.badge-patient {

    background: #e8f7f8;

    color: #087f8c;
}


.badge-doctor {

    background: #eaf8f0;

    color: #18794e;
}


.badge-nurse {

    background: #fff4e8;

    color: #b05a00;
}


.badge-receptionist {

    background: #eef3ff;

    color: #3d5da8;
}


.status-active {

    background: #eaf8f0;

    color: #18794e;
}


.status-inactive {

    background: #fff1f1;

    color: #b42318;
}


/* =========================================================
   ACTIONS
========================================================= */

.actions {

    display: flex;

    gap: 6px;

    flex-wrap: wrap;
}


.action-btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    min-width: 58px;

    padding: 6px 9px;

    border-radius: 6px;

    text-decoration: none;

    border: none;

    font-family: inherit;

    font-size: 11px;

    font-weight: 600;

    cursor: pointer;
}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    padding: 45px;

    text-align: center;

    color: #8796a1;

    font-size: 13px;
}


/* =========================================================
   MODAL
========================================================= */

.modal {

    position: fixed;

    inset: 0;

    background:
        rgba(16,45,68,0.45);

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 20px;

    z-index: 500;
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
        0 25px 70px
        rgba(0,0,0,0.18);
}


.modal-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom: 22px;
}


.modal-header h3 {

    font-size: 19px;
}


.close {

    border: none;

    background: #f1f5f6;

    width: 32px;
    height: 32px;

    border-radius: 50%;

    cursor: pointer;

    font-size: 17px;

    color: #536a79;

    display: flex;

    align-items: center;

    justify-content: center;
}


.form-group {

    margin-bottom: 17px;
}


.form-group label {

    display: block;

    margin-bottom: 7px;

    font-size: 12px;

    font-weight: 600;

    color: #536a79;
}


.form-group input,
.form-group select {

    width: 100%;

    height: 44px;

    border:
        1px solid #dce6ea;

    border-radius: 7px;

    padding: 0 12px;

    outline: none;

    font-family: inherit;

    color: #17324d;
}


.form-group input:focus,
.form-group select:focus {

    border-color: #087f8c;

    box-shadow:
        0 0 0 3px
        rgba(8,127,140,0.08);
}


.form-actions {

    display: flex;

    justify-content: flex-end;

    gap: 10px;

    margin-top: 22px;
}


.permission-box {

    background: #f5fafb;

    border: 1px solid #e4eef0;

    border-radius: 8px;

    padding: 13px;

    margin-top: 8px;

    color: #536a79;

    font-size: 12px;

    line-height: 1.6;
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 900px) {

    .sidebar {

        width: 70px;

        padding: 20px 10px;
    }


    .logo {

        justify-content: center;

        padding-left: 0;
        padding-right: 0;
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


    .filters {

        grid-template-columns: 1fr 1fr;
    }
}


@media (max-width: 600px) {

    .topbar {

        padding: 0 18px;
    }


    .admin-info {

        display: none;
    }


    .content {

        padding: 22px 15px;
    }


    .content-header {

        align-items: flex-start;

        gap: 15px;

        flex-direction: column;
    }


    .filters {

        grid-template-columns: 1fr;
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


        <a
            href="users.php"
            class="active"
        >

            <span class="menu-icon">
                👥
            </span>

            <span>
                Users
            </span>

        </a>


        <a href="#">

            <span class="menu-icon">
                🏥
            </span>

            <span>
                Departments
            </span>

        </a>


        <a href="#">

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


<!-- =========================================================
     MAIN
========================================================= -->

<div class="main">


<header class="topbar">

    <div class="page-title">

        <h1>
            User Management
        </h1>

        <p>
            Manage SmartHospital accounts and permissions
        </p>

    </div>


    <div class="admin-profile">

        <div class="avatar">

            <?= strtoupper(
                substr(
                    $_SESSION["full_name"] ?? "A",
                    0,
                    1
                )
            ) ?>

        </div>


        <div class="admin-info">

            <strong>

                <?= htmlspecialchars(
                    $_SESSION["full_name"] ?? "Administrator"
                ) ?>

            </strong>


            <span>
                System Administrator
            </span>

        </div>

    </div>

</header>


<main class="content">


<!-- =========================================================
     PAGE HEADER
========================================================= -->

<div class="content-header">

    <div>

        <h2>
            Users
        </h2>

        <p>
            Create, update and manage hospital users.
        </p>

    </div>


    <button
        class="btn btn-primary"
        onclick="openAddModal()"
    >

        + Add User

    </button>

</div>


<!-- =========================================================
     ALERTS
========================================================= -->

<?php if (!empty($message)): ?>

<div class="alert alert-success">

    <?= htmlspecialchars($message) ?>

</div>

<?php endif; ?>


<?php if (!empty($error)): ?>

<div class="alert alert-error">

    <?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>


<!-- =========================================================
     FILTERS
========================================================= -->

<div class="filter-card">

<form method="GET">

<div class="filters">


    <div class="field">

        <label>
            Search
        </label>

        <input
            type="text"
            name="search"
            placeholder="Search name or email..."
            value="<?= htmlspecialchars($search) ?>"
        >

    </div>


    <div class="field">

        <label>
            Role
        </label>

        <select name="role">

            <option value="">
                All Roles
            </option>


            <option
                value="admin"
                <?= $filter_role === "admin"
                    ? "selected"
                    : "" ?>
            >
                Admin
            </option>


            <option
                value="receptionist"
                <?= $filter_role === "receptionist"
                    ? "selected"
                    : "" ?>
            >
                Receptionist
            </option>


            <option
                value="doctor"
                <?= $filter_role === "doctor"
                    ? "selected"
                    : "" ?>
            >
                Doctor
            </option>


            <option
                value="nurse"
                <?= $filter_role === "nurse"
                    ? "selected"
                    : "" ?>
            >
                Nurse
            </option>


            <option
                value="patient"
                <?= $filter_role === "patient"
                    ? "selected"
                    : "" ?>
            >
                Patient
            </option>

        </select>

    </div>


    <div class="field">

        <label>
            Status
        </label>

        <select name="status">

            <option value="">
                All Status
            </option>


            <option
                value="active"
                <?= $filter_status === "active"
                    ? "selected"
                    : "" ?>
            >
                Active
            </option>


            <option
                value="inactive"
                <?= $filter_status === "inactive"
                    ? "selected"
                    : "" ?>
            >
                Inactive
            </option>

        </select>

    </div>


    <div>

        <button
            type="submit"
            class="btn btn-primary"
        >

            Search

        </button>

    </div>

</div>

</form>

</div>


<!-- =========================================================
     USERS TABLE
========================================================= -->

<div class="table-card">

<div class="table-wrapper">

<table>

<thead>

<tr>

    <th>
        User
    </th>

    <th>
        Email
    </th>

    <th>
        Role
    </th>

    <th>
        Status
    </th>

    <th>
        Created
    </th>

    <th>
        Actions
    </th>

</tr>

</thead>


<tbody>


<?php if ($users_result->num_rows > 0): ?>


<?php while ($user = $users_result->fetch_assoc()): ?>


<tr>


<td>

    <div class="user-name">

        <?= htmlspecialchars(
            $user["full_name"]
        ) ?>

    </div>

</td>


<td>

    <div class="email">

        <?= htmlspecialchars(
            $user["email"]
        ) ?>

    </div>

</td>


<td>

    <?php

    $role_class =
        "badge-" .
        strtolower($user["role"]);

    ?>

    <span
        class="badge <?= htmlspecialchars($role_class) ?>"
    >

        <?= htmlspecialchars(
            $user["role"]
        ) ?>

    </span>

</td>


<td>

    <?php

    $status_class =
        $user["status"] === "active"
        ? "status-active"
        : "status-inactive";

    ?>

    <span
        class="badge <?= $status_class ?>"
    >

        <?= htmlspecialchars(
            $user["status"]
        ) ?>

    </span>

</td>


<td>

    <?= date(
        "d M Y",
        strtotime($user["created_at"])
    ) ?>

</td>


<td>

<div class="actions">


    <!-- EDIT -->

    <a
        href="users.php?edit=<?= $user["id"] ?>"
        class="action-btn btn-secondary"
    >

        Edit

    </a>


    <?php if ($user["id"] != $_SESSION["user_id"]): ?>


    <!-- ACTIVATE / DEACTIVATE -->

    <form
        method="POST"
        style="display:inline;"
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($csrf_token) ?>"
        >

        <input
            type="hidden"
            name="id"
            value="<?= $user["id"] ?>"
        >

        <button
            type="submit"
            name="toggle_user"
            class="action-btn btn-warning"
            onclick="return confirm('Change this user status?');"
        >

            <?= $user["status"] === "active"
                ? "Deactivate"
                : "Activate" ?>

        </button>

    </form>


    <!-- DELETE -->

    <form
        method="POST"
        style="display:inline;"
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($csrf_token) ?>"
        >

        <input
            type="hidden"
            name="id"
            value="<?= $user["id"] ?>"
        >

        <button
            type="submit"
            name="delete_user"
            class="action-btn btn-danger"
            onclick="return confirm('Delete this user permanently? This cannot be undone.');"
        >

            Delete

        </button>

    </form>


    <?php else: ?>


        <span class="action-btn btn-secondary">

            Current Account

        </span>


    <?php endif; ?>


</div>

</td>


</tr>


<?php endwhile; ?>


<?php else: ?>


<tr>

<td colspan="6">

    <div class="empty">

        No users found.

    </div>

</td>

</tr>


<?php endif; ?>


</tbody>

</table>

</div>

</div>


</main>

</div>


<!-- =========================================================
     ADD USER MODAL
========================================================= -->

<div
    class="modal"
    id="addModal"
    style="display:none;"
>

<div class="modal-box">


<div class="modal-header">

    <h3>
        Add New User
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


<input
    type="hidden"
    name="csrf_token"
    value="<?= htmlspecialchars($csrf_token) ?>"
>


<div class="form-group">

<label>
    Full Name *
</label>

<input
    type="text"
    name="full_name"
    placeholder="Enter full name"
    maxlength="150"
    required
>

</div>


<div class="form-group">

<label>
    Email Address *
</label>

<input
    type="email"
    name="email"
    placeholder="Enter email address"
    maxlength="150"
    required
>

</div>


<div class="form-group">

<label>
    Password *
</label>

<input
    type="password"
    name="password"
    placeholder="Create password"
    minlength="6"
    required
>

</div>


<div class="form-group">

<label>
    Role *
</label>

<select
    name="role"
    required
>

    <option value="">
        Select role
    </option>

    <option value="admin">
        Administrator
    </option>

    <option value="receptionist">
        Receptionist
    </option>

    <option value="doctor">
        Doctor
    </option>

    <option value="nurse">
        Nurse
    </option>

    <option value="patient">
        Patient
    </option>

</select>


<div class="permission-box">

<strong>
    Permissions:
</strong>

<br>

The selected role determines what
the user can access in SmartHospital.

</div>

</div>


<div class="form-group">

<label>
    Account Status
</label>

<select name="status">

    <option value="active">
        Active
    </option>

    <option value="inactive">
        Inactive
    </option>

</select>

</div>


<div class="form-actions">

    <button
        type="button"
        class="btn btn-secondary"
        onclick="closeAddModal()"
    >
        Cancel
    </button>


    <button
        type="submit"
        name="add_user"
        class="btn btn-primary"
    >

        Create User

    </button>

</div>


</form>

</div>

</div>


<!-- =========================================================
     EDIT MODAL
========================================================= -->

<?php if ($edit_user): ?>


<div
    class="modal"
    style="display:flex;"
>

<div class="modal-box">


<div class="modal-header">

    <h3>
        Edit User
    </h3>


    <a
        href="users.php"
        class="close"
        style="
            text-decoration:none;
        "
    >
        ×
    </a>

</div>


<form method="POST">


<input
    type="hidden"
    name="csrf_token"
    value="<?= htmlspecialchars($csrf_token) ?>"
>


<input
    type="hidden"
    name="id"
    value="<?= $edit_user["id"] ?>"
>


<div class="form-group">

<label>
    Full Name *
</label>

<input
    type="text"
    name="full_name"
    value="<?= htmlspecialchars(
        $edit_user["full_name"]
    ) ?>"
    maxlength="150"
    required
>

</div>


<div class="form-group">

<label>
    Email Address *
</label>

<input
    type="email"
    name="email"
    value="<?= htmlspecialchars(
        $edit_user["email"]
    ) ?>"
    maxlength="150"
    required
>

</div>


<div class="form-group">

<label>
    Role *
</label>


<?php if ($edit_user["id"] == $_SESSION["user_id"]): ?>


<!--
    Logged-in admin cannot change
    their own role.
-->

<input
    type="hidden"
    name="role"
    value="admin"
>


<select disabled>

    <option selected>
        Administrator
    </option>

</select>


<div class="permission-box">

<strong>
    Administrator protection:
</strong>

<br>

Your administrator role cannot be
changed from your own account.

</div>


<?php else: ?>


<select
    name="role"
    required
>

    <option
        value="admin"
        <?= $edit_user["role"] === "admin"
            ? "selected"
            : "" ?>
    >
        Administrator
    </option>


    <option
        value="receptionist"
        <?= $edit_user["role"] === "receptionist"
            ? "selected"
            : "" ?>
    >
        Receptionist
    </option>


    <option
        value="doctor"
        <?= $edit_user["role"] === "doctor"
            ? "selected"
            : "" ?>
    >
        Doctor
    </option>


    <option
        value="nurse"
        <?= $edit_user["role"] === "nurse"
            ? "selected"
            : "" ?>
    >
        Nurse
    </option>


    <option
        value="patient"
        <?= $edit_user["role"] === "patient"
            ? "selected"
            : "" ?>
    >
        Patient
    </option>

</select>


<?php endif; ?>


</div>


<div class="form-group">

<label>
    Account Status
</label>


<?php if ($edit_user["id"] == $_SESSION["user_id"]): ?>


<!--
    Logged-in admin must remain active.
-->

<input
    type="hidden"
    name="status"
    value="active"
>


<select disabled>

    <option selected>
        Active
    </option>

</select>


<div class="permission-box">

<strong>
    Administrator protection:
</strong>

<br>

Your administrator account cannot
be deactivated.

</div>


<?php else: ?>


<select name="status">

    <option
        value="active"
        <?= $edit_user["status"] === "active"
            ? "selected"
            : "" ?>
    >
        Active
    </option>


    <option
        value="inactive"
        <?= $edit_user["status"] === "inactive"
            ? "selected"
            : "" ?>
    >
        Inactive
    </option>

</select>


<?php endif; ?>


</div>


<div class="form-actions">


    <a
        href="users.php"
        class="btn btn-secondary"
    >
        Cancel
    </a>


    <button
        type="submit"
        name="update_user"
        class="btn btn-primary"
    >

        Save Changes

    </button>

</div>


</form>


<hr
    style="
        margin:25px 0;
        border:none;
        border-top:1px solid #edf1f3;
    "
>


<!-- =====================================================
     CHANGE PASSWORD
===================================================== -->

<form method="POST">


<input
    type="hidden"
    name="csrf_token"
    value="<?= htmlspecialchars($csrf_token) ?>"
>


<input
    type="hidden"
    name="id"
    value="<?= $edit_user["id"] ?>"
>


<div class="form-group">

<label>
    Change Password
</label>

<input
    type="password"
    name="new_password"
    minlength="6"
    placeholder="Enter new password"
>

</div>


<button
    type="submit"
    name="change_password"
    class="btn btn-secondary"
>

    Update Password

</button>


</form>


</div>

</div>


<?php endif; ?>


<script>


/* =========================================================
   ADD USER MODAL
========================================================= */

function openAddModal() {

    document.getElementById(
        "addModal"
    ).style.display = "flex";

}


function closeAddModal() {

    document.getElementById(
        "addModal"
    ).style.display = "none";

}


/* =========================================================
   CLOSE MODAL WHEN CLICKING OUTSIDE
========================================================= */

document.addEventListener(
    "click",
    function(event) {

        const modal =
            document.getElementById("addModal");


        if (
            event.target === modal
        ) {

            closeAddModal();

        }

    }
);

</script>


</body>

</html>