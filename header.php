<?php
// header.php
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title><?php echo htmlspecialchars(APP_TITLE); ?></title>
  <style>
    body{font-family:Arial,Helvetica,sans-serif;margin:0;background:#0b0f14;color:#e7eef7}
    .topbar{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;background:#111826;border-bottom:1px solid #1f2a3a}
    .brand{font-weight:700;letter-spacing:.3px}
    .nav a{color:#cfe3ff;text-decoration:none;margin-left:14px;padding:8px 10px;border-radius:10px}
    .nav a:hover{background:#192436}
    .wrap{max-width:1100px;margin:18px auto;padding:0 14px}
    .card{background:#0f1622;border:1px solid #1f2a3a;border-radius:16px;padding:14px;margin-bottom:16px}
    h2{margin:0 0 10px 0;font-size:18px}
    .pill{display:inline-block;padding:4px 10px;border-radius:999px;background:#182235;border:1px solid #24324a;font-size:12px;color:#cfe3ff}
    .alert{background:#2a1a10;border:1px solid #6a3b1c;color:#ffd8bf;padding:10px;border-radius:14px}
    table{width:100%;border-collapse:collapse}
    th,td{padding:10px;border-bottom:1px solid #1f2a3a;vertical-align:top}
    th{color:#b9c7da;font-size:12px;text-transform:uppercase;letter-spacing:.08em}
    .muted{color:#9fb0c6;font-size:12px}
    .btn{display:inline-block;padding:8px 10px;border-radius:12px;background:#182235;border:1px solid #24324a;color:#e7eef7;text-decoration:none}
    .btn:hover{background:#1b2a43}
    .btn-danger{background:#2a1214;border-color:#5a2428}
    .btn-danger:hover{background:#3a171a}
    .btn-primary{background:#12304a;border-color:#1f5a8a}
    .btn-primary:hover{background:#154064}
    input,textarea,select{width:100%;padding:10px;border-radius:12px;border:1px solid #24324a;background:#0b0f14;color:#e7eef7;box-sizing:border-box}
    textarea{min-height:90px;resize:vertical}
    .row{display:grid;grid-template-columns:1fr;gap:12px}
    @media(min-width:720px){.row{grid-template-columns:1fr 1fr}}
    .actions a{margin-right:8px;white-space:nowrap}
    .small{font-size:12px}
    .dircode{font-family:ui-monospace,Consolas,monospace;font-size:12px;color:#cfe3ff}
    .category-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
    .category-title-wrap{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
    .icon-btn{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:10px;background:#182235;border:1px solid #24324a;color:#e7eef7;cursor:pointer;font-size:16px;line-height:1}
    .icon-btn:hover{background:#1b2a43}
    .icon-btn.is-unlocked{background:#12304a;border-color:#1f5a8a}
    .drag-handle{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:8px;border:1px solid #24324a;background:#111826;color:#9fb0c6;font-size:15px;cursor:grab;opacity:.55;transition:opacity .15s ease, background .15s ease}
    .drag-handle:hover{opacity:1;background:#162131}
    .category-card.is-unlocked .drag-handle{opacity:1;color:#cfe3ff}
    .category-card.is-locked .drag-handle{cursor:not-allowed}
    .sortable-row.dragging{opacity:.45}
    .reorder-status{min-height:18px}

    .version-badge{
      display:inline-block;
      margin-left:8px;
      padding:3px 8px;
      border-radius:999px;
      font-size:12px;
      font-weight:700;
      line-height:1;
      vertical-align:middle;
      border:1px solid transparent;
    }

    .version-release{
      background:#153221;
      color:#b7f7cd;
      border-color:#24563a;
    }

    .version-major{
      background:#132b44;
      color:#beddff;
      border-color:#24527c;
    }

    .version-beta{
      background:#3a2a12;
      color:#ffdca8;
      border-color:#7a5822;
    }

    .version-dev{
      background:#2e1f45;
      color:#d7c2ff;
      border-color:#5a3b8a;
    }

    .version-unknown{
      background:#2a2f36;
      color:#d5dce5;
      border-color:#47515c;
    }
  </style>
</head>
<body>
  <div class="topbar">
    <div class="brand">📁 <?php echo htmlspecialchars(APP_TITLE); ?></div>
	<div class="nav">
	  <a href="index.php">Home</a>
	  <a href="project_add.php">Add Project</a>
	  <a href="upload_project.php">Upload Project</a>
      <a href="changelog.php">Changelog</a>
	  <a href="debug_scan.php">Debug Scan</a>
	  <a href="install_schema.php">Install/Repair Schema</a>
	</div>
  </div>
  <div class="wrap">