<?php
/**
 * Implementation of keyword categories in the document management system
 *
 * @category   DMS
 * @package    SeedDMS_Core
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal, 2006-2008 Malcolm Cowe,
 *             2010-2023 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Class to represent a keyword category in the document management system
 *
 * @category   DMS
 * @package    SeedDMS_Core
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal, 2006-2008 Malcolm Cowe,
 *             2010-2023 Uwe Steinmann
 * @version    Release: @package_version@
 */
class SeedDMS_Core_KeywordCategory {
	/**
	 * @var integer $_id id of keyword category
	 * @access protected
	 */
	protected $_id;

	/**
	 * @var integer $_ownerID id of user who is the owner
	 * @access protected
	 */
	protected $_ownerID;

	/**
	 * @var string $_name name of category
	 * @access protected
	 */
	protected $_name;

	/**
	 * @var SeedDMS_Core_DMS $_dms reference to dms this category belongs to
	 * @access protected
	 */
	protected $_dms;

	/**
	 * SeedDMS_Core_KeywordCategory constructor.
	 * @param $id
	 * @param $ownerID
	 * @param $name
	 */
	function __construct($id, $ownerID, $name) { /* {{{ */
		$this->_id = $id;
		$this->_name = $name;
		$this->_ownerID = $ownerID;
		$this->_dms = null;
	} /* }}} */

	/**
	 * @param SeedDMS_Core_DMS $dms
	 */
	function setDMS($dms) { /* {{{ */
		$this->_dms = $dms;
	} /* }}} */

	/**
	 * @return int
	 */
	function getID() { return $this->_id; }

	/**
	 * @return string
	 */
	function getName() { return $this->_name; }

	/**
	 * @return bool|SeedDMS_Core_User
	 */
	function getOwner() { /* {{{ */
		if (!isset($this->_owner))
			$this->_owner = $this->_dms->getUser($this->_ownerID);
		return $this->_owner;
	} /* }}} */

	/**
	 * @param $newName
	 * @return bool
	 */
	function setName($newName) { /* {{{ */
		$newName = trim($newName);
		if(!$newName)
			return false;

		$db = $this->_dms->getDB();

		$queryStr = "UPDATE `tblKeywordCategories` SET `name` = ".$db->qstr($newName)." WHERE `id` = ". $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_name = $newName;
		return true;
	} /* }}} */

	/**
	 * @param SeedDMS_Core_User $user
	 * @return bool
	 */
	function setOwner($user) { /* {{{ */
		if(!$user || !$user->isType('user'))
			return false;

		$db = $this->_dms->getDB();

		$queryStr = "UPDATE `tblKeywordCategories` SET `owner` = " . $user->getID() . " WHERE `id` = " . $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_ownerID = $user->getID();
		$this->_owner = $user;
		return true;
	} /* }}} */

	/**
	 * @return array keywords in this list
	 */
	function getKeywordLists() { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "SELECT * FROM `tblKeywords` WHERE `category` = " . $this->_id . " order by `keywords`";
		return $db->getResultArray($queryStr);
	}

	/**
	 * @return integer number of keywords in this list
	 */
	function countKeywordLists() { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "SELECT COUNT(*) as `c` FROM `tblKeywords` where `category`=".$this->_id;
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && !$resArr)
			return false;

		return $resArr[0]['c'];
	} /* }}} */

	/**
	 * @param $listID
	 * @param $keywords
	 * @return bool
	 */
	function editKeywordList($listID, $keywords) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE `tblKeywords` SET `keywords` = ".$db->qstr($keywords)." WHERE `id` = $listID";
		return $db->getResult($queryStr);
	} /* }}} */

	/**
	 * @param $keywords
	 * @return bool
	 */
	function addKeywordList($keywords) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "INSERT INTO `tblKeywords` (`category`, `keywords`) VALUES (" . $this->_id . ", ".$db->qstr($keywords).")";
		return $db->getResult($queryStr);
	} /* }}} */

	/**
	 * @param $listID
	 * @return bool
	 */
	function removeKeywordList($listID) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "DELETE FROM `tblKeywords` WHERE `id` = $listID";
		return $db->getResult($queryStr);
	} /* }}} */

	/**
	 * @return bool
	 */
	function remove() { /* {{{ */
		$db = $this->_dms->getDB();

		$db->startTransaction();
		$queryStr = "DELETE FROM `tblKeywords` WHERE `category` = " . $this->_id;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$queryStr = "DELETE FROM `tblKeywordCategories` WHERE `id` = " . $this->_id;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$db->commitTransaction();
		return true;
	} /* }}} */
}
