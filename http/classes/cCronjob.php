<?php

class CronjobRepeat {
    public $TenMinutes = 1;
    public $ThirtyMinutes = 2;
    public $Daily = 3;
}

/**
 * Base class for cronjobs
 */
abstract class Cronjob {

    //! Id in CronJobs table
    private $CronJobId = NULL;

    //! DateTime object of last execution (or NULL
    private $LastExecution = NULL;

    //! The ID of the last completed session when this cronjob was executed
    private $LastSession = NULL;

    //! DateInterval object representing the execution period
    private $ExecutionInterval = NULL;

    //! If TRUE, this cronjob shall be executed after a session has been finished
    private $ExecuteAfterSession = NULL;

    //! Cronjob execution log
    private $LogString = "";

    //! Job execution duration [ms]
    private $ExecutionDuration = 0;

    //! Stores a list of all session IDs that are currently running
    private static $CurrentRunningSessions = NULL;

    //! Stores the Id of the Session, that is completed and where all previous started sessions are also completed
    private static $LowestCompletedSession = NULL;


    /**
     * This constructor must be called by derived classed.
     * @param $execution_interval A DateInterval object that defines the cronjob execution period
     * @param $AfterSession If set to TRUE (default FALSE), the cronjob will be executed after end of each session
     */
    public function __construct(DateInterval $execution_interval = NULL, bool $AfterSession = FALSE) {
        global $acswuiDatabase;

        // determine current running sessions
        if (Cronjob::$CurrentRunningSessions === NULL) {
            Cronjob::$CurrentRunningSessions = array();
            foreach (ServerSlot::listSlots() as $slot) {
                $session = $slot->currentSession();
                if ($session !== NULL) {
                    Cronjob::$CurrentRunningSessions[] = $session->id();
                }
            }
        }


        // determine latest session that is completed
        // and where no older sessions are still running
        if (Cronjob::$LowestCompletedSession === NULL) {

            // no sessions running
            if (count(Cronjob::$CurrentRunningSessions) == 0) {
                $query = "SELECT Id FROM Sessions ORDER BY Id DESC LIMIT 1";
                $res = $acswuiDatabase->fetch_raw_select($query);
                if (count($res)) {
                    Cronjob::$LowestCompletedSession = (int) $res[0]['Id'];
                } else {
                    Cronjob::$LowestCompletedSession = 0;
                }

            // sessions running
            } else {
                $min_running_session = min(Cronjob::$CurrentRunningSessions);
                $query = "SELECT Id FROM Sessions WHERE Id < $min_running_session ORDER BY Id DESC LIMIT 1";
                $res = $acswuiDatabase->fetch_raw_select($query);
                if (count($res)) {
                    Cronjob::$LowestCompletedSession = (int) $res[0]['Id'];
                } else {
                    Cronjob::$LowestCompletedSession = 0;
                }
            }

            echo "Cronjob::LowestCompletedSession=" . Cronjob::$LowestCompletedSession . "<br>";
        }


        // determine Id in CronJobs table
        $fields_where = ['Name'=>get_class($this)];
        $fields_request = ['Id', 'LastStart', 'LastSession'];
        $res = $acswuiDatabase->fetch_2d_array("CronJobs", $fields_request, $fields_where);
        if (count($res) > 0) {
            $this->CronJobId = $res[0]['Id'];
            $this->LastExecution = new DateTimeImmutable($res[0]['LastStart']);
            $this->LastSession = (int) $res[0]['LastSession'];
        }


        // define execution intervals
        $this->ExecuteAfterSession = $AfterSession;
        $this->ExecutionInterval = $execution_interval;
    }

    /**
     * Checks if the cronjob is due to be executed.
     * If it is due, the execute() method is called and True is returned.
     * @return True if the cronjob has been executed.
     */
    public function check_execute() {
        global $acswuiDatabase;

        $needs_execution = FALSE;
        $last_finished_session_id = 0;


        // check if jobs needs execution per time interval
        if ($this->ExecutionInterval !== NULL) {
            if ($this->LastExecution === NULL) {
                $needs_execution = TRUE;
            } else {
                $next_execution_time = $this->LastExecution->add($this->ExecutionInterval);
                $now = new DateTimeImmutable();
                if ($next_execution_time <= $now) {
                    $needs_execution = TRUE;
                }
            }
        }


        // check if job needs execution after sessions
        if ($this->ExecuteAfterSession === TRUE) {
            if ($this->LastSession < Cronjob::$LowestCompletedSession) {
                $needs_execution = TRUE;
            }
        }


        // execute cronjob
        $executed = FALSE;
        $this->ExecutionDuration = 0;
        if ($needs_execution === TRUE) {
            $LastExecution = new DateTimeImmutable();

            // log execution in db
            $cols = array();
            $cols['Name'] = get_class($this);
            $cols['LastStart'] = $LastExecution->format("Y-m-d H:i:s");
            $cols['LastDuration'] = 0;
            $cols['Status'] = "starting";
            $cols['LastSession'] = Cronjob::$LowestCompletedSession;
            if ($this->CronJobId === NULL) {
                $this->CronJobId = $acswuiDatabase->insert_row("CronJobs", $cols);
            } else {
                $acswuiDatabase->update_row("CronJobs", $this->CronJobId, $cols);
            }

            // execute the job
            $execution_start_mtime = microtime(true);
            $this->execute();
            $this->ExecutionDuration = round(1e3 * (microtime(true) - $execution_start_mtime));
            $executed = TRUE;

            // log execution in db
            $cols = array();
            $cols['LastDuration'] = $this->ExecutionDuration;
            $cols['Status'] = "finished";
            $acswuiDatabase->update_row("CronJobs", $this->CronJobId, $cols);
        }

        return $executed;
    }

    /**
     * This function needs to be implemented by derived cronjobs.
     * This is called to do the actual cronjob
     */
    abstract public function execute();


    //! @return This contains the duration needed to execute this job [ms]
    public function executionDuration() {
        return $this->ExecutionDuration;
    }


    //! @return The cronjob log messages in HTML format
    public function getLog() {
        return nl2br(htmlentities($this->LogString));
    }


    /**
     * Write a message to the execution log of the cronjob.
     */
    public function log(string $message) {
        global $acswuiLog;
        $acswuiLog->logNotice($message);

        if (substr($message, -1, 1) != "\n") $message .= "\n";
        $this->LogString .= $message;
    }
}

?>
