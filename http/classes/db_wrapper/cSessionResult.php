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


    /**
     * Function that compares two SessionResult objects by guessing their position.
     * For races Higher position is guessed by most driven laps and least total time.
     * For other session position is guessed by best laptime
     * @param $resut1 SessionResult object
     * @param $resut2 SessionResult object
     * @return -1 if $result1 is better position, else 1 (if both are equal this returns 0)
     */
    public static function comparePositionGuess($result1, $result2) {
        if ($result1->session()->type() == 3) {

            if ($result1->amountLaps() == $result2->amountLaps()) {
                return SessionResult::compareTotalTime($result1, $result2);

            } else if ($result1->amountLaps() > $result2->amountLaps()) {
                return -1;

            } else {
                return 1;
            }

        } else {
            return SessionResult::compareBestLap($result1, $result2);
        }
    }


    /**
     * Function that compares two SessionResult objects.
     * @param $resut1 SessionResult object
     * @param $resut2 SessionResult object
     * @return -1 if bestlap() of $result1 is faster, else 1 (if both are equal this returns 0)
     */
    public static function compareTotalTime($result1, $result2) {
        if ($result1->totaltime() == $result2->totaltime())
            return 0;
        else if ($result1->totaltime() < $result2->totaltime())
            return -1;
        else
            return 1;
    }


    /**
     * Function that compares two SessionResult objects.
     * @param $resut1 SessionResult object
     * @param $resut2 SessionResult object
     * @return -1 if bestlap() of $result1 is faster, else 1 (if both are equal this returns 0)
     */
    public static function compareBestLap($result1, $result2) {
        if ($result1->bestlap() == $result2->bestlap())
            return 0;
        else if ($result1->bestlap() < $result2->bestlap())
            return -1;
        else
            return 1;
    }


    /**
     * Function that compares two SessionResult objects.
     * @param $resut1 SessionResult object
     * @param $resut2 SessionResult object
     * @return -1 if position() of $result1 is better, else 1 (if both are equal this returns 0)
     */
    public static function comparePosition($result1, $result2) {
        if ($result1->position() == $result2->position())
            return 0;
        else if ($result1->position() < $result2->position())
            return -1;
        else
            return 1;
    }


    //! @return A List of SessionResult objects (If not existent in DB temporary SessionResult objects will be created)
    public static function listSessionResults(Session $session) {
        global $acswuiDatabase;

        $session_results = array();

        $res = array();
        $res = $acswuiDatabase->fetch_2d_array("SessionResults", ["Id", "TotalTime"], ["Session"=>$session->id()]);
        foreach ($res as $row) {
            if ($row['TotalTime'] == 0) continue;
            $cll = new SessionResult($row['Id'], $session);
            $session_results[] = $cll;
        }

        // create temporary results
        if (count($res) == NULL) {

            // gather driven laps
            $user_results = array();
            foreach ($session->drivenLaps() as $lap) {
                $uid = $lap->user()->id();

                if (!array_key_exists($uid, $user_results)) {
                    $user_results[$uid] = array();
                    $user_results[$uid] = new SessionResult(0, $session);
                    $user_results[$uid]->User = $lap->user();
                    $user_results[$uid]->Position = 0;
                    $user_results[$uid]->BestLap = $lap->laptime();
                    $user_results[$uid]->TotalTime = 0;
                    $user_results[$uid]->Ballast = $lap->ballast();
                    $user_results[$uid]->Restrictor = $lap->restrictor();
                    $user_results[$uid]->AmountLaps = 0;
                    $user_results[$uid]->AmountCuts = 0;
                    $user_results[$uid]->AmountCollisionEnv = 0;
                    $user_results[$uid]->AmountCollisionCar = 0;
                }

                $user_results[$uid]->CarSkin = $lap->carSkin();
                if ($lap->laptime() < $user_results[$uid]->BestLap) {
                    $user_results[$uid]->BestLap = $lap->laptime();
                    $user_results[$uid]->Ballast = $lap->ballast();
                    $user_results[$uid]->Restrictor = $lap->restrictor();
                }
                $user_results[$uid]->TotalTime += $lap->laptime();
                $user_results[$uid]->AmountLaps += 1;
                $user_results[$uid]->AmountCuts += $lap->cuts();
            }

            // gahter collisions
            foreach ($session->collisions() as $coll) {
                $uid = $coll->user()->id();
                if (!array_key_exists($uid, $user_results)) continue;
                if ($coll->secondary()) continue;

                if ($coll->type() == CollisionType::Env) {
                    $user_results[$uid]->AmountCollisionEnv += 1;
                } else if ($coll->type() == CollisionType::Car) {
                    $user_results[$uid]->AmountCollisionCar += 1;
                }
            }

            // restructure data
            $session_results = array();
            foreach ($user_results as $uid=>$rslt) {
                $session_results[] = $rslt;
            }

            // assign positions
            usort($session_results, "SessionResult::comparePositionGuess");
            for ($i=0; $i<count($session_results); ++$i) {
                $session_results[$i]->Position = $i + 1;
            }

        }

        return $session_results;
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

        $this->Position = (int) $res[0]['Position'];
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
