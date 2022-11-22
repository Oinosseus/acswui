<?php

declare(strict_types=1);
namespace DbEntry;

class SessionPenalty extends DbEntry {


    /**
     * @param $id Database table id
     */
    protected function __construct($id) {
        parent::__construct("SessionPenalties", $id);
    }


    //! @return The description of why the penalty has been assigned
    public function cause() : string {
        return $this->loadColumn("Cause");
    }


    /**
     * Create a new penalty
     * @param $session The session which the penalty shall be assigned to
     * @param $driver The driver which the penalty shall be assigned to (can be a User or TeamCar)
     * @return The new created SessionPenalty object
     */
    public static function create(Session $session, User|TeamCar $driver) : ?SessionPenalty {

        // prepare columns
        $columns = array();
        $columns["Session"] = $session->id();
        if (is_a($driver, "\\DbEntry\\TeamCar")) {
            $columns["TeamCar"] = $driver->id();
        } else if (is_a($driver, "\\DbEntry\\User")) {
            $columns['User'] = $driver->id();
        } else {
            \Core\Log::error("Unexpected type!");
            return NULL;
        }

        // create new object
        $id = \Core\Database::insert("SessionPenalties", $columns);
        return SessionPenalty::fromId($id);
    }


    //! @return The User or TeamCar object where the penalty is assigned to (can be NULL)
    public function driver() : TeamCar|User|NULL {
        $user_id = (int) $this->loadColumn("User");
        $teamcar_id = (int) $this->loadColumn("TeamCar");

        // sanity check
        if ($user_id > 0 && $teamcar_id > 0) {
            \Core\Log::error("Both, User and TeamCar are assigned at $this!");
            return NULL;
        }

        // return requested objects
        if ($teamcar_id > 0) return TeamCar::fromId($teamcar_id);
        if ($user_id > 0) return User::fromId($user_id);

        return NULL;
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * When $id is NULL or 0, NULL is returned
     * @return An object by its database Id
     */
    public static function fromId(int $id) {
        if ($id === NULL or $id == 0) return NULL;
        return parent::getCachedObject("SessionPenalties", "SessionPenalty", $id);
    }


    /**
     * List all assigned penalties in a session
     *
     * When $driver is a User object, then the user is ignored for TeamCars (only penalties for single driver)
     *
     * @param $session The requested session
     * @param $driver If set, only penalties for a certain driver are listed
     * @return A list of SessionPenalty objects
     */
    public static function listPenalties(Session $session, User|TeamCar $driver=NULL) : array {
        $ret = array();

        if ($driver === NULL) {
            // query penalties
            $query = "SELECT Id FROM SessionPenalties WHERE Session={$session->id()} ORDER BY User, TeamCar;";
            $res = \Core\Database::fetchRaw($query);
            foreach ($res as $row) {
                $id = (int) $row['Id'];
                $ret[] = SessionPenalty::fromId($id);
            }

        } else {
            // resolve driver type
            $search_key = "";
            if (is_a($driver, "\\DbEntry\\User")) {
                $search_key = "User";
            } else if (is_a($driver, "\\DbEntry\\TeamCar")) {
                $search_key = "TeamCar";
            } else {
                \Core\Log::error("Unexpected type!");
                return array();
            }

            // query penalties
            $query = "SELECT Id FROM SessionPenalties WHERE Session={$session->id()} AND $search_key={$driver->id()}";
            $res = \Core\Database::fetchRaw($query);
            foreach ($res as $row) {
                $id = (int) $row['Id'];
                $ret[] = SessionPenalty::fromId($id);
            }
        }

        return $ret;
    }


    //! @return The User that has imposed the penalty
    public function officer() : ?User {
        $id = (int) $this->loadColumn("Officer");
        if ($id == 0) return NULL;
        else return User::fromId($id);
    }


    //! @return The assigned penalty is a DNF
    public function penDnf() : bool {
        return ($this->loadColumn("PenDnf") == 0) ? FALSE : TRUE;
    }


    //! @return The assigned penalty is a DSQ
    public function penDsq() : bool {
        return ($this->loadColumn("PenDsq") == 0) ? FALSE : TRUE;
    }


    //! @return The assigned penalty for laps
    public function penLaps() : int {
        return (int) $this->loadColumn("PenLaps");
    }


    //! @return The assigned penalty for points (points for championships)
    public function penPts() : int {
        return (int) $this->loadColumn("PenPts");
    }


    //! @return The assigned penalty for safety points
    public function penSf() : int {
        return (int) $this->loadColumn("PenSf");
    }


    //! @return The assigned penalty for total time
    public function penTime() : int {
        return (int) $this->loadColumn("PenTime");
    }


    //! @return The according session where this penalty was imposed
    public function session() : Session {
        $id = (int) $this->loadColumn("Session");
        return Session::fromId($id);
    }


    //! @param $value A new description of why the penalty has been assigned
    public function setCause(string $value) {
        $this->session()->setNeedsFinalResultsCalculation();
        return $this->storeColumns(["Cause"=>$value]);
    }



    //! @param $officer The user that is responsible for imposing the penalty
    public function setOfficer(User $officer=NULL) {
        $this->session()->setNeedsFinalResultsCalculation();
        $cols = array();
        $cols['Officer'] = ($officer === NULL) ? 0 : $officer->id();
        $this->storeColumns($cols);
    }


    //! @param The new penalty value
    public function SetPenDnf(bool $value) {
        $this->session()->setNeedsFinalResultsCalculation();
        $this->storeColumns(["PenDnf" => (($value) ? 1 : 0)]);
    }


    //! @param The new penalty value
    public function SetPenDsq(bool $value) {
        $this->session()->setNeedsFinalResultsCalculation();
        $this->storeColumns(["PenDsq" => (($value) ? 1 : 0)]);
    }


    //! @param The new penalty value
    public function SetPenPts(int $value) {
        $this->session()->setNeedsFinalResultsCalculation();
        $this->storeColumns(["PenPts" => $value]);
    }


    //! @param The new penalty value
    public function SetPenLaps(int $value) {
        $this->session()->setNeedsFinalResultsCalculation();
        $this->storeColumns(["PenLaps" => $value]);
    }


    //! @param The new penalty value
    public function SetPenSf(int $value) {
        $this->session()->setNeedsFinalResultsCalculation();
        $this->storeColumns(["PenSf" => $value]);
    }


    //! @param The new penalty value
    public function SetPenTime(int $value) {
        $this->session()->setNeedsFinalResultsCalculation();
        $this->storeColumns(["PenTime" => $value]);
    }
}
