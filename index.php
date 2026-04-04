<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . "/bootstrap.php";
require_once __DIR__ . "/scanner.php";
require_once __DIR__ . "/header.php";

$APP_BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$PROJECTS_WEB_BASE = $APP_BASE . '/projects';

function version_badge_class(?string $version): string {
    $v = strtolower(trim((string)$version));

    if ($v === '') {
        return 'version-unknown';
    }

    if (str_contains($v, 'dev') || str_contains($v, 'alpha')) {
        return 'version-dev';
    }

    if (str_contains($v, 'beta') || str_contains($v, 'rc')) {
        return 'version-beta';
    }

    if (preg_match('/^\d+(\.\d+){0,2}$/', $v)) {
        $major = (int) explode('.', $v)[0];
        if ($major >= 2) {
            return 'version-major';
        }
        return 'version-release';
    }

    return 'version-unknown';
}

function read_project_info_file(string $directoryName): ?array {
    $file = __DIR__ . DIRECTORY_SEPARATOR . "projects" . DIRECTORY_SEPARATOR . $directoryName . DIRECTORY_SEPARATOR . "project_info.json";

    if (!is_file($file)) {
        return null;
    }

    $json = file_get_contents($file);
    if ($json === false) {
        return null;
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        return null;
    }

    $project_name = trim((string)($data["project_name"] ?? ""));
    $version      = trim((string)($data["version"] ?? ""));
    $description  = trim((string)($data["description"] ?? ""));
    $category     = trim((string)($data["category"] ?? "Development"));

    $allowedCategories = ["In-Progress", "Development", "Finished"];
    if (!in_array($category, $allowedCategories, true)) {
        $category = "Development";
    }

    if ($project_name === "") {
        $project_name = $directoryName;
    }

    return [
        "project_name" => $project_name,
        "version"      => $version,
        "description"  => $description,
        "category"     => $category,
    ];
}

$showActions = [
    "In-Progress" => isset($_GET["show_actions_in_progress"]),
    "Development" => isset($_GET["show_actions_development"]),
    "Finished"    => isset($_GET["show_actions_finished"]),
];

function category_toggle_name(string $category): string {
    return match ($category) {
        "In-Progress" => "show_actions_in_progress",
        "Development" => "show_actions_development",
        "Finished"    => "show_actions_finished",
        default       => "show_actions_unknown",
    };
}

function auto_import_projects(mysqli $conn, array $diskDirs, array $dbDirs): array {
    $stillUntracked = [];

    foreach ($diskDirs as $dirName) {
        if (isset($dbDirs[$dirName])) {
            continue;
        }

        $info = read_project_info_file($dirName);

        if ($info !== null) {
            $stmt = $conn->prepare("
                INSERT INTO projects (project_name, version, description, directory, category)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "sssss",
                $info["project_name"],
                $info["version"],
                $info["description"],
                $dirName,
                $info["category"]
            );

            try {
                $stmt->execute();
                $dbDirs[$dirName] = (int)$conn->insert_id;
            } catch (mysqli_sql_exception $e) {
                $stillUntracked[] = $dirName;
            }
        } else {
            $stillUntracked[] = $dirName;
        }
    }

    return [$dbDirs, $stillUntracked];
}

// 1) Scan directories on disk
$diskDirs = scan_laragon_www_dirs();

// 2) Load DB directories
$dbDirs = [];
$res = $conn->query("SELECT id, directory FROM projects");
while ($row = $res->fetch_assoc()) {
    $dbDirs[$row["directory"]] = (int)$row["id"];
}

// 3) Auto-import new folders if they contain project_info.json
[$dbDirs, $untracked] = auto_import_projects($conn, $diskDirs, $dbDirs);

// 4) Tracked but missing on disk
$missingOnDisk = [];
foreach ($dbDirs as $dirName => $id) {
    if (!in_array($dirName, $diskDirs, true)) {
        $missingOnDisk[] = $dirName;
    }
}

// 5) Fetch projects by category
$categories = ["In-Progress", "Development", "Finished"];
$projectsByCat = [];
foreach ($categories as $c) {
    $projectsByCat[$c] = [];
}

$stmt = $conn->prepare("
    SELECT id, project_name, version, description, directory, category
    FROM projects
    WHERE is_active = 1
    ORDER BY category, project_name
");
$stmt->execute();
$result = $stmt->get_result();

while ($p = $result->fetch_assoc()) {
    $projectsByCat[$p["category"]][] = $p;
}
?>

<?php if (count($untracked) > 0 || count($missingOnDisk) > 0): ?>
<div class="card">
  <h2>Scan Results</h2>

  <?php if (count($untracked) > 0): ?>
    <div class="alert">
      <b>Directories found without usable project info:</b>
      <ul>
        <?php foreach ($untracked as $d): ?>
          <li>
            <span class="dircode"><?php echo htmlspecialchars($d); ?></span>
            <br>
            <span class="muted">Add a <span class="dircode">project_info.json</span> file inside this project folder so it can be auto-imported.</span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if (count($missingOnDisk) > 0): ?>
    <div style="margin-top:12px" class="alert">
      <b>Tracked in DB but missing on disk:</b>
      <ul>
        <?php foreach ($missingOnDisk as $d): ?>
          <li><span class="dircode"><?php echo htmlspecialchars($d); ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="muted" style="margin-top:10px;">
    Found <b><?php echo count($diskDirs); ?></b> directories in
    <span class="dircode"><?php echo htmlspecialchars(__DIR__ . DIRECTORY_SEPARATOR . "projects"); ?></span>
  </div>
</div>
<?php endif; ?>
<?php
$totalProjects = 0;
foreach ($projectsByCat as $list) {
    $totalProjects += count($list);
}
?>

<?php if ($totalProjects === 0): ?>
<div class="card">
  <h2>No Projects Yet</h2>
  <p class="muted">
    You haven't added any projects yet. Add some now.
  </p>

  <p>
    <a class="btn btn-primary" href="project_add.php">Add Project</a>
  </p>

  <p class="muted small">
    Or create a folder inside <span class="dircode">projects</span> with a
    <span class="dircode">project_info.json</span> file and it will auto-import.
  </p>
</div>
<?php endif; ?>
<?php foreach ($categories as $cat): ?>
  <?php if (count($projectsByCat[$cat]) > 0): ?>
    <div class="card">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
	  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
		<h2 style="margin:0;"><?php echo htmlspecialchars($cat); ?></h2>
		<span class="pill"><?php echo count($projectsByCat[$cat]); ?> projects</span>
	  </div>

  <form method="get" style="margin:0;">
    <?php foreach ($showActions as $k => $enabled): ?>
      <?php if ($k !== $cat && $enabled): ?>
        <input type="hidden" name="<?php echo htmlspecialchars(category_toggle_name($k)); ?>" value="1">
      <?php endif; ?>
    <?php endforeach; ?>

    <label class="muted" style="display:flex;align-items:center;gap:8px;">
      <input type="checkbox"
             name="<?php echo htmlspecialchars(category_toggle_name($cat)); ?>"
             value="1"
             onchange="this.form.submit()"
             <?php echo !empty($showActions[$cat]) ? 'checked' : ''; ?>
             style="width:auto;">
      Show Edit/Delete buttons
    </label>
  </form>
</div>

      <table>
        <thead>
          <tr>
            <th>Project</th>
            <th>Directory</th>
            <th style="width:160px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($projectsByCat[$cat] as $p): ?>
            <tr>
              <td>
                <b><?php echo htmlspecialchars($p["project_name"]); ?></b>

                <?php if (!empty(trim((string)$p["version"]))): ?>
                  <span class="version-badge <?php echo version_badge_class($p["version"]); ?>">
                    v<?php echo htmlspecialchars($p["version"]); ?>
                  </span>
                <?php endif; ?>

                <br>
                <span class="muted"><?php echo nl2br(htmlspecialchars($p["description"] ?? "")); ?></span>
              </td>

				<td>
				  <a class="btn small"
					 href="<?php echo $PROJECTS_WEB_BASE . '/' . rawurlencode($p["directory"]) . '/'; ?>"
					 target="_blank">Open</a>
				</td>

				<td class="actions">
				  <?php if (!empty($showActions[$cat])): ?>
					<a class="btn" href="project_edit.php?id=<?php echo (int)$p["id"]; ?>">Edit</a>
					<a class="btn btn-danger"
					   href="project_delete.php?id=<?php echo (int)$p["id"]; ?>"
					   onclick="return confirm('Delete this project?');">Delete</a>
				  <?php else: ?>
					<span class="muted"></span>
				  <?php endif; ?>
				</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
<?php endforeach; ?>

<?php require_once __DIR__ . "/footer.php"; ?>