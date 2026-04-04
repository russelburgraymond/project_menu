<?php
// bootstrap.php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/schema.php";

ensure_schema($conn);
