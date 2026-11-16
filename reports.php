<?php
session_start();

if (!isset($_SESSION['ID']) || !is_numeric($_SESSION['ID']) || (int)$_SESSION['ID'] <= 0) {
    header('Location: login.php');
    exit();
}

$user_id = (int)$_SESSION['ID'];

$con = new mysqli('localhost', 'root', '', 'calender');

if ($con->connect_error) {
    die('Database connection failed: ' . $con->connect_error);
}

$con->set_charset('utf8mb4');

$sidebar_user_name = 'Profile';

$stmt = $con->prepare('SELECT name FROM user_info WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $sidebar_user_name = trim((string)($row['name'] ?? '')) ?: 'Profile';
}

$stmt->close();

function clean($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| Report Date / Period
|--------------------------------------------------------------------------
| The selected date controls the report:
| Weekly  -> Monday to Sunday
| Monthly -> 1st to last day of selected month
| Yearly  -> 1st January to 31st December
|--------------------------------------------------------------------------
*/

$period = $_GET['period'] ?? 'monthly';

if (!in_array($period, ['weekly', 'monthly', 'yearly'], true)) {
    $period = 'monthly';
}

$selected_date = $_GET['date'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    $selected_date = date('Y-m-d');
}

$date_object = DateTime::createFromFormat('Y-m-d', $selected_date);

if (!$date_object || $date_object->format('Y-m-d') !== $selected_date) {
    $selected_date = date('Y-m-d');
    $date_object = new DateTime($selected_date);
}

$start_date = '';
$end_date = '';
$display_range = '';

if ($period === 'weekly') {
    $start_object = clone $date_object;
    $start_object->modify('monday this week');

    $end_object = clone $start_object;
    $end_object->modify('+6 days');

    $start_date = $start_object->format('Y-m-d');
    $end_date = $end_object->format('Y-m-d');

    $display_range = $start_object->format('d M Y') . ' - ' . $end_object->format('d M Y');
} elseif ($period === 'monthly') {
    $start_object = clone $date_object;
    $start_object->modify('first day of this month');

    $end_object = clone $date_object;
    $end_object->modify('last day of this month');

    $start_date = $start_object->format('Y-m-d');
    $end_date = $end_object->format('Y-m-d');

    $display_range = $start_object->format('d M Y') . ' - ' . $end_object->format('d M Y');
} else {
    $start_object = new DateTime($date_object->format('Y') . '-01-01');
    $end_object = new DateTime($date_object->format('Y') . '-12-31');

    $start_date = $start_object->format('Y-m-d');
    $end_date = $end_object->format('Y-m-d');

    $display_range = $start_object->format('d M Y') . ' - ' . $end_object->format('d M Y');
}

/*
|--------------------------------------------------------------------------
| Financial Data
|--------------------------------------------------------------------------
*/

$income = 0;
$expenses = 0;
$income_count = 0;
$expense_count = 0;

$stmt = $con->prepare(
    'SELECT COALESCE(SUM(amount), 0), COUNT(*)
     FROM income
     WHERE user_id = ? AND income_date BETWEEN ? AND ?'
);
$stmt->bind_param('iss', $user_id, $start_date, $end_date);
$stmt->execute();
$stmt->bind_result($income, $income_count);
$stmt->fetch();
$stmt->close();

$stmt = $con->prepare(
    'SELECT COALESCE(SUM(amount), 0), COUNT(*)
     FROM expenses
     WHERE user_id = ? AND expense_date BETWEEN ? AND ?'
);
$stmt->bind_param('iss', $user_id, $start_date, $end_date);
$stmt->execute();
$stmt->bind_result($expenses, $expense_count);
$stmt->fetch();
$stmt->close();

/*
|--------------------------------------------------------------------------
| Tasks and Events
|--------------------------------------------------------------------------
*/

$completed = 0;
$pending = 0;
$event_count = 0;

$stmt = $con->prepare(
    "SELECT
        COALESCE(SUM(status = 'Completed'), 0),
        COALESCE(SUM(status <> 'Completed' OR status IS NULL), 0)
     FROM todo_list
     WHERE user_id = ? AND due_date BETWEEN ? AND ?"
);
$stmt->bind_param('iss', $user_id, $start_date, $end_date);
$stmt->execute();
$stmt->bind_result($completed, $pending);
$stmt->fetch();
$stmt->close();

$stmt = $con->prepare(
    'SELECT COUNT(*)
     FROM events
     WHERE user_id = ? AND event_date BETWEEN ? AND ?'
);
$stmt->bind_param('iss', $user_id, $start_date, $end_date);
$stmt->execute();
$stmt->bind_result($event_count);
$stmt->fetch();
$stmt->close();

/*
|--------------------------------------------------------------------------
| Expense Category Breakdown
|--------------------------------------------------------------------------
*/

$categories = [];

$stmt = $con->prepare(
    'SELECT category, COALESCE(SUM(amount), 0) AS total
     FROM expenses
     WHERE user_id = ? AND expense_date BETWEEN ? AND ?
     GROUP BY category
     ORDER BY total DESC'
);
$stmt->bind_param('iss', $user_id, $start_date, $end_date);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

$stmt->close();

/*
|--------------------------------------------------------------------------
| 50/30/20 Budget Analysis
| 50% needs, 30% wants, 20% savings.
| Buckets come from the expense_budget_categories table (per user).
| The map below is the fallback for categories with no row there; anything
| still unknown counts as "wants" so totals always add up.
|--------------------------------------------------------------------------
*/
$budget_map = [
    'Food & Groceries'  => 'needs',
    'Housing & Rent'    => 'needs',
    'Utilities & Bills' => 'needs',
    'Transportation'    => 'needs',
    'Healthcare'        => 'needs',
    'Education'         => 'needs',
    'Insurance'         => 'needs',
    'Household'         => 'needs',
    'Shopping'          => 'wants',
    'Entertainment'     => 'wants',
    'Personal Care'     => 'wants',
    'Travel'            => 'wants',
    'Subscriptions'     => 'wants',
    'Gifts & Donations' => 'wants',
    'Other'             => 'wants',
];

$needs_spending = 0.0;
$wants_spending = 0.0;

// Per-user mapping saved in the expense_budget_categories table.
// It takes priority; the default map above is only used for categories
// that have no row in the table.
$default_map = array_change_key_case($budget_map, CASE_LOWER);
$user_budget_map = [];

try {
    $map_stmt = $con->prepare(
        'SELECT category, budget_type
         FROM expense_budget_categories
         WHERE user_id = ?'
    );
    $map_stmt->bind_param('i', $user_id);
    $map_stmt->execute();
    $map_result = $map_stmt->get_result();

    while ($map_row = $map_result->fetch_assoc()) {
        $map_type = strtolower(trim((string)$map_row['budget_type']));
        if ($map_type === 'needs' || $map_type === 'wants') {
            $user_budget_map[strtolower(trim((string)$map_row['category']))] = $map_type;
        }
    }

    $map_stmt->close();
} catch (Throwable $e) {
    // Table missing or unreadable: fall back to the default map.
    $user_budget_map = [];
}

foreach ($categories as $budget_category) {
    $category_name   = strtolower(trim((string)($budget_category['category'] ?? '')));
    $category_amount = (float)$budget_category['total'];

    $bucket = $user_budget_map[$category_name] ?? $default_map[$category_name] ?? 'wants';

    if ($bucket === 'needs') {
        $needs_spending += $category_amount;
    } else {
        $wants_spending += $category_amount;
    }
}

$income_f       = (float)$income;
$needs_limit    = $income_f * 0.50;
$wants_limit    = $income_f * 0.30;
$savings_target = $income_f * 0.20;
$actual_savings = max(0, $income_f - (float)$expenses);

$needs_percent   = $needs_limit > 0    ? ($needs_spending / $needs_limit) * 100 : 0;
$wants_percent   = $wants_limit > 0    ? ($wants_spending / $wants_limit) * 100 : 0;
$savings_percent = $savings_target > 0 ? ($actual_savings / $savings_target) * 100 : 0;

// Spending buckets: staying under the limit is good
function budget_spend_status($spent, $limit, $label)
{
    if ($limit <= 0) {
        return 'Add income to calculate your ' . $label . ' budget.';
    }

    if ($spent <= $limit) {
        return '₹' . number_format($limit - $spent, 2) . ' left in your ' . $label . ' budget.';
    }

    return '₹' . number_format($spent - $limit, 2) . ' over your ' . $label . ' budget.';
}

$con->close();

/*
|--------------------------------------------------------------------------
| Calculations
|--------------------------------------------------------------------------
*/

$income = (float)$income;
$expenses = (float)$expenses;

$balance = $income - $expenses;
$saving_rate = $income > 0 ? ($balance / $income) * 100 : 0;

$task_total = (int)$completed + (int)$pending;
$task_rate = $task_total > 0 ? ((int)$completed / $task_total) * 100 : 0;

$expense_ratio = $income > 0 ? ($expenses / $income) * 100 : 0;

$sidebar_user_name = clean($sidebar_user_name);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | MyCalendar</title>

    <style>
        :root {
            --bg: #F4EFEA;
            --card: #FAF7F2;
            --border: #E5DDD1;
            --dark: #374621;
            --primary: #556B2F;
            --light: #70843D;
            --text: #2A2C24;
            --muted: #727368;
            --white: #FFFFFF;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: "Segoe UI", Arial, sans-serif;
        }

        body {
            background: var(--bg);
            color: var(--text);
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background-color: var(--dark);
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

        .nav-item a:hover,
        .nav-item.active a {
            background-color: var(--primary);
            color: var(--white);
        }

        .sidebar-footer {
            margin-top: auto;
            padding-top: 18px;
            border-top: 1px solid rgba(255, 255, 255, 0.16);
            flex-shrink: 0;
        }

        .sidebar-footer .logout a {
            color: #F2D6D6;
        }

        .sidebar-footer .logout a:hover,
        .sidebar-footer .logout.active a {
            background-color: #7A3D3D;
            color: #FFFFFF;
        }

        /* Main */
        .main-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow-y: auto;
        }

        header {
            background: var(--card);
            border-bottom: 1px solid var(--border);
            padding: 20px 36px;
        }

        .header-left h1 {
            font-size: 22px;
            color: var(--dark);
        }

        .header-left p {
            font-size: 13px;
            color: var(--muted);
            margin-top: 3px;
        }

        .content {
            padding: 28px 36px 40px;
        }

        /* Report selector */
        .report-selector {
            background: #FFFFFF;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
            min-height: 86px;
        }

        .period-buttons {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }

        .period-button {
            border: none;
            background: #EEEAE2;
            color: var(--dark);
            height: 38px;
            padding: 0 18px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .period-button:hover {
            background: #E2DDCF;
        }

        .period-button.active {
            background: var(--primary);
            color: #FFFFFF;
        }

        .date-control {
            display: flex;
            flex-direction: column;
            gap: 5px;
            margin-left: 4px;
        }

        .date-control label {
            font-size: 12px;
            font-weight: 700;
            color: var(--muted);
        }

        .date-control input {
            height: 42px;
            width: 154px;
            border: 1px solid #D8D1C6;
            border-radius: 10px;
            padding: 0 12px;
            color: var(--text);
            background: #FFFFFF;
            font-size: 13px;
            outline: none;
        }

        .date-control input:focus {
            border-color: var(--primary);
        }

        .range-display {
            margin-left: auto;
            white-space: nowrap;
            font-size: 14px;
            font-weight: 700;
            color: #20251A;
            padding-left: 20px;
        }

        /* Summary */
        .summary {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }

        .card {
            background: #FFFFFF;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 4px 16px rgba(55, 70, 33, 0.05);
        }

        .card .label {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 8px;
        }

        .card .value {
            font-size: 25px;
            font-weight: 750;
            color: var(--dark);
        }

        .grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 20px;
        }

        .card h2 {
            font-size: 18px;
            color: var(--dark);
            margin-bottom: 16px;
        }

        .row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #EEEAE2;
            gap: 20px;
        }

        .row:last-child {
            border: 0;
        }

        .row span {
            color: var(--muted);
        }

        .bar {
            margin: 12px 0;
        }

        .barhead {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .track {
            height: 9px;
            background: #ECEEE7;
            border-radius: 10px;
            overflow: hidden;
        }

        .fill {
            height: 100%;
            background: var(--primary);
            border-radius: 10px;
        }

        .empty {
            font-size: 14px;
            color: var(--muted);
        }

        @media (max-width: 1000px) {
            .summary {
                grid-template-columns: repeat(2, 1fr);
            }

            .grid {
                grid-template-columns: 1fr;
            }

            .range-display {
                width: 100%;
                margin-left: 0;
                padding-left: 0;
                margin-top: 4px;
            }

            .report-selector {
                flex-wrap: wrap;
            }
        }

        @media (max-width: 700px) {
            body {
                display: block;
            }

            .sidebar {
                width: 100%;
            }

            .main-wrapper {
                height: auto;
            }

            .content {
                padding: 20px;
            }

            .summary {
                grid-template-columns: 1fr;
            }

            .report-selector {
                align-items: stretch;
                flex-direction: column;
            }

            .period-buttons {
                width: 100%;
            }

            .period-button {
                flex: 1;
            }

            .date-control {
                width: 100%;
                margin-left: 0;
            }

            .date-control input {
                width: 100%;
            }

            .range-display {
                text-align: left;
                white-space: normal;
            }
        }

        /* 50/30/20 Budget Analysis */
        .budget-section { margin-top: 22px; }
        .budget-intro { color: var(--muted); font-size: 13px; line-height: 1.6; margin: -6px 0 18px; }
        .budget-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
        .budget-card { background: #FFFFFF; border: 1px solid var(--border); border-radius: 14px; padding: 20px; box-shadow: 0 4px 16px rgba(55,70,33,.05); }
        .budget-card h3 { font-size: 16px; color: var(--dark); margin-bottom: 7px; }
        .budget-share { font-size: 13px; color: var(--muted); margin-bottom: 16px; }
        .budget-amounts { display: flex; justify-content: space-between; gap: 12px; font-size: 13px; margin-bottom: 8px; }
        .budget-amounts strong { color: var(--dark); }
        .budget-track { height: 10px; background: #ECEEE7; border-radius: 999px; overflow: hidden; margin-bottom: 10px; }
        .budget-fill { height: 100%; border-radius: 999px; background: var(--primary); }
        .budget-fill.wants { background: #D89B35; }
        .budget-fill.savings { background: #4B83A8; }
        .budget-fill.over { background: #B5483A; }
        .budget-status { font-size: 12px; color: var(--muted); line-height: 1.5; }
        @media (max-width: 1000px) { .budget-grid { grid-template-columns: 1fr; } }
    </style>
</head>

<body>

<aside class="sidebar">
    <div class="sidebar-brand">My Calendar</div>

    <ul class="nav-list">
        <li class="nav-item">
            <a href="dashboard.php">Calendar</a>
        </li>

        <li class="nav-item">
            <a href="events.php">Events</a>
        </li>

        <li class="nav-item">
            <a href="todo.php">To-Do List</a>
        </li>

        <li class="nav-item">
            <a href="income.php">Income</a>
        </li>

        <li class="nav-item">
            <a href="expenses.php">Expenses</a>
        </li>

        <li class="nav-item active">
            <a href="reports.php">Reports</a>
        </li>

        <li class="nav-item">
            <a href="personal_vault.php">Personal Vault</a>
        </li>
    </ul>

    <div class="sidebar-footer">
        <ul class="nav-list">
            <li class="nav-item">
                <a href="profile.php"><?php echo $sidebar_user_name; ?></a>
            </li>

            <li class="nav-item logout">
                <a href="login.php">Logout</a>
            </li>
        </ul>
    </div>
</aside>

<div class="main-wrapper">

    <header>
        <div class="header-left">
            <h1>Reports</h1>
            <p>View a summary of your finances, tasks and events</p>
        </div>
    </header>

    <main class="content">

        <!-- Report period selector -->
        <form class="report-selector" id="reportForm" method="get">

            <input type="hidden" name="period" id="periodInput" value="<?php echo clean($period); ?>">

            <div class="period-buttons">
                <button
                    type="button"
                    class="period-button <?php echo $period === 'weekly' ? 'active' : ''; ?>"
                    data-period="weekly">
                    Weekly
                </button>

                <button
                    type="button"
                    class="period-button <?php echo $period === 'monthly' ? 'active' : ''; ?>"
                    data-period="monthly">
                    Monthly
                </button>

                <button
                    type="button"
                    class="period-button <?php echo $period === 'yearly' ? 'active' : ''; ?>"
                    data-period="yearly">
                    Yearly
                </button>
            </div>

            <div class="date-control">
                <label for="reportDate">Select Date</label>
                <input
                    type="date"
                    id="reportDate"
                    name="date"
                    value="<?php echo clean($selected_date); ?>"
                >
            </div>

            <div class="range-display">
                Showing: <?php echo clean($display_range); ?>
            </div>

        </form>

        <!-- Summary cards -->
        <section class="summary">

            <div class="card">
                <div class="label">Total Income</div>
                <div class="value">₹<?php echo number_format($income, 2); ?></div>
            </div>

            <div class="card">
                <div class="label">Total Expenses</div>
                <div class="value">₹<?php echo number_format($expenses, 2); ?></div>
            </div>

            <div class="card">
                <div class="label">Balance</div>
                <div class="value">₹<?php echo number_format($balance, 2); ?></div>
            </div>

            <div class="card">
                <div class="label">Savings Rate</div>
                <div class="value"><?php echo number_format($saving_rate, 1); ?>%</div>
            </div>

        </section>

        <section class="grid">

            <div class="card">
                <h2>Financial Overview</h2>

                <div class="row">
                    <span>Income Records</span>
                    <strong><?php echo (int)$income_count; ?></strong>
                </div>

                <div class="row">
                    <span>Expense Records</span>
                    <strong><?php echo (int)$expense_count; ?></strong>
                </div>

                <div class="row">
                    <span>Total Income</span>
                    <strong>₹<?php echo number_format($income, 2); ?></strong>
                </div>

                <div class="row">
                    <span>Total Expenses</span>
                    <strong>₹<?php echo number_format($expenses, 2); ?></strong>
                </div>

                <div class="row">
                    <span>Remaining Balance</span>
                    <strong>₹<?php echo number_format($balance, 2); ?></strong>
                </div>
            </div>

            <div class="card">
                <h2>Productivity</h2>

                <div class="row">
                    <span>Tasks Completed</span>
                    <strong><?php echo (int)$completed; ?></strong>
                </div>

                <div class="row">
                    <span>Tasks Pending</span>
                    <strong><?php echo (int)$pending; ?></strong>
                </div>

                <div class="row">
                    <span>Task Completion</span>
                    <strong><?php echo number_format($task_rate, 1); ?>%</strong>
                </div>

                <div class="row">
                    <span>Events</span>
                    <strong><?php echo (int)$event_count; ?></strong>
                </div>
            </div>

        </section>

        <!-- 50/30/20 Budget Analysis -->
        <section class="budget-section">
            <div class="card">
                <h2>50/30/20 Budget Analysis</h2>
                <p class="budget-intro">A guide for dividing your recorded income into 50% needs, 30% wants and 20% savings. The figures below use the same selected date range as this report.</p>
                <div class="budget-grid">
                    <article class="budget-card">
                        <h3>Needs · 50%</h3>
                        <p class="budget-share">Suggested limit: 50% of income</p>
                        <div class="budget-amounts"><span>Spent: <strong>₹<?php echo number_format($needs_spending, 2); ?></strong></span><span>Limit: <strong>₹<?php echo number_format($needs_limit, 2); ?></strong></span></div>
                        <div class="budget-track"><div class="budget-fill <?php echo $needs_percent > 100 ? 'over' : ''; ?>" style="width: <?php echo min(100, max(0, $needs_percent)); ?>%"></div></div>
                        <p class="budget-status"><?php echo budget_spend_status($needs_spending, $needs_limit, 'needs'); ?></p>
                    </article>
                    <article class="budget-card">
                        <h3>Wants · 30%</h3>
                        <p class="budget-share">Suggested limit: 30% of income</p>
                        <div class="budget-amounts"><span>Spent: <strong>₹<?php echo number_format($wants_spending, 2); ?></strong></span><span>Limit: <strong>₹<?php echo number_format($wants_limit, 2); ?></strong></span></div>
                        <div class="budget-track"><div class="budget-fill wants <?php echo $wants_percent > 100 ? 'over' : ''; ?>" style="width: <?php echo min(100, max(0, $wants_percent)); ?>%"></div></div>
                        <p class="budget-status"><?php echo budget_spend_status($wants_spending, $wants_limit, 'wants'); ?></p>
                    </article>
                    <article class="budget-card">
                        <h3>Savings · 20%</h3>
                        <p class="budget-share">Target: 20% of income</p>
                        <div class="budget-amounts"><span>Saved: <strong>₹<?php echo number_format($actual_savings, 2); ?></strong></span><span>Target: <strong>₹<?php echo number_format($savings_target, 2); ?></strong></span></div>
                        <div class="budget-track"><div class="budget-fill savings" style="width: <?php echo min(100, max(0, $savings_percent)); ?>%"></div></div>
                        <p class="budget-status">
                            <?php
                            if ($savings_target <= 0) {
                                echo 'Add income to calculate your savings target.';
                            } elseif ($expenses > $income_f) {
                                echo 'Spending is ₹' . number_format($expenses - $income_f, 2) . ' more than income, so nothing was saved.';
                            } elseif ($actual_savings >= $savings_target) {
                                echo 'Target met. You saved ' . number_format(($actual_savings / $income_f) * 100, 1) . '% of income.';
                            } else {
                                echo 'You saved ' . number_format(($actual_savings / $income_f) * 100, 1) . '% of income. ₹' . number_format($savings_target - $actual_savings, 2) . ' short of the target.';
                            }
                            ?>
                        </p>
                    </article>
                </div>
            </div>
        </section>

    </main>
</div>

<script>
    const reportForm = document.getElementById('reportForm');
    const periodInput = document.getElementById('periodInput');
    const reportDate = document.getElementById('reportDate');
    const periodButtons = document.querySelectorAll('.period-button');

    periodButtons.forEach(button => {
        button.addEventListener('click', function () {
            periodInput.value = this.dataset.period;
            reportForm.submit();
        });
    });

    reportDate.addEventListener('change', function () {
        reportForm.submit();
    });
</script>

</body>
</html>