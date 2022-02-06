<?php
//    SeedDMS. Document Management System
//    Copyright (C) 2013 Uwe Steinmann
//
//    This program is free software; you can redistribute it and/or modify
//    it under the terms of the GNU General Public License as published by
//    the Free Software Foundation; either version 2 of the License, or
//    (at your option) any later version.
//
//    This program is distributed in the hope that it will be useful,
//    but WITHOUT ANY WARRANTY; without even the implied warranty of
//    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//    GNU General Public License for more details.
//
//    You should have received a copy of the GNU General Public License
//    along with this program; if not, write to the Free Software
//    Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.

include("../inc/inc.Version.php");
include("../inc/inc.Settings.php");
include("../inc/inc.LogInit.php");
include("../inc/inc.Language.php");
include("../inc/inc.Init.php");
include("../inc/inc.Extension.php");
include("../inc/inc.DBInit.php");
include("../inc/inc.Scheduler.php");
include("../inc/inc.ClassUI.php");
include("../inc/inc.Authentication.php");

$tmp = explode('.', basename($_SERVER['SCRIPT_FILENAME']));
$view = UI::factory($theme, $tmp[1], array('dms'=>$dms, 'user'=>$user));
$accessop = new SeedDMS_AccessOperation($dms, $user, $settings);
if (!$accessop->check_view_access($view, $_GET)) {
	UI::exitError(getMLText("admin_tools"),getMLText("access_denied"));
}

if(isset($_GET['task'])) {
	$taskname = $_GET['task'];
} else {
	$taskname = '';
}

if(isset($_GET['extension'])) {
	$extname = $_GET['extension'];
} else {
	$extname = '';
}

if(isset($_GET['taskid'])) {
	$taskid = $_GET['taskid'];
} else {
	$taskid = 0;
}

$scheduler = new SeedDMS_Scheduler($dms->getDB());

if($view) {
	$view->setParam('scheduler', $scheduler);
	$view->setParam('accessobject', $accessop);
	$view->setParam('taskname', $taskname);
	$view->setParam('extname', $extname);
	$view->setParam('taskid', $taskid);
	$view($_GET);
	exit;
}

?>
