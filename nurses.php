
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
   ADD NURSE
========================================================= */

if (isset($_POST["add_nurse"])) {

    $user_id = intval($_POST["user_id"] ?? 0);
    $department_id = intval($_POST["department_id"] ?? 0);
    $specialization = trim($_POST["specialization"] ?? "");
    $license_number = trim($_POST["license_number"] ?? "");
    $status = $_POST["status"] ?? "offline";

    if ($user_id <= 0 || $department_id <= 0) {

        $message = "Please select a nurse and department.";
        $message_type = "error";

    } else {

        $check = $conn->prepare(
            "SELECT id
             FROM nurses
             WHERE user_id = ?"
        );

        $check->bind_param("i", $user_id);
        $check->execute();

        $existing = $check->get_result();

        if ($existing->num_rows > 0) {

            $message = "This user is already registered as a nurse.";
            $message_type = "error";

        } else {

            $stmt = $conn->prepare(
                "INSERT INTO nurses
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

                $message = "Nurse added successfully.";
                $message_type = "success";

            } else {

                $message = "Unable to add nurse: " . $stmt->error;
                $message_type = "error";
            }

            $stmt->close();
        }

        $check->close();
    }
}

/* =========================================================
   DELETE NURSE
========================================================= */

if (isset($_GET["delete"])) {

    $nurse_id = intval($_GET["delete"]);

    if ($nurse_id > 0) {

        $stmt = $conn->prepare(
            "DELETE FROM nurses
             WHERE id = ?"
        );

        $stmt->bind_param("i", $nurse_id);

        if ($stmt->execute()) {

            $message = "Nurse deleted successfully.";
            $message_type = "success";

        } else {

            $message = "Unable to delete nurse.";
            $message_type = "error";
        }

        $stmt->close();
    }
}

/* =========================================================
   EDIT NURSE
========================================================= */

if (isset($_POST["update_nurse"])) {

    $nurse_id = intval($_POST["nurse_id"] ?? 0);
    $department_id = intval($_POST["department_id"] ?? 0);
    $specialization = trim($_POST["specialization"] ?? "");
    $license_number = trim($_POST["license_number"] ?? "");
    $status = $_POST["status"] ?? "offline";

    if ($nurse_id <= 0 || $department_id <= 0) {

        $message = "Invalid nurse or department.";
        $message_type = "error";

    } else {

        $stmt = $conn->prepare(
            "UPDATE nurses
             SET
                department_id = ?,
                specialization = ?,
                license_number = ?,
                status = ?
             WHERE id = ?"
        );

        $stmt->bind_param(
            "isssi",
            $department_id,
            $specialization,
            $license_number,
            $status,
            $nurse_id
        );

        if ($stmt->execute()) {

            $message = "Nurse updated successfully.";
            $message_type = "success";

        } else {

            $message = "Unable to update nurse: " . $stmt->error;
            $message_type = "error";
        }

        $stmt->close();
    }
}

/* =========================================================
   STATISTICS
========================================================= */

$total_nurses = 0;
$available_nurses = 0;
$busy_nurses = 0;
$offline_nurses = 0;

$result = $conn->query(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 'available'), 0) AS available,
        COALESCE(SUM(status = 'busy'), 0) AS busy,
        COALESCE(SUM(status = 'offline'), 0) AS offline
     FROM nurses"
);

if ($result) {

    $row = $result->fetch_assoc();

    $total_nurses = intval($row["total"]);
    $available_nurses = intval($row["available"]);
    $busy_nurses = intval($row["busy"]);
    $offline_nurses = intval($row["offline"]);
}

/* =========================================================
   SEARCH
========================================================= */

$search = trim($_GET["search"] ?? "");

if ($search !== "") {

    $stmt = $conn->prepare(
        "SELECT
            nurses.id,
            nurses.user_id,
            nurses.department_id,
            nurses.specialization,
            nurses.license_number,
            nurses.status,
            nurses.created_at,
            users.full_name,
            users.email,
            departments.name AS department_name
         FROM nurses
         INNER JOIN users
             ON nurses.user_id = users.id
         INNER JOIN departments
             ON nurses.department_id = departments.id
         WHERE
            users.full_name LIKE ?
            OR users.email LIKE ?
            OR departments.name LIKE ?
            OR nurses.specialization LIKE ?
            OR nurses.license_number LIKE ?
         ORDER BY nurses.id DESC"
    );

    $search_value = "%" . $search . "%";

    $stmt->bind_param(
        "sssss",
        $search_value,
        $search_value,
        $search_value,
        $search_value,
        $search_value
    );

    $stmt->execute();

    $nurses = $stmt->get_result();

} else {

    $nurses = $conn->query(
        "SELECT
            nurses.id,
            nurses.user_id,
            nurses.department_id,
            nurses.specialization,
            nurses.license_number,
            nurses.status,
            nurses.created_at,
            users.full_name,
            users.email,
            departments.name AS department_name
         FROM nurses
         INNER JOIN users
             ON nurses.user_id = users.id
         INNER JOIN departments
             ON nurses.department_id = departments.id
         ORDER BY nurses.id DESC"
    );
}

/* =========================================================
   NURSE USERS
========================================================= */

$nurse_users = $conn->query(
    "SELECT
        u.id,
        u.full_name,
        u.email
     FROM users u
     WHERE u.role = 'nurse'
       AND u.status = 'active'
       AND NOT EXISTS (
            SELECT 1
            FROM nurses n
            WHERE n.user_id = u.id
       )
     ORDER BY u.full_name ASC"
);

if (!$nurse_users) {
    die("Nurse users query failed: " . $conn->error);
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
    die("Departments query failed: " . $conn->error);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Nurse Management | SmartHospital</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

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
    color: #17324d;
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
    font-size: 14px;
}

.admin-info {
    line-height: 1.3;
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

.add-btn:hover {
    background: #066b76;
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
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border: 1px solid #e9eff2;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 5px 18px rgba(23,50,77,0.04);
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
    font-size: 23px;
    margin-bottom: 3px;
}

.stat-card p {
    color: #8796a1;
    font-size: 12px;
}

/* =========================================================
   CARD
========================================================= */

.card {
    background: white;
    border: 1px solid #e9eff2;
    border-radius: 12px;
    padding: 23px;
    box-shadow: 0 5px 18px rgba(23,50,77,0.04);
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

.search-box input:focus {
    border-color: #087f8c;
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
}

th {
    background: #f7fafb;
    color: #71828e;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .5px;
    padding: 13px;
    text-align: left;
    border-bottom: 1px solid #e8eef1;
}

td {
    padding: 15px 13px;
    border-bottom: 1px solid #edf1f3;
    font-size: 13px;
}

.nurse-name {
    font-weight: 600;
    color: #17324d;
}

.email {
    color: #8796a1;
    font-size: 11px;
    margin-top: 3px;
}

.badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
    text-transform: capitalize;
}

.badge.available {
    background: #e7f7ee;
    color: #16834d;
}

.badge.busy {
    background: #fff5df;
    color: #a66a00;
}

.badge.offline {
    background: #eef1f3;
    color: #687781;
}

.action-btn {
    display: inline-block;
    padding: 6px 9px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 11px;
    margin-right: 4px;
}

.edit-btn {
    background: #e8f7f8;
    color: #087f8c;
}

.delete-btn {
    background: #fff0ef;
    color: #b42318;
}

.empty {
    text-align: center;
    padding: 45px 20px;
    color: #8796a1;
}

.empty-icon {
    font-size: 35px;
    margin-bottom: 10px;
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
.form-group select {
    width: 100%;
    padding: 11px 12px;
    border: 1px solid #dce6ea;
    border-radius: 8px;
    outline: none;
    font-size: 13px;
    background: white;
}

.form-group input:focus,
.form-group select:focus {
    border-color: #087f8c;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

.cancel-btn {
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

@media (max-width: 1100px) {

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
        align-items: flex-start;
        gap: 15px;
        flex-direction: column;
    }

    .stats {
        grid-template-columns: 1fr;
    }

    .search-box {
        flex-direction: column;
    }

    .search-box button,
    .search-box a {
        text-align: center;
    }
}

</style>

</head>

<body>

<!-- =========================================================
     SIDEBAR
========================================================= -->

<aside class="sidebar">

    <a href="admin_dashboard.php" class="logo">

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

        <a href="nurses.php" class="active">

            <span class="menu-icon">
                🧑‍⚕️
            </span>

            <span>
                Nurses
            </span>

        </a>

        <div class="menu-title" style="margin-top:25px;">
            Management
        </div>

        <!-- QUEUE: FIXED -->

        <a href="queue.php">

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
                Nurse Management
            </h1>

            <p>
                SmartHospital Queue Management System
            </p>

        </div>

        <div class="admin-profile">

            <div class="avatar">

                <?= htmlspecialchars(
                    strtoupper(
                        substr($full_name, 0, 1)
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
                    Nurses
                </h2>

                <p>
                    Register and manage hospital nurses.
                </p>

            </div>

            <button
                type="button"
                class="add-btn"
                onclick="openAddModal()"
            >
                + Add Nurse
            </button>

        </div>


        <?php if ($message !== ""): ?>

            <div class="message <?= htmlspecialchars($message_type) ?>">

                <?= htmlspecialchars($message) ?>

            </div>

        <?php endif; ?>


        <!-- STATISTICS -->

        <section class="stats">

            <div class="stat-card">

                <div class="stat-icon">
                    🧑‍⚕️
                </div>

                <div>

                    <h3>
                        <?= $total_nurses ?>
                    </h3>

                    <p>
                        Total Nurses
                    </p>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon">
                    🟢
                </div>

                <div>

                    <h3>
                        <?= $available_nurses ?>
                    </h3>

                    <p>
                        Available
                    </p>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon">
                    🟠
                </div>

                <div>

                    <h3>
                        <?= $busy_nurses ?>
                    </h3>

                    <p>
                        Busy
                    </p>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon">
                    ⚫
                </div>

                <div>

                    <h3>
                        <?= $offline_nurses ?>
                    </h3>

                    <p>
                        Offline
                    </p>

                </div>

            </div>

        </section>


        <!-- NURSE TABLE -->

        <section class="card">

            <div class="card-header">

                <h3>
                    Hospital Nurses
                </h3>

                <span>
                    <?= $total_nurses ?> nurse(s)
                </span>

            </div>


            <!-- SEARCH -->

            <form
                method="GET"
                class="search-box"
            >

                <input
                    type="text"
                    name="search"
                    value="<?= htmlspecialchars($search) ?>"
                    placeholder="Search nurse, email, department, specialization or license..."
                >

                <button type="submit">
                    Search
                </button>

                <?php if ($search !== ""): ?>

                    <a href="nurses.php">
                        Clear
                    </a>

                <?php endif; ?>

            </form>


            <!-- TABLE -->

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>Nurse</th>

                            <th>Department</th>

                            <th>Specialization</th>

                            <th>License</th>

                            <th>Status</th>

                            <th>Actions</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if ($nurses && $nurses->num_rows > 0): ?>

                        <?php while ($nurse = $nurses->fetch_assoc()): ?>

                            <tr>

                                <td>

                                    <div class="nurse-name">

                                        <?= htmlspecialchars(
                                            $nurse["full_name"]
                                        ) ?>

                                    </div>

                                    <div class="email">

                                        <?= htmlspecialchars(
                                            $nurse["email"]
                                        ) ?>

                                    </div>

                                </td>

                                <td>

                                    <?= htmlspecialchars(
                                        $nurse["department_name"]
                                    ) ?>

                                </td>

                                <td>

                                    <?= htmlspecialchars(
                                        $nurse["specialization"] ?: "—"
                                    ) ?>

                                </td>

                                <td>

                                    <?= htmlspecialchars(
                                        $nurse["license_number"] ?: "—"
                                    ) ?>

                                </td>

                                <td>

                                    <span class="badge <?= htmlspecialchars($nurse["status"]) ?>">

                                        <?= htmlspecialchars(
                                            $nurse["status"]
                                        ) ?>

                                    </span>

                                </td>

                                <td>

                                    <a
                                        href="#"
                                        class="action-btn edit-btn"
                                        onclick='openEditModal(<?= json_encode($nurse) ?>); return false;'
                                    >
                                        Edit
                                    </a>

                                    <a
                                        href="nurses.php?delete=<?= (int)$nurse["id"] ?>"
                                        class="action-btn delete-btn"
                                        onclick="return confirm('Are you sure you want to delete this nurse?');"
                                    >
                                        Delete
                                    </a>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="6">

                                <div class="empty">

                                    <div class="empty-icon">
                                        🧑‍⚕️
                                    </div>

                                    <strong>
                                        No nurses found.
                                    </strong>

                                    <p style="margin-top:6px;">
                                        Add a nurse to start managing hospital nurses.
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
     ADD NURSE MODAL
========================================================= -->

<div
    class="modal"
    id="addModal"
>

    <div class="modal-box">

        <div class="modal-header">

            <h3>
                Add Nurse
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
                    Nurse User
                </label>

                <select
                    name="user_id"
                    required
                >

                    <option value="">
                        Select nurse
                    </option>

                    <?php if ($nurse_users->num_rows > 0): ?>

                        <?php while ($user = $nurse_users->fetch_assoc()): ?>

                            <option value="<?= (int)$user["id"] ?>">

                                <?= htmlspecialchars(
                                    $user["full_name"]
                                ) ?>

                                —
                                <?= htmlspecialchars(
                                    $user["email"]
                                ) ?>

                            </option>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <option value="" disabled>
                            No available nurse users
                        </option>

                    <?php endif; ?>

                </select>

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

                    <?php if ($departments->num_rows > 0): ?>

                        <?php while ($department = $departments->fetch_assoc()): ?>

                            <option value="<?= (int)$department["id"] ?>">

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


            <div class="form-group">

                <label>
                    Specialization
                </label>

                <input
                    type="text"
                    name="specialization"
                    placeholder="e.g. General Nursing"
                >

            </div>


            <div class="form-group">

                <label>
                    License Number
                </label>

                <input
                    type="text"
                    name="license_number"
                    placeholder="Enter professional license number"
                >

            </div>


            <div class="form-group">

                <label>
                    Status
                </label>

                <select name="status">

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


            <div class="form-actions">

                <button
                    type="button"
                    class="cancel-btn"
                    onclick="closeAddModal()"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    name="add_nurse"
                    class="save-btn"
                >
                    Add Nurse
                </button>

            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     EDIT NURSE MODAL
========================================================= -->

<div
    class="modal"
    id="editModal"
>

    <div class="modal-box">

        <div class="modal-header">

            <h3>
                Edit Nurse
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
                name="nurse_id"
                id="edit_nurse_id"
            >


            <div class="form-group">

                <label>
                    Nurse
                </label>

                <input
                    type="text"
                    id="edit_nurse_name"
                    readonly
                >

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

                    <?php

                    $edit_departments = $conn->query(
                        "SELECT
                            id,
                            name
                         FROM departments
                         WHERE status = 'active'
                         ORDER BY name ASC"
                    );

                    ?>

                    <?php if ($edit_departments && $edit_departments->num_rows > 0): ?>

                        <?php while ($department = $edit_departments->fetch_assoc()): ?>

                            <option
                                value="<?= (int)$department["id"] ?>"
                            >

                                <?= htmlspecialchars(
                                    $department["name"]
                                ) ?>

                            </option>

                        <?php endwhile; ?>

                    <?php endif; ?>

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


            <div class="form-actions">

                <button
                    type="button"
                    class="cancel-btn"
                    onclick="closeEditModal()"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    name="update_nurse"
                    class="save-btn"
                >
                    Save Changes
                </button>

            </div>

        </form>

    </div>

</div>


<script>

/* =========================================================
   ADD MODAL
========================================================= */

function openAddModal() {

    document.getElementById("addModal").style.display = "flex";

}

function closeAddModal() {

    document.getElementById("addModal").style.display = "none";

}


/* =========================================================
   EDIT MODAL
========================================================= */

function openEditModal(nurse) {

    document.getElementById("editModal").style.display = "flex";

    document.getElementById("edit_nurse_id").value =
        nurse.id;

    document.getElementById("edit_nurse_name").value =
        nurse.full_name;

    document.getElementById("edit_department_id").value =
        nurse.department_id;

    document.getElementById("edit_specialization").value =
        nurse.specialization || "";

    document.getElementById("edit_license_number").value =
        nurse.license_number || "";

    document.getElementById("edit_status").value =
        nurse.status;
}

function closeEditModal() {

    document.getElementById("editModal").style.display = "none";

}


/* =========================================================
   CLOSE MODALS OUTSIDE
========================================================= */

window.onclick = function(event) {

    const addModal =
        document.getElementById("addModal");

    const editModal =
        document.getElementById("editModal");

    if (event.target === addModal) {

        closeAddModal();

    }

    if (event.target === editModal) {

        closeEditModal();

    }

};

</script>

</body>

</html>
```
