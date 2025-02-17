<?php

require_once("inc/inc.ClassSchedulerTaskBase.php");

/**
 * Class containing methods for running a scheduled task
 *
 * @author  Uwe Steinmann <uwe@steinmann.cx>
 * @package SeedDMS
 * @subpackage  core
 */
class SeedDMS_ExpiredDocumentsTask extends SeedDMS_SchedulerTaskBase { /* {{{ */

	/**
	 * Run the task
	 *
	 * @param SeedDMS_SchedulerTask $task task to be executed
	 * @return boolean true if task was executed succesfully, otherwise false
	 */
	public function execute(SeedDMS_SchedulerTask $task) {
		$dms = $this->dms;
		$user = $this->user;
		$settings = $this->settings;
		$logger = $this->logger;
		$taskparams = $task->getParameter();
		$tableformat = " %-10s %5d %-60s";
		$tableformathead = " %-10s %5s %-60s";
		$tableformathtml = "<tr><td>%s</td><td>%d</td><td>%s</td></tr>";
		$tableformatheadhtml = "<tr><th>%s</th><th>%s</th><th>%s</th></tr>";
		$body = '';
		$bodyhtml = '';

		require_once('inc/inc.ClassEmailNotify.php');
		$email = new SeedDMS_EmailNotify($dms, $settings->_smtpSendFrom, $settings->_smtpServer, $settings->_smtpPort, $settings->_smtpUser, $settings->_smtpPassword);

		if(!empty($taskparams['peruser'])) {
			$users = $dms->getAllUsers();
			foreach($users as $u) {
				if(!$u->isGuest() && !$u->isDisabled()) {
					$docs = $dms->getDocumentsExpired(intval($taskparams['days']), $u);
					if (count($docs)>0) {
						$bodyhtml .= "<table>".PHP_EOL;
						$bodyhtml .= sprintf($tableformatheadhtml."\n", getMLText("expires", array(), ""), "ID", getMLText("name", array(), ""));
						$body .= sprintf($tableformathead."\n", getMLText("expires", array(), ""), "ID", getMLText("name", array(), ""));
						$body .= "---------------------------------------------------------------------------------\n";
						foreach($docs as $doc) {
							$body .= sprintf($tableformat."\n", getReadableDate($doc->getExpires()), $doc->getId(), $doc->getName());
							$bodyhtml .= sprintf($tableformathtml."\n", getReadableDate($doc->getExpires()), $doc->getId(), $doc->getName());
						}
						$bodyhtml .= "</table>".PHP_EOL;
						$params = array();
						$params['count'] = count($docs);
						$params['__body__'] = $body;
						$params['__body_html__'] = $bodyhtml;
						$params['sitename'] = $settings->_siteName;
						$email->toIndividual('', $u, 'expired_docs_mail_subject', '', $params);

						$logger->log('Task \'expired_docs\': Sending reminder \'expired_docs_mail_subject\' to user \''.$u->getLogin().'\'', PEAR_LOG_INFO);
					}
				}
			}
		} elseif($taskparams['email']) {
			$docs = $dms->getDocumentsExpired(intval($taskparams['days']));
			if (count($docs)>0) {
				$bodyhtml .= "<table>".PHP_EOL;
				$bodyhtml .= sprintf($tableformatheadhtml."\n", getMLText("expiration_date", array(), ""), "ID", getMLText("name", array(), ""));
				$body .= sprintf($tableformathead."\n", getMLText("expiration_date", array(), ""), "ID", getMLText("name", array(), ""));
				$body .= "---------------------------------------------------------------------------------\n";
				foreach($docs as $doc) {
					$body .= sprintf($tableformat."\n", getReadableDate($doc->getExpires()), $doc->getId(), $doc->getName());
					$bodyhtml .= sprintf($tableformathtml."\n", getReadableDate($doc->getExpires()), $doc->getId(), $doc->getName());
				}
				$bodyhtml .= "</table>".PHP_EOL;
				$params = array();
				$params['count'] = count($docs);
				$params['__body__'] = $body;
				$params['__body_html__'] = $bodyhtml;
				$params['sitename'] = $settings->_siteName;
				$email->toIndividual('', $taskparams['email'], 'expired_docs_mail_subject', '', $params);

				$logger->log('Task \'expired_docs\': Sending reminder \'expired_docs_mail_subject\' to user \''.$taskparams['email'].'\'', PEAR_LOG_INFO);
			}
		} else {
				$logger->log('Task \'expired_docs\': neither peruser nor email is set', PEAR_LOG_WARNING);
		}
		return true;
	}

	public function getDescription() {
		return 'Check for expired documents and set the document status';
	}

	public function getAdditionalParams() {
		return array(
			array(
				'name'=>'email',
				'type'=>'string',
				'description'=> '',
			),
			array(
				'name'=>'days',
				'type'=>'integer',
				'description'=> 'Number of days to check for. Negative values will look into the past. 0 will just check for documents expiring the current day. Keep in mind that the document is still valid on the expiration date.',
			),
			array(
				'name'=>'peruser',
				'type'=>'boolean',
				'description'=> 'Send mail to each user. If set, a list of all expired documents will be send to the owner of the documents.',
			)
		);
	}
} /* }}} */

/**
 * Class for processing a single folder
 *
 * SeedDMS_Task_Indexer_Process_Folder::process() is used as a callable when
 * iterating over all folders recursively.
 */
class SeedDMS_Task_Indexer_Process_Folder { /* {{{ */
	protected $scheduler;

	protected $forceupdate;

	protected $fulltextservice;

	protected $logger;

	protected $dacount;

	protected $facount;

	protected $ducount;

	protected $fucount;

	public function __construct($scheduler, $fulltextservice, $forceupdate, $logger) { /* {{{ */
		$this->scheduler = $scheduler;
		$this->fulltextservice = $fulltextservice;
		$this->logger = $logger;
		$this->forceupdate = $forceupdate;
		$this->numdocs = $this->fulltextservice->Indexer()->count();
		$this->dacount = 0;
		$this->facount = 0;
		$this->ducount = 0;
		$this->fucount = 0;
	} /* }}} */

	public function process($folder, $depth=0) { /* {{{ */
		$lucenesearch = $this->fulltextservice->Search();
		$documents = $folder->getDocuments();
		$logger = $this->logger;
//		echo str_repeat('  ', $depth+1).$folder->getId().":".$folder->getFolderPathPlain()." ";
		if(($this->numdocs == 0) || !($hit = $lucenesearch->getFolder($folder->getId()))) {
			try {
				$idoc = $this->fulltextservice->IndexedDocument($folder, true);
				$error = $idoc->getErrorMsg();
				if(!$error) {
					if(isset($GLOBALS['SEEDDMS_HOOKS']['indexFolder'])) {
						foreach($GLOBALS['SEEDDMS_HOOKS']['indexFolder'] as $hookObj) {
							if (method_exists($hookObj, 'preIndexFolder')) {
								$hookObj->preIndexDocument(null, $folder, $idoc);
							}
						}
					}
					$this->fulltextservice->Indexer()->addDocument($idoc);
//					echo "(".getMLText('index_folder_added').")".PHP_EOL;
					$logger->log('Task \'indexingdocs\': folder '.$folder->getId().' added', PEAR_LOG_INFO);
					$this->facount++;
				} else {
//					echo "(".$error.")".PHP_EOL;
					$logger->log('Task \'indexingdocs\': adding folder '.$folder->getId().' failed', PEAR_LOG_ERR);
				}
			} catch(Exception $e) {
//				echo "(Timeout)".PHP_EOL;
				$logger->log('Task \'indexingdocs\': adding folder '.$folder->getId().' failed', PEAR_LOG_ERR);
			}
		} else {
			/* Check if the attribute indexed is set or has a value older
			 * than the lastet content. Folders without such an attribute
			 * where added when a new folder was added to the dms. In such
			 * a case the folder content wasn't indexed.
			 */
			try {
				$indexed = (int) $hit->getDocument()->getFieldValue('indexed');
			} catch (/* Zend_Search_Lucene_ */Exception $e) {
				$indexed = 0;
			}
			if($indexed >= $folder->getDate() && !$this->forceupdate) {
//				echo "(".getMLText('index_folder_unchanged').")".PHP_EOL;
			} else {
				$this->fulltextservice->Indexer()->delete($hit->id);
				try {
					$idoc = $this->fulltextservice->IndexedDocument($folder, true);
					$error = $idoc->getErrorMsg();
					if(!$error) {
						if(isset($GLOBALS['SEEDDMS_HOOKS']['indexDocument'])) {
							foreach($GLOBALS['SEEDDMS_HOOKS']['indexDocument'] as $hookObj) {
								if (method_exists($hookObj, 'preIndexDocument')) {
									$hookObj->preIndexDocument(null, $folder, $idoc);
								}
							}
						}
						$this->fulltextservice->Indexer()->addDocument($idoc);
//						echo "(".getMLText('index_folder_updated').")".PHP_EOL;
						$logger->log('Task \'indexingdocs\': folder '.$folder->getId().' updated', PEAR_LOG_INFO);
						$this->fucount++;
					} else {
//						echo "(".$error.")".PHP_EOL;
						$logger->log('Task \'indexingdocs\': updating folder '.$folder->getId().' failed', PEAR_LOG_ERR);
					}
				} catch(Exception $e) {
//					echo "(Timeout)".PHP_EOL;
					$logger->log('Task \'indexingdocs\': updating folder '.$folder->getId().' failed. '.$e->getMessage(), PEAR_LOG_ERR);
				}
			}
		}
		if($documents) {
			foreach($documents as $document) {
//				echo str_repeat('  ', $depth+2).$document->getId().":".$document->getName()." ";
				/* If the document wasn't indexed before then just add it */
				if(($this->numdocs == 0) || !($hit = $lucenesearch->getDocument($document->getId()))) {
					try {
						$idoc = $this->fulltextservice->IndexedDocument($document, true);
						if(isset($GLOBALS['SEEDDMS_HOOKS']['indexDocument'])) {
							foreach($GLOBALS['SEEDDMS_HOOKS']['indexDocument'] as $hookObj) {
								if (method_exists($hookObj, 'preIndexDocument')) {
									$hookObj->preIndexDocument(null, $document, $idoc);
								}
							}
						}
						if($this->fulltextservice->Indexer()->addDocument($idoc)) {
//						echo "(".getMLText('index_document_added').")".PHP_EOL;
							$logger->log('Task \'indexingdocs\': document '.$document->getId().' added', PEAR_LOG_INFO);
						} else {
							$logger->log('Task \'indexingdocs\': adding document '.$document->getId().' failed', PEAR_LOG_ERR);
						}
						$this->dacount++;
					} catch(Exception $e) {
//						echo "(Timeout)".PHP_EOL;
						$logger->log('Task \'indexingdocs\': adding document '.$document->getId().' failed. '.$e->getMessage(), PEAR_LOG_ERR);
					}
				} else {
					/* Check if the attribute indexed is set or has a value older
					 * than the lastet content. Documents without such an attribute
					 * where added when a new document was added to the dms. In such
					 * a case the document content wasn't indexed.
					 */
					try {
						$indexed = (int) $hit->getDocument()->getFieldValue('indexed');
					} catch (/* Zend_Search_Lucene_ */Exception $e) {
						$indexed = 0;
					}
					$content = $document->getLatestContent();
					if($content) {
						if($indexed >= $content->getDate() && !$this->forceupdate) {
//							echo "(".getMLText('index_document_unchanged').")".PHP_EOL;
						} else {
							$this->fulltextservice->Indexer()->delete($hit->id);
							try {
								$idoc = $this->fulltextservice->IndexedDocument($document, true);
								if(isset($GLOBALS['SEEDDMS_HOOKS']['indexDocument'])) {
									foreach($GLOBALS['SEEDDMS_HOOKS']['indexDocument'] as $hookObj) {
										if (method_exists($hookObj, 'preIndexDocument')) {
											$hookObj->preIndexDocument(null, $document, $idoc);
										}
									}
								}
								if($this->fulltextservice->Indexer()->addDocument($idoc)) {
//								echo "(".getMLText('index_document_updated').")".PHP_EOL;
									$logger->log('Task \'indexingdocs\': document '.$document->getId().' updated', PEAR_LOG_INFO);
								} else {
									$logger->log('Task \'indexingdocs\': updating document '.$document->getId().' failed', PEAR_LOG_ERR);
								}
								$this->ducount++;
							} catch(Exception $e) {
//								echo "(Timeout)".PHP_EOL;
								$logger->log('Task \'indexingdocs\': updating document '.$document->getId().' failed', PEAR_LOG_ERR);
							}
						}
					} else {
//						echo "(Missing content)".PHP_EOL;
						$logger->log('Task \'indexingdocs\': document '.$document->getId().' misses content', PEAR_LOG_ERR);
					}
				}
			}
		}
	} /* }}} */

	public function statistics() {
		return array('folder'=>array('add'=>$this->facount, 'update'=>$this->fucount), 'document'=>array('add'=>$this->dacount, 'update'=>$this->ducount));
	}
} /* }}} */

/**
 * Class containing methods for running a scheduled task
 *
 * @author  Uwe Steinmann <uwe@steinmann.cx>
 * @package SeedDMS
 * @subpackage  core
 */
class SeedDMS_IndexingDocumentsTask extends SeedDMS_SchedulerTaskBase { /* {{{ */

	/**
	 * Run the task
	 *
	 * @param SeedDMS_SchedulerTask $task task to be executed
	 * @return boolean true if task was executed succesfully, otherwise false
	 */
	public function execute(SeedDMS_SchedulerTask $task) {
		$dms = $this->dms;
		$logger = $this->logger;
		$fulltextservice = $this->fulltextservice;
		$taskparams = $task->getParameter();
		$folder = $dms->getRootFolder();
		$recreate = isset($taskparams['recreate']) ? $taskparams['recreate'] : false;

		if($fulltextservice) {
			if($recreate) {
				$index = $fulltextservice->Indexer(true);
				if(!$index) {
					UI::exitError(getMLText("admin_tools"),getMLText("no_fulltextindex"));
				}
			} else {
				$index = $fulltextservice->Indexer(false);
				if(!$index) {
					$index = $fulltextservice->Indexer(true);
					if(!$index) {
						UI::exitError(getMLText("admin_tools"),getMLText("no_fulltextindex"));
					}
				}
			}

			$folderprocess = new SeedDMS_Task_Indexer_Process_Folder($this, $fulltextservice, $recreate, $logger);
			call_user_func(array($folderprocess, 'process'), $folder, -1);
			$tree = new SeedDMS_FolderTree($folder, array($folderprocess, 'process'));
			$stat = $folderprocess->statistics();
			$logger->log('Task \'indexingdocs\': '.$stat['folder']['add'].' folders added, '.$stat['folder']['update'].' folders updated, '.$stat['document']['add'].' documents added, '.$stat['document']['update'].' documents updated', PEAR_LOG_INFO);
		} else {
			$logger->log('Task \'indexingdocs\': fulltext search is turned off', PEAR_LOG_WARNING);
		}

		return true;
	}

	public function getDescription() {
		return 'Indexing all new or updated documents';
	}

	public function getAdditionalParams() {
		return array(
			array(
				'name'=>'recreate',
				'type'=>'boolean',
				'description'=> 'Force recreation of index',
			)
		);
	}
} /* }}} */

/**
 * Class for processing a single folder
 *
 * SeedDMS_Task_CheckSum_Process_Folder::process() is used as a callable when
 * iterating over all folders recursively.
 */
class SeedDMS_Task_CheckSum_Process_Folder { /* {{{ */
	public function __construct() { /* {{{ */
	} /* }}} */

	public function process($folder) { /* {{{ */
		$dms = $folder->getDMS();
		$documents = $folder->getDocuments();
		if($documents) {
			foreach($documents as $document) {
				$versions = $document->getContent();
				foreach($versions as $version) {
					if(file_exists($dms->contentDir.$version->getPath())) {
						$checksum = SeedDMS_Core_File::checksum($dms->contentDir.$version->getPath());
						if($checksum != $version->getChecksum()) {
							echo $document->getId().':'.$version->getVersion().' wrong checksum'.PHP_EOL;
						}
					} else {
						echo $document->getId().':'.$version->getVersion().' missing content'.PHP_EOL;
					}
				}
			}
		}
	} /* }}} */
} /* }}} */

/**
 * Class containing methods for running a scheduled task
 *
 * @author  Uwe Steinmann <uwe@steinmann.cx>
 * @package SeedDMS
 * @subpackage  core
 */
class SeedDMS_CheckSumTask extends SeedDMS_SchedulerTaskBase { /* {{{ */

	/**
	 * Run the task
	 *
	 * @param SeedDMS_SchedulerTask $task task to be executed
	 * @return boolean true if task was executed succesfully, otherwise false
	 */
	public function execute(SeedDMS_SchedulerTask $task) {
		$dms = $this->dms;
		$logger = $this->logger;
		$taskparams = $task->getParameter();
		$folder = $dms->getRootFolder();

		$folderprocess = new SeedDMS_Task_CheckSum_Process_Folder();
		$tree = new SeedDMS_FolderTree($folder, array($folderprocess, 'process'));
		call_user_func(array($folderprocess, 'process'), $folder);

		return true;
	}

	public function getDescription() {
		return 'Check all documents for a propper checksum';
	}

	public function getAdditionalParams() {
		return array(
		);
	}
} /* }}} */

/**
 * Class for processing a single folder
 *
 * SeedDMS_Task_Preview_Process_Folder::process() is used as a callable when
 * iterating over all folders recursively.
 */
class SeedDMS_Task_Preview_Process_Folder { /* {{{ */
	protected $logger;

	protected $previewer;

	protected $widths;

	public function __construct($previewer, $widths, $logger) { /* {{{ */
		$this->logger = $logger;
		$this->previewer = $previewer;
		$this->widths = $widths;
	} /* }}} */

	public function process($folder) { /* {{{ */
		$dms = $folder->getDMS();
		$documents = $folder->getDocuments();
		if($documents) {
		foreach($documents as $document) {
				$versions = $document->getContent();
				foreach($versions as $version) {
					foreach($this->widths as $previewtype=>$width) {
						if($previewtype == 'detail' || $document->isLatestContent($version->getVersion())) {
							$isnew = null;
							if($this->previewer->createPreview($version, $width, $isnew)) {
								if($isnew){
									$this->logger->log('Task \'preview\': created preview ('.$width.'px) for document '.$document->getId().':'.$version->getVersion(), PEAR_LOG_INFO);
								}
							}
						}
					}
				}
				$files = $document->getDocumentFiles();
				foreach($files as $file) {
					$this->previewer->createPreview($file, $width, $isnew);
					if($isnew){
						$this->logger->log('Task \'preview\': created preview ('.$width.'px) for attachment of document '.$document->getId().':'.$file->getId(), PEAR_LOG_INFO);
					}
				}
			}
		}
	} /* }}} */
} /* }}} */

/**
 * Class containing methods for running a scheduled task
 *
 * @author  Uwe Steinmann <uwe@steinmann.cx>
 * @package SeedDMS
 * @subpackage  core
 */
class SeedDMS_PreviewTask extends SeedDMS_SchedulerTaskBase { /* {{{ */

	/**
	 * Run the task
	 *
	 * @param SeedDMS_SchedulerTask $task task to be executed
	 * @return boolean true if task was executed succesfully, otherwise false
	 */
	public function execute(SeedDMS_SchedulerTask $task) {
		$dms = $this->dms;
		$logger = $this->logger;
		$settings = $this->settings;
		$conversionmgr = $this->conversionmgr;
		$taskparams = $task->getParameter();
		$folder = $dms->getRootFolder();

		$previewer = new SeedDMS_Preview_Previewer($settings->_cacheDir);
		$logger->log('Task \'previewer\': '.($conversionmgr ? 'has conversionmgr' : 'has not conversionmgr'), PEAR_LOG_INFO);
		if($conversionmgr) {
			$fromservices = $conversionmgr->getServices();
			foreach($fromservices as $from=>$toservices)
				foreach($toservices as $to=>$services)
					foreach($services as $service)
						$logger->log($from.'->'.$to.' : '.get_class($service), PEAR_LOG_DEBUG);
			$previewer->setConversionMgr($conversionmgr);
		} else
			$previewer->setConverters(isset($settings->_converters['preview']) ? $settings->_converters['preview'] : array());

		$folderprocess = new SeedDMS_Task_Preview_Process_Folder($previewer, array('list'=>$settings->_previewWidthList, 'detail'=>$settings->_previewWidthDetail), $logger);
		$tree = new SeedDMS_FolderTree($folder, array($folderprocess, 'process'));
		call_user_func(array($folderprocess, 'process'), $folder);

		return true;
	}

	public function getDescription() {
		return 'Check all documents for a missing preview image';
	}

	public function getAdditionalParams() {
		return array(
		);
	}
} /* }}} */

/**
 * Class containing methods for running a scheduled task
 *
 * @author  Uwe Steinmann <uwe@steinmann.cx>
 * @package SeedDMS
 * @subpackage  core
 */
class SeedDMS_CalendarTask extends SeedDMS_SchedulerTaskBase { /* {{{ */

	/**
	 * Run the task
	 *
	 * @param SeedDMS_SchedulerTask $task task to be executed
	 * @return boolean true if task was executed succesfully, otherwise false
	 */
	public function execute(SeedDMS_SchedulerTask $task) {
		$dms = $this->dms;
		$user = $this->user;
		$logger = $this->logger;
		$settings = $this->settings;
		$taskparams = $task->getParameter();
		$tableformat = " %-10s %5d %-60s";
		$tableformathead = " %-10s %5s %-60s";
		$tableformathtml = "<tr><td>%s</td><td>%d</td><td>%s</td></tr>";
		$tableformatheadhtml = "<tr><th>%s</th><th>%s</th><th>%s</th></tr>";

		require_once('inc/inc.ClassEmailNotify.php');
		require_once('inc/inc.ClassCalendar.php');
		$email = new SeedDMS_EmailNotify($dms, $settings->_smtpSendFrom, $settings->_smtpServer, $settings->_smtpPort, $settings->_smtpUser, $settings->_smtpPassword);

		$calendar = new SeedDMS_Calendar($dms->getDB(), null);
		$allusers = $dms->getAllUsers();
		foreach($allusers as $auser) {
			if(!$auser->isAdmin() && !$auser->isGuest() && !$auser->isDisabled() && $auser->getEmail()) {
				$body = ''.$auser->getLogin()." <".$auser->getEmail().">\n\n";
				$bodyhtml = '<p>'.$auser->getLogin()." &lt;".$auser->getEmail()."&gt;</p>";
				$calendar->setUser($auser);
				$start = mktime(0,0,0, date('m'), date('d'), date('Y'));
				$events = $calendar->getEventsInInterval($start, $start+7*86400);
				if($events && count($events)>0) {
					$bodyhtml .= "<table>".PHP_EOL;
					$bodyhtml .= sprintf($tableformatheadhtml."\n", getMLText("date", array(), ""), "ID", getMLText("name", array(), ""));
					$body .= sprintf($tableformathead."\n", getMLText("expires", array(), ""), "ID", getMLText("name", array(), ""));
					$body .= "---------------------------------------------------------------------------------\n";
					foreach($events as $event) {
						$body .= sprintf($tableformat."\n", getReadableDate($event['start']), 1, $event['name']);
						$bodyhtml .= sprintf($tableformathtml."\n", getReadableDate($event['start']), 1, $event['name']);
					}
					$bodyhtml .= "</table>".PHP_EOL;
					$params = array();
					$params['count'] = count($events);
					$params['__body__'] = $body;
					$params['__body_html__'] = $bodyhtml;
					$params['sitename'] = $settings->_siteName;
					$email->toIndividual('', $auser, 'calendar_events_mail_subject', '', $params);

					$logger->log('Task \'calendar_events\': Sending reminder \'calender_events_mail_subject\' to user \''.$auser->getLogin().'\'', PEAR_LOG_INFO);
				}
			}
		}

		return true;
	}

	public function getDescription() {
		return 'Check calendar for upcoming events';
	}

	public function getAdditionalParams() {
		return array(
		);
	}
} /* }}} */

$GLOBALS['SEEDDMS_SCHEDULER']['tasks']['core']['expireddocs'] = 'SeedDMS_ExpiredDocumentsTask';
$GLOBALS['SEEDDMS_SCHEDULER']['tasks']['core']['indexingdocs'] = 'SeedDMS_IndexingDocumentsTask';
$GLOBALS['SEEDDMS_SCHEDULER']['tasks']['core']['checksum'] = 'SeedDMS_CheckSumTask';
$GLOBALS['SEEDDMS_SCHEDULER']['tasks']['core']['preview'] = 'SeedDMS_PreviewTask';
$GLOBALS['SEEDDMS_SCHEDULER']['tasks']['core']['calendar'] = 'SeedDMS_CalendarTask';
