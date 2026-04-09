<?php
// bootstrap.php

if (!defined("APP_TITLE")) {
    define("APP_TITLE", "Project Menu");
}

if (!defined("APP_VERSION")) {
    $version_file = __DIR__ . DIRECTORY_SEPARATOR . "VERSION";
    $version_value = is_file($version_file) ? trim((string) file_get_contents($version_file)) : "3.9.2";
    define("APP_VERSION", $version_value !== "" ? $version_value : "3.9.2");
}

if (!defined("SCAN_IGNORE")) {
    define("SCAN_IGNORE", json_encode([
        ".", "..",
        ".git", ".idea",
        "node_modules", "vendor",
        "tmp", "logs"
    ]));
}

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/schema.php";

ensure_schema($conn);
