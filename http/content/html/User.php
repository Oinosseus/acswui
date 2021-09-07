<?php

namespace Content\Html;

class User extends \core\HtmlContent {

    public function __construct() {
        parent::__construct(_("User"),  _("User Options"));
    }

    public function getHtml() {
        $html = "";

        $current_user = \Core\UserManager::loggedUser();

        // root login
        if ($current_user === NULL) {
            $html .= "<br>";
            $html .= "<form action=\"index.php?UserManager=RootLogin\" method=\"post\">";
            $html .= "<input type=\"password\" name=\"RootPassword\">";
            $html .= "<button type=\"submit\">" . _("Login as Root") . "</button>";
            $html .= "</form>";
        }

        return $html;
    }
}

?>
