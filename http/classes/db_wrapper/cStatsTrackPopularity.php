<?php

$_StatsTrackPopularityTrackHash = NULL;

/**
 * Cached wrapper to database StatsTrackPopularity table element
 */
class StatsTrackPopularity {
    private $Id = NULL;
    private $Timestamp = NULL;
    private $LastScannedLap = NULL;
    private $Track = NULL;
    private $LapCount = NULL;
    private $Popularity = NULL;
    private $LaptimeCumulative = NULL;


    /**
     * Create a StatsTrackPopularity object
     * When the $id parameter is 0, it is assumed that a new object wants to be created.
     * @param $id The Id of the database table row (or 0 for a new object)
     */
    public function __construct(int $id) {
        $this->Id = $id;
    }


    /**
     * Calculate latest track popularities
     * @return An array of (unsaved) StatsTrackPopularity objects.
     */
    public static function calculatePopularities() {
        global $acswuiDatabase;

        $stps = array();

        // scan current valid tracks
        $track_cache = array();
        $min_last_scanned_lap = NULL;
        foreach (Track::listTracks() as $track) {
            $tid = $track->id();

            $track_cache[$tid] = array();
            $track_cache[$tid]['Track'] = $track;
            $track_cache[$tid]['LapCount'] = 0;
            $track_cache[$tid]['Meters'] = 0;
            $track_cache[$tid]['LastScannedLap'] = 0;
            $track_cache[$tid]['LaptimeCumulative'] = 0.0;

            $stp = StatsTrackPopularity::getLatest($track);
            if ($stp != NULL) {
                $track_cache[$tid]['LastScannedLap'] = $stp->lastScannedLapId();
                $track_cache[$tid]['LapCount'] = $stp->lapCount();
                $track_cache[$tid]['LaptimeCumulative'] = $stp->laptime();
            }

            if ($min_last_scanned_lap === NULL || $track_cache[$tid]['LastScannedLap'] < $min_last_scanned_lap) {
                $min_last_scanned_lap = $track_cache[$tid]['LastScannedLap'];
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
            $tid = $lap->session()->track()->id();
            $last_scanned_lap = $lid;

            // check for valid current track
            if (!array_key_exists($tid, $track_cache)) continue;

            // check if lap is already scanned for this track
            if ($lid <= $track_cache[$tid]['LastScannedLap']) continue;

            // increment lapcount
            $track_cache[$tid]['LapCount'] += 1;
            $track_cache[$tid]['LastScannedLap'] = $lid;
            $track_cache[$tid]['LaptimeCumulative'] += $lap->laptime() / 1e3;
        }

        // calculate new data
        foreach ($track_cache as $tid=>$data) {
            $meters = $track_cache[$tid]['LapCount'] * $track_cache[$tid]['Track']->length();
            $track_cache[$tid]['Meters'] = $meters;
            if ($meters > $most_driven_meters) $most_driven_meters = $meters;
        }

        // calculate new data, and save
        $timestamp = new Datetime();
        foreach ($track_cache as $tid=>$data) {

            // this will not work and must never been uncommented
            // skipping tracks will break $min_last_scanned_lap calculation
            // if ($track_cache[$tid]['LapCount'] == 0) continue;

            if ($most_driven_meters == 0)
                $popularity = 0;
            else
                $popularity = $data['Meters'] / $most_driven_meters;

            // save
            $stp = new StatsTrackPopularity(0);
            $stp->Timestamp = $timestamp;
            $stp->LastScannedLap = $last_scanned_lap;
            $stp->Track = $track_cache[$tid]['Track'];
            $stp->LapCount = $track_cache[$tid]['LapCount'];
            $stp->Popularity = $popularity;
            $stp->LaptimeCumulative = $track_cache[$tid]['LaptimeCumulative'];
            $stps[] = $stp;
        }

        return $stps;
    }


    /**
     * Compares to StatsTrackPopularity objects for most popularity.
     * This is intended for usort() of arrays with StatsTrackPopularity objects
     * @param $l1 StatsTrackPopularity object
     * @param $l2 StatsTrackPopularity object
     * @return -1 if $l1 is more popular, +1 when $l2 is more popular, 0 if both are equal
     */
    public static function comparePopularity(StatsTrackPopularity $stp1, StatsTrackPopularity $stp2) {
        if ($stp1->popularity() < $stp2->popularity()) return 1;
        if ($stp1->popularity() > $stp2->popularity()) return -1;
        return 0;
    }


    //! @return The latest StatsTrackPopularity object for a certain track (can return NULL)
    public static function getLatest(Track $track) {
        global $_StatsTrackPopularityTrackHash;

        if ($_StatsTrackPopularityTrackHash === NULL) {
            $_StatsTrackPopularityTrackHash = array();
            foreach (StatsTrackPopularity::listLatest() as $stp) {
                $_StatsTrackPopularityTrackHash[$stp->track()->id()] = $stp;
            }
        }

        if (array_key_exists($track->id(), $_StatsTrackPopularityTrackHash)) {
            return $_StatsTrackPopularityTrackHash[$track->id()];
        }

        return NULL;
    }


    //! @return The number of laps driven on this track
    public function lapCount() {
        if ($this->LapCount === NULL) $this->updateFromDb();
        return $this->LapCount;
    }


    //! @return The cummulated laptime in [s]
    public function laptime() {
        if ($this->LaptimeCumulative === NULL) $this->updateFromDb();
        return $this->LaptimeCumulative;
    }


    //! @return The Id of the last Lap that counts into this statistics
    public function lastScannedLapId() {
        if ($this->LastScannedLap === NULL) $this->updateFromDb();
        return $this->LastScannedLap;
    }


    //! @return A ordered list of
    public static function listLatest() {
        global $acswuiDatabase;
        $stps = array();

        foreach (Track::listTracks() as $track) {
            $tid = $track->id();
            $query = "SELECT Id FROM StatsTrackPopularity WHERE Track = $tid ORDER BY Id DESC LIMIT 1";
            $res = $acswuiDatabase->fetch_raw_select($query);
            if (count($res)) {
                $stps[] = new StatsTrackPopularity($res[0]['Id']);
            }
        }

        usort($stps, "StatsTrackPopularity::comparePopularity");

        return $stps;
    }


    //! @return The popularity of a track in [%]
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
        $columns['LastScannedLap'] = $this->LastScannedLap;
        $columns['Track'] = $this->Track->id();
        $columns['LapCount'] = $this->LapCount;
        $columns['Popularity'] = $this->Popularity;
        $columns['LaptimeCumulative'] = $this->LaptimeCumulative;

        // save
        $this->Id = $acswuiDatabase->insert_row("StatsTrackPopularity", $columns);
    }


    //! @return The according track object
    public function track() {
        if ($this->Track === NULL) $this->updateFromDb();
        return $this->Track;
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
        $columns[] = 'Track';
        $columns[] = 'LapCount';
        $columns[] = 'Popularity';
        $columns[] = 'LaptimeCumulative';

        // request from db
        $res = $acswuiDatabase->fetch_2d_array("StatsTrackPopularity", $columns, ['Id'=>$this->Id]);
        if (count($res) !== 1) {
            $acswuiLog->logError("Cannot find StatsTrackPopularity.Id=" . $this->Id);
            return;
        }

        $this->Timestamp = new DateTime($res[0]['Timestamp']);
        $this->LastScannedLap = (int) $res[0]['LastScannedLap'];
        $this->Track = new Track($res[0]['Track']);
        $this->LapCount = (int) $res[0]['LapCount'];
        $this->Popularity = (float) $res[0]['Popularity'];
        $this->LaptimeCumulative = (float) $res[0]['LaptimeCumulative'];
    }
}

?>
