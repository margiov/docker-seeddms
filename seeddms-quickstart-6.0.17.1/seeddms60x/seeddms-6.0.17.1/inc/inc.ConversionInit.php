<?php
$conversionmgr = null;
require_once("inc.ClassConversionMgr.php");
$conversionmgr = new SeedDMS_ConversionMgr();

if(!empty($settings->_converters['preview'])) {
	foreach($settings->_converters['preview'] as $mimetype=>$cmd) {
		$conversionmgr->addService(new SeedDMS_ConversionServiceExec($mimetype, 'image/png', $cmd))->setLogger($logger);
	}
}

if(!empty($settings->_converters['pdf'])) {
	foreach($settings->_converters['pdf'] as $mimetype=>$cmd) {
		$conversionmgr->addService(new SeedDMS_ConversionServiceExec($mimetype, 'application/pdf', $cmd))->setLogger($logger);
	}
}

if(!empty($settings->_converters['fulltext'])) {
	foreach($settings->_converters['fulltext'] as $mimetype=>$cmd) {
		$conversionmgr->addService(new SeedDMS_ConversionServiceExec($mimetype, 'text/plain', $cmd))->setLogger($logger);
	}
}

$conversionmgr->addService(new SeedDMS_ConversionServicePdfToImage('application/pdf', 'image/png'))->setLogger($logger);

$conversionmgr->addService(new SeedDMS_ConversionServiceImageToImage('image/jpeg', 'image/png'))->setLogger($logger);
$conversionmgr->addService(new SeedDMS_ConversionServiceImageToImage('image/png', 'image/png'))->setLogger($logger);
$conversionmgr->addService(new SeedDMS_ConversionServiceImageToImage('image/jpg', 'image/png'))->setLogger($logger);
$conversionmgr->addService(new SeedDMS_ConversionServiceImageToImage('image/svg+xml', 'image/png'))->setLogger($logger);

$conversionmgr->addService(new SeedDMS_ConversionServiceTextToText('text/plain', 'text/plain'))->setLogger($logger);

if(isset($GLOBALS['SEEDDMS_HOOKS']['initConversion'])) {
	foreach($GLOBALS['SEEDDMS_HOOKS']['initConversion'] as $hookObj) {
		if (method_exists($hookObj, 'getConversionServices')) {
			if($services = $hookObj->getConversionServices(array('dms'=>$dms, 'settings'=>$settings, 'logger'=>$logger))) {
				foreach($services as $service) {
					$conversionmgr->addService($service)->setLogger($logger);
				}
			}
		}
	}
}
