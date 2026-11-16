<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>home cal</title>

    <!-- Font Awesome -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">


    <style>

        /* =========================
           BASIC RESET
        ========================= */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            background-color: #f3ecdf;
            color: #34382a;
        }


        /* =========================
           NAVBAR
        ========================= */

        .navbar {
            width: 100%;
            height: 75px;

            background-color: #fffdf8;

            display: flex;
            align-items: center;
            justify-content: space-between;

            padding: 0 7%;

            border-bottom: 1px solid #e3ddcf;
        }


        /* Logo */

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;

            text-decoration: none;
            color: #59633d;
        }

        .logo-icon {
            width: 42px;
            height: 42px;

            background-color: #59633d;
            color: white;

            border-radius: 11px;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 20px;
        }

        .logo h2 {
            font-size: 23px;
        }


        /* Navigation */

        .nav-links {
            display: flex;
            align-items: center;
            gap: 32px;
        }

        .nav-links a {
            text-decoration: none;
            color: #55594a;

            font-size: 15px;
            font-weight: 500;

            transition: 0.3s;
        }

        .nav-links a:hover {
            color: #59633d;
        }


        /* Login Button */

        .login-btn {
            border: 1px solid #59633d;

            padding: 10px 22px;

            border-radius: 8px;

            color: #59633d !important;

            font-weight: 600 !important;
        }

        .login-btn:hover {
            background-color: #59633d;
            color: white !important;
        }


        /* =========================
           HERO SECTION
        ========================= */

        .hero {
            min-height: 620px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            padding: 70px 7%;

            gap: 60px;
        }


        /* Hero Content */

        .hero-content {
            width: 52%;
        }

        .hero-tag {
            display: inline-block;

            background-color: #dfe3ce;
            color: #59633d;

            padding: 8px 15px;

            border-radius: 30px;

            font-size: 13px;
            font-weight: 600;

            margin-bottom: 22px;
        }

        .hero-content h1 {
            font-size: 56px;

            line-height: 1.12;

            color: #34382a;

            margin-bottom: 22px;
        }

        .hero-content h1 span {
            color: #59633d;
        }

        .hero-content p {
            max-width: 570px;

            font-size: 18px;

            line-height: 1.7;

            color: #6f7168;

            margin-bottom: 32px;
        }


        /* Hero Buttons */

        .hero-buttons {
            display: flex;
            gap: 15px;
        }

        .primary-btn {
            display: inline-flex;

            align-items: center;
            justify-content: center;

            gap: 9px;

            padding: 14px 25px;

            background-color: #59633d;
            color: white;

            border-radius: 9px;

            text-decoration: none;

            font-size: 15px;
            font-weight: 600;

            transition: 0.3s;
        }

        .primary-btn:hover {
            background-color: #46502f;
            transform: translateY(-2px);
        }

        .secondary-btn {
            display: inline-flex;

            align-items: center;
            justify-content: center;

            gap: 9px;

            padding: 14px 25px;

            background-color: transparent;

            color: #59633d;

            border: 1px solid #59633d;

            border-radius: 9px;

            text-decoration: none;

            font-size: 15px;
            font-weight: 600;

            transition: 0.3s;
        }

        .secondary-btn:hover {
            background-color: #59633d;
            color: white;
        }


        /* =========================
           CALENDAR PREVIEW
        ========================= */

        .hero-preview {
            width: 45%;

            display: flex;
            justify-content: center;
        }

        .calendar-card {
            width: 100%;
            max-width: 470px;

            background-color: #fffdf8;

            border-radius: 20px;

            padding: 25px;

            box-shadow: 0 15px 45px rgba(60, 65, 40, 0.15);

            transform: rotate(1deg);
        }


        /* Calendar Header */

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;

            margin-bottom: 25px;
        }

        .calendar-header h3 {
            color: #34382a;
            font-size: 21px;
        }

        .calendar-header span {
            color: #77776e;
            font-size: 14px;
        }


        /* Calendar Week */

        .week-days,
        .calendar-days {
            display: grid;

            grid-template-columns: repeat(7, 1fr);

            gap: 8px;
        }

        .week-days {
            margin-bottom: 10px;
        }

        .week-days div {
            text-align: center;

            font-size: 12px;

            color: #888b7e;

            font-weight: 600;
        }


        /* Calendar Dates */

        .calendar-days div {
            height: 42px;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 9px;

            font-size: 13px;

            color: #55594a;
        }

        .calendar-days .empty {
            visibility: hidden;
        }

        .calendar-days .today {
            background-color: #59633d;
            color: white;
            font-weight: bold;
        }

        .calendar-days .event {
            background-color: #e2e6d3;
            color: #59633d;
            font-weight: 600;
        }


        /* Calendar Bottom */

        .calendar-bottom {
            margin-top: 22px;

            display: flex;

            gap: 12px;
        }

        .mini-task {
            flex: 1;

            padding: 13px;

            background-color: #f3ecdf;

            border-radius: 10px;
        }

        .mini-task small {
            color: #77776e;
            font-size: 11px;
        }

        .mini-task p {
            color: #59633d;

            font-size: 13px;

            font-weight: 600;

            margin-top: 5px;
        }




        /* =========================
           ACTIVE CALENDAR
        ========================= */

        .calendar-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .calendar-nav {
            width: 30px;
            height: 30px;
            border: 1px solid #e3ddcf;
            background-color: #f8f5ed;
            color: #59633d;
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            padding: 0;
            transition: 0.2s;
        }

        .calendar-nav:hover {
            background-color: #dfe3ce;
        }

        .calendar-days .calendar-date {
            cursor: pointer;
            transition: 0.2s;
        }

        .calendar-days .calendar-date:hover {
            background-color: #e2e6d3;
            color: #59633d;
        }

        .calendar-days .today {
            background-color: #59633d;
            color: white;
            font-weight: bold;
            box-shadow: 0 0 0 3px #dfe3ce;
        }

        .calendar-days .today:hover {
            background-color: #46502f;
            color: white;
        }

        .calendar-days .selected-date {
            outline: 2px solid #59633d;
            outline-offset: -2px;
        }

        /* =========================
           FEATURES SECTION
        ========================= */

        .features-section {
            background-color: #fffdf8;

            padding: 85px 7%;
        }

        .section-heading {
            text-align: center;

            max-width: 650px;

            margin: 0 auto 50px;
        }

        .section-heading span {
            color: #59633d;

            font-size: 13px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: 1px;
        }

        .section-heading h2 {
            font-size: 36px;

            color: #34382a;

            margin: 12px 0 15px;
        }

        .section-heading p {
            color: #77776e;

            line-height: 1.6;
        }


        /* Feature Cards */

        .feature-grid {
            display: grid;

            grid-template-columns: repeat(4, 1fr);

            gap: 22px;
        }

        .feature-card {
            padding: 30px 25px;

            background-color: #f8f5ed;

            border: 1px solid #ebe5d8;

            border-radius: 15px;

            transition: 0.3s;
        }

        .feature-card:hover {
            transform: translateY(-5px);

            box-shadow:
                0 10px 25px rgba(60, 65, 40, 0.10);
        }

        .feature-card-icon {
            width: 50px;
            height: 50px;

            background-color: #dfe3ce;

            color: #59633d;

            border-radius: 12px;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 20px;

            margin-bottom: 20px;
        }

        .feature-card h3 {
            font-size: 18px;

            color: #34382a;

            margin-bottom: 10px;
        }

        .feature-card p {
            font-size: 14px;

            color: #77776e;

            line-height: 1.6;
        }


        /* =========================
           HOW IT WORKS
        ========================= */

        .how-section {
            padding: 85px 7%;

            background-color: #f3ecdf;
        }

        .steps {
            display: grid;

            grid-template-columns: repeat(3, 1fr);

            gap: 30px;

            max-width: 1000px;

            margin: auto;
        }

        .step {
            text-align: center;
        }

        .step-number {
            width: 55px;
            height: 55px;

            margin: 0 auto 20px;

            background-color: #59633d;

            color: white;

            border-radius: 50%;

            display: flex;

            align-items: center;
            justify-content: center;

            font-size: 18px;

            font-weight: bold;
        }

        .step h3 {
            margin-bottom: 10px;

            color: #34382a;
        }

        .step p {
            color: #77776e;

            font-size: 14px;

            line-height: 1.6;
        }


        /* =========================
           CTA SECTION
        ========================= */

        .cta-section {
            padding: 70px 7%;
        }

        .cta-box {
            background-color: #59633d;

            border-radius: 20px;

            padding: 60px 40px;

            text-align: center;

            color: white;
        }

        .cta-box h2 {
            font-size: 36px;

            margin-bottom: 15px;
        }

        .cta-box p {
            color: #e6e8dd;

            max-width: 600px;

            margin: 0 auto 28px;

            line-height: 1.6;
        }

        .cta-btn {
            display: inline-block;

            background-color: #f3ecdf;

            color: #59633d;

            padding: 14px 28px;

            border-radius: 9px;

            text-decoration: none;

            font-weight: 700;

            transition: 0.3s;
        }

        .cta-btn:hover {
            background-color: white;

            transform: translateY(-2px);
        }


        /* =========================
           FOOTER
        ========================= */

        footer {
            background-color: #34382a;

            color: #dfe1d8;

            padding: 45px 7%;
        }

        .footer-content {
            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 30px;
        }

        .footer-logo {
            display: flex;

            align-items: center;

            gap: 10px;
        }

        .footer-logo i {
            color: #c8cfad;
        }

        .footer-logo h3 {
            color: white;
        }

        .footer-content p {
            font-size: 13px;

            color: #b8bbaf;
        }

        .footer-links {
            display: flex;

            gap: 25px;
        }

        .footer-links a {
            color: #c5c8bd;

            text-decoration: none;

            font-size: 13px;
        }

        .footer-links a:hover {
            color: white;
        }


        /* =========================
           RESPONSIVE DESIGN
        ========================= */

        @media (max-width: 1000px) {

            .hero-content h1 {
                font-size: 45px;
            }

            .feature-grid {
                grid-template-columns: repeat(2, 1fr);
            }

        }


        @media (max-width: 800px) {

            .navbar {
                padding: 0 5%;
            }

            .nav-links {
                gap: 15px;
            }

            .nav-links a:not(.login-btn) {
                display: none;
            }


            .hero {
                flex-direction: column;

                padding: 60px 5%;

                text-align: center;
            }

            .hero-content {
                width: 100%;
            }

            .hero-content p {
                margin-left: auto;
                margin-right: auto;
            }

            .hero-buttons {
                justify-content: center;
            }

            .hero-preview {
                width: 100%;
            }

            .calendar-card {
                transform: none;
            }


            .steps {
                grid-template-columns: 1fr;
            }

            .footer-content {
                flex-direction: column;

                text-align: center;
            }

        }


        @media (max-width: 550px) {

            .logo h2 {
                font-size: 20px;
            }

            .hero-content h1 {
                font-size: 38px;
            }

            .hero-buttons {
                flex-direction: column;
            }

            .primary-btn,
            .secondary-btn {
                width: 100%;
            }

            .feature-grid {
                grid-template-columns: 1fr;
            }

            .section-heading h2 {
                font-size: 30px;
            }

            .cta-box h2 {
                font-size: 28px;
            }

            .footer-links {
                flex-wrap: wrap;
                justify-content: center;
            }

        }

    </style>

</head>


<body>


    <!-- =========================
         NAVIGATION BAR
    ========================= -->

    <header class="navbar">


        <!-- Logo -->

        <a href="home.html" class="logo">

            <div class="logo-icon">

                <i class="fa-solid fa-calendar-days"></i>

            </div>

            <h2>MyCalendar</h2>

        </a>


        <!-- Navigation -->

        <nav class="nav-links">

            <a href="#features">
                Features
            </a>

            <a href="#how-it-works">
                How It Works
            </a>

            <a href="#about">
                About
            </a>

            <a href="login.php" class="login-btn">
                Login
            </a>

        </nav>

    </header>



    <!-- =========================
         HERO SECTION
    ========================= -->

    <section class="hero">


        <!-- Hero Text -->

        <div class="hero-content">

            <span class="hero-tag">
                YOUR PERSONAL ORGANIZER
            </span>

            <h1>
                Plan your day.
                <span>Manage your life.</span>
            </h1>

            <p>
                MyCalendar helps you organize your schedule,
                manage daily tasks, keep track of expenses and
                never forget your important events — all in
                one simple dashboard.
            </p>


            <div class="hero-buttons">

                <a href="reg.php" class="primary-btn">

                    Get Started

                    <i class="fa-solid fa-arrow-right"></i>

                </a>

                <a href="#features" class="secondary-btn">

                    Explore Features

                </a>

            </div>

        </div>



        <!-- Calendar Preview -->

        <div class="hero-preview">

            <div class="calendar-card">


                <div class="calendar-header">

                    <div class="calendar-title">
                        <button type="button" class="calendar-nav" onclick="changeMonth(-1)" aria-label="Previous month">
                            <i class="fa-solid fa-chevron-left"></i>
                        </button>

                        <h3 id="calendarMonthYear">September 2026</h3>

                        <button type="button" class="calendar-nav" onclick="changeMonth(1)" aria-label="Next month">
                            <i class="fa-solid fa-chevron-right"></i>
                        </button>
                    </div>

                    <span id="calendarTodayLabel">My Schedule</span>

                </div>


                <!-- Week Days -->

                <div class="week-days">

                    <div>Sun</div>
                    <div>Mon</div>
                    <div>Tue</div>
                    <div>Wed</div>
                    <div>Thu</div>
                    <div>Fri</div>
                    <div>Sat</div>

                </div>


                <!-- Calendar -->

                <div class="calendar-days" id="calendarDays">
                    <!-- Dates are generated automatically by JavaScript. -->
                </div>


                <!-- Small Tasks -->

                <div class="calendar-bottom">

                    <div class="mini-task">

                        <small>
                            TODAY
                        </small>

                        <p>
                            Complete project
                        </p>

                    </div>


                    <div class="mini-task">

                        <small>
                            UPCOMING
                        </small>

                        <p>
                            Team Meeting
                        </p>

                    </div>

                </div>

            </div>

        </div>

    </section>



    <!-- =========================
         FEATURES
    ========================= -->

    <section class="features-section"
             id="features">


        <div class="section-heading">

            <span>
                Everything in one place
            </span>

            <h2>
                Stay organized without the stress
            </h2>

            <p>
                Keep your important information organized
                and accessible through one simple dashboard.
            </p>

        </div>



        <div class="feature-grid">


            <!-- Calendar -->

            <div class="feature-card">

                <div class="feature-card-icon">

                    <i class="fa-solid fa-calendar-days"></i>

                </div>

                <h3>
                    Smart Calendar
                </h3>

                <p>
                    View your schedule monthly, daily or
                    yearly and keep track of everything
                    happening in your life.
                </p>

            </div>


            <!-- To Do -->

            <div class="feature-card">

                <div class="feature-card-icon">

                    <i class="fa-solid fa-list-check"></i>

                </div>

                <h3>
                    To-Do Lists
                </h3>

                <p>
                    Create, update and delete tasks while
                    keeping track of what you have completed.
                </p>

            </div>


            <!-- Expenses -->

            <div class="feature-card">

                <div class="feature-card-icon">

                    <i class="fa-solid fa-wallet"></i>

                </div>

                <h3>
                    Expense Tracking
                </h3>

                <p>
                    Track your income and expenses and
                    understand where your money is being spent.
                </p>

            </div>


            <!-- Events -->

            <div class="feature-card">

                <div class="feature-card-icon">

                    <i class="fa-solid fa-bell"></i>

                </div>

                <h3>
                    Events & Reminders
                </h3>

                <p>
                    Keep important events and appointments
                    organized so you can plan ahead.
                </p>

            </div>


        </div>

    </section>



    <!-- =========================
         HOW IT WORKS
    ========================= -->

    <section class="how-section"
             id="how-it-works">


        <div class="section-heading">

            <span>
                Simple process
            </span>

            <h2>
                Get organized in three steps
            </h2>

            <p>
                Start using MyCalendar with just a few
                simple steps.
            </p>

        </div>



        <div class="steps">


            <!-- Step 1 -->

            <div class="step">

                <div class="step-number">
                    1
                </div>

                <h3>
                    Create an Account
                </h3>

                <p>
                    Register your account with your basic
                    details and create your password.
                </p>

            </div>


            <!-- Step 2 -->

            <div class="step">

                <div class="step-number">
                    2
                </div>

                <h3>
                    Add Your Plans
                </h3>

                <p>
                    Add your tasks, events, income and
                    expenses to your personal dashboard.
                </p>

            </div>


            <!-- Step 3 -->

            <div class="step">

                <div class="step-number">
                    3
                </div>

                <h3>
                    Stay Organized
                </h3>

                <p>
                    View and manage everything from one
                    convenient calendar dashboard.
                </p>

            </div>


        </div>

    </section>



    <!-- =========================
         CALL TO ACTION
    ========================= -->

    <section class="cta-section"
             id="about">


        <div class="cta-box">

            <h2>
                Ready to organize your day?
            </h2>

            <p>
                Create your MyCalendar account and bring
                your calendar, tasks, events and expenses
                together in one place.
            </p>

            <a href="reg.php"
               class="cta-btn">

                Create Your Account

            </a>

        </div>

    </section>



    <!-- =========================
         FOOTER
    ========================= -->

    <footer>

        <div class="footer-content">


            <div class="footer-logo">

                <i class="fa-solid fa-calendar-days"></i>

                <h3>
                    MyCalendar
                </h3>

            </div>


            <p>
                © 2026 MyCalendar. All rights reserved.
            </p>


            <div class="footer-links">

                <a href="#">
                    Privacy Policy
                </a>

                <a href="#">
                    Terms & Conditions
                </a>

                <a href="login.html">
                    Login
                </a>

            </div>


        </div>

    </footer>




<script>
    /* =========================
       ACTIVE CALENDAR
       The calendar always uses the
       visitor's current browser date.
    ========================= */

    let calendarDate = new Date();
    let selectedCalendarDate = new Date();

    const monthNames = [
        "January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"
    ];

    function renderCalendar() {
        const calendarDays = document.getElementById("calendarDays");
        const monthYear = document.getElementById("calendarMonthYear");
        const todayLabel = document.getElementById("calendarTodayLabel");

        if (!calendarDays || !monthYear) return;

        const year = calendarDate.getFullYear();
        const month = calendarDate.getMonth();

        monthYear.textContent = `${monthNames[month]} ${year}`;

        calendarDays.innerHTML = "";

        // First day of the month: 0 = Sunday, 1 = Monday, etc.
        const firstDay = new Date(year, month, 1).getDay();

        // Number of days in the current month.
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        // Add empty cells before day 1.
        for (let i = 0; i < firstDay; i++) {
            const emptyCell = document.createElement("div");
            emptyCell.className = "empty";
            emptyCell.textContent = "0";
            calendarDays.appendChild(emptyCell);
        }

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        for (let day = 1; day <= daysInMonth; day++) {
            const dateCell = document.createElement("div");
            dateCell.className = "calendar-date";
            dateCell.textContent = day;

            const cellDate = new Date(year, month, day);
            cellDate.setHours(0, 0, 0, 0);

            // Automatically highlight today's date.
            if (cellDate.getTime() === today.getTime()) {
                dateCell.classList.add("today");
            }

            // Keep a visible outline when another date is selected.
            if (cellDate.getTime() === selectedCalendarDate.getTime() &&
                cellDate.getTime() !== today.getTime()) {
                dateCell.classList.add("selected-date");
            }

            dateCell.addEventListener("click", function () {
                selectedCalendarDate = new Date(year, month, day);
                selectedCalendarDate.setHours(0, 0, 0, 0);
                renderCalendar();
            });

            calendarDays.appendChild(dateCell);
        }

        // Show the actual current date in the small label.
        const formattedToday = today.toLocaleDateString("en-US", {
            month: "short",
            day: "numeric",
            year: "numeric"
        });

        todayLabel.textContent = `Today: ${formattedToday}`;
    }

    function changeMonth(direction) {
        calendarDate.setMonth(calendarDate.getMonth() + direction);
        renderCalendar();
    }

    // Render immediately when the page opens.
    document.addEventListener("DOMContentLoaded", renderCalendar);
</script>

</body>

</html>