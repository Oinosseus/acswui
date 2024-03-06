<?php

namespace DbEntry;

/**
 * Cached wrapper to databse Lap table element
 */
class Lap extends DbEntry {

    private $RSerRegistration = NULL;

    /**
     * @param $id Database table id
     * @param $session The according Session object (saves DB request if given)
     */
    protected function __construct($id, $session=NULL) {
        parent::__construct("Laps", $id);
    }


    //! @return The amount of ballast at this lap
    public function ballast() {
        return (int) $this->loadColumn("Ballast");
    }


    //! @return The CarSkin object used for this lap
    public function carSkin() {
        $id = $this->loadColumn("CarSkin");
        return CarSkin::fromId($id);
    }


    /**
     * Compares to Lap objects for better laptime.
     * This is intended for usort() of arrays with Lap objects
     * @param $l1 Lap object
     * @param $l2 Lap object
     * @return -1 if $l1 is quicker, +1 when $l2 is quicker, 0 if both are equal
     */
    public static function compareLaptime(Lap $l1, Lap $l2) {
        if ($l1->laptime() < $l2->laptime()) return -1;
        if ($l1->laptime() > $l2->laptime()) return 1;
        return 0;
    }


    //! @return The amount of cuts in this lap
    public function cuts() {
        return (int) $this->loadColumn("Cuts");
    }


    //! @return \Compound\SessionEntry
    public function entry() : \Compound\SessionEntry {
        return new \Compound\SessionEntry($this->session(), $this->teamCar(), $this->user(), $this->carSkin());
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) {
        return parent::getCachedObject("Laps", "Lap", $id);
    }


    /**
     * Try to find the best lap with optional filters
     * @param $drivers A list of User objects
     * @return The Lap object with best, valid laptime (or NULL)
     */
    public static function findBestLap(Track $track,
                                       Car $car,
                                       array $drivers,
                                       int $temperature_ambient_min=0,
                                       int $temperature_ambient_max=99,
                                       int $temperature_road_min=0,
                                       int $temperature_road_max=99,
                                       int $min_ballast=0,
                                       int $min_restrictor=0) : ?Self {

        $query  = " SELECT Laps.Id FROM Laps";
        $query .= " INNER JOIN Sessions ON Sessions.Id=Laps.Session";
        $query .= " INNER JOIN CarSkins ON CarSkins.Id=Laps.CarSkin";
        $query .= " WHERE Laps.Cuts=0";
        if (count($drivers) > 0) {
            $user_id_string = NULL;
            foreach ($drivers as $u) {
                if ($user_id_string === NULL) $user_id_string = "{$u->id()}";
                else $user_id_string .= ",{$u->id()}";
            }
            $query .= " AND Laps.User IN ({$user_id_string})";
        }
        $query .= " AND Sessions.Track={$track->id()}";
        $query .= " AND Sessions.TempAmb>={$temperature_ambient_min}";
        $query .= " AND Sessions.TempAmb<={$temperature_ambient_max}";
        $query .= " AND Sessions.TempRoad>={$temperature_road_min}";
        $query .= " AND Sessions.TempRoad<={$temperature_road_max}";
        $query .= " AND CarSkins.Car={$car->id()}";
        $query .= " AND Laps.Ballast>={$min_ballast}";
        $query .= " AND Laps.Restrictor>={$min_restrictor}";
        $query .= " ORDER BY Laps.Laptime ASC LIMIT 1;";

        // request from DB
        $res = \Core\Database::fetchRaw($query);
        if (count($res) > 0) {
            return Self::fromId((int) $res[0]['Id']);
        } else {
            return NULL;
        }
    }


    //! @return The amount of grip at this lap
    public function grip() {
        return (float) $this->loadColumn("Grip");
    }


    /**
     * @param $label_time If TRUE (default), the laptime will be used as label, if FALSE then the lap-id will be shown
     * @return The laptime including a link to the session where this lap was driven
     */
    public function html(bool $label_time=TRUE) : string {
        $url = "index.php?HtmlContent=SessionOverview&SessionId={$this->session()->id()}";
        $title = "Session ID {$this->session()->id()}\nLap ID {$this->id()}";

        if ($label_time)
            $label = \Core\UserManager::currentUser()->formatLaptime($this->laptime());
        else
            $label = $this->id();

        return "<a href=\"$url\" title=\"$title\">$label</a>";
    }


    //! @return The lap time in milliseconds
    public function laptime() : int {
        return (int) $this->loadColumn("Laptime");
    }


    //! @return The amount of restrictor at this lap
    public function restrictor() {
        return (int) $this->loadColumn("Restrictor");
    }


    //! @return The amount of restrictor at this lap
    public function rserRegistration() : ?RSerRegistration {
        if ($this->RSerRegistration === NULL) {
            $regid = (int) $this->loadColumn("RSerRegistration");
            if ($regid > 0) {
                $this->RSerRegistration = RSerRegistration::fromId($regid);
            }
        }

        return $this->RSerRegistration;
    }


    //! @return The Session object of this lap
    public function session() {
        $id = $this->loadColumn("Session");
        return Session::fromId($id);
    }


    //! @return The amount of minutes after session start when this lap was driven (float)
    public function sessionMinutes() {
        $delta = $this->timestamp()->diff($this->session()->timestamp());

        if ($delta->y > 0 || $delta->m > 0) {
            \Core\Log::error("Lap Id=" . $lap->id() . " timestamp is over a month older than the session!");
        }

        $delta_minutes  = $delta->d * 24 * 60;
        $delta_minutes += $delta->h * 60;
        $delta_minutes += $delta->i;
        $delta_minutes += $delta->s / 60;
        $delta_minutes += $delta->f / 60000;
        $delta_minutes = ceil($delta_minutes);

        return $delta_minutes;
    }


    //! @return The according TeamCar object (can be NULL)
    public function teamCar() : ?TeamCar {
        return TeamCar::fromId((int) $this->loadColumn("TeamCar"));
    }


    //! @return A DateTime object
    public function timestamp() {
        $dt = $this->loadColumn("Timestamp");
        return new \DateTime($dt);
    }


    //! @return The User object representing the driver of this lapt
    public function user() : ?User {
        $id = $this->loadColumn("User");
        return User::fromId($id);
    }
}

?>
