<?php

namespace Content\Html;

class B_User extends \core\HtmlContent {

    public function __construct() {
        parent::__construct(_("User"),  _("User Options"));
    }

    public function getHtml() {
        $html = "";

        $current_user = \Core\UserManager::loggedUser();

        if ($current_user === NULL) {

            // user login
            $html .= "<h1>" . _("User Login") . "</h1>";
            $html .= _("Login via Steam");
            $html .= "<br>";
            $html .= \Core\UserManager::htmlLogInOut();



            // root login
            $html .= "<h1>" . _("Root Login") . "</h1>";
            $html .= "<form action=\"index.php?UserManager=RootLogin\" method=\"post\">";
            $html .= "<input type=\"password\" name=\"RootPassword\"> ";
            $html .= "<button type=\"submit\">" . _("Login as Root") . "</button>";
            $html .= "</form>";

        } else {
            // user logout
            $html .= "<h1>" . _("User Logout") . "</h1>";
            $html .= \Core\UserManager::htmlLogInOut();
        }

        return $html;
    }
}
