<?php

/**
 * Cached wrapper to databse Lap table element
 */
class Lap {
    private $Id = NULL;
    private $Session = NULL;

    private $CarSkin = NULL;
    private $User = NULL;
    private $Laptime = NULL;
    private $Cuts = NULL;
    private $Grip = NULL;
    private $Ballast = NULL;
    private $Restrictor = NULL;
    private $Timestamp = NULL;

    /**
     * @param $id Database table id
     * @param $session The according Session object (saves DB request if given)
     */
    public function __construct($id, $session=NULL) {
        $this->Id = $id;
        $this->Session = $session;
    }

    public function __toString() {
        return "Lap(Id=" . $this->Id . ")";
    }

    //! @return Ballast of the car in this lap [kg]
    public function ballast() {
        if ($this->Ballast === NULL) $this->updateFromDb();
        return $this->Ballast;
    }

    //! @return A CarSkin object (which was used when driving the lap)
    public function carSkin() {
        if ($this->CarSkin === NULL) $this->updateFromDb();
        return $this->CarSkin;
    }

    //! @return The amount of cuts in the lap
    public function cuts() {
        if ($this->Cuts === NULL) $this->updateFromDb();
        return $this->Cuts;
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


    //! @return The level of grip in this lap
    public function grip() {
        if ($this->Grip === NULL) $this->updateFromDb();
        return $this->Grip;
    }

    //! @return The database table id
    public function id() {
        return $this->Id;
    }

    //! @return The laptime in miliseconds
    public function laptime() {
        if ($this->Laptime === NULL) $this->updateFromDb();
        return $this->Laptime;
    }

    //! @return Restrictor of the car in this lap [%]
    public function restrictor() {
        if ($this->Restrictor === NULL) $this->updateFromDb();
        return $this->Restrictor;
    }

    //! @return The according Session object
    public function session() {
        if ($this->Session === NULL) $this->updateFromDb();
        return $this->Session;
    }


    //! @return The amount of minutes after session start when this lap was driven (float)
    public function sessionMinutes() {
        global $acswuiLog;

        $delta = $this->timestamp()->diff($this->session()->timestamp());

        if ($delta->y > 0 || $delta->m > 0) {
            $acswuiLog->logError("Lap Id=" . $lap->id() . " timestamp is over a month older than the session!");
        }

        $delta_minutes  = $delta->d * 24 * 60;
        $delta_minutes += $delta->h * 60;
        $delta_minutes += $delta->i;
        $delta_minutes += $delta->s / 60;
        $delta_minutes += $delta->f / 60000;
        $delta_minutes = ceil($delta_minutes);

        return $delta_minutes;
    }


    //! @return A DateTime object trepresening when the lap was recorded
    public function timestamp() {
        if ($this->Timestamp === NULL) $this->updateFromDb();
        return $this->Timestamp;
    }

    private function updateFromDb() {
        global $acswuiDatabase;
        global $acswuiLog;

        // request from db
        $columns = array();
        $columns[] = 'Session';
        $columns[] = 'CarSkin';
        $columns[] = 'User';
        $columns[] = 'Laptime';
        $columns[] = 'Cuts';
        $columns[] = 'Grip';
        $columns[] = 'Ballast';
        $columns[] = 'Restrictor';
        $columns[] = 'Timestamp';

        $res = $acswuiDatabase->fetch_2d_array("Laps", $columns, ['Id'=>$this->Id]);
        if (count($res) !== 1) {
            $acswuiLog->logError("Cannot find Laps.Id=" . $this->Id);
            return;
        }

        if ($this->Session !== NULL) {
            if ($this->Session->id() != $res[0]['Session']) {
                $acswuiLog->logError("Session ID mismatch " . $this->Session->id() . " vs " . $res[0]['Session']);
                $this->Session = new Session($res[0]['Session']);
            }
        } else {
            $this->Session = new Session($res[0]['Session']);
        }

        $this->CarSkin = new CarSkin($res[0]['CarSkin']);
        $this->User = new User($res[0]['User']);
        $this->Laptime = (int) $res[0]['Laptime'];
        $this->Cuts = (int) $res[0]['Cuts'];
        $this->Grip = (float) $res[0]['Grip'];
        $this->Ballast = (int) $res[0]['Ballast'];
        $this->Restrictor = (int) $res[0]['Restrictor'];
        $this->Timestamp = new DateTime($res[0]['Timestamp']);
    }

    //! @return A User object (which represents the dirver of the lap)
    public function user() {
        if ($this->User === NULL) $this->updateFromDb();
        return $this->User;
    }

}

?>
