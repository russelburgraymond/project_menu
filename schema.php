<?php
// schema.php

function ensure_schema(mysqli $conn): void {

    $conn->query("
        CREATE TABLE IF NOT EXISTS projects (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          project_name VARCHAR(120) NOT NULL,
          version VARCHAR(50) NULL,
          description TEXT NULL,
          directory VARCHAR(255) NOT NULL,
          category ENUM('In-Progress','Development','Finished') NOT NULL DEFAULT 'Development',
          sort_order INT UNSIGNED NOT NULL DEFAULT 0,
          is_active TINYINT(1) NOT NULL DEFAULT 1,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uniq_directory (directory),
          KEY idx_category (category),
          KEY idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $required = [
        "project_name" => "ALTER TABLE projects ADD COLUMN project_name VARCHAR(120) NOT NULL",
        "version"      => "ALTER TABLE projects ADD COLUMN version VARCHAR(50) NULL",
        "description"  => "ALTER TABLE projects ADD COLUMN description TEXT NULL",
        "directory"    => "ALTER TABLE projects ADD COLUMN directory VARCHAR(255) NOT NULL",
        "category"     => "ALTER TABLE projects ADD COLUMN category ENUM('In-Progress','Development','Finished') NOT NULL DEFAULT 'Development'",
        "sort_order"   => "ALTER TABLE projects ADD COLUMN sort_order INT UNSIGNED NOT NULL DEFAULT 0",
        "is_active"    => "ALTER TABLE projects ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1",
        "created_at"   => "ALTER TABLE projects ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
        "updated_at"   => "ALTER TABLE projects ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    ];

    $existing = [];
    $res = $conn->query("SHOW COLUMNS FROM projects");
    while ($row = $res->fetch_assoc()) {
        $existing[$row["Field"]] = true;
    }

    foreach ($required as $col => $alter) {
        if (!isset($existing[$col])) {
            $conn->query($alter);
        }
    }

    $indexes = [];
    $res = $conn->query("SHOW INDEX FROM projects");
    while ($row = $res->fetch_assoc()) {
        $indexes[$row["Key_name"]] = true;
    }

    if (!isset($indexes["uniq_directory"])) {
        $conn->query("ALTER TABLE projects ADD UNIQUE KEY uniq_directory (directory)");
    }
    if (!isset($indexes["idx_category"])) {
        $conn->query("ALTER TABLE projects ADD KEY idx_category (category)");
    }
    if (!isset($indexes["idx_active"])) {
        $conn->query("ALTER TABLE projects ADD KEY idx_active (is_active)");
    }

    $conn->query("SET @in_progress_sort := 0");
    $conn->query("UPDATE projects SET sort_order = (@in_progress_sort := @in_progress_sort + 1) WHERE category = 'In-Progress' AND (sort_order IS NULL OR sort_order = 0) ORDER BY project_name, id");

    $conn->query("SET @development_sort := 0");
    $conn->query("UPDATE projects SET sort_order = (@development_sort := @development_sort + 1) WHERE category = 'Development' AND (sort_order IS NULL OR sort_order = 0) ORDER BY project_name, id");

    $conn->query("SET @finished_sort := 0");
    $conn->query("UPDATE projects SET sort_order = (@finished_sort := @finished_sort + 1) WHERE category = 'Finished' AND (sort_order IS NULL OR sort_order = 0) ORDER BY project_name, id");
}