<?php
session_start();
include('log.php');

// Tarkistetaan, että käyttäjä on kirjautunut ja on admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$admin = $_SESSION['user_id'];

// Ladataan admin käyttäjän asetukset
$adminSettingsFile = "user_settings/{$admin}_settings.json";
if (file_exists($adminSettingsFile)) {
    $adminSettings = json_decode(file_get_contents($adminSettingsFile), true);
    $theme = $adminSettings['theme'];
} else {
    $theme = 'light';
}

// Haetaan kaikki käyttäjät
$users = [];
if (file_exists('users.txt')) {
    $userLines = file('users.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($userLines as $userLine) {
        $userData = explode(':', $userLine);
        if (count($userData) >= 2 && $userData[0] !== $admin) {
            // Get user creation date from file stats or logs
            $creationDate = '';
            $lastLoginTime = '';
            $lastUploadTime = '';
            $ipAddress = '';
            
            // Check login logs for last login and IP
            if (file_exists('logs/login_log.txt')) {
                $loginLogs = file('logs/login_log.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach (array_reverse($loginLogs) as $log) {
                    if (strpos($log, "Käyttäjä {$userData[0]} kirjautui sisään") !== false) {
                        preg_match('/\[(.*?)\]/', $log, $dateMatches);
                        preg_match('/IP: ([\d\.]+)/', $log, $ipMatches);
                        
                        if (!empty($dateMatches[1])) {
                            $lastLoginTime = $dateMatches[1];
                        }
                        
                        if (!empty($ipMatches[1])) {
                            $ipAddress = $ipMatches[1];
                        }
                        
                        break;
                    }
                }
            }
            
            // Check upload logs for last upload
            if (file_exists('logs/upload_log.txt')) {
                $uploadLogs = file('logs/upload_log.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach (array_reverse($uploadLogs) as $log) {
                    if (strpos($log, "{$userData[0]} latasi tiedoston") !== false) {
                        preg_match('/\[(.*?)\]/', $log, $dateMatches);
                        
                        if (!empty($dateMatches[1])) {
                            $lastUploadTime = $dateMatches[1];
                            break;
                        }
                    }
                }
            }
            
            // Try to determine account creation date
            $userSettingsFile = "user_settings/{$userData[0]}_settings.json";
            if (file_exists($userSettingsFile)) {
                $creationDate = date("Y-m-d H:i:s", filectime($userSettingsFile));
            }
            
            // Count user's files
            $fileCount = 0;
            $userFileInfoPath = "user_settings/{$userData[0]}_file_info.txt";
            if (file_exists($userFileInfoPath)) {
                $fileInfos = file($userFileInfoPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $fileCount = count($fileInfos);
            }
            
            // Calculate total storage used
            $storageUsed = 0;
            $userFolder = 'uploads/' . $userData[0] . '/';
            if (is_dir($userFolder)) {
                $files = glob($userFolder . '*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        $storageUsed += filesize($file);
                    }
                }
            }
            
            $users[] = [
                'username' => $userData[0],
                'role' => isset($userData[2]) ? $userData[2] : 'user',
                'creationDate' => $creationDate,
                'lastLoginTime' => $lastLoginTime,
                'lastUploadTime' => $lastUploadTime,
                'ipAddress' => $ipAddress,
                'fileCount' => $fileCount,
                'storageUsed' => $storageUsed,
                'accountStatus' => 'Aktiivinen' // Default status
            ];
        }
    }
}

// Valittu käyttäjä
$selectedUser = isset($_GET['user']) ? $_GET['user'] : '';
$uploadedFiles = [];
$fileCount = 0;
$imageCount = 0;
$videoCount = 0;
$otherCount = 0;

// Valittu kategoria
$selectedCategory = isset($_GET['category']) ? $_GET['category'] : '';

// Valittu järjestys
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'date';

if (!empty($selectedUser)) {
    // Haetaan käyttäjän kategoriat
    $userSettingsFile = "user_settings/{$selectedUser}_settings.json";
    $categories = [];

    if (file_exists($userSettingsFile)) {
        $userSettings = json_decode(file_get_contents($userSettingsFile), true);
        if (isset($userSettings['categories'])) {
            $categories = $userSettings['categories'];
        }
    }

    if (empty($categories)) {
        $categories = [
            'yhteiskuvat' => 'Yhteiskuvat',
            'yksinkuvat' => 'Yksinkuvat',
            'rakkaan_kuvat' => 'Rakkaan kuvat',
            'ulko_kuvat' => 'Ulkokuvat',
            'muut_kuvat' => 'Muut kuvat'
        ];
    }

    // Haetaan käyttäjän tiedostot
    $userFileInfoPath = "user_settings/{$selectedUser}_file_info.txt";

    if (file_exists($userFileInfoPath)) {
        $fileInfos = file($userFileInfoPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($fileInfos as $fileInfo) {
            $info = explode(':', $fileInfo);
            if (count($info) >= 2) {
                $userFolder = 'uploads/' . $selectedUser . '/';
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
                
                // Suodata kategorian mukaan jos valittu
                if (!empty($selectedCategory) && $fileCategory !== $selectedCategory) {
                    continue;
                }
                
                $fileExtension = strtolower(pathinfo($info[1], PATHINFO_EXTENSION));
                $isImage = in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                $isVideo = in_array($fileExtension, ['mp4', 'webm', 'avi', 'mov', 'mkv']);
                
                // Päivitä laskurit
                $fileCount++;
                if ($isImage) $imageCount++;
                else if ($isVideo) $videoCount++;
                else $otherCount++;
                
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
        
        foreach ($fileInfos as $fileInfo) {
            $info = explode(':', $fileInfo);
            if (count($info) >= 3 && $info[0] === $selectedUser) {
                $userFolder = 'uploads/' . $selectedUser . '/';
                $uniqueFileName = $info[1];
                
                if (file_exists($userFolder . $uniqueFileName)) {
                    $filePath = $userFolder . $uniqueFileName;
                } else if (file_exists('uploads/' . $uniqueFileName)) {
                    $filePath = 'uploads/' . $uniqueFileName;
                } else {
                    $filePath = $userFolder . $uniqueFileName;
                }
                
                $fileCategory = isset($info[3]) ? $info[3] : 'muut_kuvat';
                
                // Suodata kategorian mukaan jos valittu
                if (!empty($selectedCategory) && $fileCategory !== $selectedCategory) {
                    continue;
                }
                
                $fileExtension = strtolower(pathinfo($info[2], PATHINFO_EXTENSION));
                $isImage = in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                $isVideo = in_array($fileExtension, ['mp4', 'webm', 'avi', 'mov', 'mkv']);
                
                // Päivitä laskurit
                $fileCount++;
                if ($isImage) $imageCount++;
                else if ($isVideo) $videoCount++;
                else $otherCount++;
                
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
            }
        }
    }

    // Järjestä tiedostot valitun tavan mukaan
    switch ($sortBy) {
        case 'name':
            usort($uploadedFiles, function($a, $b) {
                return strcasecmp($a['originalName'], $b['originalName']);
            });
            break;
        case 'size':
            usort($uploadedFiles, function($a, $b) {
                return $b['size'] - $a['size'];
            });
            break;
        case 'type':
            usort($uploadedFiles, function($a, $b) {
                return strcasecmp($a['extension'], $b['extension']);
            });
            break;
        case 'date':
        default:
            usort($uploadedFiles, function($a, $b) {
                return $b['date'] - $a['date'];
            });
            break;
    }
}

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
    <title>Käyttäjien tiedostot</title>
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
    /* User details panel styling */
    .user-details-panel {
        background: linear-gradient(to right, #f0e0ff, #f9f0ff);
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        border-left: 4px solid #a600ff;
    }
    
    .user-details-header {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
    }
    
    .user-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background-color: #a600ff;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        font-weight: bold;
        margin-right: 15px;
        box-shadow: 0 3px 6px rgba(0,0,0,0.2);
    }
    
    .user-name-role {
        flex: 1;
    }
    
    .user-name-role h3 {
        margin: 0 0 5px 0;
        color: #8400cc;
        font-size: 20px;
    }
    
    .user-role {
        display: inline-block;
        background-color: #a600ff;
        color: white;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: bold;
    }
    
    .user-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-top: 20px;
    }
    
    .stat-card {
        background-color: white;
        border-radius: 8px;
        padding: 15px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        display: flex;
        flex-direction: column;
    }
    
    .stat-label {
        font-size: 12px;
        color: #666;
        margin-bottom: 5px;
    }
    
    .stat-value {
        font-size: 16px;
        font-weight: bold;
        color: #8400cc;
    }
    
    .ip-address {
        font-family: monospace;
        background-color: #f5f5f5;
        padding: 2px 5px;
        border-radius: 3px;
    }
    
    .account-status {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: bold;
        background-color: #4cd137;
        color: white;
    }
    
    .account-status.inactive {
        background-color: #718093;
    }
    
    .account-status.suspended {
        background-color: #e84118;
    }

    /* Replace the file-actions styling with this more compact version */
    .file-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 15px;
    }

    .category-filter-container,
    .sort-filter-container {
        width: 180px;
    }

    .category-filter-container select,
    .sort-filter-container select {
        width: 100%;
        padding: 8px 12px;
        border-radius: 6px;
        border: 1px solid #d9c2ff;
        background-color: white;
        color: #333;
        font-size: 14px;
    }

    .view-toggle {
        display: flex;
        gap: 5px;
        margin-left: auto;
    }

    .view-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        padding: 8px 12px;
        border: none;
        border-radius: 6px;
        background-color: #f0f0f0;
        color: #666;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 14px;
        min-width: 100px;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .file-actions {
            flex-wrap: wrap;
        }
        
        .category-filter-container,
        .sort-filter-container {
            width: calc(50% - 5px);
        }
        
        .view-toggle {
            margin-left: 0;
            margin-top: 10px;
            width: 100%;
        }
        
        .view-btn {
            flex: 1;
        }
    }
    </style>
</head>
<body class="<?php echo $theme; ?>-theme">
    <?php include('header.php'); ?>

    <div class="container">
        <h2>Käyttäjien tiedostot</h2>
        
        <div class="user-files-container">
            <div class="user-sidebar">
                <div class="user-search">
                    <input type="text" id="userSearch" placeholder="Etsi käyttäjää..." onkeyup="searchUsers()">
                </div>
                
                <div class="user-list">
                    <h3>Käyttäjät <span class="user-count"><?php echo count($users); ?></span></h3>
                    <ul id="userListItems">
                        <?php foreach ($users as $user): ?>
                        <li>
                            <a href="?user=<?php echo urlencode($user['username']); ?>" class="user-list-item <?php echo $selectedUser === $user['username'] ? 'active' : ''; ?>">
                                <div class="user-list-info">
                                    <span class="user-list-name"><?php echo htmlspecialchars($user['username']); ?></span>
                                    <span class="user-list-role"><?php echo htmlspecialchars($user['role']); ?></span>
                                </div>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
            <div class="user-content">
                <?php if (!empty($selectedUser)): ?>
                    <?php 
                    // Find selected user details
                    $selectedUserDetails = null;
                    foreach ($users as $user) {
                        if ($user['username'] === $selectedUser) {
                            $selectedUserDetails = $user;
                            break;
                        }
                    }
                    ?>
                    
                    <?php if ($selectedUserDetails): ?>
                    <!-- User details panel -->
                    <div class="user-details-panel">
                        <div class="user-details-header">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($selectedUser, 0, 2)); ?>
                            </div>
                            <div class="user-name-role">
                                <h3><?php echo htmlspecialchars($selectedUser); ?></h3>
                                <span class="user-role"><?php echo htmlspecialchars($selectedUserDetails['role']); ?></span>
                                <span class="account-status"><?php echo $selectedUserDetails['accountStatus']; ?></span>
                            </div>
                        </div>
                        
                        <div class="user-stats">
                            <div class="stat-card">
                                <span class="stat-label">IP-osoite</span>
                                <span class="stat-value ip-address"><?php echo !empty($selectedUserDetails['ipAddress']) ? $selectedUserDetails['ipAddress'] : 'Ei tiedossa'; ?></span>
                            </div>
                            
                            <div class="stat-card">
                                <span class="stat-label">Viimeisin kirjautuminen</span>
                                <span class="stat-value"><?php echo !empty($selectedUserDetails['lastLoginTime']) ? $selectedUserDetails['lastLoginTime'] : 'Ei tiedossa'; ?></span>
                            </div>
                            
                            <div class="stat-card">
                                <span class="stat-label">Viimeisin tiedoston lataus</span>
                                <span class="stat-value"><?php echo !empty($selectedUserDetails['lastUploadTime']) ? $selectedUserDetails['lastUploadTime'] : 'Ei tiedossa'; ?></span>
                            </div>
                            
                            <div class="stat-card">
                                <span class="stat-label">Käyttäjätili luotu</span>
                                <span class="stat-value"><?php echo !empty($selectedUserDetails['creationDate']) ? $selectedUserDetails['creationDate'] : 'Ei tiedossa'; ?></span>
                            </div>
                            
                            <div class="stat-card">
                                <span class="stat-label">Tiedostojen määrä</span>
                                <span class="stat-value"><?php echo $selectedUserDetails['fileCount']; ?> tiedostoa</span>
                            </div>
                            
                            <div class="stat-card">
                                <span class="stat-label">Käytetty tallennustila</span>
                                <span class="stat-value"><?php echo formatFileSize($selectedUserDetails['storageUsed']); ?></span>
                            </div>
                            
                            <div class="stat-card">
                                <span class="stat-label">Selain</span>
                                <span class="stat-value"><?php echo isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 50) . '...' : 'Ei tiedossa'; ?></span>
                            </div>
                            
                            <div class="stat-card">
                                <span class="stat-label">Käyttöjärjestelmä</span>
                                <span class="stat-value"><?php 
                                    $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
                                    if (strpos($userAgent, 'Windows') !== false) echo 'Windows';
                                    elseif (strpos($userAgent, 'Mac') !== false) echo 'MacOS';
                                    elseif (strpos($userAgent, 'Linux') !== false) echo 'Linux';
                                    elseif (strpos($userAgent, 'Android') !== false) echo 'Android';
                                    elseif (strpos($userAgent, 'iPhone') !== false) echo 'iOS';
                                    else echo 'Ei tiedossa';
                                ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="user-content-header">
                        <h3>
                            <span class="user-content-stats">
                                Käyttäjällä <?php echo htmlspecialchars($selectedUser); ?> on <?php echo $fileCount; ?> tiedostoa - 
                                <?php echo $imageCount; ?> kuvaa, 
                                <?php echo $videoCount; ?> <?php echo $videoCount === 1 ? 'video' : 'videota'; ?>, 
                                <?php echo $otherCount; ?> muuta
                            </span>
                        </h3>
                        
                        <div class="file-actions">
                            <?php if (!empty($categories)): ?>
                            <div class="category-filter-container">
                                <select id="categoryFilter" onchange="window.location.href='?user=<?php echo urlencode($selectedUser); ?>&category='+this.value+'&sort=<?php echo $sortBy; ?>'">
                                    <option value="">Kaikki kategoriat</option>
                                    <?php foreach ($categories as $key => $name): ?>
                                    <option value="<?php echo $key; ?>" <?php echo $selectedCategory === $key ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($name); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <div class="sort-filter-container">
                                <select id="sortFilter" onchange="window.location.href='?user=<?php echo urlencode($selectedUser); ?>&category=<?php echo $selectedCategory; ?>&sort='+this.value">
                                    <option value="date" <?php echo $sortBy === 'date' ? 'selected' : ''; ?>>Uusimmat ensin</option>
                                    <option value="name" <?php echo $sortBy === 'name' ? 'selected' : ''; ?>>Nimen mukaan</option>
                                    <option value="size" <?php echo $sortBy === 'size' ? 'selected' : ''; ?>>Koon mukaan</option>
                                    <option value="type" <?php echo $sortBy === 'type' ? 'selected' : ''; ?>>Tyypin mukaan</option>
                                </select>
                            </div>
                            
                            <div class="view-toggle">
                                <button id="gridViewBtn" class="view-btn active" onclick="switchView('grid')">
                                    <span class="view-icon">⊞</span>
                                    Ruudukko
                                </button>
                                <button id="listViewBtn" class="view-btn" onclick="switchView('list')">
                                    <span class="view-icon">≡</span>
                                    Lista
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (empty($uploadedFiles)): ?>
                        <div class="no-files-message">
                            <div class="message-icon">📂</div>
                            <p>Käyttäjällä ei ole tiedostoja<?php echo !empty($selectedCategory) ? ' valitussa kategoriassa' : ''; ?>.</p>
                            <button class="back-btn" onclick="clearFilters()">Näytä kaikki tiedostot</button>
                        </div>
                    <?php else: ?>
                        <div id="fileGrid" class="file-grid">
                            <?php foreach ($uploadedFiles as $file): ?>
                                <div class="file-card" data-filename="<?php echo htmlspecialchars($file['originalName']); ?>">
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
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div id="fileList" class="file-list-view" style="display: none;">
                            <table class="files-table">
                                <thead>
                                    <tr>
                                        <th>Tiedosto</th>
                                        <th>Kategoria</th>
                                        <th>Koko</th>
                                        <th>Päivämäärä</th>
                                        <th>Toiminnot</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($uploadedFiles as $file): ?>
                                    <tr>
                                        <td class="file-name-cell">
                                            <div class="file-icon">
                                                <?php if ($file['isImage']): ?>
                                                    <span class="file-icon-img">🖼️</span>
                                                <?php elseif ($file['isVideo']): ?>
                                                    <span class="file-icon-video">🎬</span>
                                                <?php else: ?>
                                                    <span class="file-icon-doc">📄</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php echo htmlspecialchars($file['originalName']); ?>
                                        </td>
                                        <td><?php echo isset($categories[$file['category']]) ? htmlspecialchars($categories[$file['category']]) : 'Muu'; ?></td>
                                        <td><?php echo formatFileSize($file['size']); ?></td>
                                        <td><?php echo date('d.m.Y H:i', $file['date']); ?></td>
                                        <td>
                                            <a href="<?php echo htmlspecialchars($file['path']); ?>" target="_blank" class="view-btn">Avaa</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="select-user-message">
                        <div class="message-icon">👈</div>
                        <h3>Valitse käyttäjä nähdäksesi tiedostot</h3>
                        <p>Valitse käyttäjä vasemmalta nähdäksesi hänen tiedostonsa.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
    function searchUsers() {
        const input = document.getElementById('userSearch');
        const filter = input.value.toUpperCase();
        const userList = document.getElementById('userListItems');
        const users = userList.getElementsByTagName('li');
        
        for (let i = 0; i < users.length; i++) {
            const userName = users[i].querySelector('.user-list-name').textContent;
            if (userName.toUpperCase().indexOf(filter) > -1) {
                users[i].style.display = "";
            } else {
                users[i].style.display = "none";
            }
        }
    }

    function switchView(viewType) {
        const gridView = document.getElementById('fileGrid');
        const listView = document.getElementById('fileList');
        const gridBtn = document.getElementById('gridViewBtn');
        const listBtn = document.getElementById('listViewBtn');
        
        if (viewType === 'grid') {
            gridView.style.display = 'grid';
            listView.style.display = 'none';
            gridBtn.classList.add('active');
            listBtn.classList.remove('active');
        if (viewType === 'grid') {
            gridView.style.display = 'grid';
            listView.style.display = 'none';
            gridBtn.classList.add('active');
            listBtn.classList.remove('active');
            localStorage.setItem('fileViewPreference', 'grid');
        } else {
            gridView.style.display = 'none';
            listView.style.display = 'block';
            gridBtn.classList.remove('active');
            listBtn.classList.add('active');
            localStorage.setItem('fileViewPreference', 'list');
        }
    }

    function clearFilters() {
        window.location.href = '?user=<?php echo urlencode($selectedUser); ?>';
    }

    document.addEventListener("DOMContentLoaded", function() {
        // Load preferred view
        const viewPreference = localStorage.getItem('fileViewPreference');
        if (viewPreference === 'list') {
            switchView('list');
        }
        
        // Add animation to file cards
        const fileCards = document.querySelectorAll('.file-card');
        fileCards.forEach((card, index) => {
            card.style.animationDelay = (index * 0.05) + 's';
            card.classList.add('animated');
        });
    });
    </script>
</body>
</html>

