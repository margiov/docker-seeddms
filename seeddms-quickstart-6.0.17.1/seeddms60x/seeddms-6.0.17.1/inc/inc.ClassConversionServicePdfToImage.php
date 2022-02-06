<?php
/**
 * Implementation of conversion service pdf class
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
 * Implementation of conversion service pdf class
 *
 * @category   DMS
 * @package    SeedDMS
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2021 Uwe Steinmann
 * @version    Release: @package_version@
 */
class SeedDMS_ConversionServicePdfToImage extends SeedDMS_ConversionServiceBase {
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
			['name'=>'width', 'type'=>'number', 'description'=>'Width of converted image'],
			['name'=>'page', 'type'=>'number', 'description'=>'Page of Pdf document'],
		];
	} /* }}} */

	public function convert($infile, $target = null, $params = array()) {
		$start = microtime(true);
		$imagick = new Imagick();
		/* Setting a smaller resolution will speed up the conversion
		 * A resolution of 72,72 will create a 596x842 image
		 */
		$imagick->setResolution(36,36);
		$page = 0;
		if(!empty($params['page']) && intval($params['page']) > 0)
			$page = intval($params['page'])-1;
		try {
			if($imagick->readImage($infile.'['.$page.']')) {
				if(!empty($params['width']))
					$imagick->scaleImage(min((int) $params['width'], $imagick->getImageWidth()), 0);
				$imagick->setImageFormat('png');
				$end = microtime(true);
				if($this->logger) {
					$this->logger->log('Conversion from '.$this->from.' to '.$this->to.' with pdf service took '.($end-$start).' sec.', PEAR_LOG_INFO);
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



