<?php

/* =========================================================
   SMART HOSPITAL - PATIENT REGISTRATION
   ========================================================= */

require_once "db.php";

$message = "";
$message_type = "";


/* =========================================================
   DEFAULT VALUES
========================================================= */

$first_name = "";
$last_name = "";
$phone = "";
$gender = "";
$date_of_birth = "";
$email = "";
$national_id = "";
$address = "";

$family_number = "";
$is_head_of_family = "0";
$relationship_to_head = "";

$insurance_type = "None";
$insurance_provider = "";
$insurance_number = "";
$insurance_expiry = "";

$emergency_contact = "";
$emergency_phone = "";


/* =========================================================
   HANDLE REGISTRATION
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /* -----------------------------------------------------
       PERSONAL INFORMATION
    ----------------------------------------------------- */

    $first_name = trim($_POST["first_name"] ?? "");
    $last_name = trim($_POST["last_name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $gender = trim($_POST["gender"] ?? "");
    $date_of_birth = trim($_POST["date_of_birth"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $national_id = trim($_POST["national_id"] ?? "");
    $address = trim($_POST["address"] ?? "");


    /* -----------------------------------------------------
       FAMILY INFORMATION
    ----------------------------------------------------- */

    $family_number = trim($_POST["family_number"] ?? "");

    $is_head_of_family =
        isset($_POST["is_head_of_family"])
            ? "1"
            : "0";

    $relationship_to_head =
        trim($_POST["relationship_to_head"] ?? "");


    /* -----------------------------------------------------
       INSURANCE
    ----------------------------------------------------- */

    $insurance_type =
        trim($_POST["insurance_type"] ?? "None");

    $insurance_provider =
        trim($_POST["insurance_provider"] ?? "");

    $insurance_number =
        trim($_POST["insurance_number"] ?? "");

    $insurance_expiry =
        trim($_POST["insurance_expiry"] ?? "");


    /* -----------------------------------------------------
       EMERGENCY CONTACT
    ----------------------------------------------------- */

    $emergency_contact =
        trim($_POST["emergency_contact"] ?? "");

    $emergency_phone =
        trim($_POST["emergency_phone"] ?? "");


    /* -----------------------------------------------------
       ACCOUNT
    ----------------------------------------------------- */

    $password =
        $_POST["password"] ?? "";

    $confirm_password =
        $_POST["confirm_password"] ?? "";

    $terms =
        isset($_POST["terms"]);


    /* =====================================================
       VALIDATION
    ===================================================== */

    if (
        empty($first_name) ||
        empty($last_name) ||
        empty($phone) ||
        empty($gender) ||
        empty($date_of_birth) ||
        empty($email) ||
        empty($password) ||
        empty($confirm_password)
    ) {

        $message =
            "Please fill in all required fields.";

        $message_type = "error";

    } elseif (!$terms) {

        $message =
            "Please agree to the terms and conditions.";

        $message_type = "error";

    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $message =
            "Please enter a valid email address.";

        $message_type = "error";

    } elseif (strlen($password) < 8) {

        $message =
            "Password must contain at least 8 characters.";

        $message_type = "error";

    } elseif ($password !== $confirm_password) {

        $message =
            "Passwords do not match.";

        $message_type = "error";

    } elseif (
        !in_array(
            $insurance_type,
            ["None", "Public", "Private"],
            true
        )
    ) {

        $message =
            "Please select a valid insurance type.";

        $message_type = "error";

    } else {

        /* =================================================
           FULL NAME
        ================================================= */

        $full_name =
            $first_name . " " . $last_name;


        /* =================================================
           CHECK EMAIL
        ================================================= */

        $check =
            $conn->prepare(
                "SELECT id
                 FROM users
                 WHERE email = ?
                 LIMIT 1"
            );

        if (!$check) {

            $message =
                "Unable to verify your email.";

            $message_type = "error";

        } else {

            $check->bind_param(
                "s",
                $email
            );

            $check->execute();

            $check_result =
                $check->get_result();


            if ($check_result->num_rows > 0) {

                $message =
                    "An account with this email already exists.";

                $message_type = "error";

            } else {

                /* =========================================
                   CHECK NATIONAL ID IF PROVIDED
                ========================================= */

                $national_exists = false;

                if (!empty($national_id)) {

                    $id_check =
                        $conn->prepare(
                            "SELECT id
                             FROM patients
                             WHERE national_id = ?
                             LIMIT 1"
                        );

                    if ($id_check) {

                        $id_check->bind_param(
                            "s",
                            $national_id
                        );

                        $id_check->execute();

                        $id_result =
                            $id_check->get_result();

                        $national_exists =
                            $id_result->num_rows > 0;

                        $id_check->close();
                    }
                }


                if ($national_exists) {

                    $message =
                        "A patient with this National ID already exists.";

                    $message_type = "error";

                } else {

                    /* =====================================
                       INSURANCE VALIDATION
                    ===================================== */

                    if ($insurance_type === "None") {

                        $insurance_provider = "";
                        $insurance_number = "";
                        $insurance_expiry = "";
                    }


                    /* =====================================
                       FAMILY VALIDATION
                    ===================================== */

                    if ($is_head_of_family === "1") {

                        $relationship_to_head = "Self";

                    } elseif (empty($relationship_to_head)) {

                        $relationship_to_head = null;
                    }


                    /* =====================================
                       PASSWORD
                    ===================================== */

                    $hashed_password =
                        password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        );


                    $role = "patient";
                    $status = "active";


                    /* =====================================
                       PATIENT NUMBER
                    ===================================== */

                    $patient_number =
                        "PAT-" .
                        date("Y") .
                        "-" .
                        strtoupper(
                            substr(
                                uniqid(),
                                -6
                            )
                        );


                    /* =====================================
                       FAMILY NUMBER
                    ===================================== */

                    if (
                        $is_head_of_family === "1"
                    ) {

                        $family_number =
                            "FAM-" .
                            date("Y") .
                            "-" .
                            strtoupper(
                                substr(
                                    uniqid(),
                                    -6
                                )
                            );
                    }


                    /* =====================================
                       TRANSACTION
                    ===================================== */

                    $conn->begin_transaction();


                    try {

                        /* =================================
                           CREATE USER ACCOUNT
                        ================================= */

                        $user_sql = "

                            INSERT INTO users
                            (
                                full_name,
                                email,
                                password,
                                role,
                                status
                            )

                            VALUES
                            (?, ?, ?, ?, ?)

                        ";


                        $user_stmt =
                            $conn->prepare(
                                $user_sql
                            );


                        if (!$user_stmt) {

                            throw new Exception(
                                "Unable to prepare user registration."
                            );
                        }


                        $user_stmt->bind_param(
                            "sssss",
                            $full_name,
                            $email,
                            $hashed_password,
                            $role,
                            $status
                        );


                        if (
                            !$user_stmt->execute()
                        ) {

                            throw new Exception(
                                "Unable to create user account."
                            );
                        }


                        /* =================================
                           CREATE PATIENT
                        ================================= */

                        $patient_sql = "

                            INSERT INTO patients
                            (
                                patient_number,
                                national_id,
                                full_name,
                                date_of_birth,
                                gender,
                                phone,
                                email,
                                address,
                                family_number,
                                is_head_of_family,
                                relationship_to_head,
                                insurance_provider,
                                insurance_type,
                                insurance_number,
                                insurance_expiry,
                                emergency_contact,
                                emergency_phone
                            )

                            VALUES
                            (
                                ?, ?, ?, ?, ?, ?, ?, ?,
                                ?, ?, ?, ?, ?, ?, ?, ?, ?
                            )

                        ";


                        $patient_stmt =
                            $conn->prepare(
                                $patient_sql
                            );


                        if (!$patient_stmt) {

                            throw new Exception(
                                "Unable to prepare patient registration."
                            );
                        }


                        $patient_stmt->bind_param(

                            "sssssssssssssssss",

                            $patient_number,
                            $national_id,
                            $full_name,
                            $date_of_birth,
                            $gender,
                            $phone,
                            $email,
                            $address,
                            $family_number,
                            $is_head_of_family,
                            $relationship_to_head,
                            $insurance_provider,
                            $insurance_type,
                            $insurance_number,
                            $insurance_expiry,
                            $emergency_contact,
                            $emergency_phone

                        );


                        if (
                            !$patient_stmt->execute()
                        ) {

                            throw new Exception(
                                "Unable to save patient information."
                            );
                        }


                        /* =================================
                           SUCCESS
                        ================================= */

                        $conn->commit();


                        $message =
                            "Registration successful! Your patient number is " .
                            $patient_number .
                            ". You can now log in.";

                        $message_type = "success";


                        /* =================================
                           CLEAR FORM
                        ================================= */

                        $first_name = "";
                        $last_name = "";
                        $phone = "";
                        $gender = "";
                        $date_of_birth = "";
                        $email = "";
                        $national_id = "";
                        $address = "";

                        $family_number = "";
                        $is_head_of_family = "0";
                        $relationship_to_head = "";

                        $insurance_type = "None";
                        $insurance_provider = "";
                        $insurance_number = "";
                        $insurance_expiry = "";

                        $emergency_contact = "";
                        $emergency_phone = "";


                        $user_stmt->close();
                        $patient_stmt->close();

                    } catch (Exception $e) {

                        $conn->rollback();

                        $message =
                            "Registration failed: " .
                            $e->getMessage();

                        $message_type = "error";
                    }
                }
            }

            $check->close();
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
    Create Account | SmartHospital
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


body {

    font-family:
        "Segoe UI",
        Arial,
        sans-serif;

    background: #f7fbfc;

    color: #17324d;

    min-height: 100vh;
}


a {
    text-decoration: none;
}


/* =========================================================
   HEADER
========================================================= */

header {

    background: white;

    border-bottom:
        1px solid #edf2f4;
}


.navbar {

    max-width: 1200px;

    height: 76px;

    margin: auto;

    padding: 0 30px;

    display: flex;

    align-items: center;

    justify-content: space-between;
}


.logo {

    display: flex;

    align-items: center;

    gap: 10px;

    color: #087f8c;

    font-size: 21px;

    font-weight: 700;
}


.logo-icon {

    width: 38px;
    height: 38px;

    border-radius: 10px;

    display: flex;

    align-items: center;
    justify-content: center;

    background: #087f8c;

    color: white;

    font-size: 20px;
}


.back-link {

    color: #647789;

    font-size: 14px;

    font-weight: 500;
}


.back-link:hover {

    color: #087f8c;
}


/* =========================================================
   MAIN
========================================================= */

main {

    min-height:
        calc(100vh - 126px);

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 50px 20px;
}


.register-container {

    width: 100%;

    max-width: 1100px;

    display: grid;

    grid-template-columns:
        .8fr 1.2fr;

    background: white;

    border:
        1px solid #edf1f3;

    border-radius: 18px;

    overflow: hidden;

    box-shadow:
        0 18px 45px
        rgba(23,50,77,.08);
}


/* =========================================================
   LEFT INFORMATION
========================================================= */

.register-info {

    background: #087f8c;

    color: white;

    padding: 50px 40px;

    display: flex;

    flex-direction: column;

    justify-content: center;
}


.info-icon {

    width: 52px;
    height: 52px;

    background:
        rgba(255,255,255,.15);

    border-radius: 13px;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 25px;

    margin-bottom: 25px;
}


.register-info h1 {

    font-size: 32px;

    line-height: 1.2;

    margin-bottom: 17px;
}


.register-info > p {

    font-size: 14px;

    line-height: 1.8;

    color:
        rgba(255,255,255,.9);

    margin-bottom: 30px;
}


.benefit {

    display: flex;

    align-items: flex-start;

    gap: 12px;

    margin-bottom: 18px;
}


.benefit-icon {

    width: 27px;
    height: 27px;

    flex-shrink: 0;

    border-radius: 50%;

    background:
        rgba(255,255,255,.16);

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 12px;
}


.benefit-text strong {

    display: block;

    font-size: 13px;

    margin-bottom: 3px;
}


.benefit-text span {

    display: block;

    font-size: 12px;

    line-height: 1.5;

    color:
        rgba(255,255,255,.78);
}


/* =========================================================
   FORM
========================================================= */

.register-form {

    padding: 45px 50px;
}


.form-header {

    margin-bottom: 25px;
}


.form-header h2 {

    font-size: 26px;

    margin-bottom: 7px;
}


.form-header p {

    color: #7a8b98;

    font-size: 13px;
}


/* =========================================================
   SECTION TITLE
========================================================= */

.section-title {

    display: flex;

    align-items: center;

    gap: 9px;

    margin:
        25px 0 16px;

    padding-bottom: 9px;

    border-bottom:
        1px solid #edf1f3;

    color: #17324d;

    font-size: 14px;

    font-weight: 700;
}


.section-number {

    width: 24px;
    height: 24px;

    border-radius: 7px;

    background: #e8f7f8;

    color: #087f8c;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 11px;

    font-weight: 700;
}


/* =========================================================
   FORM ROW
========================================================= */

.form-row {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 17px;
}


.form-group {

    margin-bottom: 17px;
}


.form-group label {

    display: block;

    margin-bottom: 7px;

    color: #334d62;

    font-size: 13px;

    font-weight: 600;
}


.required {

    color: #b42318;
}


.form-group input,
.form-group select {

    width: 100%;

    height: 45px;

    padding: 0 13px;

    border:
        1px solid #dce6ea;

    border-radius: 7px;

    background: white;

    color: #17324d;

    font-family: inherit;

    font-size: 13px;

    outline: none;
}


.form-group input:focus,
.form-group select:focus {

    border-color: #087f8c;

    box-shadow:
        0 0 0 3px
        rgba(8,127,140,.08);
}


.form-help {

    color: #84929c;

    font-size: 10px;

    margin-top: 5px;
}


/* =========================================================
   CONDITIONAL BOX
========================================================= */

.conditional-box {

    background: #f7fbfc;

    border:
        1px solid #e4eef1;

    border-radius: 9px;

    padding: 15px;

    margin-bottom: 18px;
}


/* =========================================================
   TERMS
========================================================= */

.terms {

    display: flex;

    align-items: flex-start;

    gap: 9px;

    margin:
        20px 0;
}


.terms input {

    margin-top: 3px;

    accent-color: #087f8c;
}


.terms label {

    color: #778897;

    font-size: 12px;

    line-height: 1.5;
}


.terms a {

    color: #087f8c;

    font-weight: 600;
}


/* =========================================================
   BUTTON
========================================================= */

.register-btn {

    width: 100%;

    height: 47px;

    border: none;

    border-radius: 7px;

    background: #087f8c;

    color: white;

    font-family: inherit;

    font-size: 14px;

    font-weight: 600;

    cursor: pointer;

    transition: .2s;
}


.register-btn:hover {

    background: #066b76;
}


/* =========================================================
   MESSAGE
========================================================= */

.message {

    margin-bottom: 20px;

    padding: 13px 15px;

    border-radius: 7px;

    font-size: 13px;

    line-height: 1.5;
}


.message.success {

    background: #e9f8f3;

    color: #16745b;

    border:
        1px solid #bce8d8;
}


.message.error {

    background: #fff0f0;

    color: #b42318;

    border:
        1px solid #f3c2c2;
}


.message a {

    color: #087f8c;

    font-weight: 600;
}


/* =========================================================
   LOGIN
========================================================= */

.login-text {

    text-align: center;

    margin-top: 20px;

    color: #7b8b97;

    font-size: 13px;
}


.login-text a {

    color: #087f8c;

    font-weight: 600;
}


/* =========================================================
   FOOTER
========================================================= */

footer {

    background: #102d44;

    color: #dce7ed;

    text-align: center;

    padding: 20px;

    font-size: 12px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 850px) {

    .register-container {

        grid-template-columns: 1fr;

        max-width: 650px;
    }

    .register-info {

        padding: 35px;
    }

    .register-form {

        padding: 35px;
    }
}


@media (max-width: 600px) {

    .navbar {

        padding: 0 18px;
    }

    .logo {

        font-size: 18px;
    }

    .back-link {

        font-size: 12px;
    }

    main {

        padding: 30px 15px;
    }

    .register-info {

        padding: 30px 25px;
    }

    .register-info h1 {

        font-size: 27px;
    }

    .register-form {

        padding: 30px 25px;
    }

    .form-row {

        grid-template-columns: 1fr;

        gap: 0;
    }
}

</style>

</head>


<body>


<header>

<nav class="navbar">

    <a
        href="index.php"
        class="logo"
    >

        <span class="logo-icon">
            +
        </span>

        SmartHospital

    </a>


    <a
        href="index.php"
        class="back-link"
    >

        ← Back to Home

    </a>

</nav>

</header>


<main>


<div class="register-container">


<!-- =====================================================
     LEFT SIDE
===================================================== -->

<section class="register-info">


    <div class="info-icon">
        +
    </div>


    <h1>
        Start your healthcare journey.
    </h1>


    <p>

        Create your SmartHospital account and
        manage your hospital visits more easily.
        Join queues digitally and track your turn
        without unnecessary waiting.

    </p>


    <div class="benefit">

        <div class="benefit-icon">
            ✓
        </div>

        <div class="benefit-text">

            <strong>
                Digital Queue Access
            </strong>

            <span>
                Join available hospital queues
                from your account.
            </span>

        </div>

    </div>


    <div class="benefit">

        <div class="benefit-icon">
            ✓
        </div>

        <div class="benefit-text">

            <strong>
                Secure Patient Profile
            </strong>

            <span>
                Keep your personal and insurance
                information organized.
            </span>

        </div>

    </div>


    <div class="benefit">

        <div class="benefit-icon">
            ✓
        </div>

        <div class="benefit-text">

            <strong>
                Family Support
            </strong>

            <span>
                Connect family members under
                one family number.
            </span>

        </div>

    </div>


</section>


<!-- =====================================================
     RIGHT SIDE
===================================================== -->

<section class="register-form">


<div class="form-header">

    <h2>
        Create Patient Account
    </h2>

    <p>
        Please provide your information to register.
    </p>

</div>


<?php if (!empty($message)): ?>

<div
    class="message
    <?php
        echo $message_type === "success"
            ? "success"
            : "error";
    ?>"
>

    <?php
        echo htmlspecialchars(
            $message,
            ENT_QUOTES,
            "UTF-8"
        );
    ?>


    <?php if ($message_type === "success"): ?>

        <div style="margin-top:8px;">

            <a href="login.php">

                Continue to Login →

            </a>

        </div>

    <?php endif; ?>

</div>

<?php endif; ?>


<form
    action="register.php"
    method="POST"
>


<!-- =====================================================
     PERSONAL INFORMATION
===================================================== -->

<div class="section-title">

    <span class="section-number">
        1
    </span>

    Personal Information

</div>


<div class="form-row">


<div class="form-group">

    <label for="first_name">
        First Name <span class="required">*</span>
    </label>

    <input
        type="text"
        id="first_name"
        name="first_name"
        placeholder="Enter first name"
        value="<?= htmlspecialchars($first_name) ?>"
        required
    >

</div>


<div class="form-group">

    <label for="last_name">
        Last Name <span class="required">*</span>
    </label>

    <input
        type="text"
        id="last_name"
        name="last_name"
        placeholder="Enter last name"
        value="<?= htmlspecialchars($last_name) ?>"
        required
    >

</div>


</div>


<div class="form-row">


<div class="form-group">

    <label for="national_id">
        Patient ID / National ID
    </label>

    <input
        type="text"
        id="national_id"
        name="national_id"
        placeholder="Enter identification number"
        value="<?= htmlspecialchars($national_id) ?>"
    >

    <div class="form-help">
        Official identification number.
    </div>

</div>


<div class="form-group">

    <label for="phone">
        Phone Number <span class="required">*</span>
    </label>

    <input
        type="tel"
        id="phone"
        name="phone"
        placeholder="+250 7XX XXX XXX"
        value="<?= htmlspecialchars($phone) ?>"
        required
    >

</div>


</div>


<div class="form-row">


<div class="form-group">

    <label for="gender">
        Gender <span class="required">*</span>
    </label>

    <select
        id="gender"
        name="gender"
        required
    >

        <option value="">
            Select gender
        </option>

        <option
            value="Male"
            <?= $gender === "Male"
                ? "selected"
                : "" ?>
        >
            Male
        </option>

        <option
            value="Female"
            <?= $gender === "Female"
                ? "selected"
                : "" ?>
        >
            Female
        </option>

        <option
            value="Other"
            <?= $gender === "Other"
                ? "selected"
                : "" ?>
        >
            Other
        </option>

    </select>

</div>


<div class="form-group">

    <label for="date_of_birth">
        Date of Birth <span class="required">*</span>
    </label>

    <input
        type="date"
        id="date_of_birth"
        name="date_of_birth"
        value="<?= htmlspecialchars($date_of_birth) ?>"
        required
    >

</div>


</div>


<div class="form-group">

    <label for="address">
        Address
    </label>

    <input
        type="text"
        id="address"
        name="address"
        placeholder="City, district, sector..."
        value="<?= htmlspecialchars($address) ?>"
    >

</div>


<div class="form-group">

    <label for="email">
        Email Address <span class="required">*</span>
    </label>

    <input
        type="email"
        id="email"
        name="email"
        placeholder="example@email.com"
        value="<?= htmlspecialchars($email) ?>"
        required
    >

</div>


<!-- =====================================================
     FAMILY INFORMATION
===================================================== -->

<div class="section-title">

    <span class="section-number">
        2
    </span>

    Family Information

</div>


<div class="conditional-box">


<div class="form-row">


<div class="form-group">

    <label for="is_head_of_family">

        Are you the Head of Family?

    </label>

    <select
        id="is_head_of_family"
        name="is_head_of_family"
    >

        <option
            value="0"
            <?= $is_head_of_family === "0"
                ? "selected"
                : "" ?>
        >
            No
        </option>

        <option
            value="1"
            <?= $is_head_of_family === "1"
                ? "selected"
                : "" ?>
        >
            Yes
        </option>

    </select>

</div>


<div
    class="form-group"
    id="relationship_group"
>

    <label for="relationship_to_head">

        Relationship to Head

    </label>

    <select
        id="relationship_to_head"
        name="relationship_to_head"
    >

        <option value="">
            Select relationship
        </option>

        <option
            value="Spouse"
            <?= $relationship_to_head === "Spouse"
                ? "selected"
                : "" ?>
        >
            Spouse
        </option>

        <option
            value="Child"
            <?= $relationship_to_head === "Child"
                ? "selected"
                : "" ?>
        >
            Child
        </option>

        <option
            value="Parent"
            <?= $relationship_to_head === "Parent"
                ? "selected"
                : "" ?>
        >
            Parent
        </option>

        <option
            value="Sibling"
            <?= $relationship_to_head === "Sibling"
                ? "selected"
                : "" ?>
        >
            Sibling
        </option>

        <option
            value="Other"
            <?= $relationship_to_head === "Other"
                ? "selected"
                : "" ?>
        >
            Other
        </option>

    </select>

</div>


</div>


<div
    class="form-group"
    id="family_number_group"
>

    <label for="family_number">

        Family Number

    </label>

    <input
        type="text"
        id="family_number"
        name="family_number"
        placeholder="Enter family number if available"
        value="<?= htmlspecialchars($family_number) ?>"
    >

    <div class="form-help">

        Leave blank if you are registering as a new
        head of family. The system will generate one.

    </div>

</div>


</div>


<!-- =====================================================
     INSURANCE
===================================================== -->

<div class="section-title">

    <span class="section-number">
        3
    </span>

    Insurance Information

</div>


<div class="conditional-box">


<div class="form-row">


<div class="form-group">

    <label for="insurance_type">

        Insurance Type

    </label>

    <select
        id="insurance_type"
        name="insurance_type"
    >

        <option
            value="None"
            <?= $insurance_type === "None"
                ? "selected"
                : "" ?>
        >
            No Insurance
        </option>

        <option
            value="Public"
            <?= $insurance_type === "Public"
                ? "selected"
                : "" ?>
        >
            Public Insurance
        </option>

        <option
            value="Private"
            <?= $insurance_type === "Private"
                ? "selected"
                : "" ?>
        >
            Private Insurance
        </option>

    </select>

</div>


<div
    class="form-group"
    id="insurance_provider_group"
>

    <label for="insurance_provider">

        Insurance Provider

    </label>

    <input
        type="text"
        id="insurance_provider"
        name="insurance_provider"
        placeholder="e.g. RSSB, MMI..."
        value="<?= htmlspecialchars($insurance_provider) ?>"
    >

</div>


</div>


<div
    class="form-row"
    id="insurance_details"
>


<div class="form-group">

    <label for="insurance_number">

        Insurance Number

    </label>

    <input
        type="text"
        id="insurance_number"
        name="insurance_number"
        placeholder="Insurance membership number"
        value="<?= htmlspecialchars($insurance_number) ?>"
    >

</div>


<div class="form-group">

    <label for="insurance_expiry">

        Insurance Expiry

    </label>

    <input
        type="date"
        id="insurance_expiry"
        name="insurance_expiry"
        value="<?= htmlspecialchars($insurance_expiry) ?>"
    >

</div>


</div>


</div>


<!-- =====================================================
     EMERGENCY CONTACT
===================================================== -->

<div class="section-title">

    <span class="section-number">
        4
    </span>

    Emergency Contact

</div>


<div class="form-row">


<div class="form-group">

    <label for="emergency_contact">

        Emergency Contact Name

    </label>

    <input
        type="text"
        id="emergency_contact"
        name="emergency_contact"
        placeholder="Full name"
        value="<?= htmlspecialchars($emergency_contact) ?>"
    >

</div>


<div class="form-group">

    <label for="emergency_phone">

        Emergency Phone

    </label>

    <input
        type="tel"
        id="emergency_phone"
        name="emergency_phone"
        placeholder="+250 7XX XXX XXX"
        value="<?= htmlspecialchars($emergency_phone) ?>"
    >

</div>


</div>


<!-- =====================================================
     ACCOUNT SECURITY
===================================================== -->

<div class="section-title">

    <span class="section-number">
        5
    </span>

    Account Security

</div>


<div class="form-row">


<div class="form-group">

    <label for="password">

        Password <span class="required">*</span>

    </label>

    <input
        type="password"
        id="password"
        name="password"
        placeholder="Create a password"
        minlength="8"
        required
    >

    <div class="form-help">

        Minimum 8 characters.

    </div>

</div>


<div class="form-group">

    <label for="confirm_password">

        Confirm Password <span class="required">*</span>

    </label>

    <input
        type="password"
        id="confirm_password"
        name="confirm_password"
        placeholder="Confirm password"
        minlength="8"
        required
    >

</div>


</div>


<!-- =====================================================
     TERMS
===================================================== -->

<div class="terms">

    <input
        type="checkbox"
        id="terms"
        name="terms"
        required
    >

    <label for="terms">

        I agree to the SmartHospital

        <a href="#">
            terms and conditions
        </a>

        and understand that my information
        will be used to provide hospital services.

    </label>

</div>


<button
    type="submit"
    class="register-btn"
>

    Create Patient Account

</button>


</form>


<div class="login-text">

    Already have an account?

    <a href="login.php">
        Login here
    </a>

</div>


</section>


</div>

</main>


<footer>

© 2026 SmartHospital Queue Management System

</footer>


<script>

/* =========================================================
   FAMILY FIELDS
========================================================= */

const familyHead =
    document.getElementById(
        "is_head_of_family"
    );

const relationshipGroup =
    document.getElementById(
        "relationship_group"
    );

const familyNumber =
    document.getElementById(
        "family_number_group"
    );


function updateFamilyFields() {

    if (familyHead.value === "1") {

        relationshipGroup.style.display =
            "none";

        familyNumber.style.display =
            "block";

    } else {

        relationshipGroup.style.display =
            "block";

        familyNumber.style.display =
            "block";
    }
}


familyHead.addEventListener(
    "change",
    updateFamilyFields
);


updateFamilyFields();


/* =========================================================
   INSURANCE FIELDS
========================================================= */

const insuranceType =
    document.getElementById(
        "insurance_type"
    );

const insuranceProvider =
    document.getElementById(
        "insurance_provider_group"
    );

const insuranceDetails =
    document.getElementById(
        "insurance_details"
    );


function updateInsuranceFields() {

    if (insuranceType.value === "None") {

        insuranceProvider.style.display =
            "none";

        insuranceDetails.style.display =
            "none";

    } else {

        insuranceProvider.style.display =
            "block";

        insuranceDetails.style.display =
            "grid";
    }
}


insuranceType.addEventListener(
    "change",
    updateInsuranceFields
);


updateInsuranceFields();

</script>


</body>

</html>