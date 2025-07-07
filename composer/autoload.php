<?php

// Nextcloud-konformer Autoloader für UserIMAP
// KORRIGIERT: AppInfo liegt in appinfo/, nicht lib/

spl_autoload_register(function ($class) {
    $prefix = 'OCA\\UserIMAP\\';
    $base_dir = __DIR__ . '/../';

    // Prüfen ob die Klasse unseren Namespace verwendet
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Relativen Klassennamen extrahieren
    $relative_class = substr($class, $len);
    
    // Spezielle Behandlung für AppInfo (liegt in appinfo/, nicht lib/)
    if (strpos($relative_class, 'AppInfo\\') === 0) {
        $file = $base_dir . 'appinfo/' . substr($relative_class, 8) . '.php';
    } else {
        // Alle anderen Klassen liegen in lib/
        $file = $base_dir . 'lib/' . str_replace('\\', '/', $relative_class) . '.php';
    }

    // Datei laden falls vorhanden
    if (file_exists($file)) {
        require_once $file;
    }
});