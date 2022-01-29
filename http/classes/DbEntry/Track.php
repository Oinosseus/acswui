<?php

namespace DbEntry;

/**
 * Cached wrapper to car databse Tracks table element
 */
class Track extends DbEntry {

    // cache that stores objects of all tracks used by listTracks() method
    private static $ListTracksDeprConf = NULL;    // including deprecated, including configs
    private static $ListTracksDeprNConf = NULL;   // including depracated, excluding configs
    private static $ListTracksNDeprConf = NULL;   // excluding depracated, including configs
    private static $ListTracksNDeprNConf = NULL;  // excluding deprecated, excluding configs

    private $BestTimes = array();
    private $TrackLocation = NULL;
    private $Config = NULL;
    private $Name = NULL;
    private $Length = NULL;
    private $Pitboxes = NULL;
    private $Deprecated = NULL;
//     private $Drivers = NULL;
    private $DrivenLaps = NULL;
//     private $DrivenMeters = NULL;
//     private $DrivenSeconds = NULL;

//     private $Popularity = NULL;

//     private static $LongestDrivenTrack = NULL;

    /**
     * Construct a new object
     * @param $id Database table id
     */
    public function __construct(int $id) {
        parent::__construct("Tracks", $id);
    }


    //! @return The name of the author of this mod
    public function author() {
        return $this->loadColumn("Author");
    }


    //! @return
    public function bestLaps(CarClass $cc) {

        if (!array_key_exists($cc->id(), $this->BestTimes)) {
            $this->BestTimes[$cc->id()] = array();

            $found_driver_ids = array();
            $query = "SELECT Laps.Id, Laps.User FROM `Laps` INNER JOIN Sessions ON Sessions.Id = Laps.Session INNER JOIN CarSkins On CarSkins.Id = Laps.CarSkin INNER JOIN CarClassesMap ON CarSkins.Car = CarClassesMap.Car WHERE Sessions.Track = " . $this->id() . " AND Laps.Cuts = 0 AND CarClassesMap.CarClass = " . $cc->id() . " ORDER BY Laptime ASC;";
            foreach (\Core\Database::fetchRaw($query) as $row) {
                $user_id = $row['User'];
                if (!in_array($user_id, $found_driver_ids)) {
                    $found_driver_ids[] = $user_id;
                    $lap = Lap::fromId($row['Id']);
                    $this->BestTimes[$cc->id()][] = $lap;
                }
            }

        }

        return $this->BestTimes[$cc->id()];
    }


    /**
     * Compares to Track objects by their name (case insensitive)
     * This is intended for usort() of arrays with Track objects
     * @param $t1 Track object
     * @param $t2 Track object
     * @return -1 if $l1 is quicker, +1 when $l2 is quicker, 0 if both are equal
     */
    public static function compareName(Track $t1, Track $t2) {
        return strcasecmp($t1->name(), $t2->name());
    }


    //! @return Track config identification name
    public function config() {
        if ($this->Config === NULL) $this->Config = $this->loadColumn("Config");
        return $this->Config;
    }


    //! @return TRUE when this track is deprected
    public function deprecated() {
        if ($this->Deprecated === NULL)
            $this->Deprecated = ($this->loadColumn('Deprecated') == 0) ? FALSE : TRUE;
        return $this->Deprecated;
    }


    //! @return Description of the track
    public function description() {
        return $this->loadColumn("Description");
    }


    //! @return Amount of laps turned on this track
    public function drivenLaps() {
        if ($this->DrivenLaps === NULL) {
            $id = $this->id();
            $res = \Core\Database::fetchRaw("SELECT COUNT(Laps.Id) as DrivenLaps FROM Laps JOIN Sessions ON Laps.Session = Sessions.Id WHERE Sessions.Track = $id");
            $this->DrivenLaps = $res[0]['DrivenLaps'];
        }
        return $this->DrivenLaps;
    }


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



    /**
     * Estimate expected Laptimes
     * @param $cc If given laptimes are estimated for this CarClass
     * @return The estimated laptime as tuple of [min, typ, max]
     */
    public function estimeLaptime(CarClass $cc=NULL) {
        $l = $this->length();
        if ($cc !== NULL) {
            //! @todo Guess lap times by car class
            \Core\Log::warning("Not Implemented yet, just guessing :-(");
            return array(1000 * $l * 3.6 / 200,
                         1000 * $l * 3.6 / 150,
                         1000 * $l * 3.6 / 75);
        } else {
            return array(1000 * $l * 3.6 / 225,  // according to Porsche 919 on Nordschleife
                         1000 * $l * 3.6 / 150,
                         1000 * $l * 3.6 / 75);
        }
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) {
        return parent::getCachedObject("Tracks", "Track", $id);
    }


    /**
     * @param $include_link Include a link
     * @param $show_label Include a label
     * @param $show_img Include a preview image
     * @return Html content for this object
     */
    public function html(bool $include_link = TRUE, bool $show_label = TRUE, bool $show_img = TRUE) {

        $track_id = $this->id();
        $track_name = $this->name();
        $location = $this->location();
        $track_location_name = $location->name();
        $track_config = $this->config();
        $img_id = "TrackImage$track_id";

        // get path
        $preview_path = \Core\Config::RelPathHtdata . "/htmlimg/tracks/$track_id.png";
        $hover_path = \Core\Config::RelPathHtdata . "/htmlimg/tracks/$track_id.hover.png";

        // title
        $title = $track_name . "\n";
//         $title .= HumanValue::format($this->length(), "m") . " / ";
        $title .= $this->pitboxes() . " pits\n";
        $title .= $track_location_name . "/" . $track_config;

        $html = "";

        if ($show_label) $html .= "<label for=\"$img_id\">$track_name</label>";
        if ($show_img) $html .= "<img class=\"HoverPreviewImage\" src=\"$preview_path\" id=\"$img_id\" alt=\"$track_name\" title=\"$title\">";
        if ($include_link) $html = "<a href=\"index.php?HtmlContent=Track&Id=$track_id\">$html</a>";

        $html = "<div class=\"DbEntryHtml\">$html</div>";
        return $html;
    }


    //! @return Length of the track in meters
    public function length() {
        if ($this->Length === NULL) $this->Length = (int) $this->loadColumn("Length");
        return $this->Length;
    }

    /**
     * @param $inculde_deprecated If set to TRUE, also deprectaed items are listed (Default: False)
     * @return An array of all available Track objects in alphabetical order
     */
    public static function listTracks($inculde_deprecated=FALSE) {

        // update cache
        if (TracK::$ListTracksDeprConf === NULL) {

            Track::$ListTracksDeprConf   = array();
            Track::$ListTracksDeprNConf  = array();
            Track::$ListTracksNDeprConf  = array();
            Track::$ListTracksNDeprNConf = array();

            $columns = array();
            $columns[] = "Id";
            $columns[] = "Track";
            $columns[] = "Deprecated";

            $where = array();
            if ($inculde_deprecated !== TRUE) {
                $where['Deprecated'] = 0;
            }

            $res = \Core\Database::fetch("Tracks", $columns, $where, 'Name');
            foreach ($res as $row) {

                $id = $row['Id'];
                $track_obj = new Track($row['Id']);
                $track_name = $row['Track'];
                $deprecated = ($row['Deprecated'] == 0) ? FALSE : TRUE;

                if ($deprecated) {
                    Track::$ListTracksDeprConf[] = $track_obj;
                } else {
                    Track::$ListTracksNDeprConf[] = $track_obj;
                }


            }
        }

        return ($inculde_deprecated) ? Track::$ListTracksDeprConf : Track::$ListTracksNDeprConf;
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


    //! @return Referring TrackLocation object
    public function location() {
        if ($this->TrackLocation === NULL) {
            $location_id = $this->loadColumn("Location");
            if ($location_id == 0) {
                \Core\Log::error($this . " has invalid Location column!");
                return NULL;
            }
            $this->TrackLocation = TrackLocation::fromId($location_id);
        }
        return $this->TrackLocation;
    }


    //! @return User friendly name of the track
    public function name() {
        if ($this->Name === NULL) $this->Name = $this->loadColumn("Name");
        return $this->Name;
    }


    //! @return the path of the outline image
    public function outlinePath() {
        $track_location_track = $this->location()->track();
        $track_config = $this->config();

        // get path
        $path = "";
        if ($track_config !="") {
            $path = \Core\Config::RelPathHtdata . "/content/tracks/$track_location_track/ui/$track_config/outline.png";
        } else {
            $path = \Core\Config::RelPathHtdata . "/content/tracks/$track_location_track/ui/outline.png";
        }

        return $path;
    }


    //! @return Amount of pitboxes
    public function pitboxes() {
        if ($this->Pitboxes === NULL) $this->Pitboxes = (int) $this->loadColumn("Pitboxes");
        return $this->Pitboxes;
    }



    //! @return the path of the preview image
    public function previewPath() {
        $track_location_track = $this->location()->track();
        $track_config = $this->config();

        // get path
        $path = "";
        if ($track_config !="") {
            $path = \Core\Config::RelPathHtdata . "/content/tracks/$track_location_track/ui/$track_config/preview.png";
        } else {
            $path = \Core\Config::RelPathHtdata . "/content/tracks/$track_location_track/ui/preview.png";
        }

        return $path;
    }


    //! @return Version info string of the Track
    public function version() {
        return $this->loadColumn("Version");
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
