<?php
declare(strict_types=1);

namespace OCA\UserIMAP\Service;

use OCP\IConfig;

class UserIMAPService {
    private IConfig $config;

    public function __construct(IConfig $config) {
        $this->config = $config;
    }

    /**
     * Holt den IMAP-Server String aus der Konfiguration
     */
    public function getIMAPConnectionString(): string {
        // Neue saubere Konfiguration (empfohlen)
        $imapServer = $this->config->getSystemValue('user_imap_server', '');
        if (!empty($imapServer)) {
            return $imapServer;
        }

        // Fallback: Aus einzelnen Werten zusammenbauen
        $host = $this->config->getSystemValue('imap_inHost', 'localhost');
        $port = (int) $this->config->getSystemValue('imap_inPort', 993);
        $ssl = $this->config->getSystemValue('imap_inSSL', 'ssl');
        
        $flags = match (strtolower($ssl)) {
            'ssl' => '/imap/ssl/validate-cert',
            'tls' => '/imap/tls/validate-cert',
            'notls' => '/imap/notls',
            default => '/imap/ssl/validate-cert',
        };
        
        return '{' . $host . ':' . $port . $flags . '}';
    }

    /**
     * Holt die E-Mail-Domain für Username-Mapping
     */
    public function getEmailDomain(): string {
        return $this->config->getSystemValue('imap_host', 'die-herzogs.com');
    }
}