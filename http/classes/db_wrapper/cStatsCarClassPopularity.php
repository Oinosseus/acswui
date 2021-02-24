<?php

$_StatsCarClassPopularityCarClassHash = NULL;

/**
 * Cached wrapper to database StatsCarClassPopularity table element
 */
class StatsCarClassPopularity {
    private $Id = NULL;
    private $Timestamp = NULL;
    private $CarClass = NULL;
    private $LastScannedLap = NULL;
    private $LapCount = NULL;
    private $TimeCount = NULL;
    private $MeterCount = NULL;
    private $Popularity = NULL;


    /**
     * Create a StatsCarClassPopularityStatsCarClassPopularity object
     * When the $id parameter is 0, it is assumed that a new object wants to be created.
     * @param $id The Id of the database table row (or 0 for a new object)
     */
    public function __construct(int $id) {
        $this->Id = $id;
    }


    /**
     * Calculate latest track popularities
     * @return An array of (unsaved) StatsCarClassPopularity objects.
     */
    public static function calculatePopularities() {
        global $acswuiDatabase;

        $sccps = array();

        // scan current valid tracks
        $cache = array();
        $min_last_scanned_lap = NULL;
        foreach (CarClass::listClasses() as $carclass) {
            $cid = $carclass->id();

            $cache[$cid] = array();
            $cache[$cid]['CarClass'] = $carclass;
            $cache[$cid]['LapCount'] = 0;
            $cache[$cid]['MeterCount'] = 0;
            $cache[$cid]['TimeCount'] = 0;
            $cache[$cid]['Popularity'] = 0;
            $cache[$cid]['LastScannedLap'] = 0;

            $sccp = StatsCarClassPopularity::getLatest($carclass);
            if ($sccp != NULL) {
                $cache[$cid]['LastScannedLap'] = $sccp->lastScannedLapId();
                $cache[$cid]['LapCount'] = $sccp->lapCount();
                $cache[$cid]['TimeCount'] = $sccp->timeCount();
                $cache[$cid]['MeterCount'] = $sccp->meterCount();
            }

            if ($min_last_scanned_lap === NULL || $cache[$cid]['LastScannedLap'] < $min_last_scanned_lap) {
                $min_last_scanned_lap = $cache[$cid]['LastScannedLap'];
            }
        }
        $min_last_scanned_lap += 1;

        // scan new laps
        $most_driven_meters = 0;
        $last_scanned_lap = $min_last_scanned_lap;
        $query = "SELECT Id FROM Laps WHERE Id >= $last_scanned_lap ORDER BY Id ASC";
        $res = $acswuiDatabase->fetch_raw_select($query);
        foreach($res as $row) {
            $lap = new Lap($row['Id']);
            $lid = $lap->id();
            $last_scanned_lap = $lid;

            // update all carclasses with this lap
            foreach ($cache as $cid=>$foo) {
                $carclass = $cache[$cid]['CarClass'];

                // check if car is valid for this carclass
                if (!$carclass->validLap($lap)) continue;

                // check if lap is already scanned for this carclass
                if ($lid <= $cache[$cid]['LastScannedLap']) continue;

                // increment lapcount
                $cache[$cid]['LapCount'] += 1;
                $cache[$cid]['MeterCount'] += $lap->session()->track()->length();
                $cache[$cid]['TimeCount'] += $lap->laptime() / 1e3;
                $cache[$cid]['LastScannedLap'] = $lid;
            }
        }

        // determine most driven meters
        foreach ($cache as $cid=>$foo) {
            $meters = $cache[$cid]['MeterCount'];
            if ($meters > $most_driven_meters) $most_driven_meters = $meters;
        }

        // calculate new data, and save
        $timestamp = new Datetime();
        foreach ($cache as $cid=>$foo) {

            $cache[$cid]['Popularity'] = $cache[$cid]['MeterCount'] / $most_driven_meters;

            // save
            $sccp = new StatsCarClassPopularity(0);
            $sccp->Timestamp = $timestamp;
            $sccp->CarClass = $cache[$cid]['CarClass'];
            $sccp->LastScannedLap = $last_scanned_lap;
            $sccp->LapCount = $cache[$cid]['LapCount'];
            $sccp->TimeCount = $cache[$cid]['TimeCount'];
            $sccp->MeterCount = $cache[$cid]['MeterCount'];
            $sccp->Popularity = $cache[$cid]['Popularity'];
            $sccps[] = $sccp;
        }

        return $sccps;
    }


    //! @return The according CarClass object
    public function carClass() {
        if ($this->CarClass === NULL) $this->updateFromDb();
        return $this->CarClass;
    }


    /**
     * Compares to StatsCarClassPopularity objects for most popularity.
     * This is intended for usort() of arrays with StatsCarClassPopularity objects
     * @param $sccp1 StatsCarClassPopularity object
     * @param $sccp2 StatsCarClassPopularity object
     * @return -1 if $sccp1 is more popular, +1 when $sccp2 is more popular, 0 if both are equal
     */
    public static function comparePopularity(StatsCarClassPopularity $sccp1, StatsCarClassPopularity $sccp2) {
        if ($sccp1->popularity() < $sccp2->popularity()) return 1;
        if ($sccp1->popularity() > $sccp2->popularity()) return -1;
        return 0;
    }


    //! @return The latest StatsCarClassPopularity object for a certain CarClass (can return NULL)
    public static function getLatest(CarClass $carclass) {
        global $_StatsCarClassPopularityCarClassHash;

        if ($_StatsCarClassPopularityCarClassHash === NULL) {
            $_StatsCarClassPopularityCarClassHash = array();
            foreach (StatsCarClassPopularity::listLatest() as $sccp) {
                $_StatsCarClassPopularityCarClassHash[$sccp->carClass()->id()] = $sccp;
            }
        }

        if (array_key_exists($carclass->id(), $_StatsCarClassPopularityCarClassHash)) {
            return $_StatsCarClassPopularityCarClassHash[$carclass->id()];
        }

        return NULL;
    }


    //! @return The number of laps driven on the carclass
    public function lapCount() {
        if ($this->LapCount === NULL) $this->updateFromDb();
        return $this->LapCount;
    }


    //! @return The Id of the last Lap that counts into this statistics
    public function lastScannedLapId() {
        if ($this->LastScannedLap === NULL) $this->updateFromDb();
        return $this->LastScannedLap;
    }


    //! @return A ordered list of
    public static function listLatest() {
        global $acswuiDatabase;
        $sccps = array();

        foreach (CarClass::listClasses() as $carclass) {
            $cid = $carclass->id();
            $query = "SELECT Id FROM StatsCarClassPopularity WHERE CarClass = $cid ORDER BY Id DESC LIMIT 1";
            $res = $acswuiDatabase->fetch_raw_select($query);
            if (count($res)) {
                $sccps[] = new StatsCarClassPopularity($res[0]['Id']);
            }
        }

        usort($sccps, "StatsCarClassPopularity::comparePopularity");

        return $sccps;
    }


    //! @return The number of driven meters for this CarClass
    public function meterCount() {
        if ($this->MeterCount === NULL) $this->updateFromDb();
        return $this->MeterCount;
    }


    //! @return The popularity of a CarClass in [%]
    public function popularity() {
        if ($this->Popularity === NULL) $this->updateFromDb();
        return 100.0 * $this->Popularity;
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
        $columns['CarClass'] = $this->CarClass->id();
        $columns['LastScannedLap'] = $this->LastScannedLap;
        $columns['LapCount'] = $this->LapCount;
        $columns['TimeCount'] = $this->TimeCount;
        $columns['MeterCount'] = $this->MeterCount;
        $columns['Popularity'] = $this->Popularity;

        // save
        $this->Id = $acswuiDatabase->insert_row("StatsCarClassPopularity", $columns);
    }


    //! @return The number of seconds driven on the carclass
    public function timeCount() {
        if ($this->TimeCount === NULL) $this->updateFromDb();
        return $this->TimeCount;
    }


    //! load db entry
    private function updateFromDb() {
        global $acswuiDatabase;
        global $acswuiLog;

        // db columns
        $columns = array();
        $columns[] = 'Id';
        $columns[] = 'Timestamp';
        $columns[] = 'CarClass';
        $columns[] = 'LastScannedLap';
        $columns[] = 'LapCount';
        $columns[] = 'TimeCount';
        $columns[] = 'MeterCount';
        $columns[] = 'Popularity';

        // request from db
        $res = $acswuiDatabase->fetch_2d_array("StatsCarClassPopularity", $columns, ['Id'=>$this->Id]);
        if (count($res) !== 1) {
            $acswuiLog->logError("Cannot find StatsCarClassPopularity.Id=" . $this->Id);
            return;
        }

        $this->Timestamp = new DateTime($res[0]['Timestamp']);
        $this->CarClass = new CarCLass($res[0]['CarClass']);
        $this->LastScannedLap = (int) $res[0]['LastScannedLap'];
        $this->LapCount = (int) $res[0]['LapCount'];
        $this->TimeCount = (int) $res[0]['TimeCount'];
        $this->MeterCount = (int) $res[0]['MeterCount'];
        $this->Popularity = (float) $res[0]['Popularity'];
    }
}

?>
