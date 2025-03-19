<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_POST['viewMode'])) {
    exit;
}

$username = $_SESSION['user_id'];
$viewMode = $_POST['viewMode'];

// Only accept valid view modes
if ($viewMode !== 'grid' && $viewMode !== 'list') {
    exit;
}

// Load user settings
$userSettingsFile = "user_settings/{$username}_settings.json";
if (file_exists($userSettingsFile)) {
    $userSettings = json_decode(file_get_contents($userSettingsFile), true);
} else {
    $userSettings = [
        'theme' => 'light',
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
}

// Update view mode
$userSettings['viewMode'] = $viewMode;

// Save settings
file_put_contents($userSettingsFile, json_encode($userSettings, JSON_PRETTY_PRINT));

// Return success
echo json_encode(['success' => true]);

