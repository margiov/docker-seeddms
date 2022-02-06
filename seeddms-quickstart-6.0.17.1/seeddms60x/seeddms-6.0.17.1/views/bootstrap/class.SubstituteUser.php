<?php
/**
 * Implementation of SubstituteUser view
 *
 * @category   DMS
 * @package    SeedDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Include parent class
 */
//require_once("class.Bootstrap.php");

/**
 * Class which outputs the html page for SubstituteUser view
 *
 * @category   DMS
 * @package    SeedDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class SeedDMS_View_SubstituteUser extends SeedDMS_Theme_Style {

	function js() { /* {{{ */
		header('Content-Type: application/javascript; charset=UTF-8');
?>
		$(document).ready(function(){
			$("#myInput").on("keyup", function() {
				var value = $(this).val().toLowerCase();
				$("#myTable tbody tr").filter(function() {
					$(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
				});
			});
		});
<?php
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$allUsers = $this->params['allusers'];

		$this->htmlStartPage(getMLText("substitute_user"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("admin_tools"), "admin_tools");

		$this->contentHeading(getMLText("substitute_user"));
?>
  <input type="text" id="myInput" class="form-control" placeholder="<?= getMLText('type_to_filter'); ?>">
	<table id="myTable" class="table table-condensed table-sm">
		<thead>
		<tr><th><?php printMLText('name'); ?></th><th><?php printMLText('role'); ?>/<?php printMLText('groups'); ?></th><th></th></tr>
		</thead>
		<tbody>
<?php
		foreach ($allUsers as $currUser) {
			echo "<tr".($currUser->isDisabled() ? " class=\"error\"" : "").">";
			echo "<td>";
			$hasemail = $currUser->getEmail() && (preg_match("/.+@.+/", $currUser->getEmail()) == 1);
			if($hasemail)
				echo "<a href=\"mailto:".$currUser->getEmail()."\">";
			echo htmlspecialchars($currUser->getFullName())." (".htmlspecialchars($currUser->getLogin()).")";
			if($hasemail)
				echo "</a>";
			echo "<br />";
			if($hasemail)
				echo "<small>".htmlspecialchars($currUser->getEmail())."</small><br />";
			echo "<small>".htmlspecialchars($currUser->getComment())."</small>";
			echo "</td>";
			echo "<td>";
			echo getMLText('role').": ";
			echo htmlspecialchars($currUser->getRole()->getName());
			echo "<br />";
			$groups = $currUser->getGroups();
			if (count($groups) != 0) {
				for ($j = 0; $j < count($groups); $j++)	{
					print htmlspecialchars($groups[$j]->getName());
					if ($j +1 < count($groups))
						print ", ";
				}
			}
			echo "</td>";
			echo "<td>";
			echo "<td>";
			if($currUser->getID() != $user->getID()) {
				echo "<a class=\"btn btn-primary btn-mini btn-sm text-nowrap\" href=\"../op/op.SubstituteUser.php?userid=".((int) $currUser->getID())."&formtoken=".createFormKey('substituteuser')."\"><i class=\"fa fa-exchange\"></i><span class=\"d-none d-md-inline\"> ".getMLText('substitute_user')."</span></a> ";
			}
			echo "</td>";
			echo "</tr>";
		}
		echo "</tbody>";
		echo "</table>";

		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
