<?php
session_start();
include('log.php');

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
      ],
      'avatarColor' => '#a600ff',
      'viewMode' => 'grid',
      'autoDownload' => false,
      'confirmDelete' => true,
      // Lisätään uusia oletusasetuksia
      'showFileInfo' => true,
      'enableNotifications' => true,
      'defaultCategory' => 'muut_kuvat',
      'customThemeColor' => '#a600ff',
      // Uudet asetukset
      'enableAnimations' => true,
      'showFileCount' => true,
      'enableKeyboardShortcuts' => false,
      'interfaceLanguage' => 'fi'
  ];
  
  // Varmistetaan, että user_settings-kansio on olemassa
  if (!is_dir('user_settings')) {
      mkdir('user_settings', 0777, true);
  }
  
  // Tallennetaan oletusasetukset
  file_put_contents($userSettingsFile, json_encode($userSettings, JSON_PRETTY_PRINT));
}

$theme = $userSettings['theme'];

// Käyttäjän profiiliväri
$avatarColor = isset($userSettings['avatarColor']) ? $userSettings['avatarColor'] : '#a600ff';

// Näkymätila (ruudukko vai lista)
$viewMode = isset($userSettings['viewMode']) ? $userSettings['viewMode'] : 'grid';

// Automaattinen tiedostojen lataus
$autoDownload = isset($userSettings['autoDownload']) ? $userSettings['autoDownload'] : false;

// Poiston vahvistus
$confirmDelete = isset($userSettings['confirmDelete']) ? $userSettings['confirmDelete'] : true;

// Uudet asetukset
$showFileInfo = isset($userSettings['showFileInfo']) ? $userSettings['showFileInfo'] : true;
$enableNotifications = isset($userSettings['enableNotifications']) ? $userSettings['enableNotifications'] : true;
$defaultCategory = isset($userSettings['defaultCategory']) ? $userSettings['defaultCategory'] : 'muut_kuvat';
$customThemeColor = isset($userSettings['customThemeColor']) ? $userSettings['customThemeColor'] : '#a600ff';

// Uudet lisätyt asetukset
$enableAnimations = isset($userSettings['enableAnimations']) ? $userSettings['enableAnimations'] : true;
$showFileCount = isset($userSettings['showFileCount']) ? $userSettings['showFileCount'] : true;
$enableKeyboardShortcuts = isset($userSettings['enableKeyboardShortcuts']) ? $userSettings['enableKeyboardShortcuts'] : false;
$interfaceLanguage = isset($userSettings['interfaceLanguage']) ? $userSettings['interfaceLanguage'] : 'fi';

// Helper function to adjust color brightness
function adjustBrightness($hex, $steps) {
    // Convert hex to rgb
    $hex = str_replace('#', '', $hex);
    if(strlen($hex) == 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    // Adjust brightness
    $r = max(0, min(255, $r + $steps));
    $g = max(0, min(255, $g + $steps));
    $b = max(0, min(255, $b + $steps));

    // Convert back to hex
    return '#' . sprintf('%02x%02x%02x', $r, $g, $b);
}

// Käyttäjänimen vaihto
$usernameChanged = false;
$usernameError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_username'])) {
  $newUsername = trim($_POST['new_username']);
  $currentPassword = $_POST['current_password_for_username'];
  
  // Tarkistetaan, että uusi käyttäjänimi on kelvollinen
  if (empty($newUsername)) {
      $usernameError = "Käyttäjänimi ei voi olla tyhjä.";
  } elseif (preg_match('/[^a-zA-Z0-9_]/', $newUsername)) {
      $usernameError = "Käyttäjänimi voi sisältää vain kirjaimia, numeroita ja alaviivoja.";
  } else {
      // Tarkistetaan, että käyttäjänimi ei ole jo käytössä
      $userExists = false;
      if (file_exists('users.txt')) {
          $users = file('users.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
          foreach ($users as $user) {
              $userData = explode(':', $user);
              if ($userData[0] === $newUsername) {
                  $userExists = true;
                  break;
              }
          }
      }
      
      if ($userExists) {
          $usernameError = "Käyttäjänimi on jo käytössä.";
      } else {
          // Tarkistetaan nykyinen salasana
          $currentUserPassword = '';
          $users = file('users.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
          $userFound = false;
          
          foreach ($users as $user) {
              $userData = explode(':', $user);
              if ($userData[0] === $username) {
                  $userFound = true;
                  $currentUserPassword = $userData[1];
                  break;
              }
          }
          
          if (!$userFound || $currentPassword !== $currentUserPassword) {
              $usernameError = "Nykyinen salasana on virheellinen.";
          } else {
              // Päivitetään käyttäjänimi users.txt-tiedostossa
              $updatedUsers = [];
              foreach ($users as $user) {
                  $userData = explode(':', $user);
                  if ($userData[0] === $username) {
                      $updatedUsers[] = $newUsername . ':' . $userData[1] . ':' . (isset($userData[2]) ? $userData[2] : 'user');
                  } else {
                      $updatedUsers[] = $user;
                  }
              }
              file_put_contents('users.txt', implode("\n", $updatedUsers) . "\n");
              
              // Päivitetään tiedostojen omistajuus
              if (file_exists('file_info.txt')) {
                  $fileInfos = file('file_info.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                  $newFileInfos = [];
                  foreach ($fileInfos as $fileInfo) {
                      $info = explode(':', $fileInfo);
                      if ($info[0] === $username) {
                          $info[0] = $newUsername;
                          $newFileInfos[] = implode(':', $info);
                      } else {
                          $newFileInfos[] = $fileInfo;
                      }
                  }
                  file_put_contents('file_info.txt', implode("\n", $newFileInfos) . "\n");
              }
              
              // Siirretään käyttäjän tiedostokansio
              $oldUploadDir = 'uploads/' . $username . '/';
              $newUploadDir = 'uploads/' . $newUsername . '/';
              if (is_dir($oldUploadDir)) {
                  if (!is_dir($newUploadDir)) {
                      mkdir($newUploadDir, 0777, true);
                  }
                  
                  // Kopioidaan tiedostot uuteen kansioon
                  $files = glob($oldUploadDir . '*');
                  foreach ($files as $file) {
                      if (is_file($file)) {
                          $fileName = basename($file);
                          copy($file, $newUploadDir . $fileName);
                          unlink($file); // Poistetaan alkuperäinen
                      }
                  }
                  
                  // Poistetaan vanha kansio
                  rmdir($oldUploadDir);
              }
              
              // Siirretään käyttäjän asetukset
              $newUserSettingsFile = "user_settings/{$newUsername}_settings.json";
              if (file_exists($userSettingsFile)) {
                  copy($userSettingsFile, $newUserSettingsFile);
                  unlink($userSettingsFile);
              }
              
              // Siirretään käyttäjän tiedostotiedot
              $oldUserFileInfoPath = "user_settings/{$username}_file_info.txt";
              $newUserFileInfoPath = "user_settings/{$newUsername}_file_info.txt";
              if (file_exists($oldUserFileInfoPath)) {
                  copy($oldUserFileInfoPath, $newUserFileInfoPath);
                  unlink($oldUserFileInfoPath);
              }
              
              // Päivitetään sessio
              $_SESSION['user_id'] = $newUsername;
              
              // Kirjataan lokiin
              writeLog("Käyttäjä $username vaihtoi käyttäjänimensä: $newUsername", "", 'security');
              
              $usernameChanged = true;
              $username = $newUsername; // Päivitetään käyttäjänimi tällä sivulla
          }
      }
  }
}

// Salasanan vaihto
$passwordChanged = false;
$passwordError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
  $currentPassword = $_POST['current_password'];
  $newPassword = $_POST['new_password'];
  $confirmPassword = $_POST['confirm_password'];
  
  // Tarkistetaan, että uusi salasana ja vahvistus täsmäävät
  if ($newPassword !== $confirmPassword) {
      $passwordError = "Uusi salasana ja vahvistus eivät täsmää.";
  } 
  // Tarkistetaan salasanan pituus
  elseif (strlen($newPassword) < 6) {
      $passwordError = "Uuden salasanan tulee olla vähintään 6 merkkiä pitkä.";
  } 
  else {
      // Tarkistetaan nykyinen salasana
      $usersFile = 'users.txt';
      $users = file($usersFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      $userFound = false;
      $newUsers = [];
      
      foreach ($users as $user) {
          $userData = explode(':', $user);
          if ($userData[0] === $username) {
              $userFound = true;
              // Tarkistetään nykyinen salasana (plain text)
              if ($userData[1] === $currentPassword) {
                  // Päivitetään salasana
                  $newUsers[] = $userData[0] . ':' . $newPassword . ':' . (isset($userData[2]) ? $userData[2] : 'user');
                  $passwordChanged = true;
                  writeLog("$username vaihtoi salasanansa", "", 'security');
              } else {
                  $passwordError = "Nykyinen salasana on virheellinen.";
                  $newUsers[] = $user;
              }
          } else {
              $newUsers[] = $user;
          }
      }
      
      if ($userFound && $passwordChanged) {
          // Tallennetaan päivitetyt käyttäjätiedot
          file_put_contents($usersFile, implode("\n", $newUsers) . "\n");
      } elseif (!$userFound) {
          $passwordError = "Käyttäjää ei löydy.";
      }
  }
}

// Teeman vaihto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_theme'])) {
  $newTheme = $_POST['theme'];
  if (in_array($newTheme, ['light', 'dark', 'puhelin', 'puhelin-dark', 'purple', 'blue'])) {
      $userSettings['theme'] = $newTheme;
      file_put_contents($userSettingsFile, json_encode($userSettings, JSON_PRETTY_PRINT));
      $theme = $newTheme;
      $themeChanged = true;
  }
  
  // Tallennetaan mukautettu teemaväri
  if (isset($_POST['custom_theme_color'])) {
      $userSettings['customThemeColor'] = $_POST['custom_theme_color'];
      file_put_contents($userSettingsFile, json_encode($userSettings, JSON_PRETTY_PRINT));
      $customThemeColor = $_POST['custom_theme_color'];
  }
}

// Tiedostojen järjestys
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_file_sort'])) {
  $newSort = $_POST['file_sort'];
  $validSorts = ['name', 'date', 'size', 'category', 'newest', 'oldest'];
  if (in_array($newSort, $validSorts)) {
      $userSettings['fileSort'] = $newSort;
      file_put_contents($userSettingsFile, json_encode($userSettings, JSON_PRETTY_PRINT));
      $sortChanged = true;
  }
}

// Sivun koko
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_page_size'])) {
  $newPageSize = (int)$_POST['page_size'];
  if ($newPageSize > 0 && $newPageSize <= 100) {
      $userSettings['pageSize'] = $newPageSize;
      file_put_contents($userSettingsFile, json_encode($userSettings, JSON_PRETTY_PRINT));
      $pageSizeChanged = true;
  }
}

// Tiedostojen nimeämisasetukset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_file_naming'])) {
  $newNamingOption = $_POST['file_naming_option'];
  $validOptions = ['original', 'datetime', 'numbered', 'uuid', 'custom_prefix'];
  
  if (in_array($newNamingOption, $validOptions)) {
      $userSettings['fileNamingOption'] = $newNamingOption;
      
      // Jos valittiin custom_prefix, tallenna myös etuliite
      if ($newNamingOption === 'custom_prefix' && isset($_POST['custom_prefix'])) {
          $userSettings['customPrefix'] = trim($_POST['custom_prefix']);
      }
      
      file_put_contents($userSettingsFile, json_encode($userSettings, JSON_PRETTY_PRINT));
      $fileNamingChanged = true;
  }
}

// Käyttöliittymäasetukset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_ui_settings'])) {
  // Profiilivärin asettaminen
  if (isset($_POST['avatar_color'])) {
      $userSettings['avatarColor'] = $_POST['avatar_color'];
  }
  
  // Automaattinen lataus
  $userSettings['autoDownload'] = isset($_POST['auto_download']);
  
  // Poiston vahvistus
  $userSettings['confirmDelete'] = isset($_POST['confirm_delete']);
  
  // Uudet asetukset
  $userSettings['showFileInfo'] = isset($_POST['show_file_info']);
  $userSettings['enableNotifications'] = isset($_POST['enable_notifications']);
  $userSettings['defaultCategory'] = $_POST['default_category'];
  
  // Uudet lisätyt asetukset
  $userSettings['enableAnimations'] = isset($_POST['enable_animations']);
  $userSettings['showFileCount'] = isset($_POST['show_file_count']);
  $userSettings['enableKeyboardShortcuts'] = isset($_POST['enable_keyboard_shortcuts']);
  
  // Make sure interfaceLanguage is set with a default value if not present in POST
  $userSettings['interfaceLanguage'] = isset($_POST['interface_language']) ? $_POST['interface_language'] : 'fi';
  
  file_put_contents($userSettingsFile, json_encode($userSettings, JSON_PRETTY_PRINT));
  $uiSettingsChanged = true;
  
  // Päivitetään muuttujat
  $avatarColor = $userSettings['avatarColor'];
  $autoDownload = $userSettings['autoDownload'];
  $confirmDelete = $userSettings['confirmDelete'];
  $showFileInfo = $userSettings['showFileInfo'];
  $enableNotifications = $userSettings['enableNotifications'];
  $defaultCategory = $userSettings['defaultCategory'];
  $enableAnimations = $userSettings['enableAnimations'];
  $showFileCount = $userSettings['showFileCount'];
  $enableKeyboardShortcuts = $userSettings['enableKeyboardShortcuts'];
  $interfaceLanguage = $userSettings['interfaceLanguage'];
}

// Tiedostonäkymän asetukset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_view_settings'])) {
    // Get the view mode from the hidden input that's updated by JavaScript
    if (isset($_POST['view_mode']) && in_array($_POST['view_mode'], ['grid', 'list'])) {
        $userSettings['viewMode'] = $_POST['view_mode'];
        $viewMode = $_POST['view_mode'];
    }
    
    // Save preview preference
    $userSettings['hidePreview'] = !isset($_POST['show_preview']);
    
    file_put_contents($userSettingsFile, json_encode($userSettings, JSON_PRETTY_PRINT));
    $viewSettingsChanged = true;
}
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/S7JLkFy1/Heartcrown.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Käyttäjäasetukset</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="reduced-animations.css">
    <?php if ($theme === 'dark'): ?>
    <link rel="stylesheet" href="dark-theme.css">
    <?php elseif ($theme === 'puhelin'): ?>
    <link rel="stylesheet" href="mobile-theme.css">
    <?php elseif ($theme === 'puhelin-dark'): ?>
    <link rel="stylesheet" href="dark-mobile-theme.css">
    <?php elseif ($theme === 'purple'): ?>
    <link rel="stylesheet" href="purple-theme.css">
    <?php elseif ($theme === 'blue'): ?>
    <link rel="stylesheet" href="blue-theme.css">
    <?php endif; ?>

    <!-- Make sure dark-mobile-theme.css exists and is loaded correctly -->
    <?php if ($theme === 'puhelin-dark'): ?>
    <style>
    /* Fallback dark mobile styles in case the external file fails to load */
    :root {
        --bg-color: #1a1a1a;
        --text-color: #ffffff;
        --border-color: #333333;
        --input-bg: #2a2a2a;
        --hover-bg: #333333;
    }

    body.puhelin-dark-theme {
        background-color: var(--bg-color);
        color: var(--text-color);
    }

    .puhelin-dark-theme .container {
        background-color: var(--bg-color);
    }

    .puhelin-dark-theme input,
    .puhelin-dark-theme select,
    .puhelin-dark-theme textarea {
        background-color: var(--input-bg);
        color: var(--text-color);
        border-color: var(--border-color);
    }

    .puhelin-dark-theme .settings-section {
        background-color: var(--input-bg);
        border-color: var(--border-color);
    }

    .puhelin-dark-theme .settings-button {
        background-color: var(--primary-color);
        color: white;
    }

    .puhelin-dark-theme .settings-button:hover {
        background-color: var(--primary-hover);
    }
    </style>
    <?php endif; ?>
    
    <!-- Custom theme color application -->
    <style>
    :root {
      --primary-color: <?php echo $customThemeColor; ?>;
      --primary-hover: <?php echo adjustBrightness($customThemeColor, -20); ?>;
      --primary-light: <?php echo adjustBrightness($customThemeColor, 40); ?>;
      --primary-transparent: <?php echo $customThemeColor . '40'; ?>;
      --primary-color-rgb: <?php 
        $hex = str_replace('#', '', $customThemeColor);
        if(strlen($hex) == 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        echo "$r, $g, $b";
      ?>;
    }
    
    .menu-button, .logout-link, input[type="submit"], .settings-button, .upload-button, 
    .file-select-button, .back-btn, .view-btn.active, .view-btn:hover,
    .user-count, .category-btn, .manage-categories-btn, .add-category-btn {
      background-color: var(--primary-color);
    }
    
    .menu-button:hover, .logout-link:hover, input[type="submit"]:hover, 
    .settings-button:hover, .upload-button:hover, .file-select-button:hover, 
    .back-btn:hover, .category-btn:hover, .manage-categories-btn:hover, .add-category-btn:hover {
      background-color: var(--primary-hover);
    }
    
    h2, h3, .file-card-category, .user-list-name, .file-extension, .menu ul li a:hover,
    .menu ul li a.active, .admin-link, .back-link, .file-list li a, .view-all-files {
      color: var(--primary-color);
    }
    
    .file-card:hover {
      box-shadow: 0 12px 20px rgba(var(--primary-color-rgb), 0.2);
    }
    
    .theme-option.active, .view-mode-option.active, .schedule-option.active {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 3px var(--primary-transparent);
    }
    
    .page-size-slider {
      background: linear-gradient(to right, var(--primary-light), var(--primary-color));
    }
    
    .page-size-slider::-webkit-slider-thumb {
      background: var(--primary-color);
    }
    
    .page-size-slider::-moz-range-thumb {
      background: var(--primary-color);
    }
    
    .custom-theme-color {
      background-color: var(--primary-color);
    }
    
    /* Animaatio asetusosioille */
    .fade-in {
      opacity: 0;
      transform: translateY(20px);
      animation: fadeInUp 0.5s ease forwards;
    }
    
    @keyframes fadeInUp {
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    /* View mode selection styles */
    .view-mode-preview {
        display: flex;
        gap: 20px;
        margin: 20px 0;
    }

    .view-mode-option {
        flex: 1;
        background-color: white;
        border: 2px solid #d9c2ff;
        border-radius: 8px;
        padding: 15px;
        text-align: center;
        cursor: pointer;
        transition: border-color 0.3s;
    }

    .view-mode-option.active {
        border-color: var(--primary-color);
        background-color: #f9f0ff;
    }

    .view-mode-icon {
        font-size: 24px;
        color: var(--primary-color);
        margin-bottom: 5px;
    }

    .view-mode-label {
        font-weight: bold;
        margin-bottom: 10px;
    }

    .view-mode-sample {
        height: 80px;
        background-color: #f0e0ff;
        border-radius: 4px;
        padding: 10px;
    }

    .grid-sample {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 5px;
    }

    .sample-item {
        background-color: white;
        border-radius: 4px;
        height: 30px;
    }

    .list-sample {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .sample-row {
        background-color: white;
        border-radius: 4px;
        height: 20px;
    }
    
    /* Enhanced page size input */
    .page-size-input {
        display: flex;
        align-items: center;
        background: linear-gradient(to right, #f0e0ff, #ffffff);
        border-radius: 8px;
        padding: 5px;
        border: 1px solid #d9c2ff;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    
    .page-size-input input {
        flex: 1;
        border: none;
        background: transparent;
        padding: 8px;
        font-size: 16px;
        color: var(--primary-color);
        font-weight: bold;
        text-align: center;
    }
    
    .page-size-input .unit {
        padding: 0 10px;
        color: var(--primary-color);
        font-weight: bold;
    }
    
    .page-size-slider {
        width: 100%;
        margin: 10px 0;
        -webkit-appearance: none;
        height: 8px;
        border-radius: 4px;
        background: linear-gradient(to right, var(--primary-light), var(--primary-color));
        outline: none;
    }
    
    .page-size-slider::-webkit-slider-thumb {
        -webkit-appearance: none;
        appearance: none;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: var(--primary-color);
        cursor: pointer;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }
    
    .page-size-slider::-moz-range-thumb {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: var(--primary-color);
        cursor: pointer;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        border: none;
    }
  </style>
</head>
<body class="<?php echo $theme; ?>-theme">
  <?php include('header.php'); ?>

  <div class="container">
      <h2>Käyttäjäasetukset</h2>
      
      <?php if ($usernameChanged): ?>
          <p class="success">Käyttäjänimi vaihdettu onnistuneesti! Uusi käyttäjänimi: <?php echo htmlspecialchars($username); ?></p>
      <?php endif; ?>
      
      <?php if (!empty($usernameError)): ?>
          <p class="error"><?php echo $usernameError; ?></p>
      <?php endif; ?>
      
      <?php if ($passwordChanged): ?>
          <p class="success">Salasana vaihdettu onnistuneesti!</p>
      <?php endif; ?>
      
      <?php if (!empty($passwordError)): ?>
          <p class="error"><?php echo $passwordError; ?></p>
      <?php endif; ?>
      
      <?php if (isset($themeChanged) && $themeChanged): ?>
          <p class="success">Teema vaihdettu onnistuneesti!</p>
      <?php endif; ?>
      
      <?php if (isset($sortChanged) && $sortChanged): ?>
          <p class="success">Tiedostojen järjestys vaihdettu onnistuneesti!</p>
      <?php endif; ?>
      
      <?php if (isset($pageSizeChanged) && $pageSizeChanged): ?>
          <p class="success">Sivun koko vaihdettu onnistuneesti!</p>
      <?php endif; ?>
      
      <?php if (isset($fileNamingChanged) && $fileNamingChanged): ?>
          <p class="success">Tiedostojen nimeämisasetukset vaihdettu onnistuneesti!</p>
      <?php endif; ?>
      
      <?php if (isset($uiSettingsChanged) && $uiSettingsChanged): ?>
          <p class="success">Käyttöliittymäasetukset päivitetty onnistuneesti!</p>
      <?php endif; ?>
      
      <?php if (isset($viewSettingsChanged) && $viewSettingsChanged): ?>
          <p class="success">Tiedostonäkymän asetukset päivitetty onnistuneesti!</p>
      <?php endif; ?>
      
      <div class="settings-container">
          <div class="settings-section">
              <h3>Vaihda käyttäjänimi</h3>
              <form method="POST" class="settings-form">
                  <div class="form-group">
                      <label for="new_username">Uusi käyttäjänimi:</label>
                      <input type="text" id="new_username" name="new_username" required pattern="[a-zA-Z0-9_]+">
                      <small>Käyttäjänimi voi sisältää vain kirjaimia, numeroita ja alaviivoja.</small>
                  </div>
                  <div class="form-group">
                      <label for="current_password_for_username">Nykyinen salasana:</label>
                      <input type="password" id="current_password_for_username" name="current_password_for_username" required>
                  </div>
                  <button type="submit" name="change_username" class="settings-button">Vaihda käyttäjänimi</button>
              </form>
          </div>
          
          <div class="settings-section">
              <h3>Vaihda salasana</h3>
              <form method="POST" class="settings-form">
                  <div class="form-group">
                      <label for="current_password">Nykyinen salasana:</label>
                      <input type="password" id="current_password" name="current_password" required>
                  </div>
                  <div class="form-group">
                      <label for="new_password">Uusi salasana:</label>
                      <input type="password" id="new_password" name="new_password" required minlength="6">
                  </div>
                  <div class="form-group">
                      <label for="confirm_password">Vahvista uusi salasana:</label>
                      <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                  </div>
                  <button type="submit" name="change_password" class="settings-button">Vaihda salasana</button>
              </form>
          </div>
          
          <div class="settings-section">
              <h3>Teema</h3>
              <form method="POST" class="settings-form">
                  <div class="form-group">
                      <label for="theme">Valitse teema:</label>
                      <select id="theme" name="theme">
                          <option value="light" <?php echo $theme === 'light' ? 'selected' : ''; ?>>Vaalea</option>
                          <option value="dark" <?php echo $theme === 'dark' ? 'selected' : ''; ?>>Tumma</option>
                          <option value="puhelin" <?php echo $theme === 'puhelin' ? 'selected' : ''; ?>>Puhelin - Vaalea</option>
                          <option value="puhelin-dark" <?php echo $theme === 'puhelin-dark' ? 'selected' : ''; ?>>Puhelin - Tumma</option>
                          <option value="purple" <?php echo $theme === 'purple' ? 'selected' : ''; ?>>Violetti</option>
                          <option value="blue" <?php echo $theme === 'blue' ? 'selected' : ''; ?>>Sininen</option>
                      </select>
                  </div>
                  
                  <!-- Uusi visuaalinen teemavalitsin -->
                  <div class="theme-preview">
                      <div class="theme-option theme-light <?php echo $theme === 'light' ? 'active' : ''; ?>" data-theme="light"></div>
                      <div class="theme-option theme-dark <?php echo $theme === 'dark' ? 'active' : ''; ?>" data-theme="dark"></div>
                      <div class="theme-option theme-purple <?php echo $theme === 'purple' ? 'active' : ''; ?>" data-theme="purple"></div>
                      <div class="theme-option theme-blue <?php echo $theme === 'blue' ? 'active' : ''; ?>" data-theme="blue"></div>
                  </div>
                  
                  <!-- Mukautettu teemaväri -->
                  <div class="form-group">
                      <label for="custom_theme_color">Mukautettu teemaväri:</label>
                      <input type="color" id="custom_theme_color" name="custom_theme_color" value="<?php echo $customThemeColor; ?>">
                      <small>Valitse oma teemaväri sovellukselle</small>
                  </div>
                  
                  <button type="submit" name="change_theme" class="settings-button">Tallenna teema</button>
              </form>
          </div>
          
          <div class="settings-section">
              <h3>Tiedostojen järjestys</h3>
              <form method="POST" class="settings-form">
                  <div class="form-group">
                      <label for="file_sort">Järjestä tiedostot:</label>
                      <select id="file_sort" name="file_sort">
                          <option value="newest" <?php echo $userSettings['fileSort'] === 'newest' ? 'selected' : ''; ?>>Uusin ensin</option>
                          <option value="oldest" <?php echo $userSettings['fileSort'] === 'oldest' ? 'selected' : ''; ?>>Vanhin ensin</option>
                          <option value="name" <?php echo $userSettings['fileSort'] === 'name' ? 'selected' : ''; ?>>Nimen mukaan</option>
                          <option value="size" <?php echo $userSettings['fileSort'] === 'size' ? 'selected' : ''; ?>>Koon mukaan</option>
                          <option value="category" <?php echo $userSettings['fileSort'] === 'category' ? 'selected' : ''; ?>>Kategorian mukaan</option>
                      </select>
                  </div>
                  <button type="submit" name="change_file_sort" class="settings-button">Tallenna järjestys</button>
              </form>
          </div>
          
          <div class="settings-section">
              <h3>Sivun koko</h3>
              <form method="POST" class="settings-form">
                  <div class="form-group">
                      <label for="page_size">Tiedostoja per sivu:</label>
                      <div class="page-size-input">
                          <input type="number" id="page_size" name="page_size" min="5" max="100" value="<?php echo $userSettings['pageSize']; ?>" oninput="updateSlider(this.value)">
                          <span class="unit">kpl</span>
                      </div>
                      <input type="range" class="page-size-slider" id="page_size_slider" min="5" max="100" value="<?php echo $userSettings['pageSize']; ?>" oninput="updatePageSize(this.value)">
                      <div class="slider-labels">
                          <span>5</span>
                          <span style="float: right;">100</span>
                      </div>
                  </div>
                  <button type="submit" name="change_page_size" class="settings-button">Tallenna sivun koko</button>
              </form>
          </div>

          <div class="settings-section">
              <h3>Tiedostojen nimeäminen</h3>
              <form method="POST" class="settings-form">
                  <div class="form-group">
                      <label for="file_naming_option">Tiedostojen nimeämistapa:</label>
                      <select id="file_naming_option" name="file_naming_option" onchange="toggleCustomPrefix()">
                          <option value="original" <?php echo (isset($userSettings['fileNamingOption']) && $userSettings['fileNamingOption'] === 'original') ? 'selected' : ''; ?>>Alkuperäinen nimi (ei muokkausta)</option>
                          <option value="datetime" <?php echo (isset($userSettings['fileNamingOption']) && $userSettings['fileNamingOption'] === 'datetime') ? 'selected' : ''; ?>>Latausaika (päivämäärä ja kellonaika)</option>
                          <option value="numbered" <?php echo (isset($userSettings['fileNamingOption']) && $userSettings['fileNamingOption'] === 'numbered') ? 'selected' : ''; ?>>Numeroitu (1, 2, 3, jne.)</option>
                          <option value="uuid" <?php echo (isset($userSettings['fileNamingOption']) && $userSettings['fileNamingOption'] === 'uuid') ? 'selected' : ''; ?>>Satunnainen tunniste (UUID)</option>
                          <option value="custom_prefix" <?php echo (isset($userSettings['fileNamingOption']) && $userSettings['fileNamingOption'] === 'custom_prefix') ? 'selected' : ''; ?>>Oma etuliite + numero</option>
                      </select>
                  </div>
                  
                  <div id="custom_prefix_container" class="form-group" style="display: <?php echo (isset($userSettings['fileNamingOption']) && $userSettings['fileNamingOption'] === 'custom_prefix') ? 'block' : 'none'; ?>">
                      <label for="custom_prefix">Oma etuliite:</label>
                      <input type="text" id="custom_prefix" name="custom_prefix" value="<?php echo isset($userSettings['customPrefix']) ? htmlspecialchars($userSettings['customPrefix']) : ''; ?>" placeholder="Esim. Kuva_">
                      <small>Tiedostot nimetään muodossa: etuliite + numero (esim. Kuva_1.jpg)</small>
                  </div>
                  
                  <button type="submit" name="change_file_naming" class="settings-button">Tallenna nimeämisasetukset</button>
              </form>
          </div>

          <div class="settings-section">
              <h3>Tiedostonäkymän asetukset</h3>
              <form method="POST" class="settings-form">
                  <h4>Valitse tiedostonäkymä:</h4>
                  
                  <!-- Hidden input to store the selected view mode -->
                  <input type="hidden" id="view_mode" name="view_mode" value="<?php echo $viewMode; ?>">
                  
                  <!-- Clickable view mode options -->
                  <div class="view-mode-preview">
                      <div class="view-mode-option <?php echo $viewMode === 'grid' ? 'active' : ''; ?>" data-mode="grid" onclick="selectViewMode('grid')">
                          <div class="view-mode-icon">⊞</div>
                          <div class="view-mode-label">Ruudukko</div>
                          <div class="view-mode-sample grid-sample">
                              <div class="sample-item"></div>
                              <div class="sample-item"></div>
                              <div class="sample-item"></div>
                              <div class="sample-item"></div>
                          </div>
                      </div>
                      <div class="view-mode-option <?php echo $viewMode === 'list' ? 'active' : ''; ?>" data-mode="list" onclick="selectViewMode('list')">
                          <div class="view-mode-icon">≡</div>
                          <div class="view-mode-label">Lista</div>
                          <div class="view-mode-sample list-sample">
                              <div class="sample-row"></div>
                              <div class="sample-row"></div>
                              <div class="sample-row"></div>
                          </div>
                      </div>
                  </div>
                  
                  <div class="form-group checkbox-group">
                      <input type="checkbox" id="show_preview" name="show_preview" <?php echo (!isset($userSettings['hidePreview']) || !$userSettings['hidePreview']) ? 'checked' : ''; ?>>
                      <label for="show_preview">Näytä esikatselu listanäkymässä</label>
                      <small>Näyttää tiedoston esikatselun kun hiiri on tiedoston päällä</small>
                  </div>
                  
                  <button type="submit" name="change_view_settings" class="settings-button">Tallenna näkymäasetukset</button>
              </form>
          </div>

          <div class="settings-section">
              <h3>Käyttöliittymän asetukset</h3>
              <form method="POST" class="settings-form">
                  <div class="form-group">
                      <label for="avatar_color">Profiiliväri:</label>
                      <input type="color" id="avatar_color" name="avatar_color" value="<?php echo $avatarColor; ?>">
                      <small>Valitse väri, jota käytetään käyttäjäprofiilissasi</small>
                  </div>
                  
                  <div class="form-group checkbox-group">
                      <input type="checkbox" id="auto_download" name="auto_download" <?php echo $autoDownload ? 'checked' : ''; ?>>
                      <label for="auto_download">Lataa tiedostot automaattisesti</label>
                      <small>Jos valittu, tiedostot latautuvat automaattisesti klikattaessa, muuten avautuu ensin esikatseluikkuna</small>
                  </div>
                  
                  <div class="form-group checkbox-group">
                      <input type="checkbox" id="confirm_delete" name="confirm_delete" <?php echo $confirmDelete ? 'checked' : ''; ?>>
                      <label for="confirm_delete">Vahvista tiedostojen poisto</label>
                      <small>Jos valittu, tiedostojen poistaminen vaatii erillisen vahvistuksen</small>
                  </div>
                  
                  <!-- Uudet asetukset -->
                  <div class="form-group checkbox-group">
                      <input type="checkbox" id="show_file_info" name="show_file_info" <?php echo $showFileInfo ? 'checked' : ''; ?>>
                      <label for="show_file_info">Näytä tiedoston tiedot</label>
                      <small>Näyttää tiedoston koon, tyypin ja muut tiedot tiedostonäkymässä</small>
                  </div>
                  
                  <div class="form-group checkbox-group">
                      <input type="checkbox" id="enable_notifications" name="enable_notifications" <?php echo $enableNotifications ? 'checked' : ''; ?>>
                      <label for="enable_notifications">Käytä ilmoituksia</label>
                      <small>Näyttää ilmoituksia kun tiedostoja ladataan tai poistetaan</small>
                  </div>
                  
                  <!-- Uudet lisätyt asetukset -->
                  <div class="form-group checkbox-group">
                      <input type="checkbox" id="enable_animations" name="enable_animations" <?php echo $enableAnimations ? 'checked' : ''; ?>>
                      <label for="enable_animations">Käytä animaatioita</label>
                      <small>Näyttää animaatioita käyttöliittymässä</small>
                  </div>
                  
                  <div class="form-group checkbox-group">
                      <input type="checkbox" id="show_file_count" name="show_file_count" <?php echo $showFileCount ? 'checked' : ''; ?>>
                      <label for="show_file_count">Näytä tiedostojen määrä</label>
                      <small>Näyttää tiedostojen kokonaismäärän tiedostonäkymässä</small>
                  </div>
                  
                  <div class="form-group checkbox-group">
                      <input type="checkbox" id="enable_keyboard_shortcuts" name="enable_keyboard_shortcuts" <?php echo $enableKeyboardShortcuts ? 'checked' : ''; ?>>
                      <label for="enable_keyboard_shortcuts">Käytä näppäimistön pikakomentoja</label>
                      <small>Mahdollistaa tiedostojen hallinnan näppäimistön avulla</small>
                  </div>
                  
                  <div class="form-group">
                      <label for="default_category">Oletuskategoria:</label>
                      <select id="default_category" name="default_category">
                          <?php foreach ($userSettings['categories'] as $key => $name): ?>
                              <option value="<?php echo $key; ?>" <?php echo $defaultCategory === $key ? 'selected' : ''; ?>><?php echo $name; ?></option>
                          <?php endforeach; ?>
                      </select>
                      <small>Kategoria, joka valitaan oletuksena uusille tiedostoille</small>
                  </div>
                  
                  
                  <div class="ui-preview">
                      <span>Esikatselu profiilivärille:</span>
                      <div class="avatar-preview" id="avatarPreview" style="background-color: <?php echo $avatarColor; ?>;">
                          <?php echo substr($username, 0, 2); ?>
                      </div>
                  </div>
                  
                  <button type="submit" name="change_ui_settings" class="settings-button">Tallenna käyttöliittymäasetukset</button>
              </form>
          </div>
      </div>
  </div>

  <script src="script.js"></script>
  <script>
function toggleCustomPrefix() {
  const namingOption = document.getElementById('file_naming_option').value;
  const customPrefixContainer = document.getElementById('custom_prefix_container');
  
  if (namingOption === 'custom_prefix') {
      customPrefixContainer.style.display = 'block';
  } else {
      customPrefixContainer.style.display = 'none';
  }
}

function selectViewMode(mode) {
    // Update hidden input value
    document.getElementById('view_mode').value = mode;
    
    // Update UI
    const options = document.querySelectorAll('.view-mode-option');
    options.forEach(option => {
        if (option.dataset.mode === mode) {
            option.classList.add('active');
        } else {
            option.classList.remove('active');
        }
    });
}

function updateSlider(value) {
    document.getElementById('page_size_slider').value = value;
}

function updatePageSize(value) {
    document.getElementById('page_size').value = value;
}

document.addEventListener('DOMContentLoaded', function() {
    // Salasanan vahvistus
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    
    function validatePassword() {
        if (newPasswordInput.value !== confirmPasswordInput.value) {
            confirmPasswordInput.setCustomValidity('Salasanat eivät täsmää');
        } else {
            confirmPasswordInput.setCustomValidity('');
        }
    }
    
    newPasswordInput.addEventListener('change', validatePassword);
    confirmPasswordInput.addEventListener('keyup', validatePassword);

    // Initialize custom prefix visibility
    toggleCustomPrefix();
    
    // Profiilivärin esikatselu
    const avatarColorInput = document.getElementById('avatar_color');
    const avatarPreview = document.getElementById('avatarPreview');
    
    avatarColorInput.addEventListener('input', function() {
        avatarPreview.style.backgroundColor = this.value;
    });
    
    // Mukautetun teemavärin esikatselu
    const customThemeColorInput = document.getElementById('custom_theme_color');
    if (customThemeColorInput) {
        customThemeColorInput.addEventListener('input', function() {
            document.documentElement.style.setProperty('--primary-color', this.value);
            
            // Calculate and set derived colors
            const hexColor = this.value.replace('#', '');
            const r = parseInt(hexColor.substr(0, 2), 16);
            const g = parseInt(hexColor.substr(2, 2), 16);
            const b = parseInt(hexColor.substr(4, 2), 16);
            
            // Darker for hover
            const darkerR = Math.max(0, r - 20);
            const darkerG = Math.max(0, g - 20);
            const darkerB = Math.max(0, b - 20);
            const darkerHex = '#' + 
                darkerR.toString(16).padStart(2, '0') + 
                darkerG.toString(16).padStart(2, '0') + 
                darkerB.toString(16).padStart(2, '0');
            
            // Lighter for backgrounds
            const lighterR = Math.min(255, r + 40);
            const lighterG = Math.min(255, g + 40);
            const lighterB = Math.min(255, b + 40);
            const lighterHex = '#' + 
                lighterR.toString(16).padStart(2, '0') + 
                lighterG.toString(16).padStart(2, '0') + 
                lighterB.toString(16).padStart(2, '0');
            
            document.documentElement.style.setProperty('--primary-hover', darkerHex);
            document.documentElement.style.setProperty('--primary-light', lighterHex);
            document.documentElement.style.setProperty('--primary-transparent', this.value + '40');
            document.documentElement.style.setProperty('--primary-color-rgb', `${r}, ${g}, ${b}`);
        });
    }
    
    // Theme option click handler
    const themeOptions = document.querySelectorAll('.theme-option');
    themeOptions.forEach(option => {
        option.addEventListener('click', function() {
            const themeValue = this.dataset.theme;
            document.getElementById('theme').value = themeValue;
            
            // Update active state
            themeOptions.forEach(opt => opt.classList.remove('active'));
            this.classList.add('active');
        });
    });
});
</script>

<style>
.checkbox-group {
    display: flex;
    flex-direction: column;
    margin-bottom: 15px;
}

.checkbox-group input[type="checkbox"] {
    margin-right: 10px;
    width: auto;
}

.checkbox-group label {
    display: inline-flex;
    align-items: center;
    font-weight: normal;
    margin-bottom: 5px;
}

.checkbox-group small {
    margin-left: 22px;
    font-style: italic;
    color: #666;
}

.ui-preview {
    margin: 20px 0;
    display: flex;
    align-items: center;
    gap: 15px;
}

.avatar-preview {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    text-transform: uppercase;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
}

.avatar-preview:hover {
    transform: scale(1.1);
}

/* Uudet tyylit */
.theme-preview {
    display: flex;
    gap: 15px;
    margin: 15px 0;
}

.theme-option {
    width: 50px;
    height: 50px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.theme-option:hover {
    transform: scale(1.1);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.theme-option.active {
    border: 2px solid var(--primary-color);
    box-shadow: 0 0 0 3px var(--primary-transparent);
}

.theme-light {
    background: linear-gradient(to bottom right, #ffffff, #f0f0f0);
}

.theme-dark {
    background: linear-gradient(to bottom right, #333333, #222222);
}

.theme-purple {
    background: linear-gradient(to bottom right, #a600ff, #7700cc);
}

.theme-blue {
    background: linear-gradient(to bottom right, #0066ff, #0044cc);
}

.slider-labels {
    display: flex;
    justify-content: space-between;
    margin-top: 5px;
    color: var(--primary-color);
    font-size: 12px;
}
</style>
</body>
</html>

