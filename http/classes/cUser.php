<?php

class cUser {

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

            $matching_users = $acswuiDatabase->fetch_2d_array("Users", ["Id", "Login", "Password"], ['Login'], [$username]);
            $positive_user_ids = array();

            // check matching logins
            foreach ($matching_users as $u) {
                if ($u['Login'] == $username && password_verify($password, $u['Password'])) {
                    $positive_user_ids[count($positive_user_ids)] = $u['Id'];
                }
            }

            // login
            if (count($positive_user_ids) == 1) {
                $u = $acswuiDatabase->fetch_2d_array("Users", ["Id", "Login"], ['Id'], [$positive_user_ids[0]]);
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

        // grant root
        if ($this->IsRoot) {
            return true;
        }

        // insert permission if not present
        if (!in_array($permission, $acswuiDatabase->fetch_column_names("Groups"))) {
            $acswuiDatabase->insert_group_permission($permission);
        }

        // get group memberships of user
        $user_groups = array();
        foreach ($acswuiDatabase->fetch_2d_array("UserGroupMap", ["Group"], ['User'], [$this->Id]) as $g) {
            // check if group has the permission
            if ($acswuiDatabase->fetch_2d_array("Groups", [$permission], ['Id'], [$g['Group']])[0][$permission] > 0)
                return true;
        }

        return false;


    }

}

?>
