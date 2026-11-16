
<?php

session_start();

require_once "db.php";

$error = "";


/* =========================================================
   LOGIN PROCESS
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if (empty($email) || empty($password)) {

        $error = "Please enter your email and password.";

    } else {

        $stmt = $conn->prepare(
            "SELECT
                id,
                full_name,
                email,
                password,
                role,
                status
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        if (!$stmt) {

            $error = "System error. Please try again.";

        } else {

            $stmt->bind_param("s", $email);

            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows === 1) {

                $user = $result->fetch_assoc();


                /* =====================================================
                   ACCOUNT STATUS
                ===================================================== */

                if ($user["status"] !== "active") {

                    $error = "Your account is currently inactive.";

                }


                /* =====================================================
                   PASSWORD CHECK
                ===================================================== */

                elseif (password_verify(
                    $password,
                    $user["password"]
                )) {


                    /* =================================================
                       CREATE SESSION
                    ================================================= */

                    session_regenerate_id(true);

                    $_SESSION["user_id"] =
                        (int) $user["id"];

                    $_SESSION["full_name"] =
                        $user["full_name"];

                    $_SESSION["email"] =
                        $user["email"];

                    $_SESSION["role"] =
                        $user["role"];


                    /* =================================================
                       ROLE REDIRECTION
                    ================================================= */

                    switch ($user["role"]) {

                        case "admin":

                            header(
                                "Location: admin_dashboard.php"
                            );

                            exit;


                        case "receptionist":

                            header(
                                "Location: receptionist_dashboard.php"
                            );

                            exit;


                        case "nurse":

                            header(
                                "Location: nurse_dashboard.php"
                            );

                            exit;


                        case "doctor":

                            header(
                                "Location: doctor_dashboard.php"
                            );

                            exit;


                        case "patient":

                            header(
                                "Location: patient_dashboard.php"
                            );

                            exit;


                        default:

                            session_unset();
                            session_destroy();

                            $error =
                                "Invalid account role.";

                            break;
                    }


                } else {

                    $error =
                        "Incorrect email or password.";

                }

            } else {

                $error =
                    "Incorrect email or password.";

            }

            $stmt->close();
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
    Login | SmartHospital
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
        1px solid
        #edf2f4;
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


/* =========================================================
   LOGO
========================================================= */

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

    background: #087f8c;

    color: white;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 20px;
}


/* =========================================================
   BACK LINK
========================================================= */

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


/* =========================================================
   LOGIN CARD
========================================================= */

.login-card {

    width: 100%;

    max-width: 440px;

    background: white;

    padding: 45px;

    border-radius: 18px;

    border:
        1px solid
        #edf1f3;

    box-shadow:
        0 18px 45px
        rgba(23, 50, 77, 0.08);
}


/* =========================================================
   LOGIN ICON
========================================================= */

.login-icon {

    width: 55px;
    height: 55px;

    margin:
        0 auto 20px;

    border-radius: 14px;

    background: #e8f7f8;

    color: #087f8c;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 23px;
}


/* =========================================================
   LOGIN HEADER
========================================================= */

.login-header {

    text-align: center;

    margin-bottom: 30px;
}


.login-header h1 {

    font-size: 27px;

    margin-bottom: 8px;
}


.login-header p {

    color: #7a8b98;

    font-size: 13px;

    line-height: 1.6;
}


/* =========================================================
   ERROR MESSAGE
========================================================= */

.error-message {

    background: #fff1f1;

    color: #b42318;

    border:
        1px solid
        #f3c7c7;

    border-radius: 7px;

    padding: 11px 13px;

    margin-bottom: 20px;

    font-size: 13px;

    text-align: center;
}


/* =========================================================
   FORM
========================================================= */

.form-group {

    margin-bottom: 20px;
}


.form-group label {

    display: block;

    margin-bottom: 7px;

    color: #334d62;

    font-size: 13px;

    font-weight: 600;
}


.form-group input {

    width: 100%;

    height: 47px;

    padding: 0 13px;

    border:
        1px solid
        #dce6ea;

    border-radius: 7px;

    outline: none;

    color: #17324d;

    font-family: inherit;

    font-size: 13px;
}


.form-group input:focus {

    border-color: #087f8c;

    box-shadow:
        0 0 0 3px
        rgba(8, 127, 140, 0.08);
}


/* =========================================================
   OPTIONS
========================================================= */

.form-options {

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom: 22px;
}


.remember {

    display: flex;

    align-items: center;

    gap: 7px;

    color: #7b8b97;

    font-size: 12px;
}


.remember input {

    accent-color: #087f8c;
}


.forgot {

    color: #087f8c;

    font-size: 12px;

    font-weight: 600;
}


.forgot:hover {

    color: #066b76;

    text-decoration: underline;
}


/* =========================================================
   LOGIN BUTTON
========================================================= */

.login-btn {

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

    transition: 0.2s ease;
}


.login-btn:hover {

    background: #066b76;

    transform:
        translateY(-1px);
}


/* =========================================================
   REGISTER
========================================================= */

.register-text {

    text-align: center;

    margin-top: 24px;

    padding-top: 22px;

    border-top:
        1px solid
        #edf1f3;

    color: #7b8b97;

    font-size: 13px;
}


.register-text a {

    color: #087f8c;

    font-weight: 600;
}


.register-text a:hover {

    text-decoration: underline;
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
   MOBILE
========================================================= */

@media (max-width: 600px) {

    .navbar {

        height: 68px;

        padding: 0 18px;
    }


    .logo {

        font-size: 18px;
    }


    .logo-icon {

        width: 34px;

        height: 34px;
    }


    .back-link {

        font-size: 12px;
    }


    main {

        padding:
            30px 15px;
    }


    .login-card {

        padding:
            32px 25px;
    }


    .login-header h1 {

        font-size: 24px;
    }


    .form-options {

        gap: 10px;
    }

}

</style>

</head>


<body>


<!-- =========================================================
     HEADER
========================================================= -->

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



<!-- =========================================================
     MAIN
========================================================= -->

<main>


<div class="login-card">


    <!-- LOGIN ICON -->

    <div class="login-icon">
        +
    </div>


    <!-- LOGIN HEADER -->

    <div class="login-header">

        <h1>
            Welcome Back
        </h1>

        <p>

            Login to access your
            SmartHospital account
            and manage your hospital queue.

        </p>

    </div>



    <!-- ERROR -->

    <?php if (!empty($error)): ?>

        <div class="error-message">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>



    <!-- LOGIN FORM -->

    <form
        action="login.php"
        method="POST"
        autocomplete="on"
    >


        <!-- EMAIL -->

        <div class="form-group">

            <label for="email">

                Email Address

            </label>


            <input
                type="email"
                id="email"
                name="email"
                placeholder="Enter your email"
                autocomplete="email"
                value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
                required
            >

        </div>



        <!-- PASSWORD -->

        <div class="form-group">

            <label for="password">

                Password

            </label>


            <input
                type="password"
                id="password"
                name="password"
                placeholder="Enter your password"
                autocomplete="current-password"
                required
            >

        </div>



        <!-- OPTIONS -->

        <div class="form-options">


            <label class="remember">

                <input
                    type="checkbox"
                    name="remember"
                >

                Remember me

            </label>



            <!-- FIXED FORGOT PASSWORD LINK -->

            <a
                href="forgot_password.php"
                class="forgot"
            >

                Forgot Password?

            </a>


        </div>



        <!-- LOGIN -->

        <button
            type="submit"
            class="login-btn"
        >

            Login

        </button>


    </form>



    <!-- REGISTER -->

    <div class="register-text">

        Don't have an account?

        <a href="register.php">

            Create an account

        </a>

    </div>


</div>


</main>



<!-- =========================================================
     FOOTER
========================================================= -->

<footer>

    © 2026 SmartHospital Queue Management System

</footer>


</body>

</html>

