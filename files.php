<?php
session_start();
include('log.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['user_id'];

// Ladataan käyttäjän asetukset
$userSettingsFile = "user_settings/{$username}_settings.json";
if (file_exists($userSettingsFile)) {
    $userSettings = json_decode(file_get_contents($userSettingsFile), true);
} else {
    $userSettings = [
        'theme' => 'light',
        'fileSort' => 'name',
        'pageSize' => 10,
        'viewMode' => 'list', // Default view mode
        'categories' => [
            'yhteiskuvat' => 'Yhteiskuvat',
            'yksinkuvat' => 'Yksinkuvat',
            'rakkaan_kuvat' => 'Rakkaan kuvat',
            'ulko_kuvat' => 'Ulkokuvat',
            'muut_kuvat' => 'Muut kuvat'
        ]
    ];
    
    // Varmistetaan, että user_settings-kansio on olemassa
    if (!is_dir('user_settings')) {
        mkdir('user_settings', 0777, true);
    }
    
    // Tallennetaan oletusasetukset
    file_put_contents($userSettingsFile, json_encode($userSettings, JSON_PRETTY_PRINT));
}

$theme = $userSettings['theme'];
$sortBy = $userSettings['fileSort'];
$pageSize = $userSettings['pageSize'];
$viewMode = isset($userSettings['viewMode']) ? $userSettings['viewMode'] : 'list'; // Get view mode from settings

// Käyttäjän kategoriat
$categories = isset($userSettings['categories']) ? $userSettings['categories'] : [
    'yhteiskuvat' => 'Yhteiskuvat',
    'yksinkuvat' => 'Yksinkuvat',
    'rakkaan_kuvat' => 'Rakkaan kuvat',
    'ulko_kuvat' => 'Ulkokuvat',
    'muut_kuvat' => 'Muut kuvat'
];

// Kategorioiden hallinta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manage_categories'])) {
    if (isset($_POST['add_category']) && !empty($_POST['category_key']) && !empty($_POST['category_name'])) {
        // Lisää uusi kategoria
        $categoryKey = preg_replace('/[^a-z0-9_]/', '_', strtolower($_POST['category_key']));
        $categoryName = trim($_POST['category_name']);
        
        $userSettings['categories'][$categoryKey] = $categoryName;
        file_put_contents($userSettingsFile, json_encode($userSettings, JSON_PRETTY_PRINT));
        $successMessage = "Uusi kategoria lisätty onnistuneesti.";
        
        // Päivitetään kategoriat
        $categories = $userSettings['categories'];
    } elseif (isset($_POST['delete_category']) && !empty($_POST['delete_key'])) {
        // Poista kategoria
        $deleteKey = $_POST['delete_key'];
        
        // Varmistetaan, että kategoria on olemassa
        if (isset($userSettings['categories'][$deleteKey])) {
            // Poista kategoria asetuksista
            unset($userSettings['categories'][$deleteKey]);
            file_put_contents($userSettingsFile, json_encode($userSettings, JSON_PRETTY_PRINT));
            
            // Päivitetään tiedostot, joilla on poistettu kategoria
            $userFileInfoPath = "user_settings/{$username}_file_info.txt";
            if (file_exists($userFileInfoPath)) {
                $fileInfos = file($userFileInfoPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $newFileInfos = [];
                foreach ($fileInfos as $fileInfo) {
                    $info = explode(':', $fileInfo);
                    if (count($info) >= 3 && $info[2] === $deleteKey) {
                        // Aseta kategoria oletusarvoon
                        $info[2] = 'muut_kuvat';
                        $newFileInfos[] = $info[0] . ':' . $info[1] . ':' . $info[2];
                    } else {
                        $newFileInfos[] = $fileInfo;
                    }
                }
                file_put_contents($userFileInfoPath, implode("\n", $newFileInfos) . "\n");
            }
            
            $successMessage = "Kategoria poistettu onnistuneesti.";
            
            // Päivitetään kategoriat
            $categories = $userSettings['categories'];
        } else {
            $errorMessage = "Kategoriaa ei löydy.";
        }
    }
}

// Tiedoston nimen muokkaus
if (isset($_POST['rename']) && isset($_POST['file']) && isset($_POST['newName'])) {
    $fileToRename = $_POST['file'];
    $newName = trim($_POST['newName']);
    $newName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $newName); // Sanitize filename
    
    if (!empty($newName)) {
        $userFileInfoPath = "user_settings/{$username}_file_info.txt";
        
        if (file_exists($userFileInfoPath)) {
            $fileInfos = file($userFileInfoPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $newFileInfos = [];
            $fileRenamed = false;
            
            foreach ($fileInfos as $fileInfo) {
                $info = explode(':', $fileInfo);
                if (count($info) >= 2 && $info[0] === $fileToRename) {
                    $oldExtension = pathinfo($info[1], PATHINFO_EXTENSION);
                    $newNameWithExtension = $newName . '.' . $oldExtension;
                    $category = isset($info[2]) ? $info[2] : 'muut_kuvat';
                    $newFileInfos[] = $info[0] . ':' . $newNameWithExtension . ':' . $category;
                    $fileRenamed = true;
                } else {
                    $newFileInfos[] = $fileInfo;
                }
            }
            
            if ($fileRenamed) {
                file_put_contents($userFileInfoPath, implode("\n", $newFileInfos) . "\n");
                $successMessage = "Tiedoston nimi muutettu onnistuneesti.";
            } else {
                $errorMessage = "Tiedostoa ei löytynyt.";
            }
        } else {
            $errorMessage = "Käyttäjän tiedostotietoja ei löytynyt.";
        }
    } else {
        $errorMessage = "Uusi nimi ei voi olla tyhjä.";
    }
}

// Kategorian muokkaus
if (isset($_POST['update_category']) && isset($_POST['file']) && isset($_POST['category'])) {
    $fileToUpdate = $_POST['file'];
    $newCategory = $_POST['category'];
    $userFileInfoPath = "user_settings/{$username}_file_info.txt";
    
    if (file_exists($userFileInfoPath)) {
        $fileInfos = file($userFileInfoPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $newFileInfos = [];
        $categoryUpdated = false;
        
        foreach ($fileInfos as $fileInfo) {
            $info = explode(':', $fileInfo);
            if (count($info) >= 2 && $info[0] === $fileToUpdate) {
                $newFileInfos[] = $info[0] . ':' . $info[1] . ':' . $newCategory;
                $categoryUpdated = true;
            } else {
                $newFileInfos[] = $fileInfo;
            }
        }
        
        if ($categoryUpdated) {
            file_put_contents($userFileInfoPath, implode("\n", $newFileInfos) . "\n");
            $successMessage = "Tiedoston kategoria päivitetty onnistuneesti.";
        } else {
            $errorMessage = "Tiedostoa ei löytynyt.";
        }
    } else {
        $errorMessage = "Käyttäjän tiedostotietoja ei löytynyt.";
    }
}

// Tiedoston poisto
if (isset($_POST['delete']) && isset($_POST['file'])) {
    $fileToDelete = $_POST['file'];
    $userFileInfoPath = "user_settings/{$username}_file_info.txt";
    
    if (file_exists($userFileInfoPath)) {
        $fileInfos = file($userFileInfoPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $newFileInfos = [];
        $fileDeleted = false;
        
        foreach ($fileInfos as $fileInfo) {
            $info = explode(':', $fileInfo);
            if (count($info) >= 1 && $info[0] === $fileToDelete) {
                // Poista tiedosto palvelimelta
                $userFolder = 'uploads/' . $username . '/';
                if (file_exists($userFolder . $fileToDelete)) {
                    unlink($userFolder . $fileToDelete);
                } else if (file_exists('uploads/' . $fileToDelete)) {
                    // Tarkistetaan myös vanha sijainti yhteensopivuuden vuoksi
                    unlink('uploads/' . $fileToDelete);
                }
                $fileDeleted = true;
            } else {
                $newFileInfos[] = $fileInfo;
            }
        }
        
        if ($fileDeleted) {
            file_put_contents($userFileInfoPath, implode("\n", $newFileInfos) . "\n");
            $successMessage = "Tiedosto poistettu onnistuneesti.";
        } else {
            $errorMessage = "Tiedostoa ei löytynyt.";
        }
    } else {
        $errorMessage = "Käyttäjän tiedostotietoja ei löytynyt.";
    }
}

// Haetaan käyttäjän tiedostot
$uploadedFiles = [];
$userFileInfoPath = "user_settings/{$username}_file_info.txt";

if (file_exists($userFileInfoPath)) {
    $fileInfos = file($userFileInfoPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($fileInfos as $fileInfo) {
        $info = explode(':', $fileInfo);
        if (count($info) >= 2) {
            // Tarkistetaan tiedoston sijainti (käyttäjäkansio tai vanha sijainti)
            $userFolder = 'uploads/' . $username . '/';
            $uniqueFileName = $info[0];
            
            // Tarkistetaan, onko tiedosto käyttäjän kansiossa vai vanhassa sijainnissa
            if (file_exists($userFolder . $uniqueFileName)) {
                $filePath = $userFolder . $uniqueFileName;
            } else if (file_exists('uploads/' . $uniqueFileName)) {
                $filePath = 'uploads/' . $uniqueFileName;
            } else {
                // Tiedostoa ei löydy, mutta lisätään silti listaan
                $filePath = $userFolder . $uniqueFileName;
            }
            
            $fileCategory = isset($info[2]) ? $info[2] : 'muut_kuvat';
            $fileExtension = strtolower(pathinfo($info[1], PATHINFO_EXTENSION));
            $isImage = in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            $isVideo = in_array($fileExtension, ['mp4', 'webm', 'avi', 'mov', 'mkv']);

            $uploadedFiles[] = [
                'uniqueName' => $uniqueFileName,
                'originalName' => $info[1],
                'size' => file_exists($filePath) ? filesize($filePath) : 0,
                'date' => file_exists($filePath) ? filemtime($filePath) : 0,
                'category' => $fileCategory,
                'isImage' => $isImage,
                'isVideo' => $isVideo,
                'extension' => $fileExtension,
                'path' => $filePath
            ];
        }
    }
}
// Tarkistetaan myös vanha yhteinen tiedosto yhteensopivuuden vuoksi
else if (file_exists('file_info.txt')) {
    $fileInfos = file('file_info.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $userFiles = [];
    
    foreach ($fileInfos as $fileInfo) {
        $info = explode(':', $fileInfo);
        if (count($info) >= 3 && $info[0] === $username) {
            // Tarkistetaan tiedoston sijainti (käyttäjäkansio tai vanha sijainti)
            $userFolder = 'uploads/' . $username . '/';
            $uniqueFileName = $info[1];
            
            // Tarkistetaan, onko tiedosto käyttäjän kansiossa vai vanhassa sijainnissa
            if (file_exists($userFolder . $uniqueFileName)) {
                $filePath = $userFolder . $uniqueFileName;
            } else if (file_exists('uploads/' . $uniqueFileName)) {
                $filePath = 'uploads/' . $uniqueFileName;
            } else {
                // Tiedostoa ei löydy, mutta lisätään silti listaan
                $filePath = $userFolder . $uniqueFileName;
            }
            
            $fileCategory = isset($info[3]) ? $info[3] : 'muut_kuvat';
            $fileExtension = strtolower(pathinfo($info[2], PATHINFO_EXTENSION));
            $isImage = in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            $isVideo = in_array($fileExtension, ['mp4', 'webm', 'avi', 'mov', 'mkv']);

            $uploadedFiles[] = [
                'uniqueName' => $uniqueFileName,
                'originalName' => $info[2],
                'size' => file_exists($filePath) ? filesize($filePath) : 0,
                'date' => file_exists($filePath) ? filemtime($filePath) : 0,
                'category' => $fileCategory,
                'isImage' => $isImage,
                'isVideo' => $isVideo,
                'extension' => $fileExtension,
                'path' => $filePath
            ];
            
            // Tallennetaan tiedot käyttäjäkohtaiseen tiedostoon
            $userFiles[] = "{$uniqueFileName}:{$info[2]}:{$fileCategory}";
        }
    }
    
    // Luodaan käyttäjäkohtainen tiedosto, jos löytyi tiedostoja
    if (!empty($userFiles)) {
        file_put_contents($userFileInfoPath, implode("\n", $userFiles) . "\n");
    }
}

// Calculate file statistics
$totalFiles = count($uploadedFiles);
$imageCount = 0;
$videoCount = 0;
$otherCount = 0;

foreach ($uploadedFiles as $file) {
    if ($file['isImage']) {
        $imageCount++;
    } elseif ($file['isVideo']) {
        $videoCount++;
    } else {
        $otherCount++;
    }
}

// Hakutoiminto
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$selectedCategory = isset($_GET['category']) ? $_GET['category'] : '';
$selectedFileType = isset($_GET['filetype']) ? $_GET['filetype'] : '';

// Suodata tiedostot hakutermin, kategorian ja tiedostotyypin perusteella
if (!empty($searchTerm) || !empty($selectedCategory) || !empty($selectedFileType)) {
    $filteredFiles = [];
    foreach ($uploadedFiles as $file) {
        $matchesSearch = empty($searchTerm) || stripos($file['originalName'], $searchTerm) !== false;
        $matchesCategory = empty($selectedCategory) || $file['category'] === $selectedCategory;
        
        // Tiedostotyypin suodatus
        $matchesFileType = true;
        if (!empty($selectedFileType)) {
            if ($selectedFileType === 'images') {
                $matchesFileType = $file['isImage'];
            } elseif ($selectedFileType === 'videos') {
                $matchesFileType = $file['isVideo'];
            } elseif ($selectedFileType === 'files') {
                $matchesFileType = !$file['isImage'] && !$file['isVideo'];
            }
        }
        
        if ($matchesSearch && $matchesCategory && $matchesFileType) {
            $filteredFiles[] = $file;
        }
    }
    $uploadedFiles = $filteredFiles;
}

// Järjestä tiedostot
switch ($sortBy) {
    case 'name':
        usort($uploadedFiles, function($a, $b) {
            return strcmp($a['originalName'], $b['originalName']);
        });
        break;
    case 'date':
        usort($uploadedFiles, function($a, $b) {
            return $b['date'] - $a['date'];
        });
        break;
    case 'size':
        usort($uploadedFiles, function($a, $b) {
            return $b['size'] - $a['size'];
        });
        break;
    case 'category':
        usort($uploadedFiles, function($a, $b) {
            return strcmp($a['category'], $b['category']);
        });
        break;
}

// Sivutus
$totalFilteredFiles = count($uploadedFiles);
$totalPages = ceil($totalFilteredFiles / $pageSize);
$currentPage = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;
$startIndex = ($currentPage - 1) * $pageSize;
$pagedFiles = array_slice($uploadedFiles, $startIndex, $pageSize);

// Apufunktio tiedoston koon formatointiin
function formatFileSize($bytes) {
    if ($bytes < 1024) {
        return $bytes . ' B';
    } else if ($bytes < 1048576) {
        return round($bytes / 1024, 1) . ' KB';
    } else {
        return round($bytes / 1048576, 1) . ' MB';
    }
}
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/S7JLkFy1/Heartcrown.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tiedostot</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="reduced-animations.css">
    <?php if ($theme === 'dark'): ?>
    <link rel="stylesheet" href="dark-theme.css">
    <?php elseif ($theme === 'puhelin'): ?>
    <link rel="stylesheet" href="mobile-theme.css">
    <?php elseif ($theme === 'puhelin-dark'): ?>
    <link rel="stylesheet" href="dark-mobile-theme.css">
    <?php endif; ?>
    <style>
    /* Custom styles for this page */
    .files-container {
        display: flex;
        gap: 20px;
    }

    .files-main {
        flex: 1;
    }

    .file-preview {
        width: 300px;
        position: sticky;
        top: 20px;
        align-self: flex-start;
    }

    /* Fix for grid view buttons - improved to ensure visibility */
    .file-card {
        position: relative;
        display: flex;
        flex-direction: column;
        height: auto !important; /* Override fixed height */
        min-height: 250px;
    }

    .file-card-link {
        flex: 1;
        display: flex;
        flex-direction: column;
        z-index: 1;
    }

    .file-card-actions {
        display: flex;
        gap: 5px;
        padding: 10px;
        background-color: #f9f0ff;
        border-top: 1px solid #e0d0ff;
        z-index: 2; /* Ensure buttons are above other elements */
        position: relative; /* Ensure proper stacking context */
        width: 100%;
        box-sizing: border-box;
    }

    /* Enhanced button styling */
    .file-card-actions button {
        flex: 1;
        padding: 5px;
        font-size: 10px; /* Smaller font size */
        min-width: 0;
        z-index: 3; /* Higher z-index for buttons */
        border-radius: 4px;
        border: 1px solid #e0d0ff;
        background: linear-gradient(to bottom, #ffffff, #f5f0ff);
        color: #8400cc;
        font-weight: 500;
        transition: all 0.2s ease;
        text-transform: uppercase; /* Makes text more compact and stylish */
        letter-spacing: 0.5px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }

    .file-card-actions button:hover {
        background: linear-gradient(to bottom, #f5f0ff, #e0d0ff);
        border-color: #d0b0ff;
    }

    .file-card-actions .rename-btn {
        background: linear-gradient(to bottom, #e0d6ff, #d0b8ff); /* Uudet värit */
        color: #7a00b8; /* Uusi teksti-väri */
    }

    .file-card-actions .category-btn {
        background: linear-gradient(to bottom, #e0d6ff, #c8a9ff); /* Uudet värit */
        color: #7a00b8; /* Uusi teksti-väri */
    }

    .file-card-actions .delete-btn {
        background: linear-gradient(to bottom, rgb(204, 153, 255), rgb(203, 51, 230)); /* Uudet värit */
        color: rgb(102, 0, 204); /* Uusi teksti-väri */
    }

    /* Ensure file card info doesn't overflow */
    .file-card-info {
        padding: 10px;
        display: flex;
        flex-direction: column;
        flex-grow: 1;
        overflow: hidden;
    }

    @media (max-width: 768px) {
        .files-container {
            flex-direction: column;
        }

        .file-preview {
            width: 100%;
            position: static;
        }
    }
    </style>
</head>
<body class="<?php echo $theme; ?>-theme">
    <?php include('header.php'); ?>

    <div class="container">
        <h2>Tiedostot</h2>
        
        <?php if (isset($successMessage)): ?>
            <p class="success"><?php echo $successMessage; ?></p>
        <?php endif; ?>
        
        <?php if (isset($errorMessage)): ?>
            <p class="error"><?php echo $errorMessage; ?></p>
        <?php endif; ?>

        <!-- Hakutoiminto -->
        <div class="search-container">
            <div class="search-header">
                <h3>Haku ja suodatus</h3>
                <div class="header-actions">
                    <button type="button" class="manage-categories-btn" onclick="showCategoriesManager()">Hallitse kategorioita</button>
                    <!-- Removed view toggle buttons as requested -->
                </div>
            </div>
            <form method="GET" class="search-form">
                <div class="search-row">
                    <div class="search-field">
                        <input type="text" name="search" placeholder="Hae tiedostoja..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    </div>
                    <div class="category-filter">
                        <select name="category">
                            <option value="">Kaikki kategoriat</option>
                            <?php foreach ($categories as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo $selectedCategory === $value ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filetype-filter">
                        <select name="filetype">
                            <option value="">Kaikki tiedostotyypit</option>
                            <option value="images" <?php echo $selectedFileType === 'images' ? 'selected' : ''; ?>>Kuvat</option>
                            <option value="videos" <?php echo $selectedFileType === 'videos' ? 'selected' : ''; ?>>Videot</option>
                            <option value="files" <?php echo $selectedFileType === 'files' ? 'selected' : ''; ?>>Muut tiedostot</option>
                        </select>
                    </div>
                    <div class="search-buttons">
                        <button type="submit" class="search-btn">Hae</button>
                        <a href="files.php" class="reset-btn">Tyhjennä</a>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="files-container">
            <div class="files-main">
                <?php if (!empty($pagedFiles)): ?>
                    <!-- Grid View -->
                    <?php if ($viewMode === 'grid'): ?>
                    <div id="fileGrid" class="file-grid">
                        <?php foreach ($pagedFiles as $file): ?>
                            <div class="file-card" data-file="<?php echo htmlspecialchars($file['uniqueName']); ?>" data-path="<?php echo htmlspecialchars($file['path']); ?>" data-is-image="<?php echo $file['isImage'] ? '1' : '0'; ?>" data-extension="<?php echo htmlspecialchars($file['extension']); ?>">
                                <a href="<?php echo htmlspecialchars($file['path']); ?>" target="_blank" class="file-card-link">
                                    <?php if ($file['isImage']): ?>
                                        <div class="file-card-thumbnail">
                                            <img src="<?php echo htmlspecialchars($file['path']); ?>" alt="<?php echo htmlspecialchars($file['originalName']); ?>">
                                        </div>
                                    <?php elseif ($file['isVideo']): ?>
                                        <div class="file-card-thumbnail video-thumbnail">
                                            <div class="video-icon">▶</div>
                                            <span class="file-extension"><?php echo strtoupper($file['extension']); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="file-card-thumbnail file-thumbnail">
                                            <span class="file-extension"><?php echo strtoupper($file['extension']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="file-card-info">
                                        <h4 class="file-card-name"><?php echo htmlspecialchars($file['originalName']); ?></h4>
                                        <div class="file-card-meta">
                                            <span class="file-card-date"><?php echo date('d.m.Y H:i', $file['date']); ?></span>
                                            <span class="file-card-size"><?php echo formatFileSize($file['size']); ?></span>
                                        </div>
                                        <span class="file-card-category">
                                            <?php echo isset($categories[$file['category']]) ? htmlspecialchars($categories[$file['category']]) : 'Muu'; ?>
                                        </span>
                                    </div>
                                </a>
                                <div class="file-card-actions">
                                    <button onclick="showRenameForm('<?php echo $file['uniqueName']; ?>', '<?php echo htmlspecialchars($file['originalName']); ?>')" class="rename-btn">Nimeä</button>
                                    <button onclick="showCategoryForm('<?php echo $file['uniqueName']; ?>', '<?php echo $file['category']; ?>', '<?php echo htmlspecialchars($file['originalName']); ?>')" class="category-btn">Kategoria</button>
                                    <form method="POST" onsubmit="return confirm('Haluatko varmasti poistaa tämän tiedoston?');" style="display: inline;">
                                        <input type="hidden" name="file" value="<?php echo $file['uniqueName']; ?>">
                                        <button type="submit" name="delete" class="delete-btn">Poista</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <!-- List View -->
                    <div id="fileList">
                        <table class="file-table">
                            <thead>
                                <tr>
                                    <th>Nimi</th>
                                    <th>Kategoria</th>
                                    <th>Koko</th>
                                    <th>Päivämäärä</th>
                                    <th>Toiminnot</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pagedFiles as $file): ?>
                                    <tr class="file-row" data-file="<?php echo htmlspecialchars($file['uniqueName']); ?>" data-path="<?php echo htmlspecialchars($file['path']); ?>" data-is-image="<?php echo $file['isImage'] ? '1' : '0'; ?>" data-extension="<?php echo htmlspecialchars($file['extension']); ?>">
                                        <td>
                                            <a href="<?php echo htmlspecialchars($file['path']); ?>" target="_blank">
                                                <?php echo htmlspecialchars($file['originalName']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php 
                                            echo isset($categories[$file['category']]) 
                                                ? htmlspecialchars($categories[$file['category']]) 
                                                : 'Muu'; 
                                            ?>
                                        </td>
                                        <td><?php echo formatFileSize($file['size']); ?></td>
                                        <td><?php echo date('d.m.Y H:i', $file['date']); ?></td>
                                        <td>
                                            <button onclick="showRenameForm('<?php echo $file['uniqueName']; ?>', '<?php echo htmlspecialchars($file['originalName']); ?>')" class="rename-btn">Nimeä uudelleen</button>
                                            <button onclick="showCategoryForm('<?php echo $file['uniqueName']; ?>', '<?php echo $file['category']; ?>', '<?php echo htmlspecialchars($file['originalName']); ?>')" class="category-btn">Vaihda kategoria</button>
                                            <form method="POST" onsubmit="return confirm('Haluatko varmasti poistaa tämän tiedoston?');" style="display: inline;">
                                                <input type="hidden" name="file" value="<?php echo $file['uniqueName']; ?>">
                                                <button type="submit" name="delete" class="delete-btn">Poista</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>

                    <!-- Sivutus -->
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($searchTerm); ?>&category=<?php echo urlencode($selectedCategory); ?>&filetype=<?php echo urlencode($selectedFileType); ?>" <?php echo $i === $currentPage ? 'class="active"' : ''; ?>><?php echo $i; ?></a>
                        <?php endfor; ?>
                    </div>
                <?php else: ?>
                    <div class="no-files-message">
                        <div class="message-icon">📂</div>
                        <p>Ei ladattuja tiedostoja<?php echo !empty($searchTerm) || !empty($selectedCategory) || !empty($selectedFileType) ? ' hakuehdoilla' : ''; ?>.</p>
                        <?php if (!empty($searchTerm) || !empty($selectedCategory) || !empty($selectedFileType)): ?>
                            <button class="back-btn" onclick="window.location.href='files.php'">Näytä kaikki tiedostot</button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($viewMode === 'list' && (!isset($userSettings['hidePreview']) || !$userSettings['hidePreview'])): ?>
            <div class="file-preview" id="filePreview">
                <h3>Esikatselu</h3>
                <div id="previewContent"></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Uudelleennimeämislomake (piilotettu oletuksena) -->
    <div id="renameForm" class="modal" style="display: none;">
        <div class="modal-content">
            <h3>Nimeä tiedosto uudelleen</h3>
            <form method="POST">
                <input type="hidden" id="renameFile" name="file" value="">
                <input type="text" id="newFileName" name="newName" required>
                <button type="submit" name="rename">Tallenna</button>
                <button type="button" onclick="hideRenameForm()">Peruuta</button>
            </form>
        </div>
    </div>

    <!-- Kategorian vaihtolomake (piilotettu oletuksena) - Paranneltu versio -->
    <div id="categoryForm" class="modal" style="display: none;">
        <div class="modal-content categories-modal">
            <h3>Vaihda tiedoston kategoria</h3>
            <p id="categoryFileName" class="file-info">Tiedosto: <span></span></p>
            
            <form method="POST">
                <input type="hidden" id="categoryFile" name="file" value="">
                
                <div class="category-selection-list">
                    <?php foreach ($categories as $value => $label): ?>
                        <div class="category-option">
                            <input type="radio" id="cat_<?php echo $value; ?>" name="category" value="<?php echo $value; ?>" class="category-radio">
                            <label for="cat_<?php echo $value; ?>" class="category-label"><?php echo $label; ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="modal-buttons">
                    <button type="submit" name="update_category" class="save-btn">Tallenna</button>
                    <button type="button" onclick="hideCategoryForm()" class="cancel-btn">Peruuta</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Kategorioiden hallinta (piilotettu oletuksena) -->
    <div id="categoriesManager" class="modal" style="display: none;">
        <div class="modal-content categories-modal">
            <h3>Hallitse kategorioita</h3>
            
            <div class="current-categories">
                <h4>Nykyiset kategoriat</h4>
                <table class="categories-table">
                    <thead>
                        <tr>
                            <th>Kategorian nimi</th>
                            <th>Toiminto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $key => $name): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($name); ?></td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('Haluatko varmasti poistaa tämän kategorian?');">
                                        <input type="hidden" name="delete_key" value="<?php echo $key; ?>">
                                        <input type="hidden" name="manage_categories" value="1">
                                        <button type="submit" name="delete_category" class="delete-category-btn">Poista</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="add-category">
                <h4>Lisää uusi kategoria</h4>
                <form method="POST">
                    <div class="form-group">
                        <label for="category_key">Kategorian tunniste (vain pienet kirjaimet, numerot ja alaviivat):</label>
                        <input type="text" id="category_key" name="category_key" pattern="[a-z0-9_]+" required>
                    </div>
                    <div class="form-group">
                        <label for="category_name">Kategorian nimi:</label>
                        <input type="text" id="category_name" name="category_name" required>
                    </div>
                    <input type="hidden" name="manage_categories" value="1">
                    <button type="submit" name="add_category" class="add-category-btn">Lisää kategoria</button>
                </form>
            </div>
            
            <button type="button" onclick="hideCategoriesManager()" class="close-btn">Sulje</button>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
    function showRenameForm(uniqueName, originalName) {
        document.getElementById('renameFile').value = uniqueName;
        document.getElementById('newFileName').value = originalName.split('.').slice(0, -1).join('.'); // Remove file extension
        document.getElementById('renameForm').style.display = 'block';
    }

    function hideRenameForm() {
        document.getElementById('renameForm').style.display = 'none';
    }

    function showCategoryForm(uniqueName, currentCategory, fileName) {
        document.getElementById('categoryFile').value = uniqueName;
        document.getElementById('categoryFileName').querySelector('span').textContent = fileName;
        
        // Valitse nykyinen kategoria
        const radioButton = document.getElementById('cat_' + currentCategory);
        if (radioButton) {
            radioButton.checked = true;
        }
        
        document.getElementById('categoryForm').style.display = 'block';
    }

    function hideCategoryForm() {
        document.getElementById('categoryForm').style.display = 'none';
    }
    
    function showCategoriesManager() {
        document.getElementById('categoriesManager').style.display = 'block';
    }
    
    function hideCategoriesManager() {
        document.getElementById('categoriesManager').style.display = 'none';
    }

    document.addEventListener('DOMContentLoaded', function() {
        const fileRows = document.querySelectorAll('.file-row');
        const fileCards = document.querySelectorAll('.file-card');
        const previewContent = document.getElementById('previewContent');
        const isMobileTheme = document.body.classList.contains('puhelin-theme') || document.body.classList.contains('puhelin-dark-theme');

        // Add hover preview for list view
        fileRows.forEach(row => {
            const filePath = row.dataset.path;
            const isImage = row.dataset.isImage === '1';
            const fileExtension = row.dataset.extension;
            
            // Tavallinen esikatselu hiiren hover-toiminnolla
            row.addEventListener('mouseenter', function() {
                if (!filePath) return; // Skip if path is not defined
                
                let previewHtml = '';

                if (isImage) {
                    previewHtml = `<img src="${filePath}" alt="Esikatselu" class="preview-image">`;
                } else if (['mp4', 'webm', 'avi', 'mov', 'mkv', 'flv', 'wmv', 'mpeg', 'mpg', '3gp', 'ogg'].includes(fileExtension)) {
                    previewHtml = `<video src="${filePath}" controls class="preview-video"></video>`;
                } else if (['mp3', 'wav', 'ogg', 'flac', 'aac', 'm4a', 'wma', 'aiff', 'alac', 'opus', 'amr'].includes(fileExtension)) {
                    previewHtml = `<audio src="${filePath}" controls class="preview-audio"></audio>`;
                } else {
                    previewHtml = '<p>Esikatselu ei ole saatavilla tälle tiedostotyypille.</p>';
                }

                previewContent.innerHTML = previewHtml;
                previewContent.style.opacity = '0';
                setTimeout(() => {
                    previewContent.style.opacity = '1';
                }, 50);
            });

            row.addEventListener('mouseleave', function() {
                previewContent.style.opacity = '0';
            });
        });
        
        // Add animation to file cards
        fileCards.forEach((card, index) => {
            card.style.animationDelay = (index * 0.05) + 's';
            card.classList.add('animated');
        });
    });
    </script>
</body>
</html>

