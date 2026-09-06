<?php
/*
|--------------------------------------------------------------------------
| Rental Network - Category Management
|--------------------------------------------------------------------------
| Independent Category Management module.
| This page manages only the categories table.
| Item Management remains a separate module.
|--------------------------------------------------------------------------
*/

require "database/connection.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* CSRF protection for all state-changing category actions. */
if (empty($_SESSION["category_csrf_token"])) {
    $_SESSION["category_csrf_token"] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION["category_csrf_token"];

/* Authentication note:
   Login/authentication is handled by the project's authentication module.
   Do not guess a session key here; the integration owner should add the
   project's actual admin authorization guard once that convention is finalized. */

/* ----------------------------- Helpers ----------------------------- */

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function redirectTo(string $message = "", string $type = "success"): void
{
    $params = [];
    if ($message !== "") {
        $params["message"] = $message;
        $params["type"] = $type;
    }

    header("Location: category_management.php" . ($params ? "?" . http_build_query($params) : ""));
    exit;
}

function dbPrepare(mysqli $conn, string $sql): mysqli_stmt
{
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException("Database operation could not be prepared.");
    }

    return $stmt;
}

/* ----------------------------- Database ----------------------------- */

$conn->query("
    CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_name VARCHAR(100) NOT NULL UNIQUE,
        description VARCHAR(255) DEFAULT NULL,
        image_path VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

/* Support older category tables without changing existing data. */
$columns = [];
$columnResult = $conn->query("SHOW COLUMNS FROM categories");

if ($columnResult) {
    while ($column = $columnResult->fetch_assoc()) {
        $columns[$column["Field"]] = true;
    }
}

if (!isset($columns["image_path"])) {
    $conn->query("ALTER TABLE categories ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER description");
}

/* ----------------------------- Uploads ----------------------------- */

$uploadDir = __DIR__ . "/uploads/categories/";
$uploadWebPath = "uploads/categories/";

if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

/* ----------------------------- Category usage check ----------------------------- */

function categoryIsInUse(mysqli $conn, int $categoryId): bool
{
    /* Item Management remains a separate module. If integration later adds
       items.category_id, this check automatically prevents orphaning items. */
    $tableCheck = $conn->query("SHOW TABLES LIKE 'items'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        return false;
    }

    $columnCheck = $conn->query("SHOW COLUMNS FROM items LIKE 'category_id'");
    if (!$columnCheck || $columnCheck->num_rows === 0) {
        return false;
    }

    $stmt = dbPrepare($conn, "SELECT 1 FROM items WHERE category_id = ? LIMIT 1");
    $stmt->bind_param("i", $categoryId);
    $stmt->execute();
    $inUse = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $inUse;
}

/* ----------------------------- Messages ----------------------------- */

$flashMessage = $_GET["message"] ?? "";
$flashType = $_GET["type"] ?? "success";

/* ----------------------------- POST Actions ----------------------------- */

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if (!hash_equals(
        $_SESSION["category_csrf_token"] ?? "",
        $_POST["csrf_token"] ?? ""
    )) {
        redirectTo("Security check failed. Please try again.", "error");
    }

    try {
        /* ---------- ADD CATEGORY ---------- */
        if ($action === "add") {
            $name = trim($_POST["category_name"] ?? "");
            $description = trim($_POST["description"] ?? "");

            if ($name === "") {
                redirectTo("Category name is required.", "error");
            }

            if (mb_strlen($name) > 100) {
                redirectTo("Category name cannot exceed 100 characters.", "error");
            }

            if (mb_strlen($description) > 255) {
                redirectTo("Description cannot exceed 255 characters.", "error");
            }

            /* Case-insensitive duplicate protection. */
            $check = dbPrepare(
                $conn,
                "SELECT id FROM categories
                 WHERE LOWER(TRIM(category_name)) = LOWER(TRIM(?))
                 LIMIT 1"
            );
            $check->bind_param("s", $name);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            if ($exists) {
                redirectTo(
                    "This category already exists. Use the existing category instead.",
                    "error"
                );
            }

            $imagePath = null;

            if (isset($_FILES["category_image"]) &&
                $_FILES["category_image"]["error"] !== UPLOAD_ERR_NO_FILE) {

                $file = $_FILES["category_image"];

                if ($file["error"] !== UPLOAD_ERR_OK) {
                    redirectTo("Category image could not be uploaded.", "error");
                }

                if ($file["size"] > 3 * 1024 * 1024) {
                    redirectTo("Category image must be 3 MB or smaller.", "error");
                }

                $allowedMime = [
                    "image/jpeg" => "jpg",
                    "image/png"  => "png",
                    "image/webp" => "webp"
                ];

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file["tmp_name"]);

                if (!isset($allowedMime[$mime])) {
                    redirectTo("Only JPG, PNG and WEBP images are allowed.", "error");
                }

                $filename = "category_" . bin2hex(random_bytes(10)) . "." . $allowedMime[$mime];
                $destination = $uploadDir . $filename;

                if (!move_uploaded_file($file["tmp_name"], $destination)) {
                    redirectTo("Could not save the category image.", "error");
                }

                $imagePath = $uploadWebPath . $filename;
            }

            $stmt = dbPrepare(
                $conn,
                "INSERT INTO categories (category_name, description, image_path)
                 VALUES (?, ?, ?)"
            );
            $stmt->bind_param("sss", $name, $description, $imagePath);
            $stmt->execute();
            $stmt->close();

            redirectTo("Category created successfully.");
        }

        /* ---------- EDIT CATEGORY ---------- */
        if ($action === "edit") {
            $id = (int)($_POST["category_id"] ?? 0);
            $name = trim($_POST["category_name"] ?? "");
            $description = trim($_POST["description"] ?? "");

            if ($id <= 0 || $name === "") {
                redirectTo("Please provide valid category details.", "error");
            }

            if (mb_strlen($name) > 100) {
                redirectTo("Category name cannot exceed 100 characters.", "error");
            }

            if (mb_strlen($description) > 255) {
                redirectTo("Description cannot exceed 255 characters.", "error");
            }

            $check = dbPrepare(
                $conn,
                "SELECT id FROM categories
                 WHERE LOWER(TRIM(category_name)) = LOWER(TRIM(?))
                 AND id <> ?
                 LIMIT 1"
            );
            $check->bind_param("si", $name, $id);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            if ($exists) {
                redirectTo("Another category already uses this name.", "error");
            }

            $currentStmt = dbPrepare(
                $conn,
                "SELECT image_path FROM categories WHERE id = ? LIMIT 1"
            );
            $currentStmt->bind_param("i", $id);
            $currentStmt->execute();
            $current = $currentStmt->get_result()->fetch_assoc();
            $currentStmt->close();

            if (!$current) {
                redirectTo("Category not found.", "error");
            }

            $imagePath = $current["image_path"];
            $oldImagePath = $current["image_path"];
            $newUploadedImage = false;

            if (isset($_FILES["category_image"]) &&
                $_FILES["category_image"]["error"] !== UPLOAD_ERR_NO_FILE) {

                $file = $_FILES["category_image"];

                if ($file["error"] !== UPLOAD_ERR_OK) {
                    redirectTo("Category image could not be uploaded.", "error");
                }

                if ($file["size"] > 3 * 1024 * 1024) {
                    redirectTo("Category image must be 3 MB or smaller.", "error");
                }

                $allowedMime = [
                    "image/jpeg" => "jpg",
                    "image/png"  => "png",
                    "image/webp" => "webp"
                ];

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file["tmp_name"]);

                if (!isset($allowedMime[$mime])) {
                    redirectTo("Only JPG, PNG and WEBP images are allowed.", "error");
                }

                $filename = "category_" . bin2hex(random_bytes(10)) . "." . $allowedMime[$mime];
                $destination = $uploadDir . $filename;

                if (!move_uploaded_file($file["tmp_name"], $destination)) {
                    redirectTo("Could not save the category image.", "error");
                }

                $imagePath = $newImagePath;
                $newUploadedImage = true;
            }

            $stmt = dbPrepare(
                $conn,
                "UPDATE categories
                 SET category_name = ?, description = ?, image_path = ?
                 WHERE id = ?"
            );
            $stmt->bind_param("sssi", $name, $description, $imagePath, $id);
            $stmt->execute();
            $stmt->close();

            /* Delete the old image only after the database update succeeds. */
            if ($newUploadedImage &&
                !empty($oldImagePath) &&
                strpos($oldImagePath, $uploadWebPath) === 0) {
                $oldFile = __DIR__ . "/" . $oldImagePath;
                if (is_file($oldFile)) {
                    @unlink($oldFile);
                }
            }

            redirectTo("Category updated successfully.");
        }

        /* ---------- DELETE CATEGORY ---------- */
        if ($action === "delete") {
            $id = (int)($_POST["category_id"] ?? 0);

            if ($id <= 0) {
                redirectTo("Invalid category.", "error");
            }

            if (categoryIsInUse($conn, $id)) {
                redirectTo(
                    "This category is currently used by one or more items and cannot be deleted.",
                    "error"
                );
            }

            $imageStmt = dbPrepare(
                $conn,
                "SELECT image_path FROM categories WHERE id = ? LIMIT 1"
            );
            $imageStmt->bind_param("i", $id);
            $imageStmt->execute();
            $category = $imageStmt->get_result()->fetch_assoc();
            $imageStmt->close();

            if (!$category) {
                redirectTo("Category not found.", "error");
            }

            $deleteStmt = dbPrepare(
                $conn,
                "DELETE FROM categories WHERE id = ?"
            );
            $deleteStmt->bind_param("i", $id);
            $deleteStmt->execute();

            if ($deleteStmt->affected_rows !== 1) {
                $deleteStmt->close();
                redirectTo("Category could not be deleted.", "error");
            }

            $deleteStmt->close();

            if (!empty($category["image_path"]) &&
                strpos($category["image_path"], $uploadWebPath) === 0) {

                $filePath = __DIR__ . "/" . $category["image_path"];

                if (is_file($filePath)) {
                    @unlink($filePath);
                }
            }

            redirectTo("Category deleted successfully.");
        }
    } catch (Throwable $ex) {
        redirectTo(
            "Something went wrong while processing the category.",
            "error"
        );
    }
}

/* ----------------------------- Filters ----------------------------- */

$search = trim($_GET["search"] ?? "");
$sort = $_GET["sort"] ?? "newest";
$view = $_GET["view"] ?? "grid";

if (!in_array($view, ["grid", "list"], true)) {
    $view = "grid";
}

$sortSql = match ($sort) {
    "name_asc"  => "c.category_name ASC",
    "name_desc" => "c.category_name DESC",
    "oldest"    => "c.created_at ASC, c.id ASC",
    default     => "c.created_at DESC, c.id DESC"
};

$like = "%" . $search . "%";

$sql = "
    SELECT
        c.id,
        c.category_name,
        c.description,
        c.image_path,
        c.created_at
    FROM categories c
    WHERE c.category_name LIKE ?
       OR c.description LIKE ?
    ORDER BY $sortSql
";

$stmt = dbPrepare($conn, $sql);
$stmt->bind_param("ss", $like, $like);
$stmt->execute();
$result = $stmt->get_result();

$categories = [];

while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

$stmt->close();

/* ----------------------------- Dashboard Stats ----------------------------- */

$countResult = $conn->query("SELECT COUNT(*) AS total FROM categories");
$totalCategories = $countResult
    ? (int)$countResult->fetch_assoc()["total"]
    : count($categories);

$newestResult = $conn->query(
    "SELECT id, category_name, created_at
     FROM categories
     ORDER BY created_at DESC, id DESC
     LIMIT 1"
);
$newestCategory = $newestResult
    ? $newestResult->fetch_assoc()
    : null;

$monthResult = $conn->query(
    "SELECT COUNT(*) AS total
     FROM categories
     WHERE YEAR(created_at) = YEAR(CURDATE())
       AND MONTH(created_at) = MONTH(CURDATE())"
);
$thisMonth = $monthResult
    ? (int)$monthResult->fetch_assoc()["total"]
    : 0;

/* ----------------------------- Page ----------------------------- */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Category Management | Rental Network</title>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
>
<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    rel="stylesheet"
>

<style>
:root {
    --bg: #f5f8fc;
    --card: #ffffff;
    --text: #101d38;
    --muted: #687894;
    --border: #e4eaf3;
    --primary: #2563eb;
    --primary-dark: #1d4ed8;
    --shadow: 0 10px 28px rgba(31, 53, 86, .07);
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background:
        radial-gradient(circle at 85% 5%, rgba(37,99,235,.045), transparent 25%),
        var(--bg);
    color: var(--text);
    font-family:
        Inter,
        ui-sans-serif,
        system-ui,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        sans-serif;
}

.page {
    min-height: 100vh;
    padding: 28px 24px 42px;
}

.container-main {
    width: min(1320px, 100%);
    margin: 0 auto;
}

/* ---------- Header ---------- */

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 24px;
    margin-bottom: 22px;
}

.eyebrow {
    color: var(--primary);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
    margin-bottom: 5px;
}

h1 {
    margin: 0;
    font-size: 30px;
    line-height: 1.15;
    font-weight: 800;
    letter-spacing: -.025em;
}

.subtitle {
    margin: 7px 0 0;
    color: var(--muted);
    font-size: 13px;
}

.add-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    border: 0;
    border-radius: 10px;
    background: var(--primary);
    color: #fff;
    padding: 11px 17px;
    font-size: 13px;
    font-weight: 750;
    box-shadow: 0 8px 20px rgba(37,99,235,.22);
    transition: .18s ease;
}

.add-btn:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    color: #fff;
}

/* ---------- Stats ---------- */

.stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 17px;
}

.stat-card {
    background: rgba(255,255,255,.9);
    border: 1px solid var(--border);
    border-radius: 13px;
    box-shadow: var(--shadow);
    padding: 15px 16px;
    min-height: 91px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.stat-icon {
    width: 43px;
    height: 43px;
    flex: 0 0 43px;
    border-radius: 13px;
    display: grid;
    place-items: center;
    background: #edf3ff;
    color: var(--primary);
    font-size: 19px;
}

.stat-icon.purple {
    background: #f1edff;
    color: #7457df;
}

.stat-icon.green {
    background: #e8f9f0;
    color: #18a15a;
}

.stat-content {
    min-width: 0;
}

.stat-label {
    color: var(--muted);
    font-size: 11px;
    margin-bottom: 3px;
}

.stat-value {
    color: var(--text);
    font-size: 16px;
    font-weight: 800;
    line-height: 1.25;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.stat-small {
    color: var(--muted);
    font-size: 10px;
    margin-top: 3px;
}

.stat-card.clickable-stat {
    cursor: pointer;
    position: relative;
    user-select: none;
}

.stat-card.clickable-stat::after {
    content: "\2192";
    position: absolute;
    right: 15px;
    bottom: 13px;
    color: #a4afbf;
    font-size: 13px;
    transition: .18s ease;
}

.stat-card.clickable-stat:hover {
    border-color: #c9d9f6;
    box-shadow: 0 14px 32px rgba(31,53,86,.11);
    transform: translateY(-2px);
}

.stat-card.clickable-stat:hover::after {
    color: var(--primary);
    transform: translateX(3px);
}

.stat-hint {
    color: var(--primary);
    font-size: 9px;
    font-weight: 700;
    margin-top: 3px;
    opacity: .9;
}

.category-card.newest-highlight {
    position: relative;
    z-index: 3;
    animation: newestPulse 1s ease-in-out 0s 4;
    border: 2px solid #2563eb !important;
    box-shadow:
        0 0 0 4px rgba(37,99,235,.18),
        0 0 24px rgba(37,99,235,.38),
        0 18px 40px rgba(37,99,235,.18) !important;
}

/* The list view uses a table row rather than a category-card. */
.list-table tr.newest-highlight > td {
    position: relative;
    background: rgba(239,246,255,.92) !important;
    border-top: 2px solid #2563eb !important;
    border-bottom: 2px solid #2563eb !important;
    box-shadow: inset 0 0 22px rgba(37,99,235,.13);
}

.list-table tr.newest-highlight > td:first-child {
    border-left: 5px solid #2563eb !important;
}

.list-table tr.newest-highlight > td:last-child {
    border-right: 2px solid #2563eb !important;
}

@keyframes newestPulse {
    0%, 100% {
        border-color: #2563eb;
        box-shadow:
            0 0 0 4px rgba(37,99,235,.18),
            0 0 24px rgba(37,99,235,.38),
            0 18px 40px rgba(37,99,235,.18);
    }
    50% {
        border-color: #60a5fa;
        box-shadow:
            0 0 0 9px rgba(37,99,235,.28),
            0 0 42px rgba(37,99,235,.58),
            0 18px 46px rgba(37,99,235,.22);
    }
}

@keyframes newestRowPulse {
    0%, 100% {
        background: rgba(239,246,255,.92);
        box-shadow: inset 0 0 22px rgba(37,99,235,.13);
    }
    50% {
        background: rgba(219,234,254,.98);
        box-shadow: inset 0 0 36px rgba(37,99,235,.30), 0 0 22px rgba(37,99,235,.24);
    }
}

.list-table tr.newest-highlight {
    animation: newestRowPulse 1s ease-in-out 0s 4;
}

/* ---------- Info ---------- */

.info-banner {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 11px;
    background: linear-gradient(100deg, #f7faff, #fbfdff);
    border: 1px solid #d9e6ff;
    border-radius: 13px;
    padding: 13px 15px;
    margin-bottom: 16px;
}

.info-icon {
    width: 26px;
    height: 26px;
    flex: 0 0 26px;
    display: grid;
    place-items: center;
    color: var(--primary);
    font-size: 16px;
}

.info-title {
    font-size: 12px;
    font-weight: 800;
    margin-bottom: 2px;
}

.info-text {
    color: var(--muted);
    font-size: 11px;
}

.info-copy {
    min-width: 0;
    padding-right: 34px;
}

.info-close {
    position: absolute;
    top: 50%;
    right: 12px;
    transform: translateY(-50%);
    width: 30px;
    height: 30px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: #8b98aa;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: .18s ease;
}

.info-close:hover {
    background: #eef4ff;
    color: var(--primary);
}

.info-banner.info-hidden {
    display: none;
}

/* ---------- Toast ---------- */

.category-toast {
    position: fixed;
    top: 20px;
    right: 22px;
    z-index: 2000;
    width: min(385px, calc(100vw - 28px));
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 12px 12px 13px;
    background: #fff;
    border: 1px solid var(--border);
    border-left: 3px solid #16a34a;
    border-radius: 12px;
    box-shadow: 0 16px 38px rgba(21,35,59,.15);
    animation: toastIn .25s ease;
    transition: opacity .35s ease, transform .35s ease;
}

.toast-error {
    border-left-color: #dc2626;
}

.toast-icon {
    width: 29px;
    height: 29px;
    flex: 0 0 29px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: #eaf8ef;
    color: #16803a;
}

.toast-error .toast-icon {
    background: #fff0f0;
    color: #c62828;
}

.toast-content {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 1px;
}

.toast-content strong {
    font-size: 12px;
}

.toast-content span {
    color: var(--muted);
    font-size: 11px;
    line-height: 1.35;
}

.toast-close {
    margin-left: auto;
    width: 25px;
    height: 25px;
    border: 0;
    border-radius: 7px;
    background: transparent;
    color: #98a2b3;
}

.toast-close:hover {
    background: #f2f4f7;
    color: #344054;
}

@keyframes toastIn {
    from {
        opacity: 0;
        transform: translateY(-9px) translateX(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0) translateX(0);
    }
}

/* ---------- Toolbar ---------- */

.toolbar {
    display: flex;
    align-items: center;
    gap: 9px;
    background: rgba(255,255,255,.96);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 9px;
    box-shadow: var(--shadow);
    margin-bottom: 18px;
}

.search-wrap {
    position: relative;
    flex: 1;
}

.search-wrap i {
    position: absolute;
    left: 13px;
    top: 50%;
    transform: translateY(-50%);
    color: #9aa6b8;
    font-size: 14px;
}

.search-wrap input {
    width: 100%;
    height: 40px;
    border: 1px solid #e0e6ef;
    border-radius: 9px;
    outline: none;
    padding: 0 12px 0 37px;
    color: var(--text);
    font-size: 11px;
    background: #fff;
}

.search-wrap input:focus {
    border-color: #a8c1f8;
    box-shadow: 0 0 0 3px rgba(37,99,235,.07);
}

.select-control {
    height: 40px;
    min-width: 150px;
    border: 1px solid #e0e6ef;
    border-radius: 9px;
    background: #fff;
    color: #344054;
    font-size: 11px;
    padding: 0 11px;
    outline: none;
}

.view-switch {
    display: flex;
    gap: 2px;
    border: 1px solid #e0e6ef;
    border-radius: 9px;
    padding: 2px;
    height: 40px;
}

.view-switch a {
    width: 35px;
    display: grid;
    place-items: center;
    border-radius: 7px;
    color: #8490a1;
    text-decoration: none;
    font-size: 13px;
}

.view-switch a.active {
    background: #edf3ff;
    color: var(--primary);
}

/* ---------- Result line ---------- */

.result-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: var(--muted);
    font-size: 11px;
    margin: 0 2px 10px;
}

.result-line strong {
    color: var(--text);
    font-weight: 800;
}

/* ---------- Cards ---------- */

.category-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 17px;
}

.category-card {
    position: relative;
    overflow: hidden;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 14px;
    box-shadow: var(--shadow);
    transition:
        transform .18s ease,
        box-shadow .18s ease,
        border-color .18s ease;
}

.category-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 17px 36px rgba(31,53,86,.12);
    border-color: #d7e0ed;
}

.category-image {
    height: 142px;
    background: #edf2f8;
    position: relative;
    overflow: hidden;
}

.category-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform .35s ease;
}

.category-card:hover .category-image img {
    transform: scale(1.025);
}

.image-placeholder {
    width: 100%;
    height: 100%;
    display: grid;
    place-items: center;
    color: #a7b1c0;
    background: linear-gradient(135deg, #eff3f8, #e8edf5);
    font-size: 36px;
}

.card-menu {
    position: absolute;
    right: 9px;
    top: 9px;
}

.card-menu > button {
    width: 32px;
    height: 32px;
    border: 1px solid rgba(255,255,255,.9);
    border-radius: 9px;
    background: rgba(255,255,255,.94);
    color: #475467;
    box-shadow: 0 5px 13px rgba(31,53,86,.08);
    backdrop-filter: blur(7px);
}

.card-menu > button:hover {
    color: var(--primary);
}

.dropdown-menu {
    min-width: 175px;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 5px;
    box-shadow: 0 15px 32px rgba(20,32,55,.14);
}

.dropdown-item {
    border-radius: 7px;
    font-size: 11px;
    padding: 8px 9px;
}

.card-content {
    padding: 13px 14px 14px;
}

.category-name {
    color: var(--text);
    font-size: 15px;
    font-weight: 800;
    line-height: 1.25;
    margin-bottom: 5px;
}

.category-description {
    color: #6d7a90;
    font-size: 10.5px;
    line-height: 1.48;
    min-height: 46px;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.card-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    border-top: 1px solid #edf0f4;
    margin-top: 11px;
    padding-top: 10px;
}

.created-date {
    color: #8090a7;
    font-size: 9.5px;
}

.created-date i {
    color: #7e8ea4;
}

/* ---------- List ---------- */

.list-wrap {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 14px;
    box-shadow: var(--shadow);
    overflow: hidden;
}

.list-table {
    width: 100%;
    border-collapse: collapse;
}

.list-table th {
    background: #f8fafc;
    color: #7c899d;
    text-transform: uppercase;
    letter-spacing: .05em;
    font-size: 9px;
    padding: 12px 15px;
    text-align: left;
}

.list-table td {
    padding: 11px 15px;
    border-top: 1px solid #edf0f4;
    vertical-align: middle;
    font-size: 11px;
}

.list-category {
    display: flex;
    align-items: center;
    gap: 10px;
}

.list-thumb,
.list-thumb-placeholder {
    width: 48px;
    height: 40px;
    flex: 0 0 48px;
    border-radius: 8px;
    object-fit: cover;
    background: #edf2f8;
}

.list-thumb-placeholder {
    display: grid;
    place-items: center;
    color: #a1acbb;
}

.list-name {
    font-weight: 750;
}

.list-description {
    max-width: 440px;
    color: var(--muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ---------- Empty ---------- */

.empty {
    background: #fff;
    border: 1px dashed #d7dfeb;
    border-radius: 14px;
    padding: 55px 20px;
    text-align: center;
}

.empty-icon {
    width: 58px;
    height: 58px;
    margin: 0 auto 12px;
    display: grid;
    place-items: center;
    border-radius: 15px;
    background: #edf3ff;
    color: var(--primary);
    font-size: 24px;
}

.empty h3 {
    font-size: 16px;
    margin-bottom: 5px;
}

.empty p {
    max-width: 450px;
    margin: 0 auto 15px;
    color: var(--muted);
    font-size: 11px;
}

/* ---------- Modal ---------- */

.modal-content {
    border: 0;
    border-radius: 15px;
    box-shadow: 0 25px 70px rgba(0,0,0,.18);
}

.modal-header {
    padding: 19px 21px 9px;
    border: 0;
}

.modal-body {
    padding: 9px 21px 20px;
}

.modal-footer {
    padding: 11px 21px 18px;
    border: 0;
}

.form-label {
    color: #344054;
    font-size: 11px;
    font-weight: 750;
}

.form-control {
    border-color: #e0e6ef;
    border-radius: 9px;
    font-size: 12px;
    padding: 9px 11px;
}

.form-control:focus {
    border-color: #a8c1f8;
    box-shadow: 0 0 0 3px rgba(37,99,235,.07);
}

.upload-box {
    display: block;
    cursor: pointer;
    text-align: center;
    border: 1.5px dashed #cbd5e1;
    border-radius: 10px;
    padding: 14px;
    background: #fafcff;
}

.upload-box:hover {
    border-color: #9dbafc;
    background: #f7faff;
}

.upload-box i {
    color: var(--primary);
    font-size: 20px;
}

.upload-box strong {
    font-size: 12px;
}

.upload-box small {
    display: block;
    color: var(--muted);
    font-size: 9px;
    margin-top: 3px;
}

.preview {
    width: 100%;
    height: 110px;
    object-fit: cover;
    border-radius: 9px;
    margin-top: 8px;
    display: none;
}

.btn {
    border-radius: 8px;
    font-size: 11px;
    font-weight: 700;
    padding: 8px 13px;
}

/* ---------- Responsive ---------- */


@media (prefers-reduced-motion: reduce) {
    .category-card,
    .stat-card,
    .stat-card.clickable-stat::after {
        transition: none !important;
    }

    .category-card.newest-highlight {
        animation: none !important;
        border-color: #2563eb !important;
        box-shadow: 0 0 0 5px rgba(37,99,235,.18), 0 0 24px rgba(37,99,235,.35) !important;
    }

    .list-table tr.newest-highlight {
        animation: none !important;
    }

    .list-table tr.newest-highlight > td {
        background: rgba(219,234,254,.98) !important;
        border-color: #2563eb !important;
        box-shadow: inset 0 0 28px rgba(37,99,235,.25);
    }
}

@media (max-width: 1050px) {
    .stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .category-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 720px) {
    .page {
        padding: 22px 13px 35px;
    }

    .topbar {
        flex-direction: column;
    }

    .add-btn {
        width: 100%;
        justify-content: center;
    }

    .stats {
        grid-template-columns: 1fr;
    }

    .toolbar {
        flex-wrap: wrap;
    }

    .search-wrap {
        flex-basis: 100%;
    }

    .select-control {
        flex: 1;
        min-width: 0;
    }

    .category-grid {
        grid-template-columns: 1fr;
    }

    .list-wrap {
        overflow-x: auto;
    }

    .list-table {
        min-width: 690px;
    }

    .category-toast {
        top: 12px;
        right: 12px;
    }
}
</style>
</head>

<body>

<div class="page">
<main class="container-main">

    <!-- HEADER -->
    <header class="topbar">
        <div>
            <div class="eyebrow">Rental Network</div>
            <h1>Category Management</h1>
            <p class="subtitle">
                Create and maintain the standardized categories used across the Rental Network.
            </p>
        </div>

        <button
            class="add-btn"
            data-bs-toggle="modal"
            data-bs-target="#addCategoryModal"
        >
            <i class="bi bi-plus-lg"></i>
            Add Category
        </button>
    </header>

    <!-- STATS -->
    <section class="stats">

        <div
            class="stat-card clickable-stat"
            id="totalCategoriesCard"
            role="button"
            tabindex="0"
            title="View all categories"
        >
            <div class="stat-icon">
                <i class="bi bi-folder2"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total Categories</div>
                <div class="stat-value"><?= $totalCategories ?></div>
                <div class="stat-small">
                    <?= $thisMonth > 0 ? "+" . $thisMonth . " this month" : "No new categories this month" ?>
                </div>
                <div class="stat-hint">View categories</div>
            </div>
        </div>

        <div
            class="stat-card clickable-stat"
            id="newestCategoryCard"
            role="button"
            tabindex="0"
            title="Highlight newest category"
        >
            <div class="stat-icon">
                <i class="bi bi-calendar3"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Newest Category</div>
                <div class="stat-value">
                    <?= $newestCategory ? e($newestCategory["category_name"]) : "—" ?>
                </div>
                <div class="stat-small">
                    <?= $newestCategory
                        ? date("d M Y", strtotime($newestCategory["created_at"]))
                        : "No categories yet" ?>
                </div>
                <?php if ($newestCategory): ?>
                    <div class="stat-hint">Locate category</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon purple">
                <i class="bi bi-collection"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">This Month</div>
                <div class="stat-value"><?= $thisMonth ?></div>
                <div class="stat-small">
                    New category<?= $thisMonth === 1 ? "" : "ies" ?> created
                </div>
            </div>
        </div>
</section>

    <!-- INFORMATION -->
    <section class="info-banner" id="categoryInfoBanner">
        <div class="info-icon">
            <i class="bi bi-info-circle"></i>
        </div>

        <div class="info-copy">
            <div class="info-title">Centralized category definitions</div>
            <div class="info-text">
                Keep category names clear and consistent so the Rental Network has one organized category list.
            </div>
        </div>

        <button
            type="button"
            class="info-close"
            id="closeCategoryInfo"
            aria-label="Dismiss category information"
            title="Dismiss"
        >
            <i class="bi bi-x-lg"></i>
        </button>
    </section>

    <!-- TOAST -->
    <?php if ($flashMessage !== ""): ?>
        <div
            id="categoryToast"
            class="category-toast <?= $flashType === "error" ? "toast-error" : "" ?>"
            role="status"
            aria-live="polite"
        >
            <div class="toast-icon">
                <i class="bi <?= $flashType === "error" ? "bi-exclamation-circle" : "bi-check-lg" ?>"></i>
            </div>

            <div class="toast-content">
                <strong>
                    <?= $flashType === "error" ? "Action failed" : "Success" ?>
                </strong>
                <span><?= e($flashMessage) ?></span>
            </div>

            <button
                type="button"
                class="toast-close"
                onclick="hideCategoryToast()"
                aria-label="Close notification"
            >
                <i class="bi bi-x"></i>
            </button>
        </div>
    <?php endif; ?>

    <!-- TOOLBAR -->
    <form class="toolbar" method="GET" id="categorySearchForm">

        <input type="hidden" name="view" value="<?= e($view) ?>">

        <div class="search-wrap">
            <i class="bi bi-search"></i>
            <input
                type="search"
                name="search"
                value="<?= e($search) ?>"
                placeholder="Search categories..."
                aria-label="Search categories"
                id="categorySearchInput"
                autocomplete="off"
            >
        </div>

        <select
            class="select-control"
            name="sort"
            onchange="this.form.submit()"
            aria-label="Sort categories"
        >
            <option value="newest" <?= $sort === "newest" ? "selected" : "" ?>>
                Newest first
            </option>
            <option value="oldest" <?= $sort === "oldest" ? "selected" : "" ?>>
                Oldest first
            </option>
            <option value="name_asc" <?= $sort === "name_asc" ? "selected" : "" ?>>
                Name A–Z
            </option>
            <option value="name_desc" <?= $sort === "name_desc" ? "selected" : "" ?>>
                Name Z–A
            </option>
        </select>

        <div class="view-switch">
            <a
                class="<?= $view === "grid" ? "active" : "" ?>"
                href="?<?= http_build_query([
                    "search" => $search,
                    "sort" => $sort,
                    "view" => "grid"
                ]) ?>"
                title="Grid view"
            >
                <i class="bi bi-grid-3x3-gap"></i>
            </a>

            <a
                class="<?= $view === "list" ? "active" : "" ?>"
                href="?<?= http_build_query([
                    "search" => $search,
                    "sort" => $sort,
                    "view" => "list"
                ]) ?>"
                title="List view"
            >
                <i class="bi bi-list"></i>
            </a>
        </div>

    </form>

    <!-- RESULT COUNT -->
    <div class="result-line" id="categoriesSection">
        <div>
            Showing <strong><?= count($categories) ?></strong>
            <?= $search !== "" ? "matching categories" : "categories" ?>
        </div>

        <div>
            Total: <strong><?= $totalCategories ?></strong>
        </div>
    </div>

    <?php if (count($categories) === 0): ?>

        <!-- EMPTY STATE -->
        <section class="empty">
            <div class="empty-icon">
                <i class="bi bi-folder2-open"></i>
            </div>

            <?php if ($search !== ""): ?>
                <h3>No categories found</h3>
                <p>
                    Try a different search term or create a new standardized category.
                </p>

                <a
                    href="category_management.php"
                    class="btn btn-light border"
                >
                    Clear Search
                </a>
            <?php else: ?>
                <h3>No categories yet</h3>
                <p>
                    Create your first standardized category for the Rental Network.
                </p>

                <button
                    class="btn btn-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#addCategoryModal"
                >
                    <i class="bi bi-plus-lg me-1"></i>
                    Create Category
                </button>
            <?php endif; ?>
        </section>

    <?php elseif ($view === "list"): ?>

        <!-- LIST VIEW -->
        <section class="list-wrap">
            <table class="list-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($categories as $category): ?>
                    <tr
                        class="category-result-row"
                        data-category-id="<?= (int)$category["id"] ?>"
                        <?= ($newestCategory && (int)$category["id"] === (int)$newestCategory["id"]) ? 'id="newestCategoryList"' : '' ?>
                    >

                        <td>
                            <div class="list-category">

                                <?php if (!empty($category["image_path"])): ?>
                                    <img
                                        class="list-thumb"
                                        src="<?= e($category["image_path"]) ?>"
                                        alt="<?= e($category["category_name"]) ?>"
                                    >
                                <?php else: ?>
                                    <div class="list-thumb-placeholder">
                                        <i class="bi bi-image"></i>
                                    </div>
                                <?php endif; ?>

                                <div class="list-name">
                                    <?= e($category["category_name"]) ?>
                                </div>

                            </div>
                        </td>

                        <td>
                            <div class="list-description">
                                <?= e(
                                    $category["description"]
                                    ?: "No description provided."
                                ) ?>
                            </div>
                        </td>

                        <td>
                            <?= date("d M Y", strtotime($category["created_at"])) ?>
                        </td>

                        <td>
                            <div class="dropdown">
                                <button
                                    type="button"
                                    class="btn btn-light border"
                                    data-bs-toggle="dropdown"
                                    aria-label="Category actions"
                                >
                                    <i class="bi bi-three-dots"></i>
                                </button>

                                <ul class="dropdown-menu dropdown-menu-end">

                                    <li>
                                        <button
                                            class="dropdown-item edit-category"
                                            type="button"
                                            data-id="<?= (int)$category["id"] ?>"
                                            data-name="<?= e($category["category_name"]) ?>"
                                            data-description="<?= e($category["description"] ?? "") ?>"
                                            data-image="<?= e($category["image_path"] ?? "") ?>"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editCategoryModal"
                                        >
                                            <i class="bi bi-pencil me-2"></i>
                                            Edit category
                                        </button>
                                    </li>

                                    <li><hr class="dropdown-divider"></li>

                                    <li>
                                        <button
                                            class="dropdown-item text-danger delete-category"
                                            type="button"
                                            data-id="<?= (int)$category["id"] ?>"
                                            data-name="<?= e($category["category_name"]) ?>"
                                        >
                                            <i class="bi bi-trash3 me-2"></i>
                                            Delete category
                                        </button>
                                    </li>

                                </ul>
                            </div>
                        </td>

                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>

    <?php else: ?>

        <!-- GRID VIEW -->
        <section class="category-grid">

            <?php foreach ($categories as $category): ?>

                <article
                    class="category-card category-result-card"
                    data-category-id="<?= (int)$category["id"] ?>"
                    <?= ($newestCategory && (int)$category["id"] === (int)$newestCategory["id"]) ? 'id="newestCategory"' : '' ?>
                >

                    <div class="category-image">

                        <?php if (!empty($category["image_path"])): ?>
                            <img
                                src="<?= e($category["image_path"]) ?>"
                                alt="<?= e($category["category_name"]) ?>"
                            >
                        <?php else: ?>
                            <div class="image-placeholder">
                                <i class="bi bi-folder2"></i>
                            </div>
                        <?php endif; ?>

                        <div class="card-menu dropdown">

                            <button
                                type="button"
                                data-bs-toggle="dropdown"
                                aria-label="Category actions"
                            >
                                <i class="bi bi-three-dots"></i>
                            </button>

                            <ul class="dropdown-menu dropdown-menu-end">

                                <li>
                                    <button
                                        class="dropdown-item edit-category"
                                        type="button"
                                        data-id="<?= (int)$category["id"] ?>"
                                        data-name="<?= e($category["category_name"]) ?>"
                                        data-description="<?= e($category["description"] ?? "") ?>"
                                        data-image="<?= e($category["image_path"] ?? "") ?>"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editCategoryModal"
                                    >
                                        <i class="bi bi-pencil me-2"></i>
                                        Edit category
                                    </button>
                                </li>

                                <li><hr class="dropdown-divider"></li>

                                <li>
                                    <button
                                        class="dropdown-item text-danger delete-category"
                                        type="button"
                                        data-id="<?= (int)$category["id"] ?>"
                                        data-name="<?= e($category["category_name"]) ?>"
                                    >
                                        <i class="bi bi-trash3 me-2"></i>
                                        Delete category
                                    </button>
                                </li>

                            </ul>

                        </div>
                    </div>

                    <div class="card-content">

                        <div class="category-name">
                            <?= e($category["category_name"]) ?>
                        </div>

                        <div class="category-description">
                            <?= e(
                                $category["description"]
                                ?: "No description provided."
                            ) ?>
                        </div>

                        <div class="card-meta">
                            <div class="created-date">
                                <i class="bi bi-calendar3"></i>
                                Created
                                <?= date("d M Y", strtotime($category["created_at"])) ?>
                            </div>
                        </div>

                    </div>

                </article>

            <?php endforeach; ?>

        </section>

    <?php endif; ?>

</main>
</div>

<!-- ADD CATEGORY MODAL -->
<div
    class="modal fade"
    id="addCategoryModal"
    tabindex="-1"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <form method="POST" enctype="multipart/form-data">

                <input type="hidden" name="action" value="add">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <div class="modal-header">

                    <div>
                        <h5 class="modal-title fw-bold">
                            Add Category
                        </h5>

                        <div class="text-muted small mt-1">
                            Create a standardized category for the platform.
                        </div>
                    </div>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>

                </div>

                <div class="modal-body">

                    <div class="mb-3">
                        <label class="form-label">
                            Category Name
                            <span class="text-danger">*</span>
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            name="category_name"
                            maxlength="100"
                            placeholder="e.g. Electronics"
                            required
                        >

                        <div class="form-text">
                            Category names must be unique.
                        </div>
                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Description
                        </label>

                        <textarea
                            class="form-control"
                            name="description"
                            rows="3"
                            maxlength="255"
                            placeholder="Briefly describe what belongs in this category."
                        ></textarea>

                    </div>

                    <div>

                        <label class="form-label">
                            Category Image
                        </label>

                        <label
                            class="upload-box w-100"
                            for="addImage"
                        >
                            <i class="bi bi-cloud-arrow-up"></i>

                            <strong class="d-block mt-1">
                                Upload category image
                            </strong>

                            <small>
                                JPG, PNG or WEBP · Maximum 3 MB
                            </small>
                        </label>

                        <input
                            class="d-none"
                            type="file"
                            id="addImage"
                            name="category_image"
                            accept="image/jpeg,image/png,image/webp"
                        >

                        <img
                            id="addPreview"
                            class="preview"
                            alt="Category image preview"
                        >

                    </div>

                </div>

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-light border"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        <i class="bi bi-check2 me-1"></i>
                        Create Category
                    </button>

                </div>

            </form>

        </div>
    </div>
</div>

<!-- EDIT CATEGORY MODAL -->
<div
    class="modal fade"
    id="editCategoryModal"
    tabindex="-1"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <form method="POST" enctype="multipart/form-data">

                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="category_id" id="editId">

                <div class="modal-header">

                    <div>
                        <h5 class="modal-title fw-bold">
                            Edit Category
                        </h5>

                        <div class="text-muted small mt-1">
                            Update the standardized category information.
                        </div>
                    </div>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>

                </div>

                <div class="modal-body">

                    <div class="mb-3">

                        <label class="form-label">
                            Category Name
                            <span class="text-danger">*</span>
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            name="category_name"
                            id="editName"
                            maxlength="100"
                            required
                        >

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Description
                        </label>

                        <textarea
                            class="form-control"
                            name="description"
                            id="editDescription"
                            rows="3"
                            maxlength="255"
                        ></textarea>

                    </div>

                    <div>

                        <label class="form-label">
                            Category Image
                        </label>

                        <label
                            class="upload-box w-100"
                            for="editImage"
                        >
                            <i class="bi bi-image"></i>

                            <strong class="d-block mt-1">
                                Replace category image
                            </strong>

                            <small>
                                JPG, PNG or WEBP · Maximum 3 MB
                            </small>
                        </label>

                        <input
                            class="d-none"
                            type="file"
                            id="editImage"
                            name="category_image"
                            accept="image/jpeg,image/png,image/webp"
                        >

                        <img
                            id="editPreview"
                            class="preview"
                            alt="Category image preview"
                        >

                    </div>

                </div>

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-light border"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        <i class="bi bi-save2 me-1"></i>
                        Save Changes
                    </button>

                </div>

            </form>

        </div>
    </div>
</div>

<!-- DELETE FORM -->
<form
    method="POST"
    id="deleteForm"
    class="d-none"
>
    <input
        type="hidden"
        name="action"
        value="delete"
    >

    <input
        type="hidden"
        name="csrf_token"
        value="<?= e($csrfToken) ?>"
    >

    <input
        type="hidden"
        name="category_id"
        id="deleteId"
    >
</form>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

<script>
/* ---------- 3-second toast ---------- */

function hideCategoryToast() {
    const toast = document.getElementById("categoryToast");

    if (!toast) {
        return;
    }

    toast.style.opacity = "0";
    toast.style.transform = "translateY(-8px) translateX(8px)";

    setTimeout(() => {
        toast.remove();
    }, 350);
}

document.addEventListener("DOMContentLoaded", () => {
    const toast = document.getElementById("categoryToast");

    if (toast) {
        setTimeout(hideCategoryToast, 3000);
    }
});

/* ---------- Live category search ---------- */
const categorySearchInput = document.getElementById("categorySearchInput");
const categorySearchForm = document.getElementById("categorySearchForm");

if (categorySearchInput) {
    categorySearchInput.addEventListener("input", function () {
        const query = this.value.trim().toLowerCase();
        const cards = document.querySelectorAll(".category-result-card");
        const rows = document.querySelectorAll(".category-result-row");

        const results = [...cards, ...rows];

        results.forEach(item => {
            const text = item.textContent.toLowerCase();
            item.style.display = !query || text.includes(query) ? "" : "none";
        });

        // Search is intentionally handled while typing.
        // Prevent the GET form from requiring Enter for normal filtering.
    });

    categorySearchInput.addEventListener("keydown", function (event) {
        if (event.key === "Enter") {
            event.preventDefault();
        }
    });
}

/* ---------- Information banner ---------- */

const categoryInfoBanner =
    document.getElementById("categoryInfoBanner");

const closeCategoryInfo =
    document.getElementById("closeCategoryInfo");

if (categoryInfoBanner && closeCategoryInfo) {
    const infoDismissed =
        localStorage.getItem("rentalNetworkCategoryInfoDismissed");

    if (infoDismissed === "1") {
        categoryInfoBanner.classList.add("info-hidden");
    }

    closeCategoryInfo.addEventListener("click", () => {
        categoryInfoBanner.classList.add("info-hidden");
        localStorage.setItem(
            "rentalNetworkCategoryInfoDismissed",
            "1"
        );
    });
}

/* ---------- Dashboard card interactions ---------- */

function scrollToCategories() {
    const target = document.getElementById("categoriesSection");

    if (!target) {
        return;
    }

    target.scrollIntoView({
        behavior: "smooth",
        block: "start"
    });
}

function highlightNewestCategory() {
    const newestId = <?= $newestCategory ? (int)$newestCategory["id"] : 0 ?>;

    let newest = null;

    if (newestId > 0) {
        newest = document.querySelector(
            '[data-category-id="' + newestId + '"]'
        );
    }

    if (!newest) {
        newest =
            document.getElementById("newestCategory") ||
            document.getElementById("newestCategoryList");
    }

    if (!newest) {
        scrollToCategories();
        return;
    }

    // Scroll first, then start the visual emphasis after the browser
    // has positioned the category. This also works in List View.
    newest.scrollIntoView({
        behavior: "smooth",
        block: "center"
    });

    newest.classList.remove("newest-highlight");
    void newest.offsetWidth;

    // Strong blue pulse for roughly 4 seconds.
    newest.classList.add("newest-highlight");

    setTimeout(() => {
        newest.classList.remove("newest-highlight");
    }, 4200);
}

const totalCategoriesCard =
    document.getElementById("totalCategoriesCard");

if (totalCategoriesCard) {
    totalCategoriesCard.addEventListener("click", scrollToCategories);

    totalCategoriesCard.addEventListener("keydown", (event) => {
        if (event.key === "Enter" || event.key === " ") {
            event.preventDefault();
            scrollToCategories();
        }
    });
}

const newestCategoryCard =
    document.getElementById("newestCategoryCard");

if (newestCategoryCard) {
    newestCategoryCard.addEventListener("click", highlightNewestCategory);

    newestCategoryCard.addEventListener("keydown", (event) => {
        if (event.key === "Enter" || event.key === " ") {
            event.preventDefault();
            highlightNewestCategory();
        }
    });
}

/* ---------- Image Preview ---------- */

function setupPreview(inputId, previewId) {

    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);

    input.addEventListener("change", function () {

        const file = this.files && this.files[0];

        if (!file) {
            preview.style.display = "none";
            preview.removeAttribute("src");
            return;
        }

        const reader = new FileReader();

        reader.onload = function (event) {
            preview.src = event.target.result;
            preview.style.display = "block";
        };

        reader.readAsDataURL(file);
    });
}

setupPreview("addImage", "addPreview");
setupPreview("editImage", "editPreview");

/* ---------- Edit Category ---------- */

document.querySelectorAll(".edit-category").forEach(button => {

    button.addEventListener("click", function () {

        document.getElementById("editId").value =
            this.dataset.id;

        document.getElementById("editName").value =
            this.dataset.name;

        document.getElementById("editDescription").value =
            this.dataset.description || "";

        const preview =
            document.getElementById("editPreview");

        if (this.dataset.image) {

            preview.src = this.dataset.image;
            preview.style.display = "block";

        } else {

            preview.style.display = "none";
            preview.removeAttribute("src");

        }

        document.getElementById("editImage").value = "";

    });

});

/* ---------- Delete Category ---------- */

document.querySelectorAll(".delete-category").forEach(button => {

    button.addEventListener("click", function () {

        const id = this.dataset.id;
        const name = this.dataset.name;

        const confirmed = confirm(
            "Delete \"" + name + "\"?\n\n" +
            "This will permanently remove the category " +
            "from the category master list."
        );

        if (!confirmed) {
            return;
        }

        document.getElementById("deleteId").value = id;
        document.getElementById("deleteForm").submit();

    });

});
</script>

</body>
</html>
