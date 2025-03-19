<?php
session_start();
include('log.php');  // Lokitiedoston kirjoittaminen

// Tarkistetaan, että käyttäjä on kirjautunut sisään
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
        'language' => 'fi',
        'notifications' => 'on',
        'fileSort' => 'name',
        'pageSize' => 10,
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

// Käyttäjän kategoriat
$categories = isset($userSettings['categories']) ? $userSettings['categories'] : [
    'yhteiskuvat' => 'Yhteiskuvat',
    'yksinkuvat' => 'Yksinkuvat',
    'rakkaan_kuvat' => 'Rakkaan kuvat',
    'ulko_kuvat' => 'Ulkokuvat',
    'muut_kuvat' => 'Muut kuvat'
];

// Latauksen käsittely
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload'])) {
    $category = isset($_POST['category']) ? $_POST['category'] : 'muut_kuvat';
    $uploadedFiles = 0;
    $errorCount = 0;
    
    // Käsitellään useita tiedostoja
    $fileCount = count($_FILES['files']['name']);
    
    // Varmistetaan, että käyttäjän kansio on olemassa
    $userUploadDir = 'uploads/' . $username . '/';
    if (!is_dir($userUploadDir)) {
        mkdir($userUploadDir, 0777, true);
    }
    
    // Haetaan käyttäjän tiedostojen nimeämisasetus
    $fileNamingOption = isset($userSettings['fileNamingOption']) ? $userSettings['fileNamingOption'] : 'datetime';
    $customPrefix = isset($userSettings['customPrefix']) ? $userSettings['customPrefix'] : 'Tiedosto_';
    
    // Alustetaan numerointi, jos käytetään numeroitua nimeämistä
    $fileNumber = 1;
    if ($fileNamingOption === 'numbered') {
        // Haetaan viimeisin numero käyttäjän tiedostoista
        $userFileInfoPath = "user_settings/{$username}_file_info.txt";
        if (file_exists($userFileInfoPath)) {
            $fileInfos = file($userFileInfoPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($fileInfos as $fileInfo) {
                $info = explode(':', $fileInfo);
                if (count($info) >= 2) {
                    $fileName = $info[1];
                    if (preg_match('/^(\d+)\./', $fileName, $matches)) {
                        $num = (int)$matches[1];
                        if ($num >= $fileNumber) {
                            $fileNumber = $num + 1;
                        }
                    }
                }
            }
        }
    }
    
    for ($i = 0; $i < $fileCount; $i++) {
        if ($_FILES['files']['error'][$i] === 0) {
            $originalFileName = $_FILES['files']['name'][$i];
            $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
            $allowedImageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            // Tarkistetaan onko tiedosto kuva
            $isImage = in_array($fileExtension, $allowedImageTypes);
            
            // Luodaan uniikki tiedostonimi tallennusta varten
            $uniqueFileName = uniqid(time() . "_", true) . "." . $fileExtension;
            $uploadFile = $userUploadDir . $uniqueFileName;
            
            // Määritetään näytettävä tiedostonimi käyttäjän asetusten mukaan
            $displayFileName = $originalFileName; // Oletuksena alkuperäinen nimi
            
            if ($isImage || $fileNamingOption !== 'original') {
                switch ($fileNamingOption) {
                    case 'original':
                        $displayFileName = $originalFileName;
                        break;
                    case 'datetime':
                        $dateFormatted = date("Y-m-d_H-i-s");
                        $displayFileName = $dateFormatted . ($fileCount > 1 ? "_" . ($i + 1) : "") . "." . $fileExtension;
                        break;
                    case 'numbered':
                        $displayFileName = ($fileNumber + $i) . "." . $fileExtension;
                        break;
                    case 'uuid':
                        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                            mt_rand(0, 0xffff),
                            mt_rand(0, 0x0fff) | 0x4000,
                            mt_rand(0, 0x3fff) | 0x8000,
                            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                        );
                        $displayFileName = $uuid . "." . $fileExtension;
                        break;
                    case 'custom_prefix':
                        $displayFileName = $customPrefix . ($fileNumber + $i) . "." . $fileExtension;
                        break;
                }
            }

            if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $uploadFile)) {
                // Tallennetaan tiedoston tiedot tekstitiedostoon
                // Lisätään kategoria tiedostotietoihin
                $userFileInfoPath = "user_settings/{$username}_file_info.txt";
                $fileInfo = "$uniqueFileName:$displayFileName:$category\n";
                file_put_contents($userFileInfoPath, $fileInfo, FILE_APPEND);
                
                writeLog("$username latasi tiedoston", $displayFileName, 'upload');
                $uploadedFiles++;
            } else {
                $errorCount++;
            }
        } else {
            $errorCount++;
        }
    }
    
    if ($uploadedFiles > 0) {
        $successMessage = "Ladattu onnistuneesti $uploadedFiles tiedostoa!";
    }
    
    if ($errorCount > 0) {
        $errorMessage = "Virhe $errorCount tiedoston latauksessa.";
    }
}

// Haetaan käyttäjän lataamat tiedostot
$uploadedFiles = [];
$userFileInfoPath = "user_settings/{$username}_file_info.txt";

// Tarkistetaan ensin käyttäjäkohtainen tiedosto
if (file_exists($userFileInfoPath)) {
    $fileInfos = file($userFileInfoPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($fileInfos as $fileInfo) {
        $info = explode(':', $fileInfo);
        if (count($info) >= 3) {
            $userFolder = 'uploads/' . $username . '/';
            $filePath = file_exists($userFolder . $info[0]) ? $userFolder . $info[0] : 'uploads/' . $info[0];
            
            $fileCategory = isset($info[2]) ? $info[2] : 'muut_kuvat';
            $uploadedFiles[] = [
                'uniqueName' => $info[0],
                'originalName' => $info[1],
                'category' => $fileCategory,
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
        if (count($info) >= 3 && $info[0] === $username) {
            // Tarkistetaan tiedoston sijainti (käyttäjäkansio tai vanha sijainti)
            $userFolder = 'uploads/' . $username . '/';
            $filePath = file_exists($userFolder . $info[1]) ? $userFolder . $info[1] : 'uploads/' . $info[1];
            
            $fileCategory = isset($info[3]) ? $info[3] : 'muut_kuvat';
            $uploadedFiles[] = [
                'uniqueName' => $info[1],
                'originalName' => $info[2],
                'category' => $fileCategory,
                'path' => $filePath
            ];
            
            // Siirretään tiedot käyttäjäkohtaiseen tiedostoon
            $userFileInfo = "{$info[1]}:{$info[2]}:{$fileCategory}\n";
            file_put_contents($userFileInfoPath, $userFileInfo, FILE_APPEND);
        }
    }
}

// Rajoitetaan näytettävien tiedostojen määrää etusivulla
$recentFiles = array_slice($uploadedFiles, 0, 5);
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/S7JLkFy1/Heartcrown.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tiedoston lataus</title>
    <link rel="stylesheet" href="style.css">
    <?php if ($theme === 'dark'): ?>
    <link rel="stylesheet" href="dark-theme.css">
    <?php endif; ?>
</head>
<body class="<?php echo $theme; ?>-theme">
    <?php include('header.php'); ?>

    <div class="container">
        <?php if (isset($successMessage)): ?>
            <p class="success"><?php echo $successMessage; ?></p>
        <?php endif; ?>
        
        <?php if (isset($errorMessage)): ?>
            <p class="error"><?php echo $errorMessage; ?></p>
        <?php endif; ?>
        
        <h2>Lataa tiedostoja</h2>
        <form method="POST" enctype="multipart/form-data" class="upload-form" id="uploadForm">
            <div class="file-input-container">
                <div class="file-drop-area">
                    <span class="file-msg">Raahaa tiedostot tähän tai</span>
                    <button type="button" class="file-select-button">Valitse tiedostot</button>
                    <input type="file" name="files[]" id="files" required class="file-input" onchange="handleFileSelect(this)" multiple>
                </div>
                <div id="selected-files" class="selected-files">
                    <p>Ei tiedostoja valittu</p>
                </div>
                
                <div id="category-selection" class="category-selection" style="display: none;">
                    <label for="category">Valitse kategoria:</label>
                    <select name="category" id="category">
                        <?php foreach ($categories as $value => $label): ?>
                            <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="category-info">Kuvat nimetään automaattisesti päivämäärän mukaan.</p>
                    
                    <input type="submit" name="upload" value="Lataa tiedostot" class="upload-button">
                </div>
            </div>
        </form>
        
        <?php if (!empty($recentFiles)): ?>
            <h3>Viimeisimmät lataukset</h3>
            <ul class="file-list">
                <?php foreach ($recentFiles as $file): ?>
                    <li>
                        <a href="<?php echo $file['path']; ?>" target="_blank">
                            <?php echo htmlspecialchars($file['originalName']); ?>
                        </a>
                        <span class="file-category"><?php echo isset($categories[$file['category']]) ? $categories[$file['category']] : 'Muu'; ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (count($uploadedFiles) > 5): ?>
                <a href="files.php" class="view-all-files">Näytä kaikki tiedostot</a>
            <?php endif; ?>
        <?php else: ?>
            <p>Et ole vielä ladannut yhtään tiedostoa.</p>
        <?php endif; ?>
    </div>

    <script src="script.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const dropArea = document.querySelector('.file-drop-area');
        const fileInput = document.querySelector('.file-input');
        const fileButton = document.querySelector('.file-select-button');
        
        // Trigger file input when button is clicked
        fileButton.addEventListener('click', function() {
            fileInput.click();
        });
        
        // Highlight drop area when dragging files over it
        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, unhighlight, false);
        });
        
        // Handle dropped files
        dropArea.addEventListener('drop', handleDrop, false);
        
        function highlight(e) {
            e.preventDefault();
            e.stopPropagation();
            dropArea.classList.add('highlight');
        }
        
        function unhighlight(e) {
            e.preventDefault();
            e.stopPropagation();
            dropArea.classList.remove('highlight');
        }
        
        function handleDrop(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const dt = e.dataTransfer;
            const files = dt.files;
            
            fileInput.files = files;
            handleFileSelect(fileInput);
        }
    });
    
    function handleFileSelect(input) {
    const selectedFiles = document.getElementById('selected-files');
    const categorySelection = document.getElementById('category-selection');
    const files = input.files;
    
    if (files.length > 0) {
        // Näytä valitut tiedostot
        let fileListHTML = '<ul class="selected-file-list">';
        let imageCount = 0;
        
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const fileExtension = file.name.split('.').pop().toLowerCase();
            const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileExtension);
            
            if (isImage) {
                imageCount++;
            }
            
            fileListHTML += `<li>
                ${file.name} (${formatFileSize(file.size)})
                <button type="button" class="remove-file-btn" onclick="removeFile(${i})">Poista</button>
            </li>`;
        }
        
        fileListHTML += '</ul>';
        selectedFiles.innerHTML = fileListHTML;
        
        // Näytä kategoria-valinta jos on kuvia
        if (imageCount > 0) {
            categorySelection.style.display = 'block';
            document.querySelector('.category-info').textContent = 
                `${imageCount} kuvaa nimetään automaattisesti päivämäärän mukaan.`;
        } else {
            categorySelection.style.display = 'block';
            document.querySelector('.category-info').textContent = 
                'Ei kuvia valittu. Kategoria vaikuttaa vain kuviin.';
        }
    } else {
        selectedFiles.innerHTML = '<p>Ei tiedostoja valittu</p>';
        categorySelection.style.display = 'none';
    }
}

function removeFile(index) {
    const fileInput = document.getElementById('files');
    const dt = new DataTransfer();
    const files = fileInput.files;
    
    for (let i = 0; i < files.length; i++) {
        if (i !== index) {
            dt.items.add(files[i]);
        }
    }
    
    fileInput.files = dt.files;
    handleFileSelect(fileInput);
}
    
    function formatFileSize(bytes) {
        if (bytes < 1024) {
            return bytes + ' B';
        } else if (bytes < 1048576) {
            return (bytes / 1024).toFixed(2) + ' KB';
        } else {
            return (bytes / 1048576).toFixed(2) + ' MB';
        }
    }
    </script>
</body>
</html>

