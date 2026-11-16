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
    die("User query failed: " . htmlspecialchars($conn->error));
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
    die("Patient query failed: " . htmlspecialchars($conn->error));
}

$patient_stmt->bind_param("s", $email);
$patient_stmt->execute();

$patient_result = $patient_stmt->get_result();

if ($patient_result->num_rows === 0) {

    die("
        <div style='
            font-family: Arial;
            padding: 40px;
            text-align: center;
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


/* =========================================================
   GET PRESCRIPTIONS
========================================================= */

$prescriptions = [];

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

    WHERE p.patient_id = ?

    ORDER BY
        p.prescription_date DESC,
        p.id DESC
";

$prescription_stmt =
    $conn->prepare($prescription_sql);

if ($prescription_stmt) {

    $prescription_stmt->bind_param(
        "i",
        $patient_id
    );

    $prescription_stmt->execute();

    $prescription_result =
        $prescription_stmt->get_result();

    while (
        $row =
        $prescription_result->fetch_assoc()
    ) {

        $prescriptions[] = $row;
    }
}


/* =========================================================
   GET MEDICINE ITEMS FOR EACH PRESCRIPTION
========================================================= */

$prescription_items = [];

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
        "Prescription items query failed: " .
        htmlspecialchars($conn->error)
    );
}


/* =========================================================
   LOAD ITEMS
========================================================= */

foreach ($prescriptions as $prescription) {

    $prescription_id =
        (int) $prescription["id"];

    $item_stmt->bind_param(
        "i",
        $prescription_id
    );

    $item_stmt->execute();

    $item_result =
        $item_stmt->get_result();

    $prescription_items[
        $prescription_id
    ] = [];

    while (
        $item =
        $item_result->fetch_assoc()
    ) {

        $prescription_items[
            $prescription_id
        ][] = $item;
    }
}


/* =========================================================
   TOTAL PRESCRIPTIONS
========================================================= */

$total_prescriptions =
    count($prescriptions);


/* =========================================================
   COUNT PENDING
========================================================= */

$pending_count = 0;

$paid_count = 0;

$dispensed_count = 0;


foreach ($prescriptions as $prescription) {

    if (
        $prescription["status"]
        === "pending"
    ) {

        $pending_count++;
    }

    elseif (
        $prescription["status"]
        === "paid"
    ) {

        $paid_count++;
    }

    elseif (
        $prescription["status"]
        === "dispensed"
    ) {

        $dispensed_count++;
    }
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
    My Prescriptions | SmartHospital
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


/* =========================================================
   LOGO
========================================================= */

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


/* =========================================================
   TOPBAR
========================================================= */

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


/* =========================================================
   WELCOME
========================================================= */

.welcome {

    background:
        linear-gradient(
            135deg,
            #087f8c,
            #066b76
        );

    color: white;

    border-radius: 14px;

    padding: 27px 30px;

    margin-bottom: 25px;

    display: flex;

    align-items: center;

    justify-content: space-between;
}


.welcome h2 {

    font-size: 22px;

    margin-bottom: 7px;
}


.welcome p {

    font-size: 13px;

    opacity: .85;
}


.welcome-icon {

    font-size: 48px;

    opacity: .25;
}


/* =========================================================
   STATS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 18px;

    margin-bottom: 25px;
}


.stat-card {

    background: white;

    border:
        1px solid
        #e9eff2;

    border-radius: 12px;

    padding: 20px;

    display: flex;

    align-items: center;

    gap: 15px;

    box-shadow:
        0 5px 18px
        rgba(23,50,77,.04);
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

    font-size: 22px;

    margin-bottom: 3px;
}


.stat-card p {

    color: #8796a1;

    font-size: 12px;
}


/* =========================================================
   PRESCRIPTION CARD
========================================================= */

.prescription-card {

    background: white;

    border:
        1px solid
        #e9eff2;

    border-radius: 14px;

    margin-bottom: 22px;

    overflow: hidden;

    box-shadow:
        0 5px 18px
        rgba(23,50,77,.04);
}


/* =========================================================
   PRESCRIPTION HEADER
========================================================= */

.prescription-header {

    padding: 22px 25px;

    border-bottom:
        1px solid
        #edf1f3;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;
}


.prescription-header h3 {

    font-size: 16px;

    margin-bottom: 5px;
}


.prescription-meta {

    color: #8796a1;

    font-size: 12px;
}


.prescription-meta strong {

    color: #17324d;
}


/* =========================================================
   STATUS
========================================================= */

.status {

    display: inline-block;

    padding: 7px 13px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: 700;

    text-transform: capitalize;
}


.status.pending {

    background: #fff5df;

    color: #a66a00;
}


.status.paid {

    background: #e8f7f0;

    color: #16834d;
}


.status.dispensed {

    background: #e7f0ff;

    color: #315c9b;
}


.status.cancelled {

    background: #fff0ef;

    color: #b42318;
}


/* =========================================================
   TABLE
========================================================= */

.medicine-table {

    width: 100%;

    border-collapse: collapse;
}


.medicine-table th {

    text-align: left;

    padding: 13px 25px;

    background: #f8fafb;

    color: #7b8b96;

    font-size: 10px;

    text-transform: uppercase;

    letter-spacing: .4px;
}


.medicine-table td {

    padding: 15px 25px;

    border-top:
        1px solid
        #edf1f3;

    font-size: 12px;
}


.medicine-name {

    font-weight: 700;

    color: #17324d;
}


.medicine-category {

    display: block;

    color: #8a99a4;

    font-size: 10px;

    margin-top: 3px;
}


/* =========================================================
   TOTAL
========================================================= */

.prescription-total {

    padding: 18px 25px;

    background: #f8fbfc;

    display: flex;

    align-items: center;

    justify-content: space-between;
}


.total-label {

    color: #687781;

    font-size: 12px;
}


.total-amount {

    color: #087f8c;

    font-size: 20px;

    font-weight: 700;
}


/* =========================================================
   NOTES
========================================================= */

.notes {

    padding: 18px 25px;

    border-top:
        1px solid
        #edf1f3;
}


.notes strong {

    display: block;

    font-size: 12px;

    margin-bottom: 5px;
}


.notes p {

    color: #687781;

    font-size: 12px;

    line-height: 1.6;
}


/* =========================================================
   ACTIONS
========================================================= */

.prescription-actions {

    padding: 18px 25px;

    border-top:
        1px solid
        #edf1f3;

    display: flex;

    justify-content: flex-end;

    gap: 10px;
}


.btn {

    border: none;

    text-decoration: none;

    display: inline-block;

    padding: 11px 18px;

    border-radius: 8px;

    font-size: 12px;

    font-weight: 700;

    cursor: pointer;
}


.btn-pay {

    background: #087f8c;

    color: white;
}


.btn-pay:hover {

    background: #066b76;
}


.btn-view {

    background: #eef3f5;

    color: #17324d;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty {

    background: white;

    border:
        1px solid
        #e9eff2;

    border-radius: 14px;

    padding: 60px 25px;

    text-align: center;

    box-shadow:
        0 5px 18px
        rgba(23,50,77,.04);
}


.empty-icon {

    font-size: 48px;

    margin-bottom: 15px;
}


.empty h3 {

    font-size: 17px;

    margin-bottom: 7px;
}


.empty p {

    color: #8796a1;

    font-size: 12px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1050px) {

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

    .welcome {

        padding: 20px;
    }

    .prescription-header {

        align-items: flex-start;

        flex-direction: column;
    }

    .medicine-table {

        display: block;

        overflow-x: auto;
    }

    .prescription-total {

        gap: 15px;

        flex-direction: column;

        align-items: flex-start;
    }

    .prescription-actions {

        justify-content: stretch;

        flex-direction: column;
    }

    .btn {

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


        <a
            href="patient_dashboard.php"
        >

            <span class="menu-icon">
                ⌂
            </span>

            <span>
                Dashboard
            </span>

        </a>


        <a
            href="patient_queue.php"
        >

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


        <a
            href="patient_history.php"
        >

            <span class="menu-icon">
                📋
            </span>

            <span>
                My History
            </span>

        </a>


        <a
            href="patient_profile.php"
        >

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
                My Prescriptions
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


    <!-- =====================================================
         CONTENT
    ===================================================== -->

    <main class="content">


        <!-- =================================================
             WELCOME
        ================================================= -->

        <section class="welcome">

            <div>

                <h2>
                    My Medicines 💊
                </h2>

                <p>
                    View prescriptions from your doctor
                    and make payments for your medicines.
                </p>

            </div>


            <div class="welcome-icon">
                💊
            </div>

        </section>


        <!-- =================================================
             STATISTICS
        ================================================= -->

        <section class="stats">


            <div class="stat-card">

                <div class="stat-icon">
                    🧾
                </div>

                <div>

                    <h3>
                        <?= $total_prescriptions ?>
                    </h3>

                    <p>
                        Total Prescriptions
                    </p>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon">
                    ⏳
                </div>

                <div>

                    <h3>
                        <?= $pending_count ?>
                    </h3>

                    <p>
                        Awaiting Payment
                    </p>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon">
                    ✅
                </div>

                <div>

                    <h3>
                        <?= $paid_count ?>
                    </h3>

                    <p>
                        Paid
                    </p>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon">
                    💊
                </div>

                <div>

                    <h3>
                        <?= $dispensed_count ?>
                    </h3>

                    <p>
                        Dispensed
                    </p>

                </div>

            </div>


        </section>


        <!-- =================================================
             PRESCRIPTIONS
        ================================================= -->

        <?php if (empty($prescriptions)): ?>


            <div class="empty">

                <div class="empty-icon">
                    💊
                </div>

                <h3>
                    No Prescriptions Yet
                </h3>

                <p>
                    Your doctor has not created
                    a medicine prescription for you yet.
                </p>

            </div>


        <?php else: ?>


            <?php foreach (
                $prescriptions
                as $prescription
            ): ?>


                <?php

                $prescription_id =
                    (int) $prescription["id"];


                $items =
                    $prescription_items[
                        $prescription_id
                    ] ?? [];


                $grand_total = 0;


                foreach (
                    $items
                    as $item
                ) {

                    $grand_total +=
                        (float)
                        $item["total_price"];
                }


                $status =
                    strtolower(
                        $prescription["status"]
                    );

                ?>


                <div class="prescription-card">


                    <!-- HEADER -->

                    <div class="prescription-header">

                        <div>

                            <h3>

                                Prescription
                                #<?= $prescription_id ?>

                            </h3>


                            <div class="prescription-meta">

                                Doctor:

                                <strong>

                                    <?= htmlspecialchars(
                                        $prescription[
                                            "doctor_name"
                                        ] ?? "Not assigned"
                                    ) ?>

                                </strong>

                                &nbsp; • &nbsp;

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

                            </div>

                        </div>


                        <span
                            class="status
                            <?= htmlspecialchars($status) ?>"
                        >

                            <?= htmlspecialchars(
                                $status
                            ) ?>

                        </span>

                    </div>


                    <!-- MEDICINES -->

                    <?php if (!empty($items)): ?>


                        <table class="medicine-table">

                            <thead>

                                <tr>

                                    <th>
                                        Medicine
                                    </th>

                                    <th>
                                        Quantity
                                    </th>

                                    <th>
                                        Dosage
                                    </th>

                                    <th>
                                        Frequency
                                    </th>

                                    <th>
                                        Duration
                                    </th>

                                    <th>
                                        Price
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php foreach (
                                    $items
                                    as $item
                                ): ?>


                                    <tr>

                                        <td>

                                            <div
                                                class="medicine-name"
                                            >

                                                <?= htmlspecialchars(
                                                    $item[
                                                        "medicine_name"
                                                    ]
                                                ) ?>

                                            </div>


                                            <?php if (
                                                !empty(
                                                    $item[
                                                        "category"
                                                    ]
                                                )
                                            ): ?>

                                                <span
                                                    class="medicine-category"
                                                >

                                                    <?= htmlspecialchars(
                                                        $item[
                                                            "category"
                                                        ]
                                                    ) ?>

                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <td>

                                            <?= (int)
                                                $item[
                                                    "quantity"
                                                ] ?>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $item[
                                                    "dosage"
                                                ] ?? "-"
                                            ) ?>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $item[
                                                    "frequency"
                                                ] ?? "-"
                                            ) ?>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $item[
                                                    "duration"
                                                ] ?? "-"
                                            ) ?>

                                        </td>


                                        <td>

                                            <strong>

                                                <?= number_format(
                                                    (float)
                                                    $item[
                                                        "total_price"
                                                    ],
                                                    0
                                                ) ?>

                                                RWF

                                            </strong>

                                        </td>

                                    </tr>


                                <?php endforeach; ?>


                            </tbody>

                        </table>


                    <?php else: ?>


                        <div
                            style="
                                padding:25px;
                                color:#8796a1;
                                font-size:12px;
                            "
                        >

                            No medicines have been
                            added to this prescription yet.

                        </div>


                    <?php endif; ?>


                    <!-- TOTAL -->

                    <div class="prescription-total">

                        <div class="total-label">

                            Total Medicine Cost

                        </div>


                        <div class="total-amount">

                            <?= number_format(
                                $grand_total,
                                0
                            ) ?>

                            RWF

                        </div>

                    </div>


                    <!-- NOTES -->

                    <?php if (
                        !empty(
                            $prescription["notes"]
                        )
                    ): ?>


                        <div class="notes">

                            <strong>
                                Doctor's Instructions
                            </strong>

                            <p>

                                <?= nl2br(
                                    htmlspecialchars(
                                        $prescription[
                                            "notes"
                                        ]
                                    )
                                ) ?>

                            </p>

                        </div>


                    <?php endif; ?>


                    <!-- ACTIONS -->

                    <div class="prescription-actions">


                        <?php if (
                            $status === "pending"
                        ): ?>


                            <a
                                href="payment.php?prescription_id=<?= $prescription_id ?>"
                                class="btn btn-pay"
                            >

                                💳 Pay for Medicines

                            </a>


                        <?php elseif (
                            $status === "paid"
                        ): ?>


                            <span
                                class="btn"
                                style="
                                    background:#e8f7f0;
                                    color:#16834d;
                                "
                            >

                                ✅ Payment Confirmed

                            </span>


                        <?php elseif (
                            $status === "dispensed"
                        ): ?>


                            <span
                                class="btn"
                                style="
                                    background:#e7f0ff;
                                    color:#315c9b;
                                "
                            >

                                💊 Medicine Dispensed

                            </span>


                        <?php elseif (
                            $status === "cancelled"
                        ): ?>


                            <span
                                class="btn"
                                style="
                                    background:#fff0ef;
                                    color:#b42318;
                                "
                            >

                                Prescription Cancelled

                            </span>


                        <?php endif; ?>


                    </div>


                </div>


            <?php endforeach; ?>


        <?php endif; ?>


    </main>

</div>


</body>

</html>