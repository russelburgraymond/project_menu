<?php
require_once __DIR__ . "/bootstrap.php";
require_once __DIR__ . "/scanner.php";
require_once __DIR__ . "/header.php";

$id = (int)($_GET["id"] ?? 0);
$categories = ["In-Progress", "Development", "Finished"];
$diskDirs = scan_laragon_www_dirs();

$stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

if (!$project) {
  echo "<div class='card'><div class='alert'><b>Not found.</b></div></div>";
  require_once __DIR__ . "/footer.php";
  exit;
}

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

  if ($project_name === "" || $directory === "") {
    $error = "Project name and directory are required.";
  } elseif (!in_array($category, $categories, true)) {
    $error = "Invalid category.";
  } else {
    $sort_order = (int)($project["sort_order"] ?? 0);
    if ($category !== ($project["category"] ?? "") || $sort_order <= 0) {
      $sort_order = next_sort_order($conn, $category);
    }

    $stmt = $conn->prepare("
      UPDATE projects
      SET project_name = ?, version = ?, description = ?, directory = ?, category = ?, sort_order = ?
      WHERE id = ?
    ");
    $stmt->bind_param("sssssii", $project_name, $version, $description, $directory, $category, $sort_order, $id);

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
          foreach (["In-Progress","Development","Finished"] as $c) {
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

<?php require_once __DIR__ . "/footer.php"; ?>