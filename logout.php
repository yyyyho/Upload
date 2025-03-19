<?php
session_start();
include('log.php');

// Jos käyttäjä on kirjautunut sisään
if (isset($_SESSION['user_id'])) {
    $username = $_SESSION['user_id'];

    // Kirjataan uloskirjautuminen lokiin
    $logMessage = "Käyttäjä $username kirjautui ulos";
    writeLog($logMessage);

    // Tyhjennetään sessio
    session_unset();
    session_destroy();
}

// Ohjataan käyttäjä kirjautumissivulle
header('Location: login.php');
exit;
?>
