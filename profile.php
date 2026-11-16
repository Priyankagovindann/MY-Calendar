<?php
session_start();

/* =========================================================
   AUTHENTICATION
========================================================= */
if (!isset($_SESSION['ID']) || !is_numeric($_SESSION['ID']) || (int)$_SESSION['ID'] <= 0) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['ID'];

/* =========================================================
   DATABASE
========================================================= */
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

$con->set_charset("utf8mb4");

/* =========================================================
   HELPERS
========================================================= */
function clean($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = "";
$message_type = "";

/* =========================================================
   UPDATE PROFILE

   Registration fields stored in user_info:
   id, name, dob, phone_no, email, password
========================================================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_profile"])) {

    $name             = trim($_POST["name"] ?? "");
    $dob              = trim($_POST["dob"] ?? "");
    $phone_no         = trim($_POST["phone_no"] ?? "");
    $email            = trim($_POST["email"] ?? "");
    $new_password     = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    /* Basic validation */
    if ($name === "" || $dob === "" || $phone_no === "" || $email === "") {
        $message = "Please fill in all profile details.";
        $message_type = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $message_type = "error";
    } else {

        /* Validate date stored in MySQL format */
        $date_object = DateTime::createFromFormat('Y-m-d', $dob);

        if (!$date_object || $date_object->format('Y-m-d') !== $dob) {
            $message = "Please enter a valid date of birth.";
            $message_type = "error";
        } else {

            /* Check whether another account already uses this email */
            $stmt = $con->prepare(
                "SELECT id FROM user_info WHERE email = ? AND id <> ? LIMIT 1"
            );
            $stmt->bind_param("si", $email, $user_id);
            $stmt->execute();
            $stmt->store_result();

            $email_exists = $stmt->num_rows > 0;
            $stmt->close();

            if ($email_exists) {
                $message = "That email address is already registered to another account.";
                $message_type = "error";
            } elseif ($new_password !== "" && $new_password !== $confirm_password) {
                $message = "New password and confirm password do not match.";
                $message_type = "error";
            } else {

                /*
                 * The current application stores passwords directly in the
                 * user_info.password column, so this keeps the same format
                 * used by the existing login system.
                 */
                if ($new_password !== "") {

                    $stmt = $con->prepare(
                        "UPDATE user_info
                         SET name = ?, dob = ?, phone_no = ?, email = ?, password = ?
                         WHERE id = ?"
                    );

                    $stmt->bind_param(
                        "sssssi",
                        $name,
                        $dob,
                        $phone_no,
                        $email,
                        $new_password,
                        $user_id
                    );

                } else {

                    $stmt = $con->prepare(
                        "UPDATE user_info
                         SET name = ?, dob = ?, phone_no = ?, email = ?
                         WHERE id = ?"
                    );

                    $stmt->bind_param(
                        "ssssi",
                        $name,
                        $dob,
                        $phone_no,
                        $email,
                        $user_id
                    );
                }

                if ($stmt->execute()) {
                    $message = "Profile updated successfully.";
                    $message_type = "success";
                } else {
                    $message = "Unable to update profile: " . $stmt->error;
                    $message_type = "error";
                }

                $stmt->close();
            }
        }
    }
}

/* =========================================================
   LOAD CURRENT USER
========================================================= */
$user = null;

$stmt = $con->prepare(
    "SELECT id, name, dob, phone_no, email, password
     FROM user_info
     WHERE id = ?
     LIMIT 1"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

$con->close();

/* =========================================================
   DISPLAY VALUES
========================================================= */
$display_name = $user['name'] ?? '';
$display_email = $user['email'] ?? '';

$avatar_initials = '';
$name_parts = preg_split('/\s+/', trim($display_name));

if (!empty($name_parts[0])) {
    $avatar_initials .= strtoupper(substr($name_parts[0], 0, 1));
}

if (count($name_parts) > 1 && !empty($name_parts[count($name_parts) - 1])) {
    $avatar_initials .= strtoupper(substr($name_parts[count($name_parts) - 1], 0, 1));
}

if ($avatar_initials === '') {
    $avatar_initials = 'U';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - MyCalendar</title>

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
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, sans-serif;
        }

        body {
            background: var(--bg-beige);
            color: var(--text-dark);
            min-height: 100vh;
        }

        /* =====================================================
           SIDEBAR - SAME DASHBOARD DESIGN
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

        .sidebar-footer {
            margin-top: auto;
            padding-top: 18px;
            border-top: 1px solid rgba(255, 255, 255, 0.16);
            flex-shrink: 0;
        }

        .sidebar-footer .nav-list {
            gap: 8px;
        }

        .sidebar-footer .nav-item a {
            color: #E2E8D5;
        }

        .sidebar-footer .nav-item a:hover {
            background: var(--olive-primary);
            color: #FFFFFF;
        }

        .sidebar-footer .logout a {
            color: #F2D6D6;
        }

        .sidebar-footer .logout a:hover {
            background: #7A3D3D;
            color: #FFFFFF;
        }

        /* =====================================================
           MAIN
        ===================================================== */
        .main-wrapper {
            margin-left: 250px;
            min-height: 100vh;
        }

        header {
            background: var(--card-beige);
            border-bottom: 1px solid var(--border-beige);
            padding: 20px 36px;
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

        .content {
            padding: 30px 36px 50px;
            max-width: 1200px;
        }

        /* =====================================================
           PAGE HEADER
        ===================================================== */
        .page-header {
            margin-bottom: 24px;
        }

        .page-header h2 {
            font-size: 28px;
            color: var(--olive-dark);
            margin-bottom: 5px;
        }

        .page-header p {
            color: var(--text-muted);
            font-size: 15px;
        }

        /* =====================================================
           ALERTS
        ===================================================== */
        .message {
            border-radius: 9px;
            padding: 13px 16px;
            margin-bottom: 22px;
            font-size: 14px;
            font-weight: 600;
        }

        .message.success {
            background: #E5F0E5;
            color: var(--success);
            border: 1px solid #C9DDC9;
        }

        .message.error {
            background: #F7E3E3;
            color: var(--danger);
            border: 1px solid #E6C4C4;
        }

        /* =====================================================
           PROFILE SUMMARY
        ===================================================== */
        .profile-summary {
            background: var(--card-beige);
            border: 1px solid var(--border-beige);
            border-radius: 12px;
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 18px;
            margin-bottom: 24px;
            box-shadow: 0 4px 16px rgba(55, 70, 33, 0.04);
        }

        .avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: var(--olive-tint);
            color: var(--olive-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 25px;
            font-weight: 800;
            flex-shrink: 0;
        }

        .profile-summary h3 {
            font-size: 22px;
            color: var(--olive-dark);
            margin-bottom: 4px;
        }

        .profile-summary p {
            color: var(--text-muted);
            font-size: 14px;
        }

        /* =====================================================
           PROFILE CARD
        ===================================================== */
        .profile-card {
            background: var(--card-beige);
            border: 1px solid var(--border-beige);
            border-radius: 12px;
            padding: 26px;
            box-shadow: 0 4px 16px rgba(55, 70, 33, 0.04);
        }

        .card-heading {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 22px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-beige);
        }

        .heading-icon {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            background: var(--olive-tint);
            color: var(--olive-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
            font-weight: 700;
        }

        .card-heading h3 {
            font-size: 18px;
            color: var(--olive-dark);
        }

        .card-heading p {
            margin-top: 2px;
            color: var(--text-muted);
            font-size: 13px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-field.full {
            grid-column: 1 / -1;
        }

        .form-field label {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .form-field input {
            width: 100%;
            border: 1px solid #D9D0C3;
            background: #FFFFFF;
            color: var(--text-dark);
            border-radius: 8px;
            padding: 13px 14px;
            font-size: 15px;
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-field input:focus {
            border-color: var(--olive-primary);
            box-shadow: 0 0 0 3px rgba(85, 107, 47, 0.10);
        }

        .form-field input::placeholder {
            color: #A3A198;
        }

        .field-note {
            margin-top: 4px;
            color: var(--text-muted);
            font-size: 12px;
            line-height: 1.5;
        }

        /* =====================================================
           PASSWORD NOTE
        ===================================================== */
        .password-note {
            margin-top: 18px;
            background: var(--olive-tint);
            border: 1px solid #D6DEC8;
            border-radius: 8px;
            padding: 12px 14px;
            color: var(--olive-dark);
            font-size: 13px;
            line-height: 1.5;
        }

        /* =====================================================
           FORM ACTIONS
        ===================================================== */
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid var(--border-beige);
        }

        .cancel-button,
        .save-button {
            text-decoration: none;
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .cancel-button {
            background: var(--card-alt);
            color: var(--text-dark);
            border: 1px solid var(--border-beige);
        }

        .cancel-button:hover {
            background: #E5DDD1;
        }

        .save-button {
            background: var(--olive-primary);
            color: #FFFFFF;
        }

        .save-button:hover {
            background: var(--olive-dark);
        }

        /* =====================================================
           RESPONSIVE
        ===================================================== */
        @media (max-width: 800px) {
            .sidebar {
                width: 220px;
                min-width: 220px;
            }

            .main-wrapper {
                margin-left: 220px;
            }

            .content {
                padding: 25px 20px 40px;
            }

            header {
                padding: 18px 20px;
            }
        }

        @media (max-width: 650px) {
            .sidebar {
                width: 100%;
                min-width: 100%;
                height: auto;
                position: relative;
                padding: 18px;
            }

            .sidebar-footer {
                margin-top: 20px;
            }

            .main-wrapper {
                margin-left: 0;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-field.full {
                grid-column: auto;
            }

            .profile-summary {
                align-items: flex-start;
            }

            .form-actions {
                flex-direction: column-reverse;
            }

            .cancel-button,
            .save-button {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>

<body>

    <!-- =====================================================
         SIDEBAR
    ====================================================== -->
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

            <li class="nav-item">
                <a href="expenses.php">
                    Expenses
                </a>
            </li>

        </ul>

        <!-- PROFILE AND LOGOUT AT THE END OF SIDEBAR -->
        <div class="sidebar-footer">
            <ul class="nav-list">

                <li class="nav-item active">
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
         MAIN CONTENT
    ====================================================== -->
    <div class="main-wrapper">

        <header>
            <div class="header-left">
                <h1>My Profile</h1>
                <p>Manage your MyCalendar account details.</p>
            </div>
        </header>

        <main class="content">

            <div class="page-header">
                <h2>Profile Settings</h2>
                <p>Update the details you entered when creating your account.</p>
            </div>

            <?php if ($message !== ""): ?>
                <div class="message <?php echo clean($message_type); ?>">
                    <?php echo clean($message); ?>
                </div>
            <?php endif; ?>

            <!-- PROFILE SUMMARY -->
            <section class="profile-summary">
                <div class="avatar">
                    <?php echo clean($avatar_initials); ?>
                </div>

                <div>
                    <h3><?php echo clean($display_name); ?></h3>
                    <p><?php echo clean($display_email); ?></p>
                </div>
            </section>

            <!-- EDIT PROFILE -->
            <form method="POST" action="" autocomplete="off">

                <section class="profile-card">

                    <div class="card-heading">
                        <div class="heading-icon">●</div>
                        <div>
                            <h3>Personal Information</h3>
                            <p>All details from your registration can be modified here.</p>
                        </div>
                    </div>

                    <div class="form-grid">

                        <div class="form-field full">
                            <label for="name">Full Name</label>
                            <input
                                type="text"
                                id="name"
                                name="name"
                                value="<?php echo clean($user['name']); ?>"
                                placeholder="Enter your full name"
                                required
                            >
                        </div>

                        <div class="form-field">
                            <label for="dob">Date of Birth</label>
                            <input
                                type="date"
                                id="dob"
                                name="dob"
                                value="<?php echo clean($user['dob']); ?>"
                                required
                            >
                        </div>

                        <div class="form-field">
                            <label for="phone_no">Phone Number</label>
                            <input
                                type="tel"
                                id="phone_no"
                                name="phone_no"
                                value="<?php echo clean($user['phone_no']); ?>"
                                placeholder="Enter your phone number"
                                required
                            >
                        </div>

                        <div class="form-field full">
                            <label for="email">Email Address</label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="<?php echo clean($user['email']); ?>"
                                placeholder="Enter your email"
                                required
                            >
                            <span class="field-note">
                                This is also the email address used to log in to MyCalendar.
                            </span>
                        </div>

                    </div>

                </section>

                <!-- PASSWORD -->
                <section class="profile-card" style="margin-top:24px;">

                    <div class="card-heading">
                        <div class="heading-icon">🔒</div>
                        <div>
                            <h3>Change Password</h3>
                            <p>Leave these fields empty if you do not want to change your password.</p>
                        </div>
                    </div>

                    <div class="form-grid">

                        <div class="form-field">
                            <label for="new_password">New Password</label>
                            <input
                                type="password"
                                id="new_password"
                                name="new_password"
                                placeholder="Enter new password"
                                autocomplete="new-password"
                            >
                        </div>

                        <div class="form-field">
                            <label for="confirm_password">Confirm New Password</label>
                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                placeholder="Confirm new password"
                                autocomplete="new-password"
                            >
                        </div>

                    </div>

                    <div class="password-note">
                        Enter a new password only when you want to change your current password.
                    </div>

                    <div class="form-actions">
                        <a href="dashboard.php" class="cancel-button">Cancel</a>
                        <button type="submit" name="update_profile" class="save-button">
                            Save Changes
                        </button>
                    </div>

                </section>

            </form>

        </main>

    </div>

</body>
</html>
