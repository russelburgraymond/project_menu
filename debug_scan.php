<?php
require_once __DIR__ . "/bootstrap.php";
require_once __DIR__ . "/scanner.php";

$dirs = scan_laragon_www_dirs();
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Debug Scan</title></head>
<body style="font-family:Consolas,monospace;background:#0b0f14;color:#e7eef7;padding:18px;">
<pre>
BASE (scanned) = <?php echo htmlspecialchars(__DIR__); ?>

is_dir(BASE) = <?php echo is_dir(__DIR__) ? "YES" : "NO"; ?>


SCAN_IGNORE:
<?php print_r(json_decode(SCAN_IGNORE, true)); ?>


Raw scandir(BASE):
<?php print_r(scandir(__DIR__)); ?>


Filtered directories:
<?php print_r($dirs); ?>

</pre>
<p><a style="color:#cfe3ff" href="index.php">Back</a></p>
</body>
</html>
