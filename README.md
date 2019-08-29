# UserIMAP
Place this app in **/path/to/nextcloud/apps/**

## Possible settings v0.0.2 (/config/config.php)
`  'imap_host' => 'yourdomain.tld',
  'imap_inHost' => 'imap.yourdomain.tld',
  'imap_inPort' => 143,
  'imap_inSSL' => 'tls',
  'imap_outHost' => 'smtp.yourdomain.tld',
  'imap_outPort' => 587,
  'imap_outSSL' => 'tls',
  'imap_ud_host' => 'https://ud.yourdomain.tld', // User Details server that provides additional user details like displayname, groups, ...
  'user_backends' => 
  array (
    0 => 
    array (
      'class' => 'OC_User_IMAP_wUD',
      'arguments' => 
      array (
        0 => '{imap.yourdomain.tld:143}',
      ),
    ),
  ),`
