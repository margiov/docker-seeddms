<?php
/**
 * Implementation of Search result view
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
 * Include class to preview documents
 */
require_once("SeedDMS/Preview.php");

/**
 * Class which outputs the html page for Search result view
 *
 * @category   DMS
 * @package    SeedDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class SeedDMS_View_Search extends SeedDMS_Theme_Style {

	/**
	 * Mark search query sting in a given string
	 *
	 * @param string $str mark this text
	 * @param string $tag wrap the marked text with this html tag
	 * @return string marked text
	 */
	function markQuery($str, $tag = "b") { /* {{{ */
		$querywords = preg_split("/ /", $this->query);
		
		foreach ($querywords as $queryword)
			$str = str_ireplace("($queryword)", "<" . $tag . ">\\1</" . $tag . ">", $str);
		
		return $str;
	} /* }}} */

	function js() { /* {{{ */
		header('Content-Type: application/javascript; charset=UTF-8');

		parent::jsTranslations(array('cancel', 'splash_move_document', 'confirm_move_document', 'move_document', 'confirm_transfer_link_document', 'transfer_content', 'link_document', 'splash_move_folder', 'confirm_move_folder', 'move_folder'));

?>
$(document).ready( function() {
	$('#export').on('click', function(e) {
		e.preventDefault();
		window.location.href = $(this).attr('href')+'&includecontent='+($('#includecontent').prop('checked') ? '1' : '0');
	});
});
<?php
//		$this->printFolderChooserJs("form1");
		$this->printDeleteFolderButtonJs();
		$this->printDeleteDocumentButtonJs();
		/* Add js for catching click on document in one page mode */
		$this->printClickDocumentJs();
		$this->printClickFolderJs();
?>
$(document).ready(function() {
	$('body').on('submit', '#form1', function(ev){
	});
});
<?php
	} /* }}} */

	function export() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$entries = $this->params['searchhits'];
		$includecontent = $this->params['includecontent'];

		include("../inc/inc.ClassDownloadMgr.php");
		$downmgr = new SeedDMS_Download_Mgr();
		if($extraheader = $this->callHook('extraDownloadHeader'))
			$downmgr->addHeader($extraheader);
		foreach($entries as $entry) {
			if($entry->isType('document')) {
				$extracols = $this->callHook('extraDownloadColumns', $entry);
				if($includecontent && $rawcontent = $this->callHook('rawcontent', $entry->getLatestContent())) {
					$downmgr->addItem($entry->getLatestContent(), $extracols, $rawcontent);
				} else
					$downmgr->addItem($entry->getLatestContent(), $extracols);
			}
		}
		$filename = tempnam(sys_get_temp_dir(), '');
		if($includecontent) {
			$downmgr->createArchive($filename);
			header("Content-Transfer-Encoding: binary");
			header("Content-Length: " . filesize($filename));
			header("Content-Disposition: attachment; filename=\"export-" .date('Y-m-d') . ".zip\"");
			header("Content-Type: application/zip");
			header("Cache-Control: must-revalidate");
		} else {
			$downmgr->createToc($filename);
			header("Content-Transfer-Encoding: binary");
			header("Content-Length: " . filesize($filename));
			header("Content-Disposition: attachment; filename=\"export-" .date('Y-m-d') . ".xlsx\"");
			header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
			header("Cache-Control: must-revalidate");
		}

		readfile($filename);
		unlink($filename);
	} /* }}} */

	function opensearchsuggestion() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$query = $this->params['query'];
		$entries = $this->params['searchhits'];
		$recs = array();
		$content = "<?xml version=\"1.0\"?>\n";
		$content .= "<SearchSuggestion version=\"2.0\" xmlns=\"http://opensearch.org/searchsuggest2\">\n";
		$content .= "<Query xml:space=\"preserve\">".$query."</Query>";
		if($entries) {
			$content .= "<Section>\n";
			foreach ($entries as $entry) {
				$content .= "<Item>\n";
				if($entry->isType('document')) {
					$content .= "<Text xml:space=\"preserve\">".$entry->getName()."</Text>\n";
					$content .= "<Url xml:space=\"preserve\">http:".((isset($_SERVER['HTTPS']) && (strcmp($_SERVER['HTTPS'],'off')!=0)) ? "s" : "")."://".$_SERVER['HTTP_HOST'].$settings->_httpRoot."out/out.ViewDocument.php?documentid=".$entry->getId()."</Url>\n";
				} elseif($entry->isType('folder')) {
					$content .= "<Text xml:space=\"preserve\">".$entry->getName()."</Text>\n";
					$content .= "<Url xml:space=\"preserve\">http:".((isset($_SERVER['HTTPS']) && (strcmp($_SERVER['HTTPS'],'off')!=0)) ? "s" : "")."://".$_SERVER['HTTP_HOST'].$settings->_httpRoot."out/out.ViewFolder.php?folderid=".$entry->getId()."</Url>\n";
				}
				$content .= "</Item>\n";
			}
			$content .= "</Section>\n";
		}
		$content .= "</SearchSuggestion>";
		header("Content-Disposition: attachment; filename=\"search.xml\"; filename*=UTF-8''search.xml");
		header('Content-Type: application/x-suggestions+xml');
		echo $content;
	} /* }}} */

function typeahead() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$query = $this->params['query'];
		$entries = $this->params['searchhits'];
		$recs = array();
		if($entries) {
			foreach ($entries as $entry) {
				if($entry->isType('document')) {
//					$recs[] = 'D'.$entry->getName();
					$recs[] = array('type'=>'D', 'id'=>$entry->getId(), 'name'=>$entry->getName());
				} elseif($entry->isType('folder')) {
//					$recs[] = 'F'.$entry->getName();
					$recs[] = array('type'=>'F', 'id'=>$entry->getId(), 'name'=>$entry->getName());
				}
			}
		}
		array_unshift($recs, array('type'=>'S', 'name'=>$query));
		header('Content-Type: application/json');
		echo json_encode($recs);
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$fullsearch = $this->params['fullsearch'];
		$total = $this->params['total'];
		$totaldocs = $this->params['totaldocs'];
		$totalfolders = $this->params['totalfolders'];
		$limit = $this->params['limit'];
		$attrdefs = $this->params['attrdefs'];
		$allCats = $this->params['allcategories'];
		$allUsers = $this->params['allusers'];
		$mode = $this->params['mode'];
		$resultmode = $this->params['resultmode'];
		$workflowmode = $this->params['workflowmode'];
		$enablefullsearch = $this->params['enablefullsearch'];
		$enableclipboard = $this->params['enableclipboard'];
		$attributes = $this->params['attributes'];
		$categories = $this->params['categories'];
		$category = $this->params['category'];
		$mimetype = $this->params['mimetype'];
		$owner = $this->params['owner'];
		$startfolder = $this->params['startfolder'];
		$createstartdate = $this->params['createstartdate'];
		$createenddate = $this->params['createenddate'];
		$expstartdate = $this->params['expstartdate'];
		$expenddate = $this->params['expenddate'];
		$statusstartdate = $this->params['statusstartdate'];
		$statusenddate = $this->params['statusenddate'];
		$revisionstartdate = $this->params['revisionstartdate'];
		$revisionenddate = $this->params['revisionenddate'];
		$creationdate = $this->params['creationdate'];
		$expirationdate = $this->params['expirationdate'];
		$statusdate = $this->params['statusdate'];
		$revisiondate = $this->params['revisiondate'];
		$status = $this->params['status'];
		$record_type = $this->params['recordtype'];
		$this->query = $this->params['query'];
		$orderby = $this->params['orderby'];
		$entries = $this->params['searchhits'];
		$facets = $this->params['facets'];
		$totalpages = $this->params['totalpages'];
		$pageNumber = $this->params['pagenumber'];
		$searchTime = $this->params['searchtime'];
		$urlparams = $this->params['urlparams'];
		$searchin = $this->params['searchin'];
		$cachedir = $this->params['cachedir'];
		$previewwidth = $this->params['previewWidthList'];
		$previewconverters = $this->params['previewConverters'];
		$timeout = $this->params['timeout'];
		$xsendfile = $this->params['xsendfile'];
		$reception = $this->params['reception'];
		$showsinglesearchhit = $this->params['showsinglesearchhit'];

		if($showsinglesearchhit && count($entries) == 1) {
			$entry = $entries[0];
			if($entry->isType('document')) {
				header('Location: ../out/out.ViewDocument.php?documentid='.$entry->getID());
				exit;
			} elseif($entry->isType('folder')) {
				header('Location: ../out/out.ViewFolder.php?folderid='.$entry->getID());
				exit;
			}
		}

//		if ($pageNumber != 'all')
//			$entries = array_slice($entries, ($pageNumber-1)*$limit, $limit);

		$this->htmlStartPage(getMLText("search_results"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation("", "");

		$this->rowStart();
		$this->columnStart(4);
		//$this->contentHeading("<button class=\"btn btn-primary\" id=\"searchform-toggle\" data-toggle=\"collapse\" href=\"#searchform\"><i class=\"fa fa-exchange\"></i></button> ".getMLText('search'), true);
		$this->contentHeading(getMLText('search'), true);
		if($this->query) {
		echo "<div id=\"searchform\" class=\"_collapse mb-sm-4\">";
		}
?>
  <ul class="nav nav-pills" id="searchtab">
	  <li class="nav-item <?php echo ($fullsearch == false) ? 'active' : ''; ?>"><a class="nav-link <?php echo ($fullsearch == false) ? 'active' : ''; ?>" data-target="#database" data-toggle="tab"><?php printMLText('databasesearch'); ?></a></li>
<?php
		if($enablefullsearch) {
?>
	  <li class="nav-item <?php echo ($fullsearch == true) ? 'active' : ''; ?>"><a class="nav-link <?php echo ($fullsearch == true) ? 'active' : ''; ?>" data-target="#fulltext" data-toggle="tab"><?php printMLText('fullsearch'); ?></a></li>
<?php
		}
?>
	</ul>
	<div class="tab-content">
	  <div class="tab-pane <?php echo ($fullsearch == false) ? 'active' : ''; ?>" id="database">
		<form class="form-horizontal" action="<?= $this->params['settings']->_httpRoot ?>out/out.Search.php" name="form1">
<input type="hidden" name="fullsearch" value="0" />
<?php
// Database search Form {{{
		$this->contentContainerStart();

		$this->formField(
			getMLText("search_query"),
			array(
				'element'=>'input',
				'type'=>'text',
				'name'=>'query',
				'value'=>htmlspecialchars($this->query)
			)
		);
		$options = array();
		$options[] = array('1', getMLText('search_mode_and'), $mode=='AND');
		$options[] = array('0', getMLText('search_mode_or'), $mode=='OR');
		$this->formField(
			getMLText("search_mode"),
			array(
				'element'=>'select',
				'name'=>'mode',
				'multiple'=>false,
				'options'=>$options
			)
		);
		$options = array();
		$options[] = array('1', getMLText('keywords').' ('.getMLText('documents_only').')', in_array('1', $searchin));
		$options[] = array('2', getMLText('name'), in_array('2', $searchin));
		$options[] = array('3', getMLText('comment'), in_array('3', $searchin));
		$options[] = array('4', getMLText('attributes'), in_array('4', $searchin));
		$options[] = array('5', getMLText('id'), in_array('5', $searchin));
		$this->formField(
			getMLText("search_in"),
			array(
				'element'=>'select',
				'name'=>'searchin[]',
				'class'=>'chzn-select',
				'multiple'=>true,
				'options'=>$options
			)
		);
		$options = array();
		$options[] = array('', getMLText('orderby_unsorted'));
		$options[] = array('dd', getMLText('orderby_date_desc'), 'dd'==$orderby);
		$options[] = array('d', getMLText('orderby_date_asc'), 'd'==$orderby);
		$options[] = array('nd', getMLText('orderby_name_desc'), 'nd'==$orderby);
		$options[] = array('n', getMLText('orderby_name_asc'), 'n'==$orderby);
		$options[] = array('id', getMLText('orderby_id_desc'), 'id'==$orderby);
		$options[] = array('i', getMLText('orderby_id_asc'), 'i'==$orderby);
		$this->formField(
			getMLText("orderby"),
			array(
				'element'=>'select',
				'name'=>'orderby',
				'class'=>'chzn-select',
				'multiple'=>false,
				'options'=>$options
			)
		);
		$options = array();
		foreach ($allUsers as $currUser) {
			if($user->isAdmin() || (!$currUser->isGuest() && (!$currUser->isHidden() || $currUser->getID() == $user->getID())))
				$options[] = array($currUser->getID(), htmlspecialchars($currUser->getLogin()), in_array($currUser->getID(), $owner), array(array('data-subtitle', htmlspecialchars($currUser->getFullName()))));
		}
		$this->formField(
			getMLText("owner"),
			array(
				'element'=>'select',
				'name'=>'owner[]',
				'class'=>'chzn-select',
				'multiple'=>true,
				'options'=>$options
			)
		);
		$options = array();
		$options[] = array('1', getMLText('search_mode_documents'), $resultmode==1);
		$options[] = array('2', getMLText('search_mode_folders'), $resultmode==2);
		$options[] = array('3', getMLText('search_resultmode_both'), $resultmode==3);
		$this->formField(
			getMLText("search_resultmode"),
			array(
				'element'=>'select',
				'name'=>'resultmode',
				'multiple'=>false,
				'options'=>$options
			)
		);
		$this->formField(getMLText("under_folder"), $this->getFolderChooserHtml("form1", M_READ, -1, $startfolder));
		$this->formField(
			getMLText("creation_date")." (".getMLText('from').")",
			$this->getDateChooser($createstartdate, "createstart", $this->params['session']->getLanguage())
		);
		$this->formField(
			getMLText("creation_date")." (".getMLText('to').")",
			$this->getDateChooser($createenddate, "createend", $this->params['session']->getLanguage())
		);
		if($attrdefs) {
			foreach($attrdefs as $attrdef) {
				if($attrdef->getObjType() == SeedDMS_Core_AttributeDefinition::objtype_all) {
					if($attrdef->getType() == SeedDMS_Core_AttributeDefinition::type_date) {
						$this->formField(htmlspecialchars($attrdef->getName().' ('.getMLText('from').')'), $this->getAttributeEditField($attrdef, !empty($attributes[$attrdef->getID()]['from']) ? getReadableDate(makeTsFromDate($attributes[$attrdef->getID()]['from'])) : '', 'attributes', true, 'from'));
						$this->formField(htmlspecialchars($attrdef->getName().' ('.getMLText('to').')'), $this->getAttributeEditField($attrdef, !empty($attributes[$attrdef->getID()]['to']) ? getReadableDate(makeTsFromDate($attributes[$attrdef->getID()]['to'])) : '', 'attributes', true, 'to'));
					} else
						$this->formField(htmlspecialchars($attrdef->getName()), $this->getAttributeEditField($attrdef, isset($attributes[$attrdef->getID()]) ? $attributes[$attrdef->getID()] : '', 'attributes', true));
				}
			}
		}
		$this->contentContainerEnd();
		// }}}

		// Seach options for documents {{{
		/* First check if any of the folder filters are set. If it is,
		 * open the accordion.
		 */
		$openfilterdlg = false;
		if($attrdefs) {
			foreach($attrdefs as $attrdef) {
				if($attrdef->getObjType() == SeedDMS_Core_AttributeDefinition::objtype_document || $attrdef->getObjType() == SeedDMS_Core_AttributeDefinition::objtype_documentcontent) {
					if(!empty($attributes[$attrdef->getID()]))
						$openfilterdlg = true;
				}
			}
		}
		if($categories)
			$openfilterdlg = true;
		if($status)
			$openfilterdlg = true;
		if($expirationdate)
			$openfilterdlg = true;
		if($revisiondate)
			$openfilterdlg = true;
		if($reception)
			$openfilterdlg = true;
		if($statusdate)
			$openfilterdlg = true;

		if($totaldocs) {
			ob_start();
			$this->formField(
				getMLText("include_content"),
				array(
					'element'=>'input',
					'type'=>'checkbox',
					'name'=>'includecontent',
					'id'=>'includecontent',
					'value'=>1,
				)
			);
			//$this->formSubmit("<i class=\"fa fa-download\"></i> ".getMLText('export'));
			print $this->html_link('Search', array_merge($_GET, array('action'=>'export')), array('class'=>'btn btn-primary', 'id'=>'export'), "<i class=\"fa fa-download\"></i> ".getMLText("export"), false, true)."\n";
			$content = ob_get_clean();
			$this->printAccordion(getMLText('export'), $content);
		}

		/* Start of fields only applicable to documents */
		ob_start();
		$tmpcatids = array();
		foreach($categories as $tmpcat)
			$tmpcatids[] = $tmpcat->getID();
		$options = array();
		$allcategories = $dms->getDocumentCategories();
		foreach($allcategories as $acategory) {
			$options[] = array($acategory->getID(), $acategory->getName(), in_array($acategory->getId(), $tmpcatids));
		}
		$this->formField(
			getMLText("categories"),
			array(
				'element'=>'select',
				'class'=>'chzn-select',
				'name'=>'category[]',
				'multiple'=>true,
				'attributes'=>array(array('data-placeholder', getMLText('select_category'), array('data-no_results_text', getMLText('unknown_document_category')))),
				'options'=>$options
			)
		);
		$options = array();
		if($workflowmode == 'traditional' || $workflowmode == 'traditional_only_approval') {
			if($workflowmode == 'traditional') { 
				$options[] = array(S_DRAFT_REV, getOverallStatusText(S_DRAFT_REV), in_array(S_DRAFT_REV, $status));
			}
		} elseif($workflowmode == 'advanced') {
			$options[] = array(S_IN_WORKFLOW, getOverallStatusText(S_IN_WORKFLOW), in_array(S_IN_WORKFLOW, $status));
		}
		$options[] = array(S_DRAFT_APP, getOverallStatusText(S_DRAFT_APP), in_array(S_DRAFT_APP, $status));
		$options[] = array(S_RELEASED, getOverallStatusText(S_RELEASED), in_array(S_RELEASED, $status));
		$options[] = array(S_REJECTED, getOverallStatusText(S_REJECTED), in_array(S_REJECTED, $status));
		$options[] = array(S_IN_REVISION, getOverallStatusText(S_IN_REVISION), in_array(S_IN_REVISION, $status));
		$options[] = array(S_EXPIRED, getOverallStatusText(S_EXPIRED), in_array(S_EXPIRED, $status));
		$options[] = array(S_OBSOLETE, getOverallStatusText(S_OBSOLETE), in_array(S_OBSOLETE, $status));
		$options[] = array(S_NEEDS_CORRECTION, getOverallStatusText(S_NEEDS_CORRECTION), in_array(S_NEEDS_CORRECTION, $status));
		$this->formField(
			getMLText("status"),
			array(
				'element'=>'select',
				'class'=>'chzn-select',
				'name'=>'status[]',
				'multiple'=>true,
				'attributes'=>array(array('data-placeholder', getMLText('select_status')), array('data-no_results_text', getMLText('unknown_status'))),
				'options'=>$options
			)
		);
		$this->formField(
			getMLText("expires")." (".getMLText('from').")",
			$this->getDateChooser($expstartdate, "expirationstart", $this->params['session']->getLanguage())
		);
		$this->formField(
			getMLText("expires")." (".getMLText('to').")",
			$this->getDateChooser($expenddate, "expirationend", $this->params['session']->getLanguage())
		);
		$this->formField(
			getMLText("revision")." (".getMLText('from').")",
			$this->getDateChooser($revisionstartdate, "revisiondatestart", $this->params['session']->getLanguage())
		);
		$this->formField(
			getMLText("revision")." (".getMLText('to').")",
			$this->getDateChooser($revisionenddate, "revisiondateend", $this->params['session']->getLanguage())
		);
		$this->formField(
			getMLText("status_change")." (".getMLText('from').")",
			$this->getDateChooser($statusstartdate, "statusdatestart", $this->params['session']->getLanguage())
		);
		$this->formField(
			getMLText("status_change")." (".getMLText('to').")",
			$this->getDateChooser($statusenddate, "statusdateend", $this->params['session']->getLanguage())
		);
		if($attrdefs) {
			foreach($attrdefs as $attrdef) {
				if($attrdef->getObjType() == SeedDMS_Core_AttributeDefinition::objtype_document || $attrdef->getObjType() == SeedDMS_Core_AttributeDefinition::objtype_documentcontent) {
					if($attrdef->getType() == SeedDMS_Core_AttributeDefinition::type_date) {
						$this->formField(htmlspecialchars($attrdef->getName().' ('.getMLText('from').')'), $this->getAttributeEditField($attrdef, !empty($attributes[$attrdef->getID()]['from']) ? getReadableDate(makeTsFromDate($attributes[$attrdef->getID()]['from'])) : '', 'attributes', true, 'from'));
						$this->formField(htmlspecialchars($attrdef->getName().' ('.getMLText('to').')'), $this->getAttributeEditField($attrdef, !empty($attributes[$attrdef->getID()]['to']) ? getReadableDate(makeTsFromDate($attributes[$attrdef->getID()]['to'])) : '', 'attributes', true, 'to'));
					} else
						$this->formField(htmlspecialchars($attrdef->getName()), $this->getAttributeEditField($attrdef, isset($attributes[$attrdef->getID()]) ? $attributes[$attrdef->getID()] : '', 'attributes', true));
				}
			}
		}
		// }}}

		// Seach options for folders {{{
		$content = ob_get_clean();
		$this->printAccordion(getMLText('filter_for_documents'), $content);
		/* First check if any of the folder filters are set. If it is,
		 * open the accordion.
		 */
		$openfilterdlg = false;
		if($attrdefs) {
			foreach($attrdefs as $attrdef) {
				if($attrdef->getObjType() == SeedDMS_Core_AttributeDefinition::objtype_folder) {
					if(!empty($attributes[$attrdef->getID()]))
						$openfilterdlg = true;
				}
			}
		}
		ob_start();
		if($attrdefs) {
			foreach($attrdefs as $attrdef) {
				if($attrdef->getObjType() == SeedDMS_Core_AttributeDefinition::objtype_folder) {
					if($attrdef->getType() == SeedDMS_Core_AttributeDefinition::type_date) {
						$this->formField(htmlspecialchars($attrdef->getName().' ('.getMLText('from').')'), $this->getAttributeEditField($attrdef, !empty($attributes[$attrdef->getID()]['from']) ? getReadableDate(makeTsFromDate($attributes[$attrdef->getID()]['from'])) : '', 'attributes', true, 'from'));
						$this->formField(htmlspecialchars($attrdef->getName().' ('.getMLText('to').')'), $this->getAttributeEditField($attrdef, !empty($attributes[$attrdef->getID()]['to']) ? getReadableDate(makeTsFromDate($attributes[$attrdef->getID()]['to'])) : '', 'attributes', true, 'to'));
					} else
						$this->formField(htmlspecialchars($attrdef->getName()), $this->getAttributeEditField($attrdef, isset($attributes[$attrdef->getID()]) ? $attributes[$attrdef->getID()] : '', 'attributes', true));
				}
			}
		}
		$content = ob_get_clean();
		if($content)
			$this->printAccordion(getMLText('filter_for_folders'), $content);
		// }}}

		$this->formSubmit("<i class=\"fa fa-search\"></i> ".getMLText('search'));
?>
</form>
		</div>
<?php
		// }}}
		// }}}

		// Fulltext search Form {{{
		if($enablefullsearch) {
	  	echo "<div class=\"tab-pane ".(($fullsearch == true) ? 'active' : '')."\" id=\"fulltext\">\n";
?>
<form class="form-horizontal" action="<?= $this->params['settings']->_httpRoot ?>out/out.Search.php" name="form2" style="min-height: 330px;">
<input type="hidden" name="fullsearch" value="1" />
<?php
			$this->contentContainerStart();
			$this->formField(
				getMLText("search_query"),
				array(
					'element'=>'input',
					'type'=>'text',
					'name'=>'query',
					'value'=>htmlspecialchars($this->query)
				)
			);
			$this->formField(getMLText("under_folder"), $this->getFolderChooserHtml("form1", M_READ, -1, $startfolder, 'folderfullsearchid'));
			if(!isset($facets['owner'])) {
				$options = array();
				foreach ($allUsers as $currUser) {
					if($user->isAdmin() || (!$currUser->isGuest() && (!$currUser->isHidden() || $currUser->getID() == $user->getID())))
						$options[] = array($currUser->getID(), htmlspecialchars($currUser->getLogin()), in_array($currUser->getID(), $owner), array(array('data-subtitle', htmlspecialchars($currUser->getFullName()))));
				}
				$this->formField(
					getMLText("owner"),
					array(
						'element'=>'select',
						'name'=>'owner[]',
						'class'=>'chzn-select',
						'multiple'=>true,
						'options'=>$options
					)
				);
			}
			if(!isset($facets['category'])) {
				$tmpcatids = array();
				foreach($categories as $tmpcat)
					$tmpcatids[] = $tmpcat->getID();
				$options = array();
				$allcategories = $dms->getDocumentCategories();
				foreach($allcategories as $acategory) {
					$options[] = array($acategory->getID(), $acategory->getName(), in_array($acategory->getId(), $tmpcatids));
				}
				$this->formField(
					getMLText("category_filter"),
					array(
						'element'=>'select',
						'class'=>'chzn-select',
						'name'=>'category[]',
						'multiple'=>true,
						'attributes'=>array(array('data-placeholder', getMLText('select_category'), array('data-no_results_text', getMLText('unknown_document_category')))),
						'options'=>$options
					)
				);
			}
			if(!isset($facets['status'])) {
				$options = array();
				if($workflowmode == 'traditional' || $workflowmode == 'traditional_only_approval') {
					if($workflowmode == 'traditional') { 
						$options[] = array(S_DRAFT_REV, getOverallStatusText(S_DRAFT_REV), in_array(S_DRAFT_REV, $status));
					}
				} elseif($workflowmode == 'advanced') {
					$options[] = array(S_IN_WORKFLOW, getOverallStatusText(S_IN_WORKFLOW), in_array(S_IN_WORKFLOW, $status));
				}
				$options[] = array(S_DRAFT_APP, getOverallStatusText(S_DRAFT_APP), in_array(S_DRAFT_APP, $status));
				$options[] = array(S_RELEASED, getOverallStatusText(S_RELEASED), in_array(S_RELEASED, $status));
				$options[] = array(S_REJECTED, getOverallStatusText(S_REJECTED), in_array(S_REJECTED, $status));
				$options[] = array(S_EXPIRED, getOverallStatusText(S_EXPIRED), in_array(S_EXPIRED, $status));
				$options[] = array(S_OBSOLETE, getOverallStatusText(S_OBSOLETE), in_array(S_OBSOLETE, $status));
				$this->formField(
					getMLText("status"),
					array(
						'element'=>'select',
						'class'=>'chzn-select',
						'name'=>'status[]',
						'multiple'=>true,
						'attributes'=>array(array('data-placeholder', getMLText('select_status')), array('data-no_results_text', getMLText('unknown_status'))),
						'options'=>$options
					)
				);
			}

			if($facets) {
				foreach($facets as $facetname=>$values) {
					$multiple = true;
//					if(in_array($facetname, ['owner', 'status', 'mimetype']))
//						$multiple = false;
					$options = array();
					if($facetname == 'owner') {
						foreach($values as $v=>$c) {
							$uu = $dms->getUserByLogin($v);
							if($uu) {
								$option = array($uu->getId(), $v.' ('.$c.')');
								if(isset(${$facetname}) && in_array($uu->getId(), ${$facetname}))
									$option[] = true;
								$options[] = $option;
							}
						}
					} elseif($facetname == 'category') {
						foreach($values as $v=>$c) {
							$cat = $dms->getDocumentCategoryByName($v);
							if($cat) {
								$option = array($cat->getId(), $v.' ('.$c.')');
								if(isset(${$facetname}) && in_array($cat->getId(), ${$facetname}))
									$option[] = true;
								$options[] = $option;
							}
						}
					} elseif($facetname == 'status') {
						foreach($values as $v=>$c) {
								$option = array($v, getOverallStatusText($v).' ('.$c.')');
								if(isset(${$facetname}) && in_array($v, ${$facetname}))
									$option[] = true;
								$options[] = $option;
						}
					} else {
						foreach($values as $v=>$c) {
							$option = array($v, $v.' ('.$c.')');
							if(isset(${$facetname}) && in_array($v, ${$facetname}))
								$option[] = true;
							$options[] = $option;
						}
					}
					$this->formField(
						getMLText($facetname),
						array(
							'element'=>'select',
							'id'=>$facetname,
							'name'=>$facetname."[]",
							'class'=>'chzn-select',
							'attributes'=>array(array('data-placeholder', getMLText('select_'.$facetname)), array('data-allow-clear', 'true')),
							'options'=>$options,
							'multiple'=>$multiple
						)
					);
				}
			}
			$this->contentContainerEnd();
			$this->formSubmit("<i class=\"fa fa-search\"></i> ".getMLText('search'));
?>
</form>
<?php
			echo "</div>\n";
		}
		// }}}
?>
	</div>
<?php
		if($this->query) {
			echo "</div>\n";
		}
		$this->columnEnd();
		$this->columnStart(8);
		$this->contentHeading(getMLText('search_results'));
// Search Result {{{
		$foldercount = $doccount = 0;
		if($entries) {
			/*
			foreach ($entries as $entry) {
				if($entry->isType('document')) {
					$doccount++;
				} elseif($entry->isType('document')) {
					$foldercount++;
				}
			}
			 */
			echo $this->infoMsg(getMLText("search_report", array("count"=>$total, "doccount" => $totaldocs, "foldercount" => $totalfolders, 'searchtime'=>$searchTime)));
			$this->pageList($pageNumber, $totalpages, "../out/out.Search.php", $urlparams);
//			$this->contentContainerStart();

			$txt = $this->callHook('searchListHeader', $orderby, 'asc');
			if(is_string($txt))
				echo $txt;
			else {
				parse_str($_SERVER['QUERY_STRING'], $tmp);
				$tmp['orderby'] = ($orderby=="n"||$orderby=="na") ? "nd" : "n";
				print "<table class=\"table table-condensed table-sm table-hover\">";
				print "<thead>\n<tr>\n";
				print "<th></th>\n";
				print "<th>".getMLText("name");
				if(!$fullsearch) {
					print $orderby." <a href=\"../out/out.Search.php?".http_build_query($tmp)."\" title=\"".getMLText("sort_by_name")."\">".($orderby=="n"||$orderby=="na"?' <i class="fa fa-sort-alpha-asc selected"></i>':($orderby=="nd"?' <i class="fa fa-sort-alpha-desc selected"></i>':' <i class="fa fa-sort-alpha-asc"></i>'))."</a>";
					$tmp['orderby'] = ($orderby=="d"||$orderby=="da") ? "dd" : "d";
					print " <a href=\"../out/out.Search.php?".http_build_query($tmp)."\" title=\"".getMLText("sort_by_date")."\">".($orderby=="d"||$orderby=="da"?' <i class="fa fa-sort-amount-asc selected"></i>':($orderby=="dd"?' <i class="fa fa-sort-amount-desc selected"></i>':' <i class="fa fa-sort-amount-asc"></i>'))."</a>";
				}
				print "</th>\n";
				//print "<th>".getMLText("attributes")."</th>\n";
				print "<th>".getMLText("status")."</th>\n";
				print "<th>".getMLText("action")."</th>\n";
				print "</tr>\n</thead>\n<tbody>\n";
			}

			$previewer = new SeedDMS_Preview_Previewer($cachedir, $previewwidth, $timeout, $xsendfile);
			$previewer->setConverters($previewconverters);
			foreach ($entries as $entry) {
				if($entry->isType('document')) {
					$txt = $this->callHook('documentListItem', $entry, $previewer, false, 'search');
					if(is_string($txt))
						echo $txt;
					else {
						$document = $entry;
						$owner = $document->getOwner();
						if($lc = $document->getLatestContent())
							$previewer->createPreview($lc);

						if (in_array(3, $searchin))
							$comment = $this->markQuery(htmlspecialchars($document->getComment()));
						else
							$comment = htmlspecialchars($document->getComment());
						if (strlen($comment) > 150) $comment = substr($comment, 0, 147) . "...";

						$lcattributes = $lc ? $lc->getAttributes() : null;
						$attrstr = '';
						if($lcattributes) {
							$attrstr .= "<table class=\"table table-condensed table-sm\">\n";
							$attrstr .= "<tr><th>".getMLText('name')."</th><th>".getMLText('attribute_value')."</th></tr>";
							foreach($lcattributes as $lcattribute) {
								$arr = $this->callHook('showDocumentContentAttribute', $lc, $lcattribute);
								if(is_array($arr)) {
									$attrstr .= "<tr>";
									$attrstr .= "<td>".$arr[0].":</td>";
									$attrstr .= "<td>".$arr[1]."</td>";
									$attrstr .= "</tr>";
								} elseif(is_string($arr)) {
									$attrstr .= $arr;
								} else {
									$attrdef = $lcattribute->getAttributeDefinition();
									$attrstr .= "<tr><td>".htmlspecialchars($attrdef->getName())."</td><td>".htmlspecialchars(implode(', ', $lcattribute->getValueAsArray()))."</td></tr>\n";
									// TODO: better use printAttribute()
									// $this->printAttribute($lcattribute);
								}
							}
							$attrstr .= "</table>\n";
						}
						$docattributes = $document->getAttributes();
						if($docattributes) {
							$attrstr .= "<table class=\"table table-condensed table-sm\">\n";
							$attrstr .= "<tr><th>".getMLText('name')."</th><th>".getMLText('attribute_value')."</th></tr>";
							foreach($docattributes as $docattribute) {
								$arr = $this->callHook('showDocumentAttribute', $document, $docattribute);
								if(is_array($arr)) {
									$attrstr .= "<tr>";
									$attrstr .= "<td>".$arr[0].":</td>";
									$attrstr .= "<td>".$arr[1]."</td>";
									$attrstr .= "</tr>";
								} elseif(is_string($arr)) {
									$attrstr .= $arr;
								} else {
									$attrdef = $docattribute->getAttributeDefinition();
									$attrstr .= "<tr><td>".htmlspecialchars($attrdef->getName())."</td><td>".htmlspecialchars(implode(', ', $docattribute->getValueAsArray()))."</td></tr>\n";
								}
							}
							$attrstr .= "</table>\n";
						}
						$extracontent = array();
						$extracontent['below_title'] = $this->getListRowPath($document);
						if($attrstr)
							$extracontent['bottom_title'] = '<br />'.$this->printPopupBox('<span class="btn btn-mini btn-sm btn-secondary">'.getMLText('attributes').'</span>', $attrstr, true);
						print $this->documentListRow($document, $previewer, false, 0, $extracontent);
					}
				} elseif($entry->isType('folder')) {
					$txt = $this->callHook('folderListItem', $entry, false, 'search');
					if(is_string($txt))
						echo $txt;
					else {
					$folder = $entry;
					$owner = $folder->getOwner();
					if (in_array(2, $searchin)) {
						$folderName = $this->markQuery(htmlspecialchars($folder->getName()), "i");
					} else {
						$folderName = htmlspecialchars($folder->getName());
					}

					$attrstr = '';
					$folderattributes = $folder->getAttributes();
					if($folderattributes) {
						$attrstr .= "<table class=\"table table-condensed table-sm\">\n";
						$attrstr .= "<tr><th>".getMLText('name')."</th><th>".getMLText('attribute_value')."</th></tr>";
						foreach($folderattributes as $folderattribute) {
							$attrdef = $folderattribute->getAttributeDefinition();
							$attrstr .= "<tr><td>".htmlspecialchars($attrdef->getName())."</td><td>".htmlspecialchars(implode(', ', $folderattribute->getValueAsArray()))."</td></tr>\n";
						}
						$attrstr .= "</table>";
					}
					$extracontent = array();
					$extracontent['below_title'] = $this->getListRowPath($folder);
					if($attrstr)
						$extracontent['bottom_title'] = '<br />'.$this->printPopupBox('<span class="btn btn-mini btn-sm btn-secondary">'.getMLText('attributes').'</span>', $attrstr, true);
					print $this->folderListRow($folder, false, $extracontent);
					}
				}
			}
			print "</tbody></table>\n";
//			$this->contentContainerEnd();
			$this->pageList($pageNumber, $totalpages, "../out/out.Search.php", $_GET);
		} else {
			$numResults = $totaldocs + $totalfolders;
			if ($numResults == 0) {
				echo $this->warningMsg(getMLText("search_no_results"));
			}
		}
// }}}
		$this->columnEnd();
		$this->rowEnd();
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
