<?php
declare(strict_types=1);

namespace OCA\UserIMAP;

use OCA\UserIMAP\Service\UserIMAPService;

class Base {
    private UserIMAPService $config;

    public function __construct(?UserIMAPService $config = null) {
        if ($config === null) {
            // hole Service manuell aus DI-Container, falls nicht per Konstruktor übergeben
            $app = new \OCA\UserIMAP\AppInfo\Application();
            $this->config = $app->getContainer()->get(UserIMAPService::class);
        } else {
            $this->config = $config;
        }
    }

    public function authenticate(string $uid, string $password): bool {
        $flags = match (strtolower($this->config->getSSL())) {
            'ssl' => '/imap/ssl/validate-cert',
            'tls' => '/imap/tls/validate-cert',
            'notls' => '/imap/notls',
            default => '',
        };

        $host = $this->config->getHost();
        $port = $this->config->getPort();

        $mbox = @imap_open(
            '{' . $host . ':' . $port . $flags . '}INBOX',
            $uid,
            $password,
            0,
            1,
            ['DISABLE_AUTHENTICATOR' => 'GSSAPI']
        );

        if ($mbox) {
            imap_close($mbox);
            return true;
        }

        return false;
    }
}
