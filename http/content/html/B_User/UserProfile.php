<?php

namespace Content\Html;

class UserProfile extends \core\HtmlContent {

    public function __construct() {
        parent::__construct(_("Profile"),  _("User Profile"));
        $this->requirePermission("ViewUsers");
    }

    public function getHtml() {
        $html = "User Profile";

        return $html;
    }
}

?>
