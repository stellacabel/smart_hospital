<?php

/* =========================================================
   SMART HOSPITAL
   ADD PAYMENT / CREATE INVOICE
========================================================= */

session_start();

require_once "db.php";


/* =========================================================
   ADMIN ACCESS
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
   HELPER
========================================================= */

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}


/* =========================================================
   ADMIN INFORMATION
========================================================= */

$admin_name = $_SESSION["full_name"] ?? "Administrator";

$first_letter = strtoupper(
    substr(
        trim($admin_name),
        0,
        1
    )
);


/* =========================================================
   FORM VARIABLES
========================================================= */

$patient_id = "";
$consultation_id = "";
$amount = "";
$insurance_amount = "0.00";
$patient_amount = "0.00";
$payment_method = "Cash";
$payment_status = "Pending";
$transaction_reference = "";
$notes = "";

$message = "";
$message_type = "";


/* =========================================================
   GET PATIENTS
========================================================= */

$patients = [];

$patient_sql = "
    SELECT
        id,
        patient_number,
        full_name,
        insurance_provider,
        insurance_type,
        insurance_number
    FROM patients
    ORDER BY full_name ASC
";

$patient_result = $conn->query($patient_sql);

if ($patient_result) {

    while ($row = $patient_result->fetch_assoc()) {

        $patients[] = $row;
    }
}


/* =========================================================
   GET CONSULTATIONS
========================================================= */

$consultations = [];

$consultation_sql = "
    SELECT
        c.id,
        c.patient_id,
        c.created_at,
        c.status,

        p.patient_number,
        p.full_name AS patient_name,

        dep.name AS department_name

    FROM consultations c

    INNER JOIN patients p
        ON c.patient_id = p.id

    LEFT JOIN departments dep
        ON c.department_id = dep.id

    ORDER BY c.id DESC
";

$consultation_result = $conn->query($consultation_sql);

if ($consultation_result) {

    while ($row = $consultation_result->fetch_assoc()) {

        $consultations[] = $row;
    }
}


/* =========================================================
   HANDLE PAYMENT
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $patient_id = (int)($_POST["patient_id"] ?? 0);

    $consultation_id_raw =
        trim($_POST["consultation_id"] ?? "");

    $consultation_id =
        $consultation_id_raw !== ""
            ? (int)$consultation_id_raw
            : null;

    $amount = trim($_POST["amount"] ?? "");

    $insurance_amount =
        trim($_POST["insurance_amount"] ?? "0");

    $payment_method =
        trim($_POST["payment_method"] ?? "Cash");

    $payment_status =
        trim($_POST["payment_status"] ?? "Pending");

    $transaction_reference =
        trim($_POST["transaction_reference"] ?? "");

    $notes =
        trim($_POST["notes"] ?? "");


    /* =====================================================
       VALIDATE AMOUNTS
    ===================================================== */

    $amount_number = (float)$amount;

    $insurance_number = (float)$insurance_amount;


    if ($insurance_number < 0) {
        $insurance_number = 0;
    }


    $patient_number_amount =
        $amount_number - $insurance_number;


    if ($patient_number_amount < 0) {
        $patient_number_amount = 0;
    }


    $patient_amount =
        number_format(
            $patient_number_amount,
            2,
            ".",
            ""
        );


    /* =====================================================
       VALIDATION
    ===================================================== */

    if ($patient_id <= 0) {

        $message =
            "Please select a patient.";

        $message_type = "error";

    } elseif ($amount_number <= 0) {

        $message =
            "Please enter a valid payment amount.";

        $message_type = "error";

    } elseif ($insurance_number > $amount_number) {

        $message =
            "Insurance amount cannot be greater than the total amount.";

        $message_type = "error";

    } elseif (
        !in_array(
            $payment_method,
            [
                "Cash",
                "Mobile Money",
                "Bank",
                "Insurance"
            ],
            true
        )
    ) {

        $message =
            "Invalid payment method.";

        $message_type = "error";

    } elseif (
        !in_array(
            $payment_status,
            [
                "Pending",
                "Paid",
                "Partially Paid",
                "Waived"
            ],
            true
        )
    ) {

        $message =
            "Invalid payment status.";

        $message_type = "error";

    } else {


        /* =================================================
           VERIFY PATIENT
        ================================================= */

        $verify_patient = $conn->prepare(
            "
            SELECT
                id,
                patient_number,
                full_name
            FROM patients
            WHERE id = ?
            LIMIT 1
            "
        );


        if (!$verify_patient) {

            $message =
                "Unable to verify patient.";

            $message_type = "error";

        } else {

            $verify_patient->bind_param(
                "i",
                $patient_id
            );

            $verify_patient->execute();

            $verify_result =
                $verify_patient->get_result();


            if ($verify_result->num_rows === 0) {

                $message =
                    "Selected patient does not exist.";

                $message_type = "error";

            } else {


                /* =============================================
                   GENERATE INVOICE NUMBER
                ============================================= */

                $invoice_number =
                    "INV-" .
                    date("Y") .
                    "-" .
                    strtoupper(
                        substr(
                            uniqid(),
                            -6
                        )
                    );


                /* =============================================
                   PAYMENT DATE
                ============================================= */

                $paid_at = null;

                if (
                    $payment_status === "Paid" ||
                    $payment_status === "Partially Paid"
                ) {

                    $paid_at =
                        date("Y-m-d H:i:s");
                }


                /* =============================================
                   INSERT PAYMENT
                ============================================= */

                $payment_sql = "
                    INSERT INTO payments
                    (
                        patient_id,
                        consultation_id,
                        invoice_number,
                        amount,
                        insurance_amount,
                        patient_amount,
                        payment_method,
                        payment_status,
                        transaction_reference,
                        notes,
                        paid_at
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
                        ?,
                        ?,
                        ?
                    )
                ";


                $payment_stmt =
                    $conn->prepare(
                        $payment_sql
                    );


                if (!$payment_stmt) {

                    $message =
                        "Unable to prepare payment.";

                    $message_type = "error";

                } else {


                    $payment_stmt->bind_param(
                        "iissddsssss",
                        $patient_id,
                        $consultation_id,
                        $invoice_number,
                        $amount_number,
                        $insurance_number,
                        $patient_number_amount,
                        $payment_method,
                        $payment_status,
                        $transaction_reference,
                        $notes,
                        $paid_at
                    );


                    if ($payment_stmt->execute()) {

                        $new_payment_id =
                            $payment_stmt->insert_id;


                        /*
                         * SUCCESS
                         */

                        header(
                            "Location: payment_view.php?id=" .
                            $new_payment_id .
                            "&created=1"
                        );

                        exit;

                    } else {

                        $message =
                            "Unable to save payment: " .
                            $payment_stmt->error;

                        $message_type = "error";
                    }


                    $payment_stmt->close();
                }
            }


            $verify_patient->close();
        }
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
    Create Payment | SmartHospital
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

    font-family:
        "Segoe UI",
        Arial,
        sans-serif;

    background: #f5f9fa;

    color: #17324d;

    min-height: 100vh;
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

    overflow-y: auto;
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


/* =========================================================
   MENU
========================================================= */

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


/* =========================================================
   LOGOUT
========================================================= */

.logout {

    margin-top: 35px;
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


/* =========================================================
   TOPBAR
========================================================= */

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

    max-width: 1150px;
}


/* =========================================================
   BACK
========================================================= */

.back-link {

    display: inline-block;

    color: #087f8c;

    text-decoration: none;

    font-size: 13px;

    font-weight: 600;

    margin-bottom: 20px;
}


/* =========================================================
   CARD
========================================================= */

.card {

    background: white;

    border:
        1px solid #e6edef;

    border-radius: 15px;

    padding: 28px;

    box-shadow:
        0 8px 25px
        rgba(23,50,77,.04);
}


.card-header {

    border-bottom:
        1px solid #edf1f3;

    padding-bottom: 20px;

    margin-bottom: 25px;
}


.card-header h2 {

    font-size: 21px;

    margin-bottom: 5px;
}


.card-header p {

    color: #8494a0;

    font-size: 12px;
}


/* =========================================================
   MESSAGE
========================================================= */

.message {

    padding: 13px 15px;

    border-radius: 8px;

    margin-bottom: 20px;

    font-size: 13px;

    line-height: 1.5;
}


.message.error {

    background: #fff0f0;

    color: #b42318;

    border:
        1px solid #f2c3c3;
}


/* =========================================================
   FORM GRID
========================================================= */

.form-grid {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 20px;
}


.form-group {

    margin-bottom: 18px;
}


.form-group.full {

    grid-column: 1 / -1;
}


label {

    display: block;

    margin-bottom: 7px;

    font-size: 13px;

    font-weight: 600;

    color: #334d62;
}


input,
select,
textarea {

    width: 100%;

    border:
        1px solid #dce6ea;

    border-radius: 8px;

    background: white;

    color: #17324d;

    font-family: inherit;

    font-size: 13px;

    outline: none;
}


input,
select {

    height: 46px;

    padding: 0 13px;
}


textarea {

    min-height: 100px;

    padding: 13px;

    resize: vertical;
}


input:focus,
select:focus,
textarea:focus {

    border-color: #087f8c;

    box-shadow:
        0 0 0 3px
        rgba(8,127,140,.08);
}


/* =========================================================
   AMOUNT BOX
========================================================= */

.amount-summary {

    grid-column: 1 / -1;

    display: grid;

    grid-template-columns:
        1fr 1fr 1fr;

    gap: 15px;

    margin:
        5px 0 10px;
}


.amount-box {

    border:
        1px solid #e0e9ec;

    border-radius: 10px;

    padding: 17px;

    background: #f9fbfc;
}


.amount-box span {

    display: block;

    color: #7d8d98;

    font-size: 11px;

    margin-bottom: 7px;

    text-transform: uppercase;

    font-weight: 600;
}


.amount-box strong {

    font-size: 19px;

    color: #17324d;
}


.amount-box.patient {

    background: #e9f8f8;

    border-color: #bde5e8;
}


.amount-box.patient strong {

    color: #087f8c;
}


/* =========================================================
   PATIENT INFORMATION
========================================================= */

.patient-info {

    display: none;

    background: #f3fafb;

    border:
        1px solid #d7edef;

    border-radius: 9px;

    padding: 15px;

    margin-top: 10px;

    margin-bottom: 5px;
}


.patient-info strong {

    display: block;

    font-size: 14px;

    margin-bottom: 5px;
}


.patient-info span {

    color: #667b88;

    font-size: 12px;

    display: block;

    margin-top: 3px;
}


/* =========================================================
   BUTTONS
========================================================= */

.actions {

    display: flex;

    justify-content: flex-end;

    gap: 10px;

    margin-top: 10px;

    padding-top: 20px;

    border-top:
        1px solid #edf1f3;
}


.btn {

    display: inline-block;

    border: none;

    border-radius: 8px;

    padding:
        12px 20px;

    font-family: inherit;

    font-size: 13px;

    font-weight: 600;

    cursor: pointer;

    text-decoration: none;
}


.btn-secondary {

    background: #eef3f5;

    color: #17324d;
}


.btn-primary {

    background: #087f8c;

    color: white;
}


.btn-primary:hover {

    background: #066b76;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 850px) {

    .form-grid {

        grid-template-columns: 1fr;
    }

    .form-group.full {

        grid-column: auto;
    }

    .amount-summary {

        grid-template-columns: 1fr;
    }

}


@media (max-width: 700px) {

    .sidebar {

        width: 70px;

        padding:
            20px 10px;
    }

    .logo {

        justify-content: center;

        padding-bottom: 20px;
    }

    .logo span:last-child {

        display: none;
    }

    .menu-title {

        display: none;
    }

    .menu a {

        justify-content: center;
    }

    .menu a span:not(.menu-icon) {

        display: none;
    }

    .main {

        margin-left: 70px;
    }

    .topbar {

        padding:
            0 20px;
    }

    .admin-info {

        display: none;
    }

    .content {

        padding:
            22px 18px;
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


        <a href="admin_consultations.php">

            <span class="menu-icon">
                📋
            </span>

            <span>
                Consultation Reports
            </span>

        </a>


        <a
            href="payments.php"
            class="active"
        >

            <span class="menu-icon">
                💳
            </span>

            <span>
                Payments
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
            Create Payment
        </h1>

        <p>
            SmartHospital Financial Management
        </p>

    </div>


    <div class="admin-profile">

        <div class="avatar">

            <?= e($first_letter) ?>

        </div>


        <div class="admin-info">

            <strong>
                <?= e($admin_name) ?>
            </strong>

            <span>
                System Administrator
            </span>

        </div>

    </div>


</header>



<main class="content">


<a
    href="payments.php"
    class="back-link"
>
    ← Back to Payments
</a>



<div class="card">


    <div class="card-header">

        <h2>
            Create Invoice / Payment
        </h2>

        <p>
            Record a patient payment and insurance contribution.
        </p>

    </div>



    <?php if (!empty($message)): ?>

        <div class="message <?= e($message_type) ?>">

            <?= e($message) ?>

        </div>

    <?php endif; ?>



    <form
        method="POST"
        action="payment_add.php"
    >


        <div class="form-grid">


            <!-- =========================================
                 PATIENT
            ========================================== -->

            <div class="form-group full">

                <label for="patient_id">
                    Patient
                </label>

                <select
                    name="patient_id"
                    id="patient_id"
                    required
                    onchange="showPatientInfo()"
                >

                    <option value="">
                        Select patient
                    </option>


                    <?php foreach ($patients as $patient): ?>

                        <option
                            value="<?= (int)$patient["id"] ?>"
                            data-name="<?= e($patient["full_name"]) ?>"
                            data-number="<?= e($patient["patient_number"]) ?>"
                            data-insurance="<?= e($patient["insurance_type"] ?? "None") ?>"
                            data-provider="<?= e($patient["insurance_provider"] ?? "") ?>"
                            data-insurance-number="<?= e($patient["insurance_number"] ?? "") ?>"
                            <?= ((string)$patient_id === (string)$patient["id"]) ? "selected" : "" ?>
                        >

                            <?= e($patient["patient_number"]) ?>
                            -
                            <?= e($patient["full_name"]) ?>

                        </option>

                    <?php endforeach; ?>

                </select>


                <div
                    class="patient-info"
                    id="patientInfo"
                >

                    <strong id="patientName"></strong>

                    <span id="patientNumber"></span>

                    <span id="patientInsurance"></span>

                    <span id="patientInsuranceNumber"></span>

                </div>

            </div>



            <!-- =========================================
                 CONSULTATION
            ========================================== -->

            <div class="form-group">

                <label for="consultation_id">

                    Consultation

                    <span
                        style="
                            color:#8999a4;
                            font-weight:400;
                        "
                    >
                        (Optional)
                    </span>

                </label>


                <select
                    name="consultation_id"
                    id="consultation_id"
                >

                    <option value="">
                        No specific consultation
                    </option>


                    <?php foreach ($consultations as $consultation): ?>

                        <option
                            value="<?= (int)$consultation["id"] ?>"
                            data-patient="<?= (int)$consultation["patient_id"] ?>"
                            <?= (
                                $consultation_id !== "" &&
                                (int)$consultation_id ===
                                (int)$consultation["id"]
                            )
                                ? "selected"
                                : ""
                            ?>
                        >

                            #<?= (int)$consultation["id"] ?>

                            -

                            <?= e($consultation["patient_name"]) ?>

                            -

                            <?= e($consultation["department_name"] ?? "General") ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>



            <!-- =========================================
                 TOTAL AMOUNT
            ========================================== -->

            <div class="form-group">

                <label for="amount">
                    Total Amount (RWF)
                </label>

                <input
                    type="number"
                    id="amount"
                    name="amount"
                    min="0"
                    step="0.01"
                    placeholder="0.00"
                    value="<?= e($amount) ?>"
                    oninput="calculateAmount()"
                    required
                >

            </div>



            <!-- =========================================
                 AMOUNT SUMMARY
            ========================================== -->

            <div class="amount-summary">


                <div class="amount-box">

                    <span>
                        Total Amount
                    </span>

                    <strong id="totalDisplay">
                        0.00 RWF
                    </strong>

                </div>


                <div class="amount-box">

                    <span>
                        Insurance
                    </span>

                    <strong id="insuranceDisplay">
                        0.00 RWF
                    </strong>

                </div>


                <div class="amount-box patient">

                    <span>
                        Patient Pays
                    </span>

                    <strong id="patientDisplay">
                        0.00 RWF
                    </strong>

                </div>


            </div>



            <!-- =========================================
                 INSURANCE
            ========================================== -->

            <div class="form-group">

                <label for="insurance_amount">

                    Insurance Contribution (RWF)

                </label>

                <input
                    type="number"
                    id="insurance_amount"
                    name="insurance_amount"
                    min="0"
                    step="0.01"
                    value="<?= e($insurance_amount) ?>"
                    oninput="calculateAmount()"
                >

            </div>



            <!-- =========================================
                 PAYMENT METHOD
            ========================================== -->

            <div class="form-group">

                <label for="payment_method">
                    Payment Method
                </label>

                <select
                    name="payment_method"
                    id="payment_method"
                    required
                >

                    <option
                        value="Cash"
                        <?= $payment_method === "Cash"
                            ? "selected"
                            : "" ?>
                    >
                        Cash
                    </option>

                    <option
                        value="Mobile Money"
                        <?= $payment_method === "Mobile Money"
                            ? "selected"
                            : "" ?>
                    >
                        Mobile Money
                    </option>

                    <option
                        value="Bank"
                        <?= $payment_method === "Bank"
                            ? "selected"
                            : "" ?>
                    >
                        Bank
                    </option>

                    <option
                        value="Insurance"
                        <?= $payment_method === "Insurance"
                            ? "selected"
                            : "" ?>
                    >
                        Insurance
                    </option>

                </select>

            </div>



            <!-- =========================================
                 PAYMENT STATUS
            ========================================== -->

            <div class="form-group">

                <label for="payment_status">
                    Payment Status
                </label>

                <select
                    name="payment_status"
                    id="payment_status"
                    required
                >

                    <option
                        value="Pending"
                        <?= $payment_status === "Pending"
                            ? "selected"
                            : "" ?>
                    >
                        Pending
                    </option>

                    <option
                        value="Paid"
                        <?= $payment_status === "Paid"
                            ? "selected"
                            : "" ?>
                    >
                        Paid
                    </option>

                    <option
                        value="Partially Paid"
                        <?= $payment_status === "Partially Paid"
                            ? "selected"
                            : "" ?>
                    >
                        Partially Paid
                    </option>

                    <option
                        value="Waived"
                        <?= $payment_status === "Waived"
                            ? "selected"
                            : "" ?>
                    >
                        Waived
                    </option>

                </select>

            </div>



            <!-- =========================================
                 TRANSACTION REFERENCE
            ========================================== -->

            <div class="form-group">

                <label for="transaction_reference">

                    Transaction Reference

                    <span
                        style="
                            color:#8999a4;
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
                    placeholder="e.g. TXN123456"
                    value="<?= e($transaction_reference) ?>"
                >

            </div>



            <!-- =========================================
                 NOTES
            ========================================== -->

            <div class="form-group full">

                <label for="notes">
                    Notes
                </label>

                <textarea
                    id="notes"
                    name="notes"
                    placeholder="Add any payment notes..."
                ><?= e($notes) ?></textarea>

            </div>


        </div>



        <!-- =============================================
             ACTIONS
        ============================================== -->

        <div class="actions">

            <a
                href="payments.php"
                class="btn btn-secondary"
            >
                Cancel
            </a>


            <button
                type="submit"
                class="btn btn-primary"
            >
                💳 Save Payment
            </button>

        </div>


    </form>


</div>


</main>


</div>



<script>

/* =========================================================
   CALCULATE PAYMENT
========================================================= */

function calculateAmount()
{

    let total =
        parseFloat(
            document.getElementById("amount").value
        ) || 0;


    let insurance =
        parseFloat(
            document.getElementById("insurance_amount").value
        ) || 0;


    if (insurance < 0) {

        insurance = 0;
    }


    if (insurance > total) {

        insurance = total;
    }


    let patient =
        total - insurance;


    document.getElementById(
        "totalDisplay"
    ).textContent =
        total.toLocaleString(
            "en-US",
            {
                minimumFractionDigits: 2
            }
        ) + " RWF";


    document.getElementById(
        "insuranceDisplay"
    ).textContent =
        insurance.toLocaleString(
            "en-US",
            {
                minimumFractionDigits: 2
            }
        ) + " RWF";


    document.getElementById(
        "patientDisplay"
    ).textContent =
        patient.toLocaleString(
            "en-US",
            {
                minimumFractionDigits: 2
            }
        ) + " RWF";

}


/* =========================================================
   PATIENT INFORMATION
========================================================= */

function showPatientInfo()
{

    const select =
        document.getElementById(
            "patient_id"
        );


    const option =
        select.options[
            select.selectedIndex
        ];


    const box =
        document.getElementById(
            "patientInfo"
        );


    if (!option || !option.value) {

        box.style.display = "none";

        return;
    }


    const name =
        option.dataset.name || "";


    const number =
        option.dataset.number || "";


    const insurance =
        option.dataset.insurance || "None";


    const provider =
        option.dataset.provider || "";


    const insuranceNumber =
        option.dataset.insuranceNumber || "";


    document.getElementById(
        "patientName"
    ).textContent =
        name;


    document.getElementById(
        "patientNumber"
    ).textContent =
        "Patient No: " + number;


    document.getElementById(
        "patientInsurance"
    ).textContent =
        "Insurance: " +
        insurance +
        (
            provider
                ? " - " + provider
                : ""
        );


    document.getElementById(
        "patientInsuranceNumber"
    ).textContent =
        insuranceNumber
            ? "Insurance No: " +
              insuranceNumber
            : "Insurance No: Not provided";


    box.style.display = "block";
}


/* =========================================================
   FILTER CONSULTATIONS BY PATIENT
========================================================= */

document
    .getElementById("patient_id")
    .addEventListener(
        "change",
        function()
        {

            const patientId =
                this.value;


            const consultationSelect =
                document.getElementById(
                    "consultation_id"
                );


            const options =
                consultationSelect.querySelectorAll(
                    "option[data-patient]"
                );


            options.forEach(
                function(option)
                {

                    if (
                        !patientId ||
                        option.dataset.patient ===
                        patientId
                    ) {

                        option.style.display =
                            "";

                    } else {

                        option.style.display =
                            "none";
                    }

                }
            );


            if (patientId) {

                consultationSelect.value = "";
            }

        }
    );


/* =========================================================
   INITIALIZE
========================================================= */

calculateAmount();

showPatientInfo();

</script>


</body>

</html>