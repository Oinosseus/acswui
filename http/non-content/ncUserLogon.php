<?php
//
// This non-content script processes user related actions.
//
//
// REQUEST Variables
// -----------------
//
// Following http post variables are available within this non-content script.
// The variables are case sensitive.
//
// ACTION : "login", "logout"
//   This defines what is requested action.
//
// USERNAME : string
//   If ACTION=="login" this defines the requested user for login.
//
// PASSWORD : string
//   If ACTION=="login" this defines the unencrypted password for the login request.
//

class ncUserLogon extends cNonContentPage {

    function getContent() {


        // access global data
        global $acswuiLog;
        global $acswuiUser;

        // return value
        $ret = "";

        // determine action
        $action = (isset($_REQUEST['ACTION'])) ? $_REQUEST['ACTION'] : "";


        // action user login
        if ($action=="login") {

            $username = (isset($_REQUEST['USERNAME'])) ? $_REQUEST['USERNAME'] : "";
            $password = (isset($_REQUEST['PASSWORD'])) ? $_REQUEST['PASSWORD'] : "";

            if ($acswuiUser->login($username, $password)) {
                $ret = "login successful";
            } else {
                $ret = "login failed";
            }


        // action logout
        } else if ($action == "logout") {
            $acswuiUser->logout();
            $ret = "logged out";


        // unknown action
        } else {
            $acswuiLog->logWarning("Invalid action: $action");
            return false;
        }


        return $ret;
    }
}

?>
