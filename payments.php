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
    $_SESSION["role"] !== "patient"
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
        email
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
$email = $user["email"];


/* =========================================================
   GET PATIENT
========================================================= */

$patient_sql = "
    SELECT
        id,
        patient_number,
        full_name,
        email,
        phone
    FROM patients
    WHERE email = ?
    LIMIT 1
";

$patient_stmt = $conn->prepare($patient_sql);

if (!$patient_stmt) {

    die(
        "Patient query failed: " .
        htmlspecialchars($conn->error)
    );
}

$patient_stmt->bind_param("s", $email);
$patient_stmt->execute();

$patient_result = $patient_stmt->get_result();

if ($patient_result->num_rows === 0) {

    die("
        <div style='
            font-family:Arial;
            padding:40px;
            text-align:center;
        '>

            <h2>Patient profile not found</h2>

            <p>
                Your account is not connected
                to a patient record.
            </p>

            <a href='logout.php'>
                Logout
            </a>

        </div>
    ");
}

$patient = $patient_result->fetch_assoc();

$patient_id = (int) $patient["id"];

$patient_phone = $patient["phone"] ?? "";


/* =========================================================
   GET PRESCRIPTION ID
========================================================= */

$prescription_id = isset($_GET["prescription_id"])
    ? (int) $_GET["prescription_id"]
    : 0;


if ($prescription_id <= 0) {

    header("Location: patient_prescriptions.php");
    exit;
}


/* =========================================================
   GET PRESCRIPTION
========================================================= */

$prescription_sql = "
    SELECT
        p.id,
        p.patient_id,
        p.doctor_id,
        p.queue_id,
        p.prescription_date,
        p.notes,
        p.status,

        u.full_name AS doctor_name

    FROM prescriptions p

    LEFT JOIN doctors d
        ON p.doctor_id = d.id

    LEFT JOIN users u
        ON d.user_id = u.id

    WHERE
        p.id = ?
        AND p.patient_id = ?

    LIMIT 1
";

$prescription_stmt = $conn->prepare(
    $prescription_sql
);

if (!$prescription_stmt) {

    die(
        "Prescription query failed: " .
        htmlspecialchars($conn->error)
    );
}

$prescription_stmt->bind_param(
    "ii",
    $prescription_id,
    $patient_id
);

$prescription_stmt->execute();

$prescription_result =
    $prescription_stmt->get_result();


if ($prescription_result->num_rows === 0) {

    die("
        <div style='
            font-family:Arial;
            padding:40px;
            text-align:center;
        '>

            <h2>Prescription not found</h2>

            <p>
                This prescription does not belong
                to your account.
            </p>

            <a href='patient_prescriptions.php'>
                Back to Prescriptions
            </a>

        </div>
    ");
}

$prescription =
    $prescription_result->fetch_assoc();


/* =========================================================
   CHECK PRESCRIPTION STATUS
========================================================= */

if ($prescription["status"] !== "pending") {

    header(
        "Location: patient_prescriptions.php"
    );

    exit;
}


/* =========================================================
   GET MEDICINES
========================================================= */

$item_sql = "
    SELECT
        pi.id,
        pi.prescription_id,
        pi.medicine_id,
        pi.quantity,
        pi.dosage,
        pi.frequency,
        pi.duration,
        pi.unit_price,
        pi.total_price,

        m.medicine_name,
        m.category

    FROM prescription_items pi

    INNER JOIN medicines m
        ON pi.medicine_id = m.id

    WHERE pi.prescription_id = ?

    ORDER BY pi.id ASC
";

$item_stmt = $conn->prepare($item_sql);

if (!$item_stmt) {

    die(
        "Medicine query failed: " .
        htmlspecialchars($conn->error)
    );
}

$item_stmt->bind_param(
    "i",
    $prescription_id
);

$item_stmt->execute();

$item_result = $item_stmt->get_result();

$items = [];

$grand_total = 0;


while (
    $item = $item_result->fetch_assoc()
) {

    $items[] = $item;

    $grand_total +=
        (float) $item["total_price"];
}


/* =========================================================
   CHECK ITEMS
========================================================= */

if (empty($items)) {

    die("
        <div style='
            font-family:Arial;
            padding:40px;
            text-align:center;
        '>

            <h2>No medicines found</h2>

            <p>
                This prescription does not contain
                any medicines yet.
            </p>

            <a href='patient_prescriptions.php'>
                Back to Prescriptions
            </a>

        </div>
    ");
}


/* =========================================================
   CHECK EXISTING PAYMENT
========================================================= */

$existing_payment = null;

$payment_check_sql = "
    SELECT
        id,
        invoice_number,
        amount,
        patient_amount,
        payment_method,
        payment_status,
        transaction_reference,
        paid_at,
        created_at

    FROM payments

    WHERE
        prescription_id = ?
        AND patient_id = ?

    ORDER BY id DESC

    LIMIT 1
";

$payment_check_stmt =
    $conn->prepare($payment_check_sql);

if ($payment_check_stmt) {

    $payment_check_stmt->bind_param(
        "ii",
        $prescription_id,
        $patient_id
    );

    $payment_check_stmt->execute();

    $payment_check_result =
        $payment_check_stmt->get_result();

    if (
        $payment_check_result->num_rows > 0
    ) {

        $existing_payment =
            $payment_check_result->fetch_assoc();
    }
}


/* =========================================================
   PROCESS PAYMENT
========================================================= */

$error = "";

$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {


    /* =====================================================
       CSRF TOKEN
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


        /* =================================================
           PAYMENT METHOD
        ================================================= */

        $payment_method =
            trim(
                $_POST["payment_method"] ?? ""
            );


        $allowed_methods = [
            "Mobile Money",
            "Bank",
            "Cash",
            "Insurance"
        ];


        if (
            !in_array(
                $payment_method,
                $allowed_methods,
                true
            )
        ) {

            $error =
                "Please select a valid payment method.";

        }


        /* =================================================
           PHONE NUMBER
        ================================================= */

        $phone_number =
            trim(
                $_POST["phone_number"] ?? ""
            );


        if (
            $error === "" &&
            $payment_method === "Mobile Money" &&
            $phone_number === ""
        ) {

            $error =
                "Please enter your mobile money phone number.";

        }


        /* =================================================
           TRANSACTION REFERENCE
        ================================================= */

        $transaction_reference =
            trim(
                $_POST["transaction_reference"] ?? ""
            );


        /*
         * For the current development version,
         * payment remains Pending.
         *
         * We will later connect this to
         * a real payment gateway.
         */


        if ($error === "") {


            /* =============================================
               GENERATE INVOICE NUMBER
            ============================================= */

            $invoice_number =
                "INV-" .
                date("Ymd") .
                "-" .
                strtoupper(
                    substr(
                        bin2hex(
                            random_bytes(4)
                        ),
                        0,
                        8
                    )
                );


            /* =============================================
               START TRANSACTION
            ============================================= */

            $conn->begin_transaction();


            try {


                /* =========================================
                   INSERT PAYMENT
                ========================================= */

                $insert_sql = "
                    INSERT INTO payments (

                        patient_id,
                        prescription_id,
                        consultation_id,
                        invoice_number,
                        amount,
                        insurance_amount,
                        patient_amount,
                        payment_method,
                        payment_status,
                        transaction_reference,
                        notes

                    )

                    VALUES (

                        ?,
                        ?,
                        NULL,
                        ?,
                        ?,
                        0.00,
                        ?,
                        ?,
                        'Pending',
                        ?,
                        ?

                    )
                ";


                $insert_stmt =
                    $conn->prepare($insert_sql);


                if (!$insert_stmt) {

                    throw new Exception(
                        "Payment insert failed: " .
                        $conn->error
                    );
                }


                $notes =
                    "Prescription payment submitted by patient.";


                $insert_stmt->bind_param(
                    "iisddsss",
                    $patient_id,
                    $prescription_id,
                    $invoice_number,
                    $grand_total,
                    $grand_total,
                    $payment_method,
                    $transaction_reference,
                    $notes
                );


                if (
                    !$insert_stmt->execute()
                ) {

                    throw new Exception(
                        "Payment could not be saved: " .
                        $insert_stmt->error
                    );
                }


                /* =========================================
                   COMMIT
                ========================================= */

                $conn->commit();


                $success =
                    "Payment request submitted successfully. " .
                    "Your payment is waiting for confirmation.";


                /*
                 * Reload existing payment information.
                 */

                $existing_payment = [

                    "id" =>
                        $insert_stmt->insert_id,

                    "invoice_number" =>
                        $invoice_number,

                    "amount" =>
                        $grand_total,

                    "patient_amount" =>
                        $grand_total,

                    "payment_method" =>
                        $payment_method,

                    "payment_status" =>
                        "Pending",

                    "transaction_reference" =>
                        $transaction_reference,

                    "paid_at" =>
                        null,

                    "created_at" =>
                        date("Y-m-d H:i:s")
                ];


            } catch (Exception $e) {

                $conn->rollback();

                $error =
                    $e->getMessage();
            }
        }
    }
}


/* =========================================================
   CSRF TOKEN
========================================================= */

if (
    !isset($_SESSION["csrf_token"])
) {

    $_SESSION["csrf_token"] =
        bin2hex(
            random_bytes(32)
        );
}


$csrf_token =
    $_SESSION["csrf_token"];


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
    Pay for Medicines | SmartHospital
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


.content {

    padding: 32px 35px;

    max-width: 1200px;
}


.back-link {

    display: inline-block;

    margin-bottom: 20px;

    color: #087f8c;

    text-decoration: none;

    font-size: 13px;

    font-weight: 600;
}


.back-link:hover {

    text-decoration: underline;
}


.payment-layout {

    display: grid;

    grid-template-columns:
        1.2fr
        .8fr;

    gap: 22px;

    align-items: start;
}


.card {

    background: white;

    border:
        1px solid
        #e9eff2;

    border-radius: 14px;

    box-shadow:
        0 5px 18px
        rgba(23,50,77,.04);

    overflow: hidden;
}


.card-header {

    padding: 22px 25px;

    border-bottom:
        1px solid
        #edf1f3;
}


.card-header h2 {

    font-size: 17px;

    margin-bottom: 5px;
}


.card-header p {

    color: #8796a1;

    font-size: 12px;
}


.medicine-row {

    padding: 17px 25px;

    border-bottom:
        1px solid
        #edf1f3;

    display: flex;

    justify-content: space-between;

    gap: 15px;
}


.medicine-name {

    font-weight: 700;

    font-size: 13px;
}


.medicine-details {

    color: #8796a1;

    font-size: 11px;

    margin-top: 5px;

    line-height: 1.5;
}


.medicine-price {

    font-weight: 700;

    font-size: 13px;

    white-space: nowrap;
}


.total-box {

    padding: 22px 25px;

    background: #f8fbfc;

    display: flex;

    align-items: center;

    justify-content: space-between;
}


.total-label {

    color: #687781;

    font-size: 13px;
}


.total-price {

    color: #087f8c;

    font-size: 22px;

    font-weight: 800;
}


.doctor-info {

    padding: 18px 25px;

    border-bottom:
        1px solid
        #edf1f3;

    font-size: 12px;

    color: #687781;
}


.doctor-info strong {

    color: #17324d;
}


.alert {

    padding: 14px 18px;

    border-radius: 9px;

    margin-bottom: 20px;

    font-size: 12px;

    line-height: 1.5;
}


.alert.error {

    background: #fff0ef;

    color: #b42318;

    border:
        1px solid
        #f4cfcb;
}


.alert.success {

    background: #e8f7f0;

    color: #16834d;

    border:
        1px solid
        #c8ead8;
}


.form-body {

    padding: 25px;
}


.form-group {

    margin-bottom: 19px;
}


.form-group label {

    display: block;

    font-size: 12px;

    font-weight: 700;

    margin-bottom: 8px;
}


.form-group input,
.form-group select {

    width: 100%;

    padding: 12px 13px;

    border:
        1px solid
        #dce5e9;

    border-radius: 8px;

    font-family: inherit;

    font-size: 13px;

    outline: none;

    background: white;
}


.form-group input:focus,
.form-group select:focus {

    border-color: #087f8c;

    box-shadow:
        0 0 0 3px
        rgba(8,127,140,.08);
}


.help-text {

    display: block;

    color: #8796a1;

    font-size: 10px;

    margin-top: 6px;

    line-height: 1.5;
}


.payment-methods {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 10px;

    margin-bottom: 20px;
}


.method-option {

    position: relative;
}


.method-option input {

    position: absolute;

    opacity: 0;

}


.method-option label {

    display: block;

    border:
        1px solid
        #dce5e9;

    border-radius: 9px;

    padding: 14px;

    cursor: pointer;

    font-size: 12px;

    font-weight: 700;

    transition: .2s;
}


.method-option label span {

    display: block;

    font-size: 20px;

    margin-bottom: 6px;
}


.method-option input:checked + label {

    border-color: #087f8c;

    background: #e8f7f8;

    color: #087f8c;
}


.mobile-money-fields {

    display: none;
}


.mobile-money-fields.show {

    display: block;
}


.submit-btn {

    width: 100%;

    border: none;

    padding: 14px;

    border-radius: 9px;

    background: #087f8c;

    color: white;

    font-size: 13px;

    font-weight: 700;

    cursor: pointer;
}


.submit-btn:hover {

    background: #066b76;
}


.notice {

    margin-top: 15px;

    padding: 13px;

    background: #f8fafb;

    border-radius: 8px;

    color: #687781;

    font-size: 10px;

    line-height: 1.6;
}


.existing-payment {

    padding: 20px 25px;

    background: #fffaf0;

    border-bottom:
        1px solid
        #f1e4c6;
}


.existing-payment strong {

    color: #8a6100;
}


.payment-details {

    margin-top: 10px;

    font-size: 11px;

    line-height: 1.8;

    color: #687781;
}


@media (max-width: 900px) {

    .payment-layout {

        grid-template-columns: 1fr;
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

    .payment-methods {

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
        href="patient_dashboard.php"
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
            Patient Menu
        </div>


        <a href="patient_dashboard.php">

            <span class="menu-icon">
                ⌂
            </span>

            <span>
                Dashboard
            </span>

        </a>


        <a href="patient_queue.php">

            <span class="menu-icon">
                🎫
            </span>

            <span>
                My Queue
            </span>

        </a>


        <a
            href="patient_prescriptions.php"
            class="active"
        >

            <span class="menu-icon">
                💊
            </span>

            <span>
                My Prescriptions
            </span>

        </a>


        <a href="patient_history.php">

            <span class="menu-icon">
                📋
            </span>

            <span>
                My History
            </span>

        </a>


        <a href="patient_profile.php">

            <span class="menu-icon">
                👤
            </span>

            <span>
                My Profile
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
                Pay for Medicines
            </h1>

            <p>
                SmartHospital Pharmacy Services
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
                    Patient
                </span>

            </div>

        </div>

    </header>



    <!-- CONTENT -->

    <main class="content">


        <a
            href="patient_prescriptions.php"
            class="back-link"
        >
            ← Back to My Prescriptions
        </a>


        <?php if ($error !== ""): ?>

            <div class="alert error">

                ❌

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <?php if ($success !== ""): ?>

            <div class="alert success">

                ✅

                <?= htmlspecialchars($success) ?>

            </div>

        <?php endif; ?>


        <div class="payment-layout">


            <!-- =================================================
                 PRESCRIPTION SUMMARY
            ================================================== -->

            <section class="card">


                <div class="card-header">

                    <h2>
                        Prescription #<?= $prescription_id ?>
                    </h2>

                    <p>
                        Medicine payment summary
                    </p>

                </div>


                <div class="doctor-info">

                    Doctor:

                    <strong>

                        <?= htmlspecialchars(
                            $prescription["doctor_name"]
                            ?? "Not assigned"
                        ) ?>

                    </strong>

                    <br>

                    Date:

                    <strong>

                        <?= htmlspecialchars(
                            date(
                                "d M Y, H:i",
                                strtotime(
                                    $prescription[
                                        "prescription_date"
                                    ]
                                )
                            )
                        ) ?>

                    </strong>

                </div>


                <?php foreach ($items as $item): ?>

                    <div class="medicine-row">

                        <div>

                            <div class="medicine-name">

                                <?= htmlspecialchars(
                                    $item["medicine_name"]
                                ) ?>

                            </div>


                            <div class="medicine-details">

                                Quantity:

                                <?= (int)
                                    $item["quantity"] ?>

                                <br>

                                Dosage:

                                <?= htmlspecialchars(
                                    $item["dosage"]
                                    ?? "-"
                                ) ?>

                                <br>

                                Frequency:

                                <?= htmlspecialchars(
                                    $item["frequency"]
                                    ?? "-"
                                ) ?>

                                <br>

                                Duration:

                                <?= htmlspecialchars(
                                    $item["duration"]
                                    ?? "-"
                                ) ?>

                            </div>

                        </div>


                        <div class="medicine-price">

                            <?= number_format(
                                (float)
                                $item["total_price"],
                                0
                            ) ?>

                            RWF

                        </div>

                    </div>

                <?php endforeach; ?>


                <div class="total-box">

                    <div class="total-label">

                        Total Medicine Cost

                    </div>

                    <div class="total-price">

                        <?= number_format(
                            $grand_total,
                            0
                        ) ?>

                        RWF

                    </div>

                </div>


            </section>



            <!-- =================================================
                 PAYMENT FORM
            ================================================== -->

            <section class="card">


                <div class="card-header">

                    <h2>
                        Payment
                    </h2>

                    <p>
                        Choose how you want to pay
                    </p>

                </div>


                <?php if (
                    $existing_payment &&
                    $success === ""
                ): ?>

                    <div class="existing-payment">

                        <strong>
                            ⚠️ Payment Already Submitted
                        </strong>


                        <div class="payment-details">

                            Invoice:

                            <strong>

                                <?= htmlspecialchars(
                                    $existing_payment[
                                        "invoice_number"
                                    ]
                                ) ?>

                            </strong>

                            <br>

                            Method:

                            <?= htmlspecialchars(
                                $existing_payment[
                                    "payment_method"
                                ]
                            ) ?>

                            <br>

                            Status:

                            <strong>

                                <?= htmlspecialchars(
                                    $existing_payment[
                                        "payment_status"
                                    ]
                                ) ?>

                            </strong>

                        </div>

                    </div>


                <?php else: ?>


                    <form
                        method="POST"
                        action=""
                        class="form-body"
                    >


                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars(
                                $csrf_token
                            ) ?>"
                        >


                        <div class="form-group">

                            <label>
                                Payment Method
                            </label>


                            <div class="payment-methods">


                                <div
                                    class="method-option"
                                >

                                    <input
                                        type="radio"
                                        id="mobile_money"
                                        name="payment_method"
                                        value="Mobile Money"
                                        required
                                    >

                                    <label
                                        for="mobile_money"
                                    >

                                        <span>
                                            📱
                                        </span>

                                        Mobile Money

                                    </label>

                                </div>


                                <div
                                    class="method-option"
                                >

                                    <input
                                        type="radio"
                                        id="bank"
                                        name="payment_method"
                                        value="Bank"
                                    >

                                    <label
                                        for="bank"
                                    >

                                        <span>
                                            🏦
                                        </span>

                                        Bank

                                    </label>

                                </div>


                                <div
                                    class="method-option"
                                >

                                    <input
                                        type="radio"
                                        id="cash"
                                        name="payment_method"
                                        value="Cash"
                                    >

                                    <label
                                        for="cash"
                                    >

                                        <span>
                                            💵
                                        </span>

                                        Cash

                                    </label>

                                </div>


                                <div
                                    class="method-option"
                                >

                                    <input
                                        type="radio"
                                        id="insurance"
                                        name="payment_method"
                                        value="Insurance"
                                    >

                                    <label
                                        for="insurance"
                                    >

                                        <span>
                                            🛡️
                                        </span>

                                        Insurance

                                    </label>

                                </div>


                            </div>

                        </div>



                        <!-- MOBILE MONEY -->

                        <div
                            class="form-group mobile-money-fields"
                            id="mobileMoneyFields"
                        >

                            <label for="phone_number">

                                Mobile Money Number

                            </label>


                            <input
                                type="text"
                                id="phone_number"
                                name="phone_number"
                                value="<?= htmlspecialchars(
                                    $patient_phone
                                ) ?>"
                                placeholder="07XXXXXXXX"
                            >


                            <span class="help-text">

                                Your saved patient phone number
                                is shown automatically.

                            </span>

                        </div>



                        <!-- TRANSACTION REFERENCE -->

                        <div class="form-group">

                            <label
                                for="transaction_reference"
                            >

                                Transaction Reference
                                <span
                                    style="
                                        color:#8796a1;
                                        font-weight:400;
                                    "
                                >
                                    (Optional)
                                </span>

                            </label>


                            <input
                                type="text"
                                id="transaction_reference"
                                name="transaction_reference"
                                placeholder="Enter payment reference if available"
                            >


                            <span class="help-text">

                                If you have already made the
                                payment through a bank or
                                mobile money service, enter
                                the transaction reference here.

                            </span>

                        </div>



                        <button
                            type="submit"
                            class="submit-btn"
                        >

                            💳 Submit Payment

                        </button>


                        <div class="notice">

                            <strong>
                                Important:
                            </strong>

                            This development version records
                            your payment request as
                            <strong>Pending</strong>.

                            Actual Mobile Money and Bank
                            verification will be connected
                            later to the appropriate payment
                            provider.

                        </div>


                    </form>


                <?php endif; ?>


            </section>


        </div>


    </main>

</div>



<script>

/* =========================================================
   MOBILE MONEY FIELD
========================================================= */

const paymentMethods =
    document.querySelectorAll(
        'input[name="payment_method"]'
    );

const mobileMoneyFields =
    document.getElementById(
        "mobileMoneyFields"
    );

const phoneInput =
    document.getElementById(
        "phone_number"
    );


function updatePaymentMethod() {

    const selected =
        document.querySelector(
            'input[name="payment_method"]:checked'
        );


    if (
        selected &&
        selected.value === "Mobile Money"
    ) {

        mobileMoneyFields.classList.add("show");

        phoneInput.required = true;

    } else {

        mobileMoneyFields.classList.remove("show");

        phoneInput.required = false;
    }
}


paymentMethods.forEach(
    function(method) {

        method.addEventListener(
            "change",
            updatePaymentMethod
        );

    }
);


updatePaymentMethod();

</script>


</body>

</html>