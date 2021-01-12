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
//     private $Drivers = NULL;
//     private $DrivenLaps = NULL;
//     private $DrivenMeters = NULL;
//     private $DrivenSeconds = NULL;

//     private $Popularity = NULL;

//     private static $LongestDrivenTrack = NULL;

    /**
     * @param $id Database table id
     */
    public function __construct(int $id) {
        $this->Id = (int) $id;
    }

    public function __toString() {
        return "Track(Id=" . $this->Id . ")";
    }

    private function cacheUpdateBasics() {
        global $acswuiLog;
        global $acswuiDatabase;

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

    //! @return Track config identification name
    public function config() {
        if ($this->Config === NULL) $this->cacheUpdateBasics();
        return $this->Config;
    }

//     //! @return Amount of laps turned on this track
//     public function drivenLaps() {
//         if ($this->DrivenLaps !== NULL) return $this->DrivenLaps;
//         $this->updateDriven();
//         return $this->DrivenLaps;
//     }

//     //! @return Amount of driven meters on a track
//     public function drivenMeters() {
//         if ($this->DrivenMeters !== NULL) return $this->DrivenMeters;
//         $this->updateDriven();
//         return $this->DrivenMeters;
//     }

//     //! @return Amount of driven seconds on a track
//     public function drivenSeconds() {
//         if ($this->DrivenSeconds !== NULL) return $this->DrivenSeconds;
//         $this->updateDriven();
//         return $this->DrivenSeconds();
//     }

//     //! @return A list of User objects that have driven at least a lap on this track
//     public function drivers() {
//         global $acswuiDatabase;
//
//         if ($this->Drivers !== NULL) return $this->Drivers;
//
//         // determine driven users
//         $driver_ids = array();
//         $query = "SELECT Laps.User FROM Laps";
//         $query .= " INNER JOIN Sessions ON Sessions.Id=Laps.Session";
//         $query .= " WHERE Sessions.Track=" . $this->Id;
//         foreach ($acswuiDatabase->fetch_raw_select($query) as $lap) {
//             if (!in_array($lap['User'], $driver_ids)) $driver_ids[] = $lap['User'];
//         }
//
//         // populate user list
//         $this->Drivers = array();
//         foreach ($driver_ids as $id) {
//             $this->Drivers[] = new User($id);
//         }
//
//         return $this->Drivers;
//     }


    //! @return Html img tag containing preview image
    public function htmlImg($img_id="", $max_height=NULL) {
        global $acswuiConfig;

        $track = $this->track();
        $config = $this->config();
        $name = $this->name();
        $img_id = ($img_id == "") ? "":"id=\"$img_id\"";
        $max_height = ($max_height === NULL) ? "" : "height=\"$max_height\"";

        // get path
        if ($config !="") {
            $basepath = $acswuiConfig->AcsContent . "/content/tracks/$track/ui/$config";
        } else {
            $basepath = $acswuiConfig->AcsContent . "/content/tracks/$track/ui";
        }

        return "<img src=\"$basepath/preview.png\" $img_id alt=\"$name\" title=\"$name\" $max_height>";
    }


    //! @return The unique database row ID of the Track
    public function id() {
        return $this->Id;
    }

    //! @return Length of the track in meters
    public function length() {
        if ($this->Length === NULL) $this->cacheUpdateBasics();
        return $this->Length;
    }

    /**
     * @param $inculde_deprecated If set to TRUE, also deprectaed items are listed (Default: False)
     * @return An array of all available Track objects in alphabetical order
     */
    public static function listTracks($inculde_deprecated=FALSE) {
        global $acswuiDatabase;

        $list = array();

        $where = array();
        if ($inculde_deprecated !== TRUE) {
            $where['Deprecated'] = 0;
        }

        foreach ($acswuiDatabase->fetch_2d_array("Tracks", ['Id'], $where, 'Name') as $row) {
            $list[] = new Track($row['Id']);
        }

        return $list;
    }

//     //! @return The longest track
//     public static function longestDrivenTrack() {
//         global $acswuiDatabase;
//         if (Track::$LongestDrivenTrack !== NULL) return Track::$LongestDrivenTrack;
//
//         $ldt = NULL;
//         $ldt_driven_length = 0;
//         foreach (Track::listTracks() as $t) {
//             if ($ldt === NULL || $t->drivenMeters() > $ldt_driven_length) {
//                 $ldt = $t;
//                 $ldt_driven_length = $t->drivenMeters();
//             }
//         }
//         Track::$LongestDrivenTrack = $ldt;
//
//         return Track::$LongestDrivenTrack;
//     }

    //! @return User friendly name of the track
    public function name() {
        if ($this->Name === NULL) $this->cacheUpdateBasics();
        return $this->Name;
    }

    //! @return Amount of pitboxes
    public function pitboxes() {
        if ($this->Pitboxes === NULL) $this->cacheUpdateBasics();
        return $this->Pitboxes;
    }

//     //! @return A floating point Number [0,1] that represents the popularity of the track
//     public function popularity() {
//         global $acswuiDatabase;
//
//         if ($this->Popularity !== NULL) return $this->Popularity;
//
//         // determine longest track
//         $this->Popularity = 1.0;
//         $this->Popularity *= $this->drivenMeters() / (Track::longestDrivenTrack()->drivenMeters());
//         $this->Popularity *= count($this->drivers()) / count(User::listDrivers());
//
//         return $this->Popularity;
//     }

    //! @return Track identification name
    public function track() {
        if ($this->Track === NULL) $this->cacheUpdateBasics();
        return $this->Track;
    }

//     //! Update the caches for DrivenMeters and DrivenSeconds
//     private function updateDriven() {
//         global $acswuiDatabase;
//
//         $this->DrivenSeconds = 0;
//         $this->DrivenMeters = 0;
//
//         $query = "SELECT Laps.Laptime FROM Laps";
//         $query .= " INNER JOIN Sessions ON Sessions.Id=Laps.Session";
//         $query .= " WHERE Sessions.Track=" . $this->id();
//         $res = $acswuiDatabase->fetch_raw_select($query);
//         $this->DrivenLaps = count($res);
//         $this->DrivenMeters = $this->DrivenLaps * $this->length();
//         foreach ($res as $row) {
//             $this->DrivenSeconds += $row['Laptime'] / 1000;
//         }
//     }

}

?>
