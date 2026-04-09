<?php
require_once __DIR__ . "/bootstrap.php";
require_once __DIR__ . "/header.php";

$versions = [];
$versionStmt = $conn->query("
    SELECT version, MAX(release_date) AS release_date, COUNT(*) AS entry_count
    FROM changelog_entries
    GROUP BY version
    ORDER BY
        CASE WHEN version = 'Unreleased' THEN 0 ELSE 1 END,
        COALESCE(MAX(release_date), '1000-01-01') DESC,
        version DESC
");
while ($row = $versionStmt->fetch_assoc()) {
    $versions[] = $row;
}

$changelogFile = __DIR__ . '/CHANGELOG.md';
$unreleasedRangeText = '';
if (is_file($changelogFile)) {
    $rawChangelog = file_get_contents($changelogFile);
    if ($rawChangelog !== false && preg_match('/^Range:\s*(v?[^\r\n]+)$/mi', $rawChangelog, $m)) {
        $unreleasedRangeText = trim($m[1]);
    }
}

$selectedVersion = trim((string)($_GET['version'] ?? ''));
if ($selectedVersion === '' && $versions !== []) {
    $selectedVersion = (string)$versions[0]['version'];
}

$selectedMeta = null;
foreach ($versions as &$versionRow) {
    if ((string)$versionRow['version'] === 'Unreleased' && $unreleasedRangeText !== '') {
        $versionRow['display_version'] = $unreleasedRangeText;
    } else {
        $versionRow['display_version'] = 'v' . (string)$versionRow['version'];
    }

    if ((string)$versionRow['version'] === $selectedVersion) {
        $selectedMeta = $versionRow;
    }
}
unset($versionRow);

$entriesBySection = [];
if ($selectedVersion !== '') {
    $entryStmt = $conn->prepare("
        SELECT section_title, entry_text, release_date
        FROM changelog_entries
        WHERE version = ?
        ORDER BY FIELD(section_title, 'Added', 'Changed', 'Fixed', 'Removed', 'Security', 'Deprecated', 'Features', 'Improvements', 'Fixes'), section_title, sort_order, id
    ");
    $entryStmt->bind_param('s', $selectedVersion);
    $entryStmt->execute();
    $entryResult = $entryStmt->get_result();
    while ($row = $entryResult->fetch_assoc()) {
        $section = (string)$row['section_title'];
        if (!isset($entriesBySection[$section])) {
            $entriesBySection[$section] = [
                'release_date' => $row['release_date'],
                'items' => [],
            ];
        }
        $entriesBySection[$section]['items'][] = $row['entry_text'];
    }
}

$totalVersions = count($versions);
$totalEntries = 0;
foreach ($versions as $versionRow) {
    $totalEntries += (int)$versionRow['entry_count'];
}
?>
<div class="card changelog-hero">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div>
      <div class="pill" style="margin-bottom:10px;">Release Notes</div>
      <h2 style="margin:0 0 8px 0;font-size:28px;">Changelog</h2>
      <div class="muted" style="font-size:14px;line-height:1.7;max-width:760px;">
        Browse grouped release notes without duplicating the same small changes across multiple tiny versions. In-progress work lives under Unreleased until you are ready to cut a formal release.
      </div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
      <div class="stat-pill"><strong><?php echo (int)$totalVersions; ?></strong><span>Versions</span></div>
      <div class="stat-pill"><strong><?php echo (int)$totalEntries; ?></strong><span>Entries</span></div>
      <a class="btn" href="admin.php">Admin Menu</a>
    </div>
  </div>
</div>

<div class="changelog-layout">
  <aside class="card changelog-sidebar">
    <div class="sidebar-title-row">
      <div>
        <h2 style="margin:0 0 6px 0;">Versions</h2>
        <div class="muted">Pick a release group to view its notes.</div>
      </div>
    </div>

    <?php if ($versions === []): ?>
      <div class="empty-note">No changelog entries have been saved yet.</div>
    <?php else: ?>
      <div class="version-list">
        <?php foreach ($versions as $versionRow): ?>
          <?php
            $isActive = ((string)$versionRow['version'] === $selectedVersion);
            $versionDate = trim((string)($versionRow['release_date'] ?? ''));
          ?>
          <a class="version-link<?php echo $isActive ? ' is-active' : ''; ?>" href="changelog.php?version=<?php echo urlencode((string)$versionRow['version']); ?>">
            <span class="version-link-main"><?php echo htmlspecialchars((string)($versionRow['display_version'] ?? ('v' . (string)$versionRow['version']))); ?></span>
            <span class="version-link-meta"><?php echo htmlspecialchars($versionDate !== '' ? $versionDate : ((string)$versionRow['version'] === 'Unreleased' ? 'Pending release' : 'No date')); ?> · <?php echo (int)$versionRow['entry_count']; ?> item<?php echo ((int)$versionRow['entry_count'] === 1 ? '' : 's'); ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </aside>

  <section class="changelog-main">
    <?php if ($selectedVersion === '' || $entriesBySection === []): ?>
      <div class="card empty-state-card">
        <h2 style="margin-bottom:8px;">No release selected</h2>
        <div class="muted">Choose a version from the left to load its changelog entries.</div>
      </div>
    <?php else: ?>
      <div class="card release-header-card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
          <div>
            <div class="pill" style="margin-bottom:10px;"><?php echo $selectedVersion === 'Unreleased' ? 'Current Development Batch' : 'Selected Version'; ?></div>
            <h2 style="margin:0 0 8px 0;font-size:26px;"><?php echo htmlspecialchars((string)($selectedMeta['display_version'] ?? ('v' . $selectedVersion))); ?></h2>
            <div class="muted"><?php echo htmlspecialchars((string)($selectedMeta['release_date'] ?? '') !== '' ? (string)$selectedMeta['release_date'] : ($selectedVersion === 'Unreleased' ? 'Pending release' : '')); ?></div>
          </div>
          <div class="muted" style="font-size:13px;max-width:380px;line-height:1.7;">
            Release notes are grouped by section so the history stays readable instead of inflating into a giant stack of duplicate micro-version entries.
          </div>
        </div>
      </div>

      <?php foreach ($entriesBySection as $sectionTitle => $sectionData): ?>
        <div class="card changelog-section-card">
          <div class="section-heading-row">
            <div>
              <h2 style="margin:0 0 4px 0;"><?php echo htmlspecialchars($sectionTitle); ?></h2>
              <div class="muted"><?php echo count($sectionData['items']); ?> item<?php echo count($sectionData['items']) === 1 ? '' : 's'; ?></div>
            </div>
          </div>
          <div class="release-items">
            <?php foreach ($sectionData['items'] as $item): ?>
              <div class="release-item">
                <div class="release-bullet"></div>
                <div class="release-copy"><?php echo htmlspecialchars($item); ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
</div>

<?php require_once __DIR__ . "/footer.php"; ?>
