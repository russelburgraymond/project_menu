<?php
require_once __DIR__ . "/bootstrap.php";

function redirect_with_message(string $message, string $type = "success"): void {
    header("Location: index.php?message=" . urlencode($message) . "&message_type=" . urlencode($type));
    exit;
}

function delete_project_directory(string $directoryName): bool {
    $projectsRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . "projects");
    if ($projectsRoot === false) {
        return false;
    }

    $targetPath = $projectsRoot . DIRECTORY_SEPARATOR . $directoryName;
    if (!file_exists($targetPath)) {
        return true;
    }

    $realTarget = realpath($targetPath);
    if ($realTarget === false) {
        return false;
    }

    $normalizedRoot = rtrim(str_replace('\\', '/', $projectsRoot), '/');
    $normalizedTarget = rtrim(str_replace('\\', '/', $realTarget), '/');

    if ($normalizedTarget === $normalizedRoot || strpos($normalizedTarget, $normalizedRoot . '/') !== 0) {
        return false;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($realTarget, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir() && !$item->isLink()) {
            if (!rmdir($item->getPathname())) {
                return false;
            }
        } else {
            if (!unlink($item->getPathname())) {
                return false;
            }
        }
    }

    return rmdir($realTarget);
}

if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST") {
    redirect_with_message("Invalid delete request.", "error");
}

$id = (int)($_POST["id"] ?? 0);
$confirmName = trim((string)($_POST["confirm_name"] ?? ""));

if ($id <= 0) {
    redirect_with_message("Invalid project selected for deletion.", "error");
}

$stmt = $conn->prepare("SELECT project_name, directory FROM projects WHERE id = ? LIMIT 1");
if (!$stmt) {
    redirect_with_message("Unable to prepare project delete lookup.", "error");
}
$stmt->bind_param("i", $id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$project) {
    redirect_with_message("Project not found.", "error");
}

$projectName = trim((string)($project["project_name"] ?? ""));
$directoryName = trim((string)($project["directory"] ?? ""));

if ($projectName === '' || $directoryName === '') {
    redirect_with_message("Project data is incomplete and cannot be deleted safely.", "error");
}

if ($confirmName !== $projectName) {
    redirect_with_message("Project name confirmation did not match. Delete cancelled.", "error");
}

if (!delete_project_directory($directoryName)) {
    redirect_with_message("Unable to delete the project directory. The database entry was not removed.", "error");
}

$deleteStmt = $conn->prepare("DELETE FROM projects WHERE id = ?");
if (!$deleteStmt) {
    redirect_with_message("Project directory was deleted, but the database entry could not be removed.", "error");
}
$deleteStmt->bind_param("i", $id);
$deleteStmt->execute();
$deleteStmt->close();

redirect_with_message('Project "' . $projectName . '" was deleted along with its project directory.');
