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

if (
    !isset($_SESSION["role"]) ||
    !in_array(
        $_SESSION["role"],
        ["admin", "receptionist"],
        true
    )
) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];


/* =========================================================
   GET LOGGED-IN USER
========================================================= */

$user_sql = "
    SELECT
        id,
        full_name,
        email,
        role
    FROM users
    WHERE id = ?
    LIMIT 1
";

$user_stmt = $conn->prepare($user_sql);

if (!$user_stmt) {
    die(
        "User query failed: " .
        htmlspecialchars($conn->error)
    );
}

$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();

$user_result = $user_stmt->get_result();

if ($user_result->num_rows === 0) {
    session_destroy();

    header("Location: login.php");
    exit;
}

$user = $user_result->fetch_assoc();

$full_name = $user["full_name"];
$role = $user["role"];


/* =========================================================
   CSRF TOKEN
========================================================= */

if (!isset($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] =
        bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION["csrf_token"];


/* =========================================================
   PAYMENT ACTION
========================================================= */

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /* =====================================================
       CSRF CHECK
    ===================================================== */

    if (
        !isset($_POST["csrf_token"]) ||
        !isset($_SESSION["csrf_token"]) ||
        !hash_equals(
            $_SESSION["csrf_token"],
            $_POST["csrf_token"]
        )
    ) {

        $error =
            "Invalid security token. Please try again.";

    } else {

        $payment_id =
            isset($_POST["payment_id"])
                ? (int) $_POST["payment_id"]
                : 0;

        $action =
            trim(
                $_POST["action"] ?? ""
            );


        /* =================================================
           VALIDATE ACTION
        ================================================= */

        if ($payment_id <= 0) {

            $error =
                "Invalid payment selected.";

        } elseif (
            !in_array(
                $action,
                ["verify", "reject"],
                true
            )
        ) {

            $error =
                "Invalid payment action.";

        } else {

            /* =============================================
               START TRANSACTION
            ============================================= */

            $conn->begin_transaction();


            try {

                /* =========================================
                   GET PAYMENT
                ========================================= */

                $payment_sql = "
                    SELECT
                        id,
                        patient_id,
                        prescription_id,
                        amount,
                        patient_amount,
                        payment_method,
                        payment_status,
                        transaction_reference

                    FROM payments

                    WHERE id = ?

                    LIMIT 1

                    FOR UPDATE
                ";

                $payment_stmt =
                    $conn->prepare($payment_sql);

                if (!$payment_stmt) {

                    throw new Exception(
                        "Payment query failed: " .
                        $conn->error
                    );
                }

                $payment_stmt->bind_param(
                    "i",
                    $payment_id
                );

                $payment_stmt->execute();

                $payment_result =
                    $payment_stmt->get_result();


                if (
                    $payment_result->num_rows === 0
                ) {

                    throw new Exception(
                        "Payment not found."
                    );
                }


                $payment =
                    $payment_result->fetch_assoc();


                /* =========================================
                   PREVENT DOUBLE PROCESSING
                ========================================= */

                if (
                    strtolower(
                        $payment["payment_status"]
                    ) !== "pending"
                ) {

                    throw new Exception(
                        "This payment has already been processed."
                    );
                }


                $prescription_id =
                    (int)
                    $payment["prescription_id"];


                /* =========================================
                   GET PRESCRIPTION
                ========================================= */

                $prescription_sql = "
                    SELECT
                        id,
                        patient_id,
                        status

                    FROM prescriptions

                    WHERE id = ?

                    LIMIT 1

                    FOR UPDATE
                ";

                $prescription_stmt =
                    $conn->prepare(
                        $prescription_sql
                    );

                if (!$prescription_stmt) {

                    throw new Exception(
                        "Prescription query failed: " .
                        $conn->error
                    );
                }

                $prescription_stmt->bind_param(
                    "i",
                    $prescription_id
                );

                $prescription_stmt->execute();

                $prescription_result =
                    $prescription_stmt->get_result();


                if (
                    $prescription_result->num_rows === 0
                ) {

                    throw new Exception(
                        "Prescription not found."
                    );
                }


                $prescription =
                    $prescription_result->fetch_assoc();


                /* =========================================
                   VERIFY PAYMENT
                ========================================= */

                if ($action === "verify") {

                    /*
                     * Payment becomes Paid
                     */

                    $update_payment_sql = "
                        UPDATE payments

                        SET
                            payment_status = 'Paid',
                            verified_by = ?,
                            verified_at = NOW(),
                            paid_at = NOW()

                        WHERE id = ?

                        LIMIT 1
                    ";

                    $update_payment_stmt =
                        $conn->prepare(
                            $update_payment_sql
                        );

                    if (!$update_payment_stmt) {

                        throw new Exception(
                            "Payment update failed: " .
                            $conn->error
                        );
                    }

                    $update_payment_stmt->bind_param(
                        "ii",
                        $user_id,
                        $payment_id
                    );

                    if (
                        !$update_payment_stmt->execute()
                    ) {

                        throw new Exception(
                            "Payment could not be verified: " .
                            $update_payment_stmt->error
                        );
                    }


                    /* =====================================
                       UPDATE PRESCRIPTION
                    ===================================== */

                    if (
                        $prescription["status"] === "pending"
                    ) {

                        $update_prescription_sql = "
                            UPDATE prescriptions

                            SET status = 'paid'

                            WHERE id = ?

                            LIMIT 1
                        ";

                        $update_prescription_stmt =
                            $conn->prepare(
                                $update_prescription_sql
                            );

                        if (
                            !$update_prescription_stmt
                        ) {

                            throw new Exception(
                                "Prescription update failed: " .
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
                                "Prescription could not be updated: " .
                                $update_prescription_stmt->error
                            );
                        }
                    }


                    /* =====================================
                       COMMIT
                    ===================================== */

                    $conn->commit();


                    $success =
                        "Payment verified successfully. " .
                        "Prescription #"
                        . $prescription_id .
                        " is now marked as Paid.";

                }


                /* =========================================
                   REJECT PAYMENT
                ========================================= */

                elseif ($action === "reject") {

                    $update_payment_sql = "
                        UPDATE payments

                        SET
                            payment_status = 'Rejected',
                            verified_by = ?,
                            verified_at = NOW()

                        WHERE id = ?

                        LIMIT 1
                    ";

                    $update_payment_stmt =
                        $conn->prepare(
                            $update_payment_sql
                        );

                    if (!$update_payment_stmt) {

                        throw new Exception(
                            "Payment update failed: " .
                            $conn->error
                        );
                    }

                    $update_payment_stmt->bind_param(
                        "ii",
                        $user_id,
                        $payment_id
                    );

                    if (
                        !$update_payment_stmt->execute()
                    ) {

                        throw new Exception(
                            "Payment could not be rejected: " .
                            $update_payment_stmt->error
                        );
                    }


                    /*
                     * Prescription remains pending.
                     */

                    $conn->commit();


                    $success =
                        "Payment has been rejected. " .
                        "The prescription remains pending.";
                }
            }

            catch (Exception $e) {

                $conn->rollback();

                $error =
                    $e->getMessage();
            }
        }
    }
}


/* =========================================================
   GET PAYMENT STATISTICS
========================================================= */

$total_payments = 0;
$pending_payments = 0;
$paid_payments = 0;
$rejected_payments = 0;


/* TOTAL */

$total_sql = "
    SELECT COUNT(*) AS total
    FROM payments
";

$total_result =
    $conn->query($total_sql);

if ($total_result) {

    $total_row =
        $total_result->fetch_assoc();

    $total_payments =
        (int) $total_row["total"];
}


/* PENDING */

$pending_sql = "
    SELECT COUNT(*) AS total
    FROM payments
    WHERE payment_status = 'Pending'
";

$pending_result =
    $conn->query($pending_sql);

if ($pending_result) {

    $pending_row =
        $pending_result->fetch_assoc();

    $pending_payments =
        (int) $pending_row["total"];
}


/* PAID */

$paid_sql = "
    SELECT COUNT(*) AS total
    FROM payments
    WHERE payment_status = 'Paid'
";

$paid_result =
    $conn->query($paid_sql);

if ($paid_result) {

    $paid_row =
        $paid_result->fetch_assoc();

    $paid_payments =
        (int) $paid_row["total"];
}


/* REJECTED */

$rejected_sql = "
    SELECT COUNT(*) AS total
    FROM payments
    WHERE payment_status = 'Rejected'
";

$rejected_result =
    $conn->query($rejected_sql);

if ($rejected_result) {

    $rejected_row =
        $rejected_result->fetch_assoc();

    $rejected_payments =
        (int) $rejected_row["total"];
}


/* =========================================================
   GET PAYMENTS
========================================================= */

$payments_sql = "
    SELECT

        p.id,
        p.invoice_number,
        p.amount,
        p.patient_amount,
        p.payment_method,
        p.payment_status,
        p.transaction_reference,
        p.created_at,
        p.paid_at,
        p.verified_at,

        pt.patient_number,
        pt.full_name AS patient_name,
        pt.phone,

        pr.id AS prescription_id,
        pr.prescription_date,
        pr.status AS prescription_status,

        u.full_name AS verified_by_name

    FROM payments p

    INNER JOIN patients pt
        ON p.patient_id = pt.id

    INNER JOIN prescriptions pr
        ON p.prescription_id = pr.id

    LEFT JOIN users u
        ON p.verified_by = u.id

    ORDER BY
        CASE
            WHEN p.payment_status = 'Pending'
            THEN 0
            ELSE 1
        END,
        p.id DESC
";

$payments_result =
    $conn->query($payments_sql);

if (!$payments_result) {

    die(
        "Payments query failed: " .
        htmlspecialchars($conn->error)
    );
}


/* =========================================================
   INITIAL
========================================================= */

$initial =
    strtoupper(
        substr(
            trim($full_name),
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

<title>
    Pharmacy Payments | SmartHospital
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
}

.page-intro {

    margin-bottom: 25px;
}

.page-intro h2 {

    font-size: 20px;

    margin-bottom: 5px;
}

.page-intro p {

    color: #8796a1;

    font-size: 12px;
}


/* =========================================================
   ALERTS
========================================================= */

.alert {

    padding: 14px 18px;

    border-radius: 9px;

    margin-bottom: 20px;

    font-size: 12px;

    line-height: 1.5;
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
   STATISTICS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 16px;

    margin-bottom: 25px;
}

.stat-card {

    background: white;

    border:
        1px solid
        #e9eff2;

    border-radius: 12px;

    padding: 20px;

    box-shadow:
        0 5px 18px
        rgba(23,50,77,.04);
}

.stat-label {

    color: #8796a1;

    font-size: 11px;

    margin-bottom: 8px;
}

.stat-number {

    font-size: 25px;

    font-weight: 800;
}

.stat-card.pending .stat-number {
    color: #b7791f;
}

.stat-card.paid .stat-number {
    color: #16834d;
}

.stat-card.rejected .stat-number {
    color: #b42318;
}


/* =========================================================
   TABLE
========================================================= */

.table-card {

    background: white;

    border:
        1px solid
        #e9eff2;

    border-radius: 14px;

    overflow: hidden;

    box-shadow:
        0 5px 18px
        rgba(23,50,77,.04);
}

.table-header {

    padding: 20px 23px;

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

.table-wrapper {
    overflow-x: auto;
}

table {

    width: 100%;

    border-collapse: collapse;

    min-width: 1050px;
}

thead {
    background: #f8fbfc;
}

th {

    padding: 13px 15px;

    text-align: left;

    font-size: 10px;

    text-transform: uppercase;

    letter-spacing: .4px;

    color: #72828d;

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

    display: block;

    color: #8796a1;

    font-size: 10px;

    margin-top: 3px;
}

.invoice {

    font-weight: 700;

    color: #087f8c;
}

.amount {

    font-weight: 800;
}

.reference {

    color: #596a75;

    font-family: monospace;

    font-size: 11px;
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

    background: #fff4dc;

    color: #9a6700;
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
   ACTIONS
========================================================= */

.actions {

    display: flex;

    gap: 7px;
}

.action-form {
    display: inline;
}

.btn {

    border: none;

    border-radius: 7px;

    padding: 8px 11px;

    font-family: inherit;

    font-size: 10px;

    font-weight: 700;

    cursor: pointer;
}

.btn-verify {

    background: #e8f7f0;

    color: #16834d;
}

.btn-verify:hover {
    background: #16834d;
    color: white;
}

.btn-reject {

    background: #fff0ef;

    color: #b42318;
}

.btn-reject:hover {

    background: #b42318;

    color: white;
}

.verified-info {

    color: #8796a1;

    font-size: 10px;

    line-height: 1.5;
}

.empty {

    padding: 50px 20px;

    text-align: center;

    color: #8796a1;

    font-size: 13px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1000px) {

    .stats {

        grid-template-columns:
            repeat(2, 1fr);
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

        grid-template-columns: 1fr;
    }
}

</style>

</head>

<body>


<!-- =======================================================
     SIDEBAR
======================================================= -->

<aside class="sidebar">

    <a
        href="dashboard.php"
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
            Pharmacy & Payments
        </div>


        <a href="dashboard.php">

            <span class="menu-icon">
                ⌂
            </span>

            <span>
                Dashboard
            </span>

        </a>


        <a
            href="pharmacy_payments.php"
            class="active"
        >

            <span class="menu-icon">
                💳
            </span>

            <span>
                Payments
            </span>

        </a>


        <a href="queue.php">

            <span class="menu-icon">
                🎫
            </span>

            <span>
                Queue
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


    <!-- TOPBAR -->

    <header class="topbar">

        <div class="page-title">

            <h1>
                Pharmacy Payments
            </h1>

            <p>
                Verify patient medicine payments
            </p>

        </div>


        <div class="profile">

            <div class="avatar">

                <?= htmlspecialchars($initial) ?>

            </div>


            <div class="profile-info">

                <strong>

                    <?= htmlspecialchars($full_name) ?>

                </strong>

                <span>

                    <?= htmlspecialchars(
                        ucfirst($role)
                    ) ?>

                </span>

            </div>

        </div>

    </header>


    <!-- CONTENT -->

    <main class="content">


        <div class="page-intro">

            <h2>
                Payment Verification
            </h2>

            <p>
                Review and verify medicine payments submitted by patients.
            </p>

        </div>


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

                <div class="stat-label">
                    Total Payments
                </div>

                <div class="stat-number">

                    <?= $total_payments ?>

                </div>

            </div>


            <div class="stat-card pending">

                <div class="stat-label">
                    Pending Verification
                </div>

                <div class="stat-number">

                    <?= $pending_payments ?>

                </div>

            </div>


            <div class="stat-card paid">

                <div class="stat-label">
                    Paid
                </div>

                <div class="stat-number">

                    <?= $paid_payments ?>

                </div>

            </div>


            <div class="stat-card rejected">

                <div class="stat-label">
                    Rejected
                </div>

                <div class="stat-number">

                    <?= $rejected_payments ?>

                </div>

            </div>


        </div>


        <!-- =================================================
             PAYMENT TABLE
        ================================================== -->

        <section class="table-card">


            <div class="table-header">

                <h2>
                    Medicine Payments
                </h2>

                <span>
                    <?= $pending_payments ?>
                    payment(s) waiting for verification
                </span>

            </div>


            <div class="table-wrapper">

                <?php if (
                    $payments_result->num_rows > 0
                ): ?>

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
                                    Submitted
                                </th>

                                <th>
                                    Action
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php while (
                            $payment =
                            $payments_result->fetch_assoc()
                        ): ?>


                            <tr>


                                <!-- INVOICE -->

                                <td>

                                    <div class="invoice">

                                        <?= htmlspecialchars(
                                            $payment[
                                                "invoice_number"
                                            ]
                                        ) ?>

                                    </div>

                                </td>


                                <!-- PATIENT -->

                                <td>

                                    <div class="patient-name">

                                        <?= htmlspecialchars(
                                            $payment[
                                                "patient_name"
                                            ]
                                        ) ?>

                                    </div>

                                    <span
                                        class="patient-number"
                                    >

                                        <?= htmlspecialchars(
                                            $payment[
                                                "patient_number"
                                            ]
                                        ) ?>

                                    </span>

                                </td>


                                <!-- PRESCRIPTION -->

                                <td>

                                    <strong>

                                        #<?= (int)
                                            $payment[
                                                "prescription_id"
                                            ] ?>

                                    </strong>

                                    <span
                                        class="patient-number"
                                    >

                                        <?= htmlspecialchars(
                                            ucfirst(
                                                $payment[
                                                    "prescription_status"
                                                ]
                                            )
                                        ) ?>

                                    </span>

                                </td>


                                <!-- AMOUNT -->

                                <td>

                                    <span
                                        class="amount"
                                    >

                                        <?= number_format(
                                            (float)
                                            $payment[
                                                "patient_amount"
                                            ],
                                            0
                                        ) ?>

                                        RWF

                                    </span>

                                </td>


                                <!-- METHOD -->

                                <td>

                                    <?= htmlspecialchars(
                                        $payment[
                                            "payment_method"
                                        ]
                                    ) ?>

                                </td>


                                <!-- REFERENCE -->

                                <td>

                                    <?php if (
                                        !empty(
                                            $payment[
                                                "transaction_reference"
                                            ]
                                        )
                                    ): ?>

                                        <span
                                            class="reference"
                                        >

                                            <?= htmlspecialchars(
                                                $payment[
                                                    "transaction_reference"
                                                ]
                                            ) ?>

                                        </span>

                                    <?php else: ?>

                                        <span
                                            style="
                                                color:#a3afb6;
                                            "
                                        >

                                            No reference

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <?php
                                    $status_class =
                                        strtolower(
                                            $payment[
                                                "payment_status"
                                            ]
                                        );
                                    ?>

                                    <span
                                        class="status
                                        <?= htmlspecialchars(
                                            $status_class
                                        ) ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $payment[
                                                "payment_status"
                                            ]
                                        ) ?>

                                    </span>

                                </td>


                                <!-- DATE -->

                                <td>

                                    <?= htmlspecialchars(
                                        date(
                                            "d M Y",
                                            strtotime(
                                                $payment[
                                                    "created_at"
                                                ]
                                            )
                                        )
                                    ) ?>

                                    <br>

                                    <span
                                        class="patient-number"
                                    >

                                        <?= htmlspecialchars(
                                            date(
                                                "H:i",
                                                strtotime(
                                                    $payment[
                                                        "created_at"
                                                    ]
                                                )
                                            )
                                        ) ?>

                                    </span>

                                </td>


                                <!-- ACTION -->

                                <td>

                                    <?php if (
                                        strtolower(
                                            $payment[
                                                "payment_status"
                                            ]
                                        ) === "pending"
                                    ): ?>

                                        <div class="actions">


                                            <!-- VERIFY -->

                                            <form
                                                method="POST"
                                                class="action-form"
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
                                                    value="<?= (int)
                                                        $payment["id"] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="verify"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn btn-verify"
                                                >

                                                    ✓ Verify

                                                </button>

                                            </form>


                                            <!-- REJECT -->

                                            <form
                                                method="POST"
                                                class="action-form"
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
                                                    value="<?= (int)
                                                        $payment["id"] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="reject"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn btn-reject"
                                                >

                                                    ✕ Reject

                                                </button>

                                            </form>


                                        </div>


                                    <?php else: ?>


                                        <div
                                            class="verified-info"
                                        >

                                            <?php if (
                                                $payment[
                                                    "verified_by_name"
                                                ]
                                            ): ?>

                                                By:

                                                <?= htmlspecialchars(
                                                    $payment[
                                                        "verified_by_name"
                                                    ]
                                                ) ?>

                                                <br>

                                            <?php endif; ?>


                                            <?php if (
                                                $payment[
                                                    "verified_at"
                                                ]
                                            ): ?>

                                                <?= htmlspecialchars(
                                                    date(
                                                        "d M Y H:i",
                                                        strtotime(
                                                            $payment[
                                                                "verified_at"
                                                            ]
                                                        )
                                                    )
                                                ) ?>

                                            <?php endif; ?>

                                        </div>


                                    <?php endif; ?>

                                </td>


                            </tr>


                        <?php endwhile; ?>

                        </tbody>

                    </table>


                <?php else: ?>


                    <div class="empty">

                        💳

                        <br><br>

                        No medicine payments have been submitted yet.

                    </div>


                <?php endif; ?>


            </div>


        </section>


    </main>

</div>

</body>

</html>
```
