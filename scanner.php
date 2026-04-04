<?php
// scanner.php
// Scans the /projects directory for project folders

function scan_laragon_www_dirs(): array {

    // Target folder
    $base = __DIR__ . DIRECTORY_SEPARATOR . "projects";

    $ignore = json_decode(SCAN_IGNORE, true) ?: [];

    if (!is_dir($base)) return [];

    $dirs = [];
    $items = scandir($base);
    if ($items === false) return [];

    foreach ($items as $name) {

        if (in_array($name, $ignore, true)) continue;

        $path = $base . DIRECTORY_SEPARATOR . $name;

        if (is_dir($path)) {
            $dirs[] = $name;
        }
    }

    sort($dirs, SORT_NATURAL | SORT_FLAG_CASE);
    return $dirs;
}
?>