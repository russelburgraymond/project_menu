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


function next_sort_order(mysqli $conn, string $category): int {
    $stmt = $conn->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort FROM projects WHERE category = ?");
    $stmt->bind_param("s", $category);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return (int)($row['next_sort'] ?? 1);
}

function auto_import_projects(mysqli $conn, array $diskDirs, array $dbDirs): array {
    $stillUntracked = [];

    foreach ($diskDirs as $dirName) {
        if (isset($dbDirs[$dirName])) {
            continue;
        }

        $info = read_project_info_file($dirName);

        if ($info !== null) {
            $sortOrder = next_sort_order($conn, $info['category']);
            $stmt = $conn->prepare("
                INSERT INTO projects (project_name, version, description, directory, category, sort_order)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "sssssi",
                $info["project_name"],
                $info["version"],
                $info["description"],
                $dirName,
                $info["category"],
                $sortOrder
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


$diskDirs = scan_laragon_www_dirs();

$dbDirs = [];
$res = $conn->query("SELECT id, directory FROM projects");
while ($row = $res->fetch_assoc()) {
    $dbDirs[$row["directory"]] = (int)$row["id"];
}

[$dbDirs, $untracked] = auto_import_projects($conn, $diskDirs, $dbDirs);

$missingOnDisk = [];
foreach ($dbDirs as $dirName => $id) {
    if (!in_array($dirName, $diskDirs, true)) {
        $missingOnDisk[] = $dirName;
    }
}
$missingOnDiskLookup = array_fill_keys($missingOnDisk, true);

$categories = pm_get_categories($conn, false, true);
$projectsByCat = [];

$actionMessage = trim((string)($_GET["message"] ?? ""));
$actionMessageType = trim((string)($_GET["message_type"] ?? "success"));
if ($actionMessageType !== "error") {
    $actionMessageType = "success";
}
foreach ($categories as $c) {
    $projectsByCat[$c] = [];
}

$stmt = $conn->prepare("
    SELECT
        p.id,
        p.project_name,
        p.version,
        p.description,
        p.directory,
        p.category,
        p.sort_order,
        p.is_branch,
        p.parent_project_id,
        parent.project_name AS parent_project_name,
        (
            SELECT COUNT(*)
            FROM projects child
            WHERE child.parent_project_id = p.id
              AND child.is_branch = 1
              AND child.is_active = 1
        ) AS branch_count
    FROM projects p
    LEFT JOIN projects parent ON parent.id = p.parent_project_id
    WHERE p.is_active = 1
    ORDER BY p.category, p.sort_order, p.project_name, p.id
");
$stmt->execute();
$result = $stmt->get_result();

$branchesByParent = [];

while ($p = $result->fetch_assoc()) {
    if (!empty($p['is_branch']) && !empty($p['parent_project_id'])) {
        $branchesByParent[(int)$p['parent_project_id']][] = [
            'id' => (int)$p['id'],
            'project_name' => (string)$p['project_name'],
            'version' => (string)($p['version'] ?? ''),
            'description' => (string)($p['description'] ?? ''),
            'directory' => (string)$p['directory'],
            'is_missing_on_disk' => isset($missingOnDiskLookup[$p['directory']]),
        ];
        continue;
    }

    $categoryName = (string)($p["category"] ?? "");
    if (!array_key_exists($categoryName, $projectsByCat)) {
        $projectsByCat[$categoryName] = [];
        $categories[] = $categoryName;
    }
    $projectsByCat[$categoryName][] = $p;
}
?>

<?php if ($actionMessage !== ""): ?>
<div class="card">
  <div class="<?php echo $actionMessageType === 'error' ? 'alert' : 'pill'; ?>">
    <?php echo htmlspecialchars($actionMessage); ?>
  </div>
</div>
<?php endif; ?>

<?php if (count($untracked) > 0): ?>
<div class="card">
  <h2>Scan Results</h2>

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

<style>
  .project-row-missing-on-disk {
    background:#4d1f1f;
  }
  .project-row-missing-on-disk:hover {
    background:#5d2626;
  }
  .project-row-missing-on-disk td {
    border-top-color:#8a3a3a;
  }
  .missing-pill {
    display:inline-block;
    margin-left:8px;
    padding:2px 8px;
    border-radius:999px;
    font-size:11px;
    font-weight:700;
    letter-spacing:.02em;
    color:#ffd3d3;
    background:#6a2323;
    border:1px solid #9f4a4a;
    vertical-align:middle;
  }
  .branch-modal-backdrop {
    position:fixed;
    inset:0;
    background:rgba(0, 0, 0, 0.65);
    display:none;
    align-items:center;
    justify-content:center;
    z-index:9999;
    padding:24px;
  }
  .branch-modal-backdrop.is-open {
    display:flex;
  }
  .branch-modal {
    width:min(520px, 100%);
    background:#0d1624;
    border:1px solid #24324a;
    border-radius:18px;
    box-shadow:0 20px 60px rgba(0,0,0,.45);
    padding:18px;
  }
  .branch-modal-title {
    margin:0 0 6px 0;
  }
  .branch-modal-list {
    display:grid;
    gap:10px;
    margin-top:14px;
  }
  .branch-launch-link {
    display:block;
    text-decoration:none;
    color:inherit;
    background:#111c2d;
    border:1px solid #273751;
    border-radius:14px;
    padding:12px 14px;
    transition:background .15s ease, border-color .15s ease;
  }
  .branch-launch-link:hover {
    background:#15243a;
    border-color:#3b5377;
  }
  .branch-launch-name {
    display:block;
    font-weight:700;
    margin-bottom:4px;
  }
  .branch-launch-meta {
    display:block;
    color:#9db0c9;
    font-size:13px;
  }
  .project-row-has-branches {
    cursor:pointer;
  }
</style>

<?php foreach ($categories as $cat): ?>
  <?php if (count($projectsByCat[$cat]) > 0): ?>
    <div class="card category-card is-locked" data-category="<?php echo htmlspecialchars($cat); ?>">
      <div class="category-head">
        <div class="category-title-wrap">
          <h2 style="margin:0;"><?php echo htmlspecialchars($cat); ?></h2>
          <button type="button"
                  class="icon-btn js-toggle-reorder"
                  data-locked-icon="🔒"
                  data-unlocked-icon="🔓"
                  aria-label="Unlock project reordering for <?php echo htmlspecialchars($cat); ?>"
                  title="Unlock project reordering for <?php echo htmlspecialchars($cat); ?>">🔒</button>
          <span class="pill"><?php echo count($projectsByCat[$cat]); ?> projects</span>
        </div>

      </div>

      <table>
        <thead>
          <tr>
            <th class="reorder-column" style="width:54px;">Order</th>
            <th>Project</th>
            <th class="actions-column" style="width:160px;">Actions</th>
          </tr>
        </thead>
        <tbody class="js-sortable-body">
          <?php foreach ($projectsByCat[$cat] as $p): ?>
            <?php
              $isMissingOnDisk = isset($missingOnDiskLookup[$p['directory']]);
              $launchUrl = $isMissingOnDisk ? '' : ($PROJECTS_WEB_BASE . '/' . rawurlencode($p['directory']) . '/');
              $branchOptions = [];
              $mainVersion = trim((string)($p['version'] ?? ''));
              $branchOptions[] = [
                'label' => (string)$p['project_name'],
                'meta' => $mainVersion !== '' ? 'Main project • v' . $mainVersion : 'Main project',
                'url' => $launchUrl,
                'missing' => $isMissingOnDisk,
              ];
              foreach ($branchesByParent[(int)$p['id']] ?? [] as $branchProject) {
                $branchLaunchUrl = $branchProject['is_missing_on_disk'] ? '' : ($PROJECTS_WEB_BASE . '/' . rawurlencode($branchProject['directory']) . '/');
                $branchVersion = trim((string)($branchProject['version'] ?? ''));
                $branchOptions[] = [
                  'label' => (string)$branchProject['project_name'],
                  'meta' => $branchVersion !== '' ? 'Branch • v' . $branchVersion : 'Branch',
                  'url' => $branchLaunchUrl,
                  'missing' => !empty($branchProject['is_missing_on_disk']),
                ];
              }
              $hasBranches = count($branchOptions) > 1;
            ?>
            <tr class="sortable-row<?php echo $isMissingOnDisk ? ' project-row-missing-on-disk' : ''; ?><?php echo $hasBranches ? ' project-row-has-branches' : ''; ?>"
                data-project-id="<?php echo (int)$p['id']; ?>"
                data-project-name="<?php echo htmlspecialchars($p["project_name"], ENT_QUOTES); ?>"
                data-launch-url="<?php echo htmlspecialchars($launchUrl); ?>"
                data-has-branches="<?php echo $hasBranches ? '1' : '0'; ?>"
                data-branch-options="<?php echo htmlspecialchars(json_encode($branchOptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES); ?>"
                draggable="false">
              <td class="drag-cell">
                <span class="drag-handle" title="Drag to reorder">↕</span>
              </td>
              <td>
                <b><?php echo htmlspecialchars($p["project_name"]); ?></b>

                <?php if (!empty(trim((string)$p["version"]))): ?>
                  <span class="version-badge <?php echo version_badge_class($p["version"]); ?>">
                    v<?php echo htmlspecialchars($p["version"]); ?>
                  </span>
                <?php endif; ?>

                <?php if ((int)($p['branch_count'] ?? 0) > 0): ?>
                  <span class="pill" style="margin-left:8px;">Branches: <?php echo (int)$p['branch_count']; ?></span>
                <?php endif; ?>

                <?php if ($isMissingOnDisk): ?>
                  <span class="missing-pill">Missing on disk</span>
                <?php endif; ?>

                <br>
                <span class="muted"><?php echo nl2br(htmlspecialchars($p["description"] ?? "")); ?></span>
              </td>

              <td class="actions actions-cell">
                <a class="action-link edit"
                   href="project_edit.php?id=<?php echo (int)$p["id"]; ?>"
                   title="Edit project"
                   aria-label="Edit project">✏️</a>
                <?php if ($isMissingOnDisk): ?>
                  <a class="action-link delete js-delete-missing-project"
                     href="delete_missing_project.php?id=<?php echo (int)$p["id"]; ?>"
                     data-project-id="<?php echo (int)$p["id"]; ?>"
                     data-project-name="<?php echo htmlspecialchars($p["project_name"], ENT_QUOTES); ?>"
                     title="Delete missing project entry"
                     aria-label="Delete missing project entry">🗑️</a>
                <?php else: ?>
                  <a class="action-link delete js-delete-project"
                     href="project_delete.php?id=<?php echo (int)$p["id"]; ?>"
                     data-project-id="<?php echo (int)$p["id"]; ?>"
                     data-project-name="<?php echo htmlspecialchars($p["project_name"], ENT_QUOTES); ?>"
                     title="Delete project"
                     aria-label="Delete project">🗑️</a>
                  <a class="action-link update"
                     href="update_project.php?id=<?php echo (int)$p["id"]; ?>"
                     title="Update from project_info.json"
                     aria-label="Update from project_info.json"
                     onclick="return confirm('Update this project from its project_info.json file?');">🔄</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
<?php endforeach; ?>

<div id="branch-modal-backdrop" class="branch-modal-backdrop" aria-hidden="true">
  <div class="branch-modal" role="dialog" aria-modal="true" aria-labelledby="branch-modal-title">
    <div style="display:flex;align-items:start;justify-content:space-between;gap:12px;">
      <div>
        <h2 id="branch-modal-title" class="branch-modal-title">Choose a branch</h2>
        <div id="branch-modal-subtitle" class="muted">Select which version of this project you want to open.</div>
      </div>
      <button type="button" id="branch-modal-close" class="icon-btn" aria-label="Close branch selector" title="Close">✕</button>
    </div>
    <div id="branch-modal-list" class="branch-modal-list"></div>
  </div>
</div>

<script>
(function () {
  function enableRows(card, enabled) {
    const rows = card.querySelectorAll('.sortable-row');
    rows.forEach(function (row) {
      row.draggable = enabled;
    });
  }

  function setStatus(card, message, isError) {
    const status = card.querySelector('.js-reorder-status');
    if (!status) return;
    status.textContent = message;
    status.style.color = isError ? '#ffb4b4' : '';
  }

  function saveOrder(card) {
    const category = card.getAttribute('data-category');
    const order = Array.from(card.querySelectorAll('.sortable-row')).map(function (row) {
      return parseInt(row.getAttribute('data-project-id'), 10);
    });

    setStatus(card, 'Saving order...', false);

    fetch('reorder_projects.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        category: category,
        order: order
      })
    })
    .then(function (response) {
      return response.json().then(function (data) {
        if (!response.ok || !data.ok) {
          throw new Error((data && data.message) ? data.message : 'Unable to save order.');
        }
        return data;
      });
    })
    .then(function () {
      setStatus(card, 'Order saved', false);
    })
    .catch(function (error) {
      setStatus(card, error.message || 'Unable to save order.', true);
    });
  }

  document.querySelectorAll('.category-card').forEach(function (card) {
    const button = card.querySelector('.js-toggle-reorder');
    const tbody = card.querySelector('.js-sortable-body');
    let draggedRow = null;

    enableRows(card, false);

    if (button) {
      button.addEventListener('click', function () {
        const unlocking = card.classList.contains('is-locked');
        card.classList.toggle('is-locked', !unlocking);
        card.classList.toggle('is-unlocked', unlocking);
        button.classList.toggle('is-unlocked', unlocking);
        button.textContent = unlocking ? button.getAttribute('data-unlocked-icon') : button.getAttribute('data-locked-icon');
        button.setAttribute('title', unlocking ? 'Lock project reordering for ' + card.getAttribute('data-category') : 'Unlock project reordering for ' + card.getAttribute('data-category'));
        button.setAttribute('aria-label', button.getAttribute('title'));
        enableRows(card, unlocking);
        setStatus(card, unlocking ? 'Unlocked — drag projects to reorder' : 'Locked', false);
      });
    }

    if (!tbody) {
      return;
    }

    tbody.addEventListener('click', function (event) {
      const row = event.target.closest('.sortable-row');
      if (!row) {
        return;
      }

      if (event.target.closest('a, button, input, select, textarea, label, .drag-handle, .actions-cell')) {
        return;
      }

      if (row.getAttribute('data-has-branches') === '1') {
        openBranchModal(row);
        return;
      }

      const launchUrl = row.getAttribute('data-launch-url');
      if (launchUrl) {
        window.location.href = launchUrl;
      }
    });

    tbody.addEventListener('dragstart', function (event) {
      const row = event.target.closest('.sortable-row');
      if (!row || card.classList.contains('is-locked')) {
        event.preventDefault();
        return;
      }
      draggedRow = row;
      row.classList.add('dragging');
      if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
      }
    });

    tbody.addEventListener('dragend', function () {
      if (draggedRow) {
        draggedRow.classList.remove('dragging');
      }
      draggedRow = null;
    });

    tbody.addEventListener('dragover', function (event) {
      if (!draggedRow || card.classList.contains('is-locked')) {
        return;
      }
      event.preventDefault();
      const targetRow = event.target.closest('.sortable-row');
      if (!targetRow || targetRow === draggedRow) {
        return;
      }
      const bounds = targetRow.getBoundingClientRect();
      const shouldInsertAfter = event.clientY > bounds.top + (bounds.height / 2);
      if (shouldInsertAfter) {
        targetRow.parentNode.insertBefore(draggedRow, targetRow.nextSibling);
      } else {
        targetRow.parentNode.insertBefore(draggedRow, targetRow);
      }
    });

    tbody.addEventListener('drop', function (event) {
      if (!draggedRow || card.classList.contains('is-locked')) {
        return;
      }
      event.preventDefault();
      saveOrder(card);
    });
  });

  const branchModalBackdrop = document.getElementById('branch-modal-backdrop');
  const branchModalTitle = document.getElementById('branch-modal-title');
  const branchModalSubtitle = document.getElementById('branch-modal-subtitle');
  const branchModalList = document.getElementById('branch-modal-list');
  const branchModalClose = document.getElementById('branch-modal-close');

  function closeBranchModal() {
    if (!branchModalBackdrop) {
      return;
    }
    branchModalBackdrop.classList.remove('is-open');
    branchModalBackdrop.setAttribute('aria-hidden', 'true');
    branchModalList.innerHTML = '';
  }

  function openBranchModal(row) {
    if (!branchModalBackdrop || !branchModalList) {
      return;
    }

    let options = [];
    try {
      options = JSON.parse(row.getAttribute('data-branch-options') || '[]');
    } catch (error) {
      options = [];
    }

    branchModalTitle.textContent = row.getAttribute('data-project-name') || 'Choose a branch';
    branchModalSubtitle.textContent = 'Select which version of this project you want to open.';
    branchModalList.innerHTML = '';

    options.forEach(function (option) {
      const item = document.createElement(option.url ? 'a' : 'div');
      item.className = 'branch-launch-link';
      if (option.url) {
        item.href = option.url;
      }

      const name = document.createElement('span');
      name.className = 'branch-launch-name';
      name.textContent = option.label || 'Project';
      item.appendChild(name);

      const meta = document.createElement('span');
      meta.className = 'branch-launch-meta';
      meta.textContent = option.missing ? ((option.meta || 'Project') + ' • Missing on disk') : (option.meta || 'Project');
      item.appendChild(meta);

      if (!option.url) {
        item.style.opacity = '0.7';
        item.style.cursor = 'default';
      }

      branchModalList.appendChild(item);
    });

    branchModalBackdrop.classList.add('is-open');
    branchModalBackdrop.setAttribute('aria-hidden', 'false');
  }

  if (branchModalBackdrop) {
    branchModalBackdrop.addEventListener('click', function (event) {
      if (event.target === branchModalBackdrop) {
        closeBranchModal();
      }
    });
  }

  if (branchModalClose) {
    branchModalClose.addEventListener('click', closeBranchModal);
  }

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeBranchModal();
    }
  });

  document.querySelectorAll('.js-delete-project').forEach(function (link) {
    link.addEventListener('click', function (event) {
      event.preventDefault();

      const projectId = this.getAttribute('data-project-id');
      const projectName = this.getAttribute('data-project-name') || '';
      const warning = 'Warning!!! This will delete the database entry AND the project directory and CANNOT be undone!\n\nType the project name to continue:\n' + projectName;
      const typedName = window.prompt(warning, '');

      if (typedName === null) {
        return;
      }

      if (typedName !== projectName) {
        window.alert('Project name did not match. Delete cancelled.');
        return;
      }

      const form = document.createElement('form');
      form.method = 'post';
      form.action = 'project_delete.php';

      const idInput = document.createElement('input');
      idInput.type = 'hidden';
      idInput.name = 'id';
      idInput.value = projectId;
      form.appendChild(idInput);

      const nameInput = document.createElement('input');
      nameInput.type = 'hidden';
      nameInput.name = 'confirm_name';
      nameInput.value = typedName;
      form.appendChild(nameInput);

      document.body.appendChild(form);
      form.submit();
    });
  });

  document.querySelectorAll('.js-delete-missing-project').forEach(function (link) {
    link.addEventListener('click', function (event) {
      event.preventDefault();

      const projectId = this.getAttribute('data-project-id');
      const projectName = this.getAttribute('data-project-name') || '';
      const confirmed = window.confirm('Delete the missing project entry for "' + projectName + '" from the database?');

      if (!confirmed) {
        return;
      }

      const form = document.createElement('form');
      form.method = 'post';
      form.action = 'delete_missing_project.php';

      const idInput = document.createElement('input');
      idInput.type = 'hidden';
      idInput.name = 'id';
      idInput.value = projectId;
      form.appendChild(idInput);

      document.body.appendChild(form);
      form.submit();
    });
  });
})();
</script>

<?php require_once __DIR__ . "/footer.php"; ?>
