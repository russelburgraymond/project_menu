<?php
require_once __DIR__ . "/bootstrap.php";
require_once __DIR__ . "/scanner.php";
require_once __DIR__ . "/header.php";

$directoryPrefill = $_GET["directory"] ?? "";
$diskDirs = scan_laragon_www_dirs();
$categories = pm_get_categories($conn);
$error = "";
$projectsBase = __DIR__ . DIRECTORY_SEPARATOR . "projects";

function next_sort_order(mysqli $conn, string $category): int {
    $stmt = $conn->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort FROM projects WHERE category = ?");
    $stmt->bind_param("s", $category);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row["next_sort"] ?? 1);
}

function branch_parent_options(mysqli $conn, string $excludeDirectory = ""): array {
    $options = [];
    $res = $conn->query("SELECT id, project_name, directory FROM projects WHERE is_active = 1 ORDER BY project_name, id");
    while ($row = $res->fetch_assoc()) {
        if ($excludeDirectory !== "" && (string)$row["directory"] === $excludeDirectory) {
            continue;
        }
        $options[] = $row;
    }
    return $options;
}

function write_project_info_file(string $projectsBase, string $directory, array $data, bool $overwrite = false): array {
    $folder = $projectsBase . DIRECTORY_SEPARATOR . $directory;
    if (!is_dir($folder)) {
        return [false, "Project folder does not exist."];
    }

    $file = $folder . DIRECTORY_SEPARATOR . "project_info.json";

    if (is_file($file) && !$overwrite) {
        return [false, "project_info.json already exists."];
    }

    $payload = [
        "project_name" => $data["project_name"],
        "version"      => $data["version"],
        "description"  => $data["description"],
        "category"     => $data["category"],
    ];

    if (!empty($data["is_branch"]) && !empty($data["parent_project_name"])) {
        $payload["is_branch"] = true;
        $payload["parent_project_name"] = $data["parent_project_name"];
    }

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        return [false, "Could not build project_info.json."];
    }

    if (file_put_contents($file, $json) === false) {
        return [false, "Could not write project_info.json."];
    }

    return [true, ""];
}

$branchParents = branch_parent_options($conn);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $project_name = trim($_POST["project_name"] ?? "");
  $version      = trim($_POST["version"] ?? "");
  $description  = trim($_POST["description"] ?? "");
  $directory    = trim($_POST["directory"] ?? "");
  $category     = $_POST["category"] ?? pm_first_active_category($conn);
  $overwrite_info = isset($_POST["overwrite_info"]);
  $is_branch = isset($_POST["is_branch"]) ? 1 : 0;
  $parent_project_id = (int)($_POST["parent_project_id"] ?? 0);
  $parent_project_name = "";

  if ($project_name === "" || $directory === "") {
    $error = "Project name and directory are required.";
  } elseif (!pm_is_valid_category($conn, $category)) {
    $error = "Invalid category.";
  } elseif ($is_branch && $parent_project_id <= 0) {
    $error = "Select a parent project for this branch.";
  } else {
    if ($is_branch) {
      $parentStmt = $conn->prepare("SELECT project_name FROM projects WHERE id = ? LIMIT 1");
      $parentStmt->bind_param("i", $parent_project_id);
      $parentStmt->execute();
      $parentRow = $parentStmt->get_result()->fetch_assoc();
      if (!$parentRow) {
        $error = "Selected parent project was not found.";
      } else {
        $parent_project_name = (string)$parentRow["project_name"];
      }
    }
  }

  if ($error === "") {
    $sort_order = next_sort_order($conn, $category);
    $stmt = $conn->prepare("\n      INSERT INTO projects (project_name, version, description, directory, category, sort_order, is_branch, parent_project_id)\n      VALUES (?, ?, ?, ?, ?, ?, ?, ?)\n    ");
    $parentProjectIdForSave = $is_branch ? $parent_project_id : null;
    $stmt->bind_param("sssssiii", $project_name, $version, $description, $directory, $category, $sort_order, $is_branch, $parentProjectIdForSave);

    try {
      $stmt->execute();

      [$ok, $writeError] = write_project_info_file($projectsBase, $directory, [
        "project_name" => $project_name,
        "version"      => $version,
        "description"  => $description,
        "category"     => $category,
        "is_branch"    => $is_branch,
        "parent_project_name" => $parent_project_name,
      ], $overwrite_info);

      if (!$ok && $writeError === "project_info.json already exists.") {
        $error = "Project saved to database, but project_info.json already exists. Check overwrite and save again if you want to replace it.";
      } elseif (!$ok) {
        $error = "Project saved to database, but metadata file could not be written: " . $writeError;
      } else {
        header("Location: index.php");
        exit;
      }
    } catch (mysqli_sql_exception $e) {
      if (stripos($e->getMessage(), "uniq_directory") !== false) {
        $error = "That directory is already in the database.";
      } else {
        $error = "Database error: " . $e->getMessage();
      }
    }
  }
}
?>

<div class="card">
  <h2>Add Project</h2>

  <?php if ($error): ?>
    <div class="alert"><b>Error:</b> <?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <form method="post">
    <div class="row">
      <div>
        <label class="muted">Project Name</label>
        <input name="project_name" value="<?php echo htmlspecialchars($_POST["project_name"] ?? ""); ?>">
      </div>

      <div>
        <label class="muted">Version</label>
        <input name="version" value="<?php echo htmlspecialchars($_POST["version"] ?? ""); ?>" placeholder="Example: 1.0.0">
      </div>
    </div>

    <div style="margin-top:12px">
      <label class="muted">Category</label>
      <select name="category">
        <?php
          $current = $_POST["category"] ?? pm_first_active_category($conn);
          foreach ($categories as $c) {
            $sel = ($c === $current) ? "selected" : "";
            echo "<option $sel>" . htmlspecialchars($c) . "</option>";
          }
        ?>
      </select>
    </div>

    <div style="margin-top:12px">
      <label class="muted">Description</label>
      <textarea name="description"><?php echo htmlspecialchars($_POST["description"] ?? ""); ?></textarea>
    </div>

    <div style="margin-top:12px">
      <label class="muted" style="display:flex;align-items:center;gap:8px;">
        <input type="checkbox" name="is_branch" value="1" style="width:auto;" <?php echo isset($_POST["is_branch"]) ? "checked" : ""; ?>>
        This project is a branch of another project
      </label>
    </div>

    <div id="branch-parent-wrap"<?php echo isset($_POST["is_branch"]) ? "" : ' style="display:none;"'; ?>>
      <label class="muted">Parent Project</label>
      <select name="parent_project_id">
        <option value="0">-- Select parent project --</option>
        <?php
          $selectedParent = (int)($_POST["parent_project_id"] ?? 0);
          foreach ($branchParents as $parent) {
            $sel = ((int)$parent["id"] === $selectedParent) ? "selected" : "";
            echo '<option value="' . (int)$parent["id"] . '" ' . $sel . '>' . htmlspecialchars($parent["project_name"]) . '</option>';
          }
        ?>
      </select>
    </div>

    <div style="margin-top:12px">
      <label class="muted">Directory</label>
      <select name="directory">
        <option value="">-- Select a folder --</option>
        <?php
          $selectedDir = $_POST["directory"] ?? $directoryPrefill;
          foreach ($diskDirs as $d) {
            $sel = ($d === $selectedDir) ? "selected" : "";
            echo "<option value=\"" . htmlspecialchars($d) . "\" $sel>" . htmlspecialchars($d) . "</option>";
          }
        ?>
      </select>
    </div>

    <div style="margin-top:12px">
      <label class="muted" style="display:flex;align-items:center;gap:8px;">
        <input type="checkbox" name="overwrite_info" value="1" style="width:auto;">
        Overwrite existing project_info.json if present
      </label>
    </div>

    <div style="margin-top:14px">
      <button class="btn btn-primary" type="submit">Save</button>
      <a class="btn" href="index.php">Cancel</a>
    </div>
  </form>
</div>

<script>
(function () {
  var checkbox = document.querySelector('input[name="is_branch"]');
  var wrap = document.getElementById('branch-parent-wrap');
  if (!checkbox || !wrap) return;
  checkbox.addEventListener('change', function () {
    wrap.style.display = checkbox.checked ? '' : 'none';
  });
})();
</script>

<?php require_once __DIR__ . "/footer.php"; ?>
