<?php

/* =========================================================
   SMART HOSPITAL - PAYMENT / RECEIPT VIEW
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
   GET PAYMENT ID
========================================================= */

$payment_id = isset($_GET["id"])
    ? (int)$_GET["id"]
    : 0;

if ($payment_id <= 0) {
    die("Invalid payment ID.");
}


/* =========================================================
   GET PAYMENT
========================================================= */

$sql = "
    SELECT

        p.id,
        p.patient_id,
        p.consultation_id,
        p.invoice_number,

        p.amount,
        p.insurance_amount,
        p.patient_amount,

        p.payment_method,
        p.payment_status,

        p.transaction_reference,
        p.notes,

        p.paid_at,
        p.created_at,

        pt.patient_number,
        pt.full_name AS patient_name

    FROM payments p

    INNER JOIN patients pt
        ON p.patient_id = pt.id

    WHERE p.id = ?

    LIMIT 1
";


$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error: " . e($conn->error));
}


$stmt->bind_param(
    "i",
    $payment_id
);


$stmt->execute();


$result = $stmt->get_result();


if ($result->num_rows === 0) {

    die("
        <div style='
            font-family:Arial;
            padding:50px;
            text-align:center;
        '>
            <h2>Payment Not Found</h2>
            <p>The payment record does not exist.</p>
            <a href='payments.php'>
                Back to Payments
            </a>
        </div>
    ");

}


$payment = $result->fetch_assoc();


/* =========================================================
   ADMIN INFORMATION
========================================================= */

$full_name = $_SESSION["full_name"] ?? "Administrator";


/* =========================================================
   STATUS
========================================================= */

$status = $payment["payment_status"];

$badge_class = "pending";

if ($status === "Paid") {

    $badge_class = "paid";

} elseif ($status === "Partially Paid") {

    $badge_class = "partial";

} elseif ($status === "Waived") {

    $badge_class = "waived";
}


/* =========================================================
   DATE FORMAT
========================================================= */

$created_date = !empty($payment["created_at"])
    ? date(
        "d M Y, H:i",
        strtotime($payment["created_at"])
    )
    : "—";


$paid_date = !empty($payment["paid_at"])
    ? date(
        "d M Y, H:i",
        strtotime($payment["paid_at"])
    )
    : "—";

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
    Receipt <?= e($payment["invoice_number"]) ?> | SmartHospital
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

    background: #f3f7f8;

    color: #17324d;

    min-height: 100vh;
}


/* =========================================================
   TOP BAR
========================================================= */

.topbar {

    height: 72px;

    background: white;

    border-bottom: 1px solid #e5edef;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 35px;
}


.brand {

    display: flex;

    align-items: center;

    gap: 10px;

    font-size: 19px;

    font-weight: 700;

    color: #17324d;
}


.logo {

    width: 36px;

    height: 36px;

    border-radius: 9px;

    background: #087f8c;

    color: white;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 20px;
}


.admin {

    font-size: 12px;

    color: #71818c;
}


/* =========================================================
   PAGE
========================================================= */

.page {

    max-width: 1050px;

    margin: 0 auto;

    padding: 30px 20px 50px;
}


.actions {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 20px;
}


.back {

    color: #087f8c;

    text-decoration: none;

    font-size: 13px;

    font-weight: 600;
}


.buttons {

    display: flex;

    gap: 10px;
}


.btn {

    border: none;

    border-radius: 7px;

    padding: 10px 15px;

    font-size: 12px;

    font-weight: 600;

    cursor: pointer;

    text-decoration: none;

    display: inline-block;
}


.btn-print {

    background: #087f8c;

    color: white;
}


.btn-print:hover {

    background: #066b76;
}


/* =========================================================
   RECEIPT
========================================================= */

.receipt {

    background: white;

    border-radius: 14px;

    border: 1px solid #e2eaed;

    overflow: hidden;

    box-shadow:
        0 8px 30px rgba(20,50,70,.06);
}


/* =========================================================
   RECEIPT HEADER
========================================================= */

.receipt-header {

    padding: 30px;

    border-bottom: 1px solid #edf1f3;

    display: flex;

    justify-content: space-between;

    gap: 30px;
}


.hospital-name {

    font-size: 24px;

    font-weight: 800;

    color: #17324d;
}


.hospital-subtitle {

    color: #81909a;

    font-size: 12px;

    margin-top: 5px;
}


.receipt-title {

    text-align: right;
}


.receipt-title h1 {

    font-size: 22px;

    color: #087f8c;

    margin-bottom: 6px;
}


.invoice {

    font-size: 12px;

    color: #71818c;
}


.invoice strong {

    color: #17324d;
}


/* =========================================================
   STATUS
========================================================= */

.status {

    margin-top: 10px;

    display: inline-block;

    padding: 6px 11px;

    border-radius: 15px;

    font-size: 10px;

    font-weight: 700;
}


.status.paid {

    background: #e8f7ef;

    color: #18794e;
}


.status.pending {

    background: #fff5df;

    color: #9a6700;
}


.status.partial {

    background: #eaf3ff;

    color: #2167a8;
}


.status.waived {

    background: #f1f3f5;

    color: #687783;
}


/* =========================================================
   PATIENT INFORMATION
========================================================= */

.patient-section {

    padding: 25px 30px;

    border-bottom: 1px solid #edf1f3;
}


.section-title {

    font-size: 11px;

    color: #82919b;

    text-transform: uppercase;

    letter-spacing: .7px;

    font-weight: 700;

    margin-bottom: 15px;
}


.patient-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 20px;
}


.info-label {

    color: #8998a2;

    font-size: 10px;

    margin-bottom: 4px;
}


.info-value {

    font-size: 13px;

    font-weight: 600;

    color: #17324d;
}


/* =========================================================
   PAYMENT DETAILS
========================================================= */

.payment-section {

    padding: 25px 30px;
}


.payment-table {

    width: 100%;

    border-collapse: collapse;
}


.payment-table th {

    background: #f8fafb;

    padding: 13px;

    text-align: left;

    color: #71818c;

    font-size: 10px;

    text-transform: uppercase;
}


.payment-table td {

    padding: 15px 13px;

    border-bottom: 1px solid #edf1f3;

    font-size: 12px;
}


.payment-table td:last-child,
.payment-table th:last-child {

    text-align: right;
}


/* =========================================================
   TOTALS
========================================================= */

.totals {

    width: 340px;

    max-width: 100%;

    margin-left: auto;

    margin-top: 20px;
}


.total-row {

    display: flex;

    justify-content: space-between;

    padding: 8px 0;

    font-size: 12px;

    color: #71818c;
}


.total-row strong {

    color: #17324d;
}


.total-final {

    border-top: 2px solid #17324d;

    margin-top: 7px;

    padding-top: 13px;

    font-size: 16px;

    color: #17324d;
}


.total-final strong {

    color: #087f8c;
}


/* =========================================================
   EXTRA INFORMATION
========================================================= */

.extra {

    padding: 0 30px 30px;

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 20px;
}


.extra-box {

    background: #f8fafb;

    border-radius: 9px;

    padding: 15px;
}


.extra-box h4 {

    font-size: 10px;

    color: #82919b;

    text-transform: uppercase;

    margin-bottom: 7px;
}


.extra-box p {

    font-size: 12px;

    color: #536774;

}


/* =========================================================
   FOOTER
========================================================= */

.receipt-footer {

    border-top: 1px solid #edf1f3;

    padding: 22px 30px;

    text-align: center;

    color: #8a99a4;

    font-size: 10px;

}


@media print {

    body {

        background: white;
    }


    .topbar,
    .actions {

        display: none;
    }


    .page {

        max-width: 100%;

        padding: 0;
    }


    .receipt {

        border: none;

        box-shadow: none;

        border-radius: 0;
    }

}


@media(max-width:700px) {

    .topbar {

        padding: 0 18px;
    }


    .admin {

        display: none;
    }


    .receipt-header {

        flex-direction: column;
    }


    .receipt-title {

        text-align: left;
    }


    .patient-grid {

        grid-template-columns: 1fr;
    }


    .extra {

        grid-template-columns: 1fr;
    }


    .actions {

        align-items: flex-start;

        gap: 15px;

        flex-direction: column;
    }

}

</style>

</head>


<body>


<!-- =========================================================
     TOPBAR
========================================================= -->

<header class="topbar">

    <div class="brand">

        <div class="logo">
            +
        </div>

        SmartHospital

    </div>


    <div class="admin">

        <?= e($full_name) ?>
        · Administrator

    </div>

</header>



<!-- =========================================================
     PAGE
========================================================= -->

<div class="page">


    <div class="actions">

        <a
            href="payments.php"
            class="back"
        >
            ← Back to Payments
        </a>


        <div class="buttons">

            <button
                onclick="window.print()"
                class="btn btn-print"
            >
                🖨 Print Receipt
            </button>

        </div>

    </div>



    <!-- =====================================================
         RECEIPT
    ====================================================== -->

    <section class="receipt">


        <!-- HEADER -->

        <div class="receipt-header">


            <div>

                <div class="hospital-name">
                    SmartHospital
                </div>

                <div class="hospital-subtitle">
                    Smart Healthcare Management System
                </div>

                <div class="hospital-subtitle">
                    Payment & Billing Department
                </div>

            </div>



            <div class="receipt-title">

                <h1>
                    PAYMENT RECEIPT
                </h1>

                <div class="invoice">

                    Invoice:

                    <strong>
                        <?= e($payment["invoice_number"]) ?>
                    </strong>

                </div>


                <span class="status <?= e($badge_class) ?>">

                    <?= e($status) ?>

                </span>

            </div>


        </div>



        <!-- PATIENT -->

        <div class="patient-section">


            <div class="section-title">
                Patient Information
            </div>


            <div class="patient-grid">


                <div>

                    <div class="info-label">
                        Patient Name
                    </div>

                    <div class="info-value">

                        <?= e(
                            $payment["patient_name"]
                        ) ?>

                    </div>

                </div>


                <div>

                    <div class="info-label">
                        Patient Number
                    </div>

                    <div class="info-value">

                        <?= e(
                            $payment["patient_number"]
                        ) ?>

                    </div>

                </div>


                <div>

                    <div class="info-label">
                        Payment Date
                    </div>

                    <div class="info-value">

                        <?= e($created_date) ?>

                    </div>

                </div>


            </div>


        </div>



        <!-- PAYMENT DETAILS -->

        <div class="payment-section">


            <div class="section-title">
                Payment Details
            </div>


            <table class="payment-table">


                <thead>

                    <tr>

                        <th>
                            Description
                        </th>

                        <th>
                            Payment Method
                        </th>

                        <th>
                            Amount
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <tr>

                        <td>
                            Hospital Services / Medical Care
                        </td>

                        <td>

                            <?= e(
                                $payment["payment_method"]
                            ) ?>

                        </td>

                        <td>

                            <?= number_format(
                                (float)$payment["amount"],
                                0
                            ) ?>

                            RWF

                        </td>

                    </tr>

                </tbody>


            </table>



            <!-- TOTALS -->

            <div class="totals">


                <div class="total-row">

                    <span>
                        Total Amount
                    </span>

                    <strong>

                        <?= number_format(
                            (float)$payment["amount"],
                            0
                        ) ?>

                        RWF

                    </strong>

                </div>


                <div class="total-row">

                    <span>
                        Insurance Contribution
                    </span>

                    <strong>

                        <?= number_format(
                            (float)$payment["insurance_amount"],
                            0
                        ) ?>

                        RWF

                    </strong>

                </div>


                <div class="total-row total-final">

                    <span>
                        Patient Paid
                    </span>

                    <strong>

                        <?= number_format(
                            (float)$payment["patient_amount"],
                            0
                        ) ?>

                        RWF

                    </strong>

                </div>


            </div>


        </div>



        <!-- EXTRA -->

        <div class="extra">


            <div class="extra-box">

                <h4>
                    Transaction Reference
                </h4>

                <p>

                    <?= !empty(
                        $payment["transaction_reference"]
                    )

                        ? e(
                            $payment["transaction_reference"]
                        )

                        : "—"
                    ?>

                </p>

            </div>



            <div class="extra-box">

                <h4>
                    Paid At
                </h4>

                <p>

                    <?= e($paid_date) ?>

                </p>

            </div>



            <?php if (!empty($payment["notes"])): ?>

                <div class="extra-box">

                    <h4>
                        Notes
                    </h4>

                    <p>

                        <?= e(
                            $payment["notes"]
                        ) ?>

                    </p>

                </div>

            <?php endif; ?>


        </div>



        <!-- FOOTER -->

        <div class="receipt-footer">

            Thank you for choosing SmartHospital.

            <br>

            This receipt was generated by the SmartHospital Management System.

        </div>


    </section>


</div>


</body>

</html>