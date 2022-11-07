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

            if (\Core\UserManager::currentUser()->permitted("Notify_Maladministration")) {
                $html .= $this->malAdministrations();
            }

            $html .= $this->openRegistrations();
        }

        return $html;
    }


    private function malAdministrations() : string {
        $html = "";

        // search for currently used tracks and cars
        // $currently_used_tracks = array();
        // $currently_used_cars = array();
        $tracklocationss_with_no_download = array();
        $tracklocationss_with_no_geolocation = array();
        $cars_with_no_download = array();

        // search session loops
        foreach (\DbEntry\SessionLoop::listLoops() as $sl) {
            if ($sl->enabled()) {

                // tracks
                $tloc = $sl->track()->location();
                if (!$tloc->kunosOriginal()) {
                    if (strlen($tloc->downloadUrl()) == 0) {
                        if (!in_array($tloc, $tracklocationss_with_no_download)) {
                            $tracklocationss_with_no_download[] = $tloc;
                        }
                    }
                    if (abs($tloc->geoLocation()->latitude()) < 0.1 && abs($tloc->geoLocation()->longitude()) < 0.1) {
                        if (!in_array($tloc, $tracklocationss_with_no_geolocation)) {
                            $tracklocationss_with_no_geolocation[] = $tloc;
                        }
                    }
                }

                // cars
                foreach ($sl->carClass()->cars() as $car) {
                    if (!$car->kunosOriginal() && strlen($car->downloadUrl()) == 0) {
                        if (!in_array($car, $cars_with_no_download)) {
                            $cars_with_no_download[] = $car;
                        }
                    }
                }
            }
        }

        // search in session schedules
        foreach (\DbEntry\SessionSchedule::listSchedules() as $ss) {


            // tracks
            $tloc = $ss->track()->location();
            if (!$tloc->kunosOriginal()) {
                if (strlen($tloc->downloadUrl()) == 0) {
                    if (!in_array($tloc, $tracklocationss_with_no_download)) {
                        $tracklocationss_with_no_download[] = $tloc;
                    }
                }
                if (abs($tloc->geoLocation()->latitude()) < 0.1 && abs($tloc->geoLocation()->longitude()) < 0.1) {
                    if (!in_array($tloc, $tracklocationss_with_no_geolocation)) {
                        $tracklocationss_with_no_geolocation[] = $tloc;
                    }
                }
            }

            // cars
            foreach ($ss->carClass()->cars() as $car) {
                if (!$car->kunosOriginal() && strlen($car->downloadUrl()) == 0) {
                    if (!in_array($car, $cars_with_no_download)) {
                        $cars_with_no_download[] = $car;
                    }
                }
            }
        }

        // show suspicious items
        $html .= "<h1>" . _("Suspects of Maladministrations") . "</h1>";

        if (count($tracklocationss_with_no_geolocation) > 0) {
            $html .= "<h2>" . _("Tracks with suspicious Geo-Location") . "</h2>";
            foreach ($tracklocationss_with_no_geolocation as $t) {
                $html .= $t->html();
            }
        }

        if (count($tracklocationss_with_no_download) > 0) {
            $html .= "<h2>" . _("Tracks with no download url") . "</h2>";
            foreach ($tracklocationss_with_no_download as $t) {
                $html .= $t->html();
            }
        }

        if (count($cars_with_no_download) > 0) {
            $html .= "<h2>" . _("Cars with no download url") . "</h2>";
            foreach ($cars_with_no_download as $c) {
                $html .= $c->html();
            }
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

        $html .= "<a href=\"" . $this->url([], "SessionSchedules") . "\">" . _("All Events") . "</a>";

        return $html;
    }
}
