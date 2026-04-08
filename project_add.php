<?php
require_once __DIR__ . "/bootstrap.php";
require_once __DIR__ . "/scanner.php";
require_once __DIR__ . "/header.php";

$directoryPrefill = $_GET["directory"] ?? "";
$diskDirs = scan_laragon_www_dirs();
$categories = ["In-Progress", "Development", "Finished"];
$error = "";
$projectsBase = __DIR__ . DIRECTORY_SEPARATOR . "projects";

function next_sort_order(mysqli $conn, string $category): int {
    $stmt = $conn->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort FROM projects WHERE category = ?");
    $stmt->bind_param("s", $category);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row["next_sort"] ?? 1);
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

    $json = json_encode([
        "project_name" => $data["project_name"],
        "version"      => $data["version"],
        "description"  => $data["description"],
        "category"     => $data["category"],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        return [false, "Could not build project_info.json."];
    }

    if (file_put_contents($file, $json) === false) {
        return [false, "Could not write project_info.json."];
    }

    return [true, ""];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $project_name = trim($_POST["project_name"] ?? "");
  $version      = trim($_POST["version"] ?? "");
  $description  = trim($_POST["description"] ?? "");
  $directory    = trim($_POST["directory"] ?? "");
  $category     = $_POST["category"] ?? "Development";
  $overwrite_info = isset($_POST["overwrite_info"]);

  if ($project_name === "" || $directory === "") {
    $error = "Project name and directory are required.";
  } elseif (!in_array($category, $categories, true)) {
    $error = "Invalid category.";
  } else {
    $sort_order = next_sort_order($conn, $category);
    $stmt = $conn->prepare("
      INSERT INTO projects (project_name, version, description, directory, category, sort_order)
      VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssssi", $project_name, $version, $description, $directory, $category, $sort_order);

    try {
      $stmt->execute();

      [$ok, $writeError] = write_project_info_file($projectsBase, $directory, [
        "project_name" => $project_name,
        "version"      => $version,
        "description"  => $description,
        "category"     => $category,
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
          $current = $_POST["category"] ?? "Development";
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

<?php require_once __DIR__ . "/footer.php"; ?>