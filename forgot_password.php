```php
<?php

session_start();

require_once "db.php";

$message = "";
$error = "";

$email = "";


/* =========================================================
   RESET PASSWORD
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";


    /* =====================================================
       VALIDATE EMAIL
    ===================================================== */

    if (empty($email)) {

        $error = "Please enter your email address.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    }


    /* =====================================================
       VALIDATE PASSWORD
    ===================================================== */

    elseif (empty($new_password)) {

        $error = "Please enter a new password.";

    } elseif (strlen($new_password) < 6) {

        $error = "Password must be at least 6 characters.";

    } elseif ($new_password !== $confirm_password) {

        $error = "Passwords do not match.";

    }


    /* =====================================================
       FIND USER
    ===================================================== */

    else {

        $stmt = $conn->prepare(
            "SELECT id, full_name, email
             FROM users
             WHERE email = ?
             LIMIT 1"
        );


        if (!$stmt) {

            $error =
                "System error. Please try again.";

        } else {

            $stmt->bind_param(
                "s",
                $email
            );

            $stmt->execute();

            $result =
                $stmt->get_result();


            /* =================================================
               USER FOUND
            ================================================= */

            if ($result->num_rows === 1) {

                $user =
                    $result->fetch_assoc();

                $user_id =
                    (int) $user["id"];


                /* =============================================
                   HASH NEW PASSWORD
                ============================================= */

                $hashed_password =
                    password_hash(
                        $new_password,
                        PASSWORD_DEFAULT
                    );


                /* =============================================
                   UPDATE PASSWORD
                ============================================= */

                $update_stmt = $conn->prepare(
                    "UPDATE users
                     SET password = ?
                     WHERE id = ?
                     LIMIT 1"
                );


                if (!$update_stmt) {

                    $error =
                        "Unable to update password.";

                } else {

                    $update_stmt->bind_param(
                        "si",
                        $hashed_password,
                        $user_id
                    );


                    if ($update_stmt->execute()) {

                        $message =
                            "Your password has been reset successfully. You can now login with your new password.";

                        $email = "";

                    } else {

                        $error =
                            "Unable to reset your password. Please try again.";

                    }


                    $update_stmt->close();
                }


            } else {

                $error =
                    "No account was found with that email address.";

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
    Forgot Password | SmartHospital
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
   CARD
========================================================= */

.password-card {

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
   ICON
========================================================= */

.password-icon {

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
   HEADER
========================================================= */

.password-header {

    text-align: center;

    margin-bottom: 30px;
}


.password-header h1 {

    font-size: 27px;

    margin-bottom: 8px;
}


.password-header p {

    color: #7a8b98;

    font-size: 13px;

    line-height: 1.6;
}


/* =========================================================
   ERROR
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
   SUCCESS
========================================================= */

.success-message {

    background: #eaf8f0;

    color: #16834d;

    border:
        1px solid
        #bde5cd;

    border-radius: 7px;

    padding: 14px;

    margin-bottom: 20px;

    font-size: 13px;

    line-height: 1.6;

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
   BUTTON
========================================================= */

.reset-btn {

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


.reset-btn:hover {

    background: #066b76;

    transform:
        translateY(-1px);
}


/* =========================================================
   LOGIN LINK
========================================================= */

.login-link {

    text-align: center;

    margin-top: 24px;

    padding-top: 22px;

    border-top:
        1px solid
        #edf1f3;

    color: #7b8b97;

    font-size: 13px;
}


.login-link a {

    color: #087f8c;

    font-weight: 600;
}


.login-link a:hover {

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


    .password-card {

        padding:
            32px 25px;
    }


    .password-header h1 {

        font-size: 24px;
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
        href="login.php"
        class="back-link"
    >

        ← Back to Login

    </a>


</nav>

</header>



<!-- =========================================================
     MAIN
========================================================= -->

<main>


<div class="password-card">


    <!-- ICON -->

    <div class="password-icon">

        🔐

    </div>



    <!-- HEADER -->

    <div class="password-header">

        <h1>
            Reset Password
        </h1>

        <p>

            Enter your registered email
            and create a new password
            for your SmartHospital account.

        </p>

    </div>



    <!-- ERROR -->

    <?php if (!empty($error)): ?>

        <div class="error-message">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>



    <!-- SUCCESS -->

    <?php if (!empty($message)): ?>

        <div class="success-message">

            <?= htmlspecialchars($message) ?>

        </div>

    <?php endif; ?>



    <!-- FORM -->

    <?php if (empty($message)): ?>

    <form
        action="forgot_password.php"
        method="POST"
        autocomplete="off"
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
                placeholder="Enter your registered email"
                value="<?= htmlspecialchars($email) ?>"
                autocomplete="email"
                required
            >

        </div>



        <!-- NEW PASSWORD -->

        <div class="form-group">

            <label for="new_password">

                New Password

            </label>


            <input
                type="password"
                id="new_password"
                name="new_password"
                placeholder="Enter your new password"
                minlength="6"
                autocomplete="new-password"
                required
            >

        </div>



        <!-- CONFIRM PASSWORD -->

        <div class="form-group">

            <label for="confirm_password">

                Confirm New Password

            </label>


            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                placeholder="Confirm your new password"
                minlength="6"
                autocomplete="new-password"
                required
            >

        </div>



        <!-- BUTTON -->

        <button
            type="submit"
            class="reset-btn"
        >

            Reset Password

        </button>


    </form>

    <?php endif; ?>



    <!-- LOGIN -->

    <div class="login-link">

        <?php if (!empty($message)): ?>

            <a href="login.php">

                ← Return to Login

            </a>

        <?php else: ?>

            Remember your password?

            <a href="login.php">

                Login here

            </a>

        <?php endif; ?>

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
```
