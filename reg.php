<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create Account | MyCalendar</title>

    <!-- Font Awesome -->
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background-color: #f3ecdf;
        }

        /* ================= MAIN PAGE ================= */

        .register-page {
            min-height: 100vh;
            display: flex;
        }


        /* ================= LEFT SIDE ================= */

        .register-left {
            width: 50%;
            background-color: #59633d;
            color: white;
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }


        /* Logo */

        .brand {
            position: absolute;
            top: 35px;
            left: 50px;

            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-icon {
            width: 45px;
            height: 45px;

            background-color: #f3ecdf;
            color: #59633d;

            border-radius: 12px;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 22px;
        }

        .brand h2 {
            font-size: 25px;
        }


        /* Left Content */

        .left-content {
            max-width: 500px;
        }

        .left-content h1 {
            font-size: 45px;
            line-height: 1.2;
            margin-bottom: 20px;
        }

        .left-content p {
            font-size: 17px;
            line-height: 1.7;
            color: #e8e4d8;
            margin-bottom: 35px;
        }


        /* Features */

        .features {
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
            width: 42px;
            height: 42px;

            background-color: rgba(255, 255, 255, 0.15);

            border-radius: 10px;

            display: flex;
            align-items: center;
            justify-content: center;
        }

        .feature span {
            font-size: 16px;
        }


        /* ================= RIGHT SIDE ================= */

        .register-right {
            width: 50%;
            background-color: #f3ecdf;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 40px;
        }


        /* Registration Card */

        .register-card {
            width: 100%;
            max-width: 460px;

            background-color: #fffdf8;

            padding: 40px;

            border-radius: 20px;

            box-shadow: 0 10px 35px rgba(60, 65, 40, 0.12);
        }


        /* Mobile Logo */

        .mobile-logo {
            display: none;
            text-align: center;
            margin-bottom: 25px;
        }

        .mobile-logo i {
            font-size: 35px;
            color: #59633d;
        }


        /* Heading */

        .register-card h2 {
            color: #34382a;
            font-size: 30px;
            margin-bottom: 8px;
        }

        .subtitle {
            color: #77776e;
            margin-bottom: 30px;
        }


        /* ================= FORM ================= */

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;

            margin-bottom: 8px;

            color: #3f432f;

            font-weight: 600;

            font-size: 14px;
        }


        /* Input Box */

        .input-box {
            position: relative;
        }

        .input-icon {
            position: absolute;

            left: 15px;
            top: 50%;

            transform: translateY(-50%);

            color: #7b8261;
        }


        /* Inputs */

        .input-box input {
            width: 100%;
            height: 50px;

            padding: 0 15px 0 45px;

            border: 1px solid #d7d3c7;

            border-radius: 10px;

            background-color: white;

            outline: none;

            font-size: 15px;

            transition: 0.3s;
        }

        .input-box input:focus {
            border-color: #59633d;

            box-shadow:
                0 0 0 3px rgba(89, 99, 61, 0.10);
        }


        /* Date Input */

        input[type="date"] {
            color: #555;
        }


        /* ================= TERMS ================= */

        .terms {
            display: flex;
            align-items: flex-start;

            gap: 9px;

            margin: 8px 0 22px;

            font-size: 13px;

            color: #77776e;

            line-height: 1.5;
        }

        .terms input {
            margin-top: 3px;

            accent-color: #59633d;
        }

        .terms label {
            cursor: pointer;
        }

        .terms a {
            color: #59633d;

            text-decoration: none;

            font-weight: 600;
        }

        .terms a:hover {
            text-decoration: underline;
        }


        /* ================= BUTTON ================= */

        .register-btn {
            width: 100%;
            height: 50px;

            border: none;

            border-radius: 10px;

            background-color: #59633d;

            color: white;

            font-size: 16px;

            font-weight: 600;

            cursor: pointer;

            transition: 0.3s;
        }

        .register-btn:hover {
            background-color: #46502f;
        }


        /* ================= LOGIN LINK ================= */

        .login-link {
            text-align: center;

            margin-top: 25px;

            color: #77776e;

            font-size: 14px;
        }

        .login-link a {
            color: #59633d;

            text-decoration: none;

            font-weight: 600;
        }

        .login-link a:hover {
            text-decoration: underline;
        }


        /* ================= RESPONSIVE ================= */

        @media (max-width: 800px) {

            .register-left {
                display: none;
            }

            .register-right {
                width: 100%;
                min-height: 100vh;

                padding: 25px;
            }

            .mobile-logo {
                display: block;
            }

            .register-card {
                padding: 30px 25px;
            }
        }


        @media (max-width: 450px) {

            .register-right {
                padding: 15px;
            }

            .register-card {
                padding: 25px 20px;
            }

            .register-card h2 {
                font-size: 26px;
            }
        }

    </style>

</head>


<body>

    <div class="register-page">


        <!-- ================= LEFT SIDE ================= -->

        <div class="register-left">


            <!-- Brand -->

            <div class="brand">

                <div class="brand-icon">

                    <i class="fa-solid fa-calendar-days"></i>

                </div>

                <h2>MyCalendar</h2>

            </div>


            <!-- Content -->

            <div class="left-content">

                <h1>
                    Organize your life,
                    one day at a time.
                </h1>

                <p>
                    Create your free account and manage
                    your calendar, tasks, events and
                    expenses from one simple dashboard.
                </p>


                <!-- Features -->

                <div class="features">


                    <div class="feature">

                        <div class="feature-icon">

                            <i class="fa-solid fa-calendar"></i>

                        </div>

                        <span>
                            Manage your calendar easily
                        </span>

                    </div>


                    <div class="feature">

                        <div class="feature-icon">

                            <i class="fa-solid fa-list-check"></i>

                        </div>

                        <span>
                            Keep track of your daily tasks
                        </span>

                    </div>


                    <div class="feature">

                        <div class="feature-icon">

                            <i class="fa-solid fa-wallet"></i>

                        </div>

                        <span>
                            Track income and expenses
                        </span>

                    </div>


                    <div class="feature">

                        <div class="feature-icon">

                            <i class="fa-solid fa-bell"></i>

                        </div>

                        <span>
                            Never miss important events
                        </span>

                    </div>


                </div>

            </div>

        </div>


        <!-- ================= RIGHT SIDE ================= -->

        <div class="register-right">


            <!-- Registration Card -->

            <div class="register-card">


                <!-- Mobile Logo -->

                <div class="mobile-logo">

                    <i class="fa-solid fa-calendar-days"></i>

                </div>


                <!-- Heading -->

                <h2>
                    Create an account
                </h2>

                <p class="subtitle">
                    Start managing your day with MyCalendar.
                </p>


                <!-- Registration Form -->

                <form method="post">


                    <!-- Full Name -->

                    <div class="form-group">

                        <label for="name">
                            Full Name
                        </label>

                        <div class="input-box">

                            <i class="fa-solid fa-user input-icon"></i>

                            <input
                                type="text"
                                id="name"
                                name="name"
                                placeholder="Enter your full name"
                                required
                            >

                        </div>

                    </div>


                    <!-- Date of Birth -->

                    <div class="form-group">

                        <label for="dob">
                            Date of Birth
                        </label>

                        <div class="input-box">

                            <i class="fa-solid fa-calendar-days input-icon"></i>

                            <input
                                type="date"
                                id="dob"
                                name="dob"
                                required
                            >

                        </div>

                    </div>


                    <!-- Phone Number -->

                    <div class="form-group">

                        <label for="phone">
                            Phone Number
                        </label>

                        <div class="input-box">

                            <i class="fa-solid fa-phone input-icon"></i>

                            <input
                                type="tel"
                                id="phone"
                                name="phone"
                                placeholder="Enter your phone number"
                                maxlength="10"
                                required
                            >

                        </div>

                    </div>


                    <!-- Email -->

                    <div class="form-group">

                        <label for="email">
                            Email Address
                        </label>

                        <div class="input-box">

                            <i class="fa-solid fa-envelope input-icon"></i>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                placeholder="Enter your email"
                                required
                            >

                        </div>

                    </div>


                    <!-- Password -->

                    <div class="form-group">

                        <label for="password">
                            Password
                        </label>

                        <div class="input-box">

                            <i class="fa-solid fa-lock input-icon"></i>

                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Create a password"
                                required
                            >

                        </div>

                    </div>


                    <!-- Confirm Password -->

                    <div class="form-group">

                        <label for="confirm-password">
                            Confirm Password
                        </label>

                        <div class="input-box">

                            <i class="fa-solid fa-lock input-icon"></i>

                            <input
                                type="password"
                                id="confirm-password"
                                name="confirm_password"
                                placeholder="Confirm your password"
                                required
                            >

                        </div>

                    </div>


                    <!-- Terms -->

                    <div class="terms">

                        <input
                            type="checkbox"
                            id="terms"
                            name="terms"
                            required
                        >

                        <label for="terms">

                            I agree to the

                            <a href="#">
                                Terms & Conditions
                            </a>

                            and

                            <a href="#">
                                Privacy Policy
                            </a>.

                        </label>

                    </div>


                    <!-- Register Button -->

                    <button
                        type="submit"
                        class="register-btn"
                        name="sub"
                    >
                        Create Account
                    </button>


                </form>


                <!-- Login -->

                <div class="login-link">

                    Already have an account?

                    <a href="login.php">
                        Login
                    </a>

                </div>


            </div>

        </div>

    </div>

<!-- PHP -->

<?php
   $conn = new mysqli("localhost","root", "", "calender");

   if($conn){
    if(isset($_POST ['sub'])){
        $name = $_POST ['name'];
        $dob = $_POST ['dob'];
        $ph_no = $_POST ['phone'];
        $email = $_POST ['email'];    
        $password = $_POST ['password'];
        $status = "new";
       

        $sql = "INSERT INTO user_info (name,dob,phone_no,email,password) VALUES ('$name','$dob','$ph_no','$email','$password')";

        if(mysqli_query($conn,$sql)){
            echo("<script> alert('Registered Successfully'); document.location = 'login.php' </script>");
        }
    }
   }
   ?>






</body>

</html>