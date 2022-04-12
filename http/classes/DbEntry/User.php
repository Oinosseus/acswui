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

    private $LastLap = NULL;
    private $DaysSinceLastLap = NULL;
    private $DaysSinceLastLogin = NULL;
    private $Teams = NULL;

    private static $CommunityList = NULL;
    private static $DriverList = NULL;
    private static $UserList = NULL;

    private $CountLaps = NULL;
    private $PreferredUserLanguage = NULL;


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


    /**
     * Nat-Compares to User objects by name()
     * This is intended for usort() of arrays
     * @param $obj1 User object
     * @param $obj2 User object
     * @return -1, +1 or 0 on equal
     */
    public static function compareName(User $obj1, User $obj2) {
        return strnatcasecmp($obj1->name(), $obj2->name());
    }


    //! @return The amount of days since the last driven lap (999999 if the user never drove a lap)
    public function daysSinceLastLap() {
        if ($this->DaysSinceLastLap === NULL) {
            $query = "SELECT Timestamp FROM Laps WHERE User=" . $this->id() . " ORDER BY Id DESC LIMIT 1;";
            $res = \Core\Database::fetchRaw($query);
            if (count($res) == 1) {
                $t_last_lap = \Core\Database::timestamp2DateTime($res[0]['Timestamp']);
                $_now = new \DateTime("now");
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
            $_now = new \DateTime("now");
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


    //! @return A User object that represents the guest user (virtual, not existing in database)
    public static function guestUser() {
        $u = new USer(NULL);
        return $u;
    }


    //! @return A date time value formatted for the user
    public function formatDateTime(\DateTime $dt) {
        $tz = new \DateTimezone($this->getParam("UserTimezone"));
        $dt->setTimezone($tz);
        return $dt->format($this->getParam("UserFormatDate"));
    }


    //! @return A date time value formatted for the user (without seconds
    public function formatDateTimeNoSeconds(\DateTime $dt) {
        $tz = new \DateTimezone($this->getParam("UserTimezone"));
        $dt->setTimezone($tz);
        $format = $this->getParam("UserFormatDate");

        // remove seconds
        $format = str_replace(":s", "", $format);

        return $dt->format($format);
    }


    /**
     * @param $distance A distance in meters
     * @return A string with formated distance
     */
    public function formatDistance(int $distance) {

        // Giga
        if ($distance >= 1e9)
            return sprintf("%0.2f Gm", $distance / 1e9);

        // Mega
        else if ($distance >= 100e6)
            return sprintf("%0.0f Mm", $distance / 1e6);
        else if ($distance >= 10e6)
            return sprintf("%0.1f Mm", $distance / 1e6);
        else if ($distance >= 1e6)
            return sprintf("%0.2f Mm", $distance / 1e6);

        // kilo
        else if ($distance >= 100e3)
            return sprintf("%0.0f km", $distance / 1e3);
        else if ($distance >= 10e3)
            return sprintf("%0.1f km", $distance / 1e3);
        else if ($distance >= 1e3)
            return sprintf("%0.2f km", $distance / 1e3);

        // meter
        else
            return $distance . " m";
    }


    /**
     * @param $laptime Laptime in milliseconds
     * @return A string with formated laptime
     */
    public function formatLaptime(int $laptime) {
        $milliseconds = $laptime % 1000;
        $laptime /= 1000;
        $seconds = $laptime % 60;
        $minutes = $laptime / 60;
        return sprintf("%0d:%02d.%03d" , $minutes, $seconds, $milliseconds);
    }



    /**
     * @param $laptime Difference between two Laptimes in milliseconds
     * @return A string with formated laptime
     */
    public function formatLaptimeDelta(int $laptime_delta) {
        if ($laptime_delta < 1000) {
            return sprintf("%d ms", $laptime_delta);
        } else if ($laptime_delta < 60000) {
            $laptime_delta /= 10;
            $centi_seconds = $laptime_delta % 100;
            $laptime_delta /= 100;
            $seconds = $laptime_delta % 60;
            return sprintf("%d.%02d s", $seconds, $centi_seconds);
        } else {
            $laptime_delta /= 1000;
            $seconds = $laptime_delta % 60;
            $minutes = $laptime_delta / 60;
            return sprintf("%d:%02d", $minutes, $seconds);
        }
    }


    /**
     * @param $power The power in [W]
     * @return The power formatted for the User
     */
    public function formatPower(float $power) {
        $unit = \Core\UserManager::currentUser()->getParam("UserUnitPower");
        switch ($unit) {
            case "OLD":
                return \Core\SiPrefix::threeDigits($power * 0.00136) . " PS";

            case "SI":
                $si = new \Core\SiPrefix($power);
                return $si->humanValue("W");

            default:
                \Core\Log::error("Unkown Unit '$unit'!");
                return "?";
        }
    }


    /**
     * @param $power The power in [g/W]
     * @return The power formatted for the User
     */
    public function formatPowerSpecific(float $power) {
        $unit = \Core\UserManager::currentUser()->getParam("UserUnitPowerSpecific");
        switch ($unit) {
            case "SI1":
                $si = new \Core\SiPrefix($power);
                return $si->humanValue("g/W");

            case "SI2":
                $si = new \Core\SiPrefix(1e3/$power);
                return $si->humanValue("W/kg");

            case "OLD1":
                return \Core\SiPrefix::threeDigits($power /  1.36) . " kg/PS";

            case "OLD2":
                return \Core\SiPrefix::threeDigits(1360 / $power) . " PS/t";

            default:
                \Core\Log::error("Unkown Unit '$unit'!");
                return "?";
        }
    }


    /**
     * @param $interval An arbitrary \Core\TimeInterval object
     * @return A string with formated interval
     */
    public function formatTimeInterval(\Core\TimeInterval $interval) {
        $seconds = $interval->seconds();

        if ($seconds < 60) {
            return sprintf("%d s", $seconds);

        } else if ($seconds < (60*60)) {
            $minutes = $seconds / 60;
            $seconds = $seconds % 60;
            return sprintf("%d:%02d min", $minutes, $seconds);


        } else {
//         } else if ($seconds < (60*60*24)) {
            $hours = $seconds / (60*60);
            $remaining_secs = $seconds % (60*60);
            $minutes = $remaining_secs / 60;
            $seconds = $remaining_secs % 60;
            return sprintf("%d:%02d:%02d h", $hours, $minutes, $seconds);
        }
    }


    //! @return A time value formatted for the user (date is ignored)
    public function formatTime(\DateTime $dt) {
        $tz = new \DateTimezone($this->getParam("UserTimezone"));
        $dt->setTimezone($tz);
        return $dt->format("H:i:s");
    }



    //! @return A time value formatted for the user (date is ignored)
    public function formatTimeNoSeconds(\DateTime $dt) {
        $tz = new \DateTimezone($this->getParam("UserTimezone"));
        $dt->setTimezone($tz);
        return $dt->format("H:i");
    }



    /**
     * @param $weight The weight in [kg]
     * @return The weight formatted for the User
     */
    public function formatWeight(float $weight) {
        $unit = \Core\UserManager::currentUser()->getParam("UserUnitWeight");
        switch ($unit) {
            case "OLD":
                if ($weight > 1e3) {
                    $weight = \Core\SiPrefix::threeDigits($weight/1e3);
                    return "$weight t";
                } else {
                    $si = new \Core\SiPrefix($weight*1e3);
                    return $si->humanValue("g");
                }

            case "SI":
                $si = new \Core\SiPrefix($weight*1e3);
                return $si->humanValue("g");

            default:
                \Core\Log::error("Unkown Unit '$unit'!");
                return "?";
        }
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) {
        if ($id == 0) {  // root user
            return new User(0);
        }

        return parent::getCachedObject("Users", "User", $id);
    }


    /**
     * @param $steam64guid The Steam ID
     * @return The requested User object (can be NULL)
     */
    public static function fromSteam64GUID(string $steam64guid) {
        $res = \Core\Database::fetch("Users", ['Id'], ['Steam64GUID'=>$steam64guid]);
        return (count($res) == 1) ? User::fromId($res[0]['Id']) : NULL;
    }


    //! @return A list of Group objects where the user is member in
    public function groups() {
        if ($this->isRoot()) { // root user
            return Group::listGroups();

        } else if ($this->id() === NULL || $this->id() == 0) {  // guest user
            return array(Group::fromName(\Core\Config::GuestGroup));

        } else if ($this->Groups === NULL) { // regular user
            $this->Groups = array();
            $res = \Core\Database::fetch("UserGroupMap", ['Group'], ['User'=>$this->id()]);
            foreach ($res as $row) {
                $g = \DbEntry\Group::fromId($row['Group']);
                if ($g === NULL) {
                    \Core\Log::warning("Received NULL for group Id {$row['Group']}");
                } else {
                    $this->Groups[] = $g;
                }
            }

            // check for automatic group assignments
            if ($this->isDriver()) $this->Groups[] = Group::fromName(\Core\Config::DriverGroup);
        }
        return $this->Groups;
    }


    //! @return A html string with the user name and containing a link to the user profile page
    public function html() {
        $html = "";
        if ($this->privacyFulfilled()) {
            $html .= "<a href=\"index.php?HtmlContent=UserProfile&UserId=" . $this->id() . "\">";
            $html .= $this->name();
            $html .= "</a>";
        } else {
            $html .= "<div class=\"UserHiddenName\">User-" . $this->id() . "</div>";
        }
        return $html;
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


    //! @return The Lap object of the last driven lap (or NULL)
    public function lastLap() {
        if ($this->LastLap === NULL) {
            $query = "SELECT Id FROM Laps WHERE User=" . $this->id() . " ORDER BY Id DESC LIMIT 1;";
            $res = \Core\Database::fetchRaw($query);
            if (count($res) > 0) {
                $this->LastLap = Lap::fromId($res[0]['Id']);
            }
        }
        return $this->LastLap;
    }


    //! @return A DateTime object
    public function lastLogin() {
        return \Core\Database::timestamp2DateTime($this->loadColumn("LastLogin"));
    }



    //! @return A list of users which are considered to be active community members
    public static function listCommunity() {

        if (User::$CommunityList === NULL) {
            User::$CommunityList = array();

            $t_thld = new \DateTime("now");
            $days = \Core\ACswui::getParam("NonActiveDrivingDays");
            $delta_t = new \DateInterval("P" . $days . "D");
            $t_thld = $t_thld->sub($delta_t);

            $query = "SELECT DISTINCT User FROM Laps WHERE Timestamp >= \"" . \Core\Database::dateTime2timestamp($t_thld)  . "\" ORDER BY User ASC";
            $res = \Core\Database::fetchRaw($query);
            foreach ($res as $row) {
                $u = User::fromId($row['User']);
                if ($u->isCommunity()) User::$CommunityList[] = $u;
            }
        }

        return User::$CommunityList;
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

            $t_thld = new \DateTime("now");
            $days = \Core\ACswui::getParam("NonActiveDrivingDays");
            $delta_t = new \DateInterval("P" . $days . "D");
            $t_thld = $t_thld->sub($delta_t);

            $query = "SELECT DISTINCT User FROM Laps WHERE Timestamp >= \"" . \Core\Database::dateTime2timestamp($t_thld)  . "\" ORDER BY User ASC";
            $res = \Core\Database::fetchRaw($query);
            foreach ($res as $row) {
                User::$DriverList[] = User::fromId($row['User']);
            }
        }

        return User::$DriverList;
    }


    //! @return The preferred locale by this user
    public function locale() {

        // return directly set locale
        $locale = $this->getParam("UserLocale");
        if ($locale !== "auto") return $locale;

        // auto detect locale
        if ($this->PreferredUserLanguage == NULL) {

            // check preferred browser languages
            $preferred = array();
            if (function_exists("apache_request_headers") && array_key_exists("Accept-Language", apache_request_headers())) {
                $preferred = explode(",", apache_request_headers()["Accept-Language"]);
            } else if (array_key_exists('HTTP_ACCEPT_LANGUAGE', $_SERVER)){
                $preferred = explode(",", $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            } else {
                \Core\Log::error("Cannot detect client languages!");
            }

            // decide for translation
            foreach ($preferred as $pref) {

                // remove qualifier
                $pref_no_qual = strstr($pref, ";q=", True);
                if ($pref_no_qual !== False) {
                    $pref = $pref_no_qual;
                }

                // check against each available locales
                foreach (\Core\Config::Locales as $locale) {

                    $locale_short = explode("_", $locale)[0];

                    // if match found return language directory
                    if (strpos($pref, $locale_short) !== False) {
                        \Core\Log::debug("preferred client language: ". $locale);
                        $this->PreferredUserLanguage = $locale;
                        break;
                    }
                }

                if ($this->PreferredUserLanguage !== NULL) break;
            }

            // inform no language match found
            if ($this->PreferredUserLanguage === NULL) {
                $msg = "Could not find translation!\n";
                $msg .= "Preferred translations: '" . implode("', '", $preferred) . "'\n";
                \Core\Log::warning($msg);
            }
        }

        return $this->PreferredUserLanguage;
    }


    /**
     * @warning For HTML output use html() instead
     * @return The username
     */
    public function name() {
        if ($this->isRoot()) {
            return "root";
        } else if ($this->id() == NULL) {
            return _("Guest");
        } else if ($this->privacyFulfilled()) {
            return $this->loadColumn("Name");
        } else {
            return "User-" . $this->id();
        }
    }


    //! @return The national flag of the selected country
    public function nationalFlag() {
        return $this->parameterCollection()->child("UserCountry")->valueLabel();
    }


    public function parameterCollection() {
        if ($this->ParameterCollection === NULL) {
            $base = \Core\ACswui::parameterCollection()->child("User");
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
                // both, me and the other must be drivers
                if ($current_user && $current_user->isDriver() && $this->isDriver()) $privacy_fillfilled = TRUE;
                break;
            case 'Community':
                // both, me and the other must be community level
                if ($current_user && $current_user->isCommunity() && $this->isCommunity()) $privacy_fillfilled = TRUE;
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


    //! @return A list of Team objects
    public function teams() {
        if ($this->Teams === NULL) {
            $this->Teams = array();
            $sid = \Core\Database::escape($this->steam64GUID());
            $query = "SELECT DISTINCT Team FROM `CarSkins` WHERE Steam64GUID = '$sid';";
            foreach (\Core\Database::fetchRaw($query) as $row) {
                $this->Teams[] = Team::fromId($row['Team']);
            }
        }
        return $this->Teams;
    }

}

?>
