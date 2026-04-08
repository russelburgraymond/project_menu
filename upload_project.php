<?php
require_once __DIR__ . "/bootstrap.php";
require_once __DIR__ . "/header.php";

$error = "";
$success = "";

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

function read_project_info_from_path(string $folderPath): ?array {
    $file = $folderPath . DIRECTORY_SEPARATOR . "project_info.json";
    if (!is_file($file)) return null;

    $json = file_get_contents($file);
    if ($json === false) return null;

    $data = json_decode($json, true);
    if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) return null;

    $project_name = trim((string)($data["project_name"] ?? ""));
    $version      = trim((string)($data["version"] ?? ""));
    $description  = trim((string)($data["description"] ?? ""));
    $category     = trim((string)($data["category"] ?? "Development"));

    $allowed = ["In-Progress", "Development", "Finished"];
    if (!in_array($category, $allowed, true)) {
        $category = "Development";
    }

    if ($project_name === "") {
        return null;
    }

    return [
        "project_name" => $project_name,
        "version" => $version,
        "description" => $description,
        "category" => $category,
    ];
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
                $topFolders = [];
                $hasLooseFiles = false;

                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $stat = $zip->statIndex($i);
                    if (!$stat || empty($stat["name"])) continue;

                    $entry = str_replace('\\', '/', $stat["name"]);
                    $entry = ltrim($entry, '/');

                    if ($entry === '') continue;
                    if (str_contains($entry, '../')) {
                        $error = "Unsafe zip contents detected.";
                        break;
                    }

                    $parts = explode('/', $entry);
                    $first = trim($parts[0]);

                    if ($first === '') continue;

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
                        $folderName = safe_folder_name($originalFolder);

                        if ($folderName === "") {
                            $error = "Invalid project folder name inside the zip.";
                        } else {
                            $destination = $projectsBase . DIRECTORY_SEPARATOR . $folderName;

                            if (is_dir($destination)) {
                                $error = "A project folder with that name already exists.";
                            } else {
                                $extractBase = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "project_upload_" . uniqid();
                                @mkdir($extractBase, 0777, true);

                                if (!$zip->extractTo($extractBase)) {
                                    $error = "Could not extract the zip file.";
                                } else {
                                    $sourceFolder = $extractBase . DIRECTORY_SEPARATOR . $originalFolder;

                                    if (!is_dir($sourceFolder)) {
                                        $error = "The extracted project folder was not found.";
                                    } else {
                                        if (!@rename($sourceFolder, $destination)) {
                                            $error = "Could not move the extracted project into the projects folder.";
                                        } else {
                                            $info = read_project_info_from_path($destination);

                                            if ($info !== null) {
                                                $check = $conn->prepare("SELECT id FROM projects WHERE directory = ?");
                                                $check->bind_param("s", $folderName);
                                                $check->execute();

                                                if ($check->get_result()->num_rows === 0) {
                                                    $sortOrder = next_sort_order($conn, $info["category"]);
                                                    $stmt = $conn->prepare("
                                                        INSERT INTO projects (project_name, version, description, directory, category, sort_order)
                                                        VALUES (?, ?, ?, ?, ?, ?)
                                                    ");
                                                    $stmt->bind_param(
                                                        "sssssi",
                                                        $info["project_name"],
                                                        $info["version"],
                                                        $info["description"],
                                                        $folderName,
                                                        $info["category"],
                                                        $sortOrder
                                                    );
                                                    $stmt->execute();
                                                }

                                                $success = "Project uploaded, extracted, and added automatically.";
                                            } else {
                                                header("Location: project_add.php?directory=" . urlencode($folderName) . "&from_upload=1");
                                                exit;
                                            }
                                        }
                                    }
                                }

                                if (is_dir($extractBase)) {
                                    $it = new RecursiveDirectoryIterator($extractBase, RecursiveDirectoryIterator::SKIP_DOTS);
                                    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
                                    foreach ($files as $fileInfo) {
                                        if ($fileInfo->isDir()) {
                                            @rmdir($fileInfo->getRealPath());
                                        } else {
                                            @unlink($fileInfo->getRealPath());
                                        }
                                    }
                                    @rmdir($extractBase);
                                }
                            }
                        }
                    }
                }

                $zip->close();
            }
        }
    }
}
?>

<div class="card">
  <h2>Upload Project Zip</h2>

  <?php if ($error): ?>
    <div class="alert"><b>Error:</b> <?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="pill"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <div>
      <label class="muted">Project Zip File</label>
      <input type="file" name="project_zip" accept=".zip">
    </div>

    <div style="margin-top:12px" class="muted">
      The zip must contain exactly one project folder at the top level.
      Example: <span class="dircode">aecalculator/index.php</span>
    </div>

    <div style="margin-top:14px">
      <button class="btn btn-primary" type="submit">Upload and Install</button>
      <a class="btn" href="index.php">Cancel</a>
    </div>
  </form>
</div>

<?php require_once __DIR__ . "/footer.php"; ?>