<?php

class cUser {

    public function __construct() {

        // initialize session variables
        if (!isset($_SESSION['user_name'])) $_SESSION['user_name'] = "";
        if (!isset($_SESSION['user_ip']))   $_SESSION['user_ip']   = "";
        if (!isset($_SESSION['user_time'])) $_SESSION['user_time'] = 0;

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

        if ($name == "isLogged") {
            return (strlen($_SESSION['user_name']) > 0) ? true : false;

        } else if ($name == "isRoot") {
            return ($_SESSION['user_name'] === "root") ? true : false;

        } else if ($name == "Login") {
            return $_SESSION['user_name'];

        } else {
            $acswuiLog->logError("Invalid property access to " . $name);
            return "";

        }
    }

    public function logout() {
        $_SESSION['user_name'] = "";
        $_SESSION['user_ip']   = "";
        $_SESSION['user_time'] = 0;
        return true;
    }

    public function login($username, $password) {

        // access global data
        global $acswuiConfig;
        global $acswuiLog;

        // deny login with no username
        if (strlen($username) <= 0) {
            $this->logout();
            $acswuiLog->logWarning("Login reuqest with empty username.");
            return false;
        }

        // new session id
        session_regenerate_id();

        // check root login
        if ($username === "root") {
            if (password_verify($password, $acswuiConfig->RootPassword)) {
                $acswuiLog->logNotice("Successful root login");
                $_SESSION['user_name'] = "root";
                $_SESSION['user_ip']   = $_SERVER['REMOTE_ADDR'];
                $_SESSION['user_time'] = time();
                return true;
            } else {
                $this->logout();
                $acswuiLog->logWarning("Failed root login request!");
                return false;
            }

        }

        // this code should not be reached
        $this->logout();
        return false;
    }

}

?>
