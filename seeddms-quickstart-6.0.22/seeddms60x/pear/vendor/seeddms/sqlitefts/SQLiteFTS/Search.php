<?php
/**
 * Implementation of search in SQlite FTS index
 *
 * @category   DMS
 * @package    SeedDMS_Lucene
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010, Uwe Steinmann
 * @version    Release: @package_version@
 */


/**
 * Class for searching in a SQlite FTS index.
 *
 * @category   DMS
 * @package    SeedDMS_Lucene
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2011, Uwe Steinmann
 * @version    Release: @package_version@
 */
class SeedDMS_SQliteFTS_Search {
	/**
	 * @var object $index SQlite FTS index
	 * @access protected
	 */
	protected $index;

	/**
	 * Create a new instance of the search
	 *
	 * @param object $index SQlite FTS index
	 * @return object instance of SeedDMS_SQliteFTS_Search
	 */
	function __construct($index) { /* {{{ */
		$this->index = $index;
		$this->version = '@package_version@';
		if($this->version[0] == '@')
			$this->version = '3.0.0';
	} /* }}} */

	/**
	 * Get document from index
	 *
	 * @param int $id id of seeddms document
	 * @return object instance of SeedDMS_SQliteFTS_QueryHit or false
	 */
	function getDocument($id) { /* {{{ */
		$hits = $this->index->findById('D'.$id);
		return $hits ? $hits[0] : false;
	} /* }}} */

	/**
	 * Get folder from index
	 *
	 * @param int $id id of seeddms folder
	 * @return object instance of SeedDMS_SQliteFTS_QueryHit or false
	 */
	function getFolder($id) { /* {{{ */
		$hits = $this->index->findById('F'.$id);
		return $hits ? $hits[0] : false;
	} /* }}} */

	/**
	 * Search in index
	 *
	 * @param object $index SQlite FTS index
	 * @return object instance of SeedDMS_Lucene_Search
	 */
	function search($term, $fields=array(), $limit=array(), $order=array()) { /* {{{ */
		$querystr = '';
		$term = trim($term);
		if($term) {
			$querystr = substr($term, -1) != '*' ? $term.'*' : $term;
		}
		if(!empty($fields['owner'])) {
			if(is_string($fields['owner'])) {
				if($querystr)
					$querystr .= ' AND ';
				$querystr .= 'owner:'.$fields['owner'];
			} elseif(is_array($fields['owner'])) {
				if($querystr)
					$querystr .= ' AND ';
				$querystr .= '(owner:';
				$querystr .= implode(' OR owner:', $fields['owner']);
				$querystr .= ')';
			}
		}
		if(!empty($fields['record_type'])) {
			if($querystr)
				$querystr .= ' AND ';
			$querystr .= '(record_type:';
			$querystr .= implode(' OR record_type:', $fields['record_type']);
			$querystr .= ')';
		}
		if(!empty($fields['category'])) {
			if($querystr)
				$querystr .= ' AND ';
			$querystr .= '(category:"';
			$querystr .= implode('" AND category:"', $fields['category']);
			$querystr .= '")';
		}
		if(!empty($fields['mimetype'])) {
			if($querystr)
				$querystr .= ' AND ';
			$querystr .= '(mimetype:"';
			$querystr .= implode('" OR mimetype:"', $fields['mimetype']);
			$querystr .= '")';
		}
		if(!empty($fields['status'])) {
			if($querystr)
				$querystr .= ' AND ';
			$status = array_map(function($v){return (int)$v+10;}, $fields['status']);
			$querystr .= '(status:';
			$querystr .= implode(' OR status:', $status);
			$querystr .= ')';
		}
		if(!empty($fields['user'])) {
			if($querystr)
				$querystr .= ' AND ';
			$querystr .= '(users:';
			$querystr .= implode(' OR users:', $fields['user']);
			$querystr .= ')';
		}
		if(!empty($fields['rootFolder']) && $fields['rootFolder']->getFolderList()) {
			if($querystr)
				$querystr .= ' AND ';
			$querystr .= '(path:';
			$querystr .= str_replace(':', 'x', $fields['rootFolder']->getFolderList().$fields['rootFolder']->getID().':');
			$querystr .= '*)';
		}
		if(!empty($fields['startFolder']) && $fields['startFolder']->getFolderList()) {
			if($querystr)
				$querystr .= ' AND ';
			$querystr .= '(path:';
			$querystr .= str_replace(':', 'x', $fields['startFolder']->getFolderList().$fields['startFolder']->getID().':');
			$querystr .= '*)';
		}

		$filterstr = '';
		if(!empty($fields['created_start'])) {
			if($filterstr)
				$filterstr .= ' AND ';
			$filterstr .= '(created>='.$fields['created_start'].')';
		}
		if(!empty($fields['created_end'])) {
			if($filterstr)
				$filterstr .= ' AND ';
			$filterstr .= '(created<'.$fields['created_end'].')';
		}
		try {
			$result = $this->index->find($querystr, $filterstr, $limit, $order);
			$recs = array();
			foreach($result["hits"] as $hit) {
				$recs[] = array('id'=>$hit->id, 'document_id'=>$hit->documentid);
			}
			return array('count'=>$result['count'], 'hits'=>$recs, 'facets'=>$result['facets']);
		} catch (Exception $e) {
			return false;
		}
	} /* }}} */
}
?>
