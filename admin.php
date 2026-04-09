<?php
require_once __DIR__ . "/bootstrap.php";

function admin_redirect(string $message, string $type = 'success'): void {
    header('Location: admin.php?message=' . urlencode($message) . '&message_type=' . urlencode($type));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'reorder_categories') {
        $ids = $_POST['category_ids'] ?? [];
        if (!is_array($ids) || $ids === []) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'No category order received.']);
            exit;
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE project_categories SET sort_order = ? WHERE id = ?");
            $position = 1;
            foreach ($ids as $rawId) {
                $id = (int)$rawId;
                if ($id <= 0) {
                    continue;
                }
                $stmt->bind_param('ii', $position, $id);
                $stmt->execute();
                $position++;
            }
            $conn->commit();
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
        } catch (Throwable $e) {
            $conn->rollback();
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Could not save category order.']);
        }
        exit;
    }

    if ($action === 'add_category') {
        $categoryName = trim((string)($_POST['category_name'] ?? ''));
        if ($categoryName === '') {
            admin_redirect('Category name is required.', 'error');
        }

        $maxRes = $conn->query("SELECT COALESCE(MAX(sort_order), 0) AS max_sort FROM project_categories");
        $maxRow = $maxRes instanceof mysqli_result ? $maxRes->fetch_assoc() : null;
        $sortOrder = (int)($maxRow['max_sort'] ?? 0) + 1;

        $insert = $conn->prepare("INSERT INTO project_categories (category_name, sort_order, is_active, is_default) VALUES (?, ?, 1, 0)");

        try {
            $insert->bind_param('si', $categoryName, $sortOrder);
            $insert->execute();
            admin_redirect('Category added.');
        } catch (mysqli_sql_exception $e) {
            admin_redirect('That category already exists.', 'error');
        }
    }

    if ($action === 'save_category') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['category_name'] ?? ''));
        $sortOrder = max(1, (int)($_POST['sort_order'] ?? 1));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($id <= 0) {
            admin_redirect('Invalid category selected.', 'error');
        }
        if ($name === '') {
            admin_redirect('Category name is required.', 'error');
        }

        $currentStmt = $conn->prepare("SELECT id, category_name, is_default FROM project_categories WHERE id = ? LIMIT 1");
        $currentStmt->bind_param('i', $id);
        $currentStmt->execute();
        $current = $currentStmt->get_result()->fetch_assoc();

        if (!$current) {
            admin_redirect('Category not found.', 'error');
        }

        if ((int)$current['is_default'] === 1) {
            $name = (string)$current['category_name'];
            $isActive = 1;
        }

        $conn->begin_transaction();
        try {
            $update = $conn->prepare("UPDATE project_categories SET category_name = ?, sort_order = ?, is_active = ? WHERE id = ?");
            $update->bind_param('siii', $name, $sortOrder, $isActive, $id);
            $update->execute();

            if ($name !== $current['category_name']) {
                $projectUpdate = $conn->prepare("UPDATE projects SET category = ? WHERE category = ?");
                $projectUpdate->bind_param('ss', $name, $current['category_name']);
                $projectUpdate->execute();
            }

            $conn->commit();
            admin_redirect('Category saved.');
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            admin_redirect('Could not save category. Make sure the name is unique.', 'error');
        }
    }
}

$adminMessage = trim((string)($_GET['message'] ?? ''));
$adminMessageType = trim((string)($_GET['message_type'] ?? 'success'));
if ($adminMessageType !== 'error') {
    $adminMessageType = 'success';
}

$categories = [];
$categoriesRes = $conn->query(
    "SELECT pc.*, (SELECT COUNT(*) FROM projects p WHERE p.category = pc.category_name) AS project_count
     FROM project_categories pc
     ORDER BY pc.sort_order, pc.category_name"
);
if ($categoriesRes instanceof mysqli_result) {
    while ($row = $categoriesRes->fetch_assoc()) {
        $categories[] = $row;
    }
}

require_once __DIR__ . "/header.php";
?>

<?php if ($adminMessage !== ''): ?>
<div class="card">
  <div class="<?php echo $adminMessageType === 'error' ? 'alert' : 'pill'; ?>">
    <?php echo htmlspecialchars($adminMessage); ?>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="category-head">
    <div>
      <h2 style="margin-bottom:6px;">Admin</h2>
      <div class="muted">Administrative tools and maintenance actions for Project Menu.</div>
    </div>
    <span class="pill">Use with care</span>
  </div>
</div>

<div class="row" style="align-items:start;">
  <div class="card" style="flex:1;">
    <h2>Maintenance</h2>
    <p class="muted" style="font-size:14px;line-height:1.7;">
      These tools are grouped here so the main menu stays clean while still keeping important maintenance actions easy to reach.
    </p>
    <div style="display:grid;gap:12px;margin-top:14px;">
      <a class="btn btn-primary" href="install_schema.php">Install / Repair Schema</a>
      <div class="muted small">Run this after schema-related updates or when repairing the menu database structure.</div>

      <a class="btn" href="debug_scan.php">Debug Scan</a>
      <div class="muted small">Shows the raw directory scan results and ignored folder data to help troubleshoot auto-detection.</div>
    </div>
  </div>

  <div class="card" style="flex:1;">
    <h2>Project Categories</h2>
    <p class="muted" style="font-size:14px;line-height:1.7;">
      Categories are now stored in the database. <span class="dircode">In-Progress</span>, <span class="dircode">Development</span>, and <span class="dircode">Finished</span>
      are created automatically as default categories, and you can add your own custom categories here.
    </p>

    <form method="post" style="margin-top:14px;display:grid;gap:12px;">
      <input type="hidden" name="action" value="add_category">
      <div>
        <label class="muted">New Category Name</label>
        <input type="text" name="category_name" placeholder="Example: Archived">
      </div>
      <div>
        <button class="btn btn-primary" type="submit">Add Category</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <h2>Manage Categories</h2>
  <p class="muted" style="font-size:14px;line-height:1.7;">
    Default categories stay active and keep their original names. Custom categories can be renamed, reordered, or deactivated here.
  </p>

  <table>
    <thead>
      <tr>
        <th style="width:52px;">Move</th>
        <th>Category</th>
        <th style="width:90px;">Order</th>
        <th style="width:110px;">Active</th>
        <th style="width:100px;">Projects</th>
        <th style="width:110px;">Type</th>
        <th style="width:90px;">Save</th>
      </tr>
    </thead>
    <tbody id="category-sort-body">
      <?php foreach ($categories as $category): ?>
        <tr class="category-sort-row" data-category-id="<?php echo (int)$category['id']; ?>" draggable="true">
          <form method="post">
            <input type="hidden" name="action" value="save_category">
            <input type="hidden" name="id" value="<?php echo (int)$category['id']; ?>">
            <td style="text-align:center;">
              <span class="drag-handle" title="Drag to reorder categories">↕</span>
            </td>
            <td>
              <input type="text"
                     name="category_name"
                     value="<?php echo htmlspecialchars($category['category_name']); ?>"
                     <?php echo (int)$category['is_default'] === 1 ? 'readonly' : ''; ?>>
            </td>
            <td>
              <span class="pill js-category-order"><?php echo (int)$category['sort_order']; ?></span>
              <input type="hidden" name="sort_order" value="<?php echo (int)$category['sort_order']; ?>">
            </td>
            <td>
              <label class="muted" style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox"
                       name="is_active"
                       value="1"
                       style="width:auto;"
                       <?php echo (int)$category['is_active'] === 1 ? 'checked' : ''; ?>
                       <?php echo (int)$category['is_default'] === 1 ? 'disabled' : ''; ?>>
                <?php echo (int)$category['is_active'] === 1 ? 'Yes' : 'No'; ?>
              </label>
            </td>
            <td><span class="pill"><?php echo (int)$category['project_count']; ?></span></td>
            <td><span class="pill"><?php echo (int)$category['is_default'] === 1 ? 'Default' : 'Custom'; ?></span></td>
            <td><button class="btn" type="submit">Save</button></td>
          </form>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="muted small" style="margin-top:12px;">Drag rows to reorder categories. The Order numbers update automatically after each drop.</div>
</div>

<script>
(function () {
  const tbody = document.getElementById('category-sort-body');
  if (!tbody) {
    return;
  }

  let draggedRow = null;

  function refreshOrderNumbers() {
    const rows = Array.from(tbody.querySelectorAll('.category-sort-row'));
    rows.forEach(function (row, index) {
      const order = index + 1;
      const badge = row.querySelector('.js-category-order');
      const input = row.querySelector('input[name="sort_order"]');
      if (badge) {
        badge.textContent = String(order);
      }
      if (input) {
        input.value = String(order);
      }
    });
  }

  function persistOrder() {
    const ids = Array.from(tbody.querySelectorAll('.category-sort-row')).map(function (row) {
      return row.getAttribute('data-category-id');
    });

    const body = new URLSearchParams();
    body.append('action', 'reorder_categories');
    ids.forEach(function (id) {
      body.append('category_ids[]', id);
    });

    fetch('admin.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    }).then(function (response) {
      if (!response.ok) {
        throw new Error('Request failed');
      }
      return response.json();
    }).then(function (data) {
      if (!data || !data.ok) {
        throw new Error(data && data.message ? data.message : 'Could not save category order.');
      }
    }).catch(function () {
      window.location.href = 'admin.php?message=' + encodeURIComponent('Could not save category order.') + '&message_type=error';
    });
  }

  tbody.addEventListener('dragstart', function (event) {
    const row = event.target.closest('.category-sort-row');
    if (!row) {
      return;
    }
    draggedRow = row;
    row.classList.add('dragging');
    if (event.dataTransfer) {
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', row.getAttribute('data-category-id') || '');
    }
  });

  tbody.addEventListener('dragend', function () {
    if (draggedRow) {
      draggedRow.classList.remove('dragging');
    }
    draggedRow = null;
  });

  tbody.addEventListener('dragover', function (event) {
    if (!draggedRow) {
      return;
    }
    event.preventDefault();
    const targetRow = event.target.closest('.category-sort-row');
    if (!targetRow || targetRow === draggedRow) {
      return;
    }
    const rect = targetRow.getBoundingClientRect();
    const after = event.clientY > rect.top + (rect.height / 2);
    if (after) {
      targetRow.parentNode.insertBefore(draggedRow, targetRow.nextSibling);
    } else {
      targetRow.parentNode.insertBefore(draggedRow, targetRow);
    }
  });

  tbody.addEventListener('drop', function (event) {
    if (!draggedRow) {
      return;
    }
    event.preventDefault();
    refreshOrderNumbers();
    persistOrder();
  });
})();
</script>

<?php require_once __DIR__ . "/footer.php"; ?>
