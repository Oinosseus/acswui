<?php

declare(strict_types=1);
namespace DbEntry;

class SessionResultFinal extends DbEntry {

    /**
     * @param $id Database table id
     */
    protected function __construct($id) {
        parent::__construct("SessionResultsFinal", $id);
    }


    //! @return The best laptime in the session (in milliseconds)
    public function bestLaptime() : int {
        return (int) $this->loadColumn("BestLaptime");
    }


    /**
     * Calculate final results for a certain session
     * @param $session The Session for which the results shall be calculated
     */
    public static function calculate(Session $session) {

        // ensure to have old results deleted
        \Core\Database::query("DELETE FROM SessionResultsFinal WHERE Session={$session->id()};");

        // list of new results data first element = leader
        $new_results = array();


        // iterate over raw session results and create a preliminary final result
        $query = "SELECT Position, User, CarSkin, TeamCar, TotalTime, BestLap";
        $query .= " FROM SessionResultsAc WHERE Session={$session->id()} AND TotalTime>0";
        $query .= " ORDER BY Position ASC;";
        foreach (\Core\Database::fetchRaw($query) as $row) {

            // prepare new result
            $new_result_columns = array();
            $new_result_columns['Position'] = (int) $row['Position'];
            $new_result_columns['Session'] = $session->id();
            $new_result_columns['User'] = (int) $row['User'];
            $new_result_columns['CarSkin'] = (int) $row['CarSkin'];
            $new_result_columns['TeamCar'] = (int) $row['TeamCar'];
            $new_result_columns['FinalLaps'] = 0;
            $new_result_columns['FinalTime'] = (int) $row['TotalTime'];
            $new_result_columns['PenDnf'] = 0;
            $new_result_columns['PenDsq'] = 0;
            $new_result_columns['RankingPoints'] = array();
            $new_result_columns['BestLaptime'] = (int) $row['BestLap'];
            $new_result_columns['AmountsCuts'] = 0;
            $new_result_columns['Driver'] = ($new_result_columns['User'] == 0) ?
                                                    \DbEntry\TeamCar::fromId($new_result_columns['TeamCar']) :
                                                    \DbEntry\User::fromId($new_result_columns['User']);

            // prepare data
            $new_result_columns['RankingPoints'] = array();
            $new_result_columns['RankingPoints']['XP'] = array('P'=>0,  'Q'=>0,  'R'=>0,  'Sum'=>0);
            $new_result_columns['RankingPoints']['SX'] = array('RT'=>0, 'Q'=>0,  'R'=>0,  'Sum'=>0);
            $new_result_columns['RankingPoints']['SF'] = array('CT'=>0, 'CE'=>0, 'CC'=>0, 'Pen'=>0, 'Sum'=>0);

            // count laps
            $new_result_columns['FinalLaps'] = count($session->laps($new_result_columns['Driver']));

            // count cuts
            foreach ($session->laps($new_result_columns['Driver']) as $l)
                    $new_result_columns['AmountsCuts'] += $l->cuts();

            // apply all penalties
            $query = "SELECT Id FROM SessionPenalties WHERE Session={$session->id()} AND User={$new_result_columns['User']} AND TeamCar={$new_result_columns['TeamCar']};";
            foreach (\Core\Database::fetchRaw($query) as $penrow) {
                $spen = \DbEntry\SessionPenalty::fromId((int) $penrow['Id']);

                if ($spen->penDnf()) $new_result_columns['PenDnf'] = 1;
                if ($spen->penDsq()) $new_result_columns['PenDsq'] = 1;
                $new_result_columns['FinalTime'] += $spen->penTime() * 1000;
                $new_result_columns['FinalLaps'] += $spen->penLaps();
                $new_result_columns['RankingPoints']['SF']['Pen'] += $spen->penSf();
            }

            // store result
            $new_results[] = $new_result_columns;
        }


        // --------------------------------------------------------------------
        //  Sort Positions
        // --------------------------------------------------------------------

        // bubblesort results to update positions (performance is sufficient for these small lists)
        for ($any_swap_done=TRUE; $any_swap_done;) {
            $any_swap_done = FALSE;

            for ($rslt_idx=1; $rslt_idx<count($new_results); ++$rslt_idx) {

                $do_swap = FALSE;

                // sort by PenDsq
                if ($new_results[$rslt_idx-1]['PenDsq'] > $new_results[$rslt_idx]['PenDsq']) $do_swap = TRUE;
                else if ($new_results[$rslt_idx-1]['PenDsq'] == $new_results[$rslt_idx]['PenDsq']) {

                    // sort by PenDnf
                    if ($new_results[$rslt_idx-1]['PenDnf'] > $new_results[$rslt_idx]['PenDnf']) $do_swap = TRUE;
                    else if ($new_results[$rslt_idx-1]['PenDnf'] == $new_results[$rslt_idx]['PenDnf']) {

                        // sort race sessions
                        if ($session->type() == \DbEntry\Session::TypeRace) {

                            // sort by FinalLaps
                            if ($new_results[$rslt_idx-1]['FinalLaps'] < $new_results[$rslt_idx]['FinalLaps']) $do_swap = TRUE;
                            else if ($new_results[$rslt_idx-1]['FinalLaps'] == $new_results[$rslt_idx]['FinalLaps']) {

                                // sort by FinalTime
                                if ($new_results[$rslt_idx-1]['FinalTime'] > $new_results[$rslt_idx]['FinalTime']) $do_swap = TRUE;
                            }

                        // sort other sessions
                        } else {

                            // sort by BestLaptime
                            if ($new_results[$rslt_idx-1]['BestLaptime'] > $new_results[$rslt_idx]['BestLaptime']) $do_swap = TRUE;
                        }
                    }
                }

                // bubble swap
                if ($do_swap) {
                    $any_swap_done = TRUE;
                    $tmp = $new_results[$rslt_idx-1];
                    $new_results[$rslt_idx-1] = $new_results[$rslt_idx];
                    $new_results[$rslt_idx] = $tmp;
                }
            }
        }

        // assign position number
        for ($rslt_idx=0; $rslt_idx<count($new_results); ++$rslt_idx) {
            $new_results[$rslt_idx]['Position'] = $rslt_idx + 1;
        }


        // --------------------------------------------------------------------
        //  Ranking Points
        // --------------------------------------------------------------------

        for ($rslt_idx=0; $rslt_idx<count($new_results); ++$rslt_idx) {

            // prepare data
            $rp = $new_results[$rslt_idx]['RankingPoints'];

            // calculate some metadata
            $l = $new_results[$rslt_idx]['FinalLaps'] * $session->track()->length();  // amount of laps
            $pl = count($new_results) - $new_results[$rslt_idx]['Position'];  // positions leading

            // calculate experience
            if ($new_results[$rslt_idx]['PenDsq'] == 0) {
                if ($session->type() == \DbEntry\Session::TypeRace) {
                    $rp['XP']['R'] = \Core\Acswui::getPAram('DriverRankingXpR') * $l / 1e6;
                } else if ($session->type() == \DbEntry\Session::TypeQualifying) {
                    $rp['XP']['Q'] = \Core\Acswui::getPAram('DriverRankingXpQ') * $l / 1e6;
                } else if ($session->type() == \DbEntry\Session::TypePractice) {
                    $rp['XP']['P'] = \Core\Acswui::getPAram('DriverRankingXpP') * $l / 1e6;
                }
            }

            // success
            if ($new_results[$rslt_idx]['PenDsq'] == 0 && $new_results[$rslt_idx]['PenDnf'] == 0) {
                if ($session->type() == \DbEntry\Session::TypeRace) {
                    if ($session->lapBest() !== NULL && $new_results[$rslt_idx]['BestLaptime'] == $session->lapBest()->laptime())
                        $rp['SX']['RT'] = \Core\Acswui::getPAram('DriverRankingSxRt');
                    $rp['SX']['R'] = $pl * \Core\Acswui::getPAram('DriverRankingSxR');

                } else if ($session->type() == \DbEntry\Session::TypeQualifying) {
                    $rp['SX']['Q'] = $pl * \Core\Acswui::getPAram('DriverRankingSxQ');
                }
            }

            // safety
            if ($session->type() != Session::TypePractice || \Core\ACswui::getPAram('DriverRankingSfAP') == FALSE) {
                $rp['SF']['CT'] = $new_results[$rslt_idx]['AmountsCuts'] * \Core\Acswui::getPAram('DriverRankingSfCt');
                $normspeed_coll_env = 0;
                $normspeed_coll_car = 0;
                foreach ($session->collisions($new_results[$rslt_idx]['Driver']) as $coll) {

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
                $rp['SF']['CE'] = \Core\Acswui::getPAram('DriverRankingSfCe') * $normspeed_coll_env;
                $rp['SF']['CC'] = \Core\Acswui::getPAram('DriverRankingSfCc') * $normspeed_coll_car;
            }

            // round values
            foreach ($rp as $group=>$data) {
                foreach ($data as $key=>$value) {
                    $rp[$group][$key] = round($value, 6);
                }
            }

            // create sums
            $rp['XP']['Sum'] = $rp['XP']['R'] + $rp['XP']['Q'] + $rp['XP']['P'];
            $rp['SX']['Sum'] = $rp['SX']['RT'] + $rp['SX']['R'] + $rp['SX']['Q'];
            $rp['SF']['Sum'] = $rp['SF']['CT'] + $rp['SF']['CE'] + $rp['SF']['CC'] + $rp['SF']['Pen'];

            // store
            $new_results[$rslt_idx]['RankingPoints'] = json_encode($rp);
        }


        // --------------------------------------------------------------------
        //  Transfer to Database
        // --------------------------------------------------------------------

        // store results
        foreach ($new_results as $rslt) {
            $columns = array();
            $columns['Position'] = $rslt['Position'];
            $columns['Session'] = $session->id();
            $columns['User'] = $rslt['User'];
            $columns['CarSkin'] = $rslt['CarSkin'];
            $columns['TeamCar'] = $rslt['TeamCar'];
            $columns['BestLaptime'] = $rslt['BestLaptime'];
            $columns['FinalLaps'] = $rslt['FinalLaps'];
            $columns['FinalTime'] = $rslt['FinalTime'];
            $columns['RankingPoints'] = $rslt['RankingPoints'];
            $columns['PenDnf'] = $rslt['PenDnf'];
            $columns['PenDsq'] = $rslt['PenDsq'];
            \Core\Database::insert("SessionResultsFinal", $columns);
        }

        // set result as calculated
        \Core\Database::update("Sessions", $session->id(), ['FinalResultsCalculated'=>1]);
    }


    //! @return The CarSkin object with that this result was reached
    public function carSkin() : CarSkin {
        $id = (int) $this->loadColumn("CarSkin");
        return new \DbEntry\CarSkin($id);
    }


    //! @return The User or TeamCar that this result is granted
    public function driver() : \Compound\Driver {
        $tcid = (int) $this->loadColumn("TeamCar");
        $uid = (int) $this->loadColumn("User");
        return new \Compound\Driver($tcid, $uid);
    }


    //! @return TRUE if the driver did not finish the session
    public function dnf() : bool {
        return ($this->loadColumn("PenDnf") != 0) ? TRUE : FALSE;
    }


    //! @return TRUE if the driver was disqualified from the session
    public function dsq() : bool {
        return ($this->loadColumn("PenDsq") != 0) ? TRUE : FALSE;
    }


    //! @return The amount of laps, driven in the session
    public function finalLaps() : int {
        return (int) $this->loadColumn("FinalLaps");
    }


    //! @return The amount of time, driven in the session
    public function finalTime() : int {
        return (int) $this->loadColumn("FinalTime");
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * When $id is NULL or 0, NULL is returned
     * @return An object by its database Id
     */
    public static function fromId(int $id) {
        if ($id === NULL or $id == 0) return NULL;
        return parent::getCachedObject("SessionResultsFinal", "SessionResultFinal", $id);
    }


    //! @return A list of ordered session results (leader first)
    public static function listResults(Session $session) : array {
        $ret = array();

        $query = "SELECT Id FROM SessionResultsFinal WHERE Session={$session->id()} ORDER BY Position ASC;";
        foreach (\Core\Database::fetchRaw($query) as $row) {
            $id = (int) $row['Id'];
            $ret[] = SessionResultFinal::fromId($id);
        }

        return $ret;
    }


    //! @return The position of the driver in the session (1==leader)
    public function position() : int {
        return (int) $this->loadColumn("Position");
    }


    //! @return An array with DriverRanking points information
    public function rankingPoints() : array {
        $rp = $this->loadColumn("RankingPoints");
        return json_decode($rp, TRUE);
    }


    //! @return The session where this result refers to
    public function session() : Session {
        $id = (int) $this->loadColumn("Session");
        return Session::fromId($id);
    }
}
