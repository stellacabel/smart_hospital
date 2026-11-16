```php
<?php

session_start();

require_once "db.php";

/* =========================================================
   SECURITY
========================================================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}

$admin_id = (int) $_SESSION["user_id"];


/* =========================================================
   CSRF TOKEN
========================================================= */

if (!isset($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION["csrf_token"];


/* =========================================================
   MESSAGE VARIABLES
========================================================= */

$error = "";
$success = "";


/* =========================================================
   VERIFY / REJECT PAYMENT
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /* -----------------------------------------------------
       CSRF CHECK
    ----------------------------------------------------- */

    if (
        !isset($_POST["csrf_token"]) ||
        !isset($_SESSION["csrf_token"]) ||
        !hash_equals(
            $_SESSION["csrf_token"],
            $_POST["csrf_token"]
        )
    ) {

        $error = "Invalid security token. Please try again.";

    } else {

        $action = trim($_POST["action"] ?? "");
        $payment_id = (int) ($_POST["payment_id"] ?? 0);

        if ($payment_id <= 0) {

            $error = "Invalid payment selected.";

        } elseif (
            !in_array(
                $action,
                ["verify", "reject"],
                true
            )
        ) {

            $error = "Invalid payment action.";

        } else {

            /* -------------------------------------------------
               GET PAYMENT
            ------------------------------------------------- */

            $payment_sql = "
                SELECT
                    id,
                    patient_id,
                    prescription_id,
                    amount,
                    payment_status
                FROM payments
                WHERE id = ?
                LIMIT 1
            ";

            $payment_stmt = $conn->prepare($payment_sql);

            if (!$payment_stmt) {

                $error =
                    "Payment query failed: " .
                    $conn->error;

            } else {

                $payment_stmt->bind_param(
                    "i",
                    $payment_id
                );

                $payment_stmt->execute();

                $payment_result =
                    $payment_stmt->get_result();

                if ($payment_result->num_rows === 0) {

                    $error =
                        "Payment record was not found.";

                } else {

                    $payment =
                        $payment_result->fetch_assoc();

                    $patient_id =
                        (int) $payment["patient_id"];

                    $prescription_id =
                        (int) $payment["prescription_id"];

                    $current_status =
                        $payment["payment_status"];


                    /* =========================================
                       VERIFY PAYMENT
                    ========================================= */

                    if ($action === "verify") {

                        if ($current_status !== "Pending") {

                            $error =
                                "This payment has already been processed.";

                        } else {

                            $conn->begin_transaction();

                            try {

                                /* ---------------------------------
                                   UPDATE PAYMENT
                                --------------------------------- */

                                $update_payment_sql = "
                                    UPDATE payments
                                    SET
                                        payment_status = 'Paid',
                                        verified_by = ?,
                                        verified_at = NOW()
                                    WHERE id = ?
                                ";

                                $update_payment_stmt =
                                    $conn->prepare(
                                        $update_payment_sql
                                    );

                                if (!$update_payment_stmt) {

                                    throw new Exception(
                                        "Could not prepare payment update: " .
                                        $conn->error
                                    );
                                }

                                $update_payment_stmt->bind_param(
                                    "ii",
                                    $admin_id,
                                    $payment_id
                                );

                                if (
                                    !$update_payment_stmt->execute()
                                ) {

                                    throw new Exception(
                                        "Could not update payment: " .
                                        $update_payment_stmt->error
                                    );
                                }


                                /* ---------------------------------
                                   UPDATE PRESCRIPTION
                                --------------------------------- */

                                if ($prescription_id > 0) {

                                    $update_prescription_sql = "
                                        UPDATE prescriptions
                                        SET status = 'paid'
                                        WHERE id = ?
                                    ";

                                    $update_prescription_stmt =
                                        $conn->prepare(
                                            $update_prescription_sql
                                        );

                                    if (!$update_prescription_stmt) {

                                        throw new Exception(
                                            "Could not prepare prescription update: " .
                                            $conn->error
                                        );
                                    }

                                    $update_prescription_stmt->bind_param(
                                        "i",
                                        $prescription_id
                                    );

                                    if (
                                        !$update_prescription_stmt->execute()
                                    ) {

                                        throw new Exception(
                                            "Could not update prescription: " .
                                            $update_prescription_stmt->error
                                        );
                                    }
                                }


                                /* ---------------------------------
                                   COMMIT
                                --------------------------------- */

                                $conn->commit();

                                $success =
                                    "Payment verified successfully. " .
                                    "The prescription has been marked as paid.";

                            } catch (Exception $e) {

                                $conn->rollback();

                                $error =
                                    $e->getMessage();
                            }
                        }


                    /* =========================================
                       REJECT PAYMENT
                    ========================================= */

                    } elseif ($action === "reject") {

                        if ($current_status !== "Pending") {

                            $error =
                                "This payment has already been processed.";

                        } else {

                            $update_reject_sql = "
                                UPDATE payments
                                SET
                                    payment_status = 'Rejected',
                                    verified_by = ?,
                                    verified_at = NOW()
                                WHERE id = ?
                            ";

                            $update_reject_stmt =
                                $conn->prepare(
                                    $update_reject_sql
                                );

                            if (!$update_reject_stmt) {

                                $error =
                                    "Could not reject payment: " .
                                    $conn->error;

                            } else {

                                $update_reject_stmt->bind_param(
                                    "ii",
                                    $admin_id,
                                    $payment_id
                                );

                                if (
                                    $update_reject_stmt->execute()
                                ) {

                                    $success =
                                        "Payment rejected successfully.";

                                } else {

                                    $error =
                                        "Could not reject payment: " .
                                        $update_reject_stmt->error;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}


/* =========================================================
   FILTER
========================================================= */

$status_filter =
    trim($_GET["status"] ?? "all");

$allowed_filters = [
    "all",
    "Pending",
    "Paid",
    "Rejected"
];

if (
    !in_array(
        $status_filter,
        $allowed_filters,
        true
    )
) {
    $status_filter = "all";
}


/* =========================================================
   GET PAYMENTS
========================================================= */

$payments = [];

if ($status_filter === "all") {

    $payments_sql = "
        SELECT
            p.id,
            p.patient_id,
            p.prescription_id,
            p.invoice_number,
            p.amount,
            p.insurance_amount,
            p.patient_amount,
            p.payment_method,
            p.payment_status,
            p.transaction_reference,
            p.paid_at,
            p.verified_by,
            p.verified_at,
            p.created_at,

            pt.patient_number,
            pt.full_name AS patient_name,
            pt.phone,

            v.full_name AS verifier_name

        FROM payments p

        INNER JOIN patients pt
            ON p.patient_id = pt.id

        LEFT JOIN users v
            ON p.verified_by = v.id

        ORDER BY
            p.id DESC
    ";

    $payments_stmt =
        $conn->prepare($payments_sql);

} else {

    $payments_sql = "
        SELECT
            p.id,
            p.patient_id,
            p.prescription_id,
            p.invoice_number,
            p.amount,
            p.insurance_amount,
            p.patient_amount,
            p.payment_method,
            p.payment_status,
            p.transaction_reference,
            p.paid_at,
            p.verified_by,
            p.verified_at,
            p.created_at,

            pt.patient_number,
            pt.full_name AS patient_name,
            pt.phone,

            v.full_name AS verifier_name

        FROM payments p

        INNER JOIN patients pt
            ON p.patient_id = pt.id

        LEFT JOIN users v
            ON p.verified_by = v.id

        WHERE p.payment_status = ?

        ORDER BY
            p.id DESC
    ";

    $payments_stmt =
        $conn->prepare($payments_sql);
}


if (!$payments_stmt) {

    die(
        "Payments query failed: " .
        htmlspecialchars($conn->error)
    );
}


if ($status_filter === "all") {

    $payments_stmt->execute();

} else {

    $payments_stmt->bind_param(
        "s",
        $status_filter
    );

    $payments_stmt->execute();
}


$payments_result =
    $payments_stmt->get_result();


while (
    $payment_row =
    $payments_result->fetch_assoc()
) {

    $payments[] = $payment_row;
}


/* =========================================================
   STATISTICS
========================================================= */

$total_payments = 0;
$pending_payments = 0;
$paid_payments = 0;
$rejected_payments = 0;
$total_amount = 0;


$stats_sql = "
    SELECT
        COUNT(*) AS total_payments,

        SUM(
            payment_status = 'Pending'
        ) AS pending_payments,

        SUM(
            payment_status = 'Paid'
        ) AS paid_payments,

        SUM(
            payment_status = 'Rejected'
        ) AS rejected_payments,

        COALESCE(
            SUM(
                CASE
                    WHEN payment_status = 'Paid'
                    THEN patient_amount
                    ELSE 0
                END
            ),
            0
        ) AS total_amount

    FROM payments
";


$stats_result =
    $conn->query($stats_sql);


if ($stats_result) {

    $stats =
        $stats_result->fetch_assoc();

    $total_payments =
        (int) ($stats["total_payments"] ?? 0);

    $pending_payments =
        (int) ($stats["pending_payments"] ?? 0);

    $paid_payments =
        (int) ($stats["paid_payments"] ?? 0);

    $rejected_payments =
        (int) ($stats["rejected_payments"] ?? 0);

    $total_amount =
        (float) ($stats["total_amount"] ?? 0);
}


/* =========================================================
   ADMIN NAME
========================================================= */

$admin_name = "Administrator";

$admin_sql = "
    SELECT full_name
    FROM users
    WHERE id = ?
    LIMIT 1
";

$admin_stmt =
    $conn->prepare($admin_sql);

if ($admin_stmt) {

    $admin_stmt->bind_param(
        "i",
        $admin_id
    );

    $admin_stmt->execute();

    $admin_result =
        $admin_stmt->get_result();

    if ($admin_result->num_rows > 0) {

        $admin_row =
            $admin_result->fetch_assoc();

        $admin_name =
            $admin_row["full_name"];
    }
}


$initial =
    strtoupper(
        substr(
            trim($admin_name),
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

<title>Payments | SmartHospital</title>

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

    overflow-y: auto;
}

.logo {

    display: flex;

    align-items: center;

    gap: 10px;

    padding: 0 10px 30px;

    border-bottom:
        1px solid
        rgba(255,255,255,.10);

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
        rgba(255,255,255,.08);

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

    max-width: 1500px;
}


/* =========================================================
   ALERTS
========================================================= */

.alert {

    padding: 14px 18px;

    border-radius: 9px;

    margin-bottom: 20px;

    font-size: 13px;
}

.alert.success {

    background: #e8f7f0;

    color: #16834d;

    border:
        1px solid
        #c8ead8;
}

.alert.error {

    background: #fff0ef;

    color: #b42318;

    border:
        1px solid
        #f4cfcb;
}


/* =========================================================
   STAT CARDS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(5, 1fr);

    gap: 16px;

    margin-bottom: 25px;
}

.stat-card {

    background: white;

    border:
        1px solid
        #e9eff2;

    border-radius: 13px;

    padding: 20px;

    box-shadow:
        0 5px 18px
        rgba(23,50,77,.04);
}

.stat-icon {

    width: 40px;
    height: 40px;

    border-radius: 9px;

    background: #e8f7f8;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 19px;

    margin-bottom: 12px;
}

.stat-card h3 {

    font-size: 24px;

    margin-bottom: 4px;
}

.stat-card p {

    color: #8494a0;

    font-size: 11px;
}


/* =========================================================
   FILTER BAR
========================================================= */

.filter-bar {

    background: white;

    border:
        1px solid
        #e9eff2;

    border-radius: 13px;

    padding: 16px 20px;

    margin-bottom: 20px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;
}

.filter-title {

    font-weight: 700;

    font-size: 14px;
}

.filters {

    display: flex;

    gap: 8px;

    flex-wrap: wrap;
}

.filters a {

    text-decoration: none;

    padding: 8px 14px;

    border-radius: 7px;

    font-size: 11px;

    font-weight: 700;

    color: #687781;

    background: #f4f7f8;
}

.filters a:hover,
.filters a.active {

    background: #087f8c;

    color: white;
}


/* =========================================================
   TABLE CARD
========================================================= */

.table-card {

    background: white;

    border:
        1px solid
        #e9eff2;

    border-radius: 13px;

    overflow: hidden;

    box-shadow:
        0 5px 18px
        rgba(23,50,77,.04);
}

.table-header {

    padding: 20px 22px;

    border-bottom:
        1px solid
        #edf1f3;

    display: flex;

    align-items: center;

    justify-content: space-between;
}

.table-header h2 {
    font-size: 16px;
}

.table-header span {

    color: #8796a1;

    font-size: 11px;
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

    min-width: 1000px;
}

th {

    background: #f8fafb;

    color: #687781;

    font-size: 10px;

    text-transform: uppercase;

    letter-spacing: .5px;

    padding: 13px 15px;

    text-align: left;

    white-space: nowrap;
}

td {

    padding: 16px 15px;

    border-top:
        1px solid
        #edf1f3;

    font-size: 12px;

    vertical-align: middle;
}

.patient-name {

    font-weight: 700;

    color: #17324d;
}

.patient-number {

    color: #8796a1;

    font-size: 10px;

    margin-top: 3px;
}

.invoice {

    color: #087f8c;

    font-weight: 700;
}

.amount {

    font-weight: 800;

    white-space: nowrap;
}


/* =========================================================
   STATUS
========================================================= */

.status {

    display: inline-block;

    padding: 5px 9px;

    border-radius: 20px;

    font-size: 10px;

    font-weight: 700;
}

.status.pending {

    background: #fff4d6;

    color: #9a6b00;
}

.status.paid {

    background: #e8f7f0;

    color: #16834d;
}

.status.rejected {

    background: #fff0ef;

    color: #b42318;
}


/* =========================================================
   METHOD
========================================================= */

.method {

    font-weight: 600;

    color: #52636e;
}


/* =========================================================
   BUTTONS
========================================================= */

.actions {

    display: flex;

    gap: 7px;

    align-items: center;
}

.action-btn {

    border: none;

    padding: 7px 10px;

    border-radius: 7px;

    font-size: 10px;

    font-weight: 700;

    cursor: pointer;
}

.verify-btn {

    background: #087f8c;

    color: white;
}

.verify-btn:hover {

    background: #066b76;
}

.reject-btn {

    background: #fff0ef;

    color: #b42318;
}

.reject-btn:hover {

    background: #f8d9d5;
}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    text-align: center;

    padding: 60px 20px;

    color: #8796a1;
}

.empty-icon {

    font-size: 40px;

    margin-bottom: 12px;
}

.empty h3 {

    color: #52636e;

    font-size: 15px;

    margin-bottom: 5px;
}

.empty p {

    font-size: 11px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1100px) {

    .stats {

        grid-template-columns:
            repeat(3, 1fr);
    }
}

@media (max-width: 700px) {

    .sidebar {

        width: 70px;

        padding: 20px 10px;
    }

    .logo {

        justify-content: center;

        padding:
            0 0 20px;
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

        grid-template-columns: 1fr 1fr;
    }

    .filter-bar {

        align-items: flex-start;

        flex-direction: column;
    }
}

</style>

</head>


<body>


<!-- =======================================================
     SIDEBAR
======================================================= -->

<aside class="sidebar">

    <!-- IMPORTANT:
         Dashboard must be admin_dashboard.php
    -->

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


        <!-- FIXED DASHBOARD LINK -->

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


        <a href="queue.php">

            <span class="menu-icon">
                🎫
            </span>

            <span>
                Queue
            </span>

        </a>


        <a
            href="admin_payments.php"
            class="active"
        >

            <span class="menu-icon">
                💳
            </span>

            <span>
                Payments
            </span>

        </a>


        <a href="admin_consultations.php">

            <span class="menu-icon">
                📋
            </span>

            <span>
                Consultation Reports
            </span>

        </a>


        <a href="reports.php">

            <span class="menu-icon">
                📊
            </span>

            <span>
                Reports
            </span>

        </a>


        <a href="settings.php">

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



<!-- =======================================================
     MAIN
======================================================= -->

<div class="main">


    <header class="topbar">

        <div class="page-title">

            <h1>
                Payments
            </h1>

            <p>
                Manage patient payments and transactions
            </p>

        </div>


        <div class="profile">

            <div class="avatar">

                <?= htmlspecialchars($initial) ?>

            </div>


            <div class="profile-info">

                <strong>

                    <?= htmlspecialchars($admin_name) ?>

                </strong>

                <span>
                    System Administrator
                </span>

            </div>

        </div>

    </header>



    <main class="content">


        <!-- =================================================
             ALERTS
        ================================================== -->

        <?php if ($success !== ""): ?>

            <div class="alert success">

                ✅

                <?= htmlspecialchars($success) ?>

            </div>

        <?php endif; ?>


        <?php if ($error !== ""): ?>

            <div class="alert error">

                ❌

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>



        <!-- =================================================
             STATISTICS
        ================================================== -->

        <div class="stats">


            <div class="stat-card">

                <div class="stat-icon">
                    💳
                </div>

                <h3>
                    <?= $total_payments ?>
                </h3>

                <p>
                    Total Payments
                </p>

            </div>


            <div class="stat-card">

                <div class="stat-icon">
                    ⏳
                </div>

                <h3>
                    <?= $pending_payments ?>
                </h3>

                <p>
                    Pending Payments
                </p>

            </div>


            <div class="stat-card">

                <div class="stat-icon">
                    ✅
                </div>

                <h3>
                    <?= $paid_payments ?>
                </h3>

                <p>
                    Paid Payments
                </p>

            </div>


            <div class="stat-card">

                <div class="stat-icon">
                    ❌
                </div>

                <h3>
                    <?= $rejected_payments ?>
                </h3>

                <p>
                    Rejected Payments
                </p>

            </div>


            <div class="stat-card">

                <div class="stat-icon">
                    💰
                </div>

                <h3>
                    <?= number_format(
                        $total_amount,
                        0
                    ) ?>
                </h3>

                <p>
                    Confirmed Revenue (RWF)
                </p>

            </div>


        </div>



        <!-- =================================================
             FILTERS
        ================================================== -->

        <div class="filter-bar">

            <div class="filter-title">
                Payment Records
            </div>


            <div class="filters">

                <a
                    href="admin_payments.php"
                    class="<?= $status_filter === "all"
                        ? "active"
                        : "" ?>"
                >
                    All
                </a>


                <a
                    href="admin_payments.php?status=Pending"
                    class="<?= $status_filter === "Pending"
                        ? "active"
                        : "" ?>"
                >
                    Pending
                </a>


                <a
                    href="admin_payments.php?status=Paid"
                    class="<?= $status_filter === "Paid"
                        ? "active"
                        : "" ?>"
                >
                    Paid
                </a>


                <a
                    href="admin_payments.php?status=Rejected"
                    class="<?= $status_filter === "Rejected"
                        ? "active"
                        : "" ?>"
                >
                    Rejected
                </a>

            </div>

        </div>



        <!-- =================================================
             PAYMENT TABLE
        ================================================== -->

        <section class="table-card">


            <div class="table-header">

                <h2>
                    Patient Payments
                </h2>

                <span>

                    <?= count($payments) ?>

                    record(s)

                </span>

            </div>


            <?php if (empty($payments)): ?>


                <div class="empty">

                    <div class="empty-icon">
                        💳
                    </div>

                    <h3>
                        No payment records
                    </h3>

                    <p>
                        Patient payment transactions
                        will appear here.
                    </p>

                </div>


            <?php else: ?>


                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Invoice
                                </th>

                                <th>
                                    Patient
                                </th>

                                <th>
                                    Prescription
                                </th>

                                <th>
                                    Amount
                                </th>

                                <th>
                                    Method
                                </th>

                                <th>
                                    Reference
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Created
                                </th>

                                <th>
                                    Action
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach (
                            $payments
                            as $payment
                        ): ?>


                            <tr>


                                <td>

                                    <div class="invoice">

                                        <?= htmlspecialchars(
                                            $payment["invoice_number"] ?? ""
                                        ) ?>

                                    </div>

                                </td>


                                <td>

                                    <div class="patient-name">

                                        <?= htmlspecialchars(
                                            $payment["patient_name"] ?? ""
                                        ) ?>

                                    </div>

                                    <div class="patient-number">

                                        <?= htmlspecialchars(
                                            $payment["patient_number"] ?? ""
                                        ) ?>

                                    </div>

                                </td>


                                <td>

                                    #<?= (int) (
                                        $payment["prescription_id"] ?? 0
                                    ) ?>

                                </td>


                                <td>

                                    <div class="amount">

                                        <?= number_format(
                                            (float) (
                                                $payment["patient_amount"] ?? 0
                                            ),
                                            0
                                        ) ?>

                                        RWF

                                    </div>

                                </td>


                                <td>

                                    <div class="method">

                                        <?= htmlspecialchars(
                                            $payment["payment_method"] ?? ""
                                        ) ?>

                                    </div>

                                </td>


                                <td>

                                    <?php if (
                                        !empty(
                                            $payment[
                                                "transaction_reference"
                                            ]
                                        )
                                    ): ?>

                                        <?= htmlspecialchars(
                                            $payment[
                                                "transaction_reference"
                                            ]
                                        ) ?>

                                    <?php else: ?>

                                        <span
                                            style="
                                                color:#a0adb5;
                                            "
                                        >
                                            —
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?php

                                    $status_class =
                                        strtolower(
                                            trim(
                                                $payment[
                                                    "payment_status"
                                                ] ?? ""
                                            )
                                        );

                                    ?>

                                    <span
                                        class="status <?= htmlspecialchars(
                                            $status_class
                                        ) ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $payment[
                                                "payment_status"
                                            ] ?? ""
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <?php

                                    $created_at =
                                        $payment["created_at"] ?? "";

                                    if (!empty($created_at)) {

                                        echo htmlspecialchars(
                                            date(
                                                "d M Y H:i",
                                                strtotime($created_at)
                                            )
                                        );

                                    } else {

                                        echo "—";
                                    }

                                    ?>

                                </td>


                                <td>


                                    <?php if (
                                        ($payment[
                                            "payment_status"
                                        ] ?? "") === "Pending"
                                    ): ?>


                                        <div class="actions">


                                            <form
                                                method="POST"
                                                onsubmit="
                                                    return confirm(
                                                        'Are you sure you want to verify this payment?'
                                                    );
                                                "
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= htmlspecialchars(
                                                        $csrf_token
                                                    ) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="payment_id"
                                                    value="<?= (int) (
                                                        $payment["id"] ?? 0
                                                    ) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="verify"
                                                >

                                                <button
                                                    type="submit"
                                                    class="action-btn verify-btn"
                                                >
                                                    ✓ Verify
                                                </button>

                                            </form>


                                            <form
                                                method="POST"
                                                onsubmit="
                                                    return confirm(
                                                        'Are you sure you want to reject this payment?'
                                                    );
                                                "
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= htmlspecialchars(
                                                        $csrf_token
                                                    ) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="payment_id"
                                                    value="<?= (int) (
                                                        $payment["id"] ?? 0
                                                    ) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="reject"
                                                >

                                                <button
                                                    type="submit"
                                                    class="action-btn reject-btn"
                                                >
                                                    ✕ Reject
                                                </button>

                                            </form>


                                        </div>


                                    <?php else: ?>


                                        <span
                                            style="
                                                font-size:10px;
                                                color:#8796a1;
                                            "
                                        >

                                            <?php if (
                                                !empty(
                                                    $payment[
                                                        "verifier_name"
                                                    ]
                                                )
                                            ): ?>

                                                Verified by:

                                                <?= htmlspecialchars(
                                                    $payment[
                                                        "verifier_name"
                                                    ]
                                                ) ?>

                                            <?php else: ?>

                                                Processed

                                            <?php endif; ?>

                                        </span>


                                    <?php endif; ?>


                                </td>


                            </tr>


                        <?php endforeach; ?>


                        </tbody>

                    </table>

                </div>


            <?php endif; ?>


        </section>


    </main>

</div>


</body>

</html>
```
