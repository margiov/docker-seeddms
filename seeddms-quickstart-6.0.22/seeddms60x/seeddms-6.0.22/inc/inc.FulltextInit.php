<?php

$fulltextservice = null;
if($settings->_enableFullSearch) {
	require_once("inc.ClassFulltextService.php");
	$fulltextservice = new SeedDMS_FulltextService();
	$fulltextservice->setLogger($logger);

	if($settings->_fullSearchEngine == 'sqlitefts') {
		$indexconf = array(
			'Indexer' => 'SeedDMS_SQLiteFTS_Indexer',
			'Search' => 'SeedDMS_SQLiteFTS_Search',
			'IndexedDocument' => 'SeedDMS_SQLiteFTS_IndexedDocument',
			'Conf' => array('indexdir' => $settings->_luceneDir)
		);
		$fulltextservice->addService('sqlitefts', $indexconf);

		require_once('vendor/seeddms/sqlitefts/SQLiteFTS.php');
	} elseif($settings->_fullSearchEngine == 'lucene') {
		$indexconf = array(
			'Indexer' => 'SeedDMS_Lucene_Indexer',
			'Search' => 'SeedDMS_Lucene_Search',
			'IndexedDocument' => 'SeedDMS_Lucene_IndexedDocument',
			'Conf' => array('indexdir' => $settings->_luceneDir)
		);
		$fulltextservice->addService('lucene', $indexconf);

		if(!empty($settings->_luceneClassDir))
			require_once($settings->_luceneClassDir.'/Lucene.php');
		else
			require_once('vendor/seeddms/lucene/Lucene.php');
	} else {
		$indexconf = null;
		if(isset($GLOBALS['SEEDDMS_HOOKS']['initFulltext'])) {
			foreach($GLOBALS['SEEDDMS_HOOKS']['initFulltext'] as $hookObj) {
				if (method_exists($hookObj, 'isFulltextService') && $hookObj->isFulltextService($settings->_fullSearchEngine)) {
					if (method_exists($hookObj, 'initFulltextService')) {
						$indexconf = $hookObj->initFulltextService(array('engine'=>$settings->_fullSearchEngine, 'dms'=>$dms, 'settings'=>$settings));
					}
				}
			}
		}
		if($indexconf) {
			$fulltextservice->addService($settings->_fullSearchEngine, $indexconf);
		}
	}
	/* setConverters() is deprecated */
	$fulltextservice->setConverters(isset($settings->_converters['fulltext']) ? $settings->_converters['fulltext'] : null);
	$fulltextservice->setConversionMgr($conversionmgr);
	$fulltextservice->setMaxSize($settings->_maxSizeForFullText);
	$fulltextservice->setCmdTimeout($settings->_cmdTimeout);
//	require_once("vendor/seeddms/preview/Preview.php");
	$txtpreviewer = new SeedDMS_Preview_TxtPreviewer($settings->_cacheDir, $settings->_cmdTimeout, $settings->_enableXsendfile);
	if($conversionmgr)
		$txtpreviewer->setConversionMgr($conversionmgr);
	$fulltextservice->setPreviewer($txtpreviewer);
}

