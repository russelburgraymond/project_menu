<?php
require_once __DIR__ . "/bootstrap.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?message=' . urlencode('Invalid request.') . '&message_type=error');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php?message=' . urlencode('Missing project id.') . '&message_type=error');
    exit;
}

$stmt = $conn->prepare("SELECT project_name, directory FROM projects WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$project = $result->fetch_assoc();
$stmt->close();

if (!$project) {
    header('Location: index.php?message=' . urlencode('Project not found.') . '&message_type=error');
    exit;
}

$projectDir = __DIR__ . DIRECTORY_SEPARATOR . 'projects' . DIRECTORY_SEPARATOR . $project['directory'];
if (is_dir($projectDir)) {
    header('Location: index.php?message=' . urlencode('Project still exists on disk. Use normal delete instead.') . '&message_type=error');
    exit;
}

$delete = $conn->prepare("DELETE FROM projects WHERE id = ? LIMIT 1");
$delete->bind_param("i", $id);
$delete->execute();
$delete->close();

header('Location: index.php?message=' . urlencode('Missing project entry deleted: ' . $project['project_name']));
exit;
