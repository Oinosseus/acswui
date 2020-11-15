<?php

/**
 * Cached wrapper to car databse Tracks table element
 */
class Track {

    private $Id = NULL;
    private $Track = NULL;
    private $Config = NULL;
    private $Name = NULL;
    private $Length = NULL;
    private $Pitboxes = NULL;
    private $Drivers = NULL;
    private $DrivenLaps = NULL;
    private $DrivenMeters = NULL;
    private $DrivenSeconds = NULL;

    private $Popularity = NULL;

    private static $LongestDrivenTrack = NULL;

    /**
     * @param $id Database table id
     */
    public function __construct($id) {
        global $acswuiLog;
        global $acswuiDatabase;

        $this->Id = $id;

        // get basic information
        $res = $acswuiDatabase->fetch_2d_array("Tracks", ['Track', 'Config', 'Name', 'Length', 'Pitboxes'], ['Id'=>$this->Id]);
        if (count($res) !== 1) {
            $acswuiLog->logError("Cannot find Tracks.Id=" . $this->Id);
            return;
        }

        $this->Track = $res[0]['Track'];
        $this->Config = $res[0]['Config'];
        $this->Name = $res[0]['Name'];
        $this->Length = (int) $res[0]['Length'];
        $this->Pitboxes = (int) $res[0]['Pitboxes'];
    }

    //! @return The unique database row ID of the Track
    public function id() {
        return $this->Id;
    }

    //! @return Track identification name
    public function track() {
        return $this->Track;
    }

    //! @return Track config identification name
    public function config() {
        return $this->Config;
    }

    //! @return User friendly name of the track
    public function name() {
        return $this->Name;
    }

    //! @return Length of the track in meters
    public function length() {
        return $this->Length;
    }

    //! @return Amount of pitboxes
    public function pitboxes() {
        return $this->Pitboxes;
    }

    //! @return A list of User objects that have driven at least a lap on this track
    public function drivers() {
        global $acswuiDatabase;

        if ($this->Drivers !== NULL) return $this->Drivers;

        // determine driven users
        $driver_ids = array();
        $query = "SELECT Laps.User FROM Laps";
        $query .= " INNER JOIN Sessions ON Sessions.Id=Laps.Session";
        $query .= " WHERE Sessions.Track=" . $this->Id;
        foreach ($acswuiDatabase->fetch_raw_select($query) as $lap) {
            if (!in_array($lap['User'], $driver_ids)) $driver_ids[] = $lap['User'];
        }

        // populate user list
        $this->Drivers = array();
        foreach ($driver_ids as $id) {
            $this->Drivers[] = new User($id);
        }

        return $this->Drivers;
    }

    //! @return Amount of laps turned on this track
    public function drivenLaps() {
        if ($this->DrivenLaps !== NULL) return $this->DrivenLaps;
        $this->updateDriven();
        return $this->DrivenLaps;
    }

    //! @return Amount of driven meters on a track
    public function drivenMeters() {
        if ($this->DrivenMeters !== NULL) return $this->DrivenMeters;
        $this->updateDriven();
        return $this->DrivenMeters;
    }

    //! @return Amount of driven seconds on a track
    public function drivenSeconds() {
        if ($this->DrivenSeconds !== NULL) return $this->DrivenSeconds;
        $this->updateDriven();
        return $this->DrivenSeconds();
    }

    //! Update the caches for DrivenMeters and DrivenSeconds
    private function updateDriven() {
        global $acswuiDatabase;

        $this->DrivenSeconds = 0;
        $this->DrivenMeters = 0;

        $query = "SELECT Laps.Laptime FROM Laps";
        $query .= " INNER JOIN Sessions ON Sessions.Id=Laps.Session";
        $query .= " WHERE Sessions.Track=" . $this->id();
        $res = $acswuiDatabase->fetch_raw_select($query);
        $this->DrivenLaps = count($res);
        $this->DrivenMeters = $this->DrivenLaps * $this->length();
        foreach ($res as $row) {
            $this->DrivenSeconds += $row['Laptime'] / 1000;
        }
    }

    //! @return A floating point Number [0,1] that represents the popularity of the track
    public function popularity() {
        global $acswuiDatabase;

        if ($this->Popularity !== NULL) return $this->Popularity;

        // determine longest track
        $this->Popularity = 1.0;
        $this->Popularity *= $this->drivenMeters() / (Track::longestDrivenTrack()->drivenMeters());
        $this->Popularity *= count($this->drivers()) / count(User::listDrivers());

        return $this->Popularity;
    }

    //! @return An array of all available Track objects in alphabetical order
    public static function listTracks() {
        global $acswuiDatabase;

        $list = array();

        foreach ($acswuiDatabase->fetch_2d_array("Tracks", ['Id'], [], 'Name') as $row) {
            $list[] = new Track($row['Id']);
        }

        return $list;
    }

    //! @return The longest track
    public static function longestDrivenTrack() {
        global $acswuiDatabase;
        if (Track::$LongestDrivenTrack !== NULL) return Track::$LongestDrivenTrack;

        $ldt = NULL;
        $ldt_driven_length = 0;
        foreach (Track::listTracks() as $t) {
            if ($ldt === NULL || $t->drivenMeters() > $ldt_driven_length) {
                $ldt = $t;
                $ldt_driven_length = $t->drivenMeters();
            }
        }
        Track::$LongestDrivenTrack = $ldt;

        return Track::$LongestDrivenTrack;
    }
}

?>
