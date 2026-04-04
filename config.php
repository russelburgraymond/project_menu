<?php
// config.php

// Guard against being loaded multiple times
if (!defined("APP_TITLE")) {
    define("APP_TITLE", "Project Menu");
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
