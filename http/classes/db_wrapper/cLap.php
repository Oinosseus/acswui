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
    private $Timestamp = NULL;

    /**
     * @param $id Database table id
     * @param $session The according Session object (saves DB request if given)
     */
    public function __construct($id, $session=NULL) {
        $this->Id = $id;
        $this->Session = $session;
    }


    //! @return The database table id
    public function id() {
        return $this->Id;
    }

    //! @return The according Session object
    public function session() {
        if ($this->Session === NULL) $this->updateFromDb();
        return $this->Session;
    }

    //! @return A CarSkin object (which was used when driving the lap)
    public function carSkin() {
        if ($this->CarSkin === NULL) $this->updateFromDb();
        return $this->CarSkin;
    }

    //! @return A User object (which represents the dirver of the lap)
    public function user() {
        if ($this->User === NULL) $this->updateFromDb();
        return $this->User;
    }

    //! @return The laptime in miliseconds
    public function laptime() {
        if ($this->Laptime === NULL) $this->updateFromDb();
        return $this->Laptime;
    }

    //! @return The amount of cuts in the lap
    public function cuts() {
        if ($this->Cuts === NULL) $this->updateFromDb();
        return $this->Cuts;
    }

    //! @return The level of grip in this lap
    public function grip() {
        if ($this->Grip === NULL) $this->updateFromDb();
        return $this->Grip;
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
        $this->Laptime = $res[0]['Laptime'];
        $this->Cuts = $res[0]['Cuts'];
        $this->Grip = $res[0]['Grip'];
        $this->Timestamp = new DateTime($res[0]['Timestamp']);
    }
}

?>
