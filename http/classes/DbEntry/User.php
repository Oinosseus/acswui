<?php

namespace DbEntry;

/**
 * Cached wrapper to car databse Tracks table element
 */
class User extends DbEntry { #implements JsonSerializable {

    private $Steam64GUID = NULL;
    private $Locale = NULL;
    private $Groups = NULL;
    private $Permissions = NULL;
    private $ParameterCollection = NULL;

    private $DaysSinceLastLap = NULL;
    private $DaysSinceLastLogin = NULL;

    private static $UserList = NULL;
    private static $DriverList = NULL;

    private $CountLaps = NULL;


    /**
     * @param $id Database table id
     */
    protected function __construct($id) {
        parent::__construct("Users", $id);
    }


    //! @return The number of driven laps by this user
    public function countLaps() {
        if ($this->CountLaps === NULL) {
            $res = \Core\Database::fetchRaw("SELECT COUNT(Id) FROM Laps WHERE User=" . $this->id());
            $this->CountLaps = (int) $res[0]['COUNT(Id)'];
        }
        return $this->CountLaps;
    }


    //! @return The amount of days since the last driven lap (999999 if the user never drove a lap)
    public function daysSinceLastLap() {
        if ($this->DaysSinceLastLap === NULL) {
            $query = "SELECT Timestamp FROM Laps WHERE User=" . $this->id() . " ORDER BY Id DESC LIMIT 1;";
            $res = \Core\Database::fetchRaw($query);
            if (count($res) == 1) {
                $t_last_lap = \Core\Database::timestamp2DateTime($res[0]['Timestamp']);
                $_now = new \DateTime("now", new \DateTimeZone(\Core\Config::LocalTimeZone));
                $t_diff = $_now->diff($t_last_lap);
                $this->DaysSinceLastLap = $t_diff->days;
            } else {
                $this->DaysSinceLastLap = 999999;
            }
        }
        return $this->DaysSinceLastLap;
    }


    //! @return The amount of days since the last login of the user (when never logged in '0000-00-00T00:00:00' is assumed)
    public function daysSinceLastLogin() {
        if ($this->DaysSinceLastLogin === NULL) {
            $t_last_login = $this->lastLogin();
            $_now = new \DateTime("now", new \DateTimeZone(\Core\Config::LocalTimeZone));
            $t_diff = $_now->diff($t_last_login);
            $this->DaysSinceLastLogin = $t_diff->days;
        }
        return $this->DaysSinceLastLogin;
    }


    /**
     * Access parameters statically
     *
     * get("fooBar") is the same as parameterCollection()->child"fooBar")->value()
     * @return The curent value of a certain parameter
     */
    public function getParam(string $parameter_key) {
        return $this->parameterCollection()->child($parameter_key)->value();
    }


    /**
     * @return The username that shall be displayed on HTML output (depending on privacy settings)
     */
    public function name() {
        if ($this->isRoot()) return "root";
        return ($this->privacyFulfilled()) ? $this->loadColumn("Name") : "***";
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) {
        return parent::getCachedObject("Users", "User", $id);
    }


    //! @return A date time value formatted for the user
    public function formatDateTime(\DateTime $dt) {
        $tz = new \DateTimezone($this->getParam("UserTimezone"));
        $dt->setTimezone($tz);
        return $dt->format($this->getParam("UserFormatDate"));
    }


    //! @return A list of Group objects where the user is member in
    public function groups() {
        if ($this->isRoot()) {
            return Group::listGroups();

        } else if ($this->Groups === NULL) {
            $this->Groups = array();
            $res = \Core\Database::fetch("UserGroupMap", ['Group'], ['User'=>$this->id()]);
            foreach ($res as $row) {
                $this->Groups[] = \DbEntry\Group::fromId($row['Group']);
            }
        }
        return $this->Groups;
    }


    //! @return TRUE if the user is an active driver and uses the ACswui system
    public function isCommunity() {
        if (!$this->isDriver()) return FALSE;
        return ($this->daysSinceLastLogin() <= \Core\ACswui::getParam("CommunityLastLoginDays")) ? TRUE : FALSE;
    }


    //! TRUE if the user is an active driver
    public function isDriver() {
        return ($this->daysSinceLastLap() <= \Core\ACswui::getParam("NonActiveDrivingDays")) ? TRUE : FALSE;
    }


    //! @return True, if this represents the root user, else False
    public function isRoot() {
        return $this->id() === 0;
    }


    //! @return A DateTime object
    public function lastLogin() {
        return \Core\Database::timestamp2DateTime($this->loadColumn("LastLogin"));
    }



    //! @return A list of all users
    public static function listUsers() {

        if (User::$UserList === NULL) {
            User::$UserList = array();
            foreach (\Core\Database::fetch("Users", ['Id']) as $u) {
                User::$UserList[] = User::fromId($u['Id']);
            }
        }

        return User::$UserList;
    }


    //! @return A list of users which are considered to be active drivers
    public static function listDrivers() {

        if (User::$DriverList === NULL) {
            User::$DriverList = array();

            $t_thld = new \DateTime("now", new \DateTimeZone(\Core\Config::LocalTimeZone));
            $days = \Core\ACswui::getParam("NonActiveDrivingDays");
            $delta_t = new \DateInterval("P" . $days . "D");
            $t_thld = $t_thld->sub($delta_t);

            $query = "SELECT DISTINCT User FROM Laps WHERE Timestamp >= \"" . \Core\Database::dateTime2timestamp($t_thld)  . "\"";
            $res = \Core\Database::fetchRaw($query);
            foreach ($res as $row) {
                User::$DriverList[] = User::fromId($row['User']);
            }
        }

        return User::$DriverList;
    }


    public function parameterCollection() {
        if ($this->ParameterCollection === NULL) {
            $base = (new \Core\ACswui)->parameterCollection()->child("User");
            $this->ParameterCollection = new \Parameter\Collection($base, NULL);
            $data_json = $this->loadColumn('ParameterData');
            $data_array = json_decode($data_json, TRUE);
            if ($data_array !== NULL) $this->ParameterCollection->dataArrayImport($data_array);
        }

        return $this->ParameterCollection;
    }


    //! @return TRUE if this user has a certain permission (else FALSE)
    public function permitted(string $permission) {

        // root has all permissions
        if ($this->id() == 0 && $this->name() == "root") return TRUE;

        // check granted permissions of user
        if ($this->Permissions === NULL) {
            $this->Permissions = array();
            foreach ($this->groups() as $g) {
                foreach ($g->grantedPermissions() as $p) {
                    $this->Permissions[] = $p;
                }
            }
        }
        return in_array($permission, $this->Permissions);
    }


    //! @return TRUE if the requsted privacy level is currenlty fulfuilled
    public function privacyFulfilled() {
        $privacy_fillfilled = FALSE;

        // root user
//         if ($this->isRoot()) return TRUE;

        $current_user = \Core\UserManager::loggedUser();

        // privacy is always fulfilled when the current user is the requested user
        if ($current_user && $current_user->id() == $this->id()) return TRUE;

        // check permission
        switch ($this->getParam("UserPrivacy")) {
            case 'Public';
                $privacy_fillfilled = TRUE;
                break;
            case 'ActiveDriver':
                if ($current_user && $current_user->isDriver()) $privacy_fillfilled = TRUE;
                break;
            case 'Community':
                if ($current_user && $current_user->isCommunity()) $privacy_fillfilled = TRUE;
                break;
            case 'Private':
                break;
            default:
                \Core\Log::error("Unexpected privacy level '" . $this->getParam("UserPrivacy") . "'!");
                break;
        }

        return $privacy_fillfilled;
    }


    /**
     * Saves the current values of the users parameterCollection.
     * Remember to retrieve enventally posted changes before.
     *
     * $user->parameterCollection()->storeHttpRequest();
     * $user->saveParameterCollection()
     */
    public function saveParameterCollection() {
        if ($this->id() == 0) return;

        $column_data = array();

        // parameter data
        $data_array = $this->parameterCollection()->dataArrayExport();
        $data_json = json_encode($data_array);
        $column_data['ParameterData'] = $data_json;

        $this->storeColumns($column_data);
    }


    //! @return The Steam64GUID of the user
    public function steam64GUID() {
        if ($this->isRoot()) return 0;
        if ($this->Steam64GUID === NULL) $this->Steam64GUID = $this->loadColumn('Steam64GUID');
        return $this->Steam64GUID;
    }


}

?>
