<?php
require_once __DIR__ . "/bootstrap.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$directory = trim((string)($_GET['directory'] ?? $_POST['directory'] ?? ''));
$projectsBase = __DIR__ . DIRECTORY_SEPARATOR . 'projects';
$projectPath = $directory !== '' ? $projectsBase . DIRECTORY_SEPARATOR . $directory : '';

if ($directory === '' || !is_dir($projectPath)) {
    header('Location: upload_project.php');
    exit;
}

function upload_details_prefill(mysqli $conn, string $directory, string $projectPath): array {
    $default = [
        'project_name' => str_replace('_', ' ', $directory),
        'version' => '',
        'description' => '',
        'category' => pm_first_active_category($conn),
        'is_branch' => 0,
        'parent_project_id' => 0,
        'download_project_info' => 0,
        'info_error' => '',
    ];

    $sessionPrefill = $_SESSION['upload_details_prefill'] ?? null;
    if (is_array($sessionPrefill) && ($sessionPrefill['directory'] ?? '') === $directory) {
        unset($_SESSION['upload_details_prefill']);
        return array_merge($default, $sessionPrefill);
    }

    $infoPath = $projectPath . DIRECTORY_SEPARATOR . 'project_info.json';
    if (is_file($infoPath)) {
        $json = file_get_contents($infoPath);
        if ($json !== false) {
            $data = json_decode($json, true);
            if (is_array($data)) {
                $category = trim((string)($data['category'] ?? $default['category']));
                if (!pm_is_valid_category($conn, $category, true)) {
                    $category = $default['category'];
                }
                return array_merge($default, [
                    'project_name' => trim((string)($data['project_name'] ?? $default['project_name'])),
                    'version' => trim((string)($data['version'] ?? '')),
                    'description' => trim((string)($data['description'] ?? '')),
                    'category' => $category,
                ]);
            }
            return array_merge($default, ['info_error' => 'Existing project_info.json could not be parsed and will be replaced when you save.']);
        }
    }

    return $default;
}

function next_sort_order_for_details(mysqli $conn, string $category): int {
    $stmt = $conn->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort FROM projects WHERE category = ?");
    $stmt->bind_param('s', $category);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['next_sort'] ?? 1);
}

function build_project_info_json(array $project, ?string $parentProjectName = null): string {
    $payload = [
        'project_name' => $project['project_name'],
        'version' => $project['version'],
        'description' => $project['description'],
        'category' => $project['category'],
    ];

    if (!empty($project['is_branch']) && $parentProjectName !== null && $parentProjectName !== '') {
        $payload['is_branch'] = true;
        $payload['parent_project_name'] = $parentProjectName;
    }

    return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

$prefill = upload_details_prefill($conn, $directory, $projectPath);
$error = '';
$success = '';
$autoDownload = false;
$downloadUrl = '';

$categories = pm_get_categories($conn);
$parentProjects = [];
$resParents = $conn->query("SELECT id, project_name, directory FROM projects WHERE is_active = 1 ORDER BY project_name, id");
while ($row = $resParents->fetch_assoc()) {
    if ((string)$row['directory'] === $directory) {
        continue;
    }
    $parentProjects[] = $row;
}

$dbRes = $conn->query('SHOW DATABASES');

$existingDatabases = [];
while ($row = $dbRes->fetch_array()) {
    $dbName = $row[0];

    // hide system databases
    if (in_array($dbName, [
        'information_schema',
        'mysql',
        'performance_schema',
        'sys'
    ])) {
        continue;
    }

    $existingDatabases[] = $dbName;
}

natcasesort($existingDatabases);
$existingDatabases = array_values($existingDatabases);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_name = trim((string)($_POST['project_name'] ?? ''));
    $version = trim((string)($_POST['version'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $category = trim((string)($_POST['category'] ?? pm_first_active_category($conn)));
    $is_branch = isset($_POST['is_branch']) ? 1 : 0;
    $parent_project_id = (int)($_POST['parent_project_id'] ?? 0);
    $download_project_info = isset($_POST['download_project_info']) ? 1 : 0;

    $prefill = [
        'project_name' => $project_name,
        'version' => $version,
        'description' => $description,
        'category' => $category,
        'is_branch' => $is_branch,
        'parent_project_id' => $parent_project_id,
        'download_project_info' => $download_project_info,
        'info_error' => $prefill['info_error'] ?? '',
    ];

    if ($project_name === '') {
        $error = 'Project Name is required.';
    } elseif (!pm_is_valid_category($conn, $category)) {
        $error = 'Please choose a valid category.';
    } elseif ($is_branch && $parent_project_id <= 0) {
        $error = 'Please select the parent project this upload belongs to.';
    }

    $parentProjectName = null;
    if ($error === '' && $is_branch) {
        $stmtParent = $conn->prepare('SELECT project_name FROM projects WHERE id = ? LIMIT 1');
        $stmtParent->bind_param('i', $parent_project_id);
        $stmtParent->execute();
        $parentRow = $stmtParent->get_result()->fetch_assoc();
        $stmtParent->close();
        if (!$parentRow) {
            $error = 'The selected parent project could not be found.';
        } else {
            $parentProjectName = (string)$parentRow['project_name'];
        }
    }

    if ($error === '') {
        $jsonContent = build_project_info_json([
            'project_name' => $project_name,
            'version' => $version,
            'description' => $description,
            'category' => $category,
            'is_branch' => $is_branch,
        ], $parentProjectName);

        $infoPath = $projectPath . DIRECTORY_SEPARATOR . 'project_info.json';
        if (file_put_contents($infoPath, $jsonContent) === false) {
            $error = 'The project was uploaded, but project_info.json could not be written into the project folder.';
        } else {
            $existingStmt = $conn->prepare('SELECT id, category, sort_order FROM projects WHERE directory = ? LIMIT 1');
            $existingStmt->bind_param('s', $directory);
            $existingStmt->execute();
            $existingProject = $existingStmt->get_result()->fetch_assoc();
            $existingStmt->close();

            try {
                if ($existingProject) {
                    $sortOrder = (int)($existingProject['sort_order'] ?? 0);
                    if ($sortOrder <= 0 || (string)$existingProject['category'] !== $category) {
                        $sortOrder = next_sort_order_for_details($conn, $category);
                    }
                    $projectId = (int)$existingProject['id'];
                    $update = $conn->prepare(
                        'UPDATE projects SET project_name = ?, version = ?, description = ?, category = ?, sort_order = ?, is_branch = ?, parent_project_id = ? WHERE id = ?'
                    );
                    $update->bind_param('ssssiiii', $project_name, $version, $description, $category, $sortOrder, $is_branch, $parent_project_id, $projectId);
                    $update->execute();
                    $update->close();
                } else {
                    $sortOrder = next_sort_order_for_details($conn, $category);
                    $insert = $conn->prepare(
                        'INSERT INTO projects (project_name, version, description, directory, category, sort_order, is_branch, parent_project_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $insert->bind_param('sssssiii', $project_name, $version, $description, $directory, $category, $sortOrder, $is_branch, $parent_project_id);
                    $insert->execute();
                    $insert->close();
                }

                $message = 'Project saved successfully.';
                if ($download_project_info) {
                    $success = $message . ' Your new project_info.json download should begin automatically.';
                    $autoDownload = true;
                    $downloadUrl = 'download_project_info.php?directory=' . urlencode($directory);
                } else {
                    header('Location: index.php?message=' . urlencode($message));
                    exit;
                }
            } catch (mysqli_sql_exception $e) {
                $error = 'Database error while saving the uploaded project.';
            }
        }
    }
}

require_once __DIR__ . '/header.php';
?>

<div class="card">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
    <div>
      <h2 style="margin:0 0 6px 0;">Upload Details</h2>
      <div class="muted">Review the uploaded project, save its details, and optionally download a fresh project_info.json file.</div>
    </div>
    <span class="pill"><?php echo htmlspecialchars($directory); ?></span>
  </div>

  <?php if (!empty($prefill['info_error'])): ?>
    <div class="alert" style="margin-top:12px;"><b>Note:</b> <?php echo htmlspecialchars($prefill['info_error']); ?></div>
  <?php endif; ?>

  <?php if ($error !== ''): ?>
    <div class="alert" style="margin-top:12px;"><b>Error:</b> <?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <?php if ($success !== ''): ?>
    <div class="pill" style="margin-top:12px;"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>
</div>

<div class="row" style="align-items:start;">
  <div class="card" style="flex:1.2;">
    <h2>Project Details</h2>

    <form method="post" style="margin-top:12px;">
      <input type="hidden" name="directory" value="<?php echo htmlspecialchars($directory); ?>">

      <div class="row">
        <div>
          <label class="muted">Project Name</label>
          <input name="project_name" value="<?php echo htmlspecialchars($prefill['project_name'] ?? ''); ?>" required>
        </div>
        <div>
          <label class="muted">Version</label>
          <input name="version" value="<?php echo htmlspecialchars($prefill['version'] ?? ''); ?>" placeholder="Example: 1.0.0">
        </div>
      </div>

      <div style="margin-top:12px;">
        <label class="muted">Category</label>
        <select name="category">
          <?php foreach ($categories as $c): ?>
            <option value="<?php echo htmlspecialchars($c); ?>" <?php echo (($prefill['category'] ?? '') === $c) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="margin-top:12px;">
        <label class="muted">Description</label>
        <textarea name="description"><?php echo htmlspecialchars($prefill['description'] ?? ''); ?></textarea>
      </div>

      <div style="margin-top:12px;padding:12px;border-radius:14px;background:#0d1624;border:1px solid #24324a;">
        <label class="muted" style="display:flex;align-items:center;gap:8px;font-size:14px;">
          <input type="checkbox" name="is_branch" value="1" style="width:auto;" <?php echo !empty($prefill['is_branch']) ? 'checked' : ''; ?>>
          This upload is a branch of another project
        </label>

        <div id="branch-parent-wrap" style="margin-top:12px;<?php echo empty($prefill['is_branch']) ? 'display:none;' : ''; ?>">
          <label class="muted">Parent Project</label>
          <select name="parent_project_id">
            <option value="0">-- Select parent project --</option>
            <?php foreach ($parentProjects as $parent): ?>
              <option value="<?php echo (int)$parent['id']; ?>" <?php echo ((int)($prefill['parent_project_id'] ?? 0) === (int)$parent['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($parent['project_name']); ?></option>
            <?php endforeach; ?>
          </select>
          <div class="muted" style="margin-top:6px;">For now, branch projects still save as normal menu entries and can be fully linked into a branch selector later.</div>
        </div>
      </div>

      <div style="margin-top:12px;">
        <label class="muted" style="display:flex;align-items:center;gap:8px;font-size:14px;">
          <input type="checkbox" name="download_project_info" value="1" style="width:auto;" <?php echo !empty($prefill['download_project_info']) ? 'checked' : ''; ?>>
          Download project_info.json file after saving
        </label>
      </div>

      <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;">
        <button class="btn btn-primary" type="submit">Save</button>
        <a class="btn" href="index.php">Cancel</a>
      </div>
    </form>
  </div>

  <div class="card" style="flex:0.8;">
    <h2>Database Name Warning</h2>
    <div class="alert">
      <b>Warning!</b> Make sure your uploaded project does not try to use one of the databases listed below unless that is intentional.<br><br>
      Names beginning with <span class="dircode">ao_</span> should be avoided for normal user projects because that prefix is reserved for official add-on module planning.
    </div>

    <div style="margin-top:12px;display:grid;gap:8px;max-height:320px;overflow:auto;">
      <?php foreach ($existingDatabases as $dbName): ?>
        <div class="stat-pill">
          <strong><?php echo htmlspecialchars($dbName); ?></strong>
          <span>Existing database</span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php if ($autoDownload && $downloadUrl !== ''): ?>
<script>
window.addEventListener('load', function () {
  var hiddenLink = document.createElement('a');
  hiddenLink.href = <?php echo json_encode($downloadUrl); ?>;
  hiddenLink.style.display = 'none';
  hiddenLink.setAttribute('download', 'project_info.json');
  document.body.appendChild(hiddenLink);
  hiddenLink.click();
});
</script>
<?php endif; ?>

<script>
(function () {
  var checkbox = document.querySelector('input[name="is_branch"]');
  var parentWrap = document.getElementById('branch-parent-wrap');
  if (!checkbox || !parentWrap) return;

  checkbox.addEventListener('change', function () {
    parentWrap.style.display = checkbox.checked ? '' : 'none';
  });
})();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
