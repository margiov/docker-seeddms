<?php
/**
 * Implementation of SQLiteFTS index
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
 * Class for managing a SQLiteFTS index.
 *
 * @category   DMS
 * @package    SeedDMS_Lucene
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2011, Uwe Steinmann
 * @version    Release: @package_version@
 */
class SeedDMS_SQLiteFTS_Indexer {

	/**
	 * @var string $_ftstype
	 * @access protected
	 */
	protected $_ftstype;

	/**
	 * @var object $_conn sqlite index
	 * @access protected
	 */
	protected $_conn;

	/**
	 * @var array $_stop_words array of stop words
	 * @access protected
	 */
	protected $_stop_words;

	const ftstype = 'fts5';

	/**
	 * Remove stopwords from string
	 */
  protected function strip_stopwords($str = "") { /* {{{ */
    // 1.) break string into words
    // [^-\w\'] matches characters, that are not [0-9a-zA-Z_-']
    // if input is unicode/utf-8, the u flag is needed: /pattern/u
    $words = preg_split('/[^-\w\']+/u', $str, -1, PREG_SPLIT_NO_EMPTY);

    // 2.) if we have at least 2 words, remove stopwords
    if(!empty($words)) {
      $stopwords = $this->_stop_words;
      $words = array_filter($words, function ($w) use (&$stopwords) {
        return ((mb_strlen($w, 'utf-8') > 2) && !isset($stopwords[mb_strtolower($w, "utf-8")]));
      });
    }

    // check if not too much was removed such as "the the" would return empty
    if(!empty($words))
      return implode(" ", $words);
    return $str;
  } /* }}} */

	/**
	 * Constructor
	 *
	 */
	function __construct($indexerDir) { /* {{{ */
		$this->_conn = new PDO('sqlite:'.$indexerDir.'/index.db');
		$this->_ftstype = self::ftstype;
		if($this->_ftstype == 'fts5')
			$this->_rawid = 'rowid';
		else
			$this->_rawid = 'docid';
		$this->_stop_words = [];
	} /* }}} */

	/**
	 * Open an existing index
	 *
	 * @param string $indexerDir directory on disk containing the index
	 */
	static function open($conf) { /* {{{ */
		if(file_exists($conf['indexdir'].'/index.db')) {
			return new SeedDMS_SQLiteFTS_Indexer($conf['indexdir']);
		} else
			return static::create($conf);
	} /* }}} */

	/**
	 * Create a new index
	 *
	 * @param array $conf $conf['indexdir'] is the directory on disk containing the index
	 */
	static function create($conf) { /* {{{ */
		if(file_exists($conf['indexdir'].'/index.db'))
			unlink($conf['indexdir'].'/index.db');
		$index =  new SeedDMS_SQLiteFTS_Indexer($conf['indexdir']);
		/* Make sure the sequence of fields is identical to the field list
		 * in SeedDMS_SQLiteFTS_Term
		 */
		$version = SQLite3::version();
		if(self::ftstype == 'fts4') {
			if($version['versionNumber'] >= 3008000)
				$sql = 'CREATE VIRTUAL TABLE docs USING fts4(documentid, record_type, title, comment, keywords, category, mimetype, origfilename, owner, content, created, indexed, users, status, path, notindexed=created, notindexed=indexed, matchinfo=fts3)';
			else
				$sql = 'CREATE VIRTUAL TABLE docs USING fts4(documentid, record_type, title, comment, keywords, category, mimetype, origfilename, owner, content, created, indexed, users, status, path, matchinfo=fts3)';
			$res = $index->_conn->exec($sql);
			if($res === false) {
				return null;
			}
			$sql = 'CREATE VIRTUAL TABLE docs_terms USING fts4aux(docs);';
			$res = $index->_conn->exec($sql);
			if($res === false) {
				return null;
			}
		} elseif(self::ftstype == 'fts5') {
			$sql = 'CREATE VIRTUAL TABLE docs USING fts5(documentid, record_type, title, comment, keywords, category, mimetype, origfilename, owner, content, created unindexed, indexed unindexed, users, status, path)';
			$res = $index->_conn->exec($sql);
			if($res === false) {
				return null;
			}
			$sql = 'CREATE VIRTUAL TABLE docs_terms USING fts5vocab(docs, \'col\');';
			$res = $index->_conn->exec($sql);
			if($res === false) {
				return null;
			}
		} else
			return null;
		return($index);
	} /* }}} */

	/**
	 * Do some initialization
	 *
	 */
	public function init($stopWordsFile='') { /* {{{ */
		if($stopWordsFile)
			$this->_stop_words = array_flip(preg_split("/[\s,]+/", file_get_contents($stopWordsFile)));
	} /* }}} */

	/**
	 * Add document to index
	 *
	 * @param object $doc indexed document of class 
	 * SeedDMS_SQLiteFTS_IndexedDocument
	 * @return boolean false in case of an error, otherwise true
	 */
	function addDocument($doc) { /* {{{ */
		if(!$this->_conn)
			return false;

		foreach(array('comment', 'keywords', 'category', 'content', 'mimetype', 'origfilename', 'status', 'created', 'indexed') as $kk) {
			try {
				${$kk} = $doc->getFieldValue($kk);
			} catch (Exception $e) {
				${$kk} = '';
			}
		}
		$sql = "DELETE FROM docs WHERE documentid=".$this->_conn->quote($doc->getFieldValue('document_id'));
		$res = $this->_conn->exec($sql);
		if($res === false) {
			return false;
		}
		if($this->_stop_words)
			$content = $this->strip_stopwords($content);

		$sql = "INSERT INTO docs (documentid, record_type, title, comment, keywords, category, owner, content, mimetype, origfilename, created, indexed, users, status, path) VALUES (".$this->_conn->quote($doc->getFieldValue('document_id')).", ".$this->_conn->quote($doc->getFieldValue('record_type')).", ".$this->_conn->quote($doc->getFieldValue('title')).", ".$this->_conn->quote($comment).", ".$this->_conn->quote($keywords).", ".$this->_conn->quote($category).", ".$this->_conn->quote($doc->getFieldValue('owner')).", ".$this->_conn->quote($content).", ".$this->_conn->quote($mimetype).", ".$this->_conn->quote($origfilename).", ".(int)$created.", ".(int)$indexed.", ".$this->_conn->quote($doc->getFieldValue('users')).", ".$this->_conn->quote($status).", ".$this->_conn->quote($doc->getFieldValue('path'))/*time()*/.")";
		$res = $this->_conn->exec($sql);
		if($res === false) {
			return false;
			var_dump($this->_conn->errorInfo());
		}
		return $res;
	} /* }}} */

	/**
	 * Remove document from index
	 *
	 * @param object $id internal id of document
	 * @return boolean false in case of an error, otherwise true
	 */
	public function delete($id) { /* {{{ */
		if(!$this->_conn)
			return false;

		$sql = "DELETE FROM docs WHERE ".$this->_rawid."=".(int) $id;
		$res = $this->_conn->exec($sql);
		return $res;
	} /* }}} */

	/**
	 * Check if document was deleted
	 *
	 * Just for compatibility with lucene.
	 *
	 * @return boolean always false
	 */
	public function isDeleted($id) { /* {{{ */
		return false;
	} /* }}} */

	/**
	 * Find documents in index
	 *
	 * @param string $query 
	 * @param array $limit array with elements 'limit' and 'offset'
	 * @return boolean false in case of an error, otherwise array with elements
	 * 'count', 'hits', 'facets'. 'hits' is an array of SeedDMS_SQLiteFTS_QueryHit
	 */
	public function find($query, $filter='', $limit=array(), $order=array()) { /* {{{ */
		if(!$this->_conn)
			return false;

		/* First count some records for facets */
		foreach(array('owner', 'mimetype', 'category', 'status') as $facetname) {
			$sql = "SELECT `".$facetname."`, count(*) AS `c` FROM `docs`";
			if($query) {
				$sql .= " WHERE docs MATCH ".$this->_conn->quote($query);
			}
			if($filter) {
				if($query)
					$sql .= " AND ".$filter;
				else
					$sql .= " WHERE ".$filter;
			}
			$res = $this->_conn->query($sql." GROUP BY `".$facetname."`");
			if(!$res)
				throw new SeedDMS_SQLiteFTS_Exception("Counting records in facet \"$facetname\" failed.");
//				return false;
			$facets[$facetname] = array();
			foreach($res as $row) {
				if($row[$facetname] && $row['c']) {
					if($facetname == 'category') {
						$tmp = explode('#', $row[$facetname]);
						if(count($tmp) > 1) {
							foreach($tmp as $t) {
								if(!isset($facets[$facetname][$t]))
									$facets[$facetname][$t] = $row['c'];
								else
									$facets[$facetname][$t] += $row['c'];
							}
						} else {
							if(!isset($facets[$facetname][$row[$facetname]]))
								$facets[$facetname][$row[$facetname]] = $row['c'];
							else
								$facets[$facetname][$row[$facetname]] += $row['c'];
						}
					} elseif($facetname == 'status') {
						$facets[$facetname][($row[$facetname]-10).''] = $row['c'];
					} else
						$facets[$facetname][$row[$facetname]] = $row['c'];
				}
			}
		}

		$sql = "SELECT `record_type`, count(*) AS `c` FROM `docs`";
		if($query)
			$sql .= " WHERE docs MATCH ".$this->_conn->quote($query);
		if($filter) {
			if($query)
				$sql .= " AND ".$filter;
			else
				$sql .= " WHERE ".$filter;
		}
		$res = $this->_conn->query($sql." GROUP BY `record_type`");
		if(!$res)
			throw new SeedDMS_SQLiteFTS_Exception("Counting records in facet \"record_type\" failed.");
//			return false;
		$facets['record_type'] = array('document'=>0, 'folder'=>0);
		foreach($res as $row) {
			$facets['record_type'][$row['record_type']] = $row['c'];
		}
		$total = $facets['record_type']['document'] + $facets['record_type']['folder'];

		$sql = "SELECT ".$this->_rawid.", documentid FROM docs";
		if($query)
			$sql .= " WHERE docs MATCH ".$this->_conn->quote($query);
		if($filter) {
			if($query)
				$sql .= " AND ".$filter;
			else
				$sql .= " WHERE ".$filter;
		}
		if($this->_ftstype == 'fts5') {
			//$sql .= " ORDER BY rank";
			// boost documentid, record_type, title, comment, keywords, category, mimetype, origfilename, owner, content, created unindexed, users, status, path
			if(!empty($order['by'])) {
				switch($order['by']) {
				case "title":
					$sql .= " ORDER BY title";
					break;
				case "created":
					$sql .= " ORDER BY created";
					break;
				default:
					$sql .= " ORDER BY bm25(docs, 10.0, 0.0, 10.0, 5.0, 5.0, 10.0)";
				}
				if(!empty($order['dir'])) {
					if($order['dir'] == 'desc')
						$sql .= " DESC";
				}
			}
		}
		if(!empty($limit['limit']))
			$sql .= " LIMIT ".(int) $limit['limit'];
		if(!empty($limit['offset']))
			$sql .= " OFFSET ".(int) $limit['offset'];
		$res = $this->_conn->query($sql);
		if(!$res)
			throw new SeedDMS_SQLiteFTS_Exception("Searching for documents failed.");
		$hits = array();
		if($res) {
			foreach($res as $rec) {
				$hit = new SeedDMS_SQLiteFTS_QueryHit($this);
				$hit->id = $rec[$this->_rawid];
				$hit->documentid = $rec['documentid'];
				$hits[] = $hit;
			}
		}
		return array('count'=>$total, 'hits'=>$hits, 'facets'=>$facets);
	} /* }}} */

	/**
	 * Get a single document from index
	 *
	 * @param string $id id of document
	 * @return boolean false in case of an error, otherwise true
	 */
	public function findById($id) { /* {{{ */
		if(!$this->_conn)
			return false;

		$sql = "SELECT ".$this->_rawid.", documentid FROM docs WHERE documentid=".$this->_conn->quote($id);
		$res = $this->_conn->query($sql);
		$hits = array();
		if($res) {
			while($rec = $res->fetch(PDO::FETCH_ASSOC)) {
				$hit = new SeedDMS_SQLiteFTS_QueryHit($this);
				$hit->id = $rec[$this->_rawid];
				$hit->documentid = $rec['documentid'];
				$hits[] = $hit;
			}
		}
		return $hits;
	} /* }}} */

	/**
	 * Get a single document from index
	 *
	 * @param integer $id id of index record
	 * @return boolean false in case of an error, otherwise true
	 */
	public function getDocument($id, $content=true) { /* {{{ */
		if(!$this->_conn)
			return false;

		$sql = "SELECT ".$this->_rawid.", documentid, title, comment, owner, keywords, category, mimetype, origfilename, created, indexed, users, status, path".($content ? ", content" : "")." FROM docs WHERE ".$this->_rawid."='".$id."'";
		$res = $this->_conn->query($sql);
		$doc = false;
		if($res) {
			if(!($rec = $res->fetch(PDO::FETCH_ASSOC)))
				return false;
			$doc = new SeedDMS_SQLiteFTS_Document();
			$doc->addField(SeedDMS_SQLiteFTS_Field::Keyword('docid', $rec[$this->_rawid]));
			$doc->addField(SeedDMS_SQLiteFTS_Field::Keyword('document_id', $rec['documentid']));
			$doc->addField(SeedDMS_SQLiteFTS_Field::Text('title', $rec['title']));
			$doc->addField(SeedDMS_SQLiteFTS_Field::Text('comment', $rec['comment']));
			$doc->addField(SeedDMS_SQLiteFTS_Field::Text('keywords', $rec['keywords']));
			$doc->addField(SeedDMS_SQLiteFTS_Field::Text('category', $rec['category']));
			$doc->addField(SeedDMS_SQLiteFTS_Field::Keyword('mimetype', $rec['mimetype']));
			$doc->addField(SeedDMS_SQLiteFTS_Field::Keyword('origfilename', $rec['origfilename']));
			$doc->addField(SeedDMS_SQLiteFTS_Field::Text('owner', $rec['owner']));
			$doc->addField(SeedDMS_SQLiteFTS_Field::Keyword('created', $rec['created']));
			$doc->addField(SeedDMS_SQLiteFTS_Field::Keyword('indexed', $rec['indexed']));
			$doc->addField(SeedDMS_SQLiteFTS_Field::Text('users', $rec['users']));
			$doc->addField(SeedDMS_SQLiteFTS_Field::Keyword('status', $rec['status']));
			$doc->addField(SeedDMS_SQLiteFTS_Field::Keyword('path', explode('x', substr($rec['path'], 1, -1))));
			if($content)
				$doc->addField(SeedDMS_SQLiteFTS_Field::UnStored('content', $rec['content']));
		}
		return $doc;
	} /* }}} */

	/**
	 * Return list of terms in index
	 *
	 * @return array list of SeedDMS_SQLiteFTS_Term
	 */
	public function terms($prefix='', $col='') { /* {{{ */
		if(!$this->_conn)
			return false;

		if($this->_ftstype == 'fts5') {
			$sql = "SELECT term, col, doc as occurrences FROM docs_terms";
			if($prefix || $col) {
				$sql .= " WHERE";
				if($prefix) {
					$sql .= " term like '".$prefix."%'";
					if($col)
						$sql .= " AND";
				}
				if($col)
					$sql .= " col = '".$col."'";
			}
			$sql .= " ORDER BY col, occurrences desc";
		} else {
			$sql = "SELECT term, col, occurrences FROM docs_terms WHERE col!='*'";
			if($prefix)
				$sql .= " AND term like '".$prefix."%'";
			if($col)
				$sql .= " AND col = '".$col."'";
			$sql .=	" ORDER BY col, occurrences desc";
		}
		$res = $this->_conn->query($sql);
		$terms = array();
		if($res) {
			while($rec = $res->fetch(PDO::FETCH_ASSOC)) {
				$term = new SeedDMS_SQLiteFTS_Term($rec['term'], $rec['col'], $rec['occurrences']);
				$terms[] = $term;
			}
		}
		return $terms;
	} /* }}} */

	/**
	 * Return number of documents in index
	 *
	 * @return interger number of documents
	 */
	public function count() { /* {{{ */
		$sql = "SELECT count(*) c FROM docs";
		$res = $this->_conn->query($sql);
		if($res) {
			$rec = $res->fetch(PDO::FETCH_ASSOC);
			return $rec['c'];
		}
		return 0;
	} /* }}} */

	/**
	 * Commit changes
	 *
	 * This function does nothing!
	 */
	function commit() { /* {{{ */
	} /* }}} */

	/**
	 * Optimize index
	 *
	 * This function does nothing!
	 */
	function optimize() { /* {{{ */
	} /* }}} */
}
?>
