<?php

session_start();

if (!isset($_SESSION['ID'])) {

    header("Location: login.php");

    exit();

}

$user_id = $_SESSION['ID'];

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

   MANUAL INCOME SOURCE

\========================================================= */

// Keep the source as free text entered by the user.

// Older expenses that used income_id remain supported in history.

$con->query(

    "ALTER TABLE expenses

     ADD COLUMN IF NOT EXISTS spending_from_income VARCHAR(255) NULL AFTER payment_method"

);

/* =========================================================

   ADD EXPENSE

\========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_expense'])) {

    $expense_name = trim($_POST['expense_name'] ?? '');

    $category = trim($_POST['category'] ?? '');

    $amount = trim($_POST['amount'] ?? '');

    $expense_date = $_POST['expense_date'] ?? '';

    $payment_method = trim($_POST['payment_method'] ?? '');

    $spending_from_income = trim($_POST['spending_from_income'] ?? '');

    $description = trim($_POST['description'] ?? '');

    if ($expense_name === '' || $category === '' || $amount === '' || $expense_date === '' || $payment_method === '') {

        $message = "Please fill in all required fields.";

        $message_type = "error";

    } elseif (!is_numeric($amount) || (float)$amount <= 0) {

        $message = "Please enter a valid amount.";

        $message_type = "error";

    } else {

        $date_object = DateTime::createFromFormat('Y-m-d', $expense_date);

        if (!$date_object || $date_object->format('Y-m-d') !== $expense_date) {

            $message = "Please select a valid expense date.";

            $message_type = "error";

        } else {

            $amount = number_format((float)$amount, 2, '.', '');

            $stmt = $con->prepare(

                "INSERT INTO expenses

                (user_id, expense_name, category, amount, expense_date, payment_method, spending_from_income, description)

                VALUES (?, ?, ?, ?, ?, ?, ?, ?)"

            );

            if (!$stmt) {

                $message = "Database error: " . $con->error;

                $message_type = "error";

            } else {

                $stmt->bind_param(

                    "issdssss",

                    $user_id,

                    $expense_name,

                    $category,

                    $amount,

                    $expense_date,

                    $payment_method,

                    $spending_from_income,

                    $description

                );

                if ($stmt->execute()) {

                    $stmt->close();

                    header("Location: expenses.php?added=1");

                    exit();

                }

                $message = "Unable to save expense: " . $stmt->error;

                $message_type = "error";

                $stmt->close();

            }

        }

    }

}

/* =========================================================

   DELETE EXPENSE

\========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_expense'])) {

    $expense_id = (int)($_POST['expense_id'] ?? 0);

    if ($expense_id > 0) {

        $stmt = $con->prepare(

            "DELETE FROM expenses

             WHERE id = ? AND user_id = ?"

        );

        $stmt->bind_param("ii", $expense_id, $user_id);

        $stmt->execute();

        $stmt->close();

        header("Location: expenses.php?deleted=1");

        exit();

    }

}

/* =========================================================

   DELETE INCOME

\========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_income'])) {

    $income_id = (int)($_POST['income_id'] ?? 0);

    if ($income_id > 0) {

        $stmt = $con->prepare(

            "DELETE FROM income

             WHERE id = ? AND user_id = ?"

        );

        $stmt->bind_param("ii", $income_id, $user_id);

        $stmt->execute();

        $stmt->close();

        header("Location: expenses.php?income_deleted=1");

        exit();

    }

}

/* =========================================================

   SUCCESS MESSAGES

\========================================================= */

if (isset($_GET['added'])) {

    $message = "Expense added successfully.";

    $message_type = "success";

}

if (isset($_GET['deleted'])) {

    $message = "Expense deleted successfully.";

    $message_type = "success";

}

if (isset($_GET['income_deleted'])) {

    $message = "Income deleted successfully.";

    $message_type = "success";

}

/* =========================================================

   TOTAL INCOME

\========================================================= */

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

   TOTAL EXPENSES

\========================================================= */

/* =========================================================

   EXPENSE PERIOD FILTER

\========================================================= */

$period = $_GET['period'] ?? 'monthly';

$selected_date = $_GET['date'] ?? date('Y-m-d');

if (!in_array($period, ['weekly', 'monthly', 'yearly'], true)) {

    $period = 'monthly';

}

$selected_object = DateTime::createFromFormat('Y-m-d', $selected_date);

if (!$selected_object || $selected_object->format('Y-m-d') !== $selected_date) {

    $selected_object = new DateTime();

    $selected_date = $selected_object->format('Y-m-d');

}

if ($period === 'weekly') {

    $period_start = clone $selected_object;

    $period_start->modify('monday this week');

    $period_end = clone $period_start;

    $period_end->modify('+6 days');

    $period_label = $period_start->format('d M Y') . ' - ' . $period_end->format('d M Y');

} elseif ($period === 'yearly') {

    $period_start = new DateTime($selected_object->format('Y') . '-01-01');

    $period_end = new DateTime($selected_object->format('Y') . '-12-31');

    $period_label = $selected_object->format('Y');

} else {

    $period_start = new DateTime($selected_object->format('Y-m-01'));

    $period_end = clone $period_start;

    $period_end->modify('last day of this month');

    $period_label = $selected_object->format('F Y');

}

$period_start_sql = $period_start->format('Y-m-d');

$period_end_sql = $period_end->format('Y-m-d');

/* =========================================================

   PERIOD INCOME

\========================================================= */

$period_income = 0;

$stmt = $con->prepare(

    "SELECT COALESCE(SUM(amount), 0)

     FROM income

     WHERE user_id = ?

       AND income_date BETWEEN ? AND ?"

);

$stmt->bind_param("iss", $user_id, $period_start_sql, $period_end_sql);

$stmt->execute();

$stmt->bind_result($period_income);

$stmt->fetch();

$stmt->close();

/* =========================================================

   PERIOD EXPENSES

\========================================================= */

$period_expenses = 0;

$stmt = $con->prepare(

    "SELECT COALESCE(SUM(amount), 0)

     FROM expenses

     WHERE user_id = ?

       AND expense_date BETWEEN ? AND ?"

);

$stmt->bind_param("iss", $user_id, $period_start_sql, $period_end_sql);

$stmt->execute();

$stmt->bind_result($period_expenses);

$stmt->fetch();

$stmt->close();

$remaining_balance = (float)$period_income - (float)$period_expenses;

/* =========================================================

   EXPENSE HISTORY FOR SELECTED PERIOD

\========================================================= */

$history = [];

/* =========================================================

   EXPENSE SEARCH

   Search by expense name, category, amount, date or payment method.

\========================================================= */

$search_term = trim($_GET['search'] ?? '');

$sql = "

    SELECT

        e.id,

        e.expense_date AS record_date,

        e.expense_name AS description,

        e.category,

        e.amount,

        e.payment_method,

        COALESCE(e.spending_from_income, i.income_source, 'Not entered') AS funding_source

    FROM expenses e

    LEFT JOIN income i

        ON i.id = e.income_id

       AND i.user_id = e.user_id

    WHERE e.user_id = ?

      AND e.expense_date BETWEEN ? AND ?

";

if ($search_term !== '') {

    $sql .= "

      AND (

            e.expense_name LIKE CONCAT('%', ?, '%')

            OR e.category LIKE CONCAT('%', ?, '%')

            OR CAST(e.amount AS CHAR) LIKE CONCAT('%', ?, '%')

            OR e.expense_date LIKE CONCAT('%', ?, '%')

            OR e.payment_method LIKE CONCAT('%', ?, '%')

          )

    ";

}

$sql .= " ORDER BY e.expense_date DESC, e.id DESC";

$stmt = $con->prepare($sql);

if ($search_term !== '') {

    $stmt->bind_param(

        "isssssss",

        $user_id,

        $period_start_sql,

        $period_end_sql,

        $search_term,

        $search_term,

        $search_term,

        $search_term,

        $search_term

    );

} else {

    $stmt->bind_param("iss", $user_id, $period_start_sql, $period_end_sql);

}

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $history[] = $row;

}

$stmt->close();

$con->close();

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Expenses</title>

    <style>

        :root {

            --background: #F4EFE5;

            --card: #FFFDF8;

            --header: #EFE9DC;

            --border: #DDD4C4;

            --olive: #5A673C;

            --olive-dark: #374621;

            --olive-light: #E4E9D9;

            --text: #1F241A;

            --muted: #77776F;

            --income: #5A673C;

            --expense: #A64F3E;

        }

        * {

            box-sizing: border-box;

            margin: 0;

            padding: 0;

        }

        body {

            font-family: Arial, Helvetica, sans-serif;

            background: var(--background);

            color: var(--text);

            min-height: 100vh;

        }

        .layout {

            display: flex;

            min-height: 100vh;

            width: 100%;

        }

        .sidebar {

            width: 250px;

            min-width: 250px;

            height: 100vh;

            position: fixed;

            left: 0;

            top: 0;

            background: #374621;

            color: #FFFFFF;

            padding: 28px 18px;

            overflow: hidden;

            z-index: 1000;

        }

        .sidebar-brand {

            padding: 0 12px;

            margin-bottom: 30px;

            color: #E4E9D9;

            font-size: 14px;

            font-weight: 800;

            letter-spacing: 1.3px;

            text-transform: uppercase;

        }

        .nav-list {

            list-style: none;

            display: flex;

            flex-direction: column;

            gap: 7px;

        }

        .nav-item a {

            display: flex;

            align-items: center;

            gap: 12px;

            width: 100%;

            padding: 13px 14px;

            border-radius: 9px;

            color: #E7EBDD;

            text-decoration: none;

            font-size: 15px;

            font-weight: 600;

            transition: background 0.2s ease, color 0.2s ease;

        }

        .nav-item a:hover,

        .nav-item.active a {

            background: #5A673C;

            color: #FFFFFF;

        }

        .sidebar-icon {

            width: 22px;

            text-align: center;

            font-size: 17px;

            flex-shrink: 0;

        }

        .content-area {

            width: 100%;

            margin-left: 250px;

            min-height: 100vh;

        }

        .page {

            width: 100%;

            padding: 18px 22px 35px;

        }

        .topbar {

            display: flex;

            justify-content: space-between;

            align-items: flex-start;

            margin-bottom: 25px;

        }

        .title-area h1 {

            font-size: 27px;

            line-height: 1.1;

            color: #26311D;

            font-weight: 800;

        }

        .title-area p {

            margin-top: 5px;

            color: #73766E;

            font-size: 14px;

        }

        .add-button {

            border: 0;

            background: var(--olive);

            color: #fff;

            padding: 10px 18px;

            border-radius: 9px;

            font-size: 14px;

            font-weight: 700;

            cursor: pointer;

            min-width: 169px;

        }

        .add-button:hover {

            background: var(--olive-dark);

        }

        .summary-grid {

            display: grid;

            grid-template-columns: repeat(3, 1fr);

            gap: 16px;

            margin-bottom: 22px;

        }

        .summary-card {

            min-height: 88px;

            background: var(--card);

            border: 1px solid var(--border);

            border-radius: 12px;

            display: flex;

            align-items: center;

            padding: 15px 18px;

        }

        .summary-icon {

            width: 42px;

            height: 42px;

            border-radius: 9px;

            background: var(--olive-light);

            display: flex;

            align-items: center;

            justify-content: center;

            color: var(--olive);

            font-size: 22px;

            font-weight: 500;

            margin-right: 13px;

            flex-shrink: 0;

        }

        .summary-label {

            color: #73766E;

            font-size: 12px;

            margin-bottom: 5px;

        }

        .summary-value {

            font-size: 20px;

            font-weight: 800;

            color: #171C13;

        }

        .history-card {

            background: var(--card);

            border: 1px solid var(--border);

            border-radius: 14px;

            padding: 18px 20px 20px;

        }

        .history-card h2 {

            color: #182016;

            font-size: 19px;

            margin-bottom: 0;

        }

        .table-wrap {

            overflow-x: auto;

        }

        table {

            width: 100%;

            border-collapse: collapse;

            min-width: 900px;

        }

        thead th {

            background: var(--header);

            color: #586438;

            text-align: left;

            font-size: 12px;

            font-weight: 700;

            padding: 12px 14px;

        }

        thead th:first-child {

            border-top-left-radius: 0;

        }

        thead th:last-child {

            border-top-right-radius: 0;

        }

        tbody td {

            padding: 13px 14px;

            font-size: 13px;

            border-bottom: 1px solid #E1D9CC;

            vertical-align: middle;

        }

        tbody tr:last-child td {

            border-bottom: none;

        }

        .amount {

            font-weight: 700;

            white-space: nowrap;

        }

        .income-amount {

            color: var(--income);

        }

        .expense-amount {

            color: var(--expense);

        }

        .actions {

            display: flex;

            gap: 7px;

        }

        .action-button {

            width: 32px;

            height: 32px;

            border: 0;

            border-radius: 7px;

            background: #EEE9DE;

            color: #536239;

            cursor: pointer;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 15px;

        }

        .action-button:hover {

            background: #E2DDCF;

        }

        .delete-button {

            color: #C94C4C;

            background: #F8E8E8;

        }

        .delete-button:hover {

            background: #F2D7D7;

            color: #A83B3B;

        }

        .empty-state {

            text-align: center;

            padding: 45px 20px;

            color: var(--muted);

            font-size: 16px;

        }

        .form-card {

            display: none;

            background: var(--card);

            border: 1px solid var(--border);

            border-radius: 16px;

            padding: 24px;

            margin-bottom: 25px;

        }

        .form-card.show {

            display: block;

        }

        .form-title {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 20px;

        }

        .form-title h2 {

            color: #26311D;

            font-size: 21px;

        }

        .close-button {

            border: 0;

            background: transparent;

            color: #73766E;

            font-size: 28px;

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

            font-size: 14px;

            font-weight: 700;

            color: #33392D;

        }

        .form-group input,

        .form-group select,

        .form-group textarea {

            width: 100%;

            border: 1px solid var(--border);

            border-radius: 8px;

            padding: 12px;

            font-size: 15px;

            background: #fff;

            color: var(--text);

            outline: none;

        }

        .form-group input:focus,

        .form-group select:focus,

        .form-group textarea:focus {

            border-color: var(--olive);

        }

        .form-group textarea {

            min-height: 90px;

            resize: vertical;

        }

        .form-actions {

            display: flex;

            justify-content: flex-end;

            margin-top: 20px;

        }

        .save-button {

            border: 0;

            border-radius: 8px;

            background: var(--olive);

            color: #fff;

            padding: 12px 23px;

            font-size: 15px;

            font-weight: 700;

            cursor: pointer;

        }

        .message {

            padding: 12px 15px;

            border-radius: 8px;

            margin-bottom: 18px;

            font-size: 14px;

            font-weight: 600;

        }

        .message.success {

            color: #3E7047;

            background: #E6EFE3;

            border: 1px solid #C8D8C1;

        }

        .message.error {

            color: #A3483E;

            background: #F5E5E1;

            border: 1px solid #E1C4BD;

        }

        @media (max-width: 950px) {

            .summary-grid {

                grid-template-columns: 1fr;

            }

        }

        @media (max-width: 650px) {

                        .expense-search {

                width: 100%;

                min-width: 0;

            }

            .expense-search input {

                min-width: 0;

            }

.page {

                padding: 18px 12px 30px;

            }

            .topbar {

                flex-direction: column;

                gap: 18px;

            }

            .add-button {

                width: 100%;

            }

            .form-grid {

                grid-template-columns: 1fr;

            }

            .form-group.full {

                grid-column: auto;

            }

            .title-area h1 {

                font-size: 27px;

            }

            .title-area p {

                font-size: 16px;

            }

        }

        /* Sidebar footer: Profile and Logout stay at the bottom */

        .sidebar {

            display: flex;

            flex-direction: column;

            min-height: 100vh;

        }

        .sidebar-footer {

            margin-top: auto;

            padding-top: 18px;

        }

        .sidebar-footer .nav-list {

            margin: 0;

        }

        .sidebar-footer .logout a {

            color: #b84b45;

        }

        .sidebar-footer .logout a:hover {

            color: #ffffff;

            background: #b84b45;

        }

        /* Profile is now available from the sidebar */

        .user-profile {

            display: none !important;

        }

        .period-filter {

            background: var(--card);

            border: 1px solid var(--border);

            border-radius: 12px;

            padding: 14px 16px;

            margin-bottom: 18px;

            display: flex;

            align-items: flex-end;

            gap: 12px;

            flex-wrap: wrap;

        }

                .expense-search {

            display: flex;

            align-items: center;

            gap: 8px;

            flex: 1;

            min-width: 260px;

        }

        .expense-search input {

            width: 100%;

            border: 1px solid var(--border);

            border-radius: 8px;

            padding: 9px 11px;

            background: #FFFFFF;

            color: var(--text);

            font-size: 13px;

            outline: none;

        }

        .expense-search input:focus {

            border-color: var(--olive);

            box-shadow: 0 0 0 2px rgba(90, 103, 60, 0.08);

        }

        .expense-search button,

        .expense-search .clear-search {

            border: 0;

            border-radius: 8px;

            padding: 9px 13px;

            font-size: 12px;

            font-weight: 700;

            cursor: pointer;

            text-decoration: none;

            white-space: nowrap;

        }

        .expense-search button {

            background: var(--olive);

            color: #FFFFFF;

        }

        .expense-search button:hover {

            background: var(--olive-dark);

        }

        .expense-search .clear-search {

            background: #EEE9DE;

            color: #536239;

        }

        .expense-search .clear-search:hover {

            background: #E2DDCF;

        }

        .search-result-note {

            margin: -4px 0 14px;

            color: var(--muted);

            font-size: 12px;

        }

.period-options {

            display: flex;

            gap: 6px;

        }

        .period-option {

            text-decoration: none;

            padding: 8px 13px;

            border-radius: 8px;

            background: #EEE9DE;

            color: #536239;

            font-size: 12px;

            font-weight: 700;

        }

        .period-option.active {

            background: var(--olive);

            color: #FFFFFF;

        }

        .period-date-group {

            display: flex;

            flex-direction: column;

            gap: 5px;

        }

        .period-date-group label {

            font-size: 11px;

            color: var(--muted);

            font-weight: 700;

        }

        .period-date-group input {

            border: 1px solid var(--border);

            border-radius: 8px;

            padding: 8px 10px;

            background: #FFFFFF;

            color: var(--text);

            font-size: 12px;

            outline: none;

        }

        .period-date-group input:focus {

            border-color: var(--olive);

        }

        .period-label {

            margin-left: auto;

            color: var(--olive-dark);

            font-size: 12px;

            font-weight: 700;

            padding-bottom: 8px;

        }

        .summary-period {

            font-size: 11px;

            color: var(--muted);

            margin-top: 2px;

        }

        @media (max-width: 650px) {

            .period-filter {

                align-items: stretch;

                flex-direction: column;

            }

            .period-options {

                width: 100%;

            }

            .period-option {

                flex: 1;

                text-align: center;

            }

            .period-label {

                margin-left: 0;

            }

        }

</style>

</head>

<body>

<div class="layout">

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

            <li class="nav-item">

                <a href="income.php">

                    Income

                </a>

            </li>

            <li class="nav-item active">

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

    <div class="content-area">

<div class="page">

    <div class="topbar">

        <div class="title-area">

            <h1>Expenses</h1>

            <p>Track your income and spending.</p>

        </div>

        <button

            type="button"

            class="add-button"

            onclick="openExpenseForm()"

        >

            + Add Expense

        </button>

    </div>

    <?php if ($message !== "") : ?>

        <div class="message <?php echo htmlspecialchars($message_type); ?>">

            <?php echo htmlspecialchars($message); ?>

        </div>

    <?php endif; ?>

        <section class="period-filter">

        <div class="period-options">

            <a href="expenses.php?period=weekly&date=<?php echo htmlspecialchars($selected_date); ?>&search=<?php echo urlencode($search_term); ?>"

               class="period-option <?php echo $period === 'weekly' ? 'active' : ''; ?>">

                Weekly

            </a>

            <a href="expenses.php?period=monthly&date=<?php echo htmlspecialchars($selected_date); ?>&search=<?php echo urlencode($search_term); ?>"

               class="period-option <?php echo $period === 'monthly' ? 'active' : ''; ?>">

                Monthly

            </a>

            <a href="expenses.php?period=yearly&date=<?php echo htmlspecialchars($selected_date); ?>&search=<?php echo urlencode($search_term); ?>"

               class="period-option <?php echo $period === 'yearly' ? 'active' : ''; ?>">

                Yearly

            </a>

        </div>

        <form method="get" class="period-date-group">

            <label for="expense_period_date">Select Date</label>

            <input

                type="date"

                id="expense_period_date"

                name="date"

                value="<?php echo htmlspecialchars($selected_date); ?>"

                onchange="this.form.submit()"

            >

            <input

                type="hidden"

                name="period"

                value="<?php echo htmlspecialchars($period); ?>"

            >

            <input

                type="hidden"

                name="search"

                value="<?php echo htmlspecialchars($search_term); ?>"

            >

        </form>

        <div class="period-label">

            Showing: <?php echo htmlspecialchars($period_label); ?>

        </div>

    </section>

<section class="summary-grid">

        <div class="summary-card">

            <div class="summary-icon">↓</div>

         <div>

            <div class="summary-label">Total Income<div class="summary-period"><?php echo htmlspecialchars($period_label); ?></div></div>

                <div class="summary-value">

                    ₹<?php echo number_format((float)$period_income, 0); ?>

                 </div>

            </div>

        </div>

        <div class="summary-card">

            <div class="summary-icon">↑</div>

            <div>

                <div class="summary-label">Total Expenses<div class="summary-period"><?php echo htmlspecialchars($period_label); ?></div></div>

                <div class="summary-value">

                    ₹<?php echo number_format((float)$period_expenses, 0); ?>

                </div>

            </div>

        </div>

        <div class="summary-card">

            <div class="summary-icon">▣</div>

            <div>

                <div class="summary-label">Remaining Balance<div class="summary-period"><?php echo htmlspecialchars($period_label); ?></div></div>

                <div class="summary-value">

                    ₹<?php echo number_format($remaining_balance, 0); ?>

                </div>

            </div>

        </div>

    </section>

    <section

        class="form-card"

        id="addExpenseCard"

    >

        <div class="form-title">

            <h2>Add Expense</h2>

            <button

                type="button"

                class="close-button"

                onclick="closeExpenseForm()"

            >

                ×

            </button>

        </div>

        <form method="post" action="expenses.php">

            <div class="form-grid">

                <div class="form-group">

                    <label for="expense_name">

                        Expense Name

                    </label>

                    <input

                        type="text"

                        id="expense_name"

                        name="expense_name"

                        placeholder="e.g. Grocery Shopping"

                        value="<?php echo htmlspecialchars($_POST['expense_name'] ?? ''); ?>"

                        required

                    >

                </div>

                <div class="form-group">

                    <label for="category">

                        Category

                    </label>

                    <select

                        id="category"

                        name="category"

                        required

                    >

                        <option value="">

                            Select Category

                        </option>

                        <?php

                        $categories = [

                                "Food & Groceries",

                                "Housing & Rent",

                                "Utilities & Bills",

                                "Transportation",

                                "Healthcare",

                                "Education",

                                "Shopping",

                                "Entertainment",

                                "Personal Care",

                                "Travel",

                                "Subscriptions",

                                "Household",

                                "Insurance",

                                "Gifts & Donations",

                                "Other"

                            ];

                        foreach ($categories as $item):

                        ?>

                            <option

                                value="<?php echo htmlspecialchars($item); ?>"

                                <?php echo (($_POST['category'] ?? '') === $item) ? 'selected' : ''; ?>

                            >

                                <?php echo htmlspecialchars($item); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

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

                    <label for="expense_date">

                        Date

                    </label>

                    <input

                        type="date"

                        id="expense_date"

                        name="expense_date"

                        value="<?php echo htmlspecialchars($_POST['expense_date'] ?? date('Y-m-d')); ?>"

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

                            Select Payment Method

                        </option>

                        <?php

                        $methods = [

                            "Bank Transfer",

                            "Cash",

                            "UPI",

                            "Card",

                            "Other"

                        ];

                        foreach ($methods as $method):

                        ?>

                            <option

                                value="<?php echo htmlspecialchars($method); ?>"

                                <?php echo (($_POST['payment_method'] ?? '') === $method) ? 'selected' : ''; ?>

                            >

                                <?php echo htmlspecialchars($method); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="form-group full">

                    <label for="description">

                        Description

                    </label>

                    <textarea

                        id="description"

                        name="description"

                        placeholder="Add additional details..."

                    ><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>

                </div>

            </div>

            <div class="form-actions">

                <button

                    type="submit"

                    name="add_expense"

                    class="save-button"

                >

                    Save Expense

                </button>

            </div>

        </form>

    </section>

    <section class="history-card">

        <h2>

            Expense History

        </h2>

        <form method="get" class="expense-search" style="margin-bottom:14px; width:100%; max-width:100%;">

            <input

                type="text"

                name="search"

                value="<?php echo htmlspecialchars($search_term); ?>"

                placeholder="Search by expense name, category, amount, date or payment method"

                autocomplete="off"

            >

            <input type="hidden" name="period" value="<?php echo htmlspecialchars($period); ?>">

            <input type="hidden" name="date" value="<?php echo htmlspecialchars($selected_date); ?>">

            <button type="submit">Search</button>

            <?php if ($search_term !== '') : ?>

                <a

                    href="expenses.php?period=<?php echo urlencode($period); ?>&date=<?php echo urlencode($selected_date); ?>"

                    class="clear-search"

                >Clear</a>

            <?php endif; ?>

        </form>

        <?php if ($search_term !== '') : ?>

            <div class="search-result-note">

                Showing results matching "<?php echo htmlspecialchars($search_term); ?>" within <?php echo htmlspecialchars($period_label); ?>.

            </div>

        <?php endif; ?>

        <div class="table-wrap">

            <?php if (empty($history)) : ?>

                <div class="empty-state">

                    No expenses found.

                </div>

            <?php else : ?>

                <table>

                    <thead>

                        <tr>

                            <th>Date</th>

                            <th>Description</th>

                            <th>Category</th>

                            <th>Amount</th>

                            <th>Action</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($history as $record) : ?>

                        <tr>

                            <td>

                                <?php

                                echo htmlspecialchars(

                                    date("Y-m-d", strtotime($record['record_date']))

                                );

                                ?>

                            </td>

                            <td>

                                <?php

                                echo htmlspecialchars(

                                    $record['description']

                                );

                                ?>

                            </td>

                            <td>

                                <?php

                                echo htmlspecialchars(

                                    $record['category']

                                );

                                ?>

                            </td>

                            <td class="amount expense-amount">

                                - ₹<?php echo number_format((float)$record['amount'], 0); ?>

                            </td>

                            <td>

                                <div class="actions">

                                    <button

                                        type="button"

                                        class="action-button"

                                        title="Edit"

                                        onclick="alert('Edit will be added next.')"

                                    >

                                        ✎

                                    </button>

                                    <form

                                        method="post"

                                        onsubmit="return confirm('Are you sure you want to delete this expense?');"

                                    >

                                        <input

                                            type="hidden"

                                            name="expense_id"

                                            value="<?php echo (int)$record['id']; ?>"

                                        >

                                        <button

                                            type="submit"

                                            name="delete_expense"

                                            class="action-button delete-button"

                                            title="Delete"

                                        >

                                            🗑

                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            <?php endif; ?>

        </div>

    </section>

</div>

    </div>

</div>

<script>

    function openExpenseForm() {

        const form = document.getElementById("addExpenseCard");

        form.classList.add("show");

        form.scrollIntoView({

            behavior: "smooth",

            block: "start"

        });

    }

    function closeExpenseForm() {

        document

            .getElementById("addExpenseCard")

            .classList.remove("show");

    }

</script>

</body>

</html>
