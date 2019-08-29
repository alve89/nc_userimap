<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

//namespace OCA\userimap;

/**
 * User authentication against an IMAP mail server
 *
 * @category Apps
 * @package  UserIMAP
 * @author   Robin Appelman <icewind@owncloud.com>
 * @author	 Stefan Herzog <nextcloud@devel.stefan-herzog.com>
 * @license  http://www.gnu.org/licenses/agpl AGPL
 * @link     http://github.com/owncloud/apps
 */




class OC_User_IMAP_wUD extends \OCA\userimap\Base {
	private $mailbox;

	/**
	 * Create new IMAP authentication provider
	 *
	 * @param string $mailbox PHP imap_open mailbox definition, e.g.
	 *                        {127.0.0.1:143/imap/readonly}
	 */
	public function __construct($mailbox) {
		parent::__construct($mailbox);
		$this->mailbox=$mailbox;
		//$this->app = new \OCP\AppFramework\App('userimap');
		//$config = \OC::$server->getConfig();
	}

	/**
	 * Check if the password is correct without logging in the user
	 *
	 * @param string $uid      The username
	 * @param string $password The password
	 *
	 * @return true/false
	 */
	public function checkPassword($uid, $password)
	{
		$this->pw = $password;
		
		
		// Check if uid already contains @host.tld and add it if not
		if(!strstr($uid, '@' . $this->config->getSystemValue('imap_host')))
		{
			
			$this->mailHost = $this->config->getSystemValue('imap_host');
			$this->emailAddress = trim($uid.'@'.$this->mailHost);
			$uid = $this->emailAddress;
			$uid = mb_strtolower($uid);
		}


		if (!function_exists('imap_open'))
		{
			OCP\Util::writeLog('user_imap', 'ERROR: PHP imap extension is not installed', OCP\Util::ERROR);
			return false;
		}

		// Try to authenticate user against IMAP server
		$mbox = @imap_open($this->mailbox, $uid, $password, OP_HALFOPEN, 1);
		imap_errors();
		imap_alerts();
		if($mbox !== FALSE) {
			imap_close($mbox);

			$this->inHost = $this->config->getSystemValue('imap_inHost');
			$this->inPort = $this->config->getSystemValue('imap_inPort');
			$this->inSSL = $this->config->getSystemValue('imap_inSSL');

			$this->outHost = $this->config->getSystemValue('imap_outHost');
			$this->outPort = $this->config->getSystemValue('imap_outPort');
			$this->outSSL = $this->config->getSystemValue('imap_outSSL');

			// uid is the username given in the login form without @host.tld
			$this->uid = substr($uid, 0, strpos($uid, '@'));

			// Check if UD server exists to retrieve user details
			if($this->udServerExists())
			{
				// Retrieve user details from identity server
				$this->userData = $this->getUserDetails($this->uid);

				// Create display name of the user
				$this->displayName = $this->userData->firstname . ' ' . $this->userData->lastname;

				// Set a new class property for the users groups
				$this->userGroups = $this->userData->groups;
			}

			if (!$this->userExists($this->uid))
			{
				// Store as new user if it not exists
				$this->storeUser($this->uid);

				// Check if UD server exists to retrieve user details
				if($this->udServerExists())
				{
					// Set displayed name of user
					$this->setDisplayName();
				}
			}

			// Check if UD server exists to retrieve user details
			if($this->udServerExists())
			{
				// Remove user from all groups and add it to the retrieved groups
				$this->addUserToGroups();

				// Update mail account to keep password updated
				$this->updateMailAccount();
			}

			return $this->uid;
		}
		else
		{
			return false;
		}
	}
}
