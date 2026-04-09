<?php
$directory = trim((string)($_GET['directory'] ?? ''));
if ($directory === '' || preg_match('/[^A-Za-z0-9._-]/', $directory)) {
    http_response_code(404);
    exit('File not found.');
}

$file = __DIR__ . DIRECTORY_SEPARATOR . 'projects' . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR . 'project_info.json';
if (!is_file($file)) {
    http_response_code(404);
    exit('File not found.');
}

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="project_info.json"');
header('Content-Length: ' . filesize($file));
readfile($file);
exit;
