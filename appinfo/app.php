<?php
declare(strict_types=1);

/**
 * Legacy App Bootstrap für UserIMAP
 * Funktioniert mit allen Nextcloud-Versionen
 */

// Manual includes für sicheres Laden
require_once __DIR__ . '/../lib/Service/UserIMAPService.php';
require_once __DIR__ . '/../lib/IMAP.php';

try {
    // Service und Backend erstellen
    $config = \OC::$server->getConfig();
    
    // Richtigen Logger für Nextcloud 27+ verwenden
    $logger = \OC::$server->get(\Psr\Log\LoggerInterface::class);
    
    $imapService = new \OCA\UserIMAP\Service\UserIMAPService($config);
    $backend = new \OCA\UserIMAP\IMAP($imapService, $logger);
    
    // Backend beim UserManager registrieren
    $userManager = \OC::$server->getUserManager();
    $userManager->registerBackend($backend);
    
    // Success-Log
    $logger->info('UserIMAP backend loaded via legacy app.php');
    
} catch (\Exception $e) {
    // Fallback auf alten Logger falls der neue nicht verfügbar ist
    $fallbackLogger = \OC::$server->getLogger();
    $fallbackLogger->error('UserIMAP failed to load: ' . $e->getMessage());
}