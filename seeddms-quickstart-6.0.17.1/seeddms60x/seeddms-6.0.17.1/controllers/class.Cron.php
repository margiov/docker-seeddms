<?php
/**
 * Implementation of Cron controller
 *
 * @category   DMS
 * @package    SeedDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010-2020 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Class which does the busines logic for the regular cron job
 *
 * @category   DMS
 * @package    SeedDMS
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010-2020 Uwe Steinmann
 * @version    Release: @package_version@
 */
class SeedDMS_Controller_Cron extends SeedDMS_Controller_Common {

	public function run() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$settings = $this->params['settings'];
		$mode = $this->params['mode'];
		$db = $dms->getDb();

		$scheduler = new SeedDMS_Scheduler($db);
		$tasks = $scheduler->getTasks();

		foreach($tasks as $task) {
			if(isset($GLOBALS['SEEDDMS_SCHEDULER']['tasks'][$task->getExtension()]) && is_object($taskobj = resolveTask($GLOBALS['SEEDDMS_SCHEDULER']['tasks'][$task->getExtension()][$task->getTask()]))) {
				switch($mode) {
				case "run":
				case "dryrun":
					if(method_exists($taskobj, 'execute')) {
            if(!$task->getDisabled() && $task->isDue()) {
							if($mode == 'run') {
								/* Schedule the next run right away to prevent a second execution
								 * of the task when the cron job of the scheduler is called before
								 * the last run was finished. The task itself can still be scheduled
								 * to fast, but this is up to the admin of seeddms.
								 */
								$task->updateLastNextRun();
								if($taskobj->execute($task)) {
									add_log_line("Execution of task ".$task->getExtension()."::".$task->getTask()." successful.");
								} else {
									add_log_line("Execution of task ".$task->getExtension()."::".$task->getTask()." failed, task has been disabled.", PEAR_LOG_ERR);
									$task->setDisabled(1);
								}
							} elseif($mode == 'dryrun') {
								echo "Running ".$task->getExtension()."::".$task->getTask()." in dry mode\n";
							}
            }
					}
					break;
				}
			}
		}

		return true;
	} /* }}} */
}

