<?php
// schema.php

function pm_table_exists(mysqli $conn, string $tableName): bool {
    $safe = $conn->real_escape_string($tableName);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

function pm_column_info(mysqli $conn, string $tableName, string $columnName): ?array {
    $safeTable = $conn->real_escape_string($tableName);
    $safeCol = $conn->real_escape_string($columnName);
    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeCol}'");
    if ($res instanceof mysqli_result) {
        $row = $res->fetch_assoc();
        return is_array($row) ? $row : null;
    }
    return null;
}

function pm_seed_default_categories(mysqli $conn): void {
    $defaults = [
        ['In-Progress', 1],
        ['Development', 2],
        ['Finished', 3],
    ];

    $stmt = $conn->prepare(
        "INSERT INTO project_categories (category_name, sort_order, is_active, is_default)
         VALUES (?, ?, 1, 1)
         ON DUPLICATE KEY UPDATE
            sort_order = VALUES(sort_order),
            is_default = 1,
            is_active = 1"
    );

    foreach ($defaults as [$name, $sort]) {
        $stmt->bind_param('si', $name, $sort);
        $stmt->execute();
    }
}

function pm_sync_project_categories_from_projects(mysqli $conn): void {
    if (!pm_table_exists($conn, 'project_categories') || !pm_table_exists($conn, 'projects')) {
        return;
    }

    $res = $conn->query("SELECT DISTINCT TRIM(category) AS category_name FROM projects WHERE category IS NOT NULL AND TRIM(category) <> ''");
    if (!($res instanceof mysqli_result)) {
        return;
    }

    $maxRes = $conn->query("SELECT COALESCE(MAX(sort_order), 0) AS max_sort FROM project_categories");
    $maxRow = $maxRes instanceof mysqli_result ? $maxRes->fetch_assoc() : null;
    $nextSort = (int)($maxRow['max_sort'] ?? 0) + 1;

    $insert = $conn->prepare(
        "INSERT INTO project_categories (category_name, sort_order, is_active, is_default)
         VALUES (?, ?, 1, 0)
         ON DUPLICATE KEY UPDATE category_name = category_name"
    );

    while ($row = $res->fetch_assoc()) {
        $name = trim((string)($row['category_name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $insert->bind_param('si', $name, $nextSort);
        $insert->execute();
        $nextSort++;
    }
}

function pm_get_categories(mysqli $conn, bool $includeInactive = false, bool $includeUsed = false): array {
    $categories = [];

    if (pm_table_exists($conn, 'project_categories')) {
        $sql = "SELECT category_name FROM project_categories";
        if (!$includeInactive) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY sort_order, category_name";
        $res = $conn->query($sql);
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $name = trim((string)($row['category_name'] ?? ''));
                if ($name !== '') {
                    $categories[$name] = $name;
                }
            }
        }
    }

    if ($includeUsed && pm_table_exists($conn, 'projects')) {
        $res = $conn->query("SELECT DISTINCT TRIM(category) AS category_name FROM projects WHERE category IS NOT NULL AND TRIM(category) <> '' ORDER BY category_name");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $name = trim((string)($row['category_name'] ?? ''));
                if ($name !== '') {
                    $categories[$name] = $name;
                }
            }
        }
    }

    if ($categories === []) {
        $categories = [
            'In-Progress' => 'In-Progress',
            'Development' => 'Development',
            'Finished' => 'Finished',
        ];
    }

    return array_values($categories);
}

function pm_is_valid_category(mysqli $conn, string $category, bool $includeInactive = false): bool {
    $category = trim($category);
    if ($category === '') {
        return false;
    }

    if (pm_table_exists($conn, 'project_categories')) {
        $sql = "SELECT id FROM project_categories WHERE category_name = ?";
        if (!$includeInactive) {
            $sql .= " AND is_active = 1";
        }
        $sql .= " LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $category);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res instanceof mysqli_result && $res->num_rows > 0;
    }

    return in_array($category, ['In-Progress', 'Development', 'Finished'], true);
}

function pm_first_active_category(mysqli $conn, string $fallback = 'Development'): string {
    $categories = pm_get_categories($conn);
    return $categories[0] ?? $fallback;
}

function pm_parse_changelog_markdown(string $contents): array {
    $lines = preg_split('/\R/', $contents) ?: [];
    $entries = [];
    $currentVersion = null;
    $currentDate = null;
    $currentSection = null;
    $sortOrder = 0;

    foreach ($lines as $rawLine) {
        $line = trim($rawLine);
        if ($line === '' || str_starts_with($line, '# Changelog') || str_starts_with($line, 'All notable changes') || str_starts_with($line, 'This project follows:') || $line === '---') {
            continue;
        }

        if (preg_match('/^##\s+\[(.+?)\](?:\s*-\s*(\d{4}-\d{2}-\d{2}))?$/', $line, $m)) {
            $currentVersion = trim($m[1]);
            $currentDate = isset($m[2]) ? trim($m[2]) : '';
            $currentSection = null;
            $sortOrder = 0;
            continue;
        }

        if (preg_match('/^##\s+([0-9A-Za-z.\-_]+)\s*-\s*(\d{4}-\d{2}-\d{2})$/', $line, $m)) {
            $currentVersion = trim($m[1]);
            $currentDate = trim($m[2]);
            $currentSection = null;
            $sortOrder = 0;
            continue;
        }

        if (preg_match('/^###\s+(.+)$/', $line, $m)) {
            $currentSection = trim($m[1]);
            continue;
        }

        if ($currentVersion !== null && $currentSection !== null && preg_match('/^-\s+(.+)$/', $line, $m)) {
            $sortOrder++;
            $entries[] = [
                'version' => $currentVersion,
                'release_date' => $currentDate,
                'section_title' => $currentSection,
                'entry_text' => trim($m[1]),
                'sort_order' => $sortOrder,
            ];
        }
    }

    return $entries;
}

function pm_sync_changelog_from_file(mysqli $conn, string $changelogFile): void {
    if (!is_file($changelogFile)) {
        return;
    }

    $contents = file_get_contents($changelogFile);
    if ($contents === false) {
        return;
    }

    $parsed = pm_parse_changelog_markdown($contents);
    if ($parsed === []) {
        return;
    }

    $conn->begin_transaction();

    try {
        $conn->query('TRUNCATE TABLE changelog_entries');

        $stmt = $conn->prepare(
            "INSERT INTO changelog_entries (version, release_date, section_title, entry_text, sort_order)
             VALUES (?, ?, ?, ?, ?)"
        );

        foreach ($parsed as $entry) {
            $releaseDate = $entry['release_date'] !== '' ? $entry['release_date'] : null;
            $stmt->bind_param(
                'ssssi',
                $entry['version'],
                $releaseDate,
                $entry['section_title'],
                $entry['entry_text'],
                $entry['sort_order']
            );
            $stmt->execute();
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
    }
}

function ensure_schema(mysqli $conn): void {
    $conn->query(
        "CREATE TABLE IF NOT EXISTS projects (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          project_name VARCHAR(120) NOT NULL,
          version VARCHAR(50) NULL,
          description TEXT NULL,
          directory VARCHAR(255) NOT NULL,
          category VARCHAR(100) NOT NULL DEFAULT 'Development',
          sort_order INT UNSIGNED NOT NULL DEFAULT 0,
          is_active TINYINT(1) NOT NULL DEFAULT 1,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uniq_directory (directory),
          KEY idx_category (category),
          KEY idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS project_categories (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          category_name VARCHAR(100) NOT NULL,
          sort_order INT UNSIGNED NOT NULL DEFAULT 0,
          is_active TINYINT(1) NOT NULL DEFAULT 1,
          is_default TINYINT(1) NOT NULL DEFAULT 0,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uniq_category_name (category_name),
          KEY idx_category_sort (sort_order),
          KEY idx_category_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS changelog_entries (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          version VARCHAR(50) NOT NULL,
          release_date DATE NULL,
          section_title VARCHAR(100) NOT NULL,
          entry_text TEXT NOT NULL,
          sort_order INT UNSIGNED NOT NULL DEFAULT 0,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uniq_changelog_entry (version, section_title, entry_text(255)),
          KEY idx_version_date (version, release_date),
          KEY idx_release_date (release_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $required = [
        'project_name' => "ALTER TABLE projects ADD COLUMN project_name VARCHAR(120) NOT NULL",
        'version'      => "ALTER TABLE projects ADD COLUMN version VARCHAR(50) NULL",
        'description'  => "ALTER TABLE projects ADD COLUMN description TEXT NULL",
        'directory'    => "ALTER TABLE projects ADD COLUMN directory VARCHAR(255) NOT NULL",
        'category'     => "ALTER TABLE projects ADD COLUMN category VARCHAR(100) NOT NULL DEFAULT 'Development'",
        'sort_order'   => "ALTER TABLE projects ADD COLUMN sort_order INT UNSIGNED NOT NULL DEFAULT 0",
        'is_active'    => "ALTER TABLE projects ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1",
        'created_at'   => "ALTER TABLE projects ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
        'updated_at'   => "ALTER TABLE projects ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        'is_branch'    => "ALTER TABLE projects ADD COLUMN is_branch TINYINT(1) NOT NULL DEFAULT 0",
        'parent_project_id' => "ALTER TABLE projects ADD COLUMN parent_project_id INT UNSIGNED NULL DEFAULT NULL",
    ];

    $existing = [];
    $res = $conn->query("SHOW COLUMNS FROM projects");
    while ($row = $res->fetch_assoc()) {
        $existing[$row['Field']] = $row;
    }

    foreach ($required as $col => $alter) {
        if (!isset($existing[$col])) {
            $conn->query($alter);
        }
    }

    $categoryInfo = pm_column_info($conn, 'projects', 'category');
    if ($categoryInfo && stripos((string)($categoryInfo['Type'] ?? ''), 'enum(') === 0) {
        $conn->query("ALTER TABLE projects MODIFY category VARCHAR(100) NOT NULL DEFAULT 'Development'");
    }

    $indexes = [];
    $res = $conn->query("SHOW INDEX FROM projects");
    while ($row = $res->fetch_assoc()) {
        $indexes[$row['Key_name']] = true;
    }

    if (!isset($indexes['uniq_directory'])) {
        $conn->query("ALTER TABLE projects ADD UNIQUE KEY uniq_directory (directory)");
    }
    if (!isset($indexes['idx_category'])) {
        $conn->query("ALTER TABLE projects ADD KEY idx_category (category)");
    }
    if (!isset($indexes['idx_active'])) {
        $conn->query("ALTER TABLE projects ADD KEY idx_active (is_active)");
    }
    if (!isset($indexes['idx_parent_project'])) {
        $conn->query("ALTER TABLE projects ADD KEY idx_parent_project (parent_project_id)");
    }

    pm_seed_default_categories($conn);
    pm_sync_project_categories_from_projects($conn);

    $res = $conn->query("SELECT category_name FROM project_categories ORDER BY sort_order, category_name");
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $cat = (string)($row['category_name'] ?? '');
            if ($cat === '') {
                continue;
            }
            $stmt = $conn->prepare(
                "SELECT COUNT(*) AS missing_count FROM projects WHERE category = ? AND (sort_order IS NULL OR sort_order = 0)"
            );
            $stmt->bind_param('s', $cat);
            $stmt->execute();
            $missingRow = $stmt->get_result()->fetch_assoc();
            if ((int)($missingRow['missing_count'] ?? 0) > 0) {
                $escaped = $conn->real_escape_string($cat);
                $conn->query("SET @pm_sort := 0");
                $conn->query("UPDATE projects SET sort_order = (@pm_sort := @pm_sort + 1) WHERE category = '{$escaped}' AND (sort_order IS NULL OR sort_order = 0) ORDER BY project_name, id");
            }
        }
    }

    if (pm_table_exists($conn, 'changelog_entries')) {
        pm_sync_changelog_from_file($conn, __DIR__ . DIRECTORY_SEPARATOR . 'CHANGELOG.md');
    }
}
