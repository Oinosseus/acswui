<?php

/**
 * Cached wrapper to car databse Tracks table element
 */
class User implements JsonSerializable {

    private $Id = NULL;
    private $Login = NULL;
    private $Password = NULL;
    private $Steam64GUID = NULL;
    private $Color = NULL;
    private $Privacy = NULL;
    private $Locale = NULL;

    private static $UserList = NULL;
    private static $DriverList = NULL;

    /**
     * @param $id Database table id
     * @param $json_data When not NULL, this constructs a DriverRanking object from json structed object data (other parameters will be ignored)
     */
    public function __construct($id, $json_data=NULL) {
        if ($json_data !== NULL) {
            $this->Id = $json_data['Id'];
        } else {
            $this->Id = $id;
        }
    }

    public function __toString() {
        return "User(Id=" . $this->Id . ")";
    }


    //! @return The color code for this user as string (eg #12ab9f)
    public function color() {
        if ($this->Color === NULL) $this->updateFromDb();
        return $this->Color;
    }


    /**
     * @param $privacy Force a certain privacy level (defautl=NULL -> use current privacy level)
     * @return The usrname that shall be displayed on HTML output (depending on privacy settings)
     */
    public function displayName(int $privacy = NULL) {
        global $acswuiUser;

        if ($privacy === NULL) $privacy = $this->privacy();

        if ($acswuiUser->Id == $this->id()) {
            return $this->login();
        } else if ($privacy == 2) {
            return $this->login();
        } else if ($privacy == 1 && $acswuiUser->IsLogged) {
            return $this->login();
        }

        return "***";
    }


    //! @return The database table id
    public function id() {
        return $this->Id;
    }

    //! Implement JsonSerializable interface
    public function jsonSerialize() {
        $json = array();
        $json['Id'] = $this->Id;
        return $json;
    }



    //! @return The preferred locale of the user
    public function locale() {
        if ($this->Locale === NULL) $this->updateFromDb();
        return $this->Locale;
    }


    //! @return The login name of the user
    public function login() {
        if ($this->Login === NULL) $this->updateFromDb();
        return $this->Login;
    }

    //! @return The (crypted) password of the user
    public function password() {
        if ($this->Password === NULL) $this->updateFromDb();
        return $this->Password;
    }


    /**
     * Set a new color for the user
     * @param $color E.g. '#12ab98'
     */
    public function setColor(string $color) {
        global $acswuiDatabase, $acswuiLog;

        if (strlen($color) !== 7 || substr($color, 0, 1) != "#") {
            $acswuiLog->logError("Malformed color: '$color'!");
            return;
        }

        $acswuiDatabase->update_row("Users", $this->id(), ['Color'=>$color]);
        $this->Color = $color;
    }



    public function setLocale(string $new_locale) {
        global $acswuiDatabase, $acswuiConfig;

        // sanity check
        if (!in_array($new_locale, $acswuiConfig->Locales)) {
            $new_locale = "";
        }
        if ($new_locale === $this->Locale) return;

        // update DB
        $acswuiDatabase->update_row("Users", $this->id(), ['Locale'=>$new_locale]);
        $this->Locale = $new_locale;
    }



    public function setPrivacy(int $privacy) {
        global $acswuiDatabase, $acswuiLog;

        if ($privacy < 0 || $privacy > 2) {
            $acswuiLog->logError("Invalid privacy: $privacy!");
            return;
        }

        $acswuiDatabase->update_row("Users", $this->id(), ['Privacy'=>$privacy]);
        $this->Privacy = $privacy;
    }


    /**
     * Following values are possible:
     * 0 (Private): Absolute privacy (not showing any personal information to public
     * 1 (Community): Only show personal information to logged in users
     * 2 (Public): Allow anyone to see personal information
     * @return The privacy level for this user
     */
    public function privacy() {
        if ($this->Privacy == NULL) $this->updateFromDb();
        return $this->Privacy;
    }


    //! @return TRUE if the requsted privacy level is currenlty fulfuilled
    public function privacyFulfilled() {
        global $acswuiUser;

        $privacy = $this->privacy();

        if ($acswuiUser->Id == $this->id()) {
            return TRUE;
        } else if ($privacy == 2) {
            return TRUE;
        } else if ($privacy == 1 && $acswuiUser->IsLogged) {
            return TRUE;
        }

        return FALSE;
    }


    //! @return The Steam64GUID of the user
    public function steam64GUID() {
        if ($this->Steam64GUID === NULL) $this->updateFromDb();
        return $this->Steam64GUID;
    }

    //! @return True if the user is a driver (instead of admin only)
    public function isDriver() {
        if ($this->Steam64GUID === NULL) $this->updateFromDb();
        return ($this->Steam64GUID === "") ? FALSE : TRUE;
    }

    //! Update cached values
    private function updateFromDb() {
        global $acswuiDatabase;
        global $acswuiLog;

        $fields = ['Login', 'Password', 'Steam64GUID', 'Color', 'Privacy', 'Locale'];
        $res = $acswuiDatabase->fetch_2d_array("Users", $fields, ['Id'=>$this->Id]);
        if (count($res) !== 1) {
            $acswuiLog->logError("Cannot find User.Id=" . $this->Id);
            return;
        }

        $this->Login = $res[0]['Login'];
        $this->Password = $res[0]['Password'];
        $this->Steam64GUID = $res[0]['Steam64GUID'];
        $this->Color = $res[0]['Color'];
        $this->Privacy = (int) $res[0]['Privacy'];
        $this->Locale = $res[0]['Locale'];

        // automatically set privacy to community when user has a password
        if ($this->Password !== "" && $this->Privacy < 1) $this->Privacy = 1;
    }


    //! @return A list of all users
    public static function listUsers() {
        global $acswuiDatabase;

        if (User::$UserList !== NULL) return User::$UserList;

        User::$UserList = array();

        foreach ($acswuiDatabase->fetch_2d_array("Users", ['Id']) as $u) {
            User::$UserList[] = new User($u['Id']);
        }

        return User::$UserList;
    }

    //! @return A list of users that are active drivers
    public static function listDrivers() {
        if (User::$DriverList !== NULL) return User::$DriverList;

        User::$DriverList = array();

        foreach (User::listUsers() as $u) {
            if ($u->isDriver()) User::$DriverList[] = $u;
        }

        return User::$DriverList;
    }
}

?>
