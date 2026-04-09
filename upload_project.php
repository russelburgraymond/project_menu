<?php
require_once __DIR__ . "/bootstrap.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = "";
$projectsBase = __DIR__ . DIRECTORY_SEPARATOR . "projects";
if (!is_dir($projectsBase)) {
    @mkdir($projectsBase, 0777, true);
}

function safe_folder_name(string $name): string {
    $name = trim($name);
    $name = preg_replace('/[^A-Za-z0-9._ -]/', '', $name);
    $name = str_replace(' ', '_', $name);
    return trim($name, "._- ");
}

function pm_read_project_info_file_for_upload(mysqli $conn, string $file): array {
    if (!is_file($file)) {
        return ["ok" => false, "error" => "project_info.json was not found."];
    }

    $json = file_get_contents($file);
    if ($json === false) {
        return ["ok" => false, "error" => "project_info.json could not be read."];
    }

    $data = json_decode($json, true);
    if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
        return [
            "ok" => false,
            "error" => "project_info.json contains invalid JSON: " . json_last_error_msg(),
        ];
    }

    $project_name = trim((string)($data["project_name"] ?? ""));
    $version      = trim((string)($data["version"] ?? ""));
    $description  = trim((string)($data["description"] ?? ""));
    $category     = trim((string)($data["category"] ?? pm_first_active_category($conn)));

    if (!pm_is_valid_category($conn, $category, true)) {
        $category = pm_first_active_category($conn);
    }

    if ($project_name === "") {
        return ["ok" => false, "error" => "project_info.json is missing project_name."];
    }

    return [
        "ok" => true,
        "data" => [
            "project_name" => $project_name,
            "version" => $version,
            "description" => $description,
            "category" => $category,
        ],
    ];
}

function remove_tree(string $path): void {
    if (!is_dir($path)) {
        return;
    }

    $it = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $fileInfo) {
        if ($fileInfo->isDir()) {
            @rmdir($fileInfo->getRealPath());
        } else {
            @unlink($fileInfo->getRealPath());
        }
    }
    @rmdir($path);
}

function detect_uploaded_project_root(string $sourceFolder): string {
    $rootInfo = $sourceFolder . DIRECTORY_SEPARATOR . 'project_info.json';
    if (is_file($rootInfo)) {
        return $sourceFolder;
    }

    $dirs = [];
    $files = [];
    $children = scandir($sourceFolder);
    if ($children === false) {
        return $sourceFolder;
    }

    foreach ($children as $child) {
        if ($child === '.' || $child === '..') {
            continue;
        }
        $path = $sourceFolder . DIRECTORY_SEPARATOR . $child;
        if (is_dir($path)) {
            $dirs[] = $path;
        } elseif (is_file($path)) {
            $files[] = $path;
        }
    }

    if (count($dirs) === 1 && count($files) === 0) {
        return $dirs[0];
    }

    return $sourceFolder;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (
        !isset($_FILES["project_zip"]) ||
        !is_array($_FILES["project_zip"]) ||
        $_FILES["project_zip"]["error"] !== UPLOAD_ERR_OK
    ) {
        $error = "Please upload a valid zip file.";
    } else {
        $file = $_FILES["project_zip"];
        $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

        if ($ext !== "zip") {
            $error = "Only .zip files are allowed.";
        } elseif (!class_exists("ZipArchive")) {
            $error = "PHP ZipArchive is not enabled.";
        } else {
            $tmpZip = $file["tmp_name"];
            $zip = new ZipArchive();

            if ($zip->open($tmpZip) !== true) {
                $error = "Could not open the zip file.";
            } else {
                $extractBase = null;
                try {
                    $topFolders = [];
                    $hasLooseFiles = false;

                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $stat = $zip->statIndex($i);
                        if (!$stat || empty($stat["name"])) {
                            continue;
                        }

                        $entry = str_replace('\\', '/', $stat["name"]);
                        $entry = ltrim($entry, '/');
                        if ($entry === '') {
                            continue;
                        }
                        if (str_contains($entry, '../')) {
                            $error = "Unsafe zip contents detected.";
                            break;
                        }

                        $parts = explode('/', $entry);
                        $first = trim($parts[0]);
                        if ($first === '') {
                            continue;
                        }

                        if (count($parts) === 1 && substr($entry, -1) !== '/') {
                            $hasLooseFiles = true;
                        }

                        $topFolders[$first] = true;
                    }

                    if ($error === "") {
                        $topLevelCount = count($topFolders);

                        if ($hasLooseFiles) {
                            $error = "The zip must contain one project folder at the top level, not loose files.";
                        } elseif ($topLevelCount !== 1) {
                            $error = "The zip must contain exactly one top-level project folder.";
                        } else {
                            $originalFolder = array_key_first($topFolders);
                            $extractBase = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "project_upload_" . uniqid('', true);
                            @mkdir($extractBase, 0777, true);

                            if (!$zip->extractTo($extractBase)) {
                                $error = "Could not extract the zip file.";
                            } else {
                                $sourceFolder = $extractBase . DIRECTORY_SEPARATOR . $originalFolder;
                                if (!is_dir($sourceFolder)) {
                                    $error = "The extracted project folder was not found.";
                                } else {
                                    $projectRootToMove = detect_uploaded_project_root($sourceFolder);
                                    $projectRootName = basename($projectRootToMove);
                                    $finalFolderName = safe_folder_name($projectRootName !== '' ? $projectRootName : $originalFolder);

                                    if ($finalFolderName === '') {
                                        $error = 'The detected project folder name is invalid.';
                                    } else {
                                        $finalDestination = $projectsBase . DIRECTORY_SEPARATOR . $finalFolderName;
                                        if (is_dir($finalDestination)) {
                                            $error = "A project folder with that name already exists.";
                                        } elseif (!@rename($projectRootToMove, $finalDestination)) {
                                            $error = "Could not move the extracted project into the projects directory.";
                                        } else {
                                            $prefill = [
                                                'directory' => $finalFolderName,
                                                'project_name' => str_replace('_', ' ', $finalFolderName),
                                                'version' => '',
                                                'description' => '',
                                                'category' => pm_first_active_category($conn),
                                                'info_error' => '',
                                            ];

                                            $infoPath = $finalDestination . DIRECTORY_SEPARATOR . 'project_info.json';
                                            if (is_file($infoPath)) {
                                                $parsed = pm_read_project_info_file_for_upload($conn, $infoPath);
                                                if ($parsed['ok']) {
                                                    $prefill = array_merge($prefill, $parsed['data']);
                                                } else {
                                                    $prefill['info_error'] = $parsed['error'];
                                                }
                                            }

                                            $_SESSION['upload_details_prefill'] = $prefill;
                                            header('Location: upload_details.php?directory=' . urlencode($finalFolderName));
                                            exit;
                                        }
                                    }
                                }
                            }
                        }
                    }
                } finally {
                    $zip->close();
                    if ($extractBase && is_dir($extractBase)) {
                        remove_tree($extractBase);
                    }
                }
            }
        }
    }
}

require_once __DIR__ . "/header.php";
?>

<div class="card">
  <h2>Upload Project ZIP</h2>
  <p class="muted" style="font-size:14px;line-height:1.7;">
    Upload a ZIP that contains exactly one project folder at the top level. After upload, you will be taken to a details page
    where you can verify the project information, optionally assign the upload as a branch of another project, and save a
    fresh <span class="dircode">project_info.json</span> into the project folder.
  </p>

  <?php if ($error): ?>
    <div class="alert" style="margin-top:12px;"><b>Error:</b> <?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" style="margin-top:12px;">
    <div>
      <label class="muted">ZIP File</label>
      <input type="file" name="project_zip" accept=".zip" required>
    </div>

    <div style="margin-top:14px;">
      <button class="btn btn-primary" type="submit">Upload and Continue</button>
      <a class="btn" href="index.php">Cancel</a>
    </div>
  </form>
</div>

<?php require_once __DIR__ . "/footer.php"; ?>
