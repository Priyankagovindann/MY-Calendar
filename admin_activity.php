

<?php

// =============================================

// MyCalendar Admin - User Activity

// Database: calender

// Tables: user_info, events

// =============================================



if (session_status() !== PHP_SESSION_ACTIVE) {

    session_start();

}



// Protect the page using the existing admin session.

if (empty($_SESSION["ADMIN_ID"])) {

    header("Location: admin_login.php");

    exit;

}



// Connect directly to the existing database.

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);



try {

    $conn = new mysqli("localhost", "root", "", "calender");

    $conn->set_charset("utf8mb4");

} catch (mysqli_sql_exception $e) {

    error_log("MyCalendar database error: " . $e->getMessage());

    http_response_code(500);

    exit("Database connection failed. Please check your database settings.");

}



// Safely escape displayed values.

function h($value) {

    return htmlspecialchars(

        (string)($value ?? ""),

        ENT_QUOTES,

        "UTF-8"

    );

}



$adminName = $_SESSION["ADMIN_NAME"] ?? "Administrator";



// =============================================

// FILTER VALUES

// =============================================



$search = trim($_GET["search"] ?? "");

$status = $_GET["status"] ?? "all";

$category = trim($_GET["category"] ?? "");



if (strlen($search) > 100) {

    $search = substr($search, 0, 100);

}



if (!in_array($status, ["all", "upcoming", "past"], true)) {

    $status = "all";

}



if (strlen($category) > 100) {

    $category = substr($category, 0, 100);

}



// =============================================

// DASHBOARD STATISTICS

// =============================================



$result = $conn->query(

    "SELECT COUNT(*) AS total FROM user_info"

);

$totalUsers = (int)$result->fetch_assoc()["total"];



$result = $conn->query(

    "SELECT COUNT(*) AS total FROM events"

);

$totalEvents = (int)$result->fetch_assoc()["total"];



$result = $conn->query(

    "SELECT COUNT(*) AS total

     FROM events

     WHERE event_date >= CURDATE()"

);

$upcomingEvents = (int)$result->fetch_assoc()["total"];



// Get available event categories for the filter.

$categories = [];



$result = $conn->query(

    "SELECT DISTINCT category

     FROM events

     WHERE category IS NOT NULL

       AND TRIM(category) <> ''

     ORDER BY category ASC"

);



while ($row = $result->fetch_assoc()) {

    $categories[] = $row["category"];

}



// =============================================

// FETCH USER ACTIVITY

// Activity is derived from calendar event records.

// =============================================



$searchTerm = "%" . $search . "%";



$sql = "

    SELECT

        e.id AS event_id,

        e.user_id,

        e.title,

        e.event_date,

        e.event_time,

        e.category,

        e.location,

        e.created_at,

        u.name AS user_name,

        u.email AS user_email

    FROM events AS e

    LEFT JOIN user_info AS u

        ON e.user_id = u.id

    WHERE

        (

            ? = ''

            OR u.name LIKE ?

            OR u.email LIKE ?

            OR e.title LIKE ?

            OR e.category LIKE ?

            OR CAST(e.user_id AS CHAR) LIKE ?

        )

        AND (

            ? = 'all'

            OR (? = 'upcoming' AND e.event_date >= CURDATE())

            OR (? = 'past' AND e.event_date < CURDATE())

        )

        AND (? = '' OR e.category = ?)

    ORDER BY e.created_at DESC, e.id DESC

";



$stmt = $conn->prepare($sql);



$stmt->bind_param(

    "sssssssssss",

    $search,

    $searchTerm,

    $searchTerm,

    $searchTerm,

    $searchTerm,

    $searchTerm,

    $status,

    $status,

    $status,

    $category,

    $category

);



$stmt->execute();

$result = $stmt->get_result();



$activities = [];



while ($row = $result->fetch_assoc()) {

    $activities[] = $row;

}



$stmt->close();



$activityCount = count($activities);

?>



<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">



<title>User Activity | MyCalendar Admin</title>



<link rel="stylesheet"

href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">



<style>

:root {

    --olive: #59633d;

    --olive-dark: #414a2c;

    --olive-light: #e7e8dc;

    --beige: #f3ecdf;

    --card: #fffaf2;

    --border: #e5dac8;

    --text: #343a24;

    --muted: #858678;

    --white: #ffffff;

    --success-bg: #e4eddf;

    --success-text: #45663d;

    --past-bg: #eee7dc;

    --past-text: #796b57;

}



* {

    margin: 0;

    padding: 0;

    box-sizing: border-box;

}



body {

    font-family: Arial, Helvetica, sans-serif;

    background: var(--beige);

    color: var(--text);

}



/* =====================================

   SIDEBAR - NO ICONS

===================================== */



.sidebar {

    position: fixed;

    top: 0;

    left: 0;

    bottom: 0;

    width: 287px;

    padding: 30px 21px 20px;

    background: var(--olive);

    color: var(--white);

    display: flex;

    flex-direction: column;

    z-index: 100;

    overflow-y: auto;

}



.brand {

    padding: 8px 9px 0;

    margin-bottom: 34px;

}



.brand-name {

    font-size: 23px;

    font-weight: 700;

    letter-spacing: -.5px;

}



.menu-heading {

    margin: 0 13px 13px;

    color: #e0e4d2;

    font-size: 12px;

    font-weight: 700;

    letter-spacing: 1px;

    text-transform: uppercase;

}



.sidebar-menu {

    display: flex;

    flex-direction: column;

    gap: 7px;

}



.sidebar-menu a {

    display: flex;

    align-items: center;

    min-height: 49px;

    padding: 13px 17px;

    border-radius: 11px;

    color: var(--white);

    text-decoration: none;

    font-size: 15px;

    transition: background .2s ease;

}



.sidebar-menu a:hover {

    background: rgba(255,255,255,.11);

}



.sidebar-menu a.active {

    background: rgba(255,255,255,.15);

}



.sidebar-spacer {

    flex: 1;

    min-height: 25px;

}



.sidebar-bottom {

    flex-shrink: 0;

}



.logout-link {

    margin-bottom: 14px;

}



.admin-profile {

    border-top: 1px solid rgba(255,255,255,.2);

    padding: 20px 9px 9px;

    display: flex;

    align-items: center;

    gap: 13px;

}



.admin-avatar {

    width: 44px;

    height: 44px;

    flex-shrink: 0;

    border-radius: 50%;

    background: var(--beige);

    color: var(--olive);

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 16px;

    font-weight: 700;

}



.admin-info strong {

    display: block;

    font-size: 14px;

    margin-bottom: 7px;

    overflow-wrap: anywhere;

}



.admin-info span {

    color: #e1e5d4;

    font-size: 12px;

}



/* =====================================

   MAIN CONTENT

===================================== */



.main-content {

    margin-left: 287px;

    min-height: 100vh;

    padding: 37px 40px 32px 43px;

}



.page-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    flex-wrap: wrap;

    gap: 20px;

    margin-bottom: 30px;

}



.eyebrow {

    margin-bottom: 8px;

    color: var(--olive);

    font-size: 13px;

    font-weight: 700;

    letter-spacing: 1px;

}



.page-header h1 {

    font-size: 33px;

    line-height: 1.25;

    letter-spacing: -.5px;

}



.page-subtitle {

    margin-top: 11px;

    color: var(--muted);

    font-size: 15px;

    line-height: 1.6;

}



.date-display {

    display: flex;

    align-items: center;

    gap: 9px;

    padding: 12px 16px;

    background: var(--card);

    border: 1px solid var(--border);

    border-radius: 12px;

    font-size: 14px;

    white-space: nowrap;

}



/* =====================================

   STATISTICS

===================================== */



.stats-grid {

    display: grid;

    grid-template-columns: repeat(3, minmax(0, 1fr));

    gap: 20px;

    margin-bottom: 26px;

}



.stat-card {

    min-width: 0;

    min-height: 155px;

    padding: 23px;

    background: var(--card);

    border: 1px solid var(--border);

    border-radius: 15px;

}



.stat-top {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 10px;

}



.stat-label {

    color: var(--muted);

    font-size: 14px;

}



.stat-icon {

    width: 43px;

    height: 43px;

    flex-shrink: 0;

    display: flex;

    align-items: center;

    justify-content: center;

    background: var(--olive-light);

    color: var(--olive);

    border-radius: 12px;

    font-size: 18px;

}



.stat-value {

    margin-top: 17px;

    color: var(--text);

    font-size: 29px;

    font-weight: 700;

}



.stat-note {

    margin-top: 6px;

    color: var(--muted);

    font-size: 12px;

}



/* =====================================

   ACTIVITY TABLE

===================================== */



.activity-card {

    min-width: 0;

    padding: 27px 28px;

    background: var(--card);

    border: 1px solid var(--border);

    border-radius: 17px;

}



.card-header {

    display: flex;

    justify-content: space-between;

    align-items: flex-start;

    flex-wrap: wrap;

    gap: 18px;

    margin-bottom: 23px;

}



.card-header h2 {

    margin-bottom: 9px;

    font-size: 21px;

}



.card-header p {

    color: var(--muted);

    font-size: 14px;

    line-height: 1.6;

}



/* FILTERS */



.filter-form {

    display: grid;

    grid-template-columns: minmax(180px, 1fr) 150px 150px auto;

    align-items: center;

    gap: 10px;

    margin-bottom: 22px;

}



.search-field,

.filter-select {

    width: 100%;

    height: 44px;

    padding: 0 12px;

    background: #fffdf8;

    border: 1px solid var(--border);

    border-radius: 9px;

    color: var(--text);

    font-family: inherit;

    font-size: 13px;

    outline: none;

}



.search-field:focus,

.filter-select:focus {

    border-color: var(--olive);

}



.filter-button {

    height: 44px;

    padding: 0 17px;

    border: none;

    border-radius: 9px;

    background: var(--olive);

    color: var(--white);

    font-size: 13px;

    font-weight: 600;

    cursor: pointer;

}



.filter-button:hover {

    background: var(--olive-dark);

}



.clear-link {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    min-height: 44px;

    padding: 0 13px;

    border: 1px solid var(--border);

    border-radius: 9px;

    color: var(--text);

    background: transparent;

    text-decoration: none;

    font-size: 13px;

}



.clear-link:hover {

    background: #f4efe5;

}



/* TABLE */



.results-info {

    display: flex;

    justify-content: space-between;

    align-items: center;

    flex-wrap: wrap;

    gap: 10px;

    margin-bottom: 15px;

    color: var(--muted);

    font-size: 13px;

}



.table-wrapper {

    width: 100%;

    overflow-x: auto;

}



table {

    width: 100%;

    border-collapse: collapse;

    text-align: left;

}



thead th {

    padding: 15px 13px;

    background: #f4efe5;

    color: var(--muted);

    font-size: 12px;

    font-weight: 700;

    letter-spacing: .4px;

    text-transform: uppercase;

}



tbody td {

    padding: 16px 13px;

    border-bottom: 1px solid var(--border);

    font-size: 13px;

    vertical-align: middle;

}



tbody tr:last-child td {

    border-bottom: none;

}



tbody tr:hover {

    background: #f8f3e9;

}



.event-title {

    display: block;

    color: var(--text);

    font-weight: 700;

    max-width: 240px;

    white-space: normal;

    line-height: 1.5;

    overflow-wrap: anywhere;

}



.secondary-text {

    display: block;

    margin-top: 5px;

    color: var(--muted);

    font-size: 12px;

    line-height: 1.5;

}



.user-name {

    display: block;

    font-weight: 700;

    color: var(--text);

}



.user-email {

    display: block;

    max-width: 200px;

    margin-top: 5px;

    color: var(--muted);

    font-size: 12px;

    overflow-wrap: anywhere;

}



.category-badge {

    display: inline-block;

    max-width: 150px;

    padding: 6px 10px;

    border-radius: 20px;

    background: var(--olive-light);

    color: var(--olive-dark);

    font-size: 11px;

    font-weight: 700;

    overflow-wrap: anywhere;

}



.status-badge {

    display: inline-block;

    padding: 6px 10px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: 700;

    white-space: nowrap;

}



.status-upcoming {

    background: var(--success-bg);

    color: var(--success-text);

}



.status-past {

    background: var(--past-bg);

    color: var(--past-text);

}



.empty-state {

    padding: 45px 15px;

    text-align: center;

    color: var(--muted);

    font-size: 14px;

    line-height: 1.7;

}



.empty-state strong {

    display: block;

    margin-bottom: 7px;

    color: var(--text);

    font-size: 16px;

}



.card-footer {

    margin-top: 18px;

    padding-top: 18px;

    border-top: 1px solid var(--border);

    color: var(--muted);

    font-size: 12px;

}



/* =====================================

   RESPONSIVE

===================================== */



@media (max-width: 1350px) {

    .filter-form {

        grid-template-columns: minmax(180px, 1fr) 1fr;

    }

}



@media (max-width: 1200px) {

    .sidebar {

        width: 245px;

    }



    .main-content {

        margin-left: 245px;

        padding: 30px 25px;

    }



    .stats-grid {

        gap: 14px;

    }



    .stat-card {

        padding: 18px;

    }



    .activity-card {

        padding: 22px 18px;

    }

}



@media (max-width: 760px) {

    .sidebar {

        position: relative;

        width: 100%;

        height: auto;

        padding: 20px;

    }



    .brand {

        margin-bottom: 24px;

        padding: 0 8px;

    }



    .menu-heading {

        margin-top: 0;

    }



    .sidebar-menu {

        display: grid;

        grid-template-columns: repeat(2, minmax(0, 1fr));

        gap: 8px;

    }



    .sidebar-menu a {

        min-height: 45px;

        padding: 12px;

        font-size: 14px;

    }



    .sidebar-spacer {

        display: none;

    }



    .sidebar-bottom {

        margin-top: 20px;

    }



    .main-content {

        margin-left: 0;

        padding: 26px 18px;

    }



    .page-header {

        align-items: flex-start;

        flex-direction: column;

        margin-bottom: 25px;

    }



    .page-header h1 {

        font-size: 28px;

    }



    .page-subtitle {

        font-size: 14px;

    }



    .stats-grid {

        grid-template-columns: 1fr;

        gap: 13px;

    }



    .stat-card {

        min-height: auto;

    }



    .filter-form {

        grid-template-columns: 1fr 1fr;

    }



    .filter-form .search-field {

        grid-column: 1 / -1;

    }



    .filter-button,

    .clear-link {

        width: 100%;

    }



    .activity-card {

        padding: 20px 14px;

    }



    .card-header {

        flex-direction: column;

    }

}



@media (max-width: 420px) {

    .main-content {

        padding: 22px 12px;

    }



    .sidebar-menu a {

        padding: 11px 9px;

        font-size: 13px;

    }



    .filter-form {

        grid-template-columns: 1fr;

    }



    .filter-form .search-field {

        grid-column: auto;

    }

}

</style>

</head>



<body>



<!-- SIDEBAR -->

<aside class="sidebar">



    <div class="brand">

        <span class="brand-name">MyCalendar</span>

    </div>



    <div class="menu-heading">Administration</div>



    <div class="sidebar-menu">

        <a href="admin_dashboard.php">Home</a>



        <a href="admin_user.php">Users</a>



        <a href="admin_activity.php" class="active">User Activity</a>



        <a href="admin_account.php">Account Management</a>



        <a href="admin_profile.php">Admin Profile</a>

    </div>



    <div class="sidebar-spacer"></div>



    <div class="sidebar-bottom">



        <div class="sidebar-menu">

            <a href="admin_logout.php" class="logout-link">Logout</a>

        </div>



        <div class="admin-profile">

            <div class="admin-avatar">A</div>



            <div class="admin-info">

                <strong><?= h($adminName) ?></strong>

                <span>Administrator</span>

            </div>

        </div>



    </div>

</aside>



<!-- MAIN CONTENT -->

<main class="main-content">



    <header class="page-header">

        <div>

            <div class="eyebrow">ADMIN PANEL</div>



            <h1>User Activity</h1>



            <p class="page-subtitle">

                Review calendar events created by registered users.

            </p>

        </div>



        <div class="date-display">

            <i class="fa-regular fa-calendar"></i>

            <span><?= date("F d, Y") ?></span>

        </div>

    </header>



    <!-- SUMMARY CARDS -->

    <section class="stats-grid">



        <div class="stat-card">

            <div class="stat-top">

                <div class="stat-label">Registered Users</div>



                <div class="stat-icon">

                    <i class="fa-solid fa-users"></i>

                </div>

            </div>



            <div class="stat-value">

                <?= number_format($totalUsers) ?>

            </div>



            <div class="stat-note">

                Accounts in the platform

            </div>

        </div>



        <div class="stat-card">

            <div class="stat-top">

                <div class="stat-label">Total Calendar Events</div>



                <div class="stat-icon">

                    <i class="fa-regular fa-calendar"></i>

                </div>

            </div>



            <div class="stat-value">

                <?= number_format($totalEvents) ?>

            </div>



            <div class="stat-note">

                Events recorded in the database

            </div>

        </div>



        <div class="stat-card">

            <div class="stat-top">

                <div class="stat-label">Upcoming Events</div>



                <div class="stat-icon">

                    <i class="fa-regular fa-clock"></i>

                </div>

            </div>



            <div class="stat-value">

                <?= number_format($upcomingEvents) ?>

            </div>



            <div class="stat-note">

                Scheduled for today or later

            </div>

        </div>



    </section>



    <!-- ACTIVITY TABLE -->

    <section class="activity-card">



        <div class="card-header">

            <div>

                <h2>Calendar Activity</h2>



                <p>

                    Browse events associated with registered users.

                </p>

            </div>

        </div>



        <!-- SEARCH AND FILTERS -->

        <form class="filter-form" method="GET" action="admin_activity.php">



            <input

                type="search"

                name="search"

                class="search-field"

                placeholder="Search user, email, event or ID..."

                value="<?= h($search) ?>"

                maxlength="100"

                aria-label="Search calendar activity"

            >



            <select name="status" class="filter-select">

                <option value="all" <?= $status === "all" ? "selected" : "" ?>>

                    All events

                </option>



                <option value="upcoming" <?= $status === "upcoming" ? "selected" : "" ?>>

                    Upcoming events

                </option>



                <option value="past" <?= $status === "past" ? "selected" : "" ?>>

                    Past events

                </option>

            </select>



            <select name="category" class="filter-select">

                <option value="">All categories</option>



                <?php foreach ($categories as $item): ?>

                    <option

                        value="<?= h($item) ?>"

                        <?= $category === $item ? "selected" : "" ?>

                    >

                        <?= h($item) ?>

                    </option>

                <?php endforeach; ?>

            </select>



            <button type="submit" class="filter-button">

                Apply

            </button>



            <?php if ($search !== "" || $status !== "all" || $category !== ""): ?>

                <a href="admin_activity.php" class="clear-link">

                    Clear filters

                </a>

            <?php endif; ?>



        </form>



        <div class="results-info">

            <span>

                <?= number_format($activityCount) ?>

                <?= $activityCount === 1 ? "activity record" : "activity records" ?>

                found

            </span>



            <span>

                Sorted by latest event record

            </span>

        </div>



        <div class="table-wrapper">



            <table>

                <thead>

                    <tr>

                        <th>User</th>

                        <th>Event Details</th>

                        <th>Event Date</th>

                        <th>Category</th>

                        <th>Status</th>

                    </tr>

                </thead>



                <tbody>



                <?php if (empty($activities)): ?>



                    <tr>

                        <td colspan="5">

                            <div class="empty-state">

                                <strong>No activity records found</strong>



                                Try changing your search or filters, or check

                                whether calendar events have been created.

                            </div>

                        </td>

                    </tr>



                <?php else: ?>



                    <?php foreach ($activities as $activity): ?>



                        <?php

                        $eventDate = $activity["event_date"];

                        $isUpcoming = $eventDate >= date("Y-m-d");



                        $formattedDate = date(

                            "d M Y",

                            strtotime($eventDate)

                        );



                        $eventTime = $activity["event_time"];



                        $formattedTime = !empty($eventTime)

                            ? date("h:i A", strtotime($eventTime))

                            : "Time not specified";

                        ?>



                        <tr>



                            <td>

                                <span class="user-name">

                                    <?= h(

                                        $activity["user_name"]

                                            ?: "Unknown user"

                                    ) ?>

                                </span>



                                <span class="user-email">

                                    <?= h(

                                        $activity["user_email"]

                                            ?: "User ID: " . $activity["user_id"]

                                    ) ?>

                                </span>

                            </td>



                            <td>

                                <span class="event-title">

                                    <?= h($activity["title"]) ?>

                                </span>



                                <?php if (!empty($activity["location"])): ?>

                                    <span class="secondary-text">

                                        <?= h($activity["location"]) ?>

                                    </span>

                                <?php endif; ?>

                            </td>



                            <td>

                                <?= h($formattedDate) ?>



                                <span class="secondary-text">

                                    <?= h($formattedTime) ?>

                                </span>

                            </td>



                            <td>

                                <span class="category-badge">

                                    <?= h(

                                        $activity["category"]

                                            ?: "General"

                                    ) ?>

                                </span>

                            </td>



                            <td>

                                <?php if ($isUpcoming): ?>

                                    <span class="status-badge status-upcoming">

                                        Upcoming

                                    </span>

                                <?php else: ?>

                                    <span class="status-badge status-past">

                                        Past

                                    </span>

                                <?php endif; ?>

                            </td>



                        </tr>



                    <?php endforeach; ?>



                <?php endif; ?>



                </tbody>

            </table>



        </div>



        <div class="card-footer">

            Activity is based on calendar event records. Login history and

            other user actions are not tracked by this page.

        </div>



    </section>



</main>



</body>

</html>
