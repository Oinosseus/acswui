<?php

/**
 * Cached wrapper to car databse Tracks table element
 */
class User {

    private $Id = NULL;
    private $Login = NULL;
    private $Password = NULL;
    private $Steam64GUID = NULL;
    private $Color = NULL;

    private static $UserList = NULL;
    private static $DriverList = NULL;

    //! @param $id Database table id
    public function __construct($id) {
        $this->Id = $id;
    }

    public function __toString() {
        return "User(Id=" . $this->Id . ")";
    }


    //! @return The color code for this user as string (eg #12ab9f)
    public function color() {
        if ($this->Color === NULL) $this->updateFromDb();
        return $this->Color;
    }

    //! @return The database table id
    public function id() {
        return $this->Id;
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

        $res = $acswuiDatabase->fetch_2d_array("Users", ['Login', 'Password', 'Steam64GUID', 'Color'], ['Id'=>$this->Id]);
        if (count($res) !== 1) {
            $acswuiLog->logError("Cannot find User.Id=" . $this->Id);
            return;
        }

        $this->Login = $res[0]['Login'];
        $this->Password = $res[0]['Password'];
        $this->Steam64GUID = $res[0]['Steam64GUID'];
        $this->Color = $res[0]['Color'];
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
