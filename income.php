<?php

session_start();



if (!isset($_SESSION['ID']) || !is_numeric($_SESSION['ID']) || (int)$_SESSION['ID'] <= 0) {

    header("Location: login.php");

    exit();

}



$user_id = (int)$_SESSION['ID'];



$con = new mysqli("localhost", "root", "", "calender");



if ($con->connect_error) {

    die("Database connection failed: " . $con->connect_error);

}



$sidebar_user_name = "Profile";

$name_stmt = $con->prepare("SELECT name FROM user_info WHERE id = ? LIMIT 1");

if ($name_stmt) {

    $name_stmt->bind_param("i", $user_id);

    $name_stmt->execute();

    $name_result = $name_stmt->get_result();

    if ($name_row = $name_result->fetch_assoc()) {

        $sidebar_user_name = trim((string)($name_row['name'] ?? '')) ?: "Profile";

    }

    $name_stmt->close();

}

$sidebar_user_name = htmlspecialchars($sidebar_user_name, ENT_QUOTES, 'UTF-8');



$message = "";

$message_type = "";

/* =========================================================
   INCOME SEARCH
========================================================= */

$income_search = trim($_GET['search'] ?? '');




/* =========================================================

   ADD INCOME

========================================================= */



if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_income'])) {



    $income_source = trim($_POST['income_source'] ?? '');

    $amount = trim($_POST['amount'] ?? '');

    $income_date = $_POST['income_date'] ?? '';

    $payment_method = trim($_POST['payment_method'] ?? '');

    $description = trim($_POST['description'] ?? '');



    if ($income_source === '' || $amount === '' || $income_date === '' || $payment_method === '') {



        $message = "Please fill in all required fields.";

        $message_type = "error";



    } elseif (!is_numeric($amount) || (float)$amount <= 0) {



        $message = "Please enter a valid amount.";

        $message_type = "error";



    } else {



        /* Validate the date before inserting. */

        $date_object = DateTime::createFromFormat('Y-m-d', $income_date);



        if (

            !$date_object ||

            $date_object->format('Y-m-d') !== $income_date

        ) {



            $message = "Please select a valid income date.";

            $message_type = "error";



        } else {



            /*

             * Keep money as a decimal string instead of converting it

             * to a PHP floating-point value.

             */

            $amount = number_format((float)$amount, 2, '.', '');



            $stmt = $con->prepare(

                "INSERT INTO income

                (

                    user_id,

                    income_source,

                    amount,

                    income_date,

                    payment_method,

                    description

                )

                VALUES (?, ?, ?, ?, ?, ?)"

            );



            if (!$stmt) {



                $message = "Database error: " . $con->error;

                $message_type = "error";



            } else {



                $stmt->bind_param(

                    "isssss",

                    $user_id,

                    $income_source,

                    $amount,

                    $income_date,

                    $payment_method,

                    $description

                );



                if ($stmt->execute()) {



                    $stmt->close();



                    /*

                     * Redirect only after a successful INSERT.

                     * This prevents duplicate submissions on refresh.

                     */

                    header("Location: income.php?added=1");

                    exit();



                } else {



                    $message = "Unable to save income: " . $stmt->error;

                    $message_type = "error";



                    $stmt->close();

                }

            }

        }

    }

}



/* =========================================================

   DELETE INCOME

========================================================= */



if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_income'])) {



    $income_id = (int)($_POST['income_id'] ?? 0);



    if ($income_id > 0) {



        $stmt = $con->prepare(

            "DELETE FROM income

             WHERE id = ?

             AND user_id = ?"

        );



        $stmt->bind_param("ii", $income_id, $user_id);

        $stmt->execute();



        $stmt->close();



        header("Location: income.php?deleted=1");

        exit();

    }

}



/* =========================================================

   SUCCESS MESSAGES

========================================================= */



if (isset($_GET['added'])) {

    $message = "Income added successfully.";

    $message_type = "success";

}



if (isset($_GET['deleted'])) {

    $message = "Income deleted successfully.";

    $message_type = "success";

}



/* =========================================================

   TOTAL INCOME

========================================================= */



$total_income = 0;



$stmt = $con->prepare(

    "SELECT COALESCE(SUM(amount), 0)

     FROM income

     WHERE user_id = ?"

);



$stmt->bind_param("i", $user_id);

$stmt->execute();

$stmt->bind_result($total_income);

$stmt->fetch();

$stmt->close();



/* =========================================================

   THIS MONTH INCOME

========================================================= */



$monthly_income = 0;



$stmt = $con->prepare(

    "SELECT COALESCE(SUM(amount), 0)

     FROM income

     WHERE user_id = ?

     AND MONTH(income_date) = MONTH(CURDATE())

     AND YEAR(income_date) = YEAR(CURDATE())"

);



$stmt->bind_param("i", $user_id);

$stmt->execute();

$stmt->bind_result($monthly_income);

$stmt->fetch();

$stmt->close();



/* =========================================================

   INCOME COUNT

========================================================= */



$income_count = 0;



$stmt = $con->prepare(

    "SELECT COUNT(*)

     FROM income

     WHERE user_id = ?"

);



$stmt->bind_param("i", $user_id);

$stmt->execute();

$stmt->bind_result($income_count);

$stmt->fetch();

$stmt->close();



/* =========================================================

   FETCH INCOME RECORDS

========================================================= */



$income_records = [];

if ($income_search !== '') {

    $search_like = "%" . $income_search . "%";

    $stmt = $con->prepare(
        "SELECT
            id,
            income_source,
            amount,
            income_date,
            payment_method,
            description
         FROM income
         WHERE user_id = ?
         AND (
             income_source LIKE ?
             OR CAST(amount AS CHAR) LIKE ?
             OR income_date LIKE ?
             OR DATE_FORMAT(income_date, '%d %b %Y') LIKE ?
             OR payment_method LIKE ?
         )
         ORDER BY income_date DESC, id DESC"
    );

    $stmt->bind_param(
        "isssss",
        $user_id,
        $search_like,
        $search_like,
        $search_like,
        $search_like,
        $search_like
    );

} else {

    $stmt = $con->prepare(
        "SELECT
            id,
            income_source,
            amount,
            income_date,
            payment_method,
            description
         FROM income
         WHERE user_id = ?
         ORDER BY income_date DESC, id DESC"
    );

    $stmt->bind_param("i", $user_id);
}

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $income_records[] = $row;
}

$stmt->close();

$con->close();



?>

<!DOCTYPE html>

<html lang="en">



<head>



    <meta charset="UTF-8">



    <meta

        name="viewport"

        content="width=device-width, initial-scale=1.0"

    >



    <title>Income | MyCalendar</title>



    <style>



        :root {

            --bg-beige: #F4EFEA;

            --card-beige: #FAF7F2;

            --card-alt: #EFE8DE;

            --border-beige: #E5DDD1;



            --olive-dark: #374621;

            --olive-primary: #556B2F;

            --olive-light: #70843D;

            --olive-tint: #E8EDE0;



            --text-dark: #2A2C24;

            --text-muted: #727368;



            --white: #FFFFFF;



            --success: #3E7A4A;

            --error: #B94A48;

            --warning: #A87520;

        }



        * {

            box-sizing: border-box;

            margin: 0;

            padding: 0;

            font-family:

                'Segoe UI',

                -apple-system,

                BlinkMacSystemFont,

                Roboto,

                sans-serif;

        }



        body {

            background-color: var(--bg-beige);

            color: var(--text-dark);

            display: flex;

            min-height: 100vh;

        }



        /* =====================================================

           SIDEBAR

        ===================================================== */



        .sidebar {

            width: 250px;

            background-color: var(--olive-dark);

            color: #FFFFFF;

            display: flex;

            flex-direction: column;

            padding: 30px 20px;

            flex-shrink: 0;

        }



        .sidebar-brand {

            font-size: 15px;

            text-transform: uppercase;

            letter-spacing: 1.5px;

            color: #CCD6B8;

            margin-bottom: 30px;

            padding-left: 10px;

        }



        .nav-list {

            list-style: none;

            display: flex;

            flex-direction: column;

            gap: 8px;

        }



        .nav-item a {

            color: #E2E8D5;

            text-decoration: none;

            font-size: 16px;

            font-weight: 600;

            padding: 12px 16px;

            border-radius: 8px;

            display: flex;

            align-items: center;

            gap: 12px;

            transition: all 0.2s ease;

        }



        .nav-item a:hover {

            background-color: var(--olive-primary);

            color: var(--white);

        }



        .nav-item.active a {

            background-color: var(--olive-primary);

            color: var(--white);

        }



        /* =====================================================

           MAIN WRAPPER

        ===================================================== */



        .main-wrapper {

            flex: 1;

            display: flex;

            flex-direction: column;

            height: 100vh;

            overflow-y: auto;

        }



        /* =====================================================

           HEADER

        ===================================================== */



        header {

            background-color: var(--card-beige);

            border-bottom: 1px solid var(--border-beige);

            padding: 20px 36px;

            display: flex;

            justify-content: space-between;

            align-items: center;

        }



        .header-left h1 {

            font-size: 22px;

            color: var(--olive-dark);

            font-weight: 700;

        }



        .header-left p {

            font-size: 13px;

            color: var(--text-muted);

            margin-top: 3px;

        }



        .user-profile {

            display: flex;

            align-items: center;

            gap: 12px;

        }



        .avatar {

            width: 40px;

            height: 40px;

            border-radius: 50%;

            background-color: var(--olive-primary);

            color: var(--white);

            display: grid;

            place-items: center;

            font-weight: 700;

            font-size: 14px;

        }



        /* =====================================================

           CONTENT

        ===================================================== */



        .income-content {

            padding: 30px 36px;

        }



        .income-top {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 24px;

        }



        .income-heading h2 {

            font-size: 24px;

            color: var(--olive-dark);

            margin-bottom: 5px;

        }



        .income-heading p {

            font-size: 13px;

            color: var(--text-muted);

        }



        .add-income-btn {

            border: none;

            background-color: var(--olive-primary);

            color: var(--white);

            padding: 12px 20px;

            border-radius: 8px;

            font-size: 14px;

            font-weight: 700;

            cursor: pointer;

        }



        .add-income-btn:hover {

            background-color: var(--olive-dark);

        }



        /* =====================================================

           MESSAGE

        ===================================================== */



        .message {

            padding: 13px 16px;

            border-radius: 8px;

            margin-bottom: 20px;

            font-size: 14px;

            font-weight: 600;

        }



        .message.success {

            background-color: #E7F1E8;

            color: var(--success);

            border: 1px solid #C9DEC9;

        }



        .message.error {

            background-color: #F8E7E6;

            color: var(--error);

            border: 1px solid #E6C5C3;

        }



        /* =====================================================

           SUMMARY CARDS

        ===================================================== */



        .summary-grid {

            display: grid;

            grid-template-columns: repeat(3, 1fr);

            gap: 18px;

            margin-bottom: 24px;

        }



        .summary-card {

            background-color: var(--card-beige);

            border: 1px solid var(--border-beige);

            border-radius: 12px;

            padding: 22px;

            box-shadow: 0 4px 16px rgba(55, 70, 33, 0.04);

        }



        .summary-label {

            color: var(--text-muted);

            font-size: 12px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: 0.5px;

            margin-bottom: 8px;

        }



        .summary-value {

            color: var(--olive-dark);

            font-size: 25px;

            font-weight: 800;

        }



        .summary-sub {

            color: var(--text-muted);

            font-size: 12px;

            margin-top: 5px;

        }



        /* =====================================================

           ADD INCOME FORM

        ===================================================== */



        .add-income-card {

            background-color: var(--card-beige);

            border: 1px solid var(--border-beige);

            border-radius: 12px;

            padding: 25px;

            margin-bottom: 24px;

            box-shadow: 0 4px 16px rgba(55, 70, 33, 0.04);

            display: none;

        }



        .add-income-card.show {

            display: block;

        }



        .form-header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 22px;

        }



        .form-header h3 {

            font-size: 18px;

            color: var(--olive-dark);

        }



        .close-form {

            border: none;

            background: transparent;

            color: var(--text-muted);

            font-size: 22px;

            cursor: pointer;

        }



        .form-grid {

            display: grid;

            grid-template-columns: repeat(2, 1fr);

            gap: 18px;

        }



        .form-group {

            display: flex;

            flex-direction: column;

            gap: 7px;

        }



        .form-group.full {

            grid-column: 1 / -1;

        }



        .form-group label {

            font-size: 13px;

            font-weight: 700;

            color: var(--text-dark);

        }



        .form-group input,

        .form-group select,

        .form-group textarea {

            width: 100%;

            border: 1px solid var(--border-beige);

            background-color: var(--white);

            color: var(--text-dark);

            border-radius: 8px;

            padding: 11px 12px;

            font-size: 14px;

            outline: none;

        }



        .form-group input:focus,

        .form-group select:focus,

        .form-group textarea:focus {

            border-color: var(--olive-primary);

        }



        .form-group textarea {

            min-height: 95px;

            resize: vertical;

        }



        .form-actions {

            display: flex;

            justify-content: flex-end;

            margin-top: 20px;

        }



        .save-btn {

            border: none;

            background-color: var(--olive-primary);

            color: var(--white);

            padding: 12px 24px;

            border-radius: 8px;

            font-size: 14px;

            font-weight: 700;

            cursor: pointer;

        }



        .save-btn:hover {

            background-color: var(--olive-dark);

        }



        /* =====================================================

           INCOME HISTORY

        ===================================================== */



        .income-card {

            background-color: var(--card-beige);

            border: 1px solid var(--border-beige);

            border-radius: 12px;

            padding: 24px;

            margin-bottom: 24px;

            box-shadow: 0 4px 16px rgba(55, 70, 33, 0.04);

        }



        .card-header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 20px;

        }



        .card-header h3 {

            font-size: 17px;

            color: var(--olive-dark);

        }



        .income-count {

            font-size: 11px;

            font-weight: 700;

            background-color: var(--olive-tint);

            color: var(--olive-dark);

            padding: 5px 11px;

            border-radius: 12px;

            text-transform: uppercase;

            letter-spacing: 0.5px;

        }



        /* =====================================================
           INCOME SEARCH
        ===================================================== */

        .income-search {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 18px;
        }

        .income-search-input {
            flex: 1;
            min-width: 0;
            border: 1px solid var(--border-beige);
            background-color: var(--white);
            color: var(--text-dark);
            border-radius: 8px;
            padding: 11px 13px;
            font-size: 14px;
            outline: none;
        }

        .income-search-input:focus {
            border-color: var(--olive-primary);
        }

        .income-search-btn {
            border: none;
            background-color: var(--olive-primary);
            color: var(--white);
            padding: 11px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
        }

        .income-search-btn:hover {
            background-color: var(--olive-dark);
        }

        .income-clear-btn {
            color: var(--olive-dark);
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
        }

        .income-clear-btn:hover {
            text-decoration: underline;
        }

        .income-search-info {
            font-size: 12px;
            color: var(--text-muted);
            margin: -6px 0 16px;
        }

        .income-list {

            display: flex;

            flex-direction: column;

            gap: 12px;

        }



        .income-item {

            display: flex;

            align-items: center;

            gap: 15px;

            background-color: var(--white);

            border: 1px solid var(--border-beige);

            border-radius: 8px;

            padding: 15px;

        }



        .income-icon {

            width: 40px;

            height: 40px;

            border-radius: 50%;

            background-color: var(--olive-tint);

            color: var(--olive-dark);

            display: grid;

            place-items: center;

            font-size: 18px;

            flex-shrink: 0;

        }



        .income-info {

            flex: 1;

            min-width: 0;

        }



        .income-info h4 {

            font-size: 15px;

            color: var(--text-dark);

            font-weight: 700;

            margin-bottom: 5px;

        }



        .income-details {

            display: flex;

            align-items: center;

            gap: 8px;

            flex-wrap: wrap;

        }



        .income-detail {

            font-size: 10px;

            font-weight: 700;

            color: var(--text-muted);

            background-color: #F2EEE8;

            padding: 3px 8px;

            border-radius: 10px;

        }



        .income-amount {

            font-size: 16px;

            font-weight: 800;

            color: var(--success);

            white-space: nowrap;

        }



        .delete-form {

            margin-left: 5px;

        }



        .delete-btn {

            border: none;

            background: transparent;

            color: #9A958B;

            font-size: 18px;

            cursor: pointer;

            padding: 6px;

            border-radius: 6px;

        }



        .delete-btn:hover {

            color: var(--error);

            background-color: #F8E7E6;

        }



        .description {

            font-size: 12px;

            color: var(--text-muted);

            margin-top: 5px;

            word-break: break-word;

        }



        .no-income {

            text-align: center;

            padding: 45px 20px;

            color: var(--text-muted);

        }



        .no-income-icon {

            font-size: 35px;

            margin-bottom: 12px;

        }



        .no-income h4 {

            color: var(--olive-dark);

            font-size: 16px;

            margin-bottom: 6px;

        }



        .no-income p {

            font-size: 13px;

        }



        /* =====================================================

           RESPONSIVE

        ===================================================== */



        @media (max-width: 900px) {



            .sidebar {

                width: 80px;

                padding: 20px 10px;

                align-items: center;

            }



            .sidebar-brand {

                display: none;

            }



            .nav-item a {

                font-size: 12px;

                padding: 10px;

            }



            .income-content {

                padding: 25px 20px;

            }



            .summary-grid {

                grid-template-columns: 1fr;

            }



            .form-grid {

                grid-template-columns: 1fr;

            }



            .form-group.full {

                grid-column: auto;

            }

        }



        @media (max-width: 600px) {



            header {

                padding: 18px 20px;

            }



            .header-left h1 {

                font-size: 19px;

            }



            .user-profile > div:last-child {

                display: none;

            }



            .income-top {

                align-items: flex-start;

                gap: 15px;

                flex-direction: column;

            }



            .add-income-btn {

                width: 100%;

            }



            .income-search {
                flex-wrap: wrap;
            }

            .income-search-input {
                width: 100%;
                flex-basis: 100%;
            }

            .income-search-btn {
                flex: 1;
            }

            .income-item {

                align-items: flex-start;

                flex-wrap: wrap;

            }



            .income-amount {

                margin-left: 55px;

            }

        }







        /* =====================================================

           SIDEBAR PROFILE / LOGOUT

        ===================================================== */

        .sidebar-footer {

            margin-top: auto;

            padding-top: 18px;

            border-top: 1px solid rgba(255,255,255,0.16);

            flex-shrink: 0;

        }



        .sidebar-footer .nav-list {

            gap: 8px;

        }



        .sidebar-footer .logout a {

            color: #F2D6D6;

        }



        .sidebar-footer .logout a:hover,

        .sidebar-footer .logout.active a {

            background-color: #7A3D3D;

            color: #FFFFFF;

        }



        /* Profile is now handled from the sidebar. */

        .user-profile {

            display: none !important;

        }



</style>



</head>



<body>



    <!-- =====================================================

         SIDEBAR

    ===================================================== -->



    <aside class="sidebar">



        <div class="sidebar-brand">

            My Calendar

        </div>



        <ul class="nav-list">



            <li class="nav-item">

                <a href="dashboard.php">

                    Calendar

                </a>

            </li>



            <li class="nav-item">

                <a href="events.php">

                    Events

                </a>

            </li>



            <li class="nav-item">

                <a href="todo.php">

                    To-Do List

                </a>

            </li>



            <li class="nav-item active">

                <a href="income.php">

                    Income

                </a>

            </li>



            <li class="nav-item">

                <a href="expenses.php">

                    Expenses

                </a>

            </li>

             <li class="nav-item">
                <a href="reports.php">
                    Reports
                </a>
            </li>

             <li class="nav-item">
                <a href="personal_vault.php">
                    Personal Vault
                </a>
            </li>



        </ul>





        <!-- PROFILE AND LOGOUT

             Kept at the bottom of the sidebar. -->

        <div class="sidebar-footer">

            <ul class="nav-list">

                <li class="nav-item">

                    <a href="profile.php">

                        <?= $sidebar_user_name ?>

                    </a>

                </li>



                <li class="nav-item logout">

                    <a href="login.php">

                        Logout

                    </a>

                </li>

            </ul>

        </div>



    </aside>





    <!-- =====================================================

         MAIN

    ===================================================== -->



    <div class="main-wrapper">



        <header>



            <div class="header-left">



                <h1>

                    Income

                </h1>



                <p>

                    Track your income from all your different sources

                </p>



            </div>





            <div class="user-profile">



                <div class="avatar">

                    PA

                </div>



                <div>



                    <strong

                        style="

                        font-size:14px;

                        display:block;

                        "

                    >

                        Portal Admin

                    </strong>



                    <small

                        style="

                        color:var(--text-muted);

                        font-size:12px;

                        "

                    >

                        admin@portal.io

                    </small>



                </div>



            </div>



        </header>





        <main class="income-content">



            <div class="income-top">



                <div class="income-heading">



                    <h2>

                        My Income

                    </h2>



                    <p>

                        Keep track of money received from your different income sources.

                    </p>



                </div>



                <button

                    type="button"

                    class="add-income-btn"

                    onclick="openIncomeForm()"

                >

                    + Add Income

                </button>



            </div>





            <?php if (!empty($message)) : ?>



                <div class="message <?php echo $message_type; ?>">



                    <?php

                    echo htmlspecialchars($message);

                    ?>



                </div>



            <?php endif; ?>





            <!-- =================================================

                 SUMMARY

            ================================================= -->



            <section class="summary-grid">



                <div class="summary-card">



                    <div class="summary-label">

                        Total Income

                    </div>



                    <div class="summary-value">

                        ₹<?php echo number_format((float)$total_income, 2); ?>

                    </div>



                    <div class="summary-sub">

                        From all recorded sources

                    </div>



                </div>





                <div class="summary-card">



                    <div class="summary-label">

                        This Month

                    </div>



                    <div class="summary-value">

                        ₹<?php echo number_format((float)$monthly_income, 2); ?>

                    </div>



                    <div class="summary-sub">

                        Income received this month

                    </div>



                </div>





                <div class="summary-card">



                    <div class="summary-label">

                        Income Records

                    </div>



                    <div class="summary-value">

                        <?php echo (int)$income_count; ?>

                    </div>



                    <div class="summary-sub">

                        Recorded income transactions

                    </div>



                </div>



            </section>





            <!-- =================================================

                 ADD INCOME FORM

            ================================================= -->



            <section

                class="add-income-card"

                id="addIncomeCard"

            >



                <div class="form-header">



                    <h3>

                        Add New Income

                    </h3>



                    <button

                        type="button"

                        class="close-form"

                        onclick="closeIncomeForm()"

                    >

                        &times;

                    </button>



                </div>





                <form

                    method="post"

                    action="income.php"

                >



                    <div class="form-grid">



                        <div class="form-group">



                            <label for="income_source">

                                Income Source

                            </label>



                            <input

                                type="text"

                                id="income_source"

                                name="income_source"

                                placeholder="e.g. Salary, Freelancing"

                                value="<?php echo htmlspecialchars($_POST['income_source'] ?? ''); ?>"

                                required

                            >



                        </div>





                        <div class="form-group">



                            <label for="amount">

                                Amount

                            </label>



                            <input

                                type="number"

                                id="amount"

                                name="amount"

                                min="0.01"

                                step="0.01"

                                placeholder="Enter amount"

                                value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>"

                                required

                            >



                        </div>





                        <div class="form-group">



                            <label for="income_date">

                                Date Received

                            </label>



                            <input

                                type="date"

                                id="income_date"

                                name="income_date"

                                value="<?php echo htmlspecialchars($_POST['income_date'] ?? date('Y-m-d')); ?>"

                                required

                            >



                        </div>





                        <div class="form-group">



                            <label for="payment_method">

                                Payment Method

                            </label>



                            <select

                                id="payment_method"

                                name="payment_method"

                                required

                            >



                                <option value="">

                                    Select payment method

                                </option>



                                <option value="Bank Transfer" <?php echo (($_POST['payment_method'] ?? '') === 'Bank Transfer') ? 'selected' : ''; ?>>

                                    Bank Transfer

                                </option>



                                <option value="Cash" <?php echo (($_POST['payment_method'] ?? '') === 'Cash') ? 'selected' : ''; ?>>

                                    Cash

                                </option>



                                <option value="UPI" <?php echo (($_POST['payment_method'] ?? '') === 'UPI') ? 'selected' : ''; ?>>

                                    UPI

                                </option>



                                <option value="Card" <?php echo (($_POST['payment_method'] ?? '') === 'Card') ? 'selected' : ''; ?>>

                                    Card

                                </option>



                                <option value="Other" <?php echo (($_POST['payment_method'] ?? '') === 'Other') ? 'selected' : ''; ?>>

                                    Other

                                </option>



                            </select>



                        </div>





                        <div class="form-group full">



                            <label for="description">

                                Description

                            </label>



                            <textarea

                                id="description"

                                name="description"

                                placeholder="Add any additional details about this income..."

                            ><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>



                        </div>



                    </div>





                    <div class="form-actions">



                        <button

                            type="submit"

                            name="add_income"

                            class="save-btn"

                        >

                            Save Income

                        </button>



                    </div>



                </form>



            </section>





            <!-- =================================================

                 INCOME HISTORY

            ================================================= -->



            <section class="income-card">



                <div class="card-header">



                    <h3>

                        Income History

                    </h3>



                    <span class="income-count">

                        <?php echo count($income_records); ?><?php echo ($income_search !== '') ? ' Matching' : ''; ?> Records

                    </span>



                </div>





                <form
                    method="get"
                    action="income.php"
                    class="income-search"
                >

                    <input
                        type="search"
                        name="search"
                        class="income-search-input"
                        value="<?php echo htmlspecialchars($income_search, ENT_QUOTES, 'UTF-8'); ?>"
                        placeholder="Search by income source, amount, date received or payment method"
                        aria-label="Search income"
                    >

                    <button
                        type="submit"
                        class="income-search-btn"
                    >
                        Search
                    </button>

                    <?php if ($income_search !== '') : ?>

                        <a
                            href="income.php"
                            class="income-clear-btn"
                        >
                            Clear
                        </a>

                    <?php endif; ?>

                </form>

                <?php if ($income_search !== '') : ?>

                    <div class="income-search-info">
                        Showing income records matching
                        "<?php echo htmlspecialchars($income_search, ENT_QUOTES, 'UTF-8'); ?>"
                    </div>

                <?php endif; ?>


                <?php if (empty($income_records)) : ?>



                    <div class="no-income">



                        <div class="no-income-icon">

                            ₹

                        </div>



                        <h4>

                            No income recorded yet

                        </h4>



                        <p>

                            Add your first income source to start tracking your finances.

                        </p>



                    </div>



                <?php else : ?>



                    <div class="income-list">



                        <?php foreach ($income_records as $income) : ?>



                            <div class="income-item">



                                <div class="income-icon">

                                    ₹

                                </div>





                                <div class="income-info">



                                    <h4>

                                        <?php

                                        echo htmlspecialchars(

                                            $income['income_source']

                                        );

                                        ?>

                                    </h4>





                                    <div class="income-details">



                                        <span class="income-detail">

                                            <?php

                                            echo date(

                                                "d M Y",

                                                strtotime($income['income_date'])

                                            );

                                            ?>

                                        </span>



                                        <span class="income-detail">

                                            <?php

                                            echo htmlspecialchars(

                                                $income['payment_method']

                                            );

                                            ?>

                                        </span>



                                    </div>





                                    <?php if (!empty($income['description'])) : ?>



                                        <div class="description">



                                            <?php

                                            echo htmlspecialchars(

                                                $income['description']

                                            );

                                            ?>



                                        </div>



                                    <?php endif; ?>



                                </div>





                                <div class="income-amount">



                                    + ₹<?php

                                    echo number_format(

                                        (float)$income['amount'],

                                        2

                                    );

                                    ?>



                                </div>





                                <form

                                    method="post"

                                    class="delete-form"

                                    onsubmit="return confirm('Are you sure you want to delete this income record?');"

                                >



                                    <input

                                        type="hidden"

                                        name="income_id"

                                        value="<?php echo (int)$income['id']; ?>"

                                    >



                                    <button

                                        type="submit"

                                        name="delete_income"

                                        class="delete-btn"

                                        title="Delete income"

                                    >

                                        &times;

                                    </button>



                                </form>



                            </div>



                        <?php endforeach; ?>



                    </div>



                <?php endif; ?>



            </section>



        </main>



    </div>





    <script>



        function openIncomeForm() {



            const card = document.getElementById("addIncomeCard");



            card.classList.add("show");



            card.scrollIntoView({

                behavior: "smooth",

                block: "start"

            });



        }





        function closeIncomeForm() {



            const card = document.getElementById("addIncomeCard");



            card.classList.remove("show");



        }



    </script>



</body>



</html>
