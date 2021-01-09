<?php

/**
 * Cached wrapper to databse SessionResults table element
 */
class SessionResult {
    private $Id = NULL;
    private $Session = NULL;
    private $Position = NULL;
    private $User = NULL;
    private $CarSkin = NULL;
    private $BestLap = NULL;
    private $TotalTime = NULL;
    private $Ballast = NULL;
    private $Restrictor = NULL;

    /**
     * @param $id Database table id
     * @param $session The according Session object (saves DB request if given)
     */
    public function __construct($id, $session=NULL) {
        $this->Id = $id;
        $this->Session = $session;
    }


    //! @return The car ballast used for this session result [kg]
    public function ballast() {
        if ($this->Ballast === NULL) $this->updateFromDb();
        return $this->Ballast;
    }


    //! @return The best laptime of the session [ms]
    public function bestlap() {
        if ($this->BestLap === NULL) $this->updateFromDb();
        return $this->BestLap;
    }


    //! @return The according CarSkin object
    public function carSkin() {
        if ($this->CarSkin === NULL) $this->updateFromDb();
        return $this->CarSkin;
    }


    //! @return The position/place in the session results
    public function position() {
        if ($this->Position === NULL) $this->updateFromDb();
        return $this->Position;
    }


    //! @return The car restrictor used for this session result
    public function restrictor() {
        if ($this->Restrictor === NULL) $this->updateFromDb();
        return $this->Restrictor;
    }


    //! @return The according Session object
    public function session() {
        if ($this->Session === NULL) $this->updateFromDb();
        return $this->Session;
    }


    //! @return The total time spent in this session [ms]
    public function totaltime() {
        if ($this->TotalTime === NULL) $this->updateFromDb();
        return $this->TotalTime;
    }


    private function updateFromDb() {
        global $acswuiDatabase;
        global $acswuiLog;

        // request from db
        $columns = array();
        $columns[] = 'Position';
        $columns[] = 'Session';
        $columns[] = 'User';
        $columns[] = 'CarSkin';
        $columns[] = 'BestLap';
        $columns[] = 'TotalTime';
        $columns[] = 'Ballast';
        $columns[] = 'Restrictor';

        $res = $acswuiDatabase->fetch_2d_array("SessionResults", $columns, ['Id'=>$this->Id]);
        if (count($res) !== 1) {
            $acswuiLog->logError("Cannot find SessionResults.Id=" . $this->Id);
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

        $this->Position = $res[0]['Position'];
        $this->User = new User($res[0]['User']);
        $this->CarSkin = new CarSkin($res[0]['CarSkin']);
        $this->BestLap = $res[0]['BestLap'];
        $this->TotalTime = $res[0]['TotalTime'];
        $this->Ballast = $res[0]['Ballast'];
        $this->Restrictor = $res[0]['Restrictor'];
    }


    //! @return The according User object
    public function user() {
        if ($this->User === NULL) $this->updateFromDb();
        return $this->User;
    }
}

?>
