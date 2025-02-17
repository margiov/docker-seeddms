<?php
/**
 * Implementation of a query hit
 *
 * @category   DMS
 * @package    SeedDMS_SQLiteFTS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010, Uwe Steinmann
 * @version    Release: @package_version@
 */


/**
 * Class for managing a query hit.
 *
 * @category   DMS
 * @package    SeedDMS_SQLiteFTS
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2011, Uwe Steinmann
 * @version    Release: @package_version@
 */
class SeedDMS_SQLiteFTS_QueryHit {

	/**
	 * @var SeedDMS_SQliteFTS_Indexer $index
	 * @access protected
	 */
	protected $_index;

	/**
	 * @var SeedDMS_SQliteFTS_Document $document
	 * @access protected
	 */
	protected $_document;

	/**
	 * @var integer $id id of index document
	 * @access public
	 */
	public $id;

	/**
	 * @var integer $id id of real document
	 * @access public
	 */
	public $documentid;

	/**
	 *
	 */
	public function __construct(SeedDMS_SQLiteFTS_Indexer $index) { /* {{{ */
		$this->_index = $index;
		$this->_document = null;
	} /* }}} */

	/**
	 * Return the document associated with this hit
	 *
	 * @return SeedDMS_SQLiteFTS_Document
	 */
	public function getDocument() { /* {{{ */
		if (!$this->_document instanceof SeedDMS_SQLiteFTS_Document) {
			$this->_document = $this->_index->getDocument($this->id);
		}

		return $this->_document;
	} /* }}} */
}
?>
