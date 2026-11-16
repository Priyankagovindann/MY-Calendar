<?php
/*
 * Use the authenticated PHP session created by login.php.
 */
session_start();

/*
 * Do not allow todo.php to run without a valid logged-in user.
 */
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


/* =========================================================
   MESSAGE VARIABLES
========================================================= */

$message = "";
$message_type = "";


/* =========================================================
   ADD TASK
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_task'])) {

    $task_title = trim($_POST['task_title'] ?? '');
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

    $hour = $_POST['due_hour'] ?? '';
    $minute = $_POST['due_minute'] ?? '';
    $ampm = $_POST['due_ampm'] ?? '';

    $priority = $_POST['priority'] ?? 'Medium';
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');

    /*
     * Convert 12-hour AM/PM time to MySQL TIME format.
     * Example:
     * 05 : 30 PM
     * becomes
     * 17:30:00
     */

    $due_time = null;

    if (
        !empty($hour) &&
        !empty($minute) &&
        !empty($ampm)
    ) {

        $hour_24 = (int)$hour;

        if ($ampm === "AM") {

            if ($hour_24 === 12) {
                $hour_24 = 0;
            }

        } else {

            if ($hour_24 !== 12) {
                $hour_24 += 12;
            }
        }

        $due_time = sprintf(
            "%02d:%02d:00",
            $hour_24,
            (int)$minute
        );
    }


    if ($task_title === '') {

        $message = "Please enter a task title.";
        $message_type = "error";

    } else {

        $stmt = $con->prepare(
            "INSERT INTO todo_list
            (
                user_id,
                task_title,
                due_date,
                due_time,
                priority,
                category,
                description,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')"
        );

        $stmt->bind_param(
            "issssss",
            $user_id,
            $task_title,
            $due_date,
            $due_time,
            $priority,
            $category,
            $description
        );

        if ($stmt->execute()) {

            /*
             * Redirect after successful save.
             *
             * This prevents duplicate form submission
             * when the user refreshes the page.
             *
             * The form is closed because the page loads
             * without the "open" state.
             */

            header("Location: todo.php?added=1");
            exit();

        } else {

            $message = "Unable to add task.";
            $message_type = "error";
        }

        $stmt->close();
    }
}


/* =========================================================
   SUCCESS MESSAGE AFTER REDIRECT
========================================================= */

if (isset($_GET['added'])) {

    $message = "Task added successfully.";
    $message_type = "success";
}


/* =========================================================
   MARK TASK COMPLETED
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST['complete_task'])
) {

    $task_id = (int)$_POST['task_id'];

    $stmt = $con->prepare(
        "UPDATE todo_list
         SET status = 'Completed'
         WHERE id = ?
         AND user_id = ?"
    );

    $stmt->bind_param(
        "ii",
        $task_id,
        $user_id
    );

    $stmt->execute();

    $stmt->close();

    header("Location: todo.php");
    exit();
}


/* =========================================================
   MARK TASK AS PENDING
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST['pending_task'])
) {

    $task_id = (int)$_POST['task_id'];

    $stmt = $con->prepare(
        "UPDATE todo_list
         SET status = 'Pending'
         WHERE id = ?
         AND user_id = ?"
    );

    $stmt->bind_param(
        "ii",
        $task_id,
        $user_id
    );

    $stmt->execute();

    $stmt->close();

    header("Location: todo.php");
    exit();
}


/* =========================================================
   DELETE TASK
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST['delete_task'])
) {

    $task_id = (int)$_POST['task_id'];

    $stmt = $con->prepare(
        "DELETE FROM todo_list
         WHERE id = ?
         AND user_id = ?"
    );

    $stmt->bind_param(
        "ii",
        $task_id,
        $user_id
    );

    $stmt->execute();

    $stmt->close();

    header("Location: todo.php");
    exit();
}


/* =========================================================
   AUTOMATICALLY COMPLETE OVERDUE TASKS
========================================================= */

$current_date = date("Y-m-d");
$current_time = date("H:i:s");


$stmt = $con->prepare(
    "UPDATE todo_list
     SET status = 'Completed'
     WHERE user_id = ?
     AND status = 'Pending'
     AND due_date IS NOT NULL
     AND due_time IS NOT NULL
     AND (
         due_date < ?
         OR
         (
             due_date = ?
             AND due_time <= ?
         )
     )"
);

$stmt->bind_param(
    "isss",
    $user_id,
    $current_date,
    $current_date,
    $current_time
);

$stmt->execute();

$stmt->close();


/* =========================================================
   FETCH TASKS
========================================================= */

$pending_tasks = [];
$completed_tasks = [];


$stmt = $con->prepare(
    "SELECT
        id,
        task_title,
        due_date,
        due_time,
        priority,
        category,
        description,
        status
     FROM todo_list
     WHERE user_id = ?
     ORDER BY
        CASE
            WHEN status = 'Pending' THEN 0
            ELSE 1
        END,
        due_date ASC,
        due_time ASC,
        id DESC"
);

$stmt->bind_param(
    "i",
    $user_id
);

$stmt->execute();

$result = $stmt->get_result();


while ($row = $result->fetch_assoc()) {

    if ($row['status'] === 'Completed') {

        $completed_tasks[] = $row;

    } else {

        $pending_tasks[] = $row;
    }
}


$stmt->close();

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>To-Do List | MyCalendar</title>


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

        .todo-content {
            padding: 30px 36px;
        }


        .todo-top {
            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 24px;
        }


        .todo-heading h2 {
            font-size: 24px;

            color: var(--olive-dark);

            margin-bottom: 5px;
        }


        .todo-heading p {
            font-size: 13px;

            color: var(--text-muted);
        }


        /* =====================================================
           ADD TASK BUTTON
        ===================================================== */

        .add-task-btn {
            border: none;

            background-color: var(--olive-primary);

            color: var(--white);

            padding: 12px 20px;

            border-radius: 8px;

            font-size: 14px;

            font-weight: 700;

            cursor: pointer;

            transition: all 0.2s ease;
        }


        .add-task-btn:hover {
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
           ADD TASK FORM
        ===================================================== */

        .add-task-card {
            background-color: var(--card-beige);

            border: 1px solid var(--border-beige);

            border-radius: 12px;

            padding: 25px;

            margin-bottom: 24px;

            box-shadow:
                0 4px 16px rgba(55, 70, 33, 0.04);

            display: none;
        }


        .add-task-card.show {
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
            min-height: 100px;

            resize: vertical;
        }


        /* =====================================================
           TIME ROW
        ===================================================== */

        .time-row {
            display: grid;

            grid-template-columns: 1fr 1fr 1fr;

            gap: 8px;
        }


        .time-row select {
            width: 100%;
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
           TODO CARD
        ===================================================== */

        .todo-card {
            background-color: var(--card-beige);

            border: 1px solid var(--border-beige);

            border-radius: 12px;

            padding: 24px;

            margin-bottom: 24px;

            box-shadow:
                0 4px 16px rgba(55, 70, 33, 0.04);
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


        .task-count {
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
           TASK LIST
        ===================================================== */

        .task-list {
            display: flex;

            flex-direction: column;

            gap: 12px;
        }


        .task-item {
            display: flex;

            align-items: center;

            gap: 15px;

            background-color: var(--white);

            border: 1px solid var(--border-beige);

            border-radius: 8px;

            padding: 15px;
        }


        .task-check {
            width: 32px;

            height: 32px;

            border-radius: 50%;

            background-color: var(--olive-tint);

            color: var(--olive-dark);

            border: none;

            cursor: pointer;

            font-size: 16px;

            display: grid;

            place-items: center;

            flex-shrink: 0;
        }


        .task-check:hover {
            background-color: var(--olive-primary);

            color: var(--white);
        }


        .task-info {
            flex: 1;

            min-width: 0;
        }


        .task-info h4 {
            font-size: 15px;

            color: var(--text-dark);

            font-weight: 700;

            margin-bottom: 5px;
        }


        /* =====================================================
           DESCRIPTION
        ===================================================== */

        .task-description {
            font-size: 13px;

            color: var(--text-muted);

            line-height: 1.5;

            margin: 5px 0 8px;

            white-space: normal;

            word-break: break-word;
        }


        /* =====================================================
           TASK DETAILS
        ===================================================== */

        .task-details-row {
            display: flex;

            align-items: center;

            gap: 8px;

            flex-wrap: wrap;
        }


        .task-detail {
            display: inline-block;

            font-size: 10px;

            font-weight: 700;

            color: var(--text-muted);

            background-color: #F2EEE8;

            padding: 3px 8px;

            border-radius: 10px;
        }


        .priority {
            display: inline-block;

            font-size: 10px;

            font-weight: 700;

            padding: 3px 8px;

            border-radius: 10px;
        }


        .priority.high {
            background-color: #F8E7E6;

            color: var(--error);
        }


        .priority.medium {
            background-color: #F5EEDB;

            color: var(--warning);
        }


        .priority.low {
            background-color: var(--olive-tint);

            color: var(--olive-dark);
        }


        .category-label {
            display: inline-block;

            font-size: 10px;

            font-weight: 700;

            color: var(--olive-dark);

            background-color: var(--olive-tint);

            padding: 3px 8px;

            border-radius: 10px;
        }


        /* =====================================================
           DELETE BUTTON
        ===================================================== */

        .delete-form {
            margin-left: auto;
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


        /* =====================================================
           COMPLETED TASK
        ===================================================== */

        .completed-task {
            opacity: 0.72;
        }


        .completed-task .task-info h4 {
            text-decoration: line-through;

            color: var(--text-muted);
        }


        .completed-task .task-check {
            background-color: var(--olive-primary);

            color: var(--white);
        }


        /* =====================================================
           MARK PENDING
        ===================================================== */

        .pending-form {
            margin-left: auto;
        }


        .pending-btn {
            border: none;

            background-color: var(--olive-tint);

            color: var(--olive-dark);

            padding: 7px 10px;

            border-radius: 6px;

            font-size: 11px;

            font-weight: 700;

            cursor: pointer;
        }


        .pending-btn:hover {
            background-color: var(--olive-primary);

            color: var(--white);
        }


        /* =====================================================
           NO TASKS
        ===================================================== */

        .no-tasks {
            text-align: center;

            padding: 45px 20px;

            color: var(--text-muted);
        }


        .no-tasks-icon {
            font-size: 35px;

            margin-bottom: 12px;
        }


        .no-tasks h4 {
            color: var(--olive-dark);

            font-size: 16px;

            margin-bottom: 6px;
        }


        .no-tasks p {
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


            .todo-content {
                padding: 25px 20px;
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


            .todo-top {
                align-items: flex-start;

                gap: 15px;

                flex-direction: column;
            }


            .add-task-btn {
                width: 100%;
            }


            .task-item {
                align-items: flex-start;
            }


            .time-row {
                grid-template-columns: 1fr 1fr 1fr;
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


            <li class="nav-item active">

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


        <!-- HEADER -->

        <header>

            <div class="header-left">

                <h1>
                    To-Do List
                </h1>

                <p>
                    Organize your tasks and stay on top of your work
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


        <!-- =====================================================
             TODO CONTENT
        ===================================================== -->

        <main class="todo-content">


            <div class="todo-top">


                <div class="todo-heading">

                    <h2>
                        My Tasks
                    </h2>


                    <p>
                        Keep track of your pending and completed tasks.
                    </p>

                </div>


                <button
                    type="button"
                    class="add-task-btn"
                    onclick="openTaskForm()"
                >
                    + Add Task
                </button>


            </div>


            <!-- MESSAGE -->

            <?php if (!empty($message)) : ?>

                <div class="message <?php echo $message_type; ?>">

                    <?php
                    echo htmlspecialchars($message);
                    ?>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 ADD TASK FORM
            ================================================= -->

            <section
                class="add-task-card"
                id="addTaskCard"
            >


                <div class="form-header">

                    <h3>
                        Add New Task
                    </h3>


                    <button
                        type="button"
                        class="close-form"
                        onclick="closeTaskForm()"
                    >
                        &times;
                    </button>

                </div>


                <form method="POST">


                    <div class="form-grid">


                        <!-- TASK TITLE -->

                        <div class="form-group full">

                            <label for="task_title">
                                Task Title *
                            </label>


                            <input
                                type="text"
                                id="task_title"
                                name="task_title"
                                placeholder="Enter task title"
                                required
                            >

                        </div>


                        <!-- DUE DATE -->

                        <div class="form-group">

                            <label for="due_date">
                                Due Date
                            </label>


                            <input
                                type="date"
                                id="due_date"
                                name="due_date"
                            >

                        </div>


                        <!-- DUE TIME -->

                        <div class="form-group">

                            <label>
                                Due Time
                            </label>


                            <div class="time-row">


                                <!-- HOUR -->

                                <select
                                    name="due_hour"
                                    id="due_hour"
                                >

                                    <option value="">
                                        Hour
                                    </option>


                                    <?php for ($i = 1; $i <= 12; $i++) : ?>

                                        <option
                                            value="<?php
                                            echo sprintf(
                                                "%02d",
                                                $i
                                            );
                                            ?>"
                                        >
                                            <?php
                                            echo sprintf(
                                                "%02d",
                                                $i
                                            );
                                            ?>
                                        </option>

                                    <?php endfor; ?>

                                </select>


                                <!-- MINUTE -->

                                <select
                                    name="due_minute"
                                    id="due_minute"
                                >

                                    <option value="">
                                        Min
                                    </option>


                                    <?php
                                    for (
                                        $i = 0;
                                        $i < 60;
                                        $i += 5
                                    ) :
                                    ?>

                                        <option
                                            value="<?php
                                            echo sprintf(
                                                "%02d",
                                                $i
                                            );
                                            ?>"
                                        >
                                            <?php
                                            echo sprintf(
                                                "%02d",
                                                $i
                                            );
                                            ?>
                                        </option>

                                    <?php endfor; ?>

                                </select>


                                <!-- AM / PM -->

                                <select
                                    name="due_ampm"
                                    id="due_ampm"
                                >

                                    <option value="">
                                        AM/PM
                                    </option>

                                    <option value="AM">
                                        AM
                                    </option>

                                    <option value="PM">
                                        PM
                                    </option>

                                </select>


                            </div>

                        </div>


                        <!-- PRIORITY -->

                        <div class="form-group">

                            <label for="priority">
                                Priority
                            </label>


                            <select
                                id="priority"
                                name="priority"
                            >

                                <option value="Low">
                                    Low
                                </option>


                                <option
                                    value="Medium"
                                    selected
                                >
                                    Medium
                                </option>


                                <option value="High">
                                    High
                                </option>

                            </select>

                        </div>


                        <!-- CATEGORY -->

                        <div class="form-group">

                            <label for="category">
                                Category
                            </label>


                            <select
                                id="category"
                                name="category"
                            >

                                <option value="">
                                    Select category
                                </option>


                                <option value="Work">
                                    Work
                                </option>


                                <option value="Personal">
                                    Personal
                                </option>


                                <option value="Study">
                                    Study
                                </option>


                                <option value="Shopping">
                                    Shopping
                                </option>


                                <option value="Other">
                                    Other
                                </option>

                            </select>

                        </div>


                        <!-- DESCRIPTION -->

                        <div class="form-group full">

                            <label for="description">
                                Description
                            </label>


                            <textarea
                                id="description"
                                name="description"
                                placeholder="Add details about this task..."
                            ></textarea>

                        </div>

                    </div>


                    <!-- SAVE -->

                    <div class="form-actions">

                        <button
                            type="submit"
                            name="add_task"
                            class="save-btn"
                        >
                            Save Task
                        </button>

                    </div>


                </form>

            </section>


            <!-- =================================================
                 PENDING TASKS
            ================================================= -->

            <section class="todo-card">


                <div class="card-header">

                    <h3>
                        Pending Tasks
                    </h3>


                    <span class="task-count">

                        <?php
                        echo count($pending_tasks);
                        ?>

                        <?php
                        echo count($pending_tasks) == 1
                            ? "Task"
                            : "Tasks";
                        ?>

                    </span>

                </div>


                <?php if (!empty($pending_tasks)) : ?>


                    <div class="task-list">


                        <?php foreach ($pending_tasks as $task) : ?>


                            <div class="task-item">


                                <!-- COMPLETE -->

                                <form method="POST">

                                    <input
                                        type="hidden"
                                        name="task_id"
                                        value="<?php
                                        echo $task['id'];
                                        ?>"
                                    >


                                    <button
                                        type="submit"
                                        name="complete_task"
                                        class="task-check"
                                        title="Mark as completed"
                                    >
                                        ✓
                                    </button>

                                </form>


                                <!-- TASK INFO -->

                                <div class="task-info">


                                    <h4>

                                        <?php
                                        echo htmlspecialchars(
                                            $task['task_title']
                                        );
                                        ?>

                                    </h4>


                                    <?php
                                    if (
                                        !empty(
                                            $task['description']
                                        )
                                    ) :
                                    ?>

                                        <p class="task-description">

                                            <?php
                                            echo htmlspecialchars(
                                                $task['description']
                                            );
                                            ?>

                                        </p>

                                    <?php endif; ?>


                                    <div class="task-details-row">


                                        <!-- DATE -->

                                        <?php
                                        if (
                                            !empty(
                                                $task['due_date']
                                            )
                                        ) :
                                        ?>

                                            <span class="task-detail">

                                                Due:
                                                <?php
                                                echo date(
                                                    "d M Y",
                                                    strtotime(
                                                        $task['due_date']
                                                    )
                                                );
                                                ?>

                                            </span>

                                        <?php endif; ?>


                                        <!-- TIME -->

                                        <?php
                                        if (
                                            !empty(
                                                $task['due_time']
                                            )
                                        ) :
                                        ?>

                                            <span class="task-detail">

                                                <?php
                                                echo date(
                                                    "h:i A",
                                                    strtotime(
                                                        $task['due_time']
                                                    )
                                                );
                                                ?>

                                            </span>

                                        <?php endif; ?>


                                        <!-- PRIORITY -->

                                        <span
                                            class="priority
                                            <?php
                                            echo strtolower(
                                                $task['priority']
                                            );
                                            ?>"
                                        >

                                            <?php
                                            echo htmlspecialchars(
                                                $task['priority']
                                            );
                                            ?>

                                        </span>


                                        <!-- CATEGORY -->

                                        <?php
                                        if (
                                            !empty(
                                                $task['category']
                                            )
                                        ) :
                                        ?>

                                            <span
                                                class="category-label"
                                            >

                                                <?php
                                                echo htmlspecialchars(
                                                    $task['category']
                                                );
                                                ?>

                                            </span>

                                        <?php endif; ?>


                                    </div>

                                </div>


                                <!-- DELETE -->

                                <form
                                    method="POST"
                                    class="delete-form"
                                    onsubmit="
                                    return confirm(
                                        'Are you sure you want to delete this task?'
                                    );
                                    "
                                >

                                    <input
                                        type="hidden"
                                        name="task_id"
                                        value="<?php
                                        echo $task['id'];
                                        ?>"
                                    >


                                    <button
                                        type="submit"
                                        name="delete_task"
                                        class="delete-btn"
                                        title="Delete Task"
                                    >
                                        &#128465;
                                    </button>

                                </form>


                            </div>


                        <?php endforeach; ?>


                    </div>


                <?php else : ?>


                    <div class="no-tasks">

                        <div class="no-tasks-icon">
                            &#9745;
                        </div>


                        <h4>
                            No pending tasks
                        </h4>


                        <p>
                            Click "Add Task" to create your first task.
                        </p>

                    </div>


                <?php endif; ?>


            </section>


            <!-- =================================================
                 COMPLETED TASKS
                 ONLY APPEARS WHEN THERE IS A COMPLETED TASK
            ================================================= -->

            <?php if (!empty($completed_tasks)) : ?>


                <section class="todo-card">


                    <div class="card-header">

                        <h3>
                            Completed Tasks
                        </h3>


                        <span class="task-count">

                            <?php
                            echo count($completed_tasks);
                            ?>

                            <?php
                            echo count($completed_tasks) == 1
                                ? "Task"
                                : "Tasks";
                            ?>

                        </span>

                    </div>


                    <div class="task-list">


                        <?php foreach ($completed_tasks as $task) : ?>


                            <div class="task-item completed-task">


                                <!-- COMPLETED ICON -->

                                <div class="task-check">
                                    ✓
                                </div>


                                <!-- TASK INFO -->

                                <div class="task-info">


                                    <h4>

                                        <?php
                                        echo htmlspecialchars(
                                            $task['task_title']
                                        );
                                        ?>

                                    </h4>


                                    <?php
                                    if (
                                        !empty(
                                            $task['description']
                                        )
                                    ) :
                                    ?>

                                        <p class="task-description">

                                            <?php
                                            echo htmlspecialchars(
                                                $task['description']
                                            );
                                            ?>

                                        </p>

                                    <?php endif; ?>


                                    <div class="task-details-row">


                                        <!-- DATE -->

                                        <?php
                                        if (
                                            !empty(
                                                $task['due_date']
                                            )
                                        ) :
                                        ?>

                                            <span class="task-detail">

                                                Due:
                                                <?php
                                                echo date(
                                                    "d M Y",
                                                    strtotime(
                                                        $task['due_date']
                                                    )
                                                );
                                                ?>

                                            </span>

                                        <?php endif; ?>


                                        <!-- TIME -->

                                        <?php
                                        if (
                                            !empty(
                                                $task['due_time']
                                            )
                                        ) :
                                        ?>

                                            <span class="task-detail">

                                                <?php
                                                echo date(
                                                    "h:i A",
                                                    strtotime(
                                                        $task['due_time']
                                                    )
                                                );
                                                ?>

                                            </span>

                                        <?php endif; ?>


                                        <!-- PRIORITY -->

                                        <span
                                            class="priority
                                            <?php
                                            echo strtolower(
                                                $task['priority']
                                            );
                                            ?>"
                                        >

                                            <?php
                                            echo htmlspecialchars(
                                                $task['priority']
                                            );
                                            ?>

                                        </span>


                                        <!-- CATEGORY -->

                                        <?php
                                        if (
                                            !empty(
                                                $task['category']
                                            )
                                        ) :
                                        ?>

                                            <span
                                                class="category-label"
                                            >

                                                <?php
                                                echo htmlspecialchars(
                                                    $task['category']
                                                );
                                                ?>

                                            </span>

                                        <?php endif; ?>


                                    </div>

                                </div>


                                <!-- MARK PENDING -->

                                <form
                                    method="POST"
                                    class="pending-form"
                                >

                                    <input
                                        type="hidden"
                                        name="task_id"
                                        value="<?php
                                        echo $task['id'];
                                        ?>"
                                    >


                                    <button
                                        type="submit"
                                        name="pending_task"
                                        class="pending-btn"
                                    >
                                        Mark Pending
                                    </button>

                                </form>


                                <!-- DELETE -->

                                <form
                                    method="POST"
                                    class="delete-form"
                                    onsubmit="
                                    return confirm(
                                        'Are you sure you want to delete this task?'
                                    );
                                    "
                                >

                                    <input
                                        type="hidden"
                                        name="task_id"
                                        value="<?php
                                        echo $task['id'];
                                        ?>"
                                    >


                                    <button
                                        type="submit"
                                        name="delete_task"
                                        class="delete-btn"
                                        title="Delete Task"
                                    >
                                        &#128465;
                                    </button>

                                </form>


                            </div>


                        <?php endforeach; ?>


                    </div>


                </section>


            <?php endif; ?>


        </main>


    </div>


    <!-- =====================================================
         JAVASCRIPT
    ===================================================== -->

    <script>


        /* =====================================================
           OPEN ADD TASK FORM
        ===================================================== */

        function openTaskForm() {

            const form =
                document.getElementById(
                    "addTaskCard"
                );

            form.classList.add("show");


            form.scrollIntoView({
                behavior: "smooth",
                block: "start"
            });

        }


        /* =====================================================
           CLOSE ADD TASK FORM
        ===================================================== */

        function closeTaskForm() {

            const form =
                document.getElementById(
                    "addTaskCard"
                );

            form.classList.remove("show");

        }


        /* =====================================================
           DATE RESTRICTION
        ===================================================== */

        const dueDateInput =
            document.getElementById(
                "due_date"
            );


        if (dueDateInput) {

            const today =
                new Date()
                    .toISOString()
                    .split("T")[0];

            dueDateInput.min = today;
        }


        /* =====================================================
           AUTOMATICALLY CHECK OVERDUE TASKS
           
           The PHP check happens whenever the page loads.
           Reloading every 60 seconds also allows an overdue
           task to move automatically while this page remains open.
        ===================================================== */

        setInterval(function () {

            window.location.reload();

        }, 60000);


    </script>


</body>

</html>


<?php

$con->close();

?>