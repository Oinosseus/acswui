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

        } else {
            $html .= $this->openRegistrations();
        }

        return $html;
    }


    private function openRegistrations() {
        $html = "";

        // list events;
        $events = array();
        foreach (\DbEntry\SessionSchedule::listSchedules() as $ss) {
            $sr = \DbEntry\SessionScheduleRegistration::getRegistration($ss, \Core\UserManager::currentUser());
            if (!$sr || !$sr->active()) $events[] = $ss;
        }
        if (count($events) == 0) return $html;

        $html .= "<h1>" . _("Open Registrations") . "</h1>";
        $html .= ("The following events are scheduled where your are not registered.");

        $html .= "<ul>";
        foreach ($events as $e) {
            $html .= "<li>";
            $html .= $e->htmlName();
            $html .= "</li>";
        }
        $html .= "</ul>";

        return $html;
    }
}
