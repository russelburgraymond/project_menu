<?php
require_once __DIR__ . "/bootstrap.php";
require_once __DIR__ . "/header.php";

$changelogFile = __DIR__ . DIRECTORY_SEPARATOR . 'CHANGELOG.md';
$contents = is_file($changelogFile) ? file_get_contents($changelogFile) : "# Changelog\n\nNo changelog entries yet.";
?>
<div class="card">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
    <div>
      <h2 style="margin:0 0 6px 0;">Changelog</h2>
      <div class="muted">Version history for <?php echo htmlspecialchars(APP_TITLE); ?>.</div>
    </div>
    <a class="btn" href="CHANGELOG.md" target="_blank">Open Raw Changelog</a>
  </div>
</div>

<div class="card">
  <pre style="margin:0;white-space:pre-wrap;word-wrap:break-word;font-family:ui-monospace,Consolas,monospace;font-size:13px;line-height:1.5;color:#e7eef7;"><?php echo htmlspecialchars((string)$contents); ?></pre>
</div>

<?php require_once __DIR__ . "/footer.php"; ?>
