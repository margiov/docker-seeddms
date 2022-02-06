<?php
/**
 * Do authentication of users and session management
 *
 * @category   DMS
 * @package    SeedDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Uwe Steinmann
 * @version    Release: @package_version@
 */

require_once("inc.Utils.php");
require_once("inc.ClassNotificationService.php");
require_once("inc.ClassEmailNotify.php");
require_once("inc.ClassSession.php");
require_once("inc.ClassAccessOperation.php");

function __authenticate($username, $password) { /* {{{ */
	global $dms, $settings;

	$user = false;

	/* Authenticate against LDAP server {{{ */
	if (!$user && isset($settings->_ldapHost) && strlen($settings->_ldapHost)>0) {
		require_once("../inc/inc.ClassLdapAuthentication.php");
		$authobj = new SeedDMS_LdapAuthentication($dms, $settings);
		$user = $authobj->authenticate($username, $password);
	} /* }}} */

	/* Authenticate against SeedDMS database {{{ */
	else {
		require_once("../inc/inc.ClassDbAuthentication.php");
		$authobj = new SeedDMS_DbAuthentication($dms, $settings);
		$user = $authobj->authenticate($username, $password);
	} /* }}} */

	if (!$user) {
		return false;
	}

	if (($user->getID() == $settings->_guestID) && (!$settings->_enableGuestLogin)) {
		return false;
	}

	// Check if account is disabled
	if($user->isDisabled()) {
		return false;
	}

	// control admin IP address if required
	if ($user->isAdmin() && ($_SERVER['REMOTE_ADDR'] != $settings->_adminIP ) && ( $settings->_adminIP != "") ){
		return false;
	}

	return $user;
} /* }}} */

if (!isset($_SERVER['PHP_AUTH_USER'])) {
	header('WWW-Authenticate: Basic realm="'.$settings->_siteName.'"');
	header('HTTP/1.0 401 Unauthorized');
	echo getMLText('cancel_basic_authentication');
	exit;
} else {
	if(!($user = __authenticate($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']))) {
		header('WWW-Authenticate: Basic realm="'.$settings->_siteName.'"');
		header('HTTP/1.0 401 Unauthorized');
		echo getMLText('cancel_basic_authentication');
		exit;
	}
}

/* Clear login failures if login was successful */
$user->clearLoginFailures();

$dms->setUser($user);

require_once('inc/inc.Notification.php');

