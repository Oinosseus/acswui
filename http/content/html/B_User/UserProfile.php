<?php

namespace Content\Html;

class UserProfile extends \core\HtmlContent {

    public function __construct() {
        parent::__construct(_("Profile"),  _("User Profile"));
        $this->requirePermission("User_View");
    }

    public function getHtml() {
        $html = "";

        $user = \Core\UserManager::loggedUser();

        if ($user && $user->privacyFulfilled()) {
            $html .= "<strong>" . _("ID") . "</strong>: " . $user->id() . "<br>";
            $html .= "<strong>" . _("Name") . "</strong>: " . $user->name() . "<br>";
            $html .= "<strong>Steam64GUID</strong>: " . $user->steam64GUID() . "<br>";
            $html .= "<strong>" . _("Last Login") . "</strong>: " . $user->formatDateTime($user->lastLogin()) . " (" . $user->daysSinceLastLogin() . "d)<br>";

            $html .= "<strong>" . _("Status") . "</strong>: ";
            if ($user->isDriver() && $user->isCommunity()) $html .= _("active driver in community");
            else if ($user->isDriver()) $html .= _("active driver");
            else $html .= _("inactive");
            $html .= "<br>";

            $html .= "<strong>" . _("Country") . "</strong>: " . $user->parameterCollection()->child("UserCountry")->valueLabel() . "<br>";
            $html .= "<strong>" . _("Driven Laps") . "</strong>: " . $user->countLaps() . "<br>";

        }

        return $html;
    }
}

?>
