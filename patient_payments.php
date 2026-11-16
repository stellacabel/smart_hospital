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

if (
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "patient"
) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];


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


/* =========================================================
   GET LOGGED-IN USER
========================================================= */

$user_sql = "
    SELECT
        id,
        full_name,
        email,
        role,
        status
    FROM users
    WHERE id = ?
    LIMIT 1
";

$user_stmt = $conn->prepare($user_sql);

if (!$user_stmt) {
    die(
        "User query failed: " .
        e($conn->error)
    );
}

$user_stmt->bind_param(
    "i",
    $user_id
);

$user_stmt->execute();

$user_result =
    $user_stmt->get_result();

if (
    !$user_result ||
    $user_result->num_rows === 0
) {

    session_destroy();

    header("Location: login.php");

    exit;
}

$user =
    $user_result->fetch_assoc();

$user_stmt->close();

$full_name =
    $user["full_name"];

$email =
    $user["email"];


/* =========================================================
   GET PATIENT RECORD
========================================================= */

$patient_sql = "
    SELECT
        id,
        patient_number,
        full_name,
        email,
        phone,
        address,
        insurance_provider
    FROM patients
    WHERE email = ?
    LIMIT 1
";

$patient_stmt =
    $conn->prepare($patient_sql);

if (!$patient_stmt) {

    die(
        "Patient query failed: " .
        e($conn->error)
    );
}

$patient_stmt->bind_param(
    "s",
    $email
);

$patient_stmt->execute();

$patient_result =
    $patient_stmt->get_result();

if (
    !$patient_result ||
    $patient_result->num_rows === 0
) {

    die("
        <div style='
            font-family:Arial;
            padding:40px;
            text-align:center;
        '>

            <h2>
                Patient profile not found
            </h2>

            <p>
                Your login account is not connected
                to a patient record.
            </p>

            <a href='logout.php'>
                Logout
            </a>

        </div>
    ");
}

$patient =
    $patient_result->fetch_assoc();

$patient_stmt->close();

$patient_id =
    (int) $patient["id"];


/* =========================================================
   CSRF TOKEN
========================================================= */

if (
    !isset(
        $_SESSION["payment_csrf_token"]
    )
) {

    $_SESSION["payment_csrf_token"] =
        bin2hex(
            random_bytes(32)
        );
}

$csrf_token =
    $_SESSION["payment_csrf_token"];


/* =========================================================
   MESSAGE
========================================================= */

$message = "";

$message_type = "";


/* =========================================================
   CALCULATE TOTAL CONSULTATION FEES
=========================================================

   IMPORTANT:

   The doctor enters the consultation fee in:

       consultations.consultation_fee

   We read that amount here.

   We include both:

       draft
       completed

   because a doctor may enter the fee while the
   consultation is still active.

========================================================= */

$fee_sql = "
    SELECT
        COALESCE(
            SUM(consultation_fee),
            0
        ) AS total_consultation_fees
    FROM consultations
    WHERE patient_id = ?
      AND status IN ('draft', 'completed')
";

$fee_stmt =
    $conn->prepare($fee_sql);

$total_consultation_fees = 0.00;

if ($fee_stmt) {

    $fee_stmt->bind_param(
        "i",
        $patient_id
    );

    $fee_stmt->execute();

    $fee_result =
        $fee_stmt->get_result();

    if ($fee_result) {

        $fee_row =
            $fee_result->fetch_assoc();

        $total_consultation_fees =
            (float)
            $fee_row[
                "total_consultation_fees"
            ];
    }

    $fee_stmt->close();
}


/* =========================================================
   CALCULATE OTHER PAYMENT CHARGES
=========================================================

   If your system already has other charges in the
   payments table, we DO NOT count payment records
   themselves as new charges.

   Consultation fees come from consultations.

========================================================= */


/* =========================================================
   TOTAL ALREADY PAID
========================================================= */

$already_paid_sql = "
    SELECT
        COALESCE(
            SUM(patient_amount),
            0
        ) AS paid_total
    FROM payments
    WHERE patient_id = ?
      AND payment_status = 'Paid'
";

$already_paid_stmt =
    $conn->prepare(
        $already_paid_sql
    );

$already_paid = 0.00;

if ($already_paid_stmt) {

    $already_paid_stmt->bind_param(
        "i",
        $patient_id
    );

    $already_paid_stmt->execute();

    $already_paid_result =
        $already_paid_stmt->get_result();

    if ($already_paid_result) {

        $already_paid_row =
            $already_paid_result->fetch_assoc();

        $already_paid =
            (float)
            $already_paid_row[
                "paid_total"
            ];
    }

    $already_paid_stmt->close();
}


/* =========================================================
   PENDING PAYMENTS
========================================================= */

$pending_sql = "
    SELECT
        COALESCE(
            SUM(patient_amount),
            0
        ) AS pending_total
    FROM payments
    WHERE patient_id = ?
      AND payment_status = 'Pending'
";

$pending_stmt =
    $conn->prepare(
        $pending_sql
    );

$pending_total = 0.00;

if ($pending_stmt) {

    $pending_stmt->bind_param(
        "i",
        $patient_id
    );

    $pending_stmt->execute();

    $pending_result =
        $pending_stmt->get_result();

    if ($pending_result) {

        $pending_row =
            $pending_result->fetch_assoc();

        $pending_total =
            (float)
            $pending_row[
                "pending_total"
            ];
    }

    $pending_stmt->close();
}


/* =========================================================
   CURRENT BALANCE
=========================================================

   Consultation fees
   -
   Paid
   -
   Pending verification

   =
   Amount still due

========================================================= */

$amount_due =
    $total_consultation_fees
    - $already_paid
    - $pending_total;


/*
 * Never show negative balance.
 */

if ($amount_due < 0) {

    $amount_due = 0.00;
}


/* =========================================================
   SUBMIT PAYMENT
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["submit_payment"])
) {


    /* =====================================================
       CHECK CSRF
    ===================================================== */

    $submitted_token =
        $_POST["csrf_token"] ?? "";

    if (
        !hash_equals(
            $_SESSION[
                "payment_csrf_token"
            ],
            $submitted_token
        )
    ) {

        $message =
            "Invalid security token. Please refresh the page.";

        $message_type =
            "error";

    } else {


        /* =================================================
           PAYMENT METHOD
        ================================================= */

        $payment_method =
            trim(
                $_POST[
                    "payment_method"
                ] ?? ""
            );


        /* =================================================
           TRANSACTION REFERENCE
        ================================================= */

        $transaction_reference =
            trim(
                $_POST[
                    "transaction_reference"
                ] ?? ""
            );


        /* =================================================
           NOTES
        ================================================= */

        $notes =
            trim(
                $_POST["notes"] ?? ""
            );


        /* =================================================
           VALIDATE AMOUNT
        ================================================= */

        if ($amount_due <= 0) {

            $message =
                "You currently have no outstanding payment.";

            $message_type =
                "error";

        }


        /* =================================================
           VALIDATE METHOD
        ================================================= */

        elseif (
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
                "Please select a valid payment method.";

            $message_type =
                "error";

        }


        /* =================================================
           VALIDATE REFERENCE
        ================================================= */

        elseif (
            (
                $payment_method === "Mobile Money" ||
                $payment_method === "Bank"
            )
            &&
            $transaction_reference === ""
        ) {

            $message =
                "Please enter the transaction reference.";

            $message_type =
                "error";

        }


        /* =================================================
           CREATE PAYMENT
        ================================================= */

        else {


            /* =============================================
               GENERATE INVOICE NUMBER
            ============================================= */

            $invoice_number =
                "";

            $invoice_created =
                false;


            for (
                $attempt = 0;
                $attempt < 10;
                $attempt++
            ) {

                $invoice_number =
                    "INV-" .
                    date("Ymd") .
                    "-" .
                    strtoupper(
                        bin2hex(
                            random_bytes(3)
                        )
                    );


                $check_sql = "
                    SELECT id
                    FROM payments
                    WHERE invoice_number = ?
                    LIMIT 1
                ";


                $check_stmt =
                    $conn->prepare(
                        $check_sql
                    );


                if (!$check_stmt) {
                    break;
                }


                $check_stmt->bind_param(
                    "s",
                    $invoice_number
                );


                $check_stmt->execute();


                $check_result =
                    $check_stmt->get_result();


                if (
                    !$check_result ||
                    $check_result->num_rows === 0
                ) {

                    $invoice_created =
                        true;

                    $check_stmt->close();

                    break;
                }

                $check_stmt->close();
            }


            /* =============================================
               INVOICE FAILED
            ============================================= */

            if (!$invoice_created) {

                $message =
                    "Could not create a unique invoice number.";

                $message_type =
                    "error";

            } else {


                /* =========================================
                   PAYMENT AMOUNT
                ========================================= */

                $amount =
                    $amount_due;


                /*
                 * For this system the amount entered by the
                 * patient is the patient's responsibility.
                 */

                $insurance_amount =
                    0.00;


                $patient_amount =
                    $amount_due;


                $payment_status =
                    "Pending";


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
                        NULL,
                        NULL,
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


                $insert_stmt =
                    $conn->prepare(
                        $insert_sql
                    );


                if (!$insert_stmt) {

                    $message =
                        "Payment query failed: " .
                        e($conn->error);

                    $message_type =
                        "error";

                } else {


                    /*
                     * i  = patient_id
                     * s  = invoice_number
                     * d  = amount
                     * d  = insurance_amount
                     * d  = patient_amount
                     * s  = payment_method
                     * s  = payment_status
                     * s  = transaction_reference
                     * s  = notes
                     */

                    $insert_stmt->bind_param(
                        "isdddssss",
                        $patient_id,
                        $invoice_number,
                        $amount,
                        $insurance_amount,
                        $patient_amount,
                        $payment_method,
                        $payment_status,
                        $transaction_reference,
                        $notes
                    );


                    if (
                        $insert_stmt->execute()
                    ) {

                        $message =
                            "Payment submitted successfully. " .
                            number_format(
                                $amount,
                                0
                            ) .
                            " RWF is now pending verification.";

                        $message_type =
                            "success";


                        /*
                         * New CSRF token.
                         */

                        $_SESSION[
                            "payment_csrf_token"
                        ] =
                            bin2hex(
                                random_bytes(32)
                            );


                        $csrf_token =
                            $_SESSION[
                                "payment_csrf_token"
                            ];


                        /*
                         * Update values immediately.
                         */

                        $pending_total +=
                            $patient_amount;


                        $amount_due =
                            $total_consultation_fees
                            - $already_paid
                            - $pending_total;


                        if ($amount_due < 0) {
                            $amount_due = 0.00;
                        }

                    } else {

                        $message =
                            "Could not submit payment: " .
                            e(
                                $insert_stmt->error
                            );

                        $message_type =
                            "error";
                    }


                    $insert_stmt->close();
                }
            }
        }
    }
}


/* =========================================================
   PAYMENT STATISTICS
========================================================= */

$total_paid =
    0.00;

$pending_amount =
    0.00;

$payment_count =
    0;


/* =========================================================
   TOTAL PAID
========================================================= */

$paid_sql = "
    SELECT
        COALESCE(
            SUM(patient_amount),
            0
        ) AS total_paid
    FROM payments
    WHERE patient_id = ?
      AND payment_status = 'Paid'
";

$paid_stmt =
    $conn->prepare(
        $paid_sql
    );

if ($paid_stmt) {

    $paid_stmt->bind_param(
        "i",
        $patient_id
    );

    $paid_stmt->execute();

    $paid_result =
        $paid_stmt->get_result();

    if ($paid_result) {

        $paid_row =
            $paid_result->fetch_assoc();

        $total_paid =
            (float)
            $paid_row[
                "total_paid"
            ];
    }

    $paid_stmt->close();
}


/* =========================================================
   PENDING STAT
========================================================= */

$pending_stat_sql = "
    SELECT
        COALESCE(
            SUM(patient_amount),
            0
        ) AS pending_amount
    FROM payments
    WHERE patient_id = ?
      AND payment_status = 'Pending'
";

$pending_stat_stmt =
    $conn->prepare(
        $pending_stat_sql
    );

if ($pending_stat_stmt) {

    $pending_stat_stmt->bind_param(
        "i",
        $patient_id
    );

    $pending_stat_stmt->execute();

    $pending_stat_result =
        $pending_stat_stmt->get_result();

    if ($pending_stat_result) {

        $pending_stat_row =
            $pending_stat_result->fetch_assoc();

        $pending_amount =
            (float)
            $pending_stat_row[
                "pending_amount"
            ];
    }

    $pending_stat_stmt->close();
}


/* =========================================================
   PAYMENT COUNT
========================================================= */

$count_sql = "
    SELECT
        COUNT(*) AS total
    FROM payments
    WHERE patient_id = ?
";

$count_stmt =
    $conn->prepare(
        $count_sql
    );

if ($count_stmt) {

    $count_stmt->bind_param(
        "i",
        $patient_id
    );

    $count_stmt->execute();

    $count_result =
        $count_stmt->get_result();

    if ($count_result) {

        $count_row =
            $count_result->fetch_assoc();

        $payment_count =
            (int)
            $count_row["total"];
    }

    $count_stmt->close();
}


/* =========================================================
   PAYMENT HISTORY
========================================================= */

$history_sql = "
    SELECT
        id,
        invoice_number,
        amount,
        insurance_amount,
        patient_amount,
        payment_method,
        payment_status,
        transaction_reference,
        notes,
        paid_at,
        created_at
    FROM payments
    WHERE patient_id = ?
    ORDER BY created_at DESC
";

$history_stmt =
    $conn->prepare(
        $history_sql
    );

$history_result =
    null;

if ($history_stmt) {

    $history_stmt->bind_param(
        "i",
        $patient_id
    );

    $history_stmt->execute();

    $history_result =
        $history_stmt->get_result();
}


/* =========================================================
   PATIENT INITIAL
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
    Payments | SmartHospital
</title>


<style>

/* =========================================================
   RESET
========================================================= */

* {
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body {
    font-family:"Segoe UI",Arial,sans-serif;
    background:#f5f9fa;
    color:#17324d;
}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {
    position:fixed;
    left:0;
    top:0;
    width:245px;
    height:100vh;
    background:#102d44;
    color:white;
    padding:25px 18px;
    z-index:100;
}

.logo {
    display:flex;
    align-items:center;
    gap:10px;
    padding:0 10px 30px;
    border-bottom:1px solid rgba(255,255,255,.10);
    color:white;
    text-decoration:none;
    font-size:20px;
    font-weight:700;
}

.logo-icon {
    width:38px;
    height:38px;
    background:#087f8c;
    border-radius:10px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:21px;
}

.menu {
    margin-top:30px;
}

.menu-title {
    color:#8fa5b5;
    font-size:11px;
    text-transform:uppercase;
    letter-spacing:1px;
    padding:0 12px;
    margin-bottom:12px;
}

.menu a {
    display:flex;
    align-items:center;
    gap:12px;
    color:#d9e4ea;
    text-decoration:none;
    padding:13px 12px;
    margin-bottom:5px;
    border-radius:8px;
    font-size:14px;
}

.menu a:hover,
.menu a.active {
    background:#087f8c;
    color:white;
}

.menu-icon {
    width:22px;
    text-align:center;
    font-size:16px;
}

.logout {
    position:absolute;
    bottom:25px;
    left:18px;
    right:18px;
}

.logout a {
    display:block;
    text-align:center;
    padding:11px;
    border-radius:8px;
    background:rgba(255,255,255,.08);
    color:#dce7ed;
    text-decoration:none;
    font-size:13px;
}

.logout a:hover {
    background:#b42318;
    color:white;
}


/* =========================================================
   MAIN
========================================================= */

.main {
    margin-left:245px;
    min-height:100vh;
}


/* =========================================================
   TOPBAR
========================================================= */

.topbar {
    height:76px;
    background:white;
    border-bottom:1px solid #e8eef1;
    padding:0 35px;
    display:flex;
    align-items:center;
    justify-content:space-between;
}

.page-title h1 {
    font-size:21px;
}

.page-title p {
    margin-top:3px;
    color:#8494a0;
    font-size:12px;
}

.profile {
    display:flex;
    align-items:center;
    gap:11px;
}

.avatar {
    width:38px;
    height:38px;
    border-radius:50%;
    background:#e8f7f8;
    color:#087f8c;
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:700;
}

.profile-info strong {
    display:block;
    font-size:13px;
}

.profile-info span {
    color:#8a99a4;
    font-size:11px;
}


/* =========================================================
   CONTENT
========================================================= */

.content {
    padding:32px 35px;
}


/* =========================================================
   PAGE HEADER
========================================================= */

.page-header {
    background:linear-gradient(
        135deg,
        #087f8c,
        #066b76
    );

    color:white;

    border-radius:14px;

    padding:27px 30px;

    margin-bottom:25px;

    display:flex;

    align-items:center;

    justify-content:space-between;
}

.page-header h2 {
    font-size:22px;
    margin-bottom:7px;
}

.page-header p {
    font-size:13px;
    opacity:.88;
}

.page-header-icon {
    font-size:48px;
    opacity:.25;
}


/* =========================================================
   MESSAGE
========================================================= */

.message {
    padding:15px 18px;
    border-radius:10px;
    margin-bottom:20px;
    font-size:13px;
    font-weight:600;
}

.message.success {
    background:#e9f8ef;
    color:#176b3a;
    border:1px solid #bfe8ce;
}

.message.error {
    background:#fff0ef;
    color:#a5221b;
    border:1px solid #f0c2be;
}


/* =========================================================
   STATS
========================================================= */

.stats {
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:18px;
    margin-bottom:25px;
}

.stat-card {
    background:white;
    border:1px solid #e9eff2;
    border-radius:12px;
    padding:20px;
    display:flex;
    align-items:center;
    gap:15px;
    box-shadow:0 5px 18px rgba(23,50,77,.04);
}

.stat-icon {
    width:45px;
    height:45px;
    border-radius:10px;
    background:#e8f7f8;
    color:#087f8c;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:19px;
}

.stat-card h3 {
    font-size:19px;
    margin-bottom:3px;
}

.stat-card p {
    color:#8796a1;
    font-size:12px;
}


/* =========================================================
   PAYMENT GRID
========================================================= */

.payment-grid {
    display:grid;
    grid-template-columns:1fr 1.5fr;
    gap:20px;
    margin-bottom:25px;
}


/* =========================================================
   CARD
========================================================= */

.card {
    background:white;
    border:1px solid #e9eff2;
    border-radius:12px;
    padding:23px;
    box-shadow:0 5px 18px rgba(23,50,77,.04);
}

.card-header {
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:20px;
}

.card-header h3 {
    font-size:16px;
}

.card-header span {
    color:#8b9aa4;
    font-size:11px;
}


/* =========================================================
   FORM
========================================================= */

.form-group {
    margin-bottom:17px;
}

.form-group label {
    display:block;
    font-size:12px;
    font-weight:600;
    margin-bottom:7px;
    color:#314b61;
}

.form-control {
    width:100%;
    padding:12px 13px;
    border:1px solid #dce5e9;
    border-radius:8px;
    font-family:inherit;
    font-size:13px;
    color:#17324d;
    background:white;
    outline:none;
}

.form-control:focus {
    border-color:#087f8c;
    box-shadow:0 0 0 3px rgba(8,127,140,.08);
}


/* =========================================================
   AMOUNT BOX
========================================================= */

.amount-box {
    background:#eaf8f8;
    border:2px solid #087f8c;
    border-radius:12px;
    padding:18px;
    margin-bottom:20px;
}

.amount-box small {
    display:block;
    color:#53717a;
    font-size:11px;
    margin-bottom:5px;
}

.amount-box strong {
    display:block;
    color:#087f8c;
    font-size:28px;
}

.amount-box .invoice-note {
    margin-top:7px;
    font-size:10px;
    color:#70868d;
}


/* =========================================================
   BUTTON
========================================================= */

.submit-btn {
    width:100%;
    border:none;
    cursor:pointer;
    padding:13px;
    border-radius:8px;
    background:#087f8c;
    color:white;
    font-size:13px;
    font-weight:700;
}

.submit-btn:hover {
    background:#066b76;
}

.submit-btn:disabled {
    background:#aebdc2;
    cursor:not-allowed;
}


/* =========================================================
   BALANCE
========================================================= */

.balance-good {
    background:#e9f8ef;
    border-left:4px solid #19924a;
    padding:15px;
    border-radius:8px;
    color:#176b3a;
    font-size:12px;
}


/* =========================================================
   INFO
========================================================= */

.info-box {
    background:#f7fafb;
    border-radius:9px;
    padding:15px;
    margin-bottom:12px;
}

.info-box span {
    display:block;
    color:#8796a1;
    font-size:10px;
    margin-bottom:5px;
}

.info-box strong {
    font-size:13px;
}


/* =========================================================
   NOTICE
========================================================= */

.notice {
    background:#f4fafb;
    border-left:4px solid #087f8c;
    padding:13px 15px;
    border-radius:7px;
    color:#536b7a;
    font-size:11px;
    line-height:1.6;
    margin-top:15px;
}


/* =========================================================
   STATUS
========================================================= */

.status {
    display:inline-block;
    padding:6px 11px;
    border-radius:20px;
    font-size:10px;
    font-weight:700;
}

.status.pending {
    background:#fff5df;
    color:#a66a00;
}

.status.paid {
    background:#e8f7ef;
    color:#187441;
}

.status.partially-paid {
    background:#e7f0ff;
    color:#315c9b;
}

.status.waived {
    background:#eef3f5;
    color:#687781;
}


/* =========================================================
   HISTORY
========================================================= */

.history-card {
    overflow:hidden;
}

.table-wrapper {
    width:100%;
    overflow-x:auto;
}

table {
    width:100%;
    border-collapse:collapse;
    min-width:850px;
}

thead {
    background:#f7fafb;
}

th {
    text-align:left;
    padding:13px 12px;
    font-size:10px;
    text-transform:uppercase;
    letter-spacing:.4px;
    color:#738591;
    border-bottom:1px solid #e5ecef;
}

td {
    padding:14px 12px;
    font-size:12px;
    border-bottom:1px solid #edf1f3;
    vertical-align:middle;
}

.invoice {
    color:#087f8c;
    font-weight:700;
}

.amount {
    font-weight:700;
}

.reference {
    color:#647481;
    font-size:11px;
}


/* =========================================================
   EMPTY
========================================================= */

.empty {
    text-align:center;
    padding:45px 20px;
    color:#8796a1;
}

.empty-icon {
    font-size:40px;
    margin-bottom:12px;
}

.empty strong {
    display:block;
    color:#17324d;
    font-size:14px;
    margin-bottom:5px;
}

.empty p {
    font-size:12px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1100px) {

    .stats {
        grid-template-columns:repeat(2,1fr);
    }

    .payment-grid {
        grid-template-columns:1fr;
    }
}


@media(max-width:700px) {

    .sidebar {
        width:70px;
        padding:20px 10px;
    }

    .logo {
        justify-content:center;
        padding:0 0 20px;
    }

    .logo span:last-child {
        display:none;
    }

    .menu-title,
    .menu a span:not(.menu-icon),
    .logout a {
        display:none;
    }

    .menu a {
        justify-content:center;
    }

    .main {
        margin-left:70px;
    }

    .topbar {
        padding:0 20px;
    }

    .profile-info {
        display:none;
    }

    .content {
        padding:22px 18px;
    }

    .stats {
        grid-template-columns:1fr;
    }

    .page-header {
        padding:20px;
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


        <a href="patient_prescriptions.php">

            <span class="menu-icon">
                💊
            </span>

            <span>
                My Prescriptions
            </span>

        </a>


        <a
            href="patient_payments.php"
            class="active"
        >

            <span class="menu-icon">
                💳
            </span>

            <span>
                Payments
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


<!-- =========================================================
     MAIN
========================================================= -->

<div class="main">


    <!-- =====================================================
         TOPBAR
    ===================================================== -->

    <header class="topbar">


        <div class="page-title">

            <h1>
                Payments
            </h1>

            <p>
                SmartHospital Patient Payment Management
            </p>

        </div>


        <div class="profile">


            <div class="avatar">

                <?= e($initial) ?>

            </div>


            <div class="profile-info">

                <strong>
                    <?= e($full_name) ?>
                </strong>

                <span>
                    Patient
                </span>

            </div>


        </div>


    </header>


    <!-- =====================================================
         CONTENT
    ===================================================== -->

    <main class="content">


        <!-- =================================================
             HEADER
        ================================================= -->

        <section class="page-header">


            <div>

                <h2>
                    Payment Center 💳
                </h2>

                <p>
                    Your payment amount is calculated
                    automatically from your hospital account.
                </p>

            </div>


            <div class="page-header-icon">
                💳
            </div>


        </section>


        <!-- =================================================
             MESSAGE
        ================================================= -->

        <?php if ($message !== ""): ?>

            <div
                class="message <?= e($message_type) ?>"
            >

                <?= e($message) ?>

            </div>

        <?php endif; ?>


        <!-- =================================================
             STATISTICS
        ================================================= -->

        <section class="stats">


            <!-- TOTAL PAID -->

            <div class="stat-card">

                <div class="stat-icon">
                    💰
                </div>

                <div>

                    <h3>
                        <?= number_format(
                            $total_paid,
                            0
                        ) ?>
                        RWF
                    </h3>

                    <p>
                        Total Paid
                    </p>

                </div>

            </div>


            <!-- PENDING -->

            <div class="stat-card">

                <div class="stat-icon">
                    ⏳
                </div>

                <div>

                    <h3>
                        <?= number_format(
                            $pending_amount,
                            0
                        ) ?>
                        RWF
                    </h3>

                    <p>
                        Pending
                    </p>

                </div>

            </div>


            <!-- RECORDS -->

            <div class="stat-card">

                <div class="stat-icon">
                    📋
                </div>

                <div>

                    <h3>
                        <?= $payment_count ?>
                    </h3>

                    <p>
                        Payment Records
                    </p>

                </div>

            </div>


            <!-- BALANCE -->

            <div class="stat-card">

                <div class="stat-icon">
                    💳
                </div>

                <div>

                    <h3>
                        <?= number_format(
                            $amount_due,
                            0
                        ) ?>
                        RWF
                    </h3>

                    <p>
                        Amount Due
                    </p>

                </div>

            </div>


        </section>


        <!-- =================================================
             PAYMENT GRID
        ================================================= -->

        <section class="payment-grid">


            <!-- =================================================
                 MAKE PAYMENT
            ================================================= -->

            <div class="card">


                <div class="card-header">

                    <h3>
                        Make a Payment
                    </h3>

                    <span>
                        Secure Payment
                    </span>

                </div>


                <?php if ($amount_due > 0): ?>


                    <!-- =====================================
                         AMOUNT
                    ====================================== -->

                    <div class="amount-box">

                        <small>
                            AMOUNT YOU MUST PAY
                        </small>

                        <strong>

                            <?= number_format(
                                $amount_due,
                                0
                            ) ?>

                            RWF

                        </strong>


                        <div class="invoice-note">

                            This amount comes from your
                            consultation fee.

                        </div>

                    </div>


                    <!-- =====================================
                         PAYMENT FORM
                    ====================================== -->

                    <form
                        method="POST"
                        action=""
                    >


                        <!-- CSRF -->

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e($csrf_token) ?>"
                        >


                        <!-- AMOUNT -->

                        <div class="form-group">

                            <label>
                                Amount (RWF)
                            </label>


                            <input
                                type="text"
                                class="form-control"
                                value="<?= number_format(
                                    $amount_due,
                                    0
                                ) ?> RWF"
                                readonly
                            >

                        </div>


                        <!-- PAYMENT METHOD -->

                        <div class="form-group">

                            <label>
                                Payment Method
                            </label>


                            <select
                                name="payment_method"
                                id="paymentMethod"
                                class="form-control"
                                required
                            >

                                <option value="">
                                    Select payment method
                                </option>

                                <option value="Mobile Money">
                                    Mobile Money
                                </option>

                                <option value="Bank">
                                    Bank
                                </option>

                                <option value="Cash">
                                    Cash
                                </option>

                                <option value="Insurance">
                                    Insurance
                                </option>

                            </select>

                        </div>


                        <!-- TRANSACTION REFERENCE -->

                        <div class="form-group">

                            <label>
                                Transaction Reference
                            </label>


                            <input
                                type="text"
                                name="transaction_reference"
                                id="transactionReference"
                                class="form-control"
                                placeholder="Optional"
                            >


                            <small
                                id="referenceHelp"
                                style="
                                    display:block;
                                    margin-top:6px;
                                    color:#8796a1;
                                    font-size:10px;
                                "
                            >

                                Required for Mobile Money
                                and Bank.

                            </small>

                        </div>


                        <!-- NOTES -->

                        <div class="form-group">

                            <label>
                                Notes
                            </label>


                            <textarea
                                name="notes"
                                class="form-control"
                                placeholder="Optional payment notes..."
                            ></textarea>

                        </div>


                        <!-- SUBMIT -->

                        <button
                            type="submit"
                            name="submit_payment"
                            class="submit-btn"
                        >

                            💳 Pay

                            <?= number_format(
                                $amount_due,
                                0
                            ) ?>

                            RWF

                        </button>


                        <div class="notice">

                            Your payment will be saved as
                            <strong>Pending</strong>
                            until hospital administration
                            verifies it.

                        </div>


                    </form>


                <?php else: ?>


                    <!-- =====================================
                         NO BALANCE
                    ====================================== -->

                    <div class="balance-good">

                        <strong>
                            ✅ No outstanding payment
                        </strong>


                        <br>
                        <br>


                        You currently have no amount due.
                        Your account is up to date.

                    </div>


                <?php endif; ?>


            </div>


            <!-- =================================================
                 ACCOUNT INFORMATION
            ================================================= -->

            <div class="card">


                <div class="card-header">

                    <h3>
                        Payment Information
                    </h3>

                    <span>
                        Patient Account
                    </span>

                </div>


                <!-- PATIENT -->

                <div class="info-box">

                    <span>
                        Patient Name
                    </span>

                    <strong>

                        <?= e(
                            $patient["full_name"]
                        ) ?>

                    </strong>

                </div>


                <!-- PATIENT NUMBER -->

                <div class="info-box">

                    <span>
                        Patient Number
                    </span>

                    <strong>

                        <?= e(
                            $patient["patient_number"]
                        ) ?>

                    </strong>

                </div>


                <!-- EMAIL -->

                <div class="info-box">

                    <span>
                        Email
                    </span>

                    <strong>

                        <?= e(
                            $patient["email"]
                        ) ?>

                    </strong>

                </div>


                <!-- INSURANCE -->

                <?php if (
                    !empty(
                        $patient[
                            "insurance_provider"
                        ]
                    )
                ): ?>


                    <div class="info-box">

                        <span>
                            Insurance Provider
                        </span>

                        <strong>

                            <?= e(
                                $patient[
                                    "insurance_provider"
                                ]
                            ) ?>

                        </strong>

                    </div>


                <?php endif; ?>


                <!-- CONSULTATION FEES -->

                <div class="info-box">

                    <span>
                        Total Consultation Fees
                    </span>

                    <strong>

                        <?= number_format(
                            $total_consultation_fees,
                            0
                        ) ?>

                        RWF

                    </strong>

                </div>


                <!-- PAID -->

                <div class="info-box">

                    <span>
                        Already Paid
                    </span>

                    <strong>

                        <?= number_format(
                            $already_paid,
                            0
                        ) ?>

                        RWF

                    </strong>

                </div>


                <!-- PENDING -->

                <div class="info-box">

                    <span>
                        Pending Verification
                    </span>

                    <strong>

                        <?= number_format(
                            $pending_total,
                            0
                        ) ?>

                        RWF

                    </strong>

                </div>


                <!-- BALANCE -->

                <div class="info-box">

                    <span>
                        Current Balance
                    </span>

                    <strong
                        style="
                            color:#087f8c;
                            font-size:18px;
                        "
                    >

                        <?= number_format(
                            $amount_due,
                            0
                        ) ?>

                        RWF

                    </strong>

                </div>


            </div>


        </section>


        <!-- =================================================
             PAYMENT HISTORY
        ================================================= -->

        <section class="card history-card">


            <div class="card-header">

                <h3>
                    My Payment History
                </h3>

                <span>
                    <?= $payment_count ?>
                    records
                </span>

            </div>


            <?php if (
                $history_result &&
                $history_result->num_rows > 0
            ): ?>


                <div class="table-wrapper">


                    <table>


                        <thead>

                            <tr>

                                <th>
                                    Invoice
                                </th>

                                <th>
                                    Amount
                                </th>

                                <th>
                                    Patient Amount
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
                                    Date
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php while (
                            $payment =
                            $history_result->fetch_assoc()
                        ): ?>


                            <?php

                            $status_class =
                                strtolower(
                                    str_replace(
                                        " ",
                                        "-",
                                        $payment[
                                            "payment_status"
                                        ]
                                    )
                                );

                            ?>


                            <tr>


                                <!-- INVOICE -->

                                <td>

                                    <span class="invoice">

                                        <?= e(
                                            $payment[
                                                "invoice_number"
                                            ]
                                        ) ?>

                                    </span>

                                </td>


                                <!-- AMOUNT -->

                                <td>

                                    <span class="amount">

                                        <?= number_format(
                                            (float)
                                            $payment[
                                                "amount"
                                            ],
                                            0
                                        ) ?>

                                        RWF

                                    </span>

                                </td>


                                <!-- PATIENT AMOUNT -->

                                <td>

                                    <?= number_format(
                                        (float)
                                        $payment[
                                            "patient_amount"
                                        ],
                                        0
                                    ) ?>

                                    RWF

                                </td>


                                <!-- METHOD -->

                                <td>

                                    <?= e(
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


                                        <span class="reference">

                                            <?= e(
                                                $payment[
                                                    "transaction_reference"
                                                ]
                                            ) ?>

                                        </span>


                                    <?php else: ?>


                                        <span
                                            style="
                                                color:#a1adb5;
                                            "
                                        >
                                            —
                                        </span>


                                    <?php endif; ?>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <span
                                        class="status <?= e(
                                            $status_class
                                        ) ?>"
                                    >

                                        <?= e(
                                            $payment[
                                                "payment_status"
                                            ]
                                        ) ?>

                                    </span>

                                </td>


                                <!-- DATE -->

                                <td>

                                    <?= e(
                                        date(
                                            "d M Y, H:i",
                                            strtotime(
                                                $payment[
                                                    "created_at"
                                                ]
                                            )
                                        )
                                    ) ?>

                                </td>


                            </tr>


                        <?php endwhile; ?>


                        </tbody>


                    </table>


                </div>


            <?php else: ?>


                <div class="empty">

                    <div class="empty-icon">
                        💳
                    </div>


                    <strong>
                        No payment records yet
                    </strong>


                    <p>

                        Your payment history will appear
                        here after you submit a payment.

                    </p>

                </div>


            <?php endif; ?>


        </section>


    </main>


</div>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script>

const paymentMethod =
    document.getElementById(
        "paymentMethod"
    );

const transactionReference =
    document.getElementById(
        "transactionReference"
    );

const referenceHelp =
    document.getElementById(
        "referenceHelp"
    );


function updateReferenceField()
{

    if (
        !paymentMethod ||
        !transactionReference
    ) {

        return;
    }


    const method =
        paymentMethod.value;


    if (
        method === "Mobile Money" ||
        method === "Bank"
    ) {

        transactionReference.required =
            true;


        transactionReference.placeholder =
            method === "Mobile Money"
                ? "Enter Mobile Money transaction ID"
                : "Enter bank transaction reference";


        referenceHelp.textContent =
            "Transaction reference is required for " +
            method +
            " payments.";

    } else {

        transactionReference.required =
            false;


        transactionReference.placeholder =
            "Optional transaction reference";


        referenceHelp.textContent =
            "Transaction reference is optional for this method.";
    }
}


if (paymentMethod) {

    paymentMethod.addEventListener(
        "change",
        updateReferenceField
    );


    updateReferenceField();
}

</script>


</body>

</html>