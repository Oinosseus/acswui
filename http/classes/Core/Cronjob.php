<?php

namespace Core;

// class CronjobRepeat {
//     public $TenMinutes = 1;
//     public $ThirtyMinutes = 2;
//     public $Daily = 3;
// }

/**
 * Base class for cronjobs
 *
 * GET variables:
 *   VERBOSE - Set to make verbose output
 *   FORCE=CronjobName - Set to force a cronjob to run (if not already running)
 */
abstract class Cronjob {

    // execution intervals
    public const IntervalAlways = 0; //! Execute cronjob everytime (works not in combination with other intervals)
    public const IntervalLap = 1;  // execute cronjob after new laps have been driven
    public const IntervalSession = 2;  //! Execute cronjob after sessions have been closed
    public const IntervalDaily = 4;  //! Execute cronjob very day
    public const IntervalMonthly = 8;  //! Execute cronjob at certain days in a month

    // cronjob states
    public const StatusReady = 1;
    public const StatusWaiting = 2;
    public const StatusBlocked = 4;

    // static caches
    private static $LastCompletedLap = NULL;


    // internal status variables
    private $HasBeenProcessed = False;
    private $ExecutionInterval = 0;
    private $ExecutionIntervalMonthly = NULL;
    private $Status = NULL;
    private $LastExecutionTimestamp = NULL;
    private $LastExecutedCompletedSession = 0;
    private $LastExecutedFinishedSession = 0;
    private $LastExecutedLap = 0;
    private $LastExecutionDuration = 0;
    private $StatusFilePath = NULL;
    private $CustomData = array();
    private $LockedSemaphore = NULL;


    /**
     * This constructor must be called by derived classed.
     * @param $execution_interval Use bitwise OR combination of Cronjob::Interval** constants
     * @param $monthly_cycle If IntervalMonthly is set, this must be given to define the cycle
     * @param $read_only_access If this is TRUE (default), then the Cronjob cannot be executed (reading status only).
     */
    public function __construct(int $execution_interval,
                                \Parameter\ParamEnumMonthly $monthly_cycle = NULL,
                                bool $read_only_access = TRUE) {
        $this->ExecutionInterval = $execution_interval;
        $this->LastExecutionTimestamp = new \DateTime("0000-00-00 00:00");
        $this->StatusFilePath = \Core\Config::AbsPathData . "/htcache/cronjobs/" . $this->name() . ".json";

        // ensure status file existence
        if (!file_exists($this->StatusFilePath)) touch($this->StatusFilePath);

        // retrieve monthly interval
        if ($execution_interval & Cronjob::IntervalMonthly) {
            if ($monthly_cycle === NULL) {
                \Core\Log::error("Parameter 'monthly_cycle' must not be NULL, when IntervalMonthly is set!");
                $this->ExecutionIntervalMonthly = array();
            } else {
                $this->ExecutionIntervalMonthly = $monthly_cycle;
            }
        }

        // check for unique access
        $sem_key = ftok($this->StatusFilePath, "X");
        $this->LockedSemaphore = sem_get($sem_key);
        if ($this->LockedSemaphore === FALSE) {
            \Core\Log::error("Could not retrieve sempahore for $this");
            $this->LockedSemaphore = NULL;
        } else {
            if (sem_acquire($this->LockedSemaphore, TRUE) !== TRUE) {
                $this->LockedSemaphore = NULL;
            }
        }

        // load status file data
        $data = json_decode(file_get_contents($this->StatusFilePath), TRUE);
        if ($data && array_key_exists("Status", $data)) {
            $data_status = $data['Status'];
            if (array_key_exists("LastExecutedLap", $data_status)) $this->LastExecutedLap = $data_status['LastExecutedLap'];
            if (array_key_exists("LastExecutedCompletedSession", $data_status)) $this->LastExecutedCompletedSession = $data_status['LastExecutedCompletedSession'];
            if (array_key_exists("LastExecutedFinishedSession", $data_status)) $this->LastExecutedFinishedSession = $data_status['LastExecutedFinishedSession'];
            if (array_key_exists("LastExecutedTimestamp", $data_status)) $this->LastExecutionTimestamp = new \DateTime($data_status['LastExecutedTimestamp']);
        }
        if ($data && array_key_exists("Statistics", $data)) {
            $data_stats = $data['Statistics'];
            if (array_key_exists("LastExecutionDuration", $data_stats)) $this->LastExecutionDuration = $data_stats['LastExecutionDuration'];
        }
        if ($data && array_key_exists("CustomData", $data)) {
            $this->CustomData = $data['CustomData'];
            if (!is_array($this->CustomData)) $this->CustomData = array();
        }
    }


    public function __destruct() {
        if ($this->HasBeenProcessed) {

            // check for programming failures
            if ($this->LockedSemaphore === NULL) {
                \Core\Log::error("Cronjob '" . $this->name() . "' has been processsed without Lock!");
            } else {

                // prepare data
                $data = array();
                $data['Status'] = array();
                $data['Status']['LastExecutedLap'] = Cronjob::lastCompletedLap();
                $data['Status']['LastExecutedCompletedSession'] = \DbEntry\Session::fromLastCompleted()->id();
                $data['Status']['LastExecutedFinishedSession'] = \DbEntry\Session::fromLastFinished()->id();
                $data['Status']['LastExecutedTimestamp'] = $this->LastExecutionTimestamp->format("c");
                $data['Statistics'] = array();
                $data['Statistics']['LastExecutionDuration'] = round($this->LastExecutionDuration, 3);
                $data['CustomData'] = $this->CustomData;

                // save satus file
                $f = fopen($this->StatusFilePath, "w");
                fwrite($f, json_encode($data, JSON_PRETTY_PRINT));
                fclose($f);
            }
        }

        if ($this->LockedSemaphore) {
            sem_release($this->LockedSemaphore);
            $this->LockedSemaphore = NULL;
        }
    }


    //! @return The string representation
    public function __toString() {
        return "CronJob[{$this->name()}]";
    }


    /**
     * Shall be called periodically to automatically execute cronjobs.
     * Execution Intervall recommendation: each 10 seconds, aligned to Minutes
     *
     * It is ensured, that cronjobs are executes only once
     * (prevent same cronjob running multiple times in parallel)
     */
    public static function checkExecute() {
        foreach (Cronjob::listCronjobNames() as $cj_name) {

            // get object
            $cj = Cronjob::fromName($cj_name);

            // user info
            Cronjob::verboseOutput("<h1>" .$cj->name() . "</h1>");

            Cronjob::verboseOutput("Status: " . Cronjob::status2str($cj->status()) . "<br>");
            CronJob::verboseOutput(sprintf("Last Duration: %0.2f s<br>", $cj->LastExecutionDuration));

            if ($cj->status() == Cronjob::StatusReady) {

                $cj->LastExecutionTimestamp = new \DateTime("now");
                $duration_start = microtime(TRUE);
                Cronjob::verboseOutput("<div style=\"margin-left:2em; font-size:0.9em;\">");
                $cj->process();
                Cronjob::verboseOutput("</div>");
                $cj->HasBeenProcessed = TRUE;
                $duration_end = microtime(TRUE);

                $cj->LastExecutionDuration = $duration_end - $duration_start;

                CronJob::verboseOutput(sprintf("Duration: %0.2f s<br>", $cj->LastExecutionDuration));
            }

            $cj = NULL; // explicitly destruct Cronjob to unlock
        }
    }


    //! @return A cronjob object (or NULL)
    public static function fromName(string $cronjob_name) {
        $cronjob_class = "\\Cronjobs\\$cronjob_name";
        $cronjob = new $cronjob_class();
        return $cronjob;
    }


    //! @return A string informing about the execution interval
    public function intervalStr() {
        $intervals = array();

        if ($this->ExecutionInterval == Cronjob::IntervalAlways) {
            $intervals[] = _("Always");
        }

        if ($this->ExecutionInterval & Cronjob::IntervalLap) {
            $intervals[] = _("After each Lap");
        }

        if ($this->ExecutionInterval & Cronjob::IntervalSession) {
            $intervals[] = _("After each Session");
        }

        if ($this->ExecutionInterval & Cronjob::IntervalDaily) {
            $intervals[] = _("Once a Day");
        }

        if ($this->ExecutionInterval & Cronjob::IntervalMonthly) {
            $intervals[] = $this->ExecutionIntervalMonthly->valueLabel();
        }

        return implode(", ", $intervals);
    }



    //! @return the Id of the last completed Lap
    public static function lastCompletedLap() {
        if (Cronjob::$LastCompletedLap === NULL) {
                $query = "SELECT Id FROM Laps ORDER BY Id DESC LIMIT 1;";
                $res = \Core\Database::fetchRaw($query);
                if (count($res) > 0) Cronjob::$LastCompletedLap = (int) $res[0]['Id'];
                else Cronjob::$LastCompletedLap = 0;
        }
        return Cronjob::$LastCompletedLap;
    }


    //! @return The duration of the last execution of this cronjob [seconds]
    public function lastExecutionDuration() {
        return $this->LastExecutionDuration;
    }


    //! @return A DateTime object of the last execution of this cronjob
    public function lastExecutionTimestamp() {
        return $this->LastExecutionTimestamp;
    }


    //! @return An array with names of available cronjobs
    public static function listCronjobNames() {
        $cronjobs = array();

        // scan cronjobs
        foreach (scandir("classes/Cronjobs", SCANDIR_SORT_ASCENDING) as $entry) {

            // only care for php files
            if (substr($entry, -4, 4) != ".php") continue;

            # include cronjon php module
            $cron_classname = substr($entry, 0, strlen($entry)-4);
            $cronjobs[] = $cron_classname;
        }

        return $cronjobs;
    }


    /**
     * This can be used by derived cronjobs to save data between executions.
     * @param $key An arbitrary identifier
     * @param $default_value This will be returned if $key could not be found
     * @return The requested value
     */
    protected function loadData(string $key, $default_value=NULL) {
        if (array_key_exists($key, $this->CustomData)) return $this->CustomData[$key];
        else return $default_value;
    }


    //! @return The (class) name of the cronjob
    public function name() {
        return substr(get_class($this), 9);
    }


    /**
     * Must be implemented by each cronjob.
     * This acutally is doing the work.
     */
    abstract protected function process();


    /**
     * This can be used by derived cronjobs to store arbitrary data between executions
     */
    protected function saveData(string $key, $value) {
        $this->CustomData[$key] = $value;
    }


    //! @return The current status of the Cronjob (see Cronjob::Status**)
    public function status() {
        if ($this->Status === NULL) {

            // always ready
            if ($this->ExecutionInterval == CronJob::IntervalAlways) {
                $this->Status = Cronjob::StatusReady;
            }

            // ready once a day
            if ($this->ExecutionInterval & Cronjob::IntervalDaily) {

                // determine ideal start time for daily cronjobs
                $ideal_time = new \DateTime("now");
                $ideal_time->setTime(0, 0);
                $t = explode(":", \Core\ACswui::getPAram("CronjobDailyExecutionTime"));
                $ideal_time->add(new \DateInterval(sprintf("PT%sH%sM", $t[0], $t[1])));

                // get last execution time
                $last_time = $this->LastExecutionTimestamp;

                // get current time
                $now_time = new \DateTime("now");

                // check if ready
                if ($now_time > $ideal_time) {
                    if ($last_time < $ideal_time) $this->Status = Cronjob::StatusReady;
                }
            }


            // ready once a month
            if ($this->ExecutionInterval & Cronjob::IntervalMonthly) {
                $month_keys = $this->ExecutionIntervalMonthly->valueList();
                $now = new \DateTime("now");
                $day_in_month = $now->format("j");
                $count_weekdays_in_month = ceil($day_in_month / 7);  // how many often is this day present in month
                $day_name3 = $now->format("D");
                $even_odd = (($now->format("W") % 2) == 0) ? "Even" : "Odd";

                // check if today is the requested day
                $is_requested_day = FALSE;
                if (in_array($day_in_month, $month_keys)) {  // check specific day
                    $is_requested_day = TRUE;
                }
                if (in_array($count_weekdays_in_month . $day_name3, $month_keys)) {  // check x-th day in month (eg 4Mon, 3Tue)
                    $is_requested_day = TRUE;
                }
                if (in_array($day_name3 . $even_odd, $month_keys)) {  // check bi-weekly (eg. FriEven, SunOdd)
                    $is_requested_day = TRUE;
                }

                // determine ideal start time for daily cronjobs
                if ($is_requested_day) {
                    $ideal_time = new \DateTime("now");
                    $ideal_time->setTime(0, 0);
                    $t = explode(":", \Core\ACswui::getPAram("CronjobDailyExecutionTime"));
                    $ideal_time->add(new \DateInterval(sprintf("PT%sH%sM", $t[0], $t[1])));

                    // get last execution time
                    $last_time = $this->LastExecutionTimestamp;

                    // get current time
                    $now_time = new \DateTime("now");

                    // check if ready
                    if ($now_time > $ideal_time) {
                        if ($last_time < $ideal_time) $this->Status = Cronjob::StatusReady;
                    }
                }
            }


            // ready, when any new lap is available
            if ($this->ExecutionInterval & Cronjob::IntervalLap) {
                if (Cronjob::lastCompletedLap() > $this->LastExecutedLap) {
                    $this->Status = Cronjob::StatusReady;
                }
            }


            // ready, when any new session is available
            if ($this->ExecutionInterval & Cronjob::IntervalSession) {
                if (\DbEntry\Session::fromLastCompleted()->id() > $this->LastExecutedCompletedSession) {
                    $this->Status = Cronjob::StatusReady;
                }
                if (\DbEntry\Session::fromLastFinished()->id() > $this->LastExecutedFinishedSession) {
                    $this->Status = Cronjob::StatusReady;
                }
            }


            // force run
            if (array_key_exists("FORCE", $_GET)) {
                if ($_GET['FORCE'] == $this->name()) {
                    if (\Core\UserManager::permitted("Cronjobs_Force")) {
                        $this->Status = Cronjob::StatusReady;
                    } else {
                        $uid = \Core\UserManager::currentUser()->id();
                        $cname = $this->name();
                        \Core\Log::warning("Denied user $uid to force-run cronjob '$cname'.");
                    }
                } else {
                    $this->Status = Cronjob::StatusWaiting;
                }
            }


            // status is 'waiting' when no previous activation was found
            if ($this->Status == CronJob::StatusReady && $this->LockedSemaphore === NULL) {
                $this->Status = Cronjob::StatusBlocked;
            } else if ($this->Status === NULL) {
                $this->Status = Cronjob::StatusWaiting;
            }
        }

        return $this->Status;
    }


    //! Converts a Cronjob::Status** constant to a representative string
    public static function status2str($status) {
        switch ($status) {
            case Cronjob::StatusReady:
                return "Ready";
                break;

            case Cronjob::StatusBlocked:
                return "Blocked";
                break;

            case Cronjob::StatusWaiting:
                return "Waiting";
                break;

            default:
                \Core\Log::error("Undefined status: '$status'!");
                return "";
        }
    }


    /**
     * Outputs HTML if VERBOSE was given as GET variable
     */
    protected static function verboseOutput($html) {
        if (array_key_exists("VERBOSE", $_GET)) {
            echo $html;
        }
    }
}
