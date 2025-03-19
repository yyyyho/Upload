<?php
session_start();
include('log.php');

// Tarkistetaan, että käyttäjä on kirjautunut ja on admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Et ole oikeutettu hallitsemaan käyttäjiä.");
}

$username = $_SESSION['user_id'];

// Käyttäjän luonti
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $newUsername = isset($_POST['new_username']) ? trim($_POST['new_username']) : '';
    $newPassword = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
    $newRole = isset($_POST['user_role']) ? trim($_POST['user_role']) : 'user';
    
    if (empty($newUsername) || empty($newPassword)) {
        $errorMessage = "Täytä kaikki kentät!";
    } else if (!preg_match('/^[a-zA-Z0-9_]+$/', $newUsername)) {
        $errorMessage = "Käyttäjänimi voi sisältää vain kirjaimia, numeroita ja alaviivoja.";
    } else {
        // Tarkistetaan, onko käyttäjänimi jo käytössä
        $usernameExists = false;
        if (file_exists('users.txt')) {
            $existingUsers = file('users.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($existingUsers as $user) {
                $userData = explode(':', $user);
                if ($userData[0] === $newUsername) {
                    $usernameExists = true;
                    break;
                }
            }
        }
        
        if ($usernameExists) {
            $errorMessage = "Käyttäjänimi on jo käytössä.";
        } else {
            // Luodaan käyttäjän kansio
            $userUploadDir = 'uploads/' . $newUsername . '/';
            if (!is_dir($userUploadDir)) {
                mkdir($userUploadDir, 0777, true);
            }
            
            // Luodaan käyttäjän asetukset
            $userSettingsDir = 'user_settings/';
            if (!is_dir($userSettingsDir)) {
                mkdir($userSettingsDir, 0777, true);
            }
            
            $userSettingsFile = $userSettingsDir . $newUsername . '_settings.json';
            $defaultSettings = [
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
            
            file_put_contents($userSettingsFile, json_encode($defaultSettings, JSON_PRETTY_PRINT));
            
            // Lisätään käyttäjä users.txt-tiedostoon
            $newUser = $newUsername . ':' . $newPassword . ':' . $newRole . "\n";
            file_put_contents('users.txt', $newUser, FILE_APPEND);
            
            // Kirjataan lokiin
            writeLog("Admin $username lisäsi käyttäjän $newUsername", "", 'admin');
            
            $successMessage = "Käyttäjä $newUsername lisätty onnistuneesti!";
        }
    }
}

// Käyttäjän muokkaus
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $oldUsername = $_POST['old_username'];
    $newUsername = $_POST['new_username'];
    $newPassword = $_POST['password'];
    $newRole = $_POST['new_role'];
    
    // Ladataan käyttäjät
    $users = [];
    if (file_exists('users.txt')) {
        $users = file('users.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $users = array_map(function($user) {
            $parts = explode(':', $user);
            return [
                'username' => $parts[0],
                'password' => $parts[1],
                'role' => isset($parts[2]) ? $parts[2] : 'user'
            ];
        }, $users);
    }

    // Tarkistetaan, onko käyttäjänimi muuttunut ja onko uusi nimi jo käytössä
    $usernameChanged = ($oldUsername !== $newUsername);
    $usernameExists = false;
    
    if ($usernameChanged) {
        foreach ($users as $user) {
            if ($user['username'] === $newUsername) {
                $usernameExists = true;
                break;
            }
        }
    }
    
    if ($usernameChanged && $usernameExists) {
        $errorMessage = "Käyttäjänimi $newUsername on jo käytössä.";
    } else {
        $updatedUsers = [];
        foreach ($users as $user) {
            if ($user['username'] === $oldUsername) {
                // Päivitetään käyttäjän tiedot
                $updatedUsers[] = implode(':', [$newUsername, $newPassword, $newRole]);
            } else {
                $updatedUsers[] = implode(':', [$user['username'], $user['password'], $user['role']]);
            }
        }

        file_put_contents('users.txt', implode("\n", $updatedUsers) . "\n");
        
        // Jos käyttäjänimi muuttui, päivitetään myös tiedostot ja asetukset
        if ($usernameChanged) {
            // Päivitetään tiedostojen omistajuus
            if (file_exists('file_info.txt')) {
                $fileInfos = file('file_info.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $newFileInfos = [];
                foreach ($fileInfos as $fileInfo) {
                    $info = explode(':', $fileInfo);
                    if ($info[0] === $oldUsername) {
                        $info[0] = $newUsername;
                        $newFileInfos[] = implode(':', $info);
                    } else {
                        $newFileInfos[] = $fileInfo;
                    }
                }
                file_put_contents('file_info.txt', implode("\n", $newFileInfos) . "\n");
            }
            
            // Siirretään käyttäjän tiedostokansio
            $oldUploadDir = 'uploads/' . $oldUsername . '/';
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
            $oldUserSettingsFile = "user_settings/{$oldUsername}_settings.json";
            $newUserSettingsFile = "user_settings/{$newUsername}_settings.json";
            if (file_exists($oldUserSettingsFile)) {
                copy($oldUserSettingsFile, $newUserSettingsFile);
                unlink($oldUserSettingsFile);
            }
            
            // Siirretään käyttäjän tiedostotiedot
            $oldUserFileInfoPath = "user_settings/{$oldUsername}_file_info.txt";
            $newUserFileInfoPath = "user_settings/{$newUsername}_file_info.txt";
            if (file_exists($oldUserFileInfoPath)) {
                copy($oldUserFileInfoPath, $newUserFileInfoPath);
                unlink($oldUserFileInfoPath);
            }
            
            // Jos muokataan omaa käyttäjätunnusta, päivitetään sessio
            if ($oldUsername === $_SESSION['user_id']) {
                $_SESSION['user_id'] = $newUsername;
            }
            
            $successMessage = "Käyttäjä $oldUsername päivitetty onnistuneesti. Käyttäjänimi muutettu: $newUsername";
        } else {
            $successMessage = "Käyttäjä $oldUsername päivitetty onnistuneesti.";
        }
    }
}

// Käyttäjän poisto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user']) && isset($_POST['delete_username'])) {
    $deleteUsername = $_POST['delete_username'];
    
    // Ladataan käyttäjät
    $users = [];
    if (file_exists('users.txt')) {
        $users = file('users.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $users = array_map(function($user) {
            $parts = explode(':', $user);
            return [
                'username' => $parts[0],
                'password' => $parts[1],
                'role' => isset($parts[2]) ? $parts[2] : 'user'
            ];
        }, $users);
    }
    
    // Varmistetaan, että käyttäjä ei poista itseään
    if ($deleteUsername === $username) {
        $errorMessage = "Et voi poistaa omaa käyttäjätiliäsi.";
    } else {
        // Poistetaan käyttäjä users.txt-tiedostosta
        $updatedUsers = [];
        foreach ($users as $user) {
            if ($user['username'] !== $deleteUsername) {
                $updatedUsers[] = implode(':', [$user['username'], $user['password'], $user['role']]);
            }
        }
        
        file_put_contents('users.txt', implode("\n", $updatedUsers) . "\n");
        
        // Poistetaan käyttäjän tiedostot
        $userUploadDir = 'uploads/' . $deleteUsername . '/';
        if (is_dir($userUploadDir)) {
            // Poistetaan kaikki tiedostot käyttäjän kansiosta
            $files = glob($userUploadDir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            // Poistetaan kansio
            rmdir($userUploadDir);
        }
        
        // Poistetaan käyttäjän tiedot file_info.txt-tiedostosta
        if (file_exists('file_info.txt')) {
            $fileInfos = file('file_info.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $newFileInfos = [];
            foreach ($fileInfos as $fileInfo) {
                $info = explode(':', $fileInfo);
                if ($info[0] !== $deleteUsername) {
                    $newFileInfos[] = $fileInfo;
                }
            }
            file_put_contents('file_info.txt', implode("\n", $newFileInfos) . "\n");
        }
        
        // Poistetaan käyttäjän asetukset ja tiedostotiedot
        $userSettingsFile = "user_settings/{$deleteUsername}_settings.json";
        if (file_exists($userSettingsFile)) {
            unlink($userSettingsFile);
        }

        // Poistetaan käyttäjän tiedostotiedot
        $userFileInfoPath = "user_settings/{$deleteUsername}_file_info.txt";
        if (file_exists($userFileInfoPath)) {
            unlink($userFileInfoPath);
        }
        
        $successMessage = "Käyttäjä $deleteUsername ja kaikki käyttäjän tiedostot poistettu onnistuneesti.";
        
        // Kirjataan lokiin
        writeLog("Admin $username poisti käyttäjän $deleteUsername", "", 'admin');
    }
}

// Ladataan käyttäjän asetukset
$userSettingsFile = "user_settings/{$username}_settings.json";
if (file_exists($userSettingsFile)) {
    $userSettings = json_decode(file_get_contents($userSettingsFile), true);
} else {
    $userSettings = ['theme' => 'light'];
}

$theme = $userSettings['theme'];

// Käyttäjien lataaminen
$users = [];
if (file_exists('users.txt')) {
    $users = file('users.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $users = array_map(function($user) {
        $parts = explode(':', $user);
        return [
            'username' => $parts[0],
            'password' => $parts[1],
            'role' => isset($parts[2]) ? $parts[2] : 'user'
        ];
    }, $users);
}
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/S7JLkFy1/Heartcrown.png">   
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hallitse käyttäjiä</title>
    <link rel="stylesheet" href="style.css">
    <?php if ($theme === 'dark'): ?>
    <link rel="stylesheet" href="dark-theme.css">
    <?php endif; ?>
<style>
.action-buttons {
    margin-bottom: 20px;
    display: flex;
    justify-content: flex-end;
}

.add-user-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 15px;
    border-radius: 6px;
    background-color: #a600ff;
    color: white;
    font-size: 15px;
    font-weight: 500;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

.add-user-btn:hover {
    background-color: #8400cc;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.3);
}

.btn-icon {
    margin-right: 8px;
    font-size: 18px;
    font-weight: bold;
}

/* Modal styles - completely revised */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background-color: rgba(0, 0, 0, 0.7);
    z-index: 9999;
    display: none;
    justify-content: center;
    align-items: center;
    backdrop-filter: blur(5px);
}

.create-user-modal {
    background: white;
    border-radius: 12px;
    padding: 25px;
    width: 400px;
    max-width: 90%;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
}

.create-user-form input,
.create-user-form select {
    width: 100%;
    padding: 12px;
    margin-bottom: 15px;
    border: 1px solid #d9c2ff;
    border-radius: 6px;
    background-color: white;
    font-size: 15px;
    box-sizing: border-box;
}

.create-user-form label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    color: #333;
}

.form-actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.submit-btn, .cancel-btn {
    padding: 12px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
    flex: 1;
}

.submit-btn {
    background-color: #a600ff;
    color: white;
}

.submit-btn:hover {
    background-color: #8400cc;
    transform: translateY(-3px);
    box-shadow: 0 5px 10px rgba(132, 0, 204, 0.3);
}

.cancel-btn {
    background-color: #f0f0f0;
    color: #333;
}

.cancel-btn:hover {
    background-color: #e0e0e0;
    transform: translateY(-3px);
    box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
}

/* Dark theme adjustments */
.dark-theme .create-user-modal {
    background: #2a2a2a;
    color: #f0f0f0;
}

.dark-theme .create-user-form input,
.dark-theme .create-user-form select {
    background-color: #333;
    border-color: #444;
    color: #f0f0f0;
}

.dark-theme .create-user-form label {
    color: #f0f0f0;
}

.dark-theme .cancel-btn {
    background-color: #444;
    color: #f0f0f0;
}

.dark-theme .cancel-btn:hover {
    background-color: #555;
}

/* Error and success messages */
.error, .success {
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 15px;
    text-align: center;
}

.error {
    background-color: rgba(255, 0, 0, 0.1);
    color: #d32f2f;
    border: 1px solid #ffcdd2;
}

.success {
    background-color: rgba(0, 255, 0, 0.1);
    color: #388e3c;
    border: 1px solid #c8e6c9;
}

/* Animation classes */
.fade-in {
    animation: fadeIn 0.3s ease forwards;
}

.slide-down {
    animation: slideDown 0.4s ease forwards;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideDown {
    from { transform: translateY(-50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

/* Modal styles */
.modal {
    display: none;
    position: fixed;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.7);
    backdrop-filter: blur(5px);
}

.modal-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background-color: white;
    padding: 25px;
    border-radius: 12px;
    width: 400px;
    max-width: 90%;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
}

.dark-theme .modal-content {
    background-color: #2a2a2a;
    color: #f0f0f0;
}

.modal-content input,
.modal-content select {
    width: 100%;
    padding: 12px;
    margin-bottom: 15px;
    border: 1px solid #d9c2ff;
    border-radius: 6px;
    background-color: white;
    font-size: 15px;
    box-sizing: border-box;
}

.dark-theme .modal-content input,
.dark-theme .modal-content select {
    background-color: #333;
    border-color: #444;
    color: #f0f0f0;
}

.modal-content label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    color: #333;
}

.dark-theme .modal-content label {
    color: #f0f0f0;
}

.modal-buttons {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.modal-content button {
    padding: 12px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
    flex: 1;
}

.modal-content button[type="submit"] {
    background-color: #a600ff;
    color: white;
}

.modal-content button[type="submit"]:hover {
    background-color: #8400cc;
    transform: translateY(-3px);
    box-shadow: 0 5px 10px rgba(132, 0, 204, 0.3);
}

.modal-content button[type="button"] {
    background-color: #f0f0f0;
    color: #333;
}

.dark-theme .modal-content button[type="button"] {
    background-color: #444;
    color: #f0f0f0;
}

.modal-content button[type="button"]:hover {
    background-color: #e0e0e0;
    transform: translateY(-3px);
    box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
}

.dark-theme .modal-content button[type="button"]:hover {
    background-color: #555;
}

.warning {
    color: #d32f2f;
    font-weight: bold;
}
</style>
</head>
<body class="<?php echo $theme; ?>-theme">
    <?php include('header.php'); ?>

    <div class="container">
        <h2>Hallitse käyttäjiä</h2>
        <div class="action-buttons">
            <button class="add-user-btn" onclick="showCreateUserModal()">
                <span class="btn-icon">+</span> Lisää uusi käyttäjä
            </button>
        </div>

        <?php if (isset($successMessage)): ?>
            <p class="success"><?php echo $successMessage; ?></p>
        <?php endif; ?>
        
        <?php if (isset($errorMessage)): ?>
            <p class="error"><?php echo $errorMessage; ?></p>
        <?php endif; ?>

        <table class="user-table">
            <thead>
                <tr>
                    <th>Käyttäjänimi</th>
                    <th>Rooli</th>
                    <th>Toiminnot</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['role']); ?></td>
                        <td>
                            <button onclick="showEditForm('<?php echo $user['username']; ?>', '<?php echo $user['password']; ?>', '<?php echo $user['role']; ?>')" class="edit-btn">Muokkaa</button>
                            <?php if ($user['username'] !== $username): ?>
                                <button onclick="confirmDelete('<?php echo $user['username']; ?>')" class="delete-btn">Poista</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Muokkauslomake (piilotettu oletuksena) -->
        <div id="editForm" class="modal"> 
            <div class="modal-content">
                <h3>Muokkaa käyttäjää</h3>
                <form method="POST">
                    <input type="hidden" id="old_username" name="old_username">
                    
                    <label for="new_username">Käyttäjänimi:</label>
                    <input type="text" id="new_username" name="new_username" required>
                    
                    <label for="password">Salasana:</label>
                    <input type="text" id="password" name="password" required>
                    
                    <label for="new_role">Rooli:</label>
                    <select id="new_role" name="new_role">
                        <option value="user">Käyttäjä</option>
                        <option value="admin">Admin</option>
                        <option value="esimies">Esimies</option>
                        <option value="vierailija">Vierailija</option>
                    </select>
                    <div class="modal-buttons">
                        <button type="submit" name="edit_user">Tallenna muutokset</button>
                        <button type="button" onclick="hideEditForm()">Peruuta</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Poistovahvistuslomake (piilotettu oletuksena) -->
        <div id="deleteForm" class="modal">
            <div class="modal-content">
                <h3>Poista käyttäjä</h3>
                <p>Oletko varma, että haluat poistaa käyttäjän <span id="delete_username_display"></span>?</p>
                <p class="warning">Tämä poistaa myös kaikki käyttäjän tiedostot ja asetukset. Toimintoa ei voi peruuttaa!</p>
                
                <form method="POST">
                    <input type="hidden" id="delete_username" name="delete_username">
                    <div class="modal-buttons">
                        <button type="submit" name="delete_user" class="delete-btn">Poista käyttäjä</button>
                        <button type="button" onclick="hideDeleteForm()" class="cancel-btn">Peruuta</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Käyttäjän luonti -modaali -->
        <div id="createUserModal" class="modal-overlay">
            <div class="create-user-modal">
                <h3>Lisää uusi käyttäjä</h3>
                
                <form id="createUserForm" class="create-user-form" method="POST">
                    <div class="form-group">
                        <label for="new_username">Käyttäjätunnus:</label>
                        <input type="text" id="new_username" name="new_username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">Salasana:</label>
                        <input type="password" id="new_password" name="new_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="user_role">Rooli:</label>
                        <select id="user_role" name="user_role" required>
                            <option value="user">Käyttäjä</option>
                            <option value="admin">Admin</option>
                            <option value="esimies">Esimies</option>
                            <option value="vierailija">Vierailija</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="create_user" class="submit-btn">Lisää käyttäjä</button>
                        <button type="button" class="cancel-btn" onclick="hideCreateUserModal()">Peruuta</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
function showEditForm(username, password, role) {
    document.getElementById('old_username').value = username;
    document.getElementById('new_username').value = username;
    document.getElementById('password').value = password;
    document.getElementById('new_role').value = role;
    document.getElementById('editForm').style.display = 'block';
}

function hideEditForm() {
    document.getElementById('editForm').style.display = 'none';
}

function confirmDelete(username) {
    document.getElementById('delete_username').value = username;
    document.getElementById('delete_username_display').textContent = username;
    document.getElementById('deleteForm').style.display = 'block';
}

function hideDeleteForm() {
    document.getElementById('deleteForm').style.display = 'none';
}

function showCreateUserModal() {
    const modal = document.getElementById('createUserModal');
    modal.style.display = 'flex';
    modal.classList.add('fade-in');
    document.querySelector('#createUserModal .create-user-modal').classList.add('slide-down');
    
    document.getElementById('createUserForm').reset();
    document.body.style.overflow = 'hidden'; // Estä sivun vieritys
}

function hideCreateUserModal() {
    const modal = document.getElementById('createUserModal');
    modal.classList.remove('fade-in');
    document.querySelector('#createUserModal .create-user-modal').classList.remove('slide-down');
    
    setTimeout(() => {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto'; // Salli sivun vieritys
    }, 200);
}

// Lisätään tapahtumankäsittelijä modaalin sulkemiseen, kun klikataan taustaa
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('createUserModal');
    
    modal.addEventListener('click', function(e) {
        // Jos klikataan modaalin taustaa (ei sisältöä), suljetaan modaali
        if (e.target === modal) {
            hideCreateUserModal();
        }
    });
    
    // Lisätään Escape-näppäimen käsittelijä
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            hideCreateUserModal();
        }
    });
    
    // Lisätään Escape-näppäimen käsittelijä myös muille modaaleille
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (document.getElementById('editForm').style.display === 'block') {
                hideEditForm();
            }
            if (document.getElementById('deleteForm').style.display === 'block') {
                hideDeleteForm();
            }
        }
    });
    
    // Lisätään tapahtumankäsittelijä muiden modaalien sulkemiseen, kun klikataan taustaa
    document.getElementById('editForm').addEventListener('click', function(e) {
        if (e.target === this) {
            hideEditForm();
        }
    });
    
    document.getElementById('deleteForm').addEventListener('click', function(e) {
        if (e.target === this) {
            hideDeleteForm();
        }
    });
});
</script>
</body>
</html>

