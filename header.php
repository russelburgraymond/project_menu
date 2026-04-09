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
    .actions{white-space:nowrap}
    .action-link{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:10px;background:#182235;border:1px solid #24324a;color:#e7eef7;text-decoration:none;font-size:16px;line-height:1;vertical-align:middle}
    .action-link:hover{background:#1b2a43}
    .action-link.delete{background:#2a1214;border-color:#5a2428}
    .action-link.delete:hover{background:#3a171a}
    .action-link.update{background:#12304a;border-color:#1f5a8a}
    .action-link.update:hover{background:#154064}
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
    .category-card.is-locked .reorder-column,
    .category-card.is-locked .drag-cell,
    .category-card.is-locked .drag-handle,
    .category-card.is-locked .actions-column,
    .category-card.is-locked .actions-cell{display:none}
    .category-card.is-unlocked .reorder-column,
    .category-card.is-unlocked .drag-cell,
    .category-card.is-unlocked .actions-column,
    .category-card.is-unlocked .actions-cell{display:table-cell}
    .category-card.is-unlocked .drag-handle{display:inline-flex}
    .sortable-row.dragging{opacity:.45}
    .sortable-row[data-launch-url]{cursor:pointer}
    .sortable-row[data-launch-url]:hover{background:#111826}
    .category-card.is-unlocked .sortable-row[data-launch-url]{cursor:default}
    .project-main-cell{position:relative}
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

    .changelog-hero{background:linear-gradient(180deg,#101b2d 0%, #0f1622 100%);border-color:#27415f;box-shadow:0 10px 30px rgba(0,0,0,.22)}
    .stat-pill{display:inline-flex;flex-direction:column;justify-content:center;align-items:flex-start;min-width:88px;padding:10px 12px;border-radius:14px;background:#0d1624;border:1px solid #24324a;color:#cfe3ff}
    .stat-pill strong{font-size:18px;line-height:1.1;color:#ffffff}
    .stat-pill span{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#8fa8c6;margin-top:3px}
    .changelog-layout{display:grid;grid-template-columns:280px minmax(0,1fr);gap:16px;align-items:start}
    .changelog-sidebar{position:sticky;top:18px;padding:0;overflow:hidden}
    .sidebar-title-row{padding:18px 18px 12px 18px;border-bottom:1px solid #1f2a3a}
    .version-list{display:flex;flex-direction:column;padding:10px}
    .version-link{display:block;padding:12px 14px;border-radius:14px;text-decoration:none;border:1px solid transparent;color:#dce8f7;transition:background .15s ease,border-color .15s ease,transform .15s ease}
    .version-link:hover{background:#121d2e;border-color:#27415f;transform:translateX(2px)}
    .version-link.is-active{background:linear-gradient(180deg,#15304f 0%,#10233a 100%);border-color:#2d5f94;box-shadow:inset 0 0 0 1px rgba(115,168,233,.12)}
    .version-link-main{display:block;font-weight:700;font-size:15px;color:#ffffff}
    .version-link-meta{display:block;margin-top:5px;font-size:12px;color:#98aec8}
    .empty-note{padding:18px;color:#9fb0c6;font-size:14px}
    .release-header-card{background:linear-gradient(180deg,#10192a 0%, #0f1622 100%);border-color:#27415f}
    .changelog-section-card{padding:18px}
    .section-heading-row{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding-bottom:12px;border-bottom:1px solid #1f2a3a;margin-bottom:14px}
    .release-items{display:grid;gap:12px}
    .release-item{display:grid;grid-template-columns:14px minmax(0,1fr);gap:12px;align-items:flex-start;padding:12px 14px;border-radius:14px;background:#0d1624;border:1px solid #1d2d44}
    .release-bullet{width:10px;height:10px;border-radius:999px;background:#4b8cd8;margin-top:5px;box-shadow:0 0 0 4px rgba(75,140,216,.12)}
    .release-copy{line-height:1.7;color:#e7eef7}
    .empty-state-card{padding:24px}
    @media(max-width:900px){.changelog-layout{grid-template-columns:1fr}.changelog-sidebar{position:static}}
  </style>
</head>
<body>
  <div class="topbar">
    <div class="brand">📁 <?php echo htmlspecialchars(APP_TITLE); ?></div>
	<div class="nav">
	  <a href="index.php">Home</a>
	  <a href="project_add.php">Add Project</a>
	  <a href="upload_project.php">Upload Project</a>
      <a href="wiki.php">Wiki</a>
      <a href="admin.php">Admin</a>
	</div>
  </div>
  <div class="wrap">