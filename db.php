<?php
// db.php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$db_host = "127.0.0.1";
$db_user = "root";
$db_pass = "3473";
$db_name = "000_project_index"; // change if needed

// 1) Connect without selecting a database first
$conn = new mysqli($db_host, $db_user, $db_pass);
$conn->set_charset("utf8mb4");

// 2) Create the database if it does not exist
$conn->query("
    CREATE DATABASE IF NOT EXISTS `$db_name`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci
");

// 3) Select it
$conn->select_db($db_name);

?>