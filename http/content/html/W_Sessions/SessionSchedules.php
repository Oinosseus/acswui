<?php

namespace Content\Html;

class SessionSchedules extends \core\HtmlContent {

    private $CanEdit = FALSE;
    private $CurrentSchedule = NULL;


    public function __construct() {
        parent::__construct(_("Schedule"),  "Session Schedule");
        $this->requirePermission("Sessions_Schedule_View");
    }


    public function getHtml() {
        $this->CanEdit = \Core\UserManager::permitted("Sessions_Schedule_Edit");
        $html = "";

        // get requested SessionSchedule object
        if (array_key_exists('SessionSchedule', $_REQUEST))
            $this->CurrentSchedule = \DbEntry\SessionSchedule::fromId($_REQUEST['SessionSchedule']);

        // determine Actions
        if (array_key_exists("Action", $_REQUEST)) {

            if (array_key_exists("Action", $_POST) && $_POST['Action'] == "SaveRgistration") {

                if ($this->CurrentSchedule && !$this->CurrentSchedule->obsolete()) {
                    $user = \Core\UserManager::currentUser();

                    // register driver
                    if ($_POST['RegistrationType'] == "Driver") {
                        if (array_key_exists("RegistrationCarSkin", $_POST)) {
                            $car_skin = \DbEntry\CarSkin::fromId($_POST["RegistrationCarSkin"]);
                            \DbEntry\SessionScheduleRegistration::register(schedule:$this->CurrentSchedule,
                                                                        user:$user,
                                                                        car_skin:$car_skin);
                        }

                    // register team
                    } else if ($_POST['RegistrationType'] == "Team" && $this->CurrentSchedule->getParamValue("AllowTeams")) {
                        if (array_key_exists('RegistrationTeamCar', $_POST)) {
                            $team_car = \DbEntry\TeamCar::fromId($_POST["RegistrationTeamCar"]);

                            if ($team_car->carClass()->carClass() === $this->CurrentSchedule->carClass()) {
                                \DbEntry\SessionScheduleRegistration::register(schedule:$this->CurrentSchedule,
                                                                            team_car:$team_car);
                            }
                        }
                    }
                }
                $this->reload(['SessionSchedule'=>$this->CurrentSchedule->id(), "Action"=>"ShowRoster"]);
            }

            if ($this->CurrentSchedule && $_REQUEST['Action'] == "RegisterDriver") {
                $html .= $this->showRegistrationDriver();
            }

            if ($this->CurrentSchedule && $_REQUEST['Action'] == "RegisterTeam") {
                $html .= $this->showRegistrationTeam();
            }

            if ($_REQUEST['Action'] == "AskDeleteItem" && $this->CanEdit) {
                $html .= $this->showAskDeleteItem();
            }

            if ($_REQUEST['Action'] == "DoDeleteItem" && $this->CanEdit) {
                if ($this->CurrentSchedule) $this->CurrentSchedule->delete();
                $this->reload();
            }

            if ($_REQUEST['Action'] == "NewItem" && $this->CanEdit) {
                $ss = new \DbEntry\SessionSchedule(NULL);
                $ss->saveParameterCollection();
                $this->reload(['SessionSchedule'=>$ss->id(), 'Action'=>'EditItem']);
            }

            if ($_REQUEST['Action'] == "EditItem" && $this->CanEdit) {
                $html .= $this->showEditItem();
            }

            if ($_REQUEST['Action'] == "SaveScheduleItem" && $this->CanEdit) {
                if ($this->CurrentSchedule) {
                    $this->CurrentSchedule->parameterCollection()->storeHttpRequest();
                    $this->CurrentSchedule->saveParameterCollection();
                    $this->reload(['SessionSchedule'=>$this->CurrentSchedule->id(), 'Action'=>'EditItem']);
                }
                $this->reload();
            }

            if ($_REQUEST['Action'] == "SaveRoster") {
                if ($this->CurrentSchedule && !$this->CurrentSchedule->obsolete()) {

                    // admin tasks
                    if ($this->CanEdit) {

                        // save Ballast and Restrictor
                        foreach ($this->CurrentSchedule->registrations(only_active:FALSE) as $sr) {
                            $sr->setBallast($_POST["Ballast{$sr->id()}"]);
                            $sr->setRestrictor($_POST["Restrictor{$sr->id()}"]);
                        }

                        // add drivers
                        if (array_key_exists("AddDriverId", $_REQUEST)) {  // should not happend
                            $add_driver_id = $_REQUEST['AddDriverId'];
                            if ($add_driver_id) {
                                $user = \DbEntry\User::fromId($add_driver_id);
                                \DbEntry\SessionScheduleRegistration::register(schedule:$this->CurrentSchedule, user:$user);
                            }
                        }

                        // add teams
                        if (array_key_exists("AddTeamCar", $_REQUEST)) {  // can happend
                            $add_teamcar_id = $_REQUEST['AddTeamCar'];
                            if ($add_teamcar_id) {
                                $tmc = \DbEntry\TeamCar::fromId($add_teamcar_id);
                                $ssr = \DbEntry\SessionScheduleRegistration::register(schedule:$this->CurrentSchedule, team_car:$tmc);
                                $ssr->unregister();
                            }
                        }
                    }

                    // user tasks / unregister
                    $cuser = \Core\UserManager::currentUser();
                    foreach ($this->CurrentSchedule->registrations(only_active:TRUE) as $sr) {
                        if (array_key_exists("Unregister{$sr->id()}", $_POST)) {
                            if ($this->currentUserCanUnregister($sr)) $sr->unregister();
                        }
                    }
                }
                $this->reload(['SessionSchedule'=>$this->CurrentSchedule->id(), 'Action'=>'ShowRoster']);
            }

            if ($_REQUEST['Action'] == "ShowRoster") {
                $html .= $this->showRoster();
            }


        } else {
            $html .= $this->showOverview();
        }


        return $html;
    }


    private function currentUserCanUnregister(\DbEntry\SessionScheduleRegistration $sr) : bool {
        $cuser = \Core\UserManager::currentUser();

        // directly fail if registration is not active
        if (!$sr->active()) return FALSE;

        // directly fail if session schedule is obsolete
        if ($sr->sessionSchedule()->obsolete()) return FALSE;

        // check if current user is directly registered
        if ($sr->user() && $sr->user()->id() == $cuser->id()) return TRUE;

        // check if user is manager of a team regfistration
        if ($sr->teamCar()) {
            $tmm = $sr->teamCar()->team()->findMember($cuser);
            if ($tmm && $tmm->permissionManage()) return TRUE;
        }

        // if no permission found, then fail
        return FALSE;
    }


    private function showAskDeleteItem() {
        $html = "";
        if (!$this->CanEdit) return "";  // additional security
        if (!$this->CurrentSchedule) return "";

        $html .= _("Really Deleting Session Schedule Item?") . "<br>";
        $html .= \Core\UserManager::currentUser()->formatDateTimeNoSeconds($this->CurrentSchedule->start()) . " ";
        $html .= "<strong>{$this->CurrentSchedule->name()}</strong>";
        $html .= "<br><br>";

        $html .= $this->newHtmlForm("POST");
        $html .= "<input type=\"hidden\" name=\"SessionSchedule\" value=\"{$this->CurrentSchedule->id()}\">";
        $html .= "<button type=\"submit\" name=\"Action\" value=\"DoDeleteItem\">" . _("Delete") . "</button>";
        $html .= "</form>";

        $html .= " ";

        $html .= $this->newHtmlForm("GET");
        $html .= "<input type=\"hidden\" name=\"SessionSchedule\" value=\"{$this->CurrentSchedule->id()}\">";
        $html .= "<input type=\"hidden\" name=\"Action\" value=\"ShowRoster\">";
        $html .= "<button type=\"submit\">" . _("Cancel") . "</button>";
        $html .= "</form>";

        return $html;
    }


    private function showEditItem() {
        $html = "";
        if (!$this->CanEdit) return "";  // additional security
        if (!$this->CurrentSchedule) return "";

        $html .= $this->newHtmlForm("POST");
        $html .= "<input type=\"hidden\" name=\"SessionSchedule\" value=\"{$this->CurrentSchedule->id()}\">";
        $html .= $this->CurrentSchedule->parameterCollection()->getHtml(TRUE);
        $html .= "<br><br><button type=\"submit\" name=\"Action\" value=\"SaveScheduleItem\">" . _("Save") . "</button>";
        $html .= "</form>";

        $html .= $this->newHtmlForm("GET");
        $html .= "<input type=\"hidden\" name=\"SessionSchedule\" value=\"{$this->CurrentSchedule->id()}\">";
        $html .= "<input type=\"hidden\" name=\"Action\" value=\"ShowRoster\">";
        $html .= "<button type=\"submit\">" . _("Cancel") . "</button>";
        $html .= "</form>";

        return $html;
    }


    private function showOverview() {
        $html = "";

        // iCalendar link
        $url = "./ACswuiCalendar.php?UserId=" . \Core\UserManager::currentUser()->id();
        $html .= "<a href=\"$url\">" . _("iCalendar Link") . "</a><br>";

        $html .= "<div id=\"SessionScheduleOverview\">";
        $a_week_ago = (new \DateTime("now"))->sub(new \DateInterval("P7D"));
        foreach (\DbEntry\SessionSchedule::listSchedules($a_week_ago) as $ss) {

            $class_obsolete = ($ss->obsolete()) ? "Obsolete" : "";
            $count_registrations = count($ss->registrations());
            $count_pits = $ss->track()->pitboxes();
            $registration_css_class = ($count_registrations > $count_pits) ? "RegistrationsFull" : "RegistrationsAvailable";

            $html .= "<div class=\"$class_obsolete\">";
            $html .= "<a href=\"" . $this->url(["SessionSchedule"=>$ss->id(), "Action"=>"ShowRoster"]) . "\">{$ss->name()}</a><br>";
            $html .= \Core\UserManager::currentUser()->formatDateTimeNoSeconds($ss->start()) . "<br>";
            $srs = \DbEntry\SessionScheduleRegistration::getRegistrations($ss, \Core\UserManager::currentUser());
            if (count($srs) > 0) {
                $html .= "<span class=\"Registered\">" . _("Registered") . "</span>";
            } else {
                    $html .= "<span class=\"NotRegistered\">" . _("Not Registered") . "</span>";
            }
            // $html .= " (<span class=\"$registration_css_class\">$count_registrations / $count_pits</span>)<br>";
            $html .= "</div>";

            // track
            $html .= "<div class=\"track $class_obsolete\">";
            $html .= $ss->track()->html(include_link:TRUE, show_label:TRUE, show_img:TRUE);
            $html .= "</div>";

            // carclass
            $html .= "<div class=\"CarClass $class_obsolete\">";
            $html .= $ss->carClass()->html(include_link:TRUE, show_label:TRUE, show_img:TRUE);
            $html .= "</div>";

            // weather
            $html .= "<div class=\"Weather $class_obsolete\">";
            $rwc = $ss->serverPreset()->forecastWeather($ss->start(), $ss->track()->location());
            $wpc = NULL;
            if ($rwc !== NULL) {
                $wpc = $rwc->weather()->parameterCollection();
            } else if (count($ss->serverPreset()->weathers($ss->track()->location())) == 1) {
                $wpc = $ss->serverPreset()->weathers($ss->track()->location())[0]->parameterCollection();
            }
            $html .= "<div class=\"SessionScheduleWeatherForecastData\">";
            $html .= _("Ambient") . ": " . $wpc->child("AmbientBase")->value() . "&deg;C<br>";
            $html .= _("Road") . ": " . ($wpc->child("AmbientBase")->value() + $wpc->child("RoadBase")->value()) . "&deg;C<br>";
            $html .= _("Wind") . ": " . round(($wpc->child("WindBaseMin")->value() + $wpc->child("WindBaseMax")->value())/2) . "m/s<br>";
            $html .= "</div>";
            if ($rwc !== NULL) $html .= $rwc->htmlImg();
            $html .= "</div>";

            $html .= "<div class=\"$class_obsolete\">";
            $html .= "{$ss->serverSlot()->name()}<br>";;
            $html .= _("Registrations") . ": <span class=\"$registration_css_class\">$count_registrations / $count_pits</span><br>";
            if ($this->CanEdit) {
                $html .= "</br>";
                $html .= "<a href=\"" . $this->url(["SessionSchedule"=>$ss->id(), "Action"=>"EditItem"]) . "\">" . _("Edit") . "</a>, ";
                $html .= "<a href=\"" . $this->url(["SessionSchedule"=>$ss->id(), "Action"=>"AskDeleteItem"]) . "\">" . _("Delete") . "</a><br>";
            }
            $html .= "</div>";

        }
        $html .= "</div>";

        // create new item
        if ($this->CanEdit) {
            $html .= "<br>";
            $html .= $this->newHtmlForm("POST");
            $html .= "<button type=\"submit\" name=\"Action\" value=\"NewItem\">" . _("Create new Schedule item") . "</button>";
            $html .= "</form>";
        }

        return $html;
    }


    private function showRegistrationDriver() {
        $html = "";
        $ss = $this->CurrentSchedule;
        if (!$ss) return "";
        if ($ss->obsolete()) return "";
        $cu = \Core\UserManager::currentUser();

        $html .= \Core\UserManager::currentUser()->formatDateTimeNoSeconds($ss->start()) . " ";
        $html .= "<strong>{$ss->name()}</strong>";
        $html .= "<br><br>";

        // create new form
        $html .= $this->newHtmlForm("POST", "DriverRegistrationForm");
        $html .= "<input type=\"hidden\" name=\"SessionSchedule\" value=\"{$ss->id()}\">";
        $html .= "<input type=\"hidden\" name=\"RegistrationType\" value=\"Driver\">";

        // save button
        $html .= "<button type=\"submit\" name=\"Action\" value=\"SaveRgistration\">" . _("Save Registration") . "</button>";
        $html .= "<br>";

        // cars for driver registration
        foreach ($ss->carClass()->cars() as $car) {

            $html .= "<h2>{$car->name()}</h2>";
            foreach ($car->skins() as $skin) {

                // skip already occupied skins
                if ($ss->carSkinOccupied($skin)) continue;

                // skip owned skins
                if ($skin->owner() && $skin->owner()->id() != \Core\UserManager::currentUser()->id()) continue;

                // offer skin as radio button
                $skin_img = $skin->html(FALSE, TRUE, TRUE);
                $checked = FALSE;
                $disabled = FALSE;
                $html .= $this->newHtmlContentRadio("RegistrationCarSkin", $skin->id(), $skin_img, $checked, $disabled);
            }
        }

        // save button
        $html .= "<br>";
        $html .= "<button type=\"submit\" name=\"Action\" value=\"SaveRgistration\">" . _("Save Registration") . "</button>";
        $html .= "</form>";

        return $html;
    }


    private function showRegistrationTeam() {
        $html = "";
        $ss = $this->CurrentSchedule;
        if (!$ss) return "";
        if ($ss->obsolete()) return "";
        $cu = \Core\UserManager::currentUser();

        $html .= \Core\UserManager::currentUser()->formatDateTimeNoSeconds($ss->start()) . " ";
        $html .= "<strong>{$ss->name()}</strong>";
        $html .= "<br><br>";

        // create new form
        $html .= $this->newHtmlForm("POST", "DriverRegistrationForm");
        $html .= "<input type=\"hidden\" name=\"SessionSchedule\" value=\"{$ss->id()}\">";
        $html .= "<input type=\"hidden\" name=\"RegistrationType\" value=\"Team\">";

        // save button
        $html .= "<button type=\"submit\" name=\"Action\" value=\"SaveRgistration\">" . _("Save Registration") . "</button>";
        $html .= "<br>";

        // cars for team registration
        $html .= "<div id=\"RegistrationTypeTeamCars\">";
        $any_team_car_found = FALSE;
        foreach (\DbEntry\Team::listTeams(manager:$cu, carclass:$ss->carClass()) as $tm) {
            $html .= "<h1>{$tm->name()}</h1>";

            foreach ($tm->cars() as $tc) {

                // only matching carclass
                if ($tc->carClass()->carClass() != $ss->carClass()) continue;

                $skin_img = $tc->html();
                $checked = FALSE;
                $disabled = FALSE;
                $html .= $this->newHtmlContentRadio("RegistrationTeamCar", $tc->id(), $skin_img, $checked, $disabled);
                $any_team_car_found |= TRUE;
            }
        }
        $html .= "</div>";

        if (!$any_team_car_found) {
            $html .= "<br><br>";
            $html .= _("You either have no permission to manage a team or none of your teams has a car in the correct class");
            $html .= "<br><br>";
        }

        // save button
        if ($any_team_car_found) {
            $html .= "<br>";
            $html .= "<button type=\"submit\" name=\"Action\" value=\"SaveRgistration\">" . _("Save Registration") . "</button>";
        }
        $html .= "</form>";

        return $html;
    }


    private function showRoster() {
        $html = "";
        $ss = \DbEntry\SessionSchedule::fromId($_REQUEST['SessionSchedule']);
        $cuser = \Core\UserManager::currentUser();
        if (!$ss) return "";

        /////////////////////
        // Event Information
        $html .= "<div id=\"SessionDetailsOverview\">";

        // general
        $html .= "<div>";
        $html .= $cuser->formatDateTimeNoSeconds($ss->start()) . "<br>";
        $html .= "<strong>{$ss->name()}</strong>";
        if ($this->CanEdit) {
            $html .= "<br><br>";
            $html .= "<a href=\"" . $this->url(["SessionSchedule"=>$ss->id(), "Action"=>"EditItem"]) . "\">" . _("Edit") . "</a>, ";
            $html .= "<a href=\"" . $this->url(["SessionSchedule"=>$ss->id(), "Action"=>"AskDeleteItem"]) . "\">" . _("Delete") . "</a><br>";
        }
        $html .= "</div>";

        // track
        $html .= "<div>";
        $html .= $ss->track()->html() . "<br>";
        $html .= "</div>";

        // carclass
        $html .= "<div>";
        $html .= $ss->carClass()->html() . "<br>";
        $html .= "</div>";

        // time schedule
        $html .= "<div>";
        $html .= "<table>";
        $time = $ss->start();
        $schedules = $ss->serverPreset()->schedule($ss->track(), $ss->carClass());
        for ($i = 0; $i < count($schedules); ++$i) {
            [$interval, $uncertainty, $type, $name] = $schedules[$i];
            if ($type == \Enums\SessionType::Invalid && ($i+1) < count($schedules)) continue; // do not care for intermediate break
            $html .= "<tr>";
            $html .= "<td>" . $cuser->formatTimeNoSeconds($time) . "</td>";
            $html .= "<td>$name</td>";
            $html .= "<td>" . $cuser->formatTimeInterval($interval) . "</td>";
            $html .= "</tr>";
            $time->add($interval->toDateInterval());
        }
        $html .= "</table>";

        $html .= "<small>" . _("Sim Time") . ": ";
        $html .= $ss->serverPreset()->parameterCollection()->child("SessionStartTime")->valueLabel();
        $html .= " (x" . $ss->serverPreset()->parameterCollection()->child("AcServerTimeMultiplier")->valueLabel() . ")";
        $html .= "</small>";

        $html .= "</div>";

        // weather forecast
        $html .= "<div>";
        $html .= "<strong>" . _("Weather") . "</strong>";
        $rwc = $ss->serverPreset()->forecastWeather($ss->start(), $ss->track()->location());
        if ($rwc !== NULL) {
            $html .= "&nbsp;<small title=\"" . _("Weather data available from weather forecast") . "\">(" . _("Forecast") . ")</small><br>";
            $wpc = $rwc->weather()->parameterCollection();
            $html .= $rwc->htmlImg();
            $html .= "<div class=\"SessionScheduleWeatherForecastData\">";
            $html .= _("Ambient") . ": " . $wpc->child("AmbientBase")->value() . "&deg;C<br>";
            $html .= _("Road") . ": " . ($wpc->child("AmbientBase")->value() + $wpc->child("RoadBase")->value()) . "&deg;C<br>";
            $html .= _("Rain") . ": " . sprintf("%0.1f", $rwc->precipitation()) . "mm/h<br>";
            $html .= _("Wind") . ": " . round(($wpc->child("WindBaseMin")->value() + $wpc->child("WindBaseMax")->value())/2) . "m/s<br>";
            $html .= "</div>";
        } else if (count($ss->serverPreset()->weathers($ss->track()->location())) == 1) {
            $html .= "&nbsp;<small title=\"" . _("Fixed weather is used") . "\">(" . _("Fixed") . ")</small><br>";
            $wpc = $ss->serverPreset()->weathers($ss->track()->location())[0]->parameterCollection();
            $html .= "<div class=\"SessionScheduleWeatherForecastData\">";
            $html .= _("Ambient") . ": " . $wpc->child("AmbientBase")->value() . "&deg;C<br>";
            $html .= _("Road") . ": " . ($wpc->child("AmbientBase")->value() + $wpc->child("RoadBase")->value()) . "&deg;C<br>";
            $html .= _("Wind") . ": " . round(($wpc->child("WindBaseMin")->value() + $wpc->child("WindBaseMax")->value())/2) . "m/s<br>";
            $html .= "</div>";
        }
        $html .= "</div>";

        $html .= "</div><br>";


        /////////////////////
        // User Registration

        if (!$ss->obsolete()) {

            // show registration status
            $srs = \DbEntry\SessionScheduleRegistration::getRegistrations($ss, $cuser);
            if (count($srs) > 0) $html .= "<span class=\"Registered\">" . _("Registered") . "</span> ";
            else $html .= "<span class=\"NotRegistered\">" . _("Not Registered") . "</span> ";

            // offer registration buttons
            $can_register_driver = count($srs) == 0;
            $can_register_team = $ss->getParamValue("AllowTeams");
            if ($can_register_driver)
                $html .= "<a href=\"" . $this->url(["SessionSchedule"=>$ss->id(), "Action"=>"RegisterDriver"]) . "\">". _("Register Driver") . "</a>";
            if ($can_register_driver && $can_register_team)
                $html .= ", ";
            if ($can_register_team)
                $html .= "<a href=\"" . $this->url(["SessionSchedule"=>$ss->id(), "Action"=>"RegisterTeam"]) . "\">". _("Register Team") . "</a>";
            $html .= "<br><br>";
        }

        ///////////////////////
        // Registration Roster
        $html .= $this->newHtmlForm("POST", "RegistrationRoster");
        $html .= "<input type=\"hidden\" name=\"SessionSchedule\" value=\"{$ss->id()}\">";

        $html .= "<table>";
        $html .= "<tr>";
        $html .= "<th colspan=\"2\">" . _("Driver") . "</th>";
        $html .= "<th>" . _("Car") . "</th>";
        $html .= "<th>" . _("Ballast") . "</th>";
        $html .= "<th>" . _("Restrictor") . "</th>";
        $html .= "<th>" . _("Registration") . "</th>";
        $html .= "</tr>";

        $count_active_registrations = 0;
        foreach ($ss->registrations(FALSE) as $sr) {
            $count_active_registrations += ($sr->active()) ? 1 : 0;
            $u = $sr->user();

            $css_class = ($count_active_registrations > $ss->track()->pitboxes()) ? "Overbooked" : "";
            $html .= "<tr class=\"$css_class\">";
            if ($sr->teamCar()) {
                $html .= "<td class=\"TeamLogo\">{$sr->teamCar()->team()->html(TRUE, FALSE, TRUE, FALSE)}</td>";
                $html .= "<td class=\"DriverName\">";
                $drivers = $sr->teamCar()->drivers();
                for ($i=0; $i < count($drivers); ++$i) {
                    $tmm = $drivers[$i];
                    if ($i > 0) $html .= ", ";
                    $html .= $tmm->user()->html(TRUE, FALSE, TRUE);
                }
                $html .= "</td>";
            } else {
                $html .= "<td class=\"NationalFlag\">{$sr->user()->nationalFlag()}</td>";
                $html .= "<td class=\"DriverName\">{$sr->user()->html()}</td>";
            }

            $html .= "<td class=\"CarSkin\">";
            if ($sr->carSkin()) $html .= $sr->carSkin()->html(TRUE, FALSE, TRUE);
            $html .= "</td>";

            if ($this->CanEdit && !$ss->obsolete()) {
                $html .= "<td><input type=\"number\" min=\"0\" max=\"999\" name=\"Ballast{$sr->id()}\" value=\"{$sr->ballast()}\"> kg</td>";
                $html .= "<td><input type=\"number\" min=\"0\" max=\"999\" name=\"Restrictor{$sr->id()}\" value=\"{$sr->restrictor()}\"> &percnt;</td>";
            } else {
                $html .= "<td>{$sr->ballast()} kg</td>";
                $html .= "<td>{$sr->restrictor()} &percnt;</td>";
            }

            if ($sr->active()) {
                $html .= "<td><span class=\"Registered\">{$cuser->formatDateTime($sr->activated())}</span></td>";
            } else {
                $html .= "<td><span class=\"NotRegistered\">" . _("Not Registered") . "</span></td>";
            }

            // check if unregister is possible
            if ($this->currentUserCanUnregister($sr)) {
                $html .= "<td>";
                $html .= $this->newHtmlTableRowDeleteCheckbox("Unregister{$sr->id()}");
                $html .= "</td>";
            }

            $html .= "</tr>";
        }
        $html .= "</table>";

        // add inactive registration
        if ($this->CanEdit && !$ss->obsolete()) {
            $html .= "<br><br>";

            // add driver
            $html .= _("Add Driver") . ": ";
            $html .= "<select name=\"AddDriverId\">";
            $html .= "<option value=\"\" selected=\"yes\"> </option>";
            $drivers = \DbEntry\User::listDrivers();
            usort($drivers, "\DbEntry\User::compareName");
            foreach ($drivers as $d) {
                $html .= "<option value=\"{$d->id()}\">{$d->name()}</option>";
            }
            $html .= "</select><br>";

            // add Team
            if ($this->CurrentSchedule->getParamValue("AllowTeams")) {
                $html .= _("Add Team Car") . ": ";
                $html .= "<select name=\"AddTeamCar\">";
                $html .= "<option value=\"\" selected=\"yes\"> </option>";
                foreach (\DbEntry\Team::listTeams() as $tm) {
                    $team_cars = \DbEntry\TeamCar::listTeamCars(team:$tm, carclass:$this->CurrentSchedule->carClass());
                    foreach ($team_cars as $tmc) {
                        $html .= "<option value=\"{$tmc->id()}\">{$tm->name()} - {$tmc->carSkin()->name()}</option>";
                    }
                }
                $html .= "</select><br>";
            }
        }

        // save roster
        if (!$ss->obsolete()) {
            $html .= "<br><br>";
            $html .= "<button type=\"submit\" name=\"Action\" value=\"SaveRoster\">" . _("Save Registration Roster") . "</button>";
        }
        $html .= "</form>";

        return $html;
    }

}
