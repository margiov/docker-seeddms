<?php
/**
 * Implementation of conversion manager
 *
 * @category   DMS
 * @package    SeedDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2021 Uwe Steinmann
 * @version    Release: @package_version@
 */

require_once("inc/inc.ClassConversionServiceExec.php");
require_once("inc/inc.ClassConversionServiceImageToImage.php");
require_once("inc/inc.ClassConversionServicePdfToImage.php");
require_once("inc/inc.ClassConversionServiceTextToText.php");

/**
 * Implementation of conversion manager
 *
 * @category   DMS
 * @package    SeedDMS
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2021 Uwe Steinmann
 * @version    Release: @package_version@
 */
class SeedDMS_ConversionMgr {
	/**
	 * List of services for searching fulltext
	 */
	public $services;

	public function __construct() {
		$this->services = array();
	}

	public function addService($service) {
		$this->services[$service->from][$service->to][] = $service;
		return $service;
	}

	public function hasService($from, $to) {
		if(!empty($this->services[$from][$to]))
			return true;
		else
			return false;
	}

	/**
	 * Return the service that would be tried first for converting
	 * the document.
	 *
	 * The conversion may not use this service but choose a different
	 * one when it fails.
	 */
	public function getService($from, $to) {
		if(!empty($this->services[$from][$to]))
			return end($this->services[$from][$to]);
		else
			return null;
	}

	public function getServices() {
		return $this->services;
	}

	/**
	 * Convert a file
	 *
	 * @param string $file name of file to convert
	 * @param string $from mimetype of input file
	 * @param string $to   mimetype of output file
	 *
	 * @return boolean true on success, other false
	 */
	public function convert($file, $from, $to, $target=null, $params=array()) {
		if(isset($this->services[$from][$to])) {
			$services = $this->services[$from][$to];
			for(end($services); key($services)!==null; prev($services)) {
				$service = current($services);
				$text = $service->convert($file, $target, $params);
				if($text !== false)
					return $text;
			}
		}
	}
}
