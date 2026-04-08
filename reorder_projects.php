<?php
require_once __DIR__ . "/bootstrap.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode((string)$raw, true);

$category = trim((string)($data['category'] ?? ''));
$order = $data['order'] ?? null;
$allowedCategories = ['In-Progress', 'Development', 'Finished'];

if (!in_array($category, $allowedCategories, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid category.']);
    exit;
}

if (!is_array($order) || count($order) === 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'No project order was provided.']);
    exit;
}

$ids = [];
foreach ($order as $id) {
    $id = (int)$id;
    if ($id > 0) {
        $ids[] = $id;
    }
}

if (count($ids) === 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid project IDs.']);
    exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids));
$stmt = $conn->prepare("SELECT id FROM projects WHERE category = ? AND is_active = 1 AND id IN ($placeholders)");
$params = array_merge([$category], $ids);
$stmt->bind_param('s' . $types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$valid = [];
while ($row = $result->fetch_assoc()) {
    $valid[(int)$row['id']] = true;
}

foreach ($ids as $id) {
    if (!isset($valid[$id])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'One or more projects are invalid for this category.']);
        exit;
    }
}

$conn->begin_transaction();
try {
    $update = $conn->prepare("UPDATE projects SET sort_order = ? WHERE id = ? AND category = ?");
    $position = 1;
    foreach ($ids as $id) {
        $update->bind_param('iis', $position, $id, $category);
        $update->execute();
        $position++;
    }
    $conn->commit();
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Unable to save project order.']);
}
