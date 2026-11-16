
<?php
// ==========================================
// MyCalendar Admin - Users Page
// File: admin_user.php
// Database: calender
// Table: user_info
// ==========================================

// Start the admin session.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Protect this page using the existing admin login session.
if (empty($_SESSION["ADMIN_ID"])) {
    header("Location: admin_login.php");
    exit;
}

// Connect directly to the database.
// Adjust credentials if your working login uses different ones.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(
        "localhost",
        "root",
        "",
        "calender"
    );

    $conn->set_charset("utf8mb4");

} catch (mysqli_sql_exception $e) {
    error_log("MyCalendar database error: " . $e->getMessage());
    http_response_code(500);
    exit("Unable to connect to the database. Please check your database settings.");
}

// Escape output to prevent HTML injection.
function h($value) {
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

$adminName = $_SESSION["ADMIN_NAME"] ?? "Administrator";

// ==========================================
// SEARCH AND FETCH USERS
// ==========================================

$search = trim($_GET["search"] ?? "");

if (strlen($search) > 100) {
    $search = substr($search, 0, 100);
}

$users = [];
$totalUsers = 0;
$displayCount = 0;

// Get the total number of registered users.
$countResult = $conn->query(
    "SELECT COUNT(*) AS total FROM user_info"
);

$totalUsers = (int)$countResult->fetch_assoc()["total"];

// Fetch users, with optional search.
if ($search !== "") {
    $searchTerm = "%" . $search . "%";

    $stmt = $conn->prepare(
        "SELECT id, name, dob, phone_no, email
         FROM user_info
         WHERE name LIKE ?
            OR email LIKE ?
            OR phone_no LIKE ?
            OR CAST(id AS CHAR) LIKE ?
         ORDER BY id ASC"
    );

    $stmt->bind_param(
        "ssss",
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm
    );

} else {
    $stmt = $conn->prepare(
        "SELECT id, name, dob, phone_no, email
         FROM user_info
         ORDER BY id ASC"
    );
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

$stmt->close();

$displayCount = count($users);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Users | MyCalendar Admin</title>

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
           SIDEBAR - ICONS REMOVED
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
            font-weight: bold;
        }

        .admin-info {
            min-width: 0;
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
           TOTAL USERS CARD
        ===================================== */

        .summary-card {
            padding: 23px 25px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 15px;
            margin-bottom: 25px;
        }

        .summary-label {
            color: var(--muted);
            font-size: 14px;
            margin-bottom: 10px;
        }

        .summary-value {
            color: var(--olive);
            font-size: 30px;
            font-weight: 700;
        }

        .summary-note {
            margin-top: 7px;
            font-size: 13px;
            color: var(--muted);
        }

        /* =====================================
           USERS TABLE CARD
        ===================================== */

        .users-card {
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
            margin-bottom: 24px;
        }

        .card-header h2 {
            font-size: 21px;
            margin-bottom: 9px;
        }

        .card-header p {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
        }

        /* SEARCH FORM */

        .search-form {
            display: flex;
            align-items: center;
            gap: 9px;
            width: 100%;
            max-width: 470px;
        }

        .search-box {
            flex: 1;
            min-width: 0;
            height: 45px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 13px;
            background: #fffdf8;
            border: 1px solid var(--border);
            border-radius: 9px;
        }

        .search-box i {
            color: var(--muted);
        }

        .search-box input {
            width: 100%;
            min-width: 0;
            height: 100%;
            outline: none;
            border: none;
            background: transparent;
            color: var(--text);
            font-size: 14px;
        }

        .search-box input::placeholder {
            color: #aaa89e;
        }

        .search-box:focus-within {
            border-color: var(--olive);
        }

        .search-button {
            height: 45px;
            padding: 0 17px;
            border: none;
            border-radius: 9px;
            background: var(--olive);
            color: var(--white);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .search-button:hover {
            background: var(--olive-dark);
        }

        .clear-button {
            height: 45px;
            padding: 0 13px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border);
            border-radius: 9px;
            color: var(--text);
            background: transparent;
            text-decoration: none;
            font-size: 14px;
        }

        .clear-button:hover {
            background: #f4efe5;
        }

        /* =====================================
           TABLE
        ===================================== */

        .table-info {
            display: flex;
            justify-content: space-between;
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
            padding: 16px 14px;
            background: #f4efe5;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        tbody td {
            padding: 17px 14px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
            vertical-align: middle;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        tbody tr:hover {
            background: #f8f3e9;
        }

        .user-id {
            color: var(--olive);
            font-weight: 700;
        }

        .user-name {
            color: var(--text);
            font-weight: 700;
        }

        .user-email {
            color: var(--olive-dark);
            overflow-wrap: anywhere;
        }

        .user-initial {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--olive-light);
            color: var(--olive);
            font-size: 14px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .user-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-cell-details {
            min-width: 0;
        }

        .user-cell-details .user-name {
            display: block;
            overflow-wrap: anywhere;
        }

        .user-cell-details small {
            display: block;
            margin-top: 5px;
            color: var(--muted);
            font-size: 12px;
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
           RESPONSIVE DESIGN
        ===================================== */

        @media (max-width: 1200px) {
            .sidebar {
                width: 245px;
            }

            .main-content {
                margin-left: 245px;
                padding: 30px 25px;
            }

            .users-card {
                padding: 22px 19px;
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

            .date-display {
                font-size: 13px;
            }

            .card-header {
                flex-direction: column;
            }

            .search-form {
                max-width: none;
            }

            .users-card {
                padding: 20px 15px;
            }

            .summary-card {
                padding: 20px;
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

            .search-form {
                flex-wrap: wrap;
            }

            .search-box {
                flex-basis: 100%;
            }

            .search-button,
            .clear-button {
                flex: 1;
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

            <a href="admin_user.php" class="active">Users</a>

            <a href="admin_activity.php">User Activity</a>

            <a href="admin_account.php">Account Management</a>

            <a href="admin_profile.php">Admin Profile</a>
        </div>

        <div class="sidebar-spacer"></div>

        <div class="sidebar-bottom">

            <div class="sidebar-menu">
                <a href="admin_logout.php" class="logout-link">
                    Logout
                </a>
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

                <h1>Registered Users</h1>

                <p class="page-subtitle">
                    View and review the users registered on MyCalendar.
                </p>
            </div>

            <div class="date-display">
                <i class="fa-regular fa-calendar"></i>
                <span><?= date("F d, Y") ?></span>
            </div>

        </header>

        <!-- TOTAL USERS -->
        <section class="summary-card">

            <div class="summary-label">
                Total Registered Users
            </div>

            <div class="summary-value">
                <?= number_format($totalUsers) ?>
            </div>

            <div class="summary-note">
                Total accounts registered on the platform.
            </div>

        </section>

        <!-- USERS TABLE -->
        <section class="users-card">

            <div class="card-header">

                <div>
                    <h2>User Information</h2>

                    <p>
                        Search and view registered user details.
                    </p>
                </div>

                <form
                    class="search-form"
                    method="GET"
                    action="admin_user.php"
                >

                    <div class="search-box">

                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="search"
                            name="search"
                            value="<?= h($search) ?>"
                            placeholder="Search users..."
                            maxlength="100"
                            aria-label="Search users"
                        >

                    </div>

                    <button type="submit" class="search-button">
                        Search
                    </button>

                    <?php if ($search !== ""): ?>
                        <a href="admin_user.php" class="clear-button">
                            Clear
                        </a>
                    <?php endif; ?>

                </form>

            </div>

            <div class="table-info">

                <span>
                    <?= number_format($displayCount) ?>
                    <?= $displayCount === 1 ? "user" : "users" ?>
                    displayed
                </span>

                <?php if ($search !== ""): ?>
                    <span>
                        Search results for:
                        <strong><?= h($search) ?></strong>
                    </span>
                <?php endif; ?>

            </div>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>User Details</th>
                            <th>Email Address</th>
                            <th>Phone Number</th>
                            <th>Date of Birth</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (empty($users)): ?>

                        <tr>
                            <td colspan="5">

                                <div class="empty-state">

                                    <strong>No users found</strong>

                                    <?php if ($search !== ""): ?>
                                        Try another name, email, phone number or ID.
                                    <?php else: ?>
                                        Registered users will appear here.
                                    <?php endif; ?>

                                </div>

                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($users as $user): ?>

                            <?php
                            $name = trim((string)($user["name"] ?? ""));

                            $initial = $name !== ""
                                ? strtoupper(substr($name, 0, 1))
                                : "?";

                            $dob = (string)($user["dob"] ?? "");

                            if (
                                $dob === "" ||
                                $dob === "0000-00-00" ||
                                strtotime($dob) === false
                            ) {
                                $formattedDob = "Not provided";
                            } else {
                                $formattedDob = date(
                                    "d M Y",
                                    strtotime($dob)
                                );
                            }
                            ?>

                            <tr>

                                <td>
                                    <span class="user-id">
                                        #<?= h($user["id"]) ?>
                                    </span>
                                </td>

                                <td>

                                    <div class="user-cell">

                                        <div class="user-initial">
                                            <?= h($initial) ?>
                                        </div>

                                        <div class="user-cell-details">

                                            <span class="user-name">
                                                <?= h(
                                                    $name !== ""
                                                        ? $name
                                                        : "Unnamed User"
                                                ) ?>
                                            </span>

                                            <small>Registered user</small>

                                        </div>

                                    </div>

                                </td>

                                <td>
                                    <span class="user-email">
                                        <?= h($user["email"]) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= h($user["phone_no"]) ?>
                                </td>

                                <td>
                                    <?= h($formattedDob) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

            <div class="card-footer">
                User passwords are intentionally excluded from this page.
            </div>

        </section>

    </main>

</body>
</html>
