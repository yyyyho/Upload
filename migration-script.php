<?php
// Tämä skripti siirtää tiedostotiedot vanhasta yhteisestä file_info.txt tiedostosta
// käyttäjäkohtaisiin tiedostoihin user_settings kansiossa

// Varmistetaan, että user_settings-kansio on olemassa
if (!is_dir('user_settings')) {
    mkdir('user_settings', 0777, true);
    echo "Luotiin user_settings kansio.<br>";
}

// Tarkistetaan, että vanha tiedosto on olemassa
if (!file_exists('file_info.txt')) {
    die("Vanhaa file_info.txt tiedostoa ei löydy. Ei mitään siirrettävää.");
}

// Luetaan vanha tiedosto
$fileInfos = file('file_info.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (empty($fileInfos)) {
    die("Vanha file_info.txt tiedosto on tyhjä. Ei mitään siirrettävää.");
}

// Järjestetään tiedot käyttäjittäin
$userFiles = [];
foreach ($fileInfos as $fileInfo) {
    $info = explode(':', $fileInfo);
    if (count($info) >= 3) {
        $username = $info[0];
        $uniqueName = $info[1];
        $originalName = $info[2];
        $category = isset($info[3]) ? $info[3] : 'muut_kuvat';
        
        if (!isset($userFiles[$username])) {
            $userFiles[$username] = [];
        }
        
        $userFiles[$username][] = "$uniqueName:$originalName:$category";
    }
}

// Tallennetaan tiedot käyttäjäkohtaisiin tiedostoihin
$migratedUsers = 0;
$migratedFiles = 0;

foreach ($userFiles as $username => $files) {
    $userFileInfoPath = "user_settings/{$username}_file_info.txt";
    
    // Tarkistetaan, onko käyttäjäkohtainen tiedosto jo olemassa
    if (file_exists($userFileInfoPath)) {
        // Jos on, luetaan olemassa olevat tiedot
        $existingFiles = file($userFileInfoPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $existingUniqueNames = [];
        
        foreach ($existingFiles as $existingFile) {
            $existingInfo = explode(':', $existingFile);
            if (count($existingInfo) >= 1) {
                $existingUniqueNames[] = $existingInfo[0];
            }
        }
        
        // Lisätään vain uudet tiedostot
        $newFiles = [];
        foreach ($files as $file) {
            $fileInfo = explode(':', $file);
            if (!in_array($fileInfo[0], $existingUniqueNames)) {
                $newFiles[] = $file;
                $migratedFiles++;
            }
        }
        
        if (!empty($newFiles)) {
            file_put_contents($userFileInfoPath, implode("\n", $newFiles) . "\n", FILE_APPEND);
        }
    } else {
        // Jos ei ole, luodaan uusi tiedosto
        file_put_contents($userFileInfoPath, implode("\n", $files) . "\n");
        $migratedFiles += count($files);
    }
    
    $migratedUsers++;
}

echo "Siirto valmis!<br>";
echo "Käsiteltiin $migratedUsers käyttäjän tiedot.<br>";
echo "Siirrettiin yhteensä $migratedFiles tiedostoa.<br>";
echo "<a href='upload.php'>Palaa etusivulle</a>";
?>

