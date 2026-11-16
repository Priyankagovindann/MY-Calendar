<?php
session_start();

$con = new mysqli("localhost", "root", "", "calender");

if ($con->connect_error) {
    die("Database connection failed.");
}

$con->set_charset("utf8mb4");

/* Check admin login */
if (empty($_SESSION["ADMIN_ID"])) {
    header("Location: admin_login.php");
    exit();
}

$admin_id = (int) $_SESSION["ADMIN_ID"];
$message = "";
$message_type = "";

function verifyAdminPassword($entered, $stored)
{
    if (password_verify($entered, $stored)) {
        return true;
    }

    // Support existing plain-text passwords from the login system.
    return hash_equals((string) $stored, (string) $entered);
}

/* CSRF token */
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

/* Save profile changes */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $csrf_token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["csrf_token"], $csrf_token)) {
        $message = "Invalid request. Please refresh the page.";
        $message_type = "error";
    } else {

        $admin_name = trim($_POST["admin_name"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $username = trim($_POST["username"] ?? "");

        $current_password = $_POST["current_password"] ?? "";
        $new_password = $_POST["new_password"] ?? "";
        $confirm_password = $_POST["confirm_password"] ?? "";

        if ($admin_name === "" || $email === "" || $username === "") {
            $message = "Please fill in all profile fields.";
            $message_type = "error";

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address.";
            $message_type = "error";

        } else {

            /* Check duplicate email or username */
            $duplicate = $con->prepare(
                "SELECT admin_id
                 FROM admin
                 WHERE (email = ? OR username = ?)
                 AND admin_id != ?
                 LIMIT 1"
            );

            $duplicate->bind_param("ssi", $email, $username, $admin_id);
            $duplicate->execute();
            $duplicate_result = $duplicate->get_result();

            if ($duplicate_result->num_rows > 0) {
                $message = "Email or username is already being used.";
                $message_type = "error";

            } else {

                /* Update profile details */
                $update = $con->prepare(
                    "UPDATE admin
                     SET admin_name = ?, email = ?, username = ?
                     WHERE admin_id = ?"
                );

                $update->bind_param(
                    "sssi",
                    $admin_name,
                    $email,
                    $username,
                    $admin_id
                );

                if ($update->execute()) {

                    $_SESSION["ADMIN_NAME"] = $admin_name;
                    $_SESSION["ADMIN_EMAIL"] = $email;
                    $_SESSION["ADMIN_USERNAME"] = $username;

                    /* Optional password update */
                    if ($new_password !== "" || $confirm_password !== "") {

                        if ($current_password === "") {
                            $message = "Enter your current password to change it.";
                            $message_type = "error";

                        } elseif ($new_password !== $confirm_password) {
                            $message = "New password and confirmation do not match.";
                            $message_type = "error";

                        } elseif (strlen($new_password) < 8) {
                            $message = "New password must contain at least 8 characters.";
                            $message_type = "error";

                        } else {

                            $get_password = $con->prepare(
                                "SELECT password FROM admin WHERE admin_id = ?"
                            );

                            $get_password->bind_param("i", $admin_id);
                            $get_password->execute();
                            $password_result = $get_password->get_result();
                            $password_row = $password_result->fetch_assoc();

                            if (
                                !$password_row ||
                                !verifyAdminPassword(
                                    $current_password,
                                    $password_row["password"]
                                )
                            ) {
                                $message = "Current password is incorrect.";
                                $message_type = "error";

                            } else {

                                $hashed_password = password_hash(
                                    $new_password,
                                    PASSWORD_DEFAULT
                                );

                                $password_update = $con->prepare(
                                    "UPDATE admin
                                     SET password = ?
                                     WHERE admin_id = ?"
                                );

                                $password_update->bind_param(
                                    "si",
                                    $hashed_password,
                                    $admin_id
                                );

                                if ($password_update->execute()) {
                                    $message = "Profile and password updated successfully.";
                                    $message_type = "success";
                                } else {
                                    $message = "Profile updated, but password could not be changed.";
                                    $message_type = "error";
                                }
                            }
                        }

                    } else {
                        $message = "Profile updated successfully.";
                        $message_type = "success";
                    }

                } else {
                    $message = "Unable to update profile.";
                    $message_type = "error";
                }
            }
        }
    }
}

/* Load admin details */
$stmt = $con->prepare(
    "SELECT admin_id, admin_name, email, username
     FROM admin
     WHERE admin_id = ?
     LIMIT 1"
);

$stmt->bind_param("i", $admin_id);
$stmt->execute();

$result = $stmt->get_result();
$admin = $result->fetch_assoc();

if (!$admin) {
    session_destroy();
    header("Location: admin_login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Profile | MyCalendar</title>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: "Segoe UI", Arial, sans-serif;
}

:root {
    --olive: #59633d;
    --dark-olive: #414a2c;
    --beige: #f3ecdf;
    --card: #fffaf2;
    --border: #e5dac8;
    --text: #343a24;
    --muted: #858678;
}

body {
    min-height: 100vh;
    background: var(--beige);
    color: var(--text);
}

/* Sidebar */
.sidebar {
    width: 287px;
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    background: var(--olive);
    color: white;
    padding: 30px 20px;
    display: flex;
    flex-direction: column;
}

.logo {
    font-size: 25px;
    font-weight: 700;
    padding: 0 14px;
    margin-bottom: 40px;
}

.sidebar nav {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.sidebar nav a {
    display: block;
    text-decoration: none;
    color: #f7f5ef;
    padding: 15px 17px;
    border-radius: 10px;
    font-size: 15px;
    transition: 0.2s;
}

.sidebar nav a:hover,
.sidebar nav a.active {
    background: var(--dark-olive);
    color: white;
}

.sidebar .logout {
    margin-top: auto;
}

/* Main area */
.main {
    margin-left: 287px;
    min-height: 100vh;
    padding: 38px 45px;
}

.topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 30px;
}

.page-title {
    font-size: 29px;
    font-weight: 700;
    color: var(--dark-olive);
}

.page-subtitle {
    margin-top: 7px;
    font-size: 14px;
    color: var(--muted);
}

.admin-chip {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 30px;
    padding: 11px 18px;
    font-size: 14px;
    font-weight: 600;
}

/* Profile layout */
.profile-layout {
    display: grid;
    grid-template-columns: 310px minmax(0, 1fr);
    gap: 25px;
    align-items: start;
}

.profile-card,
.form-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 28px;
    box-shadow: 0 5px 18px rgba(65, 74, 44, 0.04);
}

.profile-card {
    text-align: center;
}

.avatar {
    width: 95px;
    height: 95px;
    margin: 0 auto 18px;
    border-radius: 50%;
    background: var(--olive);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 35px;
    font-weight: 700;
}

.profile-card h2 {
    font-size: 21px;
    margin-bottom: 7px;
    overflow-wrap: anywhere;
}

.profile-card .role {
    color: var(--muted);
    font-size: 14px;
    margin-bottom: 25px;
}

.profile-detail {
    text-align: left;
    border-top: 1px solid var(--border);
    padding: 15px 0;
}

.profile-detail span {
    display: block;
    font-size: 12px;
    color: var(--muted);
    margin-bottom: 6px;
}

.profile-detail strong {
    font-size: 14px;
    overflow-wrap: anywhere;
}

.form-card h2 {
    font-size: 20px;
    margin-bottom: 6px;
}

.form-card .description {
    color: var(--muted);
    font-size: 14px;
    margin-bottom: 25px;
}

.form-section-title {
    font-size: 15px;
    font-weight: 700;
    color: var(--dark-olive);
    margin: 24px 0 15px;
}

.form-section-title:first-of-type {
    margin-top: 0;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group.full {
    grid-column: 1 / -1;
}

.form-group label {
    font-size: 13px;
    font-weight: 600;
}

.form-group input {
    width: 100%;
    padding: 13px 14px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: #fffdf9;
    color: var(--text);
    font-size: 14px;
    outline: none;
}

.form-group input:focus {
    border-color: var(--olive);
    box-shadow: 0 0 0 3px rgba(89, 99, 61, 0.10);
}

.form-group input[readonly] {
    background: #f0eadf;
    color: var(--muted);
    cursor: not-allowed;
}

.password-note {
    color: var(--muted);
    font-size: 12px;
    margin-top: 5px;
}

.message {
    padding: 13px 16px;
    border-radius: 9px;
    margin-bottom: 22px;
    font-size: 14px;
}

.message.success {
    background: #e8f1e4;
    color: #3e6a36;
    border: 1px solid #c9dfc0;
}

.message.error {
    background: #f8e8e3;
    color: #9a4335;
    border: 1px solid #e8c7bd;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 25px;
}

.save-btn {
    border: none;
    background: var(--olive);
    color: white;
    padding: 13px 25px;
    border-radius: 9px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.2s;
}

.save-btn:hover {
    background: var(--dark-olive);
}

/* Responsive */
@media (max-width: 1050px) {
    .profile-layout {
        grid-template-columns: 1fr;
    }

    .profile-card {
        text-align: left;
    }

    .avatar {
        margin: 0 0 18px;
    }
}

@media (max-width: 768px) {
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
        padding: 18px;
    }

    .logo {
        margin-bottom: 15px;
    }

    .sidebar nav {
        gap: 4px;
    }

    .sidebar nav a {
        padding: 11px 14px;
    }

    .sidebar .logout {
        margin-top: 5px;
    }

    .main {
        margin-left: 0;
        padding: 25px 18px;
    }

    .topbar {
        align-items: flex-start;
        gap: 15px;
        flex-direction: column;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .form-group.full {
        grid-column: auto;
    }
}
</style>
</head>

<body>

<aside class="sidebar">
    <div class="logo">MyCalendar</div>

    <nav>
        <a href="admin_dashboard.php">Home</a>
        <a href="admin_user.php">Users</a>
        <a href="admin_activity.php">User Activity</a>
        <a href="admin_profile.php" class="active">Admin Profile</a>
    </nav>

    <nav class="logout">
        <a href="admin_logout.php">Logout</a>
    </nav>
</aside>

<main class="main">

    <div class="topbar">
        <div>
            <h1 class="page-title">Admin Profile</h1>
            <p class="page-subtitle">
                View and manage your administrator account details.
            </p>
        </div>

        <div class="admin-chip">
            Administrator
        </div>
    </div>

    <?php if ($message !== ""): ?>
        <div class="message <?= htmlspecialchars($message_type) ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="profile-layout">

        <!-- Profile Summary -->
        <section class="profile-card">

            <div class="avatar">
                <?= strtoupper(substr($admin["admin_name"], 0, 1)) ?>
            </div>

            <h2><?= htmlspecialchars($admin["admin_name"]) ?></h2>
            <p class="role">System Administrator</p>

            <div class="profile-detail">
                <span>Admin ID</span>
                <strong><?= htmlspecialchars($admin["admin_id"]) ?></strong>
            </div>

            <div class="profile-detail">
                <span>Email Address</span>
                <strong><?= htmlspecialchars($admin["email"]) ?></strong>
            </div>

            <div class="profile-detail">
                <span>Username</span>
                <strong><?= htmlspecialchars($admin["username"]) ?></strong>
            </div>

        </section>

        <!-- Edit Profile -->
        <section class="form-card">

            <h2>Edit Profile</h2>
            <p class="description">
                Update your personal account information below.
            </p>

            <form method="POST" action="admin_profile.php">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>"
                >

                <div class="form-section-title">Personal Information</div>

                <div class="form-grid">

                    <div class="form-group">
                        <label for="admin_name">Admin Name</label>
                        <input
                            type="text"
                            id="admin_name"
                            name="admin_name"
                            value="<?= htmlspecialchars($admin["admin_name"]) ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="username">Username</label>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            value="<?= htmlspecialchars($admin["username"]) ?>"
                            required
                        >
                    </div>

                    <div class="form-group full">
                        <label for="email">Email Address</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?= htmlspecialchars($admin["email"]) ?>"
                            required
                        >
                    </div>

                </div>

                <div class="form-section-title">Change Password</div>

                <p class="password-note">
                    Leave these fields empty if you do not want to change your password.
                </p>

                <div class="form-grid" style="margin-top: 15px;">

                    <div class="form-group full">
                        <label for="current_password">Current Password</label>
                        <input
                            type="password"
                            id="current_password"
                            name="current_password"
                            autocomplete="current-password"
                        >
                    </div>

                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input
                            type="password"
                            id="new_password"
                            name="new_password"
                            minlength="8"
                            autocomplete="new-password"
                        >
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            minlength="8"
                            autocomplete="new-password"
                        >
                    </div>

                </div>

                <div class="form-actions">
                    <button type="submit" class="save-btn">
                        Save Changes
                    </button>
                </div>

            </form>

        </section>

    </div>

</main>

</body>
</html>