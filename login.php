<?php
session_start();
include('log.php');

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: upload.php');
    exit;
}

// Process login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Read users from users.txt
    $authenticated = false;
    if (file_exists('users.txt')) {
        $users = file('users.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($users as $user) {
            $userData = explode(':', $user);
            if (count($userData) >= 3 && $userData[0] === $username && $userData[1] === $password) {
                $_SESSION['user_id'] = $username;
                $_SESSION['role'] = $userData[2];
                
                // Log successful login
                $logMessage = "Käyttäjä $username kirjautui sisään";
                writeLog($logMessage);
                
                $authenticated = true;
                header('Location: upload.php');
                exit;
            }
        }
    }
    
    // Fallback to hardcoded users (for backward compatibility)
    $hardcodedUsers = [
        'admin' => ['password' => 'adminpassword', 'role' => 'admin'],
        'atlas' => ['password' => 'atlas1', 'role' => 'user'],
        'Aliisa'=> ['password' => 'Naksunapero123', 'role' => 'user']
    ];
    
    if (!$authenticated && isset($hardcodedUsers[$username]) && $hardcodedUsers[$username]['password'] === $password) {
        $_SESSION['user_id'] = $username;
        $_SESSION['role'] = $hardcodedUsers[$username]['role'];
        
        // Log successful login
        $logMessage = "Käyttäjä $username kirjautui sisään";
        writeLog($logMessage);
        
        header('Location: upload.php');
        exit;
    }
    
    $errorMessage = "Virheellinen käyttäjätunnus tai salasana.";
}
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/S7JLkFy1/Heartcrown.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kirjautuminen</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <h2>Kirjaudu sisään</h2>
        <?php if (isset($errorMessage)) { echo "<p class='error'>$errorMessage</p>"; } ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Käyttäjätunnus" required><br>
            <input type="password" name="password" placeholder="Salasana" required><br>
            <input type="submit" value="Kirjaudu">
        </form>
    </div>
</body>
</html>

