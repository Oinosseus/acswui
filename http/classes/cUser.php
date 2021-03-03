<?php

class cUser {

    private $User = NULL;

    public function __construct() {

        // initialize session variables
        if (!isset($_SESSION['user_id']))    $_SESSION['user_id']   = 0;
        if (!isset($_SESSION['user_login'])) $_SESSION['user_login'] = "";
        if (!isset($_SESSION['user_ip']))    $_SESSION['user_ip']   = "";
        if (!isset($_SESSION['user_time']))  $_SESSION['user_time'] = 0;

        // check if login is expired
        if (   $_SESSION['user_ip'] != $_SERVER['REMOTE_ADDR']
            || $_SESSION['user_time'] > (time() + 60*60)) {

            $this->logout();
        }
    }

    // this allows read-only access to properties
    public function __get($name) {

        // access global data
        global $acswuiLog;

        if ($name == "IsLogged") {
            return (strlen($_SESSION['user_login']) > 0) ? true : false;

        } else if ($name == "IsRoot") {
            return ($_SESSION['user_login'] === "root") ? true : false;

        } else if ($name == "Id") {
            return $_SESSION['user_id'];

        } else if ($name == "Login") {
            return $_SESSION['user_login'];

        } else {
            $acswuiLog->logError("Invalid property access to " . $name);
            return "";

        }
    }

    public function logout() {
        $_SESSION['user_id']   = 0;
        $_SESSION['user_login'] = "";
        $_SESSION['user_ip']   = "";
        $_SESSION['user_time'] = 0;
        return true;
    }

    public function login($username, $password) {

        // access global data
        global $acswuiConfig;
        global $acswuiLog;
        global $acswuiDatabase;

        // deny login with no username
        if (strlen($username) <= 0) {
            $this->logout();
            $acswuiLog->logWarning("Login reuqest with empty username.");
            return false;
        }

        // deny login with no password
        if (strlen($password) <= 0) {
            $this->logout();
            $acswuiLog->logWarning("Login reuqest with empty password.");
            return false;
        }

        // new session id
        session_regenerate_id();

        // check root login
        if ($username === "root") {
            if (password_verify($password, $acswuiConfig->RootPassword)) {
                $acswuiLog->logNotice("Successful root login");
                $_SESSION['user_id']    = 0;
                $_SESSION['user_login'] = "root";
                $_SESSION['user_ip']    = $_SERVER['REMOTE_ADDR'];
                $_SESSION['user_time']  = time();
                return true;
            } else {
                $this->logout();
                $acswuiLog->logWarning("Failed root login request!");
                return false;
            }

        // non-root login
        } else {

            $matching_users = $acswuiDatabase->fetch_2d_array("Users", ["Id", "Login", "Password"], ['Login' => $username]);
            $positive_user_ids = array();

            // check matching logins
            foreach ($matching_users as $u) {
                if ($u['Login'] == $username && password_verify($password, $u['Password'])) {
                    $positive_user_ids[count($positive_user_ids)] = $u['Id'];
                }
            }

            // login
            if (count($positive_user_ids) == 1) {
                $u = $acswuiDatabase->fetch_2d_array("Users", ["Id", "Login"], ['Id' => $positive_user_ids[0]]);
                $_SESSION['user_id']    = $u[0]['Id'];
                $_SESSION['user_login'] = $u[0]['Login'];
                $_SESSION['user_ip']    = $_SERVER['REMOTE_ADDR'];
                $_SESSION['user_time']  = time();
                return true;
            }
        }

        // this code should not be reached
        $this->logout();
        return false;
    }

    public function hasPermission($permission) {

        // get globals
        global $acswuiDatabase;
        global $acswuiConfig;

        // insert permission if not present
        if (!in_array($permission, $acswuiDatabase->fetch_column_names("Groups"))) {
            $acswuiDatabase->insert_group_permission($permission);
        }

        // grant root
        if ($this->IsRoot) {
            return true;
        }

        // get group memberships of user
        $user_group_ids = array();
        if ($this->IsLogged) {
            // get all group memberships of logged user
            foreach ($acswuiDatabase->fetch_2d_array("UserGroupMap", ["Group"], ['User' => $this->Id]) as $g) {
                $user_group_ids[count($user_group_ids)] = $g['Group'];

            }
        } elseif (strlen($acswuiConfig->GuestGroup) > 0) {
            // get matching visitor group if existent
            foreach ($acswuiDatabase->fetch_2d_array("Groups", ["Id"], ['Name' => $acswuiConfig->GuestGroup]) as $g) {
                $user_group_ids[count($user_group_ids)] = $g['Id'];
            }
        }

        // check permissions of all group memberships
        foreach ($user_group_ids as $g) {
            // check if group has the permission
            foreach ($acswuiDatabase->fetch_2d_array("Groups", [$permission], ['Id' => $g]) as $p) {
                if ($p[$permission] > 0){
                    return true;
                }
            }
        }

        // no permissions found
        return false;


    }


    // returns True when password is correct for current user
    public function confirmPassword($password) {
        global $acswuiConfig;
        global $acswuiDatabase;

        if ($this->IsRoot) {
            return password_verify($password, $acswuiConfig->RootPassword);

        } else {
            $row = $acswuiDatabase->fetch_2d_array("Users", ["Password"], ['Id' => $this->Id]);
            if (count($row) === 1) {
                return password_verify($password, $row[0]['Password']);
            }
        }

        return FALSE;
    }


    //! @return The User object for the current logged user (NULL if no user is logged in)
    public function user() {
        if ($this->IsLogged !== TRUE) return NULL;
        if ($this->User === NULL) $this->User = new User($this->Id);
        return $this->User;
    }
}

?>
