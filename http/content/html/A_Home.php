<?php

namespace Content\Html;

class A_Home extends \core\HtmlContent {

    public function __construct() {
        $this->setThisAsHomePage();
        parent::__construct(_("Home"),  "");
    }

    public function getHtml() {
        $html = "";

        $current_user = \Core\UserManager::loggedUser();
        if ($current_user === NULL) {
            $html .= _("Please login with your steam account");
            $html .= "<br>";
            $html .= \Core\UserManager::htmlLogInOut();
        }



        return $html;
    }
}
