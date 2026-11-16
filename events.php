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


/* ===============================
   ADD EVENT
================================ */

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_event'])) {

    $title = trim($_POST['title']);
    $event_date = $_POST['event_date'];
    $event_time = $_POST['event_time'];
    $location = trim($_POST['location']);
    $description = trim($_POST['description']);
    $category = trim($_POST['category']);

    if (
        empty($title) ||
        empty($event_date) ||
        empty($event_time)
    ) {

        $message = "Please fill in all required fields.";
        $message_type = "error";

    } else {

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

            $message = "Event added successfully.";
            $message_type = "success";

        } else {

            $message = "Unable to add event.";
            $message_type = "error";
        }

        $stmt->close();
    }
}


/* ===============================
   DELETE EVENT
================================ */

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_event'])) {

    $event_id = intval($_POST['event_id']);

    $stmt = $con->prepare(
        "DELETE FROM events
         WHERE id = ? AND user_id = ?"
    );

    $stmt->bind_param("ii", $event_id, $user_id);

    if ($stmt->execute()) {

        $message = "Event deleted successfully.";
        $message_type = "success";

    } else {

        $message = "Unable to delete event.";
        $message_type = "error";
    }

    $stmt->close();
}


/* ===============================
   FETCH EVENTS
================================ */

$events = [];

$stmt = $con->prepare(
    "SELECT
        id,
        title,
        event_date,
        event_time,
        location,
        description,
        category
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


/* ===============================
   UPCOMING EVENT COUNT
================================ */

$today = date("Y-m-d");

$upcoming_count = 0;

foreach ($events as $event) {

    if ($event['event_date'] >= $today) {
        $upcoming_count++;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Events | MyCalendar</title>

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


        /* ===============================
           SIDEBAR
        ================================ */

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


        /* ===============================
           MAIN WRAPPER
        ================================ */

        .main-wrapper {
            flex: 1;

            display: flex;
            flex-direction: column;

            height: 100vh;

            overflow-y: auto;
        }


        /* ===============================
           HEADER
        ================================ */

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


        /* ===============================
           EVENTS CONTENT
        ================================ */

        .events-content {
            padding: 30px 36px;
        }


        .events-top {
            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 24px;
        }


        .events-heading h2 {
            font-size: 24px;

            color: var(--olive-dark);

            margin-bottom: 5px;
        }


        .events-heading p {
            font-size: 13px;

            color: var(--text-muted);
        }


        /* ===============================
           ADD EVENT BUTTON
        ================================ */

        .add-event-btn {
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


        .add-event-btn:hover {
            background-color: var(--olive-dark);
        }


        /* ===============================
           MESSAGE
        ================================ */

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


        /* ===============================
           ADD EVENT FORM
        ================================ */

        .add-event-card {
            background-color: var(--card-beige);

            border: 1px solid var(--border-beige);

            border-radius: 12px;

            padding: 25px;

            margin-bottom: 24px;

            box-shadow:
                0 4px 16px rgba(55, 70, 33, 0.04);

            display: none;
        }


        .add-event-card.show {
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


        /* ===============================
           EVENTS CARD
        ================================ */

        .events-card {
            background-color: var(--card-beige);

            border: 1px solid var(--border-beige);

            border-radius: 12px;

            padding: 24px;

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


        .event-count {
            font-size: 11px;

            font-weight: 700;

            background-color: var(--olive-tint);

            color: var(--olive-dark);

            padding: 5px 11px;

            border-radius: 12px;

            text-transform: uppercase;

            letter-spacing: 0.5px;
        }


        /* ===============================
           EVENT ITEM
        ================================ */

        .event-list {
            display: flex;

            flex-direction: column;

            gap: 12px;
        }


        .event-item {
            display: flex;

            align-items: center;

            gap: 15px;

            background-color: var(--white);

            border: 1px solid var(--border-beige);

            border-radius: 8px;

            padding: 14px;
        }


        .event-date-box {
            width: 55px;
            height: 55px;

            background-color: var(--olive-tint);

            color: var(--olive-dark);

            border-radius: 8px;

            display: flex;

            flex-direction: column;

            align-items: center;

            justify-content: center;

            font-size: 10px;

            font-weight: 700;

            text-transform: uppercase;

            line-height: 1.1;

            flex-shrink: 0;
        }


        .event-date-box span {
            font-size: 19px;

            margin-top: 3px;
        }


        .event-info {
            flex: 1;
        }


        .event-info h4 {
            font-size: 15px;

            color: var(--text-dark);

            font-weight: 700;

            margin-bottom: 5px;
        }


        .event-info p {
            font-size: 12px;

            color: var(--text-muted);

            margin-bottom: 4px;
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


        /* ===============================
           NO EVENTS
        ================================ */

        .no-events {
            text-align: center;

            padding: 50px 20px;

            color: var(--text-muted);
        }


        .no-events-icon {
            font-size: 35px;

            margin-bottom: 12px;
        }


        .no-events h4 {
            color: var(--olive-dark);

            font-size: 16px;

            margin-bottom: 6px;
        }


        .no-events p {
            font-size: 13px;
        }


        /* ===============================
           RESPONSIVE
        ================================ */

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


            .events-content {
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


            .events-top {
                align-items: flex-start;

                gap: 15px;

                flex-direction: column;
            }


            .add-event-btn {
                width: 100%;
            }


            .event-item {
                align-items: flex-start;
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


    <!-- ===============================
         SIDEBAR
    ================================ -->

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


            <li class="nav-item active">

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


    <!-- ===============================
         MAIN WRAPPER
    ================================ -->

    <div class="main-wrapper">


        <!-- HEADER -->

        <header>

            <div class="header-left">

                <h1>
                    Events
                </h1>


                <p>
                    Manage your upcoming and scheduled events
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


        <!-- ===============================
             EVENTS CONTENT
        ================================ -->

        <main class="events-content">


            <div class="events-top">

                <div class="events-heading">

                    <h2>
                        My Events
                    </h2>


                    <p>
                        Keep track of your meetings, appointments and important activities.
                    </p>

                </div>


                <button
                    type="button"
                    class="add-event-btn"
                    onclick="openEventForm()"
                >
                    + Add Event
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


            <!-- ===============================
                 ADD EVENT FORM
            ================================ -->

            <section
                class="add-event-card"
                id="addEventCard"
            >


                <div class="form-header">

                    <h3>
                        Add New Event
                    </h3>


                    <button
                        type="button"
                        class="close-form"
                        onclick="closeEventForm()"
                    >
                        &times;
                    </button>

                </div>


                <form method="POST">


                    <div class="form-grid">


                        <!-- EVENT TITLE -->

                        <div class="form-group full">

                            <label for="title">
                                Event Title *
                            </label>


                            <input
                                type="text"
                                id="title"
                                name="title"
                                placeholder="Enter event title"
                                required
                            >

                        </div>


                        <!-- EVENT DATE -->

                        <div class="form-group">

                            <label for="event_date">
                                Date *
                            </label>


                            <input
                                type="date"
                                id="event_date"
                                name="event_date"
                                required
                            >

                        </div>


                        <!-- EVENT TIME -->

                        <div class="form-group">

                            <label for="event_time">
                                Time *
                            </label>


                            <input
                                type="time"
                                id="event_time"
                                name="event_time"
                                required
                            >

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


                                <option value="Meeting">
                                    Meeting
                                </option>


                                <option value="Appointment">
                                    Appointment
                                </option>


                                <option value="Personal">
                                    Personal
                                </option>


                                <option value="Work">
                                    Work
                                </option>


                                <option value="Other">
                                    Other
                                </option>

                            </select>

                        </div>


                        <!-- LOCATION -->

                        <div class="form-group">

                            <label for="location">
                                Location
                            </label>


                            <input
                                type="text"
                                id="location"
                                name="location"
                                placeholder="Enter location"
                            >

                        </div>


                        <!-- DESCRIPTION -->

                        <div class="form-group full">

                            <label for="description">
                                Description
                            </label>


                            <textarea
                                id="description"
                                name="description"
                                placeholder="Add details about this event..."
                            ></textarea>

                        </div>

                    </div>


                    <div class="form-actions">

                        <button
                            type="submit"
                            name="add_event"
                            class="save-btn"
                        >
                            Save Event
                        </button>

                    </div>

                </form>

            </section>


            <!-- ===============================
                 EVENTS LIST
            ================================ -->

            <section class="events-card">


                <div class="card-header">

                    <h3>
                        Upcoming Events
                    </h3>


                    <span class="event-count">

                        <?php echo $upcoming_count; ?>

                        <?php
                        echo ($upcoming_count == 1)
                            ? "Event"
                            : "Events";
                        ?>

                    </span>

                </div>


                <?php if (count($events) > 0) : ?>


                    <div class="event-list">


                        <?php foreach ($events as $event) : ?>


                            <?php

                            $eventDate =
                                new DateTime(
                                    $event['event_date']
                                );


                            $month =
                                strtoupper(
                                    $eventDate->format('M')
                                );


                            $day =
                                $eventDate->format('d');


                            $formattedDate =
                                $eventDate->format('d M Y');


                            /*
                             * Convert MySQL TIME
                             * to 12-hour AM/PM format.
                             */

                            $formattedTime =
                                date(
                                    "h:i A",
                                    strtotime(
                                        $event['event_time']
                                    )
                                );

                            ?>


                            <div class="event-item">


                                <!-- DATE -->

                                <div class="event-date-box">

                                    <?php echo $month; ?>


                                    <span>
                                        <?php echo $day; ?>
                                    </span>

                                </div>


                                <!-- EVENT INFORMATION -->

                                <div class="event-info">


                                    <h4>

                                        <?php
                                        echo htmlspecialchars(
                                            $event['title']
                                        );
                                        ?>

                                    </h4>


                                    <p>

                                        <?php
                                        echo $formattedDate;
                                        ?>

                                        &nbsp;•&nbsp;

                                        <?php
                                        echo $formattedTime;
                                        ?>


                                        <?php
                                        if (!empty($event['location'])) :
                                        ?>

                                            &nbsp;•&nbsp;

                                            <?php
                                            echo htmlspecialchars(
                                                $event['location']
                                            );
                                            ?>

                                        <?php endif; ?>

                                    </p>


                                    <?php
                                    if (!empty($event['category'])) :
                                    ?>

                                        <span class="category-label">

                                            <?php
                                            echo htmlspecialchars(
                                                $event['category']
                                            );
                                            ?>

                                        </span>

                                    <?php endif; ?>


                                </div>


                                <!-- DELETE -->

                                <form
                                    method="POST"
                                    class="delete-form"
                                    onsubmit="
                                    return confirm(
                                        'Are you sure you want to delete this event?'
                                    );
                                    "
                                >


                                    <input
                                        type="hidden"
                                        name="event_id"
                                        value="<?php
                                        echo $event['id'];
                                        ?>"
                                    >


                                    <button
                                        type="submit"
                                        name="delete_event"
                                        class="delete-btn"
                                        title="Delete Event"
                                    >
                                        &#128465;
                                    </button>


                                </form>


                            </div>


                        <?php endforeach; ?>


                    </div>


                <?php else : ?>


                    <div class="no-events">

                        <div class="no-events-icon">
                            &#128197;
                        </div>


                        <h4>
                            No events yet
                        </h4>


                        <p>
                            Click "Add Event" to create your first event.
                        </p>

                    </div>


                <?php endif; ?>


            </section>


        </main>


    </div>


    <!-- ===============================
         JAVASCRIPT
    ================================ -->

    <script>

        function openEventForm() {

            const form =
                document.getElementById("addEventCard");

            form.classList.add("show");

            form.scrollIntoView({
                behavior: "smooth",
                block: "start"
            });
        }


        function closeEventForm() {

            const form =
                document.getElementById("addEventCard");

            form.classList.remove("show");
        }


        /* Set today's date as minimum date */

        const dateInput =
            document.getElementById("event_date");


        if (dateInput) {

            const today =
                new Date()
                    .toISOString()
                    .split("T")[0];

            dateInput.min = today;
        }

    </script>


</body>

</html>


<?php

$con->close();

?>