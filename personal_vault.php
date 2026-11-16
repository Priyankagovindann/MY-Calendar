<?php
session_start();

/* =========================================================
   LOGIN CHECK
========================================================= */

if (!isset($_SESSION['ID'])) {
    header("Location: login.html");
    exit;
}

$user_id = (int) $_SESSION['ID'];


/* =========================================================
   DATABASE CONNECTION
========================================================= */

$conn = new mysqli(
    "localhost",
    "root",
    "",
    "calender"
);

if ($conn->connect_error) {
    die("Database connection failed.");
}

$sidebar_user_name = "Profile";
$name_stmt = $conn->prepare("SELECT name FROM user_info WHERE id = ? LIMIT 1");
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

$conn->set_charset("utf8mb4");


/* =========================================================
   HELPER
========================================================= */

function clean($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
   FILE UPLOAD HELPER
========================================================= */

$upload_dir = __DIR__ . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "personal_vault";
$upload_web_dir = "uploads/personal_vault";

if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0755, true);
}

function saveVaultFile($file, $upload_dir, $upload_web_dir)
{
    if (!isset($file) || !is_array($file) || ($file["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [
            "success" => true,
            "file_name" => null,
            "file_path" => null,
            "file_type" => null
        ];
    }

    if (($file["error"] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return [
            "success" => false,
            "message" => "Unable to upload the attachment."
        ];
    }

    if (($file["size"] ?? 0) > 10 * 1024 * 1024) {
        return [
            "success" => false,
            "message" => "Attachment size must be 10 MB or less."
        ];
    }

    $allowed_mimes = [
        "application/pdf" => "pdf",
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/gif" => "gif",
        "image/webp" => "webp"
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file["tmp_name"]);

    if (!isset($allowed_mimes[$mime])) {
        return [
            "success" => false,
            "message" => "Only PDF, JPG, JPEG, PNG, GIF and WEBP files are allowed."
        ];
    }

    $extension = $allowed_mimes[$mime];
    $stored_name = bin2hex(random_bytes(16)) . "." . $extension;
    $destination = $upload_dir . DIRECTORY_SEPARATOR . $stored_name;

    if (!move_uploaded_file($file["tmp_name"], $destination)) {
        return [
            "success" => false,
            "message" => "Unable to save the uploaded attachment."
        ];
    }

    return [
        "success" => true,
        "file_name" => basename($file["name"]),
        "file_path" => $upload_web_dir . "/" . $stored_name,
        "file_type" => $mime
    ];
}

function deleteVaultFile($file_path)
{
    if (!$file_path) {
        return;
    }

    $relative_path = str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $file_path);
    $base_dir = realpath(__DIR__ . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "personal_vault");

    if (!$base_dir) {
        return;
    }

    $full_path = realpath(__DIR__ . DIRECTORY_SEPARATOR . $relative_path);

    if (
        $full_path &&
        strpos($full_path, $base_dir . DIRECTORY_SEPARATOR) === 0 &&
        is_file($full_path)
    ) {
        @unlink($full_path);
    }
}


/* =========================================================
   ADD / UPDATE
========================================================= */

$message = "";
$message_type = "";


/* ADD */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["save_vault"])
) {

    $vault_id = isset($_POST["vault_id"])
        ? (int)$_POST["vault_id"]
        : 0;

    $title = trim($_POST["title"] ?? "");
    $information = trim($_POST["information"] ?? "");
    $value = trim($_POST["value"] ?? "");
    $notes = trim($_POST["notes"] ?? "");
    $attachment = $_FILES["attachment"] ?? null;


    if (
        $title === "" ||
        $information === "" ||
        $value === ""
    ) {

        $message = "Please fill in all required fields.";
        $message_type = "error";

    } else {

        /* UPDATE */

        if ($vault_id > 0) {

            $old_file_name = null;
            $old_file_path = null;

            $old_stmt = $conn->prepare("
                SELECT file_name, file_path
                FROM personal_vault
                WHERE id = ? AND user_id = ?
                LIMIT 1
            ");

            if ($old_stmt) {
                $old_stmt->bind_param("ii", $vault_id, $user_id);
                $old_stmt->execute();
                $old_result = $old_stmt->get_result();

                if ($old_row = $old_result->fetch_assoc()) {
                    $old_file_name = $old_row["file_name"] ?? null;
                    $old_file_path = $old_row["file_path"] ?? null;
                }

                $old_stmt->close();
            }

            $uploaded = saveVaultFile($attachment, $upload_dir, $upload_web_dir);

            if (!$uploaded["success"]) {

                $message = $uploaded["message"];
                $message_type = "error";

            } else {

                $new_file_name = $uploaded["file_name"] ?? $old_file_name;
                $new_file_path = $uploaded["file_path"] ?? $old_file_path;
                $new_file_type = $uploaded["file_type"] ?? null;

                if (($uploaded["file_path"] ?? null) !== null) {

                    $stmt = $conn->prepare("
                        UPDATE personal_vault
                        SET
                            title = ?,
                            information = ?,
                            value = ?,
                            notes = ?,
                            file_name = ?,
                            file_path = ?,
                            file_type = ?
                        WHERE
                            id = ?
                            AND user_id = ?
                    ");

                    $stmt->bind_param(
                        "sssssssii",
                        $title,
                        $information,
                        $value,
                        $notes,
                        $new_file_name,
                        $new_file_path,
                        $new_file_type,
                        $vault_id,
                        $user_id
                    );

                } else {

                    $stmt = $conn->prepare("
                        UPDATE personal_vault
                        SET
                            title = ?,
                            information = ?,
                            value = ?,
                            notes = ?
                        WHERE
                            id = ?
                            AND user_id = ?
                    ");

                    $stmt->bind_param(
                        "ssssii",
                        $title,
                        $information,
                        $value,
                        $notes,
                        $vault_id,
                        $user_id
                    );
                }

                if ($stmt->execute()) {

                    if (($uploaded["file_path"] ?? null) !== null && $old_file_path) {
                        deleteVaultFile($old_file_path);
                    }

                    $message = "Information updated successfully.";
                    $message_type = "success";

                } else {

                    if (($uploaded["file_path"] ?? null) !== null && $new_file_path) {
                        deleteVaultFile($new_file_path);
                    }

                    $message = "Unable to update information.";
                    $message_type = "error";
                }

                $stmt->close();
            }

        }

        /* ADD */

        else {

            $uploaded = saveVaultFile($attachment, $upload_dir, $upload_web_dir);

            if (!$uploaded["success"]) {

                $message = $uploaded["message"];
                $message_type = "error";

            } else {

                $file_name = $uploaded["file_name"] ?? null;
                $file_path = $uploaded["file_path"] ?? null;
                $file_type = $uploaded["file_type"] ?? null;

                $stmt = $conn->prepare("
                    INSERT INTO personal_vault
                    (
                        user_id,
                        title,
                        information,
                        value,
                        notes,
                        file_name,
                        file_path,
                        file_type
                    )
                    VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->bind_param(
                    "isssssss",
                    $user_id,
                    $title,
                    $information,
                    $value,
                    $notes,
                    $file_name,
                    $file_path,
                    $file_type
                );

                if ($stmt->execute()) {

                    $message = "Information saved successfully.";
                    $message_type = "success";

                } else {

                    if ($file_path) {
                        deleteVaultFile($file_path);
                    }

                    $message = "Unable to save information.";
                    $message_type = "error";
                }

                $stmt->close();
            }
        }
    }
}


/* =========================================================
   DELETE
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["delete_vault"])
) {

    $vault_id = (int)($_POST["vault_id"] ?? 0);

    if ($vault_id > 0) {

        $file_to_delete = null;

        $file_stmt = $conn->prepare("
            SELECT file_path
            FROM personal_vault
            WHERE id = ? AND user_id = ?
            LIMIT 1
        ");

        if ($file_stmt) {
            $file_stmt->bind_param("ii", $vault_id, $user_id);
            $file_stmt->execute();
            $file_result = $file_stmt->get_result();

            if ($file_row = $file_result->fetch_assoc()) {
                $file_to_delete = $file_row["file_path"] ?? null;
            }

            $file_stmt->close();
        }

        $stmt = $conn->prepare("
            DELETE FROM personal_vault
            WHERE
                id = ?
                AND user_id = ?
        ");

        $stmt->bind_param(
            "ii",
            $vault_id,
            $user_id
        );

        if ($stmt->execute()) {

            if ($file_to_delete) {
                deleteVaultFile($file_to_delete);
            }

            $message = "Information deleted successfully.";
            $message_type = "success";

        } else {

            $message = "Unable to delete information.";
            $message_type = "error";
        }

        $stmt->close();
    }
}


/* =========================================================
   SEARCH
========================================================= */

$search = trim($_GET["search"] ?? "");

if ($search !== "") {

    $search_value = "%" . $search . "%";

    $stmt = $conn->prepare("
        SELECT
            id,
            title,
            information,
            value,
            notes,
            file_name,
            file_path,
            file_type,
            created_at,
            updated_at
        FROM personal_vault
        WHERE
            user_id = ?
            AND (
                title LIKE ?
                OR information LIKE ?
                OR notes LIKE ?
            )
        ORDER BY updated_at DESC
    ");

    $stmt->bind_param(
        "isss",
        $user_id,
        $search_value,
        $search_value,
        $search_value
    );

} else {

    $stmt = $conn->prepare("
        SELECT
            id,
            title,
            information,
            value,
            notes,
            file_name,
            file_path,
            file_type,
            created_at,
            updated_at
        FROM personal_vault
        WHERE user_id = ?
        ORDER BY updated_at DESC
    ");

    $stmt->bind_param(
        "i",
        $user_id
    );
}

$stmt->execute();

$result = $stmt->get_result();

$vault_items = [];

while ($row = $result->fetch_assoc()) {
    $vault_items[] = $row;
}

$stmt->close();

$conn->close();

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Personal Vault | MyCalendar</title>


<style>

/* =========================================================
   RESET
========================================================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}


/* =========================================================
   BODY
========================================================= */

body {

    font-family:
        Inter,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        sans-serif;

    background: #f7f3e9;

    color: #30362f;

    min-height: 100vh;
}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {
    width: 250px;
    background-color: #374621;
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
.nav-list { list-style:none; display:flex; flex-direction:column; gap:8px; }
.nav-item a { color:#E2E8D5; text-decoration:none; font-size:16px; font-weight:600; padding:12px 16px; border-radius:8px; display:flex; align-items:center; gap:12px; transition:all .2s ease; }
.nav-item a:hover, .nav-item.active a { background-color:#556B2F; color:#FFFFFF; }
.sidebar-footer { margin-top:auto; padding-top:18px; border-top:1px solid rgba(255,255,255,.16); flex-shrink:0; }
.sidebar-footer .nav-list { gap:8px; }
.sidebar-footer .logout a { color:#F2D6D6; }
.sidebar-footer .logout a:hover { background-color:#7A3D3D; color:#FFFFFF; }

/* =========================================================
   MAIN
========================================================= */

.main {

    margin-left: 245px;

    min-height: 100vh;

    padding: 34px 42px;
}


/* =========================================================
   HEADER
========================================================= */

.page-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 28px;
}


.page-header h1 {

    font-size: 30px;

    font-weight: 700;

    color: #30362f;
}


.page-header p {

    margin-top: 6px;

    color: #777d70;

    font-size: 14px;
}


/* =========================================================
   SEARCH
========================================================= */

.search-area {

    background: #ffffff;

    border: 1px solid #e1e4db;

    border-radius: 16px;

    padding: 18px;

    display: flex;

    gap: 12px;

    margin-bottom: 25px;

    box-shadow:
        0 5px 18px rgba(48, 54, 47, 0.04);
}


.search-box {

    position: relative;

    flex: 1;
}


.search-box input {

    width: 100%;

    height: 48px;

    border: 1px solid #d8ddd1;

    border-radius: 10px;

    padding: 0 16px 0 45px;

    outline: none;

    background: #fafbf8;

    color: #30362f;

    font-size: 14px;
}


.search-box input:focus {

    border-color: #89937d;

    background: #ffffff;
}


.search-icon {

    position: absolute;

    left: 16px;

    top: 50%;

    transform: translateY(-50%);

    color: #777d70;

    font-size: 18px;
}


/* =========================================================
   BUTTONS
========================================================= */

.btn {

    border: none;

    border-radius: 10px;

    padding: 0 20px;

    height: 48px;

    cursor: pointer;

    font-size: 14px;

    font-weight: 600;

    transition: 0.2s ease;
}


.btn-primary {

    background: #30362f;

    color: #ffffff;
}


.btn-primary:hover {

    background: #242922;
}


.btn-secondary {

    background: #e7ebdf;

    color: #30362f;
}


.btn-secondary:hover {

    background: #dce3d3;
}


/* =========================================================
   MESSAGE
========================================================= */

.message {

    padding: 13px 16px;

    border-radius: 10px;

    margin-bottom: 20px;

    font-size: 14px;
}


.message.success {

    background: #e3f0df;

    color: #3f6839;
}


.message.error {

    background: #f7e1e1;

    color: #9a4040;
}


/* =========================================================
   VAULT GRID
========================================================= */

.vault-grid {

    display: grid;

    grid-template-columns:
        repeat(auto-fill, minmax(310px, 1fr));

    gap: 20px;
}


/* =========================================================
   VAULT CARD
========================================================= */

.vault-card {

    background: #ffffff;

    border: 1px solid #e1e4db;

    border-radius: 16px;

    padding: 22px;

    box-shadow:
        0 5px 18px rgba(48, 54, 47, 0.04);

    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
}


.vault-card:hover {

    transform: translateY(-2px);

    box-shadow:
        0 10px 25px rgba(48, 54, 47, 0.08);
}


.vault-card-header {

    display: flex;

    justify-content: space-between;

    align-items: flex-start;

    gap: 12px;

    margin-bottom: 20px;
}


.vault-title {

    font-size: 17px;

    font-weight: 700;

    color: #30362f;
}


.vault-label {

    font-size: 12px;

    color: #858b7e;

    margin-top: 5px;
}


.vault-value {

    background: #f5f7f1;

    border: 1px solid #e3e7dc;

    border-radius: 10px;

    padding: 13px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 10px;

    margin-bottom: 12px;
}


.vault-value-text {

    font-size: 14px;

    color: #30362f;

    word-break: break-word;
}


.show-value {

    border: none;

    background: transparent;

    cursor: pointer;

    color: #626a5c;

    font-size: 13px;

    font-weight: 600;

    white-space: nowrap;
}


.vault-notes {

    font-size: 13px;

    line-height: 1.6;

    color: #73796d;

    margin-bottom: 20px;
}


.vault-actions {

    display: flex;

    gap: 9px;
}


.small-btn {

    border: none;

    border-radius: 8px;

    padding: 9px 13px;

    cursor: pointer;

    font-size: 12px;

    font-weight: 600;
}


.edit-btn {

    background: #e7ebdf;

    color: #30362f;
}


.delete-btn {

    background: #f7e1e1;

    color: #a24747;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty-state {

    background: #ffffff;

    border: 1px dashed #cfd5c7;

    border-radius: 16px;

    padding: 55px 25px;

    text-align: center;

    color: #777d70;
}


.empty-state h3 {

    color: #30362f;

    margin-bottom: 8px;

    font-size: 18px;
}


.empty-state p {

    font-size: 14px;

    line-height: 1.6;
}


/* =========================================================
   MODAL
========================================================= */

.modal-overlay {

    position: fixed;

    inset: 0;

    background: rgba(25, 30, 24, 0.45);

    display: none;

    align-items: center;

    justify-content: center;

    padding: 20px;

    z-index: 1000;
}


.modal-overlay.show {

    display: flex;
}


.modal {

    width: 100%;

    max-width: 540px;

    background: #ffffff;

    border-radius: 18px;

    padding: 28px;

    box-shadow:
        0 20px 60px rgba(0, 0, 0, 0.18);
}


.modal-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 24px;
}


.modal-header h2 {

    font-size: 21px;

    color: #30362f;
}


.close-modal {

    width: 34px;

    height: 34px;

    border: none;

    border-radius: 50%;

    background: #eef1e9;

    color: #596052;

    cursor: pointer;

    font-size: 20px;
}


/* =========================================================
   FORM
========================================================= */

.form-group {

    margin-bottom: 17px;
}


.form-group label {

    display: block;

    margin-bottom: 7px;

    font-size: 13px;

    font-weight: 600;

    color: #4e5549;
}


.form-group input,
.form-group textarea {

    width: 100%;

    border: 1px solid #d8ddd1;

    border-radius: 10px;

    background: #fafbf8;

    color: #30362f;

    padding: 12px 13px;

    outline: none;

    font-family: inherit;

    font-size: 14px;
}


.form-group textarea {

    min-height: 95px;

    resize: vertical;
}


.form-group input:focus,
.form-group textarea:focus {

    border-color: #89937d;

    background: #ffffff;
}


.form-actions {

    display: flex;

    justify-content: flex-end;

    gap: 10px;

    margin-top: 24px;
}


/* =========================================================
   ATTACHMENT
========================================================= */

.vault-attachment {
    margin-bottom: 14px;
    padding: 10px 12px;
    background: var(--olive-tint);
    border: 1px solid var(--border-beige);
    border-radius: 9px;
    font-size: 12px;
    color: var(--text-muted);
    word-break: break-word;
}

.attachment-name {
    display: block;
}

.view-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--olive-primary);
    color: var(--white);
    text-decoration: none;
}

.view-btn:hover {
    background: var(--olive-dark);
}

.form-group input[type="file"] {
    padding: 10px 12px;
    background: var(--white);
    cursor: pointer;
}

.file-help {
    display: block;
    margin-top: 6px;
    color: var(--text-muted);
    font-size: 11px;
    line-height: 1.4;
}

.current-attachment {
    margin-top: 7px;
    color: var(--olive-primary);
    font-size: 12px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 900px) {

    .sidebar {

        width: 210px;
    }

    .main {

        margin-left: 210px;

        padding: 25px;
    }
}


@media (max-width: 700px) {

    .sidebar {

        position: relative;

        width: 100%;

        height: auto;

        min-height: auto;

        border-right: none;

        border-bottom: 1px solid #d5dbc9;
    }


    .sidebar-footer {

        margin-top: 15px;
    }


    .main {

        margin-left: 0;

        padding: 20px;
    }


    .page-header {

        align-items: flex-start;

        flex-direction: column;

        gap: 15px;
    }


    .search-area {

        flex-direction: column;
    }


    .search-area .btn {

        width: 100%;
    }
}


/* =========================================================
   MYCALENDAR DASHBOARD THEME
   Applied to Personal Vault to match dashboard.php
========================================================= */

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

body {
    background: var(--bg-beige);
    color: var(--text-dark);
}

/* Sidebar */
.sidebar {
    width: 250px;
    min-width: 250px;
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    bottom: auto;
    background: var(--olive-dark);
    color: var(--white);
    padding: 30px 20px;
    border-right: none;
    overflow: hidden;
    z-index: 1000;
}

.sidebar-brand {
    padding: 0 0 0 10px;
    margin-bottom: 30px;
    font-size: 15px;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    font-weight: 600;
    color: #CCD6B8;
}

.sidebar-menu {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.sidebar-menu a,
.sidebar-footer a {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 11px 12px;
    border-radius: 8px;
    text-decoration: none;
    color: #E2E8D5;
    font-size: 14px;
    font-weight: 500;
    transition: 0.2s ease;
}

.sidebar-menu a:hover,
.sidebar-footer a:hover {
    background: var(--olive-primary);
    color: var(--white);
}

.sidebar-menu a.active {
    background: var(--olive-primary);
    color: var(--white);
    box-shadow: inset 3px 0 0 var(--olive-light);
}

.sidebar-footer {
    margin-top: auto;
    padding-top: 20px;
    border-top: 1px solid rgba(255,255,255,0.16);
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.sidebar-footer a {
    color: #E2E8D5;
}

.sidebar-footer a:last-child {
    color: #F2D6D6;
}

.sidebar-footer a:last-child:hover {
    background: #7A3D3D;
    color: var(--white);
}

/* Main */
.main {
    margin-left: 250px;
    min-height: 100vh;
    padding: 30px 34px;
}


.page-header {
    margin-bottom: 22px;
}

.page-header h1 {
    color: var(--olive-dark);
    font-size: 28px;
}

.page-header p {
    color: var(--text-muted);
}

/* Search */
.search-area {
    background: var(--card-beige);
    border: 1px solid var(--border-beige);
    border-radius: 12px;
    padding: 16px;
    box-shadow: 0 2px 8px rgba(55,70,33,0.04);
}

.search-box input {
    border: 1px solid var(--border-beige);
    background: var(--white);
    color: var(--text-dark);
}

.search-box input:focus {
    border-color: var(--olive-light);
    background: var(--white);
}

.search-icon {
    color: var(--text-muted);
}

/* Buttons */
.btn-primary {
    background: var(--olive-primary);
    color: var(--white);
}

.btn-primary:hover {
    background: var(--olive-dark);
}

.btn-secondary {
    background: var(--olive-tint);
    color: var(--olive-dark);
}

.btn-secondary:hover {
    background: #DDE5D2;
}

/* Messages */
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

/* Vault cards */
.vault-card,
.empty-state {
    background: var(--card-beige);
    border: 1px solid var(--border-beige);
    box-shadow: 0 2px 8px rgba(55,70,33,0.04);
}

.vault-card:hover {
    box-shadow: 0 8px 18px rgba(55,70,33,0.08);
}

.vault-title,
.empty-state h3 {
    color: var(--olive-dark);
}

.vault-label,
.vault-notes {
    color: var(--text-muted);
}

.vault-value {
    background: var(--card-alt);
    border: 1px solid var(--border-beige);
}

.vault-value-text {
    color: var(--text-dark);
}

.show-value {
    color: var(--olive-primary);
}

.edit-btn {
    background: var(--olive-tint);
    color: var(--olive-dark);
}

.delete-btn {
    background: #F5E4E4;
    color: var(--danger);
}

/* Modal */
.modal-overlay {
    background: rgba(42,44,36,0.48);
}

.modal {
    background: var(--card-beige);
    border: 1px solid var(--border-beige);
}

.modal-header h2 {
    color: var(--olive-dark);
}

.close-modal {
    background: var(--olive-tint);
    color: var(--olive-dark);
}

/* Form */
.form-group label {
    color: var(--text-dark);
}

.form-group input,
.form-group textarea {
    border: 1px solid var(--border-beige);
    background: var(--white);
    color: var(--text-dark);
}

.form-group input:focus,
.form-group textarea:focus {
    border-color: var(--olive-light);
    background: var(--white);
}

/* Responsive sidebar */
@media (max-width: 900px) {
    .sidebar {
        width: 220px;
        min-width: 220px;
    }

    .main {
        margin-left: 220px;
    }
}

@media (max-width: 700px) {
    .sidebar {
        position: relative;
        width: 100%;
        min-width: 100%;
        height: auto;
        padding: 20px;
        border-bottom: 1px solid var(--border-beige);
    }

    .main {
        margin-left: 0;
    }

    .sidebar-footer {
        margin-top: 15px;
    }
}

</style>

</head>


<body>


<!-- =========================================================
     SIDEBAR
========================================================= -->

<aside class="sidebar">

    <div class="sidebar-brand">
        My Calendar
    </div>

    <ul class="nav-list">
        <li class="nav-item"><a href="dashboard.php">Calendar</a></li>
        <li class="nav-item"><a href="events.php">Events</a></li>
        <li class="nav-item"><a href="todo.php">To-Do List</a></li>
        <li class="nav-item"><a href="income.php">Income</a></li>
        <li class="nav-item"><a href="expenses.php">Expenses</a></li>
        <li class="nav-item"><a href="reports.php">Reports</a></li>
        <li class="nav-item active"><a href="personal_vault.php">Personal Vault</a></li>
    </ul>

    <div class="sidebar-footer">
        <ul class="nav-list">
            <li class="nav-item"><a href="profile.php"><?= $sidebar_user_name ?></a></li>
            <li class="nav-item logout"><a href="login.php">Logout</a></li>
        </ul>
    </div>

</aside>


<!-- =========================================================
     MAIN CONTENT
========================================================= -->

<main class="main">


    <header class="page-header">

        <div>

            <h1>Personal Vault</h1>

            <p>
                Store and quickly find your important personal information.
            </p>

        </div>

    </header>


    <!-- =====================================================
         MESSAGE
    ====================================================== -->

    <?php if ($message !== "") : ?>

        <div class="message <?php echo clean($message_type); ?>">

            <?php echo clean($message); ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         SEARCH + ADD
    ====================================================== -->

    <form
        method="GET"
        class="search-area"
    >

        <div class="search-box">

            <span class="search-icon">⌕</span>

            <input
                type="text"
                name="search"
                placeholder="Search your information..."
                value="<?php echo clean($search); ?>"
                autocomplete="off"
            >

        </div>


        <button
            type="submit"
            class="btn btn-secondary"
        >
            Search
        </button>


        <button
            type="button"
            class="btn btn-primary"
            onclick="openAddModal()"
        >
            + Add Information
        </button>

    </form>


    <!-- =====================================================
         VAULT RECORDS
    ====================================================== -->

    <?php if (empty($vault_items)) : ?>

        <div class="empty-state">

            <?php if ($search !== "") : ?>

                <h3>No information found</h3>

                <p>
                    No Personal Vault information matches
                    "<?php echo clean($search); ?>".
                </p>

            <?php else : ?>

                <h3>Your Personal Vault is empty</h3>

                <p>
                    Add your important personal information
                    and find it easily whenever you need it.
                </p>

            <?php endif; ?>

        </div>

    <?php else : ?>

        <div class="vault-grid">

            <?php foreach ($vault_items as $item) : ?>

                <article class="vault-card">

                    <div class="vault-card-header">

                        <div>

                            <div class="vault-title">
                                <?php echo clean($item["title"]); ?>
                            </div>

                            <div class="vault-label">
                                <?php echo clean($item["information"]); ?>
                            </div>

                        </div>

                    </div>


                    <div class="vault-value">

                        <span
                            class="vault-value-text"
                            data-value="<?php echo clean($item["value"]); ?>"
                        >
                            ••••••••••••
                        </span>

                        <button
                            type="button"
                            class="show-value"
                            onclick="toggleValue(this)"
                        >
                            Show
                        </button>

                    </div>


                    <?php if (
                        isset($item["notes"]) &&
                        trim($item["notes"]) !== ""
                    ) : ?>

                        <div class="vault-notes">

                            <?php echo nl2br(clean($item["notes"])); ?>

                        </div>

                    <?php endif; ?>


                    <?php if (!empty($item["file_path"])) : ?>
                        <div class="vault-attachment">
                            <span class="attachment-name">
                                <?php echo clean($item["file_name"] ?: "Attached file"); ?>
                            </span>
                        </div>
                    <?php endif; ?>


                    <div class="vault-actions">

                        <?php if (!empty($item["file_path"])) : ?>
                            <a
                                href="<?php echo clean($item["file_path"]); ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="small-btn view-btn"
                            >
                                View
                            </a>
                        <?php endif; ?>

                        <button
                            type="button"
                            class="small-btn edit-btn"
                            onclick='editVault(
                                <?php echo json_encode(
                                    $item,
                                    JSON_HEX_TAG |
                                    JSON_HEX_APOS |
                                    JSON_HEX_QUOT |
                                    JSON_HEX_AMP
                                ); ?>
                            )'
                        >
                            Edit
                        </button>


                        <form
                            method="POST"
                            onsubmit="return confirmDelete();"
                        >

                            <input
                                type="hidden"
                                name="vault_id"
                                value="<?php echo (int)$item["id"]; ?>"
                            >

                            <button
                                type="submit"
                                name="delete_vault"
                                class="small-btn delete-btn"
                            >
                                Delete
                            </button>

                        </form>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>


</main>


<!-- =========================================================
     ADD / EDIT MODAL
========================================================= -->

<div
    class="modal-overlay"
    id="vaultModal"
>

    <div class="modal">


        <div class="modal-header">

            <h2 id="modalTitle">
                Add Information
            </h2>


            <button
                type="button"
                class="close-modal"
                onclick="closeModal()"
            >
                ×
            </button>

        </div>


        <form
            method="POST"
            id="vaultForm"
            enctype="multipart/form-data"
        >

            <input
                type="hidden"
                name="vault_id"
                id="vault_id"
                value="0"
            >


            <div class="form-group">

                <label for="title">
                    Title
                </label>

                <input
                    type="text"
                    id="title"
                    name="title"
                    placeholder="e.g. SBI Bank Account"
                    maxlength="150"
                    required
                >

            </div>


            <div class="form-group">

                <label for="information">
                    Information
                </label>

                <input
                    type="text"
                    id="information"
                    name="information"
                    placeholder="e.g. Account Number"
                    maxlength="150"
                    required
                >

            </div>


            <div class="form-group">

                <label for="value">
                    Value
                </label>

                <input
                    type="text"
                    id="value"
                    name="value"
                    placeholder="Enter the information"
                    required
                >

            </div>


            <div class="form-group">

                <label for="attachment">
                    Upload PDF or Image
                </label>

                <input
                    type="file"
                    id="attachment"
                    name="attachment"
                    accept=".pdf,image/jpeg,image/png,image/gif,image/webp"
                >

                <small class="file-help">
                    PDF, JPG, JPEG, PNG, GIF or WEBP — maximum 10 MB.
                </small>

                <div
                    id="currentAttachment"
                    class="current-attachment"
                ></div>

            </div>


            <div class="form-group">

                <label for="notes">
                    Notes
                </label>

                <textarea
                    id="notes"
                    name="notes"
                    placeholder="Add any additional information (optional)"
                ></textarea>

            </div>


            <div class="form-actions">

                <button
                    type="button"
                    class="btn btn-secondary"
                    onclick="closeModal()"
                >
                    Cancel
                </button>


                <button
                    type="submit"
                    name="save_vault"
                    class="btn btn-primary"
                >
                    Save Information
                </button>

            </div>

        </form>

    </div>

</div>


<script>

/* =========================================================
   MODAL
========================================================= */

const vaultModal =
    document.getElementById("vaultModal");


const modalTitle =
    document.getElementById("modalTitle");


const vaultForm =
    document.getElementById("vaultForm");


function openAddModal() {

    modalTitle.textContent =
        "Add Information";

    vaultForm.reset();

    document.getElementById("vault_id").value =
        "0";

    document.getElementById("attachment").value = "";
    document.getElementById("currentAttachment").textContent = "";

    vaultModal.classList.add("show");
}


function escapeHtml(value) {
    return String(value || "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}


function editVault(item) {

    modalTitle.textContent =
        "Edit Information";

    document.getElementById("vault_id").value =
        item.id;

    document.getElementById("title").value =
        item.title || "";

    document.getElementById("information").value =
        item.information || "";

    document.getElementById("value").value =
        item.value || "";

    document.getElementById("notes").value =
        item.notes || "";

    document.getElementById("attachment").value = "";

    const currentAttachment =
        document.getElementById("currentAttachment");

    if (item.file_path) {
        currentAttachment.innerHTML =
            'Current file: <a href="' +
            encodeURI(item.file_path) +
            '" target="_blank" rel="noopener noreferrer">' +
            escapeHtml(item.file_name || "View attachment") +
            '</a>';
    } else {
        currentAttachment.textContent = "";
    }

    vaultModal.classList.add("show");
}


function closeModal() {

    vaultModal.classList.remove("show");

    vaultForm.reset();

    document.getElementById("vault_id").value =
        "0";

    document.getElementById("attachment").value = "";
    document.getElementById("currentAttachment").textContent = "";
}


/* =========================================================
   CLOSE WHEN CLICKING OUTSIDE
========================================================= */

vaultModal.addEventListener(
    "click",
    function(event) {

        if (event.target === vaultModal) {
            closeModal();
        }

    }
);


/* =========================================================
   ESCAPE KEY
========================================================= */

document.addEventListener(
    "keydown",
    function(event) {

        if (event.key === "Escape") {
            closeModal();
        }

    }
);


/* =========================================================
   SHOW / HIDE VALUE
========================================================= */

function toggleValue(button) {

    const container =
        button.parentElement;

    const valueElement =
        container.querySelector(
            ".vault-value-text"
        );

    const realValue =
        valueElement.getAttribute(
            "data-value"
        );


    if (
        valueElement.textContent.includes("•")
    ) {

        valueElement.textContent =
            realValue;

        button.textContent =
            "Hide";

    } else {

        valueElement.textContent =
            "••••••••••••";

        button.textContent =
            "Show";
    }
}


/* =========================================================
   DELETE CONFIRMATION
========================================================= */

function confirmDelete() {

    return confirm(
        "Are you sure you want to delete this information?"
    );
}

</script>


</body>

</html>