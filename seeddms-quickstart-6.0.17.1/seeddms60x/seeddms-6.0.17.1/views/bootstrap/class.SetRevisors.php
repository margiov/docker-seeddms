<?php
/**
 * Implementation of SetRevisors view
 *
 * @category   DMS
 * @package    SeedDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2015 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Include parent class
 */
//require_once("class.Bootstrap.php");

/**
 * Class which outputs the html page for SetRevisors view
 *
 * @category   DMS
 * @package    SeedDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2015 Uwe Steinmann
 * @version    Release: @package_version@
 */
class SeedDMS_View_SetRevisors extends SeedDMS_Theme_Style {

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$folder = $this->params['folder'];
		$document = $this->params['document'];
		$content = $this->params['version'];
		$enableadminrevapp = $this->params['enableadminrevapp'];
		$enableownerrevapp = $this->params['enableownerrevapp'];
		$enableselfrevapp = $this->params['enableselfrevapp'];

		$overallStatus = $content->getStatus();

		$this->htmlStartPage(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))));
		$this->globalNavigation($folder);
		$this->contentStart();
		$this->pageNavigation($this->getFolderPathHTML($folder, true, $document), "view_document", $document);
		$this->contentHeading(getMLText("update_revisors"));

		// Retrieve a list of all users and groups that have review / approve privileges.
		$docAccess = $document->getReadAccessList($enableadminrevapp, $enableownerrevapp);

		// Retrieve list of currently assigned revisors, along with
		// their latest status.
		$revisionStatus = $content->getRevisionStatus();
		$startdate = getReadableDate(makeTsFromDate($content->getRevisionDate()));

		// Index the revision results for easy cross-reference with the revisor list.
		$revisionIndex = array("i"=>array(), "g"=>array());
		foreach ($revisionStatus as $i=>$rs) {
			if ($rs["type"]==0) {
				$revisionIndex["i"][$rs["required"]] = array("status"=>$rs["status"], "idx"=>$i);
			} elseif ($rs["type"]==1) {
				$revisionIndex["g"][$rs["required"]] = array("status"=>$rs["status"], "idx"=>$i);
			}
		}
?>

<form class="form-horizontal" action="../op/op.SetRevisors.php" method="post" name="form1">
	<input type='hidden' name='documentid' value='<?php echo $document->getID() ?>'/>
	<input type='hidden' name='version' value='<?php echo $content->getVersion() ?>'/>

<?php
		$this->contentContainerStart();
		$this->formField(
			getMLText("revision_date"),
			$this->getDateChooser($startdate, "startdate", $this->params['session']->getLanguage())
		);
?>

	<div class="control-group">
		<label class="control-label"><?php printMLText("individuals")?>:</label>
		<div class="controls">
			<select class="chzn-select span9" name="indRevisors[]" multiple="multiple" data-placeholder="<?php printMLText('select_ind_revisors'); ?>" data-no_results_text="<?php printMLText('unknown_owner'); ?>">
<?php

		foreach ($docAccess["users"] as $usr) {
			if (isset($revisionIndex["i"][$usr->getID()])) {

				switch ($revisionIndex["i"][$usr->getID()]["status"]) {
					case S_LOG_WAITING:
					case S_LOG_SLEEPING:
					case S_LOG_ACCEPTED:
					case S_LOG_REJECTED:
						print "<option value='". $usr->getID() ."' selected='selected'>".htmlspecialchars($usr->getLogin() . " - ". $usr->getFullName())."</option>";
						break;
					case S_LOG_USER_REMOVED:
						print "<option value='". $usr->getID() ."'>".htmlspecialchars($usr->getLogin() . " - ". $usr->getFullName())."</option>";
						break;
					default:
						print "<option value='". $usr->getID() ."' disabled='disabled'>".htmlspecialchars($usr->getLogin() . " - ". $usr->getFullName())."</option>";
						break;
				}
			} else {
				if (!$enableselfrevapp && $usr->getID()==$user->getID()) continue; 
				print "<option value='". $usr->getID() ."'>". htmlspecialchars($usr->getLogin() . " - ". $usr->getFullName())."</option>";
			}
		}
?>
			</select>
		</div>
	</div>

	<div class="control-group">
		<label class="control-label"><?php printMLText("individuals_in_groups")?>:</label>
		<div class="controls">
  <select class="chzn-select span9" name="grpIndRevisors[]" multiple="multiple" data-placeholder="<?php printMLText('select_grp_ind_revisors'); ?>" data-no_results_text="<?php printMLText('unknown_group'); ?>">
<?php
		foreach ($docAccess["groups"] as $group) {
			print "<option value='". $group->getID() ."'>".htmlspecialchars($group->getName())."</option>";
		}
?>
			</select>
		</div>
	</div>

	<div class="control-group">
		<label class="control-label"><?php printMLText("groups")?>:</label>
		<div class="controls">
			<select class="chzn-select span9" name="grpRevisors[]" multiple="multiple" data-placeholder="<?php printMLText('select_grp_revisors'); ?>" data-no_results_text="<?php printMLText('unknown_group'); ?>">
<?php
		foreach ($docAccess["groups"] as $group) {
			if (isset($revisionIndex["g"][$group->getID()])) {
				switch ($revisionIndex["g"][$group->getID()]["status"]) {
					case S_LOG_WAITING:
					case S_LOG_SLEEPING:
						print "<option value='". $group->getID() ."' selected='selected'>".htmlspecialchars($group->getName())."</option>";
						break;
					case S_LOG_USER_REMOVED:
						print "<option value='". $group->getID() ."'>".htmlspecialchars($group->getName())."</option>";
						break;
					default:
						print "<option id='recGrp".$group->getID()."' type='checkbox' name='grpRevisors[]' value='". $group->getID() ."' disabled='disabled'>".htmlspecialchars($group->getName())."</option>";
						break;
				}
			} else {
				print "<option value='". $group->getID() ."'>".htmlspecialchars($group->getName())."</option>";
			}
		}
?>
			</select>
		</div>
	</div>

<?php
		$this->contentContainerEnd();
		$this->formSubmit("<i class=\"fa fa-save\"></i> ".getMLText('update'));
?>
</form>
<?php
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
