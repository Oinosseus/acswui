<?php

namespace Content\Html;

class UserProfile extends \core\HtmlContent {

    public function __construct() {
        parent::__construct(_("Profile"),  _("User Profile"));
        $this->requirePermission("User_View");
    }

    public function getHtml() {
        $html = "";

        if (array_key_exists("UserId", $_GET)) {
            $user = \DbEntry\User::fromId($_GET['UserId']);
        } else {
            $user = \Core\UserManager::loggedUser();
        }

        if ($user && $user->privacyFulfilled()) {
            $html .= "<strong>" . _("ID") . "</strong>: " . $user->id() . "<br>";
            $html .= "<strong>" . _("Name") . "</strong>: " . $user->html() . "<br>";
            $html .= "<strong>Steam64GUID</strong>: " . $user->steam64GUID() . "<br>";

            $html .= "<strong>" . _("User Groups") . "</strong>:";
            for ($i=0; $i < count($user->groups()); ++$i) {
                $html .= ($i == 0) ? " " : ", ";
                $html .= $user->groups()[$i]->name();
            }
            $html .= "<br>";

            $html .= "<strong>" . _("Last Login") . "</strong>: " . $user->formatDateTime($user->lastLogin()) . " (" . $user->daysSinceLastLogin() . "d)<br>";

            $html .= "<strong>" . _("Last Lap") . "</strong>: ";
            $lap = $user->lastLap();
            if ($lap !== NULL) {
                $html .= $user->formatDateTime($lap->timestamp()) . " (" . $user->daysSinceLastLap() . "d)";
            }
            $html .= "<br>";

            $html .= "<strong>" . _("Status") . "</strong>: ";
            if ($user->isDriver() && $user->isCommunity()) $html .= _("active driver in community");
            else if ($user->isDriver()) $html .= _("active driver");
            else $html .= _("inactive");
            $html .= "<br>";

            $html .= "<strong>" . _("Country") . "</strong>: " . $user->parameterCollection()->child("UserCountry")->valueLabel() . "<br>";
            $html .= "<strong>" . _("Driven Laps") . "</strong>: " . $user->countLaps() . "<br>";

            $html .= "<h1>" . _("Teams") . "</h1>";
            $html .= "<ul>";
            foreach ($user->teams() as $team) {
                $html .= "<li>" . $team->htmlName() . "</li>";
            }
            $html .= "</ul>";

        } else {
            $html .= _("Privacy settings of the user does not allow to show any information.");
        }

        return $html;
    }
}

?>
