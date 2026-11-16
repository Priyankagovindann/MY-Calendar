<?php
session_start();

if (!isset($_SESSION['ID']) || $_SESSION['ID'] === '') {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['ID'];

$con = new mysqli("localhost", "root", "", "calender");

if ($con->connect_error) {
    die("Database connection failed: " . $con->connect_error);
}

$con->set_charset("utf8mb4");

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
   HELPERS
========================================================= */

function clean($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function get_government_holidays($year)
{
    // Central Government gazetted holidays used by the Holidays card.
    $holidays = [
        2026 => [
            "2026-01-26" => "Republic Day",
            "2026-03-04" => "Holi",
            "2026-03-21" => "Id-ul-Fitr",
            "2026-03-26" => "Ram Navami",
            "2026-03-31" => "Mahavir Jayanti",
            "2026-04-03" => "Good Friday",
            "2026-05-01" => "Buddha Purnima",
            "2026-05-27" => "Id-ul-Zuha (Bakrid)",
            "2026-06-26" => "Muharram",
            "2026-08-15" => "Independence Day",
            "2026-08-26" => "Milad-un-Nabi (Id-e-Milad)",
            "2026-09-04" => "Janmashtami",
            "2026-10-02" => "Mahatma Gandhi's Birthday",
            "2026-10-20" => "Dussehra",
            "2026-11-08" => "Diwali (Deepavali)",
            "2026-11-24" => "Guru Nanak's Birthday",
            "2026-12-25" => "Christmas Day"
        ]
    ];

    return $holidays[$year] ?? [];
}

function get_long_weekend_dates($holidays)
{
    $dates = array_keys($holidays);
    sort($dates);
    $lookup = array_fill_keys($dates, true);
    $result = [];

    foreach ($dates as $date) {
        $start = new DateTime($date);
        $end = new DateTime($date);

        while (true) {
            $previous = clone $start;
            $previous->modify('-1 day');
            $key = $previous->format('Y-m-d');
            if (!isset($lookup[$key])) {
                break;
            }
            $start = $previous;
        }

        while (true) {
            $next = clone $end;
            $next->modify('+1 day');
            $key = $next->format('Y-m-d');
            if (!isset($lookup[$key])) {
                break;
            }
            $end = $next;
        }

        $start_day = (int)$start->format('N');
        $end_day = (int)$end->format('N');

        // Monday holidays connect to Sat-Sun; Friday holidays connect to Sat-Sun.
        $is_long_weekend = ($start_day === 1 || $end_day === 5);

        // Consecutive holidays can themselves form a longer break.
        $block_length = (int)$start->diff($end)->format('%a') + 1;
        if ($block_length >= 3) {
            $is_long_weekend = true;
        }

        if ($is_long_weekend) {
            $cursor = clone $start;
            while ($cursor <= $end) {
                $result[$cursor->format('Y-m-d')] = true;
                $cursor->modify('+1 day');
            }
        }
    }

    return $result;
}

function redirect_dashboard($message, $type = "success")
{
    header(
        "Location: dashboard.php?message=" .
        urlencode($message) .
        "&type=" .
        urlencode($type)
    );
    exit();
}

/* =========================================================
   FLASH MESSAGE
========================================================= */

if (isset($_GET['message'])) {
    $message = $_GET['message'];
    $message_type = $_GET['type'] ?? "success";
}

/* =========================================================
   ADD TODO
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_todo"])) {

    $task_title  = trim($_POST["task_title"] ?? "");
    $due_date    = $_POST["due_date"] ?? "";
    $due_time    = $_POST["due_time"] ?? "";
    $priority    = trim($_POST["priority"] ?? "Medium");
    $category    = trim($_POST["todo_category"] ?? "");
    $description = trim($_POST["todo_description"] ?? "");
    $status      = trim($_POST["status"] ?? "Pending");

    if ($task_title === "" || $due_date === "") {
        redirect_dashboard("Please enter a task title and due date.", "error");
    }

    $stmt = $con->prepare(
        "INSERT INTO todo_list
        (user_id, task_title, due_date, due_time, priority, category, description, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $stmt->bind_param(
        "isssssss",
        $user_id,
        $task_title,
        $due_date,
        $due_time,
        $priority,
        $category,
        $description,
        $status
    );

    if ($stmt->execute()) {
        $stmt->close();
        redirect_dashboard("Task added successfully.");
    }

    $error = $stmt->error;
    $stmt->close();
    redirect_dashboard("Unable to add task: " . $error, "error");
}

/* =========================================================
   UPDATE TODO
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_todo"])) {

    $todo_id     = (int)($_POST["todo_id"] ?? 0);
    $task_title  = trim($_POST["task_title"] ?? "");
    $due_date    = $_POST["due_date"] ?? "";
    $due_time    = $_POST["due_time"] ?? "";
    $priority    = trim($_POST["priority"] ?? "Medium");
    $category    = trim($_POST["todo_category"] ?? "");
    $description = trim($_POST["todo_description"] ?? "");
    $status      = trim($_POST["status"] ?? "Pending");

    if ($todo_id <= 0 || $task_title === "" || $due_date === "") {
        redirect_dashboard("Please enter valid task details.", "error");
    }

    $stmt = $con->prepare(
        "UPDATE todo_list
         SET task_title = ?, due_date = ?, due_time = ?, priority = ?,
             category = ?, description = ?, status = ?
         WHERE id = ? AND user_id = ?"
    );

    $stmt->bind_param(
        "sssssssii",
        $task_title,
        $due_date,
        $due_time,
        $priority,
        $category,
        $description,
        $status,
        $todo_id,
        $user_id
    );

    if ($stmt->execute()) {
        $stmt->close();
        redirect_dashboard("Task updated successfully.");
    }

    $error = $stmt->error;
    $stmt->close();
    redirect_dashboard("Unable to update task: " . $error, "error");
}

/* =========================================================
   DELETE TODO
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_todo"])) {

    $todo_id = (int)($_POST["todo_id"] ?? 0);

    if ($todo_id > 0) {
        $stmt = $con->prepare(
            "DELETE FROM todo_list WHERE id = ? AND user_id = ?"
        );

        $stmt->bind_param("ii", $todo_id, $user_id);
        $stmt->execute();
        $stmt->close();

        redirect_dashboard("Task deleted successfully.");
    }
}

/* =========================================================
   ADD EVENT
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_event"])) {

    $title       = trim($_POST["event_title"] ?? "");
    $event_date  = $_POST["event_date"] ?? "";
    $event_time  = $_POST["event_time"] ?? "";
    $location    = trim($_POST["location"] ?? "");
    $description = trim($_POST["event_description"] ?? "");
    $category    = trim($_POST["event_category"] ?? "");

    if ($title === "" || $event_date === "") {
        redirect_dashboard("Please enter an event title and date.", "error");
    }

    $stmt = $con->prepare(
        "INSERT INTO events
        (user_id, title, event_date, event_time, location, description, category)
        VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    $stmt->bind_param(
        "issssss",
        $user_id,
        $title,
        $event_date,
        $event_time,
        $location,
        $description,
        $category
    );

    if ($stmt->execute()) {
        $stmt->close();
        redirect_dashboard("Event added successfully.");
    }

    $error = $stmt->error;
    $stmt->close();
    redirect_dashboard("Unable to add event: " . $error, "error");
}

/* =========================================================
   UPDATE EVENT
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_event"])) {

    $event_id    = (int)($_POST["event_id"] ?? 0);
    $title       = trim($_POST["event_title"] ?? "");
    $event_date  = $_POST["event_date"] ?? "";
    $event_time  = $_POST["event_time"] ?? "";
    $location    = trim($_POST["location"] ?? "");
    $description = trim($_POST["event_description"] ?? "");
    $category    = trim($_POST["event_category"] ?? "");

    if ($event_id <= 0 || $title === "" || $event_date === "") {
        redirect_dashboard("Please enter valid event details.", "error");
    }

    $stmt = $con->prepare(
        "UPDATE events
         SET title = ?, event_date = ?, event_time = ?, location = ?,
             description = ?, category = ?
         WHERE id = ? AND user_id = ?"
    );

    $stmt->bind_param(
        "ssssssii",
        $title,
        $event_date,
        $event_time,
        $location,
        $description,
        $category,
        $event_id,
        $user_id
    );

    if ($stmt->execute()) {
        $stmt->close();
        redirect_dashboard("Event updated successfully.");
    }

    $error = $stmt->error;
    $stmt->close();
    redirect_dashboard("Unable to update event: " . $error, "error");
}

/* =========================================================
   DELETE EVENT
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_event"])) {

    $event_id = (int)($_POST["event_id"] ?? 0);

    if ($event_id > 0) {
        $stmt = $con->prepare(
            "DELETE FROM events WHERE id = ? AND user_id = ?"
        );

        $stmt->bind_param("ii", $event_id, $user_id);
        $stmt->execute();
        $stmt->close();

        redirect_dashboard("Event deleted successfully.");
    }
}

/* =========================================================
   ADD EXPENSE
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_expense"])) {

    $expense_name         = trim($_POST["expense_name"] ?? "");
    $expense_category     = trim($_POST["expense_category"] ?? "");
    $amount               = trim($_POST["amount"] ?? "");
    $expense_date         = $_POST["expense_date"] ?? "";
    $payment_method       = trim($_POST["payment_method"] ?? "");
    $spending_from_income = trim($_POST["spending_from_income"] ?? "");
    $description          = trim($_POST["expense_description"] ?? "");

    if (
        $expense_name === "" ||
        $expense_category === "" ||
        $amount === "" ||
        $expense_date === ""
    ) {
        redirect_dashboard("Please fill in all required expense fields.", "error");
    }

    if (!is_numeric($amount) || (float)$amount <= 0) {
        redirect_dashboard("Please enter a valid expense amount.", "error");
    }

    $stmt = $con->prepare(
        "INSERT INTO expenses
        (user_id, expense_name, category, amount, expense_date,
         payment_method, spending_from_income, description)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $amount = (float)$amount;

    $stmt->bind_param(
        "issdssss",
        $user_id,
        $expense_name,
        $expense_category,
        $amount,
        $expense_date,
        $payment_method,
        $spending_from_income,
        $description
    );

    if ($stmt->execute()) {
        $stmt->close();
        redirect_dashboard("Expense added successfully.");
    }

    $error = $stmt->error;
    $stmt->close();
    redirect_dashboard("Unable to add expense: " . $error, "error");
}

/* =========================================================
   UPDATE EXPENSE
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_expense"])) {

    $expense_id           = (int)($_POST["expense_id"] ?? 0);
    $expense_name         = trim($_POST["expense_name"] ?? "");
    $expense_category     = trim($_POST["expense_category"] ?? "");
    $amount               = trim($_POST["amount"] ?? "");
    $expense_date         = $_POST["expense_date"] ?? "";
    $payment_method       = trim($_POST["payment_method"] ?? "");
    $spending_from_income = trim($_POST["spending_from_income"] ?? "");
    $description          = trim($_POST["expense_description"] ?? "");

    if (
        $expense_id <= 0 ||
        $expense_name === "" ||
        $expense_category === "" ||
        $amount === "" ||
        $expense_date === ""
    ) {
        redirect_dashboard("Please enter valid expense details.", "error");
    }

    if (!is_numeric($amount) || (float)$amount <= 0) {
        redirect_dashboard("Please enter a valid expense amount.", "error");
    }

    $amount = (float)$amount;

    $stmt = $con->prepare(
        "UPDATE expenses
         SET expense_name = ?, category = ?, amount = ?, expense_date = ?,
             payment_method = ?, spending_from_income = ?, description = ?
         WHERE id = ? AND user_id = ?"
    );

    $stmt->bind_param(
        "ssdssssii",
        $expense_name,
        $expense_category,
        $amount,
        $expense_date,
        $payment_method,
        $spending_from_income,
        $description,
        $expense_id,
        $user_id
    );

    if ($stmt->execute()) {
        $stmt->close();
        redirect_dashboard("Expense updated successfully.");
    }

    $error = $stmt->error;
    $stmt->close();
    redirect_dashboard("Unable to update expense: " . $error, "error");
}

/* =========================================================
   DELETE EXPENSE
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_expense"])) {

    $expense_id = (int)($_POST["expense_id"] ?? 0);

    if ($expense_id > 0) {
        $stmt = $con->prepare(
            "DELETE FROM expenses WHERE id = ? AND user_id = ?"
        );

        $stmt->bind_param("ii", $expense_id, $user_id);
        $stmt->execute();
        $stmt->close();

        redirect_dashboard("Expense deleted successfully.");
    }
}

/* =========================================================
   LOAD DATA
========================================================= */

$events = [];
$stmt = $con->prepare(
    "SELECT id, title, event_date, event_time, location, description, category
     FROM events
     WHERE user_id = ?
     ORDER BY event_date ASC, event_time ASC"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $events[] = $row;
}
$stmt->close();


$todos = [];
$stmt = $con->prepare(
    "SELECT id, task_title, due_date, due_time, priority, category, description, status
     FROM todo_list
     WHERE user_id = ?
     ORDER BY
        CASE WHEN status = 'Completed' THEN 1 ELSE 0 END,
        due_date ASC,
        due_time ASC"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $todos[] = $row;
}
$stmt->close();


$expenses = [];
$stmt = $con->prepare(
    "SELECT id, expense_name, category, amount, expense_date,
            payment_method, spending_from_income, description
     FROM expenses
     WHERE user_id = ?
     ORDER BY expense_date DESC, id DESC"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $expenses[] = $row;
}
$stmt->close();


/* =========================================================
   INCOME TOTAL
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
   EXPENSE TOTAL
========================================================= */

$total_expenses = 0;

$stmt = $con->prepare(
    "SELECT COALESCE(SUM(amount), 0)
     FROM expenses
     WHERE user_id = ?"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($total_expenses);
$stmt->fetch();
$stmt->close();

$remaining_balance = (float)$total_income - (float)$total_expenses;


/* =========================================================
   CALENDAR
========================================================= */

$today = new DateTime();

$calendar_year = isset($_GET["year"])
    ? (int)$_GET["year"]
    : (int)$today->format("Y");

$calendar_month = isset($_GET["month"])
    ? (int)$_GET["month"]
    : (int)$today->format("m");

if ($calendar_month < 1) {
    $calendar_month = 12;
    $calendar_year--;
}

if ($calendar_month > 12) {
    $calendar_month = 1;
    $calendar_year++;
}

$view = $_GET["view"] ?? "month";

if (!in_array($view, ["month", "day", "year"], true)) {
    $view = "month";
}

$selected_date = $_GET["date"] ?? $today->format("Y-m-d");

$month_start = new DateTime(
    sprintf("%04d-%02d-01", $calendar_year, $calendar_month)
);

$days_in_month = (int)$month_start->format("t");

$first_day_index = (int)$month_start->format("N") - 1;

$previous_month = clone $month_start;
$previous_month->modify("-1 month");

$next_month = clone $month_start;
$next_month->modify("+1 month");

$calendar_title = $month_start->format("F Y");


/* Dates containing an event or an outstanding To-Do */

$event_dates = [];

foreach ($events as $event) {
    $event_dates[$event["event_date"]] = true;
}

foreach ($todos as $todo) {
    if (strtolower(trim($todo["status"] ?? "pending")) !== "completed") {
        $event_dates[$todo["due_date"]] = true;
    }
}

$government_holidays = get_government_holidays($calendar_year);
$long_weekend_dates = get_long_weekend_dates($government_holidays);

/* Holidays card follows the month currently selected in the calendar. */
$monthly_holidays = [];

foreach ($government_holidays as $holiday_date => $holiday_name) {
    if ((int)date("n", strtotime($holiday_date)) === $calendar_month) {
        $monthly_holidays[$holiday_date] = $holiday_name;
    }
}


/* =========================================================
   CURRENT DAY DATA
========================================================= */

$day_events = [];
$day_todos = [];

foreach ($events as $event) {
    if ($event["event_date"] === $selected_date) {
        $day_events[] = $event;
    }
}

foreach ($todos as $todo) {
    if ($todo["due_date"] === $selected_date) {
        $day_todos[] = $todo;
    }
}


/* =========================================================
   DASHBOARD DISPLAY LIMITS
========================================================= */

$dashboard_events = array_slice(
    array_values(
        array_filter(
            $events,
            function ($event) use ($today) {
                return $event["event_date"] >= $today->format("Y-m-d");
            }
        )
    ),
    0,
    4
);

$dashboard_todos = array_slice($todos, 0, 5);

$dashboard_expenses = array_slice($expenses, 0, 5);

/* =========================================================
   UPGRADED DASHBOARD SUMMARY DATA
   Calendar and sidebar remain unchanged.
========================================================= */

$today_string = $today->format("Y-m-d");

$today_todos = [];
$today_events = [];
$today_expenses = 0;

foreach ($todos as $todo) {
    if ($todo["due_date"] === $today_string) {
        $today_todos[] = $todo;
    }
}

foreach ($events as $event) {
    if ($event["event_date"] === $today_string) {
        $today_events[] = $event;
    }
}

foreach ($expenses as $expense) {
    if ($expense["expense_date"] === $today_string) {
        $today_expenses += (float)$expense["amount"];
    }
}

/* Find the earliest upcoming pending task/event. */
$next_item = null;

foreach ($todos as $todo) {
    if (strtolower(trim($todo["status"] ?? "pending")) === "completed") {
        continue;
    }

    if ($todo["due_date"] < $today_string) {
        continue;
    }

    $next_item = [
        "type" => "To-Do",
        "title" => $todo["task_title"],
        "date" => $todo["due_date"],
        "time" => $todo["due_time"],
        "priority" => $todo["priority"]
    ];

    break;
}

foreach ($events as $event) {
    if ($event["event_date"] < $today_string) {
        continue;
    }

    $candidate = [
        "type" => "Event",
        "title" => $event["title"],
        "date" => $event["event_date"],
        "time" => $event["event_time"],
        "priority" => ""
    ];

    if ($next_item === null) {
        $next_item = $candidate;
        continue;
    }

    $next_timestamp = strtotime(
        $next_item["date"] . " " . ($next_item["time"] ?: "23:59:59")
    );

    $candidate_timestamp = strtotime(
        $candidate["date"] . " " . ($candidate["time"] ?: "23:59:59")
    );

    if ($candidate_timestamp < $next_timestamp) {
        $next_item = $candidate;
    }
}



/* =========================================================
   EDIT DATA
========================================================= */

$edit_type = $_GET["edit"] ?? "";
$edit_id   = (int)($_GET["id"] ?? 0);

$edit_record = null;

if ($edit_id > 0 && in_array($edit_type, ["todo", "event", "expense"], true)) {

    if ($edit_type === "todo") {
        $stmt = $con->prepare(
            "SELECT id, task_title, due_date, due_time, priority,
                    category, description, status
             FROM todo_list
             WHERE id = ? AND user_id = ?"
        );
    } elseif ($edit_type === "event") {
        $stmt = $con->prepare(
            "SELECT id, title, event_date, event_time, location,
                    description, category
             FROM events
             WHERE id = ? AND user_id = ?"
        );
    } else {
        $stmt = $con->prepare(
            "SELECT id, expense_name, category, amount, expense_date,
                    payment_method, spending_from_income, description
             FROM expenses
             WHERE id = ? AND user_id = ?"
        );
    }

    $stmt->bind_param("ii", $edit_id, $user_id);
    $stmt->execute();

    $edit_result = $stmt->get_result();
    $edit_record = $edit_result->fetch_assoc();

    $stmt->close();
}


/* =========================================================
   CLOSE DB
========================================================= */

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

    <title>Calendar Dashboard</title>

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

            --success: #3E8E5B;
            --danger: #C94C4C;
            --warning: #D89B35;
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
            background: var(--bg-beige);
            color: var(--text-dark);
            display: flex;
            min-height: 100vh;
        }

        /* =====================================================
           SIDEBAR
        ===================================================== */

        .sidebar {
            width: 250px;
            min-width: 250px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: var(--olive-dark);
            color: #FFFFFF;
            display: flex;
            flex-direction: column;
            padding: 30px 20px;
            overflow: hidden;
            z-index: 1000;
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

        .sidebar-footer {
            margin-top: auto;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
        }

        .sidebar-footer .nav-list {
            gap: 8px;
        }

        .sidebar-footer .nav-item a {
            color: #E2E8D5;
        }

        .sidebar-footer .logout a {
            color: #F2D6D6;
        }

        .sidebar-footer .logout a:hover {
            background: #7A3D3D;
            color: #FFFFFF;
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

        .nav-item.active a,
        .nav-item a:hover {
            background: var(--olive-primary);
            color: var(--white);
        }

        /* =====================================================
           MAIN
        ===================================================== */

        .main-wrapper {
            flex: 1;
            margin-left: 250px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        header {
            background: var(--card-beige);
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
            background: var(--olive-primary);
            color: var(--white);
            display: grid;
            place-items: center;
            font-weight: 700;
            font-size: 14px;
        }

        .dashboard-grid {
            padding: 30px 36px;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 24px;
        }

        .module-card {
            background: var(--card-beige);
            border: 1px solid var(--border-beige);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 16px rgba(55, 70, 33, 0.04);
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .module-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
        }

        .module-title {
            font-size: 17px;
            font-weight: 700;
            color: var(--olive-dark);
        }

        .module-badge {
            font-size: 11px;
            font-weight: 700;
            background: var(--olive-tint);
            color: var(--olive-dark);
            padding: 4px 10px;
            border-radius: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .module-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .small-button,
        .add-button {
            border: none;
            background: var(--olive-primary);
            color: white;
            border-radius: 7px;
            padding: 8px 11px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .small-button:hover,
        .add-button:hover {
            background: var(--olive-dark);
        }

        .edit-button,
        .delete-button {
            border: none;
            border-radius: 6px;
            padding: 6px 8px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
        }

        .edit-button {
            background: var(--olive-tint);
            color: var(--olive-dark);
        }

        .delete-button {
            background: #F5E4E4;
            color: var(--danger);
        }

        .action-row {
            display: flex;
            gap: 6px;
            margin-left: auto;
        }

        .empty-state {
            padding: 22px 10px;
            text-align: center;
            color: var(--text-muted);
            font-size: 13px;
        }

        .message {
            margin: 0 36px 0;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
        }

        .message.success {
            color: #2F6E45;
            background: #E5F1E7;
            border: 1px solid #C5DEC9;
        }

        .message.error {
            color: #A63D3D;
            background: #F8E7E7;
            border: 1px solid #E6C4C4;
        }

        /* =====================================================
           CALENDAR
        ===================================================== */

        .calendar-card {
            grid-column: span 2;
        }

        .calendar-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }

        .calendar-navigation {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .calendar-navigation a {
            text-decoration: none;
            background: var(--white);
            border: 1px solid var(--border-beige);
            color: var(--olive-dark);
            padding: 7px 10px;
            border-radius: 7px;
            font-weight: 700;
            font-size: 12px;
        }

        .calendar-navigation strong {
            min-width: 130px;
            text-align: center;
            color: var(--olive-dark);
        }

        .view-switch {
            display: flex;
            gap: 5px;
            background: var(--card-alt);
            padding: 4px;
            border-radius: 8px;
        }

        .view-switch a {
            padding: 7px 11px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 700;
        }

        .view-switch a.active {
            background: var(--olive-primary);
            color: white;
        }

        .cal-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 6px;
            text-align: center;
        }

        .cal-header {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            padding-bottom: 8px;
        }

        .cal-cell {
            min-height: 48px;
            padding: 7px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 8px;
            background: var(--white);
            border: 1px solid transparent;
            color: var(--text-dark);
            text-decoration: none;
        }

        .cal-cell:hover {
            border-color: var(--olive-light);
        }

        .cal-cell.muted {
            color: #BDBBB5;
            background: transparent;
        }

        .cal-cell.today {
            background: var(--olive-light);
            color: #FFFFFF;
            border: 2px solid var(--olive-light);
            font-weight: 800;
        }

        .cal-cell.selected {
            background: var(--olive-primary);
            color: #FFFFFF;
            font-weight: 700;
        }

        .cal-cell.event-dot {
            background: #CC3A63;
            color: #FFFFFF;
            border-color: #CC3A63;
            font-weight: 800;
        }

        .cal-cell.event-dot:hover {
            background: #B92F55;
            border-color: #B92F55;
        }

        .cal-cell.event-dot.today {
            background: var(--olive-light);
            color: #FFFFFF;
            border-color: var(--olive-light);
            box-shadow: 0 0 0 3px rgba(112, 132, 61, 0.24);
        }

        .day-view {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .day-panel {
            background: var(--white);
            border: 1px solid var(--border-beige);
            border-radius: 9px;
            padding: 16px;
        }

        .day-panel h4 {
            color: var(--olive-dark);
            margin-bottom: 12px;
        }

        .day-record {
            padding: 10px 0;
            border-bottom: 1px solid var(--border-beige);
        }

        .day-record:last-child {
            border-bottom: none;
        }

        .day-record strong {
            display: block;
            font-size: 13px;
        }

        .day-record small {
            color: var(--text-muted);
            font-size: 11px;
        }

        .year-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }

        .year-month {
            background: var(--white);
            border: 1px solid var(--border-beige);
            border-radius: 8px;
            padding: 12px;
        }

        .year-month h4 {
            color: var(--olive-dark);
            margin-bottom: 8px;
            font-size: 13px;
        }

        .year-mini {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 3px;
            font-size: 8px;
            text-align: center;
        }

        .year-mini span,
        .year-mini .year-date {
            padding: 3px 0;
            border-radius: 3px;
        }

        .year-mini .year-date {
            border: 0;
            background: transparent;
            color: inherit;
            font: inherit;
            cursor: pointer;
        }

        .year-mini .year-date:hover {
            outline: 1px solid var(--olive-light);
        }

        .year-mini .has-data {
            background: #CC3A63;
            color: #FFFFFF;
            font-weight: 800;
        }

        .year-mini .has-data:hover {
            background: #B92F55;
        }

        .year-mini .has-data.today {
            box-shadow: 0 0 0 2px rgba(204, 58, 99, 0.24);
        }

        .year-mini .today {
            background: var(--olive-light);
            color: #ffffff;
            font-weight: 800;
            border-radius: 50%;
            box-shadow: 0 0 0 2px rgba(92, 107, 63, 0.18);
        }

        .year-mini .today.has-data {
            background: var(--olive-light);
            color: #FFFFFF;
            box-shadow: 0 0 0 2px rgba(112, 132, 61, 0.24);
        }

        /* =====================================================
           CALENDAR DATE ACTION POPUP
        ===================================================== */

        .date-action-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(30, 35, 22, 0.38);
            z-index: 1200;
            padding: 20px;
        }

        .date-action-modal.show {
            display: flex;
        }

        .date-action-card {
            width: min(430px, 100%);
            background: var(--white);
            border: 1px solid var(--border-beige);
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.16);
        }

        .date-action-card h3 {
            margin: 0 0 7px;
            color: var(--text-dark);
            font-size: 20px;
        }

        .date-action-card p {
            margin: 0 0 20px;
            color: var(--text-muted);
        }

        .date-action-records {
            margin: 0 0 18px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .date-action-record {
            background: var(--card-beige);
            border: 1px solid var(--border-beige);
            border-radius: 8px;
            padding: 10px 12px;
        }

        .date-action-record strong {
            display: block;
            color: var(--text-dark);
            font-size: 13px;
            margin-bottom: 3px;
        }

        .date-action-record small {
            color: var(--text-muted);
            font-size: 11px;
            line-height: 1.45;
        }

        .date-action-record.todo-record {
            border-left: 4px solid var(--olive-light);
        }

        .date-action-record.event-record {
            border-left: 4px solid #CC3A63;
        }

        .date-action-empty {
            color: var(--text-muted);
            font-size: 12px;
            padding: 10px 0;
        }

        .date-action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .date-action-button {
            border: 1px solid var(--border-beige);
            border-radius: 9px;
            padding: 13px 12px;
            background: var(--olive-tint);
            color: var(--olive-dark);
            font-weight: 700;
            cursor: pointer;
        }

        .date-action-button:hover {
            background: var(--olive-light);
            color: #ffffff;
        }

        .date-action-cancel {
            width: 100%;
            margin-top: 12px;
            border: 0;
            background: transparent;
            color: var(--text-muted);
            padding: 8px;
            cursor: pointer;
        }

        @media (max-width: 520px) {
            .date-action-buttons {
                grid-template-columns: 1fr;
            }
        }

        /* =====================================================
           HOLIDAYS
        ===================================================== */

        .holiday-card {
            grid-column: span 2;
        }

        .holiday-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px 14px;
        }

        .holiday-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 12px;
            background: var(--white);
            border: 1px solid var(--border-beige);
            border-radius: 8px;
        }

        .holiday-date {
            min-width: 54px;
            padding: 7px 8px;
            border-radius: 8px;
            background: var(--olive-tint);
            color: var(--olive-dark);
            text-align: center;
            font-size: 10px;
            font-weight: 800;
            line-height: 1.15;
        }

        .holiday-date span {
            display: block;
            font-size: 16px;
            margin-top: 2px;
        }

        .holiday-info {
            min-width: 0;
            flex: 1;
        }

        .holiday-info strong {
            display: block;
            color: var(--text-dark);
            font-size: 13px;
            margin-bottom: 3px;
        }

        .holiday-info small {
            color: var(--text-muted);
            font-size: 11px;
        }

        .long-weekend-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 7px;
            border-radius: 10px;
            background: #FBE8EE;
            color: #CC3A63;
            font-size: 9px;
            font-weight: 800;
            white-space: nowrap;
        }

        /* =====================================================
           EVENTS
        ===================================================== */

        .record-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .record-item {
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--white);
            border: 1px solid var(--border-beige);
            border-radius: 8px;
            padding: 11px 12px;
        }

        .date-box {
            width: 46px;
            min-width: 46px;
            height: 46px;
            background: var(--olive-tint);
            color: var(--olive-dark);
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .date-box span {
            font-size: 15px;
        }

        .record-info {
            min-width: 0;
        }

        .record-info strong {
            display: block;
            font-size: 13px;
            margin-bottom: 3px;
        }

        .record-info small {
            color: var(--text-muted);
            font-size: 11px;
        }

        .todo-item {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--white);
            border: 1px solid var(--border-beige);
            border-radius: 8px;
            padding: 11px 12px;
        }

        .todo-item .todo-main {
            min-width: 0;
        }

        .todo-item strong {
            display: block;
            font-size: 13px;
        }

        .todo-item small {
            color: var(--text-muted);
            font-size: 11px;
        }

        .status {
            display: inline-block;
            margin-top: 3px;
            padding: 3px 7px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: 700;
        }

        .status.pending {
            background: #F6ECD9;
            color: #8A611C;
        }

        .status.completed {
            background: #E4F0E6;
            color: #3E6F4A;
        }

        /* =====================================================
           EXPENSES
        ===================================================== */

        .expense-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-bottom: 14px;
        }

        .expense-summary-box {
            background: var(--white);
            border: 1px solid var(--border-beige);
            border-radius: 8px;
            padding: 10px;
        }

        .expense-summary-box span {
            display: block;
            color: var(--text-muted);
            font-size: 9px;
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .expense-summary-box strong {
            color: var(--olive-dark);
            font-size: 15px;
        }

        .expense-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-beige);
        }

        .expense-row:last-child {
            border-bottom: none;
        }

        .expense-row strong {
            display: block;
            font-size: 13px;
        }

        .expense-row small {
            color: var(--text-muted);
            font-size: 10px;
        }

        .expense-amount {
            color: #A84738;
            font-weight: 700;
            white-space: nowrap;
        }

        /* =====================================================
           MODAL
        ===================================================== */

        .modal {
            position: fixed;
            inset: 0;
            background: rgba(42, 44, 36, 0.55);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 3000;
        }

        .modal.show {
            display: flex;
        }

        .modal-card {
            width: min(700px, 100%);
            max-height: 90vh;
            overflow-y: auto;
            background: var(--card-beige);
            border: 1px solid var(--border-beige);
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 18px 50px rgba(0, 0, 0, 0.18);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }

        .modal-header h2 {
            color: var(--olive-dark);
            font-size: 20px;
        }

        .close-modal {
            border: none;
            background: transparent;
            font-size: 25px;
            color: var(--text-muted);
            cursor: pointer;
        }

        .type-switch {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 7px;
            margin-bottom: 18px;
        }

        .type-switch button {
            border: 1px solid var(--border-beige);
            background: var(--white);
            color: var(--text-dark);
            padding: 10px;
            border-radius: 7px;
            font-weight: 700;
            cursor: pointer;
        }

        .type-switch button.active {
            background: var(--olive-primary);
            color: white;
            border-color: var(--olive-primary);
        }

        .form-panel {
            display: none;
        }

        .form-panel.active {
            display: block;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group.full {
            grid-column: span 2;
        }

        .form-group label {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            border: 1px solid var(--border-beige);
            border-radius: 7px;
            padding: 10px 11px;
            background: var(--white);
            color: var(--text-dark);
            outline: none;
            font-size: 13px;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--olive-primary);
        }

        .form-group textarea {
            min-height: 85px;
            resize: vertical;
        }

        .form-submit {
            margin-top: 16px;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .primary-submit {
            border: none;
            background: var(--olive-primary);
            color: white;
            border-radius: 7px;
            padding: 11px 18px;
            font-weight: 700;
            cursor: pointer;
        }

        .secondary-submit {
            border: 1px solid var(--border-beige);
            background: var(--white);
            color: var(--text-dark);
            border-radius: 7px;
            padding: 11px 18px;
            font-weight: 700;
            cursor: pointer;
        }

        /* =====================================================
           EDIT CARD
        ===================================================== */

        .edit-card {
            margin: 0 36px 24px;
        }

        .edit-card .module-header {
            margin-bottom: 14px;
        }

        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 1100px) {

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .calendar-card,
            .holiday-card {
                grid-column: span 1;
            }

            .year-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 800px) {

            .holiday-list {
                grid-template-columns: 1fr;
            }

            .sidebar {
                width: 80px;
                min-width: 80px;
                padding: 20px 10px;
                align-items: center;
            }

            .sidebar-brand {
                display: none;
            }

            .nav-item a {
                font-size: 11px;
                padding: 10px;
                justify-content: center;
            }

            .main-wrapper {
                margin-left: 80px;
            }

            header {
                padding: 18px 20px;
            }

            .dashboard-grid {
                padding: 20px;
            }

            .message {
                margin-left: 20px;
                margin-right: 20px;
            }

            .edit-card {
                margin-left: 20px;
                margin-right: 20px;
            }

            .day-view {
                grid-template-columns: 1fr;
            }

            .year-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {

            .user-profile > div:last-child {
                display: none;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
            }

            .type-switch {
                grid-template-columns: 1fr;
            }

            .expense-summary {
                grid-template-columns: 1fr;
            }

            .year-grid {
                grid-template-columns: 1fr;
            }
        }

    
        /* =====================================================
           UPGRADED DASHBOARD SUMMARY
        ===================================================== */

        .summary-section {
            grid-column: span 2;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 24px;
        }

        .summary-card {
            background: var(--card-beige);
            border: 1px solid var(--border-beige);
            border-radius: 12px;
            padding: 22px;
            box-shadow: 0 4px 16px rgba(55, 70, 33, 0.04);
            min-width: 0;
        }

        .summary-card.full {
            grid-column: span 2;
        }

        .summary-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .summary-card-title {
            color: var(--olive-dark);
            font-size: 17px;
            font-weight: 700;
        }

        .summary-card-link {
            color: var(--olive-primary);
            text-decoration: none;
            font-size: 11px;
            font-weight: 700;
        }

        .summary-card-link:hover {
            color: var(--olive-dark);
            text-decoration: underline;
        }

        .next-card {
            background: linear-gradient(135deg, var(--olive-tint), var(--card-beige));
        }

        .next-content {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .next-icon {
            width: 48px;
            height: 48px;
            min-width: 48px;
            border-radius: 12px;
            background: var(--olive-primary);
            color: var(--white);
            display: grid;
            place-items: center;
            font-size: 20px;
            font-weight: 800;
        }

        .next-content strong {
            display: block;
            color: var(--olive-dark);
            font-size: 16px;
            margin-bottom: 5px;
        }

        .next-content small {
            color: var(--text-muted);
            font-size: 11px;
        }

        .summary-list {
            display: flex;
            flex-direction: column;
            gap: 9px;
        }

        .summary-list-item {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 10px 11px;
            background: var(--white);
            border: 1px solid var(--border-beige);
            border-radius: 8px;
        }

        .summary-list-marker {
            width: 7px;
            height: 7px;
            min-width: 7px;
            border-radius: 50%;
            background: var(--olive-primary);
        }

        .summary-list-content {
            min-width: 0;
            flex: 1;
        }

        .summary-list-content strong {
            display: block;
            color: var(--text-dark);
            font-size: 13px;
            margin-bottom: 3px;
        }

        .summary-list-content small {
            color: var(--text-muted);
            font-size: 10px;
        }

        .today-empty {
            padding: 18px 10px;
            text-align: center;
            color: var(--text-muted);
            font-size: 12px;
        }

        .financial-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
        }

        .financial-box {
            background: var(--white);
            border: 1px solid var(--border-beige);
            border-radius: 9px;
            padding: 13px;
        }

        .financial-box span {
            display: block;
            color: var(--text-muted);
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .financial-box strong {
            display: block;
            color: var(--olive-dark);
            font-size: 16px;
        }

        .financial-box.spent strong {
            color: #A84738;
        }

        .financial-box.today-spent strong {
            color: var(--olive-primary);
        }

        .holiday-summary-card {
            grid-column: span 2;
        }

        .holiday-summary-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px 14px;
        }

        @media (max-width: 1100px) {
            .summary-section,
            .summary-card.full,
            .holiday-summary-card {
                grid-column: span 1;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .financial-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 600px) {
            .financial-grid,
            .holiday-summary-list {
                grid-template-columns: 1fr;
            }
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

            <li class="nav-item active">
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

        <!-- PROFILE AND LOGOUT -->
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
                    Welcome, <?php echo $sidebar_user_name; ?>
                </h1>

                <p>
                    Welcome back, here is your daily summary
                </p>

            </div>

        </header>


        <?php if ($message !== "") : ?>

            <div
                class="message <?php echo clean($message_type); ?>"
                style="margin-top:20px;"
            >
                <?php echo clean($message); ?>
            </div>

        <?php endif; ?>


        <?php if ($edit_record !== null) : ?>

            <section class="module-card edit-card">

                <div class="module-header">

                    <div class="module-title">
                        Edit <?php echo ucfirst(clean($edit_type)); ?>
                    </div>

                    <a
                        href="dashboard.php"
                        class="small-button"
                    >
                        Close
                    </a>

                </div>


                <?php if ($edit_type === "todo") : ?>

                    <form method="post">

                        <input
                            type="hidden"
                            name="todo_id"
                            value="<?php echo (int)$edit_record["id"]; ?>"
                        >

                        <div class="form-grid">

                            <div class="form-group">
                                <label>Task Title</label>
                                <input
                                    type="text"
                                    name="task_title"
                                    value="<?php echo clean($edit_record["task_title"]); ?>"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label>Status</label>
                                <select name="status">
                                    <option value="Pending" <?php echo $edit_record["status"] === "Pending" ? "selected" : ""; ?>>
                                        Pending
                                    </option>
                                    <option value="Completed" <?php echo $edit_record["status"] === "Completed" ? "selected" : ""; ?>>
                                        Completed
                                    </option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Due Date</label>
                                <input
                                    type="date"
                                    name="due_date"
                                    value="<?php echo clean($edit_record["due_date"]); ?>"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label>Due Time</label>
                                <input
                                    type="time"
                                    name="due_time"
                                    value="<?php echo clean($edit_record["due_time"]); ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label>Priority</label>
                                <select name="priority">
                                    <?php foreach (["Low", "Medium", "High"] as $priority) : ?>
                                        <option value="<?php echo $priority; ?>" <?php echo $edit_record["priority"] === $priority ? "selected" : ""; ?>>
                                            <?php echo $priority; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Category</label>
                                <input
                                    type="text"
                                    name="todo_category"
                                    value="<?php echo clean($edit_record["category"]); ?>"
                                >
                            </div>

                            <div class="form-group full">
                                <label>Description</label>
                                <textarea name="todo_description"><?php echo clean($edit_record["description"]); ?></textarea>
                            </div>

                        </div>

                        <div class="form-submit">

                            <a
                                href="dashboard.php"
                                class="secondary-submit"
                            >
                                Cancel
                            </a>

                            <button
                                type="submit"
                                name="update_todo"
                                class="primary-submit"
                            >
                                Update Task
                            </button>

                        </div>

                    </form>


                <?php elseif ($edit_type === "event") : ?>

                    <form method="post">

                        <input
                            type="hidden"
                            name="event_id"
                            value="<?php echo (int)$edit_record["id"]; ?>"
                        >

                        <div class="form-grid">

                            <div class="form-group">
                                <label>Event Title</label>
                                <input
                                    type="text"
                                    name="event_title"
                                    value="<?php echo clean($edit_record["title"]); ?>"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label>Category</label>
                                <input
                                    type="text"
                                    name="event_category"
                                    value="<?php echo clean($edit_record["category"]); ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label>Date</label>
                                <input
                                    type="date"
                                    name="event_date"
                                    value="<?php echo clean($edit_record["event_date"]); ?>"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label>Time</label>
                                <input
                                    type="time"
                                    name="event_time"
                                    value="<?php echo clean($edit_record["event_time"]); ?>"
                                >
                            </div>

                            <div class="form-group full">
                                <label>Location</label>
                                <input
                                    type="text"
                                    name="location"
                                    value="<?php echo clean($edit_record["location"]); ?>"
                                >
                            </div>

                            <div class="form-group full">
                                <label>Description</label>
                                <textarea name="event_description"><?php echo clean($edit_record["description"]); ?></textarea>
                            </div>

                        </div>

                        <div class="form-submit">

                            <a
                                href="dashboard.php"
                                class="secondary-submit"
                            >
                                Cancel
                            </a>

                            <button
                                type="submit"
                                name="update_event"
                                class="primary-submit"
                            >
                                Update Event
                            </button>

                        </div>

                    </form>


                <?php else : ?>

                    <form method="post">

                        <input
                            type="hidden"
                            name="expense_id"
                            value="<?php echo (int)$edit_record["id"]; ?>"
                        >

                        <div class="form-grid">

                            <div class="form-group">
                                <label>Expense Name</label>
                                <input
                                    type="text"
                                    name="expense_name"
                                    value="<?php echo clean($edit_record["expense_name"]); ?>"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label>Category</label>
                                <input
                                    type="text"
                                    name="expense_category"
                                    value="<?php echo clean($edit_record["category"]); ?>"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label>Amount</label>
                                <input
                                    type="number"
                                    name="amount"
                                    min="0.01"
                                    step="0.01"
                                    value="<?php echo clean($edit_record["amount"]); ?>"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label>Date</label>
                                <input
                                    type="date"
                                    name="expense_date"
                                    value="<?php echo clean($edit_record["expense_date"]); ?>"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label>Payment Method</label>
                                <input
                                    type="text"
                                    name="payment_method"
                                    value="<?php echo clean($edit_record["payment_method"]); ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label>Spending From Income</label>
                                <input
                                    type="text"
                                    name="spending_from_income"
                                    value="<?php echo clean($edit_record["spending_from_income"]); ?>"
                                >
                            </div>

                            <div class="form-group full">
                                <label>Description</label>
                                <textarea name="expense_description"><?php echo clean($edit_record["description"]); ?></textarea>
                            </div>

                        </div>

                        <div class="form-submit">

                            <a
                                href="dashboard.php"
                                class="secondary-submit"
                            >
                                Cancel
                            </a>

                            <button
                                type="submit"
                                name="update_expense"
                                class="primary-submit"
                            >
                                Update Expense
                            </button>

                        </div>

                    </form>

                <?php endif; ?>

            </section>

        <?php endif; ?>


        <main class="dashboard-grid">


            <!-- =================================================
                 1. CALENDAR
            ================================================== -->

            <section class="module-card calendar-card">

                <div class="module-header">

                    <div class="module-title">
                        Calendar
                    </div>

                    <div class="module-actions">

                        <a
                            href="dashboard.php?view=month&year=<?php echo $calendar_year; ?>&month=<?php echo $calendar_month; ?>"
                            class="add-button"
                            onclick="openTaskForm(); return false;"
                        >
                            + Task
                        </a>

                    </div>

                </div>


                <div class="calendar-toolbar">

                    <div class="calendar-navigation">

                        <a
                            href="dashboard.php?view=<?php echo clean($view); ?>&year=<?php echo $previous_month->format("Y"); ?>&month=<?php echo $previous_month->format("n"); ?>"
                        >
                            &lsaquo;
                        </a>

                        <strong>
                            <?php echo clean($calendar_title); ?>
                        </strong>

                        <a
                            href="dashboard.php?view=<?php echo clean($view); ?>&year=<?php echo $next_month->format("Y"); ?>&month=<?php echo $next_month->format("n"); ?>"
                        >
                            &rsaquo;
                        </a>

                    </div>


                    <div class="view-switch">

                        <a
                            href="dashboard.php?view=month&year=<?php echo $calendar_year; ?>&month=<?php echo $calendar_month; ?>"
                            class="<?php echo $view === "month" ? "active" : ""; ?>"
                        >
                            Monthly
                        </a>

                        <a
                            href="dashboard.php?view=day&date=<?php echo clean($selected_date); ?>&year=<?php echo $calendar_year; ?>&month=<?php echo $calendar_month; ?>"
                            class="<?php echo $view === "day" ? "active" : ""; ?>"
                        >
                            Day
                        </a>

                        <a
                            href="dashboard.php?view=year&year=<?php echo $calendar_year; ?>"
                            class="<?php echo $view === "year" ? "active" : ""; ?>"
                        >
                            Yearly
                        </a>

                    </div>

                </div>


                <?php if ($view === "month") : ?>

                    <div class="cal-grid">

                        <div class="cal-header">Mo</div>
                        <div class="cal-header">Tu</div>
                        <div class="cal-header">We</div>
                        <div class="cal-header">Th</div>
                        <div class="cal-header">Fr</div>
                        <div class="cal-header">Sa</div>
                        <div class="cal-header">Su</div>

                        <?php

                        for ($i = 0; $i < $first_day_index; $i++) {

                            $previous_day = $previous_month->format("t") -
                                            $first_day_index +
                                            $i + 1;

                            echo '<div class="cal-cell muted">' .
                                 $previous_day .
                                 '</div>';
                        }

                        for ($day = 1; $day <= $days_in_month; $day++) {

                            $date_string = sprintf(
                                "%04d-%02d-%02d",
                                $calendar_year,
                                $calendar_month,
                                $day
                            );

                            $classes = ["cal-cell"];

                            if ($date_string === $today->format("Y-m-d")) {
                                $classes[] = "today";
                            }

                            if ($date_string === $selected_date) {
                                $classes[] = "selected";
                            }

                            if (isset($event_dates[$date_string])) {
                                $classes[] = "event-dot";
                            }

                            echo '<button type="button" class="' .
                                 implode(" ", $classes) .
                                 '" onclick="openDateAction(\'' .
                                 $date_string .
                                 '\')">' .
                                 $day .
                                 '</button>';
                        }

                        $total_cells =
                            $first_day_index +
                            $days_in_month;

                        $remaining_cells =
                            (7 - ($total_cells % 7)) % 7;

                        for ($i = 1; $i <= $remaining_cells; $i++) {
                            echo '<div class="cal-cell muted">' .
                                 $i .
                                 '</div>';
                        }

                        ?>

                    </div>


                <?php elseif ($view === "day") : ?>

                    <div class="day-view">

                        <div class="day-panel">

                            <h4>
                                Events —
                                <?php echo clean(date("d M Y", strtotime($selected_date))); ?>
                            </h4>

                            <?php if (empty($day_events)) : ?>

                                <div class="empty-state">
                                    No events on this date.
                                </div>

                            <?php else : ?>

                                <?php foreach ($day_events as $event) : ?>

                                    <div class="day-record">

                                        <strong>
                                            <?php echo clean($event["title"]); ?>
                                        </strong>

                                        <small>
                                            <?php
                                            echo $event["event_time"]
                                                ? clean(date("h:i A", strtotime($event["event_time"])))
                                                : "Time not set";
                                            ?>

                                            <?php if ($event["location"] !== "") : ?>
                                                · <?php echo clean($event["location"]); ?>
                                            <?php endif; ?>
                                        </small>

                                    </div>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </div>


                        <div class="day-panel">

                            <h4>
                                To-Do —
                                <?php echo clean(date("d M Y", strtotime($selected_date))); ?>
                            </h4>

                            <?php if (empty($day_todos)) : ?>

                                <div class="empty-state">
                                    No tasks due on this date.
                                </div>

                            <?php else : ?>

                                <?php foreach ($day_todos as $todo) : ?>

                                    <div class="day-record">

                                        <strong>
                                            <?php echo clean($todo["task_title"]); ?>
                                        </strong>

                                        <small>
                                            <?php echo clean($todo["priority"]); ?>
                                            ·
                                            <?php echo clean($todo["status"]); ?>

                                            <?php if ($todo["due_time"] !== null && $todo["due_time"] !== "") : ?>
                                                · <?php echo clean(date("h:i A", strtotime($todo["due_time"]))); ?>
                                            <?php endif; ?>
                                        </small>

                                    </div>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </div>

                    </div>


                <?php else : ?>

                    <div class="year-grid">

                        <?php

                        for ($m = 1; $m <= 12; $m++) {

                            $month_name = date(
                                "F",
                                strtotime(
                                    sprintf(
                                        "%04d-%02d-01",
                                        $calendar_year,
                                        $m
                                    )
                                )
                            );

                            echo '<div class="year-month">';
                            echo '<h4>' . clean($month_name) . '</h4>';
                            echo '<div class="year-mini">';

                            $month_days = cal_days_in_month(
                                CAL_GREGORIAN,
                                $m,
                                $calendar_year
                            );

                            $first = (int)date(
                                "N",
                                strtotime(
                                    sprintf(
                                        "%04d-%02d-01",
                                        $calendar_year,
                                        $m
                                    )
                                )
                            );

                            for ($blank = 1; $blank < $first; $blank++) {
                                echo '<span></span>';
                            }

                            for ($d = 1; $d <= $month_days; $d++) {

                                $date_string = sprintf(
                                    "%04d-%02d-%02d",
                                    $calendar_year,
                                    $m,
                                    $d
                                );

                                $has_data = isset($event_dates[$date_string]);
                                $is_today = ($date_string === $today->format("Y-m-d"));
                                $classes = [];

                                if ($has_data) {
                                    $classes[] = "has-data";
                                }

                                if ($is_today) {
                                    $classes[] = "today";
                                }

                                echo '<button type="button" class="year-date ' .
                                     implode(" ", $classes) .
                                     '" onclick="openDateAction(\'' .
                                     $date_string .
                                     '\')">' .
                                     $d .
                                     '</button>';
                            }

                            echo '</div>';
                            echo '</div>';
                        }

                        ?>

                    </div>

                <?php endif; ?>

            </section>


            <!-- =================================================
                 WHAT'S NEXT
            ================================================== -->

            <section class="summary-section">

                <div class="module-header">
                    <div class="module-title">What's Next?</div>
                    <span class="module-badge">Upcoming</span>
                </div>

                <div class="summary-grid">

                    <div class="summary-card full next-card">

                        <div class="summary-card-header">
                            <div class="summary-card-title">
                                Your next important item
                            </div>

                            <?php if ($next_item !== null) : ?>
                                <span class="module-badge">
                                    <?php echo clean($next_item["type"]); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if ($next_item === null) : ?>

                            <div class="today-empty">
                                You have no upcoming tasks or events.
                            </div>

                        <?php else : ?>

                            <div class="next-content">

                                <div class="next-icon">
                                    <?php echo $next_item["type"] === "Event" ? "E" : "✓"; ?>
                                </div>

                                <div>
                                    <strong>
                                        <?php echo clean($next_item["title"]); ?>
                                    </strong>

                                    <small>
                                        <?php
                                        echo clean(
                                            date("d M Y", strtotime($next_item["date"]))
                                        );

                                        if (!empty($next_item["time"])) {
                                            echo " · " .
                                                clean(
                                                    date("h:i A", strtotime($next_item["time"]))
                                                );
                                        }

                                        if (
                                            $next_item["type"] === "To-Do" &&
                                            !empty($next_item["priority"])
                                        ) {
                                            echo " · " . clean($next_item["priority"]) . " priority";
                                        }
                                        ?>
                                    </small>
                                </div>

                            </div>

                        <?php endif; ?>

                    </div>


                    <!-- TODAY'S TASK -->

                    <div class="summary-card">

                        <div class="summary-card-header">

                            <div class="summary-card-title">
                                Today's Task
                            </div>

                            <a href="todo.php" class="summary-card-link">
                                View All
                            </a>

                        </div>

                        <div class="summary-list">

                            <?php if (empty($today_todos)) : ?>

                                <div class="today-empty">
                                    No tasks for today.
                                </div>

                            <?php else : ?>

                                <?php foreach ($today_todos as $todo) : ?>

                                    <div class="summary-list-item">

                                        <span class="summary-list-marker"></span>

                                        <div class="summary-list-content">

                                            <strong>
                                                <?php echo clean($todo["task_title"]); ?>
                                            </strong>

                                            <small>
                                                <?php
                                                echo clean($todo["status"]);

                                                if (!empty($todo["due_time"])) {
                                                    echo " · " .
                                                        clean(
                                                            date("h:i A", strtotime($todo["due_time"]))
                                                        );
                                                }

                                                if (!empty($todo["priority"])) {
                                                    echo " · " . clean($todo["priority"]);
                                                }
                                                ?>
                                            </small>

                                        </div>

                                    </div>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </div>

                    </div>


                    <!-- TODAY'S EVENT -->

                    <div class="summary-card">

                        <div class="summary-card-header">

                            <div class="summary-card-title">
                                Today's Event
                            </div>

                            <a href="events.php" class="summary-card-link">
                                View All
                            </a>

                        </div>

                        <div class="summary-list">

                            <?php if (empty($today_events)) : ?>

                                <div class="today-empty">
                                    No events for today.
                                </div>

                            <?php else : ?>

                                <?php foreach ($today_events as $event) : ?>

                                    <div class="summary-list-item">

                                        <span class="summary-list-marker"></span>

                                        <div class="summary-list-content">

                                            <strong>
                                                <?php echo clean($event["title"]); ?>
                                            </strong>

                                            <small>
                                                <?php
                                                if (!empty($event["event_time"])) {
                                                    echo clean(
                                                        date("h:i A", strtotime($event["event_time"]))
                                                    );
                                                } else {
                                                    echo "Time not set";
                                                }

                                                if (!empty($event["location"])) {
                                                    echo " · " . clean($event["location"]);
                                                }
                                                ?>
                                            </small>

                                        </div>

                                    </div>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </div>

                    </div>


                    <!-- FINANCIAL SNAPSHOT -->

                    <div class="summary-card full">

                        <div class="summary-card-header">

                            <div class="summary-card-title">
                                Financial Snapshot
                            </div>

                            <a href="expenses.php" class="summary-card-link">
                                View Expenses
                            </a>

                        </div>

                        <div class="financial-grid">

                            <div class="financial-box">

                                <span>Total Income</span>

                                <strong>
                                    ₹<?php echo number_format($total_income, 2); ?>
                                </strong>

                            </div>

                            <div class="financial-box spent">

                                <span>Total Spent</span>

                                <strong>
                                    ₹<?php echo number_format($total_expenses, 2); ?>
                                </strong>

                            </div>

                            <div class="financial-box">

                                <span>Balance</span>

                                <strong>
                                    ₹<?php echo number_format($remaining_balance, 2); ?>
                                </strong>

                            </div>

                            <div class="financial-box today-spent">

                                <span>Spent Today</span>

                                <strong>
                                    ₹<?php echo number_format($today_expenses, 2); ?>
                                </strong>

                            </div>

                        </div>

                    </div>


                    <!-- HOLIDAYS OF THE SELECTED MONTH -->

                    <section class="summary-card holiday-summary-card">

                        <div class="summary-card-header">

                            <div class="summary-card-title">
                                Holidays
                            </div>

                            <span class="module-badge">
                                <?php echo clean($month_start->format("F Y")); ?>
                            </span>

                        </div>

                        <?php if (empty($monthly_holidays)) : ?>

                            <div class="today-empty">
                                No government holidays in
                                <?php echo clean($month_start->format("F Y")); ?>.
                            </div>

                        <?php else : ?>

                            <div class="holiday-summary-list">

                                <?php foreach ($monthly_holidays as $holiday_date => $holiday_name) : ?>

                                    <?php
                                    $holiday_datetime = new DateTime($holiday_date);
                                    $is_long_weekend = isset($long_weekend_dates[$holiday_date]);
                                    ?>

                                    <div class="holiday-item">

                                        <div class="holiday-date">

                                            <?php echo clean($holiday_datetime->format("M")); ?>

                                            <span>
                                                <?php echo clean($holiday_datetime->format("d")); ?>
                                            </span>

                                        </div>

                                        <div class="holiday-info">

                                            <strong>
                                                <?php echo clean($holiday_name); ?>
                                            </strong>

                                            <small>
                                                <?php echo clean($holiday_datetime->format("l")); ?>
                                                · Government Holiday
                                            </small>

                                        </div>

                                        <?php if ($is_long_weekend) : ?>

                                            <span class="long-weekend-badge">
                                                Long Weekend
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        <?php endif; ?>

                    </section>

                </div>

            </section>

        </main>

    </div>


    <!-- =====================================================
         CALENDAR DATE ACTION POPUP
    ===================================================== -->

    <div
        class="date-action-modal"
        id="dateActionModal"
        onclick="closeDateActionFromBackground(event)"
    >

        <div
            class="date-action-card"
            onclick="event.stopPropagation()"
        >

            <h3 id="dateActionTitle">Add to this date</h3>
            <p id="dateActionText">Would you like to add a To-Do or an Event?</p>

            <div
                class="date-action-records"
                id="dateActionRecords"
            ></div>

            <div class="date-action-buttons">
                <button
                    type="button"
                    class="date-action-button"
                    onclick="chooseDateAction('todo')"
                >
                    + Add To-Do
                </button>

                <button
                    type="button"
                    class="date-action-button"
                    onclick="chooseDateAction('event')"
                >
                    + Add Event
                </button>
            </div>

            <button
                type="button"
                class="date-action-cancel"
                onclick="closeDateAction()"
            >
                Cancel
            </button>

        </div>

    </div>


    <!-- =====================================================
         TASK POPUP
    ===================================================== -->

    <div
        class="modal"
        id="taskModal"
        onclick="closeModalFromBackground(event)"
    >

        <div
            class="modal-card"
            onclick="event.stopPropagation()"
        >

            <div class="modal-header">

                <h2>
                    Task
                </h2>

                <button
                    type="button"
                    class="close-modal"
                    onclick="closeTaskForm()"
                >
                    &times;
                </button>

            </div>


            <div class="type-switch">

                <button
                    type="button"
                    id="typeTodoButton"
                    onclick="switchTaskType('todo')"
                >
                    To-Do List
                </button>

                <button
                    type="button"
                    id="typeEventButton"
                    onclick="switchTaskType('event')"
                >
                    Event
                </button>

                <button
                    type="button"
                    id="typeExpenseButton"
                    onclick="switchTaskType('expense')"
                >
                    Expense
                </button>

            </div>


            <!-- TODO FORM -->

            <div
                class="form-panel"
                id="todoPanel"
            >

                <form method="post">

                    <div class="form-grid">

                        <div class="form-group">
                            <label>Task Title</label>
                            <input
                                type="text"
                                name="task_title"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="Pending">Pending</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Due Date</label>
                            <input
                                type="date"
                                id="todoDueDate"
                                name="due_date"
                                value="<?php echo date("Y-m-d"); ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label>Due Time</label>
                            <input
                                type="time"
                                name="due_time"
                            >
                        </div>

                        <div class="form-group">
                            <label>Priority</label>
                            <select name="priority">
                                <option value="Low">Low</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="High">High</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Category</label>
                            <input
                                type="text"
                                name="todo_category"
                            >
                        </div>

                        <div class="form-group full">
                            <label>Description</label>
                            <textarea
                                name="todo_description"
                                placeholder="Add additional details..."
                            ></textarea>
                        </div>

                    </div>


                    <div class="form-submit">

                        <button
                            type="button"
                            class="secondary-submit"
                            onclick="closeTaskForm()"
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            name="add_todo"
                            class="primary-submit"
                        >
                            Save To-Do
                        </button>

                    </div>

                </form>

            </div>


            <!-- EVENT FORM -->

            <div
                class="form-panel"
                id="eventPanel"
            >

                <form method="post">

                    <div class="form-grid">

                        <div class="form-group">
                            <label>Event Title</label>
                            <input
                                type="text"
                                name="event_title"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label>Category</label>
                            <input
                                type="text"
                                name="event_category"
                            >
                        </div>

                        <div class="form-group">
                            <label>Date</label>
                            <input
                                type="date"
                                id="eventDate"
                                name="event_date"
                                value="<?php echo date("Y-m-d"); ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label>Time</label>
                            <input
                                type="time"
                                name="event_time"
                            >
                        </div>

                        <div class="form-group full">
                            <label>Location</label>
                            <input
                                type="text"
                                name="location"
                            >
                        </div>

                        <div class="form-group full">
                            <label>Description</label>
                            <textarea
                                name="event_description"
                                placeholder="Add event details..."
                            ></textarea>
                        </div>

                    </div>


                    <div class="form-submit">

                        <button
                            type="button"
                            class="secondary-submit"
                            onclick="closeTaskForm()"
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            name="add_event"
                            class="primary-submit"
                        >
                            Save Event
                        </button>

                    </div>

                </form>

            </div>


            <!-- EXPENSE FORM -->

            <div
                class="form-panel"
                id="expensePanel"
            >

                <form method="post">

                    <div class="form-grid">

                        <div class="form-group">
                            <label>Expense Name</label>
                            <input
                                type="text"
                                name="expense_name"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label>Category</label>
                            <input
                                type="text"
                                name="expense_category"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label>Amount</label>
                            <input
                                type="number"
                                name="amount"
                                min="0.01"
                                step="0.01"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label>Date</label>
                            <input
                                type="date"
                                name="expense_date"
                                value="<?php echo date("Y-m-d"); ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label>Payment Method</label>
                            <input
                                type="text"
                                name="payment_method"
                                placeholder="Cash / Card / UPI"
                            >
                        </div>

                        <div class="form-group">
                            <label>Spending From Income</label>
                            <input
                                type="text"
                                name="spending_from_income"
                                placeholder="Enter income source"
                            >
                        </div>

                        <div class="form-group full">
                            <label>Description</label>
                            <textarea
                                name="expense_description"
                                placeholder="What was this expense for?"
                            ></textarea>
                        </div>

                    </div>


                    <div class="form-submit">

                        <button
                            type="button"
                            class="secondary-submit"
                            onclick="closeTaskForm()"
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            name="add_expense"
                            class="primary-submit"
                        >
                            Save Expense
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>


    <script>

        const taskModal = document.getElementById("taskModal");

        const todoPanel = document.getElementById("todoPanel");
        const eventPanel = document.getElementById("eventPanel");
        const expensePanel = document.getElementById("expensePanel");

        const todoButton = document.getElementById("typeTodoButton");
        const eventButton = document.getElementById("typeEventButton");
        const expenseButton = document.getElementById("typeExpenseButton");


        let selectedCalendarDate = "";

        const calendarDateData = <?php
            echo json_encode(
                [
                    "events" => $events,
                    "todos" => $todos
                ],
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_HEX_TAG |
                JSON_HEX_AMP |
                JSON_HEX_APOS |
                JSON_HEX_QUOT
            );
        ?>;

        function formatCalendarDate(dateString) {
            const parts = dateString.split("-");
            if (parts.length !== 3) return dateString;

            const date = new Date(
                Number(parts[0]),
                Number(parts[1]) - 1,
                Number(parts[2])
            );

            return date.toLocaleDateString("en-IN", {
                day: "2-digit",
                month: "long",
                year: "numeric"
            });
        }

        function escapeHtml(value) {
            return String(value ?? "")
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function openDateAction(dateString) {
            selectedCalendarDate = dateString;

            const modal = document.getElementById("dateActionModal");
            const title = document.getElementById("dateActionTitle");
            const text = document.getElementById("dateActionText");
            const records = document.getElementById("dateActionRecords");

            const dateEvents = (calendarDateData.events || []).filter(
                event => event.event_date === dateString
            );

            const dateTodos = (calendarDateData.todos || []).filter(
                todo => todo.due_date === dateString
            );

            if (title) {
                title.textContent = formatCalendarDate(dateString);
            }

            if (text) {
                text.textContent =
                    dateEvents.length || dateTodos.length
                        ? "Here is what you have on this date. You can also add something new."
                        : "Nothing is scheduled for this date. Would you like to add a To-Do or an Event?";
            }

            if (records) {
                let html = "";

                dateEvents.forEach(event => {
                    const time = event.event_time
                        ? "Time: " + escapeHtml(event.event_time)
                        : "Time not set";

                    const location = event.location
                        ? " • " + escapeHtml(event.location)
                        : "";

                    html += `
                        <div class="date-action-record event-record">
                            <strong>Event: ${escapeHtml(event.title)}</strong>
                            <small>${time}${location}</small>
                        </div>
                    `;
                });

                dateTodos.forEach(todo => {
                    const time = todo.due_time
                        ? "Time: " + escapeHtml(todo.due_time)
                        : "Time not set";

                    const status = todo.status
                        ? " • Status: " + escapeHtml(todo.status)
                        : "";

                    html += `
                        <div class="date-action-record todo-record">
                            <strong>To-Do: ${escapeHtml(todo.task_title)}</strong>
                            <small>${time}${status}</small>
                        </div>
                    `;
                });

                records.innerHTML = html || `
                    <div class="date-action-empty">
                        No events or To-Do items on this date.
                    </div>
                `;
            }

            modal.classList.add("show");
            document.body.style.overflow = "hidden";
        }

        function closeDateAction() {
            const modal = document.getElementById("dateActionModal");

            modal.classList.remove("show");
            document.body.style.overflow = "";
            selectedCalendarDate = "";
        }

        function closeDateActionFromBackground(event) {
            const modal = document.getElementById("dateActionModal");

            if (event.target === modal) {
                closeDateAction();
            }
        }

        function chooseDateAction(type) {
            const date = selectedCalendarDate;

            closeDateAction();

            if (date) {
                openTaskForm(type, date);
            }
        }


        function openTaskForm(type = "todo", selectedDate = "") {

            taskModal.classList.add("show");

            switchTaskType(type);

            if (selectedDate) {
                const todoDate = document.getElementById("todoDueDate");
                const eventDate = document.getElementById("eventDate");

                if (todoDate) {
                    todoDate.value = selectedDate;
                }

                if (eventDate) {
                    eventDate.value = selectedDate;
                }
            }

            document.body.style.overflow = "hidden";
        }


        function closeTaskForm() {

            taskModal.classList.remove("show");

            document.body.style.overflow = "";

        }


        function closeModalFromBackground(event) {

            if (event.target === taskModal) {
                closeTaskForm();
            }

        }


        function switchTaskType(type) {

            todoPanel.classList.remove("active");
            eventPanel.classList.remove("active");
            expensePanel.classList.remove("active");

            todoButton.classList.remove("active");
            eventButton.classList.remove("active");
            expenseButton.classList.remove("active");


            if (type === "event") {

                eventPanel.classList.add("active");
                eventButton.classList.add("active");

            } else if (type === "expense") {

                expensePanel.classList.add("active");
                expenseButton.classList.add("active");

            } else {

                todoPanel.classList.add("active");
                todoButton.classList.add("active");

            }

        }


        document.addEventListener("keydown", function(event) {

            if (event.key === "Escape") {
                const dateActionModal = document.getElementById("dateActionModal");

                if (dateActionModal.classList.contains("show")) {
                    closeDateAction();
                } else {
                    closeTaskForm();
                }
            }

        });


        <?php if ($edit_record === null && $message_type === "error") : ?>

            // Keep the dashboard clean after an error.
            // The user can reopen the Task popup manually.

        <?php endif; ?>

    </script>

</body>
</html>
