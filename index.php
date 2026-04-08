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

function category_toggle_name(string $category): string {
    return match ($category) {
        "In-Progress" => "show_actions_in_progress",
        "Development" => "show_actions_development",
        "Finished"    => "show_actions_finished",
        default       => "show_actions_unknown",
    };
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

$showActions = [
    "In-Progress" => isset($_GET["show_actions_in_progress"]),
    "Development" => isset($_GET["show_actions_development"]),
    "Finished"    => isset($_GET["show_actions_finished"]),
];

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

$categories = ["In-Progress", "Development", "Finished"];
$projectsByCat = [];
foreach ($categories as $c) {
    $projectsByCat[$c] = [];
}

$stmt = $conn->prepare("
    SELECT id, project_name, version, description, directory, category, sort_order
    FROM projects
    WHERE is_active = 1
    ORDER BY FIELD(category, 'In-Progress', 'Development', 'Finished'), sort_order, project_name, id
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
          <span class="muted small reorder-status js-reorder-status">Locked</span>
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
            <th style="width:54px;">Order</th>
            <th>Project</th>
            <th>Directory</th>
            <th style="width:160px;">Actions</th>
          </tr>
        </thead>
        <tbody class="js-sortable-body">
          <?php foreach ($projectsByCat[$cat] as $p): ?>
            <tr class="sortable-row" data-project-id="<?php echo (int)$p['id']; ?>" draggable="false">
              <td>
                <span class="drag-handle" title="Drag to reorder">↕</span>
              </td>
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
})();
</script>

<?php require_once __DIR__ . "/footer.php"; ?>
