<?php

namespace Content\Html;

class UserProfile extends \core\HtmlContent {

    public function __construct() {
        parent::__construct(_("User"),  _("User Profile"), "User");
    }

    public function getHtml() {
        $html = "";

        $html .= \DbEntry\User::htmlLogin();

        return $html;
    }
}

?>
