<?php

namespace Content\Html;

class B_User extends \core\HtmlContent {

    private $CurrentUser = NULL;
    private $ProfileUser = NULL;

    public function __construct() {
        parent::__construct(_("User"),  _("User Overview"));
        $this->addScript("user.js");
    }

    public function getHtml() {
        $html = "";

        // remember current logged user
        $this->CurrentUser = \Core\UserManager::loggedUser();

        // check if a certain user profile is requested
        if (array_key_exists("UserId", $_GET)) {
            $this->ProfileUser = \DbEntry\User::fromId($_GET['UserId']);
        } else {
            $this->ProfileUser = $this->CurrentUser;
        }


        if ($this->CurrentUser === NULL || $this->CurrentUser->id() == $this->ProfileUser->id()) {
            $html .= $this->showLogInOut();
        }

        if ($this->ProfileUser) {
            $html .= $this->showProfile();
            $html .= $this->showGarage();
        }

        return $html;
    }


    private function showGarage() : string {
        $html = "";

        $html .= "<h1>" . _("Garage") . "</h1>";
        $html .= "<button type=\"button\" onclick=\"LoadGarage(this)\" userId=\"{$this->ProfileUser->id()}\">" . _("Load Cars") . "</button>";
        $html .= "<div id=\"GarageCars\"></div>";

        return $html;
    }


    private function showLogInOut() {
        $html = "";

        if ($this->CurrentUser === NULL) {

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


    private function showProfile() {
        $html = "";


        $html .= "<h1>" . _("User Profile") . "</h1>";
        if ($this->ProfileUser->privacyFulfilled()) {

            $html .= "<strong>" . _("ID") . "</strong>: " . $this->ProfileUser->id() . "<br>";
            $html .= "<strong>" . _("Name") . "</strong>: " . $this->ProfileUser->html() . "<br>";
            $html .= "<strong>Steam64GUID</strong>: " . $this->ProfileUser->steam64GUID() . "<br>";

            $html .= "<strong>" . _("User Groups") . "</strong>:";
            for ($i=0; $i < count($this->ProfileUser->groups()); ++$i) {
                $group = $this->ProfileUser->groups()[$i];
                if ($group === NULL) continue;
                $html .= ($i == 0) ? " " : ", ";
                $html .= $this->ProfileUser->groups()[$i]->name();
            }
            $html .= "<br>";

            $html .= "<strong>" . _("Last Login") . "</strong>: " . $this->ProfileUser->formatDateTime($this->ProfileUser->lastLogin()) . " (" . $this->ProfileUser->daysSinceLastLogin() . "d)<br>";

            $html .= "<strong>" . _("Last Lap") . "</strong>: ";
            $lap = $this->ProfileUser->lastLap();
            if ($lap !== NULL) {
                $html .= $this->ProfileUser->formatDateTime($lap->timestamp()) . " (" . $this->ProfileUser->daysSinceLastLap() . "d)";
            }
            $html .= "<br>";

            $html .= "<strong>" . _("Status") . "</strong>: ";
            if ($this->ProfileUser->isDriver() && $this->ProfileUser->isCommunity()) $html .= _("active driver in community");
            else if ($this->ProfileUser->isDriver()) $html .= _("active driver");
            else $html .= _("inactive");
            $html .= "<br>";

            $html .= "<strong>" . _("Country") . "</strong>: " . $this->ProfileUser->nationalFlag() . "<br>";
            $html .= "<strong>" . _("Driven Laps") . "</strong>: " . $this->ProfileUser->countLaps() . "<br>";

        } else {
            $html .= _("Privacy settings of the user does not allow to show any information.");
        }

        return $html;
    }
}
