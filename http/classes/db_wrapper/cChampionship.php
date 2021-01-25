<?php

/**
 * Cached wrapper to databse Championship table
 */
class Championship {
    private $Id = NULL;
    private $Name = NULL;
    private $ServerPreset = NULL;
    private $CarClasses = NULL;
    private $QualifyPositionPoints = NULL;
    private $RacePositionPoints = NULL;
    private $RaceTimePoints = NULL;
    private $RaceLeadLapPoints = NULL;
    private $BallanceBallast = NULL;
    private $BallanceRestrictor = NULL;
    private $Tracks = NULL;


    /**
     * @param $id Database table id
     */
    public function __construct($id) {
        $this->Id = $id;
    }


    /**
     * Ballast that shall be added to drivers for ballancing
     * First element is the ballast for the championship leader for the next race [kg].
     * Second element is the ballast for the second dirver in the leaderboard, and so on.
     * @return Array of integers
     */
    public function ballanceBallast() {
        if ($this->BallanceBallast === NULL) $this->updateFromDb();
        return $this->BallanceBallast;
    }


    /**
     * Restrictor that shall be added to drivers for ballancing
     * First element is the restrictor for the championship leader for the next race [%].
     * Second element is the restrictor for the second dirver in the leaderboard, and so on.
     * @return Array of integers
     */
    public function ballanceRestrictor() {
        if ($this->BallanceRestrictor === NULL) $this->updateFromDb();
        return $this->BallanceRestrictor;
    }


    //! @return A list of CarClass objects allowed for this championship
    public function carClasses() {
        if ($this->CarClasses === NULL) $this->updateFromDb();
        return $this->CarClasses;
    }


    //! @return Array of integer values (eg "1,2,3,5"->[1, 2, 3, 5]
    private function Csv2Array($csv) {
        $ret = array();
        foreach (explode(",", $csv) as $val) {
            if ($val == "") continue;
            $ret[] = (int) $val;
        }
        return $ret;
    }


    //! @return The newly created Championship object
    static public function createNew() {
        global $acswuiDatabase;
        $id = $acswuiDatabase->insert_row("Championships", ['Name'=>"New"]);
        return new Championship($id);
    }


    //! @return The database row Id
    public function id() {
        return $this->Id;
    }


    //! @return TRUE if this is valid
    public function isValid() {
        if ($this->Name === NULL) $this->updateFromDb();
        return ($this->Name === NULL) ? FALSE : TRUE;
    }


    //! Delete this Championship from the database
    public function delete() {
        global $acswuiDatabase;

        $acswuiDatabase->delete_row("Championships", $this->Id);

        $this->Id = NULL;
        $this->Name = NULL;
        $this->ServerPreset = NULL;
    }


    //! @return A list of all existing Championship objects
    public static function list() {
        global $acswuiDatabase;
        $list = array();
        $res = $acswuiDatabase->fetch_2d_array("Championships", ['Id']);
        foreach ($res as $row) {
            $list[] = new Championship($row['Id']);
        }
        return $list;
    }


    //! @return The name of the Championship
    public function name() {
        if ($this->Name === NULL) $this->updateFromDb();
        return $this->Name;
    }


    /**
     * The points for qualifying positions.
     * First array element are the points for first place,
     * second element for second palce and so on.
     * @return Array of integers
     */
    public function qualifyPositionPoints() {
        if ($this->QualifyPositionPoints === NULL) $this->updateFromDb();
        return $this->QualifyPositionPoints;
    }


    /**
     * The points for leading laps during race.
     * First array element are the points most lead laps,
     * second element for second most lead laps and so on.
     * @return Array of integers
     */
    public function raceLeadLapPoints() {
        if ($this->RaceLeadLapPoints === NULL) $this->updateFromDb();
        return $this->RaceLeadLapPoints;
    }


    /**
     * The points for race positions.
     * First array element are the points for first place,
     * second element for second palce and so on.
     * @return Array of integers
     */
    public function racePositionPoints() {
        if ($this->RacePositionPoints === NULL) $this->updateFromDb();
        return $this->RacePositionPoints;
    }


    /**
     * The points for race times.
     * First array element are the points for best time,
     * second element for second time and so on.
     * @return Array of integers
     */
    public function raceTimePoints() {
        if ($this->RaceTimePoints === NULL) $this->updateFromDb();
        return $this->RaceTimePoints;
    }


    //! @return The according ServerPreset object
    public function serverPreset() {
        if ($this->ServerPreset === NULL) $this->updateFromDb();
        return $this->ServerPreset;
    }


    //! @param $weights A list of ballast values for ballancing -> see ballanceBallast()
    public function setBallanceBallast($weights) {
        global $acswuiDatabase;

        // ensure to have integers
        $points_list = array();
        foreach ($weights as $p) {
            $points_list[] = (int) $p;
        }

        // update DB
        $columns = array();
        $columns['BallanceBallast'] = implode(",", $points_list);
        $acswuiDatabase->update_row("Championships", $this->Id, $columns);

        // invalidate cache
        $this->BallanceBallast = $points_list;
    }


    //! @param $restrictors A list of restrictor for ballancing -> see ballanceRestrictor()
    public function setBallanceRestrictor($restrictors) {
        global $acswuiDatabase;

        // ensure to have integers
        $points_list = array();
        foreach ($restrictors as $p) {
            $points_list[] = (int) $p;
        }

        // update DB
        $columns = array();
        $columns['BallanceRestrictor'] = implode(",", $points_list);
        $acswuiDatabase->update_row("Championships", $this->Id, $columns);

        // invalidate cache
        $this->BallanceRestrictor = $points_list;
    }


    //! @param $car_classes A list of CarClass objects
    public function setCarClasses($car_classes) {
        global $acswuiDatabase;

        $cc_ids = array();
        foreach ($car_classes as $cc) {
            if (!in_array($cc->id(), $cc_ids))
                $cc_ids[] = $cc->id();
        }

        $cols = array();
        $cols['CarClasses'] = implode(",", $cc_ids);
        $acswuiDatabase->update_row("Championships", $this->Id, $cols);

        $this->CarClasses = NULL;
    }


    //! @param $name The new name for this Championship
    public function setName(string $name) {
        global $acswuiDatabase;

        $cols = array();
        $cols['Name'] = $name;
        $acswuiDatabase->update_row("Championships", $this->Id, $cols);

        $this->Name = $name;
    }


    //! @param $points A list of integers representing points for qualifying positions -> see qualifyPositionPoints()
    public function setQualifyPositionPoints($points) {
        global $acswuiDatabase;

        // ensure to have integers
        $points_list = array();
        foreach ($points as $p) {
            $points_list[] = (int) $p;
        }

        // update DB
        $columns = array();
        $columns['QualifyPositionPoints'] = implode(",", $points_list);
        $acswuiDatabase->update_row("Championships", $this->Id, $columns);

        // invalidate cache
        $this->QualifyPositionPoints = $points_list;
    }


    //! @param $points A list of integers representing points for lead laps-> see raceLeadLapPoints()
    public function setRaceLeadLapPoints($points) {
        global $acswuiDatabase;

        // ensure to have integers
        $points_list = array();
        foreach ($points as $p) {
            $points_list[] = (int) $p;
        }

        // update DB
        $columns = array();
        $columns['RaceLeadLapPoints'] = implode(",", $points_list);
        $acswuiDatabase->update_row("Championships", $this->Id, $columns);

        // invalidate cache
        $this->RaceLeadLapPoints = $points_list;
    }


    //! @param $points A list of integers representing points for race positions -> see racePositionPoints()
    public function setRacePositionPoints($points) {
        global $acswuiDatabase;

        // ensure to have integers
        $points_list = array();
        foreach ($points as $p) {
            $points_list[] = (int) $p;
        }

        // update DB
        $columns = array();
        $columns['RacePositionPoints'] = implode(",", $points_list);
        $acswuiDatabase->update_row("Championships", $this->Id, $columns);

        // invalidate cache
        $this->RacePositionPoints = $points_list;
    }


    //! @param $points A list of integers representing points for best race times -> see raceTimePoints()
    public function setRaceTimePoints($points) {
        global $acswuiDatabase;

        // ensure to have integers
        $points_list = array();
        foreach ($points as $p) {
            $points_list[] = (int) $p;
        }

        // update DB
        $columns = array();
        $columns['RaceTimePoints'] = implode(",", $points_list);
        $acswuiDatabase->update_row("Championships", $this->Id, $columns);

        // invalidate cache
        $this->RaceTimePoints = $points_list;
    }


    //! @param $tracks A list of Track objects
    public function setTracks($tracks) {
        global $acswuiDatabase;

        $t_ids = array();
        foreach ($tracks as $t) {
            if (!in_array($t->id(), $t_ids))
                $t_ids[] = $t->id();
        }

        $cols = array();
        $cols['Tracks'] = implode(",", $t_ids);
        $acswuiDatabase->update_row("Championships", $this->Id, $cols);

        $this->Tracks = NULL;
    }


    //! @return A list of Track objects which are planned to race
    public function tracks() {
        if ($this->Tracks === NULL) $this->updateFromDb();
        return $this->Tracks;
    }


    //! Internal function to load data from the DB
    private function updateFromDb() {
        global $acswuiDatabase;
        global $acswuiLog;

        // request from db
        $columns = array();
        $columns[] = 'Name';
        $columns[] = 'ServerPreset';
        $columns[] = 'CarClasses';
        $columns[] = 'QualifyPositionPoints';
        $columns[] = 'RacePositionPoints';
        $columns[] = 'RaceTimePoints';
        $columns[] = 'RaceLeadLapPoints';
        $columns[] = 'BallanceBallast';
        $columns[] = 'BallanceRestrictor';
        $columns[] = 'Tracks';

        $res = $acswuiDatabase->fetch_2d_array("Championships", $columns, ['Id'=>$this->Id]);
        if (count($res) !== 1) {
            $acswuiLog->logError("Cannot find Championships.Id=" . $this->Id);
            return;
        }

        $this->Name = $res[0]['Name'];
        $this->ServerPreset = new ServerPreset($res[0]['ServerPreset']);

        $this->CarClasses = array();
        foreach (explode(",", $res[0]['CarClasses']) as $cc_id) {
            if ($cc_id == "") continue;
            $this->CarClasses[] = new CarClass((int) $cc_id);
        }

        $this->QualifyPositionPoints = $this->Csv2Array($res[0]['QualifyPositionPoints']);
        $this->RacePositionPoints = $this->Csv2Array($res[0]['RacePositionPoints']);
        $this->RaceTimePoints = $this->Csv2Array($res[0]['RaceTimePoints']);
        $this->RaceLeadLapPoints = $this->Csv2Array($res[0]['RaceLeadLapPoints']);
        $this->BallanceBallast = $this->Csv2Array($res[0]['BallanceBallast']);
        $this->BallanceRestrictor = $this->Csv2Array($res[0]['BallanceRestrictor']);

        $this->Tracks = array();
        foreach (explode(",", $res[0]['Tracks']) as $t_id) {
            if ($t_id == "") continue;
            $this->Tracks[] = new Track((int) $t_id);
        }
    }
}

?>