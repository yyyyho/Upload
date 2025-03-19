<?php
session_start();

// Tarkistetaan, onko käyttäjä kirjautunut
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php'); // Ohjataan kirjautumissivulle, jos käyttäjä ei ole kirjautunut
    exit;
}

// Tarkistetaan, että tiedosto on lähetetty
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['fileUpload'])) {
    $uploadDir = 'uploads/';  // Kansio, johon tiedostot tallennetaan
    $originalFileName = basename($_FILES['fileUpload']['name']);
    $fileExtension = pathinfo($originalFileName, PATHINFO_EXTENSION);

    // Luodaan uniikki tiedostonimi (esim. aikaleima + satunnainen osa)
    $uniqueFileName = uniqid(time() . "_", true) . "." . $fileExtension;
    $uploadFile = $uploadDir . $uniqueFileName;

    // Tarkistetaan, onko kansio olemassa, jos ei, luodaan se
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Ei rajoiteta tiedostotyyppejä, kaikki tyypit sallitaan
    // Jos haluat lisätä rajoituksia, voit tarkistaa tiedostotyypin tässä.
    
    // Yritetään siirtää tiedosto oikeaan hakemistoon
    if (move_uploaded_file($_FILES['fileUpload']['tmp_name'], $uploadFile)) {
        echo "Tiedosto on ladattu onnistuneesti! Tiedoston polku: " . $uploadFile;
    } else {
        echo "Tiedoston lataaminen epäonnistui.";
    }
} else {
    echo "Ei tiedostoa ladattu.";
}
?>
