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

    private $AmountLaps = NULL;
    private $AmountCuts = NULL;
    private $AmountCollisionEnv = NULL;
    private $AmountCollisionCar = NULL;

    /**
     * @param $id Database table id
     * @param $session The according Session object (saves DB request if given)
     */
    public function __construct($id, $session=NULL) {
        $this->Id = $id;
        $this->Session = $session;
    }


    //! @return The number of collisions with other cars
    public function amountCollisionCar() {
        if ($this->AmountCollisionCar === NULL) {

            $this->AmountCollisionCar = 0;
            foreach ($this->session()->collisions() as $cll) {
                if ($cll->type() != CollisionType::Car) continue;
                if ($cll->secondary()) continue;
                if ($cll->user()->id() == $this->user()->id()) $this->AmountCollisionCar += 1;
            }
        }

        return $this->AmountCollisionCar;
    }


    //! @return The number of collisions with environment
    public function amountCollisionEnv() {
        if ($this->AmountCollisionEnv === NULL) {

            $this->AmountCollisionEnv = 0;
            foreach ($this->session()->collisions() as $cll) {
                if ($cll->type() != CollisionType::Env) continue;
                if ($cll->user()->id() == $this->user()->id()) $this->AmountCollisionEnv += 1;
            }
        }

        return $this->AmountCollisionEnv;
    }


    //! @return The number of cuts in this session
    public function amountCuts() {
        if ($this->AmountCuts === NULL) {

            $this->AmountCuts = 0;
            foreach ($this->session()->drivenLaps() as $lap) {
                if ($lap->user()->id() == $this->user()->id()) $this->AmountCuts += $lap->cuts();
            }
        }

        return $this->AmountCuts;
    }


    //! @return The number of laps driven in this session
    public function amountLaps() {
        if ($this->AmountLaps === NULL) {

            $this->AmountLaps = 0;
            foreach ($this->session()->drivenLaps() as $lap) {
                if ($lap->user()->id() == $this->user()->id()) $this->AmountLaps += 1;
            }
        }

        return $this->AmountLaps;
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
