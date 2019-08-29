<?php
/**
 * Copyright (c) 2014 Christian Weiske <cweiske@cweiske.de>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */
namespace OCA\userimap;
//use \OC_DB;
//use OCP\Security\ICrypto;

/**
 * Base class for IMAP auth implementations that stores users
 * on their first login in a local table and load their user details from an identity server.
 * This is required for making many of the user-related ownCloud functions
 * work, including sharing files with them.
 *
 * @category Apps
 * @package  UserIMAP
 * @author   Christian Weiske <cweiske@cweiske.de>
 * @author	Stefan Herzog <nextcloud@devel.stefan-herzog.com>
 * @license  http://www.gnu.org/licenses/agpl AGPL
 * @link     http://github.com/owncloud/apps
 */


abstract class Base extends \OC\User\Backend {
	protected $backend = '';
	protected $crypto;
	//protected $crypto = \OC::$server->getCrypto();

	/**
	 * Create new instance, set backend name
	 *	 * @param string $backend Identifier of the backend
	 */
	public function __construct($backend) {
		$this->backend = $backend;
		$this->crypto = \OC::$server->getCrypto();
		$this->app = new \OCP\AppFramework\App('userimap');
		$this->config = \OC::$server->getConfig();

	}

	/**
	 * Delete a user
	 *
	 * @param string $uid The username of the user to delete
	 *
	 * @return bool
	 */
	public function deleteUser($uid) {
		/*
		OC_DB::executeAudited(
			'DELETE FROM `*PREFIX*users_external` WHERE `uid` = ? AND `backend` = ?',
			array($uid, $this->backend)
		);
		*/
		$query = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$query->delete('users_external')
			->where($query->expr()->eq('uid', $query->createNamedParameter($uid)))
			->andWhere($query->expr()->eq('backend', $query->createNamedParameter($this->backend)));
		$query->execute();
		return true;
	}

	/**
	 * Get display name of the user
	 *
	 * @param string $uid user ID of the user
	 *
	 * @return string display name
	 */
	public function getDisplayName($uid) {
		$query = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$query->select('displayname')
			->from('users_external')
			->where($query->expr()->eq('uid', $query->createNamedParameter($uid)))
			->andWhere($query->expr()->eq('backend', $query->createNamedParameter($this->backend)));
		$result = $query->execute();
		$user = $result->fetch();
		$result->closeCursor();

		$displayName = trim($user['displayname'], ' ');
		if (!empty($displayName)) {
			return $displayName;
		} else {
			return $uid;
		}

		/*
		$user = OC_DB::executeAudited(
			'SELECT `displayname` FROM `*PREFIX*users_external`'
			. ' WHERE `uid` = ? AND `backend` = ?',
			array($uid, $this->backend)
		)->fetchRow();
		$displayName = trim($user['displayname'], ' ');
		if (!empty($displayName)) {
			return $displayName;
		} else {
			return $uid;
		}
		*/
	}

	/**
	 * Get a list of all display names and user ids.
	 *
	 * @return array with all displayNames (value) and the corresponding uids (key)
	 */
	public function getDisplayNames($search = '', $limit = null, $offset = null) {

		$connection = \OC::$server->getDatabaseConnection();
		$query = $connection->getQueryBuilder();
		$query->select('uid', 'displayname')
			->from('users_external')
			->where($query->expr()->iLike('displayname', $query->createNamedParameter('%' . $connection->escapeLikeParameter($search) . '%')))
			->orWhere($query->expr()->iLike('uid', $query->createNamedParameter('%' . $connection->escapeLikeParameter($search) . '%')))
			->andWhere($query->expr()->eq('backend', $query->createNamedParameter($this->backend)));
		if ($limit) {
			$query->setMaxResults($limit);
		}
		if ($offset) {
			$query->setFirstResult($offset);
		}
		$result = $query->execute();

		$displayNames = [];
		while ($row = $result->fetch()) {
			$displayNames[$row['uid']] = $row['displayname'];
		}
		$result->closeCursor();

		return $displayNames;

		/*

		$result = OC_DB::executeAudited(
			array(
				'sql' => 'SELECT `uid`, `displayname` FROM `*PREFIX*users_external`'
					. ' WHERE (LOWER(`displayname`) LIKE LOWER(?) '
					. ' OR LOWER(`uid`) LIKE LOWER(?)) AND `backend` = ?',
				'limit'  => $limit,
				'offset' => $offset
			),
			array('%' . $search . '%', '%' . $search . '%', $this->backend)
		);

		$displayNames = array();
		while ($row = $result->fetchRow()) {
			$displayNames[$row['uid']] = $row['displayname'];
		}

		return $displayNames;
		*/
	}

	/**
	* Get a list of all users
	*
	* @return array with all uids
	*/
	public function getUsers($search = '', $limit = null, $offset = null) {
		$connection = \OC::$server->getDatabaseConnection();
		$query = $connection->getQueryBuilder();
		$query->select('uid')
			->from('users_external')
			->where($query->expr()->iLike('uid', $query->createNamedParameter($connection->escapeLikeParameter($search) . '%')))
			->andWhere($query->expr()->eq('backend', $query->createNamedParameter($this->backend)));
		if ($limit) {
			$query->setMaxResults($limit);
		}
		if ($offset) {
			$query->setFirstResult($offset);
		}
		$result = $query->execute();

		$users = [];
		while ($row = $result->fetch()) {
			$users[] = $row['uid'];
		}
		$result->closeCursor();

		return $users;

		/*
		$result = OC_DB::executeAudited(
			array(
				'sql' => 'SELECT `uid` FROM `*PREFIX*users_external`'
					. ' WHERE LOWER(`uid`) LIKE LOWER(?) AND `backend` = ?',
				'limit' => $limit,
				'offset' => $offset
			),
			array($search . '%', $this->backend)
		);
		$users = array();
		while ($row = $result->fetchRow()) {
			$users[] = $row['uid'];
		}
		*/
	}

	/**
	 * Determines if the backend can enlist users
	 *
	 * @return bool
	 */
	public function hasUserListings() {
		return true;
	}

	/**
	 * Change the display name of a user
	 *
	 * @param string $uid         The username
	 * @param string $displayName The new display name
	 *
	 * @return true/false
	 */
	public function setDisplayName() {
		if (!$this->userExists($uid)) {
			return false;
		}

		$query = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$query->update('users_external')
			->set('displayname', $query->createNamedParameter($displayName))
			->where($query->expr()->eq('uid', $query->createNamedParameter($uid)))
			->andWhere($query->expr()->eq('backend', $query->createNamedParameter($this->backend)));
		$query->execute();

		return true;

		/*
		if (!$this->userExists($this->uid)) {
			return false;
		}
		OC_DB::executeAudited(
			'UPDATE `*PREFIX*users_external` SET `displayname` = ?'
			. ' WHERE LOWER(`uid`) = ? AND `backend` = ?',
			array($this->displayName, $this->uid, $this->backend)
		);
		return true;
		*/
	}

	/**
	 * Create user record in database
	 *
	 * @param string $uid The username
	 *
	 * @return void
	 */
	protected function storeUser($uid, $groups = [])
	{
mail('stefan@die-herzogs.com', 'Debug base.php storeUser', json_encode(array('uid'=>$uid, 'this->uid' => $this->uid)));
exit;
		
		
		if (!$this->userExists($this->uid))
		{
			if(!$this->userExists($uid)) {
				

				$query = \OC::$server->getDatabaseConnection()->getQueryBuilder();
				$query->insert('users_external')
					->values([
						'uid' => $query->createNamedParameter($uid),
						'backend' => $query->createNamedParameter($this->backend),
						]);
				$query->execute();

				if ($groups) {
					$createduser = \OC::$server->getUserManager()->get($uid);
					foreach ($groups as $group) {
						\OC::$server->getGroupManager()->createGroup($group)->addUser($createduser);
					}
				}
			}

			$p = new \stdClass;
			$p->appid	= 'core';
			$p->ck		= 'lang';
			$p->cv		= 'de';
			$preferences[] = $p;

			$p = new \stdClass;
			$p->appid	= 'settings';
			$p->ck		= 'email';
			$p->cv		= $this->uid . '@' . $this->config->getSystemValue('imap_host');
			$preferences[] = $p;

			$p = new \stdClass;
			$p->appid	= 'activity';
			$p->ck		= 'notify_email_comments';
			$p->cv		= '0';
			$preferences[] = $p;

			$p = new \stdClass;
			$p->appid	= 'activity';
			$p->ck		= 'notify_email_file_changed';
			$p->cv		= '0';
			$preferences[] = $p;

			$p = new \stdClass;
			$p->appid	= 'activity';
			$p->ck		= 'notify_email_file_created';
			$p->cv		= '0';
			$preferences[] = $p;

			$p = new \stdClass;
			$p->appid	= 'activity';
			$p->ck		= 'notify_email_file_deleted';
			$p->cv		= '0';
			$preferences[] = $p;

			$p = new \stdClass;
			$p->appid	= 'activity';
			$p->ck		= 'notify_email_restored';
			$p->cv		= '0';
			$preferences[] = $p;

			$p = new \stdClass;
			$p->appid	= 'activity';
			$p->ck		= 'notify_email_public_links';
			$p->cv		= '0';
			$preferences[] = $p;

			$p = new \stdClass;
			$p->appid	= 'activity';
			$p->ck		= 'notify_email_remote_share';
			$p->cv		= '0';
			$preferences[] = $p;

			$p = new \stdClass;
			$p->appid	= 'activity';
			$p->ck		= 'notify_email_shared';
			$p->cv		= '0';
			$preferences[] = $p;

			$p = new \stdClass;
			$p->appid	= 'activity';
			$p->ck		= 'notify_email_systemtags';
			$p->cv		= '0';
			$preferences[] = $p;

			$p = new \stdClass;
			$p->appid	= 'activity';
			$p->ck		= 'notify_setting_batchtime';
			$p->cv		= '3600';
			$preferences[] = $p;

			$p = new \stdClass;
			$p->appid	= 'activity';
			$p->ck		= 'notify_setting_self';
			$p->cv		= '1';
			$preferences[] = $p;

			$p = new \stdClass;
			$p->appid	= 'activity';
			$p->ck		= 'notify_setting_selfemail';
			$p->cv		= '0';
			$preferences[] = $p;

			$p = new \stdClass;
			$p->appid	= 'activity';
			$p->ck		= 'notify_stream_comments';
			$p->cv		= '1';
			$preferences[] = $p;

			$p = new \stdClass;
			$p->appid	= 'activity';
			$p->ck		= 'notify_stream_file_changed';
			$p->cv		= '1';
			$preferences[] = $p;

			$p = new \stdClass;
			$p->appid	= 'activity';
			$p->ck		= 'notify_stream_file_created';
			$p->cv		= '1';
			$preferences[] = $p;

			$p = new \stdClass;
			$p->appid	= 'activity';
			$p->ck		= 'notify_stream_file_deleted';
			$p->cv		= '1';
			$preferences[] = $p;

			$p = new \stdClass;
			$p->appid	= 'activity';
			$p->ck		= 'notify_stream_restored';
			$p->cv		= '1';
			$preferences[] = $p;

			$p = new \stdClass;
			$p->appid	= 'activity';
			$p->ck		= 'notify_stream_public_links';
			$p->cv		= '1';
			$preferences[] = $p;

			$p = new \stdClass;
			$p->appid	= 'activity';
			$p->ck		= 'notify_stream_remote_share';
			$p->cv		= '1';
			$preferences[] = $p;

			$p = new \stdClass;
			$p->appid	= 'activity';
			$p->ck		= 'notify_stream_shared';
			$p->cv		= '1';
			$preferences[] = $p;

			$p = new \stdClass;
			$p->appid	= 'activity';
			$p->ck		= 'notify_stream_systemtags';
			$p->cv		= '1';
			$preferences[] = $p;



			foreach($preferences as $preference)
			{
				//$this->setPreference($preference->appid, $preference->ck, $preference->cv);
				// That's an already existing function from within nextcloud that does the same as the one above
				$this->config->setUserValue($this->uid, $preference->appid, $preference->ck, $preference->cv);
			}
			$this->config->setUserValue($this->uid, 'rainloop', 'rainloop-autologin-password', $this->pw);


		}
		return true;
	}


	protected function updateMailAccount()
	{
		/*
		// Remove mail account if it already exists
		OC_DB::executeAudited(
			'DELETE FROM `*PREFIX*mail_accounts` WHERE `user_id` = ?',
			array($this->uid)
		);*/

		$query = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$query->delete('mail_accounts')
			->where($query->expr()->eq('user_id', $query->createNamedParameter($this->uid)));
		$query->execute();


		
		$this->pw = $this->crypto->encrypt($this->pw);
		$query = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$query->insert('mail_accounts')
			  ->values([
					'user_id' =>$query->createNamedParameter($this->uid),
					'name' =>$query->createNamedParameter($this->displayName),
					'email' => $query->createNamedParameter($this->emailAddress),
					'inbound_host' => $query->createNamedParameter($this->inHost),
					'inbound_port' => $query->createNamedParameter($this->inPort),
					'inbound_ssl_mode' => $query->createNamedParameter($this->inSSL),
					'inbound_user' => $query->createNamedParameter($this->emailAddress),
					'inbound_password' => $query->createNamedParameter($this->pw),
					'outbound_host' => $query->createNamedParameter($this->outHost),
					'outbound_port' => $query->createNamedParameter($this->outPort),
					'outbound_ssl_mode' => $query->createNamedParameter($this->outSSL),
					'outbound_user' => $query->createNamedParameter($this->emailAddress),
					'outbound_password' => $query->createNamedParameter($this->pw)]);
		$query->execute();
		

	

		
		/*OC_DB::executeAudited(
			'INSERT INTO `*PREFIX*mail_accounts` ( `user_id`, `name`, `email`, `inbound_host`, `inbound_port`, `inbound_ssl_mode`, `inbound_user`, `inbound_password`, `outbound_host`, `outbound_port`, `outbound_ssl_mode`, `outbound_user`, `outbound_password` )'
			. ' VALUES( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?	)',
			array($this->uid, $this->displayName, $this->emailAddress, $this->inHost, $this->inPort, $this->inSSL, $this->emailAddress, $this->pw, $this->outHost, $this->outPort, $this->outSSL, $this->emailAddress, $this->pw)
		);*/


	}

	protected function addUserToGroups()
	{
		// Remove user from all groups to prevent duplicated entries and save the actuality of the assignments
		$this->removeUserFromAllGroups();

		foreach($this->userGroups as $group)
		{
			// If group doesn't exist, create group and add user afterwards
			if(!$this->groupExists($group))
			{
				// Create new group
				$this->createNewGroup($group);
			}

			// Add user to group
			$this->addUserToGroup($group);
		}
	}


	protected function createNewGroup($group)
	{
		/*
		OC_DB::executeAudited(
			'INSERT INTO `*PREFIX*groups` ( `gid`)'
			. ' VALUES( ?)',
			array($group)
		);
		*/
		
		$query = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$query->insert('groups')
			  ->values([
					'gid' =>$query->createNamedParameter($group)]);
		$query->execute();
		return true;
	}


	protected function addUserToGroup($group)
	{
		/*
		OC_DB::executeAudited(
			'INSERT INTO `*PREFIX*group_user` ( `uid`, `gid` )'
			. ' VALUES( ?, ? )',
			array($this->uid, $group));
		*/
		$query = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$query->insert('group_user')
			  ->values([
					'uid' =>$query->createNamedParameter($this->uid),
			  		'gid' =>$query->createNamedParameter($group)]);
		$query->execute();
		return true;
	}

	protected function removeUserFromAllGroups()
	{
		/*
		OC_DB::executeAudited(
			'DELETE FROM `*PREFIX*group_user` WHERE `uid` = ?',
			array($this->uid)
		);
		*/
		$query = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$query->delete('group_user')
			->where($query->expr()->eq('uid', $query->createNamedParameter($this->uid)));
		$query->execute();
		return true;
	}


	protected function isUserInGroup($group)
	{
		/*
		$result = OC_DB::executeAudited(
			'SELECT COUNT(*) FROM `*PREFIX*group_user`'
			. ' WHERE LOWER(`uid`) = LOWER(?) AND `gid`= ?',
			array($this->uid, $group)
		);

		return $result->fetchOne() > 0;
		*/
		
		$connection = \OC::$server->getDatabaseConnection();
		$query = $connection->getQueryBuilder();
		$query->select($query->func()->count('*'))
			->from('group_user')
			->where($query->expr()->iLike('uid', $query->createNamedParameter($this->uid)))
			->andWhere($query->expr()->eq('gid', $query->createNamedParameter($group)));
		$result = $query->execute();
		$users = $result->fetchColumn();
		$result->closeCursor();

		return $users > 0;
	}


	/**
	 * Check if a user exists
	 *
	 * @param string $uid the username
	 *
	 * @return boolean
	 */
	public function userExists($uid) {

		$connection = \OC::$server->getDatabaseConnection();
		$query = $connection->getQueryBuilder();
		$query->select($query->func()->count('*')) //, 'num_users'))
			->from('users_external')
			->where($query->expr()->eq('uid', $query->createNamedParameter($connection->escapeLikeParameter($uid))))
			->andWhere($query->expr()->eq('backend', $query->createNamedParameter($this->backend)));
		$result = $query->execute();
		$users = $result->fetchColumn();
		$result->closeCursor();

//mail('stefan@die-herzogs.com', 'Debug base.php userExists ohne num_users', json_encode(array('uid'=>$uid, 'userExists' => $users > 0)));
//exit;
		
		// Returns a boolean value if the users exists or not
		return $users > 0;
	}



	/**
	 * Check if a group exists
	 *
	 * @param string $groupName Name of the group
	 *
	 * @return boolean
	 */
	public function groupExists($gid) {
		/*
		$connection = \OC::$server->getDatabaseConnection();
		$query = $connection->getQueryBuilder();
		$query->select($query->func()->count('*'))
			->from('groups')
			->where($query->expr()->eq('gid', $query->createNamedParameter($connection->escapeLikeParameter($gid))));
		$result = $query->execute();
		$groups = $result->fetchColumn();
		$result->closeCursor();
		*/

		$connection = \OC::$server->getDatabaseConnection();
		$query = $connection->getQueryBuilder();
		$query->select($query->func()->count('*')) //, 'num_users'))
			->from('groups')
			->where($query->expr()->eq('gid', $query->createNamedParameter($connection->escapeLikeParameter($gid))));
		$result = $query->execute();
		$groups = $result->fetchColumn();
		$result->closeCursor();
		
//mail('stefan@die-herzogs.com', 'Debug base.php groupExists ohne num_users', json_encode(array('gid'=>$gid, 'groupExists' => $groups > 0)));
//exit;	
		
		/*
		return $users > 0;
		$result = OC_DB::executeAudited(
			'SELECT COUNT(*) FROM `*PREFIX*groups`'
			. ' WHERE LOWER(`gid`) = LOWER(?)',
			array($gid)
		);
		*/

		//return $result->fetchOne() > 0;
		return $groups > 0;
	}



	protected function udServerExists()
	{
		if($this->config->getSystemValue('imap_ud_host') != '' && !is_null($this->config->getSystemValue('imap_ud_host')))
		{
			return true;
		}
		else
		{
			return false;
		}
	}


	private function setPreference($appid, $configkey, $configvalue)
	{
		$query = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$query->insert('preferences')
			->values([
				'userid' => $query->createNamedParameter($this->uid),
				'appid' => $query->createNamedParameter($appid),
				'configkey' => $query->createNamedParameter($configkey),
				'configvalue' => $query->createNamedParameter($configvalue),
			]);
		$query->execute();

		return true;

		/*
		OC_DB::executeAudited(
			'INSERT INTO `*PREFIX*preferences` ( `userid`, `appid`, `configkey`, `configvalue`)'
			. ' VALUES( ?, ?, ?, ?)',
			array(, $appid, $configkey, $configvalue)
		);	*/
	}




	// Retrieve user data from ud server
	public function getUserDetails($username)
	{
		$ud_host = $this->config->getSystemValue('imap_ud_host');
		if(substr($ud_host, 0, 5) != 'https')
		{
			$ud_host = 'https://'.$ud_host;
		}

		$url = $ud_host.'?uid='.$username;

        // create curl resource
        $ch = curl_init();

        // set url
        curl_setopt($ch, CURLOPT_URL, $url);

        //return the transfer as a string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // $output contains the output string
        $response = curl_exec($ch);

        // get status code
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // close curl resource to free up system resources
        curl_close($ch);

        if($code != 200)
        {
	        return false;
        }
        else
        {
	        return json_decode($response);
        }
	}
}
