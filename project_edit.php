<?php
require_once __DIR__ . "/bootstrap.php";
require_once __DIR__ . "/scanner.php";

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

$id = (int)($_GET["id"] ?? 0);
$categories = pm_get_categories($conn);
$diskDirs = scan_laragon_www_dirs();

$stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

if (!$project) {
  require_once __DIR__ . "/header.php";
  echo "<div class='card'><div class='alert'><b>Not found.</b></div></div>";
  require_once __DIR__ . "/footer.php";
  exit;
}

$branchParents = branch_parent_options($conn, (string)$project["directory"]);
$error = "";

function next_sort_order(mysqli $conn, string $category): int {
  $stmt = $conn->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort FROM projects WHERE category = ?");
  $stmt->bind_param("s", $category);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  return (int)($row["next_sort"] ?? 1);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $project_name = trim($_POST["project_name"] ?? "");
  $version      = trim($_POST["version"] ?? "");
  $description  = trim($_POST["description"] ?? "");
  $directory    = trim($_POST["directory"] ?? "");
  $category     = $_POST["category"] ?? $project["category"];
  $is_branch    = isset($_POST["is_branch"]) ? 1 : 0;
  $parent_project_id = (int)($_POST["parent_project_id"] ?? 0);

  if ($project_name === "" || $directory === "") {
    $error = "Project name and directory are required.";
  } elseif (!pm_is_valid_category($conn, $category)) {
    $error = "Invalid category.";
  } elseif ($is_branch && $parent_project_id <= 0) {
    $error = "Select a parent project for this branch.";
  } else {
    $sort_order = (int)($project["sort_order"] ?? 0);
    if ($category !== ($project["category"] ?? "") || $sort_order <= 0) {
      $sort_order = next_sort_order($conn, $category);
    }

    $stmt = $conn->prepare("\n      UPDATE projects\n      SET project_name = ?, version = ?, description = ?, directory = ?, category = ?, sort_order = ?, is_branch = ?, parent_project_id = ?\n      WHERE id = ?\n    ");
    $parentProjectIdForSave = $is_branch ? $parent_project_id : null;
    $stmt->bind_param("sssssiiii", $project_name, $version, $description, $directory, $category, $sort_order, $is_branch, $parentProjectIdForSave, $id);

    try {
      $stmt->execute();
      header("Location: index.php");
      exit;
    } catch (mysqli_sql_exception $e) {
      $error = "Database error: " . $e->getMessage();
    }
  }
}
?>

<?php require_once __DIR__ . "/header.php"; ?>

<div class="card">
  <h2>Edit Project</h2>

  <?php if ($error): ?>
    <div class="alert"><b>Error:</b> <?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <form method="post">
    <div class="row">
      <div>
        <label class="muted">Project Name</label>
        <input name="project_name"
               value="<?php echo htmlspecialchars($_POST["project_name"] ?? $project["project_name"]); ?>" />
      </div>

      <div>
        <label class="muted">Version</label>
        <input name="version"
               value="<?php echo htmlspecialchars($_POST["version"] ?? ($project["version"] ?? "")); ?>"
               placeholder="Example: 1.0.0" />
      </div>
    </div>

    <div style="margin-top:12px">
      <label class="muted">Category</label>
      <select name="category">
        <?php
          $current = $_POST["category"] ?? $project["category"];
          foreach ($categories as $c) {
            $sel = ($c === $current) ? "selected" : "";
            echo "<option $sel>" . htmlspecialchars($c) . "</option>";
          }
        ?>
      </select>
    </div>

    <div style="margin-top:12px">
      <label class="muted">Description</label>
      <textarea name="description"><?php echo htmlspecialchars($_POST["description"] ?? ($project["description"] ?? "")); ?></textarea>
    </div>

    <div style="margin-top:12px">
      <label class="muted" style="display:flex;align-items:center;gap:8px;">
        <input type="checkbox" name="is_branch" value="1" style="width:auto;" <?php echo isset($_POST["is_branch"]) ? "checked" : (!empty($project["is_branch"]) ? "checked" : ""); ?>>
        This project is a branch of another project
      </label>
    </div>

    <div id="branch-parent-wrap"<?php echo (isset($_POST["is_branch"]) || !empty($project["is_branch"])) ? "" : ' style="display:none;"'; ?>>
      <label class="muted">Parent Project</label>
      <select name="parent_project_id">
        <option value="0">-- Select parent project --</option>
        <?php
          $selectedParent = (int)($_POST["parent_project_id"] ?? ($project["parent_project_id"] ?? 0));
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
        <?php
          $selectedDir = $_POST["directory"] ?? $project["directory"];
          $all = $diskDirs;
          if (!in_array($selectedDir, $all, true)) {
            array_unshift($all, $selectedDir);
          }

          foreach ($all as $d) {
            $sel = ($d === $selectedDir) ? "selected" : "";
            $missing = (!in_array($d, $diskDirs, true)) ? " (missing on disk)" : "";
            echo "<option value=\"" . htmlspecialchars($d) . "\" $sel>" . htmlspecialchars($d . $missing) . "</option>";
          }
        ?>
      </select>
    </div>

    <div style="margin-top:14px">
      <button class="btn btn-primary" type="submit">Save Changes</button>
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
