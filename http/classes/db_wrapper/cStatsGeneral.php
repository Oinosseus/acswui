<?php

// helper variable to calculate rankings
$__DriverRankingArray__ = array();



/**
 * Cached wrapper to database DriverRanking table element
 */
class StatsGeneral {
    private $Id = NULL;
    private $Timestamp = NULL;
    private $LastScannedLap = NULL;
    private $LastScannedColCar = NULL;
    private $LastScannedColEnv = NULL;
    private $LapsValid = NULL;
    private $LapsInvalid = NULL;
    private $MetersValid = NULL;
    private $MetersInvalid = NULL;
    private $SecondsValid = NULL;
    private $SecondsInvalid = NULL;
    private $Cuts = NULL;
    private $CollisionsCar = NULL;
    private $CollisionsEnvironment = NULL;


    /**
     * Create a StatsGeneral object
     * When the $id parameter is 0, it is assumed that a new object wants to be created.
     * @param $id The Id of the database table row (or 0 for a new object)
     * @param $json_data When not NULL, this constructs a StatsGeneral object from json structed object data (other parameters will be ignored)
     */
    public function __construct(int $id) {
        $this->Id = $id;
    }


    /**
     * Calculates current stats and store them in database
     * @return A new (unsaved) StatsGeneral object
     */
    public static function calculateStats() {
        global $acswuiDatabase;

        // initialize data
        $last_stats = StatsGeneral::latest();
        $new_stats = new StatsGeneral(0);
        $new_stats->Timestamp = new DateTime();
        $new_stats->LastScannedLap = $last_stats->lastScannedLapId();
        $new_stats->LastScannedColCar = $last_stats->lastScannedColCar();
        $new_stats->LastScannedColEnv = $last_stats->lastScannedColEnv();
        $new_stats->LapsValid = $last_stats->lapsValid();
        $new_stats->LapsInvalid = $last_stats->lapsInvalid();
        $new_stats->MetersValid = $last_stats->metersValid();
        $new_stats->MetersInvalid = $last_stats->metersInvalid();
        $new_stats->SecondsValid = $last_stats->secondsValid();
        $new_stats->SecondsInvalid = $last_stats->secondsInvalid();
        $new_stats->Cuts = $last_stats->cuts();
        $new_stats->CollisionsCar = $last_stats->collisionsCar();
        $new_stats->CollisionsEnvironment = $last_stats->collisionsEnvironment();

        // scan new laps
        $last_id = $new_stats->LastScannedLap;
        $query = "SELECT Id FROM Laps WHERE Id > $last_id ORDER BY Id ASC;";
        $res = $acswuiDatabase->fetch_raw_select($query);
        foreach($res as $row) {
            $lap = new Lap($row['Id']);

            $new_stats->LastScannedLap = $lap->id();
            $new_stats->Cuts += $lap->cuts();
            $new_stats->CollisionsCar = 0;

            if ($lap->cuts() == 0) {
                $new_stats->LapsValid += 1;
                $new_stats->SecondsValid += $lap->laptime() / 1e3;
                $new_stats->MetersValid += $lap->session()->track()->length();
            } else {
                $new_stats->LapsInvalid += 1;
                $new_stats->SecondsInvalid += $lap->laptime() / 1e3;
                $new_stats->MetersInvalid += $lap->session()->track()->length();
            }
        }

        // scan collisions environment
        $last_id = $new_stats->LastScannedColEnv;
        $query = "SELECT Id FROM CollisionEnv WHERE Id > $last_id ORDER BY Id ASC;";
        $res = $acswuiDatabase->fetch_raw_select($query);
        foreach($res as $row) {
            $last_id = $row['Id'];
            $new_stats->CollisionsEnvironment += 1;
        }
        $new_stats->LastScannedColEnv = $last_id;

        // scan collisions car
        $last_id = $new_stats->LastScannedColCar;
        $query = "SELECT Id FROM CollisionCar WHERE Id > $last_id ORDER BY Id ASC;";
        $res = $acswuiDatabase->fetch_raw_select($query);
        foreach($res as $row) {
            $last_id = $row['Id'];
            $new_stats->CollisionsCar += 1;
        }
        $new_stats->LastScannedColCar = $last_id;

        // done
        return $new_stats;
    }


    //! @return Number of cuts
    public function cuts() {
        if ($this->Cuts === NULL) $this->updateFromDb();
        return $this->Cuts;
    }


    //! @return Number of collisions with other cars
    public function collisionsCar() {
        if ($this->CollisionsCar === NULL) $this->updateFromDb();
        return $this->CollisionsCar;
    }


    //! @return Number of collisions with environment
    public function collisionsEnvironment() {
        if ($this->CollisionsEnvironment === NULL) $this->updateFromDb();
        return $this->CollisionsEnvironment;
    }


    //! @return The db Id of the last scanned car collision
    public function lastScannedColCar() {
        if ($this->LastScannedColCar === NULL) $this->updateFromDb();
        return $this->LastScannedColCar;
    }


    //! @return The db Id of the last scanned car collision
    public function lastScannedColEnv() {
        if ($this->LastScannedColEnv === NULL) $this->updateFromDb();
        return $this->LastScannedColEnv;
    }


    //! @return The db Id of the last scanned lap
    public function lastScannedLapId() {
        if ($this->LastScannedLap === NULL) $this->updateFromDb();
        return $this->LastScannedLap;
    }


    //! @return Number of valid driven laps (no cuts)
    public function lapsValid() {
        if ($this->LapsValid === NULL) $this->updateFromDb();
        return $this->LapsValid;
    }


    //! @return Number of invalid driven laps (with cuts)
    public function lapsInvalid() {
        if ($this->LapsInvalid === NULL) $this->updateFromDb();
        return $this->LapsInvalid;
    }


    //! @return The latest StatsGeneral from the database (creates a new StatsGeneral obeject if no db entry exists)
    public static function latest() {
        global $acswuiDatabase;

        $query = "SELECT Id FROM StatsGeneral ORDER BY Id DESC LIMIT 1";
        $res = $acswuiDatabase->fetch_raw_select($query);

        if (count($res) == 0) {
            $stats = new StatsGeneral(0);
            $stats->Timestamp = new DateTime();
            $stats->LastScannedLap = 0;
            $stats->LastScannedColCar = 0;
            $stats->LastScannedColEnv = 0;
            $stats->LapsValid = 0;
            $stats->LapsInvalid = 0;
            $stats->MetersValid = 0;
            $stats->MetersInvalid = 0;
            $stats->SecondsValid = 0;
            $stats->SecondsInvalid = 0;
            $stats->Cuts = 0;
            $stats->CollisionsCar = 0;
            $stats->CollisionsEnvironment = 0;
        } else {
            $stats = new StatsGeneral($res[0]['Id']);
        }

        return $stats;
    }


    //! @return Number of valid driven length [m] (no cuts)
    public function metersValid() {
        if ($this->MetersValid === NULL) $this->updateFromDb();
        return $this->MetersValid;
    }


    //! @return Number of invalid driven length [m] (with cuts)
    public function metersInvalid() {
        if ($this->MetersInvalid === NULL) $this->updateFromDb();
        return $this->MetersInvalid;
    }


    //! This function works only for new stats (which are not already in database)
    public function save() {
        global $acswuiLog;
        global $acswuiDatabase;

        if ($this->Id !== 0) {
            $acswuiLog->logError("Not allowed to set user on existing item!");
            return;
        }

        // db fields
        $columns = array();
        $columns['Timestamp'] = $this->Timestamp->format("Y-m-d H:i:s");
        $columns['LastScannedLap'] = $this->LastScannedLap;
        $columns['LastScannedColCar'] = $this->LastScannedColCar;
        $columns['LastScannedColEnv'] = $this->LastScannedColEnv;
        $columns['LapsValid'] = $this->LapsValid;
        $columns['LapsInvalid'] = $this->LapsInvalid;
        $columns['MetersValid'] = $this->MetersValid;
        $columns['MetersInvalid'] = $this->MetersInvalid;
        $columns['SecondsValid'] = $this->SecondsValid;
        $columns['SecondsInvalid'] = $this->SecondsInvalid;
        $columns['Cuts'] = $this->Cuts;
        $columns['CollisionsCar'] = $this->CollisionsCar;
        $columns['CollisionsEnvironment'] = $this->CollisionsEnvironment;

        // save
        $this->Id = $acswuiDatabase->insert_row("StatsGeneral", $columns);
    }


    //! @return Number of valid driven length [s] (no cuts)
    public function secondsValid() {
        if ($this->SecondsValid === NULL) $this->updateFromDb();
        return $this->SecondsValid;
    }


    //! @return Number of invalid driven length [s] (with cuts)
    public function secondsInvalid() {
        if ($this->SecondsInvalid === NULL) $this->updateFromDb();
        return $this->SecondsInvalid;
    }


    //! @return Timestamp of the static data
    public function timestamp() {
        if ($this->Timestamp === NULL) $this->updateFromDb();
        return $this->Timestamp;
    }


    //! load db entry
    private function updateFromDb() {
        global $acswuiDatabase;
        global $acswuiLog;

        // db columns
        $columns = array();
        $columns[] = 'Id';
        $columns[] = 'Timestamp';
        $columns[] = 'LastScannedLap';
        $columns[] = 'LastScannedColCar';
        $columns[] = 'LastScannedColEnv';
        $columns[] = 'LapsValid';
        $columns[] = 'LapsInvalid';
        $columns[] = 'MetersValid';
        $columns[] = 'MetersInvalid';
        $columns[] = 'SecondsValid';
        $columns[] = 'SecondsInvalid';
        $columns[] = 'Cuts';
        $columns[] = 'CollisionsCar';
        $columns[] = 'CollisionsEnvironment';

        // request from db
        $res = $acswuiDatabase->fetch_2d_array("StatsGeneral", $columns, ['Id'=>$this->Id]);
        if (count($res) !== 1) {
            $acswuiLog->logError("Cannot find StatsGeneral.Id=" . $this->Id);
            return;
        }

//         $this->Id = NULL;
        $this->Timestamp = new DateTime($res[0]['Timestamp']);
        $this->LastScannedLap = (int) $res[0]['LastScannedLap'];
        $this->LastScannedColCar = (int) $res[0]['LastScannedColCar'];
        $this->LastScannedColEnv = (int) $res[0]['LastScannedColEnv'];
        $this->LapsValid = (int) $res[0]['LapsValid'];
        $this->LapsInvalid = (int) $res[0]['LapsInvalid'];
        $this->MetersValid = (int) $res[0]['MetersValid'];
        $this->MetersInvalid = (int) $res[0]['MetersInvalid'];
        $this->SecondsValid = (int) $res[0]['SecondsValid'];
        $this->SecondsInvalid = (int) $res[0]['SecondsInvalid'];
        $this->Cuts = (int) $res[0]['Cuts'];
        $this->CollisionsCar = (int) $res[0]['CollisionsCar'];
        $this->CollisionsEnvironment = (int) $res[0]['CollisionsEnvironment'];
    }
}

?>
