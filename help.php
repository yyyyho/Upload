<?php
session_start();
include('log.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Ladataan käyttäjän asetukset teemaa varten
$username = $_SESSION['user_id'];
$userSettingsFile = "user_settings/{$username}_settings.json";
if (file_exists($userSettingsFile)) {
    $userSettings = json_decode(file_get_contents($userSettingsFile), true);
    $theme = $userSettings['theme'] ?? 'light';
} else {
    $theme = 'light';
}
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/S7JLkFy1/Heartcrown.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ohjeet</title>
    <link rel="stylesheet" href="style.css">
    <?php if ($theme === 'dark'): ?>
    <link rel="stylesheet" href="dark-theme.css">
    <?php endif; ?>
</head>
<body class="<?php echo $theme; ?>-theme">
    <?php include('header.php'); ?>

    <div class="container">
        <h2>Ohjeet</h2>
        
        <section>
            <h3>Tiedostojen lataaminen</h3>
            <p>Voit ladata tiedostoja seuraavasti:</p>
            <ol>
                <li>Mene etusivulle</li>
                <li>Klikkaa "Valitse tiedosto" -painiketta</li>
                <li>Valitse haluamasi tiedosto tietokoneeltasi</li>
                <li>Klikkaa "Lataa" -painiketta</li>
            </ol>
        </section>

        <section>
            <h3>Asetusten muuttaminen</h3>
            <p>Voit muuttaa sovelluksen asetuksia seuraavasti:</p>
            <ol>
                <li>Klikkaa "Valikko" -painiketta oikeassa yläkulmassa</li>
                <li>Valitse "Asetukset"</li>
                <li>Muuta haluamiasi asetuksia</li>
                <li>Klikkaa "Tallenna asetukset" -painiketta</li>
            </ol>
        </section>

        <section>
            <h3>Tiedostojen hallinta</h3>
            <p>Voit hallita tiedostojasi seuraavasti:</p>
            <ol>
                <li>Mene "Tiedostot" -sivulle valikon kautta</li>
                <li>Näet listan lataamistasi tiedostoista</li>
                <li>Voit lajitella tiedostoja nimen, päivämäärän tai koon mukaan</li>
                <li>Klikkaa tiedoston nimeä ladataksesi sen</li>
                <li>Käytä poista-painiketta poistaaksesi tiedoston</li>
            </ol>
        </section>

        <section>
            <h3>Tuki</h3>
            <p>Jos tarvitset lisäapua, ota yhteyttä ylläpitoon osoitteessa: Heartcrownn@gmail.com</p>
        </section>

        <section>
            <h3>Kategorioiden hallinta</h3>
            <p>Voit hallita tiedostojen kategorioita seuraavasti:</p>
            <ol>
                <li>Mene "Tiedostot" -sivulle</li>
                <li>Klikkaa "Hallitse kategorioita" -painiketta hakuosion oikeassa yläkulmassa</li>
                <li>Voit lisätä uusia kategorioita tai poistaa olemassa olevia</li>
                <li>Kategorioiden avulla voit järjestää tiedostosi helpommin löydettäviksi</li>
            </ol>
        </section>

        <section>
            <h3>Tiedostojen lataaminen raahaamalla</h3>
            <p>Voit ladata tiedostoja myös raahaamalla ne suoraan selaimeen:</p>
            <ol>
                <li>Mene etusivulle</li>
                <li>Raahaa tiedostot tietokoneeltasi suoraan latauslaatikkoon</li>
                <li>Valitse kategoria tiedostoille</li>
                <li>Klikkaa "Lataa tiedostot" -painiketta</li>
            </ol>
        </section>

        <section>
            <h3>Tiedostojen esikatselut</h3>
            <p>Voit esikatsella tiedostoja Tiedostot-sivulla:</p>
            <ol>
                <li>Vie hiiri tiedoston nimen päälle tiedostolistassa</li>
                <li>Kuvatiedostoista näytetään esikatselu oikeassa paneelissa</li>
                <li>Videotiedostoista näytetään videosoitin</li>
                <li>Äänitiedostoista näytetään äänisoitin</li>
            </ol>
        </section>

        <section>
            <h3>Tiedostojen hakeminen ja suodattaminen</h3>
            <p>Voit hakea ja suodattaa tiedostoja seuraavasti:</p>
            <ol>
                <li>Mene "Tiedostot" -sivulle</li>
                <li>Käytä hakukenttää etsiäksesi tiedostoja nimen perusteella</li>
                <li>Käytä kategoria-suodatinta näyttääksesi vain tietyn kategorian tiedostot</li>
                <li>Käytä tiedostotyyppi-suodatinta näyttääksesi vain kuvat tai muut tiedostot</li>
            </ol>
        </section>

        <section>
            <h3>Teeman vaihtaminen</h3>
            <p>Voit vaihtaa sovelluksen teemaa seuraavasti:</p>
            <ol>
                <li>Mene "Asetukset" -sivulle</li>
                <li>Valitse haluamasi teema (vaalea tai tumma)</li>
                <li>Klikkaa "Tallenna asetukset" -painiketta</li>
                <li>Teema vaihtuu välittömästi käyttöön</li>
            </ol>
        </section>

        <section>
            <h3>Tiedostojen järjestäminen</h3>
            <p>Voit järjestää tiedostot haluamallasi tavalla:</p>
            <ol>
                <li>Mene "Asetukset" -sivulle</li>
                <li>Valitse tiedostojen järjestystapa (nimi, päivämäärä, koko tai kategoria)</li>
                <li>Klikkaa "Tallenna asetukset" -painiketta</li>
                <li>Tiedostot näytetään valitsemassasi järjestyksessä Tiedostot-sivulla</li>
            </ol>
        </section>
    </div>

    <script src="script.js"></script>
</body>
</html>

