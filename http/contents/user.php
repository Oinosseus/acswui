<?php

class user extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("User");
        $this->TextDomain = "acswui";
    }

    public function getHtml() {

        // access global data
        global $acswuiUser;

        $ret  = "";
        $ret .= _("User login: " . $acswuiUser->Login);
        return $ret;
    }
}

?>
