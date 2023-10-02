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
        $cars_with_no_data_acd = array();
        $schedule_items_with_invalid_server_slot = array();
        $tracks_without_real_penalty_trackfile = array();

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
                if ($sl->track()->rpTrackfile() != TRUE && !in_array($sl->track(), $tracks_without_real_penalty_trackfile)) {
                    $tracks_without_real_penalty_trackfile[] = $sl->track();
                }

                // cars
                foreach ($sl->carClass()->cars() as $car) {
                    if (!$car->kunosOriginal() && strlen($car->downloadUrl()) == 0) {
                        if (!in_array($car, $cars_with_no_download)) {
                            $cars_with_no_download[] = $car;
                        }
                    }

                    if (!$car->hasDataAcd()) $cars_with_no_data_acd[] = $car;
                }
            }
        }

        // search in session schedules
        foreach (\Compound\ScheduledItem::listItems(NULL, new \DateTime("now")) as $ss) {


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

            // echo $ss->track()->name() . "=" . $ss->track()->rpTrackfile() . "<br>";
            if ($ss->track()->rpTrackfile() != TRUE && !in_array($ss->track(), $tracks_without_real_penalty_trackfile)) {
                $tracks_without_real_penalty_trackfile[] = $ss->track();
            }

            // cars
            foreach ($ss->listCarClasses() as $car_class) {
                foreach ($car_class->cars() as $car) {
                    if (!$car->kunosOriginal() && strlen($car->downloadUrl()) == 0) {
                        if (!in_array($car, $cars_with_no_download)) {
                            $cars_with_no_download[] = $car;
                        }
                    }

                    if (!$car->hasDataAcd()) $cars_with_no_data_acd[] = $car;
                }
            }
        }

        // search for invalid server-slots
        foreach (\Compound\ScheduledItem::listItems(NULL, new \DateTime("now")) as $si) {
            if ($si->serverSlot()->invalid())
                $schedule_items_with_invalid_server_slot[] = $si;
        }


        // show suspicious items
        $html .= "<h1>" . _("Suspects of Maladministrations") . "</h1>";
        $any_maladmin_found = FALSE;

        if (count($tracklocationss_with_no_geolocation) > 0) {
            $any_maladmin_found = TRUE;
            $html .= "<h2>" . _("Tracks with suspicious Geo-Location") . "</h2>";
            foreach ($tracklocationss_with_no_geolocation as $t) {
                $html .= $t->html();
            }
        }

        if (count($tracklocationss_with_no_download) > 0) {
            $any_maladmin_found = TRUE;
            $html .= "<h2>" . _("Tracks with no download url") . "</h2>";
            foreach ($tracklocationss_with_no_download as $t) {
                $html .= $t->html();
            }
        }

        if (count($cars_with_no_download) > 0) {
            $any_maladmin_found = TRUE;
            $html .= "<h2>" . _("Cars with no download url") . "</h2>";
            foreach ($cars_with_no_download as $c) {
                $html .= $c->html();
            }
        }

        if (count($cars_with_no_data_acd) > 0) {
            $any_maladmin_found = TRUE;
            $html .= "<h2>" . _("Cars with no data.acd file") . "</h2>";
            foreach ($cars_with_no_data_acd as $c) {
                $html .= $c->html();
            }
        }

        if (count($schedule_items_with_invalid_server_slot) > 0) {
            $any_maladmin_found = TRUE;
            $html .= "<h2>" . _("Schedules with invalid Server-Slot") . "</h2>";
            $html .= "<ul>";
            foreach ($schedule_items_with_invalid_server_slot as $si) {
                $html .= "<li>{$si->nameLink()}</li>";
            }
            $html .= "</ul>";
        }

        if (count($tracks_without_real_penalty_trackfile) > 0) {
            $any_maladmin_found = TRUE;
            $html .= "<h2>" . _("Missing trackfiles for Real Penalty") . "</h2>";
            foreach ($tracks_without_real_penalty_trackfile as $t) {
                $html .= $t->html();
            }
        }

        if (!$any_maladmin_found) {
            $html .= "<div id=\"NoMaladminFound\">" . _("all okay") . "</div>";
        }

        return $html;
    }


    private function upcommingRaces() {
        $luser = \Core\UserManager::loggedUser();
        $cuser = \Core\UserManager::currentUser();

        $html = "";
        $html .= "<h1>" . _("Upcomming Races") . "</h1>";

        // iCalendar link
        $url = "./ACswuiCalendar.php?UserId=" . \Core\UserManager::currentUser()->id();
        $html .= "<a href=\"$url\">" . _("iCalendar Link") . "</a><br>";

        $html .= "<div id=\"UpcommingRacesOverview\">";
        $a_week_ago = (new \DateTime("now"))->sub(new \DateInterval("P7D"));
        $items = \Compound\ScheduledItem::listItems(NULL, $a_week_ago);
        foreach ($items as $si) {
            $class_obsolete = ($si->obsolete()) ? "Obsolete" : "";
            $count_registrations = $si->registrations();
            $count_pits = $si->track()->pitboxes();
            $registration_css_class = ($count_registrations > $count_pits) ? "RegistrationsFull" : "RegistrationsAvailable";

            $html .= "<div class=\"$class_obsolete\">";
            $html .= $cuser->formatDate($si->start()) . "<br><br>";
            $html .= $si->nameLink() . "<br>";
            // time
            if ($luser) {
                if ($si->registered($cuser)) {
                    $html .= "<span class=\"Registered\">" . _("Registered") . "</span>";
                } else {
                    $html .= "<span class=\"NotRegistered\">" . _("Not Registered") . "</span>";
                }
            }
            // $html .= " (<span class=\"$registration_css_class\">$count_registrations / $count_pits</span>)<br>";
            if ($si->serverSlot()) {
                $html .= "<br>";
                $html .= $si->serverSlot()->htmlJoin();
            }
            $html .= "</div>";

            // track
            $html .= "<div class=\"$class_obsolete\">";
            $html .= $si->track()->html(include_link:TRUE, show_label:FALSE, show_img:TRUE);
            $html .= "</div>";

            // schedule
            $html .= "<div class=\"$class_obsolete\">";
            $html .= $si->htmlCompactSchedule() . "<br>";
            $html .= "</div>";

            // crar class / race series
            $html .= "<div class=\"$class_obsolete\">";
            if ($si->getSessionSchedule()) {
                $html .= $si->getSessionSchedule()->carClass()->html(include_link:TRUE, show_label:TRUE, show_img:TRUE);
            } else if ($si->getRSerSplit()) {
                $html .= $si->getRSerSplit()->event()->season()->series()->html(TRUE, FALSE, TRUE);
            }
            $html .= "</div>";

            // registration / weather
            $html .= "<div class=\"$class_obsolete\">";
            $html .= _("Registrations") . ": <span class=\"$registration_css_class\">$count_registrations / $count_pits</span><br>";
            $rwc = $si->serverPreset()->forecastWeather($si->start(), $si->track()->location());
            if ($rwc !== NULL) {
                $html .= $rwc->htmlImg();
                $html .= sprintf("%0.1f°C", $rwc->temperature());
            }
            $html .= "</div>";

            // download links
            $html .= "<div class=\"$class_obsolete\">";
            $html .= "<strong>" . _("Downloads") . "</strong><br>";
            // track
            $url = $si->track()->location()->downloadUrl();
            if ($url !== "") {
                $html .= _("Track Location") . ": <a href=\"$url\">" . $si->track()->location()->name() . "</a><br>";
            }
            // cars
            foreach ($si->listCarClasses() as $cc) {
                $list_urls = array();
                $list_cars = array();
                foreach ($cc->cars() as $c) {
                    if ($c->downloadUrl() != "" && !in_array($c->downloadUrl(), $list_urls)) {
                        $list_urls[] = $c->downloadUrl();
                        $list_cars[] = $c;
                    }
                }
                if (count($list_urls) == 1) {
                    $html .= _("Car Class") . ": ";
                    $html .= "<a href=\"{$list_urls[0]}\">{$cc->name()}</a>";
                    $html .= "<br>";
                } else if (count($list_urls) > 1) {
                    $html .= _("Cars") . ": ";
                    for ($i=0; $i<count($list_cars); ++$i) {
                        if ($i >= 1) $html .= ", ";
                        $ip1 = $i + 1;
                        $html .= "<a href=\"{$list_urls[$i]}\">{$cc->name()}-{$ip1}</a>";
                    }
                    $html .= "<br>";
                }
            }
            // skins
            $skins_registrations = array();
            foreach ($si->registeredCarSkins() as $s) {
                $sreg = \DbEntry\CarSkinRegistration::fromCarSkinLatest($s);
                if ($sreg && !in_array($sreg, $skins_registrations)) $skins_registrations[] = $sreg;
            }
            if (count($skins_registrations)) {
                $html .= _("Car Skins") . ": ";
                $urls = array();
                foreach ($skins_registrations as $sreg) {
                    $url = $sreg->packagedDownloadLink();
                    if ($url == NULL || in_array($url, $urls)) continue;
                    if (count($urls)) $html .= ", ";
                    $html .= "<a href=\"$url\">{$sreg->carSkin()->skin()}</a>";
                    $urls[] = $url;
                }
                $html .= "<br>";
            }
            $html .= "</div>";
        }

        $html .= "</div>";
        return $html;
    }
}
