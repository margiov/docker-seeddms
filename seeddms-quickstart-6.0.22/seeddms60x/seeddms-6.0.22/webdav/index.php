<?php
include("../inc/inc.Settings.php");

include("Log.php");
require_once("../inc/inc.Language.php");
require_once("../inc/inc.Utils.php");

$logger = getLogger('webdav-');

require_once("../inc/inc.Init.php");
require_once("../inc/inc.Extension.php");
require_once("../inc/inc.DBInit.php");
require_once("../inc/inc.ClassNotificationService.php");
require_once("../inc/inc.ClassEmailNotify.php");
require_once("../inc/inc.Notification.php");
require_once("../inc/inc.ClassController.php");

$notifier = new SeedDMS_NotificationService($logger, $settings);

if(isset($GLOBALS['SEEDDMS_HOOKS']['notification'])) {
	foreach($GLOBALS['SEEDDMS_HOOKS']['notification'] as $notificationObj) {
		if(method_exists($notificationObj, 'preAddService')) {
			$notificationObj->preAddService($dms, $notifier);
		}
	}
}

if($settings->_enableEmail) {
	$notifier->addService(new SeedDMS_EmailNotify($dms, $settings->_smtpSendFrom, $settings->_smtpServer, $settings->_smtpPort, $settings->_smtpUser, $settings->_smtpPassword));
}

if(isset($GLOBALS['SEEDDMS_HOOKS']['notification'])) {
	foreach($GLOBALS['SEEDDMS_HOOKS']['notification'] as $notificationObj) {
		if(method_exists($notificationObj, 'postAddService')) {
			$notificationObj->postAddService($dms, $notifier);
		}
	}
}

include("webdav.php");
$server = new HTTP_WebDAV_Server_SeedDMS();
$server->ServeRequest($dms, $settings, $logger, $notifier, $authenticator);
//$files = array();
//$options = array('path'=>'/Test1/subdir', 'depth'=>1);
//echo $server->MKCOL(&$options);

?>
