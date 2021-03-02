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

    //! Absolute time when cronjob is on due
    private $NextExecutionTime = NULL;

    //! DateInterval object representing the execution period
    private $ExecutionInterval = NULL;

    //! Cronjob execution log
    private $LogString = "";

    //! Job execution duration [ms]
    private $ExecutionDuration = 0;


    /**
     * This constructor must be called by derived classed.
     * @param $execution_interval A DateInterval object that defines the cronjob execution period
     */
    public function __construct(DateInterval $execution_interval) {
        global $acswuiDatabase;


        // determine Id in CronJobs table
        $dbcols = ['Name'=>get_class($this)];
        $res = $acswuiDatabase->fetch_2d_array("CronJobs", ['Id', 'LastStart'], $dbcols);
        if (count($res) > 0) {
            $this->CronJobId = $res[0]['Id'];
            $this->LastExecution = new DateTimeImmutable($res[0]['LastStart']);
        }

        // determine next execution time
        $this->ExecutionInterval = $execution_interval;
        if ($this->LastExecution === NULL) {
            $this->NextExecutionTime = new DateTimeImmutable();
        } else {
            $this->NextExecutionTime = $this->LastExecution->add($this->ExecutionInterval);
        }

    }

    /**
     * Checks if the cronjob is due to be executed.
     * If it is due, the execute() method is called and True is returned.
     * @return True if the cronjob has been executed.
     */
    public function check_execute() {
        global $acswuiDatabase;

        $executed = FALSE;
        $this->ExecutionDuration = 0;

        // check if jobs needs execution
        $now = new DateTimeImmutable();
        if ($this->NextExecutionTime <= $now) {

            $LastExecution = $now;

            // log execution in db
            $cols = array();
            $cols['Name'] = get_class($this);
            $cols['LastStart'] = $LastExecution->format("Y-m-d H:i:s");
            $cols['LastDuration'] = 0;
            $cols['Status'] = "starting";
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
