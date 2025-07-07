<?php
declare(strict_types=1);

namespace OCA\UserIMAP;

class Base {
    private string $imap_host = 'imap.deinserver.de';
    private int $imap_port = 993;
    private bool $use_ssl = true;

    public function authenticate(string $uid, string $password): bool {
        $flags = $this->use_ssl ? '/imap/ssl/validate-cert' : '/imap/notls';

        $mbox = @imap_open(
            '{' . $this->imap_host . ':' . $this->imap_port . $flags . '}INBOX',
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
