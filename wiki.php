<?php
require_once __DIR__ . "/bootstrap.php";
require_once __DIR__ . "/header.php";
?>

<div class="card">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
    <div>
      <h2 style="margin:0 0 6px 0;">Wiki</h2>
      <div class="muted">Step-by-step instructions for using <?php echo htmlspecialchars(APP_TITLE); ?> as your local project hub.</div>
    </div>
    <span class="pill">User Guide</span>
  </div>
</div>

<div class="row" style="align-items:start;">
  <div class="card" style="flex:1.25;">
    <h2>What This App Is</h2>
    <p class="muted" style="font-size:14px;line-height:1.7;">
      Project Menu is a local launcher and organizer for your PHP and HTML projects. It is meant to sit on a portable or local server and act as the
      main hub where you can view projects, launch them, upload add-ons, and keep project information organized in one place.
    </p>
    <p class="muted" style="font-size:14px;line-height:1.7;">
      The long-term goal is to package this menu together with a portable server into a single EXE. After that, users can run the hub locally,
      then install your add-on ZIP packages or even upload their own compatible projects into it.
    </p>
  </div>

  <div class="card" style="flex:0.75;">
    <h2>Quick Links</h2>
    <div style="display:grid;gap:10px;">
      <a class="btn" href="index.php">Home</a>
      <a class="btn" href="project_add.php">Add Project</a>
      <a class="btn" href="upload_project.php">Upload Project ZIP</a>
      <a class="btn" href="changelog.php">View Changelog</a>
      <a class="btn" href="admin.php">Admin Menu</a>
    </div>
  </div>
</div>

<div class="card">
  <h2>Getting Started</h2>
  <ol class="muted" style="line-height:1.9;font-size:14px;padding-left:20px;margin:0;">
    <li>Open Project Menu in your local browser.</li>
    <li>Use <span class="dircode">Add Project</span> if you want to manually register a project folder that already exists.</li>
    <li>Use <span class="dircode">Upload Project</span> if you want to upload a ZIP package and let the app install it into your projects area.</li>
    <li>From the Home page, click any project row to launch that project.</li>
    <li>Use the padlock on a category only when you want to edit, reorder, update, or delete projects in that section.</li>
    <li>Use <span class="dircode">Admin</span> for maintenance actions like schema repair and scan troubleshooting.</li>
  </ol>
</div>

<div class="row" style="align-items:start;">
  <div class="card" style="flex:1;">
    <h2>How the Home Screen Works</h2>
    <ul class="muted" style="line-height:1.9;font-size:14px;padding-left:18px;margin:0;">
      <li>Projects are grouped by category.</li>
      <li>Each project row is clickable and launches that project.</li>
      <li>If a project has linked branches, a branch badge appears on that row showing how many it has.</li>
      <li>Rows never show an empty branch count, so you will not see <span class="dircode">Branches: 0</span>.</li>
      <li>Branch projects are hidden from the main project list once linked, and clicking the parent row opens a branch-selection dialog.</li>
      <li>When a category is <strong>locked</strong>, the view stays clean for normal use.</li>
      <li>When a category is <strong>unlocked</strong>, editing tools appear for that category.</li>
      <li>Unlock mode is used for reordering and project management, then you can lock it again when done.</li>
    </ul>
  </div>

  <div class="card" style="flex:1;">
    <h2>Locked vs Unlocked Mode</h2>
    <p class="muted" style="font-size:14px;line-height:1.7;margin-top:0;">
      The padlock controls edit mode for each category.
    </p>
    <ul class="muted" style="line-height:1.9;font-size:14px;padding-left:18px;margin:0;">
      <li><strong>Locked</strong>: the closed padlock icon means the category is in clean viewing mode. Reorder controls and action icons stay hidden.</li>
      <li><strong>Unlocked</strong>: the open padlock icon means the category is in management mode. Order controls and action icons appear together.</li>
      <li>The padlock icon itself is the only lock-state indicator, so no extra Locked / Unlocked text is shown.</li>
      <li>Projects that are saved in the database but missing on disk are highlighted with a red row so they stand out immediately.</li>
      <li>Use unlocked mode only when you need to manage that category.</li>
    </ul>
  </div>
</div>

<div class="card">
  <h2>Admin Menu</h2>
  <p class="muted" style="font-size:14px;line-height:1.7;">
    Project Menu now includes a dedicated <span class="dircode">Admin</span> menu in the top navigation. This keeps maintenance tools out of the main navigation flow while still making them easy to access when needed.
  </p>
  <ul class="muted" style="line-height:1.9;font-size:14px;padding-left:18px;margin:0;">
    <li><strong>Install / Repair Schema</strong> is used after patches that change the database structure or when repairing setup issues.</li>
    <li><strong>Debug Scan</strong> helps troubleshoot folder detection and shows what the scanner is seeing inside the projects area.</li>
    <li><strong>Changelog</strong> now uses a database-backed viewer with a left-side version list, but the current source-of-truth release notes still live in <span class="dircode">CHANGELOG.md</span> and are synced into the database by Install / Repair Schema.</li>
    <li>Future admin-only tools will also be grouped here.</li>
  </ul>
</div>

<div class="card">
  <h2>Adding Projects Manually</h2>
  <ol class="muted" style="line-height:1.9;font-size:14px;padding-left:20px;margin:0;">
    <li>Click <span class="dircode">Add Project</span>.</li>
    <li>Enter the project name, description, version, category, and folder information as needed.</li>
    <li>Save the project.</li>
    <li>The project will then appear on the Home page under its selected category.</li>
  </ol>
  <p class="muted" style="font-size:14px;line-height:1.7;margin:14px 0 0 0;">
    Manual add is best for older projects, simple HTML tools, or anything that does not yet include a metadata file.
  </p>
</div>

<div class="card">
  <h2>Uploading ZIP Packages</h2>
  <ol class="muted" style="line-height:1.9;font-size:14px;padding-left:20px;margin:0;">
    <li>Click <span class="dircode">Upload Project ZIP</span>.</li>
    <li>Select the ZIP file you want to install.</li>
    <li>The app extracts the upload into the projects area.</li>
    <li>If a valid <span class="dircode">project_info.json</span> file is found, the project can auto-fill its details.</li>
    <li>If a ZIP has one extra folder level, the uploader will try to detect the real project folder automatically.</li>
  </ol>
  <p class="muted" style="font-size:14px;line-height:1.7;margin:14px 0 0 0;">
    This upload flow is what will power your future add-on system. Your downloadable packages can include their own info file so they register themselves when uploaded.
  </p>
</div>

<div class="card">
  <h2>The project_info.json File</h2>
  <p class="muted" style="font-size:14px;line-height:1.7;">
    This file is the metadata contract for auto-add and update features. When it exists and contains valid JSON, Project Menu can read it and use it to fill in project details.
  </p>
  <pre style="margin:12px 0 0 0;white-space:pre-wrap;word-wrap:break-word;font-family:ui-monospace,Consolas,monospace;font-size:13px;line-height:1.5;color:#e7eef7;">{
  "project_name": "BudgetMinder",
  "version": "2.0.2",
  "description": "A PHP/MySQL web application for the budget minder.",
  "category": "Finished"
}</pre>
  <h3 style="margin:18px 0 8px 0;">Current Supported Fields</h3>
  <ul class="muted" style="line-height:1.9;font-size:14px;padding-left:18px;margin:0;">
    <li><span class="dircode">project_name</span> - the display name saved in Project Menu</li>
    <li><span class="dircode">version</span> - the project version</li>
    <li><span class="dircode">description</span> - short summary shown in the menu</li>
    <li><span class="dircode">category</span> - where the project is grouped on the Home page</li>
  </ul>
</div>

<div class="row" style="align-items:start;">
  <div class="card" style="flex:1;">
    <h2>Important JSON Rules</h2>
    <ul class="muted" style="line-height:1.9;font-size:14px;padding-left:18px;margin:0;">
      <li>No comments inside JSON files.</li>
      <li>No trailing commas after the last item.</li>
      <li>Text values must be inside double quotes.</li>
      <li>The file must be named exactly <span class="dircode">project_info.json</span>.</li>
    </ul>
  </div>

  <div class="card" style="flex:1;">
    <h2>Supported Categories</h2>
    <ul class="muted" style="line-height:1.9;font-size:14px;padding-left:18px;margin:0;">
      <li><span class="dircode">In-Progress</span></li>
      <li><span class="dircode">Development</span></li>
      <li><span class="dircode">Finished</span></li>
    </ul>
  </div>
</div>

<div class="card">
  <h2>Reordering Projects</h2>
  <ol class="muted" style="line-height:1.9;font-size:14px;padding-left:20px;margin:0;">
    <li>Go to the category you want to manage.</li>
    <li>Click the padlock to unlock that category.</li>
    <li>The order controls will appear.</li>
    <li>Drag and drop the project rows into the order you want.</li>
    <li>Lock the category again when you are finished.</li>
  </ol>
  <p class="muted" style="font-size:14px;line-height:1.7;margin:14px 0 0 0;">
    Project order is saved in the database, not in the browser, so the layout stays consistent after refreshes and later use.
  </p>
</div>

<div class="card">
  <h2>Branches</h2>
  <p class="muted" style="font-size:14px;line-height:1.7;">
    Branches are useful when one main project has multiple variations such as language versions. A project can now be marked as a branch of another project during upload, manual add, or edit.
  </p>
  <ul class="muted" style="line-height:1.9;font-size:14px;padding-left:18px;margin:0;">
    <li>Main project rows show a <span class="dircode">Branches: X</span> badge only when they actually have linked branches.</li>
    <li>Branch projects can show <span class="dircode">Branch of: Parent Project</span> on the Home page so they are easier to identify.</li>
    <li>To link a project as a branch, check <span class="dircode">This project is a branch of another project</span> and choose the parent project from the dropdown.</li>
    <li>If a project is not a branch, leave that option unchecked and no branch relationship will be saved.</li>
  </ul>
</div>

<div class="card">
  <h2>Project Actions</h2>
  <p class="muted" style="font-size:14px;line-height:1.7;">
    Action icons appear only when a category is unlocked so the normal view stays uncluttered.
  </p>
  <ul class="muted" style="line-height:1.9;font-size:14px;padding-left:18px;margin:0;">
    <li><strong>Pencil</strong> - edit the saved project entry</li>
    <li><strong>Trash can</strong> - delete the saved project entry, or remove a missing-on-disk database entry when a project folder has already been deleted manually</li>
    <li><strong>Refresh / update icon</strong> - reread the project's <span class="dircode">project_info.json</span> and update the saved project details</li>
  </ul>
  <p class="muted" style="font-size:14px;line-height:1.7;margin:14px 0 0 0;">
    If a project is missing on disk because its folder was renamed or moved, unlock the category and use the pencil icon to edit the saved directory instead of deleting the entry.
  </p>
</div>

<div class="card">
  <h2>Updating a Project from project_info.json</h2>
  <ol class="muted" style="line-height:1.9;font-size:14px;padding-left:20px;margin:0;">
    <li>Update the project's <span class="dircode">project_info.json</span> file inside that project folder.</li>
    <li>Unlock the category that contains the project.</li>
    <li>Click the update icon for that project.</li>
    <li>Project Menu rereads the metadata file and updates the saved entry.</li>
  </ol>
  <p class="muted" style="font-size:14px;line-height:1.7;margin:14px 0 0 0;">
    This is useful when you release a new version of one of your add-ons and want the hub to refresh its name, version, description, or category without manual re-entry.
  </p>
</div>

<div class="card">
  <h2>How to Package Add-Ons for This System</h2>
  <ol class="muted" style="line-height:1.9;font-size:14px;padding-left:20px;margin:0;">
    <li>Build the project so it runs from its own folder.</li>
    <li>Place a valid <span class="dircode">project_info.json</span> file in that project folder.</li>
    <li>ZIP the project folder.</li>
    <li>Upload that ZIP through Project Menu.</li>
  </ol>
  <p class="muted" style="font-size:14px;line-height:1.7;margin:14px 0 0 0;">
    If all your projects include a valid metadata file, they can auto-add themselves into the menu with much less manual work.
  </p>
</div>


<div class="card">
  <h2>Versioning and Changelog Workflow</h2>
  <p class="muted" style="font-size:14px;line-height:1.7;">
    Project Menu now uses one running <span class="dircode">Unreleased</span> changelog entry while work is in progress. This keeps the release history from filling up with duplicate notes every time a small related change bumps the visible app version.
  </p>
  <ul class="muted" style="line-height:1.9;font-size:14px;padding-left:18px;margin:0;">
    <li><strong>MAJOR</strong> version bumps are for big milestones or breaking workflow changes.</li>
    <li><strong>MINOR</strong> version bumps are for meaningful new features or larger improvements.</li>
    <li><strong>PATCH</strong> version bumps are for grouped fixes and polish work.</li>
    <li>Put all in-progress notes under <span class="dircode">Unreleased</span> first.</li>
    <li>When you are ready to release, move the grouped <span class="dircode">Unreleased</span> notes into the final release version instead of copying the same bullet into several separate versions.</li>
    <li>The changelog sidebar can show a range such as <span class="dircode">v3.1.0 - v3.9.6</span> for the current unreleased development batch.</li>
    <li>Use grouped sections such as <span class="dircode">Added</span>, <span class="dircode">Changed</span>, <span class="dircode">Fixed</span>, and <span class="dircode">Removed</span>.</li>
  </ul>
</div>

<div class="card">
  <h2>Best Practices</h2>
  <ul class="muted" style="line-height:1.9;font-size:14px;padding-left:18px;margin:0;">
    <li>Keep each project inside its own folder.</li>
    <li>Keep <span class="dircode">project_info.json</span> clean and valid.</li>
    <li>Use the update icon after changing a project's version or description.</li>
    <li>Use unlock mode only when you want to manage projects.</li>
    <li>Keep <span class="dircode">CHANGELOG.md</span> current using one grouped <span class="dircode">Unreleased</span> section until you are ready to cut a real release. The database viewer is refreshed from that file during schema repair.</li>
  </ul>
</div>

<div class="card">
  <h2>Planned Next Steps</h2>
  <ul class="muted" style="line-height:1.9;font-size:14px;padding-left:18px;margin:0;">
    <li>Database conflict protection so different projects do not accidentally try to use the same database name.</li>
    <li>Further wiki expansion as the portable EXE and add-on ecosystem grows.</li>
    <li>More project metadata support in <span class="dircode">project_info.json</span> as needed.</li>
  </ul>
</div>

<?php require_once __DIR__ . "/footer.php"; ?>
