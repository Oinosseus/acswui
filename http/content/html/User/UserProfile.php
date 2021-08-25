<?php

namespace Content\Html;

class UserProfile extends \core\HtmlContent {

    public function __construct() {
        parent::__construct(_("Profile"),  _("User Profile"));
    }

    public function getHtml() {
        $html = "User Profile";

        return $html;
    }
}

?>
