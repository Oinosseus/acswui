<?php

namespace DbEntry;

/**
 * Cached wrapper to databse SessionResults table element
 */
class SessionResult extends DbEntry {
//     private $Id = NULL;
    private $Session = NULL;
    private $Position = NULL;
    private $PositionLeading = NULL;
    private $CarSkin = NULL;
    private $Drivers = NULL;
    private $BestLaptime = NULL;
    private $TotalTime = NULL;
    private $Ballast = NULL;
    private $Restrictor = NULL;

    private $AmountLaps = NULL;
    private $AmountCuts = NULL;
    private $AmountCollisionEnv = NULL;
    private $AmountCollisionCar = NULL;
    private $RankingPoints = NULL;

    /**
     * @param $id Database table id
     */
    protected function __construct($id) {
        parent::__construct("SessionResults", $id);
    }


    //! @return The number of collisions with other cars
    public function amountCollisionCar() {
        if ($this->AmountCollisionCar === NULL) {
            $this->AmountCollisionCar = 0;
            foreach ($this->session()->collisions() as $cll) {
                if ($cll instanceof CollisionCar) {
                    if (in_array($cll->user(), $this->drivers()))
                        $this->AmountCollisionCar += 1;
                }
            }
        }

        return $this->AmountCollisionCar;
    }


    //! @return The number of collisions with environment
    public function amountCollisionEnv() : int {

        // update cache
        if ($this->AmountCollisionEnv === NULL) {

            $this->AmountCollisionEnv = 0;
            foreach ($this->session()->collisions() as $cll) {
                if ($cll instanceof CollisionEnv) {
                    if (in_array($cll->user(), $this->drivers()))
                        $this->AmountCollisionEnv += 1;
                }
            }
        }

        return $this->AmountCollisionEnv;
    }


    //! @return The number of cuts in this session
    public function amountCuts() {
        if ($this->AmountCuts === NULL) {
            $this->AmountCuts = 0;
            foreach ($this->session()->laps($this->user()) as $lap) {
                $this->AmountCuts += $lap->cuts();
            }
        }

        return $this->AmountCuts;
    }


    //! @return The number of laps driven in this session
    public function amountLaps() {
        if ($this->AmountLaps === NULL) {
            $this->AmountLaps = count($this->session()->laps($this->user()));
        }

        return $this->AmountLaps;
    }


    //! @return The car ballast used for this session result [kg]
    public function ballast() {
        if ($this->Ballast == NULL) $this->Ballast = (int) $this->loadColumn("Ballast");
        return $this->Ballast;
    }


    //! @return The Lap best laptime of the session [ms]
    public function bestLaptime() {
        if ($this->BestLaptime == NULL) $this->BestLaptime = (int) $this->loadColumn("BestLap");
        return $this->BestLaptime;
    }


    //! @return The according CarSkin object
    public function carSkin() {
        if ($this->CarSkin == NULL) $this->CarSkin = CarSkin::fromId((int) $this->loadColumn("CarSkin"));
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
            return SessionResult::compareBestLaptime($result1, $result2);
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
    public static function compareBestLaptime($result1, $result2) {
        if ($result1->bestLaptime() == $result2->bestLaptime())
            return 0;
        else if ($result1->bestLaptime() < $result2->bestLaptime())
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


    //! @return All drivers of this result (for teams this can be multiple drivers)
    public function drivers() : array {

        // update cache
        if ($this->Drivers === NULL) {
            $this->Drivers = array();

            // add single driver
            if ($this->user()) $this->Drivers[] = $this->user();

            // add team drivers
            if ($this->teamCar()) {
                foreach ($this->teamCar()->drivers() as $tmm)
                    $this->Drivers[] = $tmm->user();
            }
        }

        return $this->Drivers;
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) {
        return parent::getCachedObject("SessionResults", "SessionResult", $id);
    }


    //! @return A List of SessionResult objects (If not existent in DB temporary SessionResult objects will be created)
    public static function listSessionResults(Session $session) {
        $session_results = array();

        // get results from database
        $res = \Core\Database::fetch("SessionResults", ["Id", "TotalTime"], ["Session"=>$session->id()]);
        foreach ($res as $row) {
            if ($row['TotalTime'] == 0) continue;
            $sr = SessionResult::fromId($row['Id']);
            $session_results[] = $sr;
        }

        return $session_results;
    }


    //! @return The position/place in the session results
    public function position() {
        if ($this->Position == NULL) $this->Position = (int) $this->loadColumn("Position");
        return $this->Position;
    }


    //! @return The positions of this, leading over other positions in this session
    public function positionLeading() {
        if ($this->PositionLeading == NULL) {
            $this->PositionLeading = count($this->session()->results()) - $this->position();
        }

        return $this->PositionLeading;
    }


    /**
     * The points for the driver ranking, that were given for this session
     * SX/BT are not contained, since they cannot be determin per session, but globally
     * @return An assiciative array
     */
    public function rankingPoints() {
        if ($this->RankingPoints === NULL) {
            $this->RankingPoints = array();
            $this->RankingPoints['XP'] = array('P'=>0,  'Q'=>0,  'R'=>0,  'Sum'=>0);
            $this->RankingPoints['SX'] = array('RT'=>0, 'Q'=>0,  'R'=>0,  'Sum'=>0);
            $this->RankingPoints['SF'] = array('CT'=>0, 'CE'=>0, 'CC'=>0, 'Sum'=>0);

            // driven length in this session
            $l = $this->amountLaps() * $this->session()->track()->length();

            // experience
            if ($this->session()->type() == \DbEntry\Session::TypeRace) {
                $this->RankingPoints['XP']['R'] = \Core\Acswui::getPAram('DriverRankingXpR') * $l / 1e6;
            } else if ($this->session()->type() == \DbEntry\Session::TypeQualifying) {
                $this->RankingPoints['XP']['Q'] = \Core\Acswui::getPAram('DriverRankingXpQ') * $l / 1e6;
            } else if ($this->session()->type() == \DbEntry\Session::TypePractice) {
                $this->RankingPoints['XP']['P'] = \Core\Acswui::getPAram('DriverRankingXpP') * $l / 1e6;
            }
            $this->RankingPoints['XP']['Sum'] = $this->RankingPoints['XP']['R'] + $this->RankingPoints['XP']['Q'] + $this->RankingPoints['XP']['P'];

            // success
            if ($this->session()->type() == \DbEntry\Session::TypeRace) {
                if ($this->session()->lapBest() !== NULL && $this->bestLaptime() == $this->session()->lapBest()->laptime())
                    $this->RankingPoints['SX']['RT'] = \Core\Acswui::getPAram('DriverRankingSxRt');
                $this->RankingPoints['SX']['R'] = $this->positionLeading() * \Core\Acswui::getPAram('DriverRankingSxR');

            } else if ($this->session()->type() == \DbEntry\Session::TypeQualifying) {
                $this->RankingPoints['SX']['Q'] = $this->positionLeading() * \Core\Acswui::getPAram('DriverRankingSxQ');
            }
            $this->RankingPoints['SX']['Sum'] = $this->RankingPoints['SX']['RT'] + $this->RankingPoints['SX']['R'] + $this->RankingPoints['SX']['Q'];

            // safety
            if ($this->session()->type() != Session::TypePractice || \Core\ACswui::getPAram('DriverRankingSfAP') == FALSE) {
                $this->RankingPoints['SF']['CT'] = $this->amountCuts() * \Core\Acswui::getPAram('DriverRankingSfCt');
                $normspeed_coll_env = 0;
                $normspeed_coll_car = 0;
                foreach ($this->session()->collisions() as $coll) {
                    if ($this->user() === NULL) continue;
                    if ($coll->user() === NULL) continue;
                    if ($coll->user()->id() != $this->user()->id()) continue;
                    if  ($coll instanceof CollisionCar) {
                        $normspeed_coll_car += $coll->speed();
                    } else if  ($coll instanceof CollisionEnv) {
                        $normspeed_coll_env += $coll->speed();
                    } else {
                        \Core\Log::error("Unknown collision class!");
                    }
                }
                $normspeed_coll_env /= \Core\Acswui::getPAram('DriverRankingCollNormSpeed');
                $normspeed_coll_car /= \Core\Acswui::getPAram('DriverRankingCollNormSpeed');
                $this->RankingPoints['SF']['CE'] = \Core\Acswui::getPAram('DriverRankingSfCe') * $normspeed_coll_env;
                $this->RankingPoints['SF']['CC'] = \Core\Acswui::getPAram('DriverRankingSfCc') * $normspeed_coll_car;
                $this->RankingPoints['SF']['Sum'] = $this->RankingPoints['SF']['CT'] + $this->RankingPoints['SF']['CE'] + $this->RankingPoints['SF']['CC'];
            }
        }


        return $this->RankingPoints;
    }


    //! @return The car restrictor used for this session result
    public function restrictor() {
        if ($this->Restrictor == NULL) $this->Restrictor = (int) $this->loadColumn("Restrictor");
        return $this->Restrictor;
    }


    //! @return The according Session object
    public function session() {
        if ($this->Session == NULL) $this->Session = Session::fromId((int) $this->loadColumn("Session"));
        return $this->Session;
    }


    public function teamCar() : ?\DbEntry\TeamCar {
        $id = (int) $this->loadColumn("TeamCar");
        return ($id == 0) ? NULL : TeamCar::fromId($id);
    }


    //! @return The total time spent in this session [ms]
    public function totaltime() {
        if ($this->TotalTime == NULL) $this->TotalTime = (int) $this->loadColumn("TotalTime");
        return $this->TotalTime;
    }


    //! @return The according User object
    public function user() : ?\DbEntry\USer {
        $id = (int) $this->loadColumn("User");
        return ($id == 0) ? NULL : User::fromId($id);
    }
}

?>
