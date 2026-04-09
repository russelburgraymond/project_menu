<?php
require_once __DIR__ . "/bootstrap.php";

$id = (int)($_GET["id"] ?? 0);

function redirect_with_message(string $message, string $type = "success"): void {
    header("Location: index.php?message=" . urlencode($message) . "&message_type=" . urlencode($type));
    exit;
}

function next_sort_order(mysqli $conn, string $category): int {
    $stmt = $conn->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort FROM projects WHERE category = ?");
    $stmt->bind_param("s", $category);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row["next_sort"] ?? 1);
}

function read_project_info_file(string $directoryName): ?array {
    $file = __DIR__ . DIRECTORY_SEPARATOR . "projects" . DIRECTORY_SEPARATOR . $directoryName . DIRECTORY_SEPARATOR . "project_info.json";

    if (!is_file($file)) {
        return null;
    }

    $json = file_get_contents($file);
    if ($json === false) {
        return null;
    }

    $data = json_decode($json, true);
    if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }

    $project_name = trim((string)($data["project_name"] ?? ""));
    $version      = trim((string)($data["version"] ?? ""));
    $description  = trim((string)($data["description"] ?? ""));
    $category     = trim((string)($data["category"] ?? pm_first_active_category($conn)));

    if (!pm_is_valid_category($conn, $category, true)) {
        $category = pm_first_active_category($conn);
    }

    if ($project_name === "") {
        $project_name = $directoryName;
    }

    return [
        "project_name" => $project_name,
        "version"      => $version,
        "description"  => $description,
        "category"     => $category,
    ];
}

if ($id <= 0) {
    redirect_with_message("Invalid project selected.", "error");
}

$stmt = $conn->prepare("SELECT id, project_name, directory, category, sort_order FROM projects WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

if (!$project) {
    redirect_with_message("Project not found.", "error");
}

$info = read_project_info_file((string)$project["directory"]);
if ($info === null) {
    redirect_with_message("Could not read a valid project_info.json for this project.", "error");
}

$sortOrder = (int)($project["sort_order"] ?? 0);
if ($sortOrder <= 0 || $info["category"] !== ($project["category"] ?? "")) {
    $sortOrder = next_sort_order($conn, $info["category"]);
}

$update = $conn->prepare(
    "UPDATE projects
     SET project_name = ?, version = ?, description = ?, category = ?, sort_order = ?
     WHERE id = ?"
);
$update->bind_param(
    "ssssii",
    $info["project_name"],
    $info["version"],
    $info["description"],
    $info["category"],
    $sortOrder,
    $id
);

try {
    $update->execute();
    redirect_with_message("Project updated from project_info.json.");
} catch (mysqli_sql_exception $e) {
    redirect_with_message("Database error while updating project.", "error");
}
