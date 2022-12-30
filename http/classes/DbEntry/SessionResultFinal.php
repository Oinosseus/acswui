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
        $query = "SELECT Position, User, CarSkin, TeamCar, TotalTime, BestLap, RSerRegistration, RSerClass";
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
            $new_result_columns['PenPts'] = 0;
            $new_result_columns['RankingPoints'] = new \Core\DriverRankingPoints();
            $new_result_columns['BestLaptime'] = (int) $row['BestLap'];
            $new_result_columns['RSerRegistration'] = (int) $row['RSerRegistration'];
            $new_result_columns['RSerClass'] = (int) $row['RSerClass'];
            $new_result_columns['AmountsCuts'] = 0;
            $new_result_columns['Driver'] = new \Compound\SessionEntry($session,
                                                                       \DbEntry\TeamCar::fromId($new_result_columns['TeamCar']),
                                                                       \DbEntry\User::fromId($new_result_columns['User']));

            // automatic registering for race series
            if ($session->scheduleItem() &&
                $session->scheduleItem()->getRSerSplit() &&
                $new_result_columns['RSerClass'] != 0 &&
                $new_result_columns['RSerRegistration'] == 0) {
                    $reg = RSerRegistration::createNew($session->scheduleItem()->getRSerSplit()->event()->season(),
                                                       RSerClass::fromId($new_result_columns['RSerClass']),
                                                       ($new_result_columns['TeamCar'] == 0) ? NULL : TeamCar::fromId($new_result_columns['TeamCar']),
                                                       ($new_result_columns['TeamCar'] == 0) ? User::fromId($new_result_columns['User']) : NULL,
                                                       ($new_result_columns['TeamCar'] == 0) ? CarSkin::fromId($new_result_columns['CarSkin']) : NULL);
                    $new_result_columns['RSerRegistration'] = $reg->id();
            }

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
                $new_result_columns['RankingPoints']->addSfPen($spen->penSf());
                $new_result_columns['PenPts'] += $spen->penPts();
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
                        if ($session->type() == \Enums\SessionType::Race) {

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

        // count DNF drivers
        $dnf_amount = 0;
        for ($rslt_idx=0; $rslt_idx<count($new_results); ++$rslt_idx) {
            if ($new_results[$rslt_idx]['PenDnf']) ++$dnf_amount;
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
            $pl_no_dnf = $pl - $dnf_amount;
            if ($pl_no_dnf < 0) $pl_no_dnf = 0;

            // calculate experience
            if ($new_results[$rslt_idx]['PenDsq'] == 0) {
                $rp->addXp($session->type(), $l);
            }

            // success
            if ($new_results[$rslt_idx]['PenDsq'] == 0 && $new_results[$rslt_idx]['PenDnf'] == 0) {
                if ($session->type() == \Enums\SessionType::Race) {
                    if ($session->lapBest() !== NULL && $new_results[$rslt_idx]['BestLaptime'] == $session->lapBest()->laptime())
                        $rp->addSxRt();
                    $rp->addSxR($pl_no_dnf);

                } else if ($session->type() == \Enums\SessionType::Qualifying) {
                    $rp->addSxQ($pl);
                }
            }

            // safety
            if ($session->type() != \Enums\SessionType::Practice || \Core\ACswui::getPAram('DriverRankingSfAP') == FALSE) {
                $rp->addSfCt($new_results[$rslt_idx]['AmountsCuts']);
                foreach ($session->collisions($new_results[$rslt_idx]['Driver']) as $coll) {
                    if  ($coll instanceof CollisionCar) {
                        $rp->addSfCc($coll->speed());
                    } else if  ($coll instanceof CollisionEnv) {
                        $rp->addSfCe($coll->speed());
                    } else {
                        \Core\Log::error("Unknown collision class!");
                    }
                }
            }

            // store
            $new_results[$rslt_idx]['RankingPoints'] = $rp->json();
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
            $columns['PenPts'] = $rslt['PenPts'];
            $columns['RSerRegistration'] = $rslt['RSerRegistration'];
            $columns['RSerClass'] = $rslt['RSerClass'];
            \Core\Database::insert("SessionResultsFinal", $columns);
        }

        // set result as calculated
        \Core\Database::update("Sessions", $session->id(), ['FinalResultsCalculated'=>1]);

        // call related race series event calculation
        $rser_split = $session->rserSplit();
        if ($rser_split !== NULL) {
            RSerResult::calculateFromEvent($rser_split->event());
        }
    }


    //! @return The CarSkin object with that this result was reached
    public function carSkin() : CarSkin {
        $id = (int) $this->loadColumn("CarSkin");
        return new \DbEntry\CarSkin($id);
    }


    //! @return The \Compound\SessionEntry that this result is granted
    public function driver() : \Compound\SessionEntry {
        $t = TeamCar::fromId((int) $this->loadColumn("TeamCar"));
        $u = User::fromId((int) $this->loadColumn("User"));
        return new \Compound\SessionEntry($this->session(), $t, $u);
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


    //! @return Additional penalty points for race series
    public function penPts() : int {
        return (int) $this->loadColumn("PenPts");
    }


    //! @return The position of the driver in the session (1==leader)
    public function position() : int {
        return (int) $this->loadColumn("Position");
    }


    //! @return An array with DriverRanking points information
    public function rankingPoints() : \Core\DriverRankingPoints {
        $rp = $this->loadColumn("RankingPoints");
        return new \Core\DriverRankingPoints($rp);
    }


    //! @return A RSerClass object or NULL
    public function rserClass() : ?RSerClass {
        return RSerClass::fromId((int) $this->loadColumn("RSerClass"));
    }


    //! @return The associated RSerRegistration (if existent)
    public function rserRegistration() : ?RSerRegistration {
        return RSerRegistration::fromId((int) $this->loadColumn('RSerRegistration'));
    }


    //! @return The session where this result refers to
    public function session() : Session {
        $id = (int) $this->loadColumn("Session");
        return Session::fromId($id);
    }
}
