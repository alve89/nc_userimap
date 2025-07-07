<?php
declare(strict_types=1);

namespace OCA\UserIMAP;

use OCP\UserInterface;
use OCA\UserIMAP\Service\UserIMAPService;
use Psr\Log\LoggerInterface;

class IMAP implements UserInterface {
    private UserIMAPService $imapService;
    private LoggerInterface $logger;

    public function __construct(UserIMAPService $imapService, LoggerInterface $logger) {
        $this->imapService = $imapService;
        $this->logger = $logger;
    }

    public function checkPassword(string $uid, string $password): string|bool {
        $this->logger->info('UserIMAP checkPassword called', ['user' => $uid]);
        
        if (empty($uid) || empty($password)) {
            $this->logger->warning('UserIMAP: Empty uid or password');
            return false;
        }

        try {
            $result = $this->authenticate($uid, $password) ? $uid : false;
            $this->logger->info('UserIMAP Auth result', [
                'user' => $uid, 
                'success' => (bool)$result
            ]);
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('IMAP Authentication error: ' . $e->getMessage(), [
                'user' => $uid,
                'exception' => $e
            ]);
            return false;
        }
    }

    private function authenticate(string $uid, string $password): bool {
        $connectionString = $this->imapService->getIMAPConnectionString();
        
        // KAS-Server benötigen oft die vollständige E-Mail-Adresse
        $username = $uid;
        if (!str_contains($uid, '@')) {
            // Domain aus config.php holen oder Standard setzen
            $domain = $this->imapService->getEmailDomain();
            $username = $uid . '@' . $domain;
        }
        
        // Debug-Log
        $this->logger->info('IMAP Auth attempt', [
            'original_user' => $uid,
            'mapped_user' => $username,
            'connection' => $connectionString
        ]);

        $mbox = @imap_open(
            $connectionString . 'INBOX',
            $username,  // Verwende mapped username
            $password,
            0,
            1,
            ['DISABLE_AUTHENTICATOR' => 'GSSAPI']
        );

        if ($mbox) {
            imap_close($mbox);
            $this->logger->info('IMAP Auth successful', ['user' => $uid]);
            return true;
        }

        $error = imap_last_error();
        $this->logger->warning('IMAP Auth failed', [
            'user' => $uid,
            'mapped_user' => $username,
            'error' => $error
        ]);

        return false;
    }

    public function userExists($uid): bool {
        return true; // IMAP-Nutzer existieren extern
    }

    public function getHome($uid): ?string {
        return null;
    }

    public function getDisplayName($uid): string {
        return $uid;
    }

    public function deleteUser($uid): bool {
        return false;
    }

    public function setDisplayName($uid, $displayName): bool {
        return false;
    }

    public function getDisplayNames($search = '', $limit = null, $offset = null): array {
        return [];
    }

    public function countUsers(): int {
        return 0;
    }

    public function getUsers($search = '', $limit = null, $offset = null): array {
        return [];
    }

    public function hasUserListings(): bool {
        return false;
    }

    public function implementsActions($actions): bool {
        return ($actions & \OC\User\Backend::CHECK_PASSWORD) === \OC\User\Backend::CHECK_PASSWORD;
    }
}