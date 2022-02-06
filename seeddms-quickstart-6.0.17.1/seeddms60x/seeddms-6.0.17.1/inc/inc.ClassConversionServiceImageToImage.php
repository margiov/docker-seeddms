<?php
/**
 * Implementation of conversion service image class
 *
 * @category   DMS
 * @package    SeedDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2021 Uwe Steinmann
 * @version    Release: @package_version@
 */

require_once("inc/inc.ClassConversionServiceBase.php");

/**
 * Implementation of conversion service image class
 *
 * @category   DMS
 * @package    SeedDMS
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2021 Uwe Steinmann
 * @version    Release: @package_version@
 */
class SeedDMS_ConversionServiceImageToImage extends SeedDMS_ConversionServiceBase {
	/**
	 * timeout
	 */
	public $timeout;

	public function __construct($from, $to) {
		$this->from = $from;
		$this->to = $to;
		$this->timeout = 5;
	}

	public function getInfo() {
		return "Convert with imagick php functions";
	}

	public function getAdditionalParams() { /* {{{ */
		return [
			['name'=>'width', 'type'=>'number', 'description'=>'Width of converted image']
		];
	} /* }}} */

	public function convert($infile, $target = null, $params = array()) {
		$start = microtime(true);
		$imagick = new Imagick();
		try {
			if($imagick->readImage($infile)) {
				if(!empty($params['width']))
					$imagick->scaleImage(min((int) $params['width'], $imagick->getImageWidth()), 0);
				$end = microtime(true);
				if($this->logger) {
					$this->logger->log('Conversion from '.$this->from.' to '.$this->to.' with image service took '.($end-$start).' sec.', PEAR_LOG_INFO);
				}
				if($target) {
					return $imagick->writeImage($target);
				} else {
					return $imagick->getImageBlob();
				}
			}
		} catch (ImagickException $e) {
			return false;
		}
		return false;
	}
}


