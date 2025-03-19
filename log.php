<?php
function writeLog($message, $fileName = '', $logType = 'login') {
    // Varmistetaan, että logs-kansio on olemassa
    $logsDir = 'logs/';
    if (!is_dir($logsDir)) {
        mkdir($logsDir, 0777, true);
    }
    
    // Aseta lokitiedoston polku
    if ($logType == 'upload') {
        $logFile = $logsDir . 'upload_log.txt';  // Tiedostojen lataustapahtumat
    } elseif ($logType == 'admin') {
        $logFile = $logsDir . 'admin_log.txt';   // Admin-toiminnot
    } else {
        $logFile = $logsDir . 'login_log.txt';   // Kirjautumis- ja uloskirjautumistapahtumat
    }

    // Hakee nykyisen ajan ja päivämäärän
    $timestamp = date("Y-m-d H:i:s");

    // Hakee käyttäjän IP-osoitteen
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    
    // Muodostetaan lokiviesti
    $logMessage = "[$timestamp] $message - IP: $ipAddress";
    if (!empty($fileName)) {
        $logMessage .= " - Tiedosto: $fileName";
    }
    $logMessage .= "\n";

    // Avaa tiedoston ja lisää uusi rivi
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}
?>

