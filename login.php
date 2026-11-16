<?php
session_start();


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sub'])) {

    $con = new mysqli("localhost", "root", "", "calender");

    if ($con->connect_error) {
        die("Database connection failed: " . $con->connect_error);
    }

    $username = trim($_POST['use'] ?? '');
    $password = $_POST['pass'] ?? '';

    $stmt = $con->prepare(
        "SELECT * FROM user_info WHERE email = ? AND password = ? LIMIT 1"
    );

    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {

        /*
         * Create a fresh authenticated session.
         */
        session_regenerate_id(true);

        /*
         * Keep the same session variable expected by todo.php.
         */
        $_SESSION['ID'] = (int)$row['id'];

        $stmt->close();
        $con->close();

        header("Location: dashboard.php");
        exit();
    }

    $stmt->close();
    $con->close();

    $login_error = "Invalid email or password.";
}
?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Login | MyCalendar</title>

<!-- Font Awesome -->
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">


<style>

    /* =========================
       RESET
    ========================= */

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }


    body {
        font-family: Arial, Helvetica, sans-serif;

        background: #f3ecdf;

        color: #343a24;
    }


    /* =========================
       LOGIN PAGE
    ========================= */

    .login-page {

        min-height: 100vh;

        display: flex;

    }


    /* =========================
       LEFT SIDE
    ========================= */

    .login-left {

        width: 52%;

        min-height: 100vh;

        background: #59633d;

        color: white;

        padding: 45px 65px;

        display: flex;

        flex-direction: column;

        position: relative;

        overflow: hidden;

    }


    /* Decorative Circle */

    .login-left::before {

        content: "";

        position: absolute;

        width: 350px;

        height: 350px;

        border-radius: 50%;

        background: rgba(255, 255, 255, 0.05);

        top: -120px;

        right: -100px;

    }


    .login-left::after {

        content: "";

        position: absolute;

        width: 250px;

        height: 250px;

        border-radius: 50%;

        background: rgba(255, 255, 255, 0.04);

        bottom: -100px;

        left: -80px;

    }


    /* =========================
       BRAND
    ========================= */

    .brand {

        display: flex;

        align-items: center;

        gap: 12px;

        font-size: 22px;

        font-weight: bold;

        position: relative;

        z-index: 2;

    }


    .brand-icon {

        width: 42px;

        height: 42px;

        border-radius: 11px;

        background: #f3ecdf;

        color: #59633d;

        display: flex;

        justify-content: center;

        align-items: center;

        font-size: 21px;

    }


    /* =========================
       WELCOME CONTENT
    ========================= */

    .welcome-content {

        margin: auto 0;

        max-width: 550px;

        position: relative;

        z-index: 2;

    }


    .welcome-content h1 {

        font-size: 48px;

        line-height: 1.15;

        margin-bottom: 20px;

    }


    .welcome-content h1 span {

        color: #d8ddc3;

    }


    .welcome-content > p {

        font-size: 17px;

        line-height: 1.7;

        color: #e1e5d4;

        max-width: 470px;

        margin-bottom: 35px;

    }


    /* =========================
       FEATURES
    ========================= */

    .feature-list {

        display: flex;

        flex-direction: column;

        gap: 18px;

    }


    .feature {

        display: flex;

        align-items: center;

        gap: 15px;

    }


    .feature-icon {

        width: 44px;

        height: 44px;

        border-radius: 10px;

        background: rgba(255, 255, 255, 0.12);

        display: flex;

        justify-content: center;

        align-items: center;

        color: #f3ecdf;

    }


    .feature strong {

        display: block;

        margin-bottom: 4px;

    }


    .feature p {

        color: #d5dac6;

        font-size: 13px;

    }


    /* =========================
       COPYRIGHT
    ========================= */

    .copyright {

        font-size: 12px;

        color: #cbd0bb;

        position: relative;

        z-index: 2;

    }


    /* =========================
       RIGHT SIDE
    ========================= */

    .login-right {

        width: 48%;

        min-height: 100vh;

        display: flex;

        align-items: center;

        justify-content: center;

        padding: 40px;

    }


    /* =========================
       LOGIN CARD
    ========================= */

    .login-card {

        width: 100%;

        max-width: 440px;

    }


    /* =========================
       MOBILE LOGO
    ========================= */

    .mobile-logo {

        display: none;

        align-items: center;

        justify-content: center;

        gap: 10px;

        color: #59633d;

        font-size: 20px;

        font-weight: bold;

        margin-bottom: 30px;

    }


    /* =========================
       HEADING
    ========================= */

    .login-heading {

        margin-bottom: 30px;

    }


    .login-heading h2 {

        font-size: 30px;

        margin-bottom: 8px;

        color: #343a24;

    }


    .login-heading p {

        color: #858678;

        font-size: 14px;

    }


    /* =========================
       FORM
    ========================= */

    .form-group {

        margin-bottom: 21px;

    }


    .form-group label {

        display: block;

        font-size: 14px;

        font-weight: bold;

        margin-bottom: 8px;

    }


    /* =========================
       PASSWORD LABEL
    ========================= */

    .password-label {

        display: flex;

        justify-content: space-between;

        align-items: center;

        margin-bottom: 8px;

    }


    .password-label label {

        margin-bottom: 0;

    }


    .password-label a {

        color: #68734a;

        text-decoration: none;

        font-size: 12px;

        font-weight: bold;

    }


    .password-label a:hover {

        text-decoration: underline;

    }


    /* =========================
       INPUT BOX
    ========================= */

    .input-box {

        width: 100%;

        height: 50px;

        background: #fffaf2;

        border: 1px solid #d8cfbc;

        border-radius: 9px;

        display: flex;

        align-items: center;

        padding: 0 14px;

        transition: 0.2s;

    }


    .input-box:focus-within {

        border-color: #68734a;

        box-shadow:
            0 0 0 3px rgba(104, 115, 74, 0.1);

    }


    .input-box > i {

        color: #858678;

        margin-right: 11px;

    }


    .input-box input {

        width: 100%;

        height: 100%;

        border: none;

        outline: none;

        background: transparent;

        color: #343a24;

        font-size: 14px;

    }


    .input-box input::placeholder {

        color: #aaa89e;

    }


    /* =========================
       SHOW PASSWORD
    ========================= */

    .show-password {

        border: none;

        background: transparent;

        color: #858678;

        cursor: pointer;

        font-size: 15px;

    }


    .show-password:hover {

        color: #59633d;

    }


    /* =========================
       REMEMBER ME
    ========================= */

    .remember-row {

        margin-bottom: 20px;

    }


    .remember {

        display: flex;

        align-items: center;

        gap: 8px;

        color: #6e7063;

        font-size: 13px;

        cursor: pointer;

    }


    .remember input {

        width: 15px;

        height: 15px;

        accent-color: #59633d;

    }


    /* =========================
       LOGIN BUTTON
    ========================= */

    .login-button {

        width: 100%;

        height: 50px;

        border: none;

        border-radius: 9px;

        background: #59633d;

        color: white;

        font-size: 15px;

        font-weight: bold;

        display: flex;

        justify-content: center;

        align-items: center;

        gap: 10px;

        cursor: pointer;

        transition: 0.2s;

    }


    .login-button:hover {

        background: #414a2c;

        transform: translateY(-1px);

    }


    /* =========================
       DIVIDER
    ========================= */

    .divider {

        display: flex;

        align-items: center;

        gap: 12px;

        margin: 27px 0;

        color: #aaa79b;

        font-size: 11px;

    }


    .divider::before,
    .divider::after {

        content: "";

        flex: 1;

        height: 1px;

        background: #ddd5c7;

    }


    /* =========================
       SIGN UP
    ========================= */

    .signup {

        text-align: center;

        font-size: 13px;

        color: #77786c;

    }


    .signup a {

        color: #59633d;

        font-weight: bold;

        text-decoration: none;

        margin-left: 4px;

    }


    .signup a:hover {

        text-decoration: underline;

    }


    /* =========================
       RESPONSIVE
    ========================= */

    @media (max-width: 900px) {

        .login-left {

            width: 45%;

            padding: 35px;

        }


        .login-right {

            width: 55%;

        }


        .welcome-content h1 {

            font-size: 38px;

        }

    }


    @media (max-width: 700px) {

        .login-page {

            display: block;

        }


        .login-left {

            display: none;

        }


        .login-right {

            width: 100%;

            min-height: 100vh;

            padding: 25px;

        }


        .mobile-logo {

            display: flex;

        }


        .login-heading h2 {

            font-size: 27px;

        }

    }

</style>

</head>

<body>


<div class="login-page">


    <!-- =================================
         LEFT SIDE
    ================================== -->

    <div class="login-left">


        <!-- Logo -->

        <div class="brand">

            <div class="brand-icon">

                <i class="fa-regular fa-calendar"></i>

            </div>

            <span>MyCalendar</span>

        </div>


        <!-- Welcome Content -->

        <div class="welcome-content">


            <h1>

                Organize your

                <span>day better.</span>

            </h1>


            <p>

                Manage your schedule, tasks, events
                and expenses — all in one simple place.

            </p>


            <!-- Feature 1 -->

            <div class="feature-list">


                <div class="feature">

                    <div class="feature-icon">

                        <i class="fa-regular fa-calendar"></i>

                    </div>


                    <div>

                        <strong>
                            Smart Calendar
                        </strong>

                        <p>
                            Plan your days with ease.
                        </p>

                    </div>

                </div>


                <!-- Feature 2 -->

                <div class="feature">

                    <div class="feature-icon">

                        <i class="fa-solid fa-list-check"></i>

                    </div>


                    <div>

                        <strong>
                            To-Do Lists
                        </strong>

                        <p>
                            Keep track of your important tasks.
                        </p>

                    </div>

                </div>


                <!-- Feature 3 -->

                <div class="feature">

                    <div class="feature-icon">

                        <i class="fa-solid fa-wallet"></i>

                    </div>


                    <div>

                        <strong>
                            Expense Tracking
                        </strong>

                        <p>
                            Know where your money goes.
                        </p>

                    </div>

                </div>


            </div>

        </div>


        <!-- Copyright -->

        <div class="copyright">

            © 2026 MyCalendar. All rights reserved.

        </div>


    </div>


    <!-- =================================
         RIGHT SIDE
    ================================== -->

    <div class="login-right">


        <div class="login-card">


            <!-- Mobile Logo -->

            <div class="mobile-logo">

                <div class="brand-icon">

                    <i class="fa-regular fa-calendar"></i>

                </div>

                <span>MyCalendar</span>

            </div>


            <!-- Heading -->

            <div class="login-heading">

                <h2>
                    Welcome back!
                </h2>

                <p>
                    Please enter your details to continue.
                </p>

            </div>


            <?php if (!empty($login_error)) : ?>
                <div style="
                    margin-bottom:16px;
                    padding:12px 14px;
                    border-radius:8px;
                    background:#f8e7e6;
                    color:#b94a48;
                    font-size:14px;
                    font-weight:600;
                ">
                    <?php echo htmlspecialchars($login_error); ?>
                </div>
            <?php endif; ?>

            <!-- Login Form -->

            <form id="loginForm" method="post">


                <!-- Email -->

                <div class="form-group">

                    <label for="email">

                        Email Address

                    </label>


                    <div class="input-box">

                        <i class="fa-regular fa-envelope"></i>


                        <input
                            type="email"
                            id="email"
                            name="use"
                            placeholder="Enter your email"
                            required>

                    </div>

                </div>


                <!-- Password -->

                <div class="form-group">


                    <div class="password-label">

                        <label for="password">

                            Password

                        </label>


                        <a href="#">

                            Forgot password?

                        </a>

                    </div>


                    <div class="input-box">

                        <i class="fa-solid fa-lock"></i>


                        <input
                            type="password"
                            id="password"
                            name="pass"
                            placeholder="Enter your password"
                            required>


                        <!-- <button
                            type="button"
                            class="show-password"
                            onclick="togglePassword()">

                            <i
                                id="passwordIcon"
                                class="fa-regular fa-eye">
                            </i>

                        </button> -->

                    </div>

                </div>


                <!-- Remember -->

                <div class="remember-row">

                    <label class="remember">

                        <input
                            type="checkbox"
                            id="remember">

                        <span>
                            Remember me
                        </span>

                    </label>

                </div>


                <!-- Login Button -->

                <button
                    type="submit"
                    class="login-button" name="sub">

                    Login

                    <!-- <i class="fa-solid fa-arrow-right"></i> -->

                </button>


                <!-- Divider -->

                <div class="divider">

                    <span>OR</span>

                </div>


                <!-- Create Account -->

                <div class="signup">

                    Don't have an account?

                    <a href="register.html">

                        Create an account

                    </a>

                </div>


            </form>


        </div>

    </div>

</div>


<script src="login.js"></script>





</body>

</html>
