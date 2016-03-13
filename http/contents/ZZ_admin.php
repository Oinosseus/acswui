<?php

class ZZ_admin extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Admin");
        $this->TextDomain = "acswui";
    }

    public function getHtml() {

        // access global data
        global $acswuiUser;

        // initialize the html output
        $html  = "";
        $html .= _("User login: " . $acswuiUser->Login);

        // login form
        $html .= '<form id="user_login_form" onsubmit="return false;">';
        $html .= '<input type="text" id="username" />';
        $html .= '<input type="password" id="userpass" />';
        $html .= '<input type="submit">';
        $html .= '</form>';

        // load java script
        $html .= '<script src="' . $this->getRelPath() . 'user.js"></script>';

        return $html;
    }
}

?>
