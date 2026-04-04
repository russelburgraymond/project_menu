<?php
require_once __DIR__ . "/bootstrap.php";

$id = (int)($_GET["id"] ?? 0);
if ($id > 0) {

  // Optional protection: don't delete Finished items
  $stmt = $conn->prepare("SELECT category FROM projects WHERE id = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();

    $stmt = $conn->prepare("DELETE FROM projects WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
}

header("Location: index.php");
exit;
