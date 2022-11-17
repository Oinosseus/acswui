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


        if (\Core\UserManager::currentUser()->permitted("Home_Notify_Maladministration")) {
            $html .= $this->malAdministrations();
        }

        if (\Core\UserManager::currentUser()->permitted("Home_UpCommingRaces")) {
            $html .= $this->upcommingRaces();
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


    private function upcommingRaces() {
        $luser = \Core\UserManager::loggedUser();
        $cuser = \Core\UserManager::currentUser();

        $html = "";
        $html .= "<h1>" . _("Upcomming Races") . "</h1>";
        $html .= "<div id=\"UpcommingRacesOverview\">";

        $a_week_ago = (new \DateTime("now"))->sub(new \DateInterval("P7D"));
        foreach (\DbEntry\SessionSchedule::listSchedules($a_week_ago) as $ss) {

            $class_obsolete = ($ss->obsolete()) ? "Obsolete" : "";
            $count_registrations = count($ss->registrations());
            $count_pits = $ss->track()->pitboxes();
            $registration_css_class = ($count_registrations > $count_pits) ? "RegistrationsFull" : "RegistrationsAvailable";

            $html .= "<div class=\"$class_obsolete\">";
            $html .= "<a href=\"" . $this->url(["SessionSchedule"=>$ss->id(), "Action"=>"ShowRoster"], "SessionSchedules") . "\">{$ss->name()}</a><br>";
            $html .= $cuser->formatDateTimeNoSeconds($ss->start()) . "<br>";
            if ($luser) {
                $srs = \DbEntry\SessionScheduleRegistration::getRegistrations($ss, $cuser);
                if (count($srs) > 0) {
                    $html .= "<span class=\"Registered\">" . _("Registered") . "</span>";
                } else {
                        $html .= "<span class=\"NotRegistered\">" . _("Not Registered") . "</span>";
                }
            }
            // $html .= " (<span class=\"$registration_css_class\">$count_registrations / $count_pits</span>)<br>";
            $html .= "</div>";

            $html .= "<div class=\"$class_obsolete\">";
            $html .= $ss->track()->html(include_link:TRUE, show_label:TRUE, show_img:TRUE);
            $html .= "</div>";

            $html .= "<div class=\"$class_obsolete\">";
            $html .= $ss->carClass()->html(include_link:TRUE, show_label:TRUE, show_img:TRUE);
            $html .= "</div>";

            $html .= "<div class=\"$class_obsolete\">";
            $html .= "{$ss->serverSlot()->name()}<br>";
            $html .= _("Registrations") . ": <span class=\"$registration_css_class\">$count_registrations / $count_pits</span><br>";
            $rwc = $ss->serverPreset()->forecastWeather($ss->start(), $ss->track()->location());
            if ($rwc !== NULL) $html .= $rwc->htmlImg();
            $html .= "</div>";

        }


        $html .= "</div>";

        return $html;
    }
}
