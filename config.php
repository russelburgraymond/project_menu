<?php
// config.php

// Guard against being loaded multiple times
if (!defined("APP_TITLE")) {
    define("APP_TITLE", "Project Menu");
}

if (!defined("APP_VERSION")) {
    $version_file = __DIR__ . DIRECTORY_SEPARATOR . "VERSION";
    $version_value = is_file($version_file) ? trim((string) file_get_contents($version_file)) : "3.1.0";
    define("APP_VERSION", $version_value !== "" ? $version_value : "3.1.0");
}

// Optional: ignore list for scanning (folder names)
// You can add more folder/file names here.
if (!defined("SCAN_IGNORE")) {
    define("SCAN_IGNORE", json_encode([
        ".", "..",
        ".git", ".idea",
        "node_modules", "vendor",
        "tmp", "logs"
    ]));
}
