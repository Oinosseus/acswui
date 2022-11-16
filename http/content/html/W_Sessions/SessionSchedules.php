<?php

namespace Content\Html;

class SessionSchedules extends \core\HtmlContent {

    private $CanEdit = FALSE;
    private $CurrentSchedule = NULL;


    public function __construct() {
        parent::__construct(_("Schedule"),  "Session Schedule");
        $this->requirePermission("Sessions_Schedule_View");
        $this->addScript("session_schedule.js");
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
                        $car_skin = \DbEntry\CarSkin::fromId($_POST["RegistrationCarSkin"]);
                        \DbEntry\SessionScheduleRegistration::register(schedule:$this->CurrentSchedule,
                                                                       user:$user,
                                                                       car_skin:$car_skin);

                    // register team
                    } else if ($_POST['RegistrationType'] == "Team" && $this->CurrentSchedule->getParamValue("AllowTeams")) {
                        $team_car = \DbEntry\TeamCar::fromId($_POST["RegistrationTeamCar"]);
                        \DbEntry\SessionScheduleRegistration::register(schedule:$this->CurrentSchedule,
                                                                       team_car:$team_car);
                    }
                }
                $this->reload(['SessionSchedule'=>$this->CurrentSchedule->id(), "Action"=>"ShowRoster"]);
            }

            if ($this->CurrentSchedule && $_REQUEST['Action'] == "Register") {
                $html .= $this->showRegistration();
            }

            if ($_REQUEST['Action'] == "UnRegister") {
                if ($this->CurrentSchedule && !$this->CurrentSchedule->obsolete()) {
                    $user = \Core\UserManager::currentUser();
                    \DbEntry\SessionScheduleRegistration::register($this->CurrentSchedule, $user);
                }
                $this->reload();
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

            if ($_REQUEST['Action'] == "SaveRoster" && $this->CanEdit) {
                if ($this->CurrentSchedule && !$this->CurrentSchedule->obsolete()) {
                    foreach (\DbEntry\SessionScheduleRegistration::listRegistrations($this->CurrentSchedule, FALSE) as $sr) {
                        $sr->setBallast($_POST["Ballast{$sr->id()}"]);
                        $sr->setRestrictor($_POST["Restrictor{$sr->id()}"]);
                    }
                    $add_driver_id = $_REQUEST['AddDriverId'];
                    if ($add_driver_id) {
                        $user = \DbEntry\User::fromId($add_driver_id);
                        \DbEntry\SessionScheduleRegistration::register($this->CurrentSchedule, $user);
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

            $html .= "<div class=\"$class_obsolete\">";
            $html .= "<strong>" . $ss->name() . "</strong><br>";
            $html .= \Core\UserManager::currentUser()->formatDateTimeNoSeconds($ss->start()) . "<br>";
            $html .= "</div>";

            $html .= "<div class=\"track $class_obsolete\">";
            $html .= $ss->track()->html(TRUE, FALSE, TRUE);
            $html .= "</div>";

            $html .= "<div class=\"CarClass $class_obsolete\">";
            $html .= $ss->carClass()->html(TRUE, FALSE, TRUE);
            $html .= "</div>";

            $html .= "<div class=\"$class_obsolete\">";
            $html .= "<small>{$ss->serverSlot()->name()}</small></br>";;
            $html .= $ss->track()->html(TRUE, TRUE, FALSE) . "<br>";
            $html .= $ss->carClass()->htmlName() . "<br>";
            $count_registrations = count($ss->registrations());
            $count_pits = $ss->track()->pitboxes();
            $registration_css_class = ($count_registrations > $count_pits) ? "RegistrationsFull" : "RegistrationsAvailable";
            $html .= _("Registrations") . ": <span class=\"$registration_css_class\">$count_registrations / $count_pits</span>";
            $html .= "</div>";

            $html .= "<div class=\"$class_obsolete\">";
            if ($ss->obsolete()) {
                // $html .= _("Registrations") . ": $count_registrations / $count_pits";
                if ($ss->sessionLast() !== NULL) {
                    $url = "index.php?HtmlContent=SessionOverview&SessionId=";
                    $url .= $ss->sessionLast()->id();
                    $html .= "<a href=\"$url\">" . _("Results") . "</a>";
                }
            } else {
                $sr = \DbEntry\SessionScheduleRegistration::getRegistration($ss, \Core\UserManager::currentUser());
                if ($sr !== NULL && $sr->active()) {
                    $html .= "<span class=\"Registered\">" . _("Registered") . "</span><br>";
                    $html .= $this->newHtmlForm("POST");
                    $html .= "<input type=\"hidden\" name=\"SessionSchedule\" value=\"{$ss->id()}\">";
                    $html .= "<button type=\"submit\" name=\"Action\" value=\"UnRegister\">" . _("Unregister") . "</button>";
                    $html .= "</form>";
                } else {
                    $html .= "<span class=\"NotRegistered\">" . _("Not Registered") . "</span><br>";
                    $html .= "<a href=\"" . $this->url(["SessionSchedule"=>$ss->id(), "Action"=>"Register"]) . "\">". _("Register") . "</a>";
                }
                // $html .= "<br><br>" . _("Registrations") . ": <span class=\"$registration_css_class\">$count_registrations / $count_pits</span>";
            }
            $html .= "</div>";


            $html .= "<div class=\"$class_obsolete\">";
            if ($this->CanEdit) {
                $html .= "<a href=\"" . $this->url(["SessionSchedule"=>$ss->id(), "Action"=>"EditItem"]) . "\">" . _("Edit") . "</a><br>";
                $html .= "<a href=\"" . $this->url(["SessionSchedule"=>$ss->id(), "Action"=>"AskDeleteItem"]) . "\">" . _("Delete") . "</a><br>";
            }
            $html .= "<a href=\"" . $this->url(["SessionSchedule"=>$ss->id(), "Action"=>"ShowRoster"]) . "\">" . _("Registration Roster") . "</a><br>";
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


    private function showRegistration() {
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

        // select registration type
        $html .= _("Registration Type") . ":&nbsp;";
        $html .= "<select name=\"RegistrationType\" id=\"RegistrationType\">";
        $html .= "<option value=\"Driver\" selected=\"yes\">" . _("Driver") . "</option>";
        if ($ss->getParamValue("AllowTeams")) {
            $html .= "<option value=\"Team\">" . _("Team") . "</option>";
        }
        $html .= "</select>";
        $html .= "<br><br>";

        // save button
        $html .= "<button type=\"submit\" name=\"Action\" value=\"SaveRgistration\">" . _("Save Registration") . "</button>";
        $html .= "<br>";

        // cars for driver registration
        $html .= "<div id=\"RegistrationTypeDriverCars\">";
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
        $html .= "</div>";

        // cars for team registration
        $html .= "<div id=\"RegistrationTypeTeamCars\">";
        foreach (\DbEntry\Team::listTeams(manager:$cu, carclass:$ss->carClass()) as $tm) {
            $html .= "<h1>{$tm->name()}</h1>";

            foreach ($tm->cars() as $tc) {
                $skin_img = $tc->html();
                $checked = FALSE;
                $disabled = FALSE;
                $html .= $this->newHtmlContentRadio("RegistrationTeamCar", $tc->id(), $skin_img, $checked, $disabled);
            }
        }
        $html .= "</div>";

        // save button
        $html .= "<br>";
        $html .= "<button type=\"submit\" name=\"Action\" value=\"SaveRgistration\">" . _("Save Registration") . "</button>";
        $html .= "</form>";

        return $html;
    }


    private function showRoster() {
        $html = "";
        $ss = \DbEntry\SessionSchedule::fromId($_REQUEST['SessionSchedule']);
        if (!$ss) return "";

        /////////////////////
        // Event Information
        $html .= "<div id=\"SessionDetailsOverview\">";

        // general
        $html .= "<div>";
        $html .= \Core\UserManager::currentUser()->formatDateTimeNoSeconds($ss->start()) . "<br>";
        $html .= "<strong>{$ss->name()}</strong><br><br>";
        $html .= $ss->carClass()->htmlName() . "<br>";
        $html .= "</div>";

        // track
        $html .= "<div>";
        $html .= $ss->track()->html() . "<br>";
        $html .= "</div>";

        // time schedule
        $html .= "<div>";
        $html .= "<table>";
        $time = $ss->start();
        $schedules = $ss->serverPreset()->schedule($ss->track(), $ss->carClass());
        for ($i = 0; $i < count($schedules); ++$i) {
            [$interval, $uncertainty, $type, $name] = $schedules[$i];
            if ($type == \DbEntry\Session::TypeInvalid && ($i+1) < count($schedules)) continue; // do not care for intermediate break
            $html .= "<tr>";
            $html .= "<td>" . \Core\UserManager::currentUser()->formatTimeNoSeconds($time) . "</td>";
            $html .= "<td>$name</td>";
            $html .= "<td>" . \Core\UserManager::currentUser()->formatTimeInterval($interval) . "</td>";
            $html .= "</tr>";
            $time->add($interval->toDateInterval());
        }
        $html .= "</table>";
        $html .= "</div>";

        // weather forecast
        $html .= "<div>";
        $html .= "<strong>" . _("Weather Forecast") . "</strong><br>";
        $rwc = $ss->serverPreset()->forecastWeather($ss->start(), $ss->track()->location());
        if ($rwc !== NULL) {
            $wpc = $rwc->weather()->parameterCollection();
            $html .= $rwc->htmlImg();
            $html .= "<div id=\"SessionScheduleWeatherForecastData\">";
            $html .= _("Ambient") . ": " . $wpc->child("AmbientBase")->value() . "&deg;C<br>";
            $html .= _("Road") . ": " . ($wpc->child("AmbientBase")->value() + $wpc->child("RoadBase")->value()) . "&deg;C<br>";
            $html .= _("Rain") . ": " . sprintf("%0.1f", $rwc->precipitation()) . "mm/h<br>";
            $html .= _("Wind") . ": " . round(($wpc->child("WindBaseMin")->value() + $wpc->child("WindBaseMax")->value())/2) . "m/s<br>";
            $html .= "</div>";
        } else if (count($ss->serverPreset()->weathers($ss->track()->location())) == 1) {
            $wpc = $ss->serverPreset()->weathers($ss->track()->location())[0]->parameterCollection();
            $html .= "<div id=\"SessionScheduleWeatherForecastData\">";
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
            $sr = \DbEntry\SessionScheduleRegistration::getRegistration($ss, \Core\UserManager::currentUser());
            if ($sr !== NULL && $sr->active()) {
                $html .= "<span class=\"Registered\">" . _("Registered") . "</span> ";
                $html .= $this->newHtmlForm("POST");
                $html .= "<input type=\"hidden\" name=\"SessionSchedule\" value=\"{$ss->id()}\">";
                $html .= "<button type=\"submit\" name=\"Action\" value=\"UnRegister\">" . _("Unregister") . "</button>";
                $html .= "</form>";
            } else {
                $html .= "<span class=\"NotRegistered\">" . _("Not Registered") . "</span> ";
                $html .= "<a href=\"" . $this->url(["SessionSchedule"=>$ss->id(), "Action"=>"Register"]) . "\">". _("Register") . "</a>";
            }
            $html .= "<br><br>";
        }

        ///////////////////////
        // Registration Roster
        $html .= $this->newHtmlForm("POST", "RegistrationRoster");
        $html .= "<input type=\"hidden\" name=\"SessionSchedule\" value=\"{$ss->id()}\">";

        $html .= "<table>";
        $html .= "<tr>";
        $html .= "<th colspan=\"2\">" . _("Driver") . "</th>";
        $html .= "<th>" . _("Car Skin") . "</th>";
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
                $html .= "<td>" . \Core\UserManager::currentUser()->formatDateTime($sr->activated()) . "</td>";
            } else {
                $html .= "<td></td>";
            }

            $html .= "</tr>";
        }
        $html .= "</table>";

        // add inactive registration
        if ($this->CanEdit && !$ss->obsolete()) {
            $html .= _("Add Driver") . ": ";
            $html .= "<select name=\"AddDriverId\">";
            $html .= "<option value=\"\"> </option>";
            $drivers = \DbEntry\User::listDrivers();
            usort($drivers, "\DbEntry\User::compareName");
            foreach ($drivers as $d) {
                $html .= "<option value=\"{$d->id()}\">{$d->name()}</option>";
            }
            $html .= "</select>";
        }


        if ($this->CanEdit && !$ss->obsolete()) {
            $html .= "<br>";
            $html .= "<button type=\"submit\" name=\"Action\" value=\"SaveRoster\">" . _("Save Registration Roster") . "</button>";
        }
        $html .= "</form>";

        return $html;
    }

}
