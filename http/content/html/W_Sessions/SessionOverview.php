<?php

namespace Content\Html;

class SessionOverview extends \core\HtmlContent {

    private $CurrentSession = NULL;
    private $CurrentPenalty = NULL;
    private $CanEditPenalties = FALSE;

    private $FilterShowPractice = FALSE;
    private $FilterShowQualifying = FALSE;
    private $FilterShowRace = TRUE;

    public function __construct() {
        parent::__construct(_("Overview"),  "Session Overview");
        $this->requirePermission("Sessions_View");
        $this->addScript("laptime_ditribution_diagram.js");
        $this->addScript("session_position_diagram.js");
        $this->addScript("session_overview.js");
    }


    private function checkFilters() {
        if (array_key_exists("ApplyFilter", $_POST)) {
            $this->FilterShowPractice = array_key_exists("ShowPractice", $_POST);
            $this->FilterShowQualifying = array_key_exists("ShowQualifying", $_POST);
            $this->FilterShowRace = array_key_exists("ShowRace", $_POST);
        } else {
            if (array_key_exists("ShowPractice", $_SESSION)) $this->FilterShowPractice = $_SESSION['ShowPractice'];
            if (array_key_exists("ShowQualifying", $_SESSION)) $this->FilterShowQualifying = $_SESSION['ShowQualifying'];
            if (array_key_exists("ShowRace", $_SESSION)) $this->FilterShowRace = $_SESSION['ShowRace'];
        }
        $_SESSION['ShowPractice'] = $this->FilterShowPractice;
        $_SESSION['ShowQualifying'] = $this->FilterShowQualifying;
        $_SESSION['ShowRace'] = $this->FilterShowRace;
    }


    public function getHtml() {
        $html = "";

        // check permissions
        $this->CanEditPenalties = \Core\UserManager::currentUser()->permitted("Sessions_Penalties_Edit");

        // retrieve requested session
        if (array_key_exists("SessionId", $_REQUEST)) {
            $this->CurrentSession = \DbEntry\Session::fromId((int) $_REQUEST['SessionId']);
        } else {
            $this->CurrentSession = \DbEntry\Session::latestSession();
        }

        // retireve requested penalty
        if (array_key_exists("PenaltyId", $_REQUEST))
            $this->CurrentPenalty = \DbEntry\SessionPenalty::fromId((int) $_REQUEST['PenaltyId']);

        // process actions
        if (array_key_exists("Action", $_REQUEST)) {

            if ($_REQUEST['Action'] == "SavePenalty") {
                if (array_key_exists("SessionDriver", $_POST)) {

                    // determine driver
                    $driver = NULL;
                    if (substr($_POST['SessionDriver'], 0, 7) == "TeamCar")
                        $driver = \DbEntry\TeamCar::fromId((int) substr($_POST['SessionDriver'], 7));
                    else
                        $driver = \DbEntry\User::fromId((int) substr($_POST['SessionDriver'], 4));
                    if ($driver === NULL) {
                        \Core\Log::error("No driver found!");
                    }

                    // create new penalty
                    if ($this->CurrentPenalty === NULL) {
                        $this->CurrentPenalty = \DbEntry\SessionPenalty::create($this->CurrentSession, $driver);
                    }

                    // assign values
                    $this->CurrentPenalty->setOfficer(\Core\UserManager::currentUser());
                    $this->CurrentPenalty->setCause($_POST["Cause"]);
                    $this->CurrentPenalty->setPenDnf(array_key_exists("PenDnf", $_POST));
                    $this->CurrentPenalty->setPenDsq(array_key_exists("PenDsq", $_POST));
                    $this->CurrentPenalty->setPenPts((int) $_POST["PenPts"]);
                    $this->CurrentPenalty->setPenLaps((int) $_POST["PenLaps"]);
                    $this->CurrentPenalty->setPenSf((int) $_POST["PenSf"]);
                    $this->CurrentPenalty->setPenTime((int) $_POST["PenTime"]);

                    // re-calculate final results
                    \DbEntry\SessionResultFinal::calculate($this->CurrentSession);

                    $this->reload(["SessionId"=>$this->CurrentSession->id()]);
                }


            } else if ($_REQUEST['Action'] == "AddPenalty" && $this->CanEditPenalties) {
                $html .= $this->getHtmlEditPenaltyForm();

            } else if ($_REQUEST['Action'] == "EditPenalty" && $this->CanEditPenalties && $this->CurrentPenalty) {
                $html .= $this->getHtmlEditPenaltyForm();
            }

        // show session oerview
        } else {
            $html .= $this->getHtmlSessionOverview();
        }

        return $html;
    }


    private function getHtmlEditPenaltyForm() : string {
        $html = "";
        if (!$this->CanEditPenalties || !$this->CurrentSession) return "";
        $cuser = \Core\UserManager::currentUser();
        $spen = $this->CurrentPenalty;

        // heading with session info
        $html .= "<h1>";
        $html .= " [" . $this->CurrentSession->id() . "] ";
        $html .= $cuser->formatDateTimeNoSeconds($this->CurrentSession->timestamp());
        $html .= " (" . $this->CurrentSession->typeChar() . ") ";
        $html .= $this->CurrentSession->name() . " @ ";
        $html .= $this->CurrentSession->track()->name();
        $html .="</h1>";

        // begin form
        $html .= $this->newHtmlForm("POST");
        if ($this->CurrentPenalty) {
            $html .= "<input type=\"hidden\" name=\"PenaltyId\" value=\"{$this->CurrentPenalty->id()}\">";
        } else {
            $html .= "<input type=\"hidden\" name=\"PenaltyId\" value=\"NEW\">";
        }

        $html .= "<table>";

        // select driver
        $html .= "<tr>";
        $html .= "<th>" . _("Driver") . "</th>";
        $html .= "<td><select name=\"SessionDriver\">";
        foreach ($this->CurrentSession->drivers(TRUE) as $driver) {
            $selected = ($spen && $spen->driver() == $driver) ? "selected=\"yes\"": "";

            // hide other drivers when penalty already exists
            if ($this->CurrentPenalty) {
                if ($driver != $spen->driver()) continue;
            }

            if (is_a($driver, "\\DbEntry\\TeamCar")) {
                $drivernames_array = array();
                foreach ($driver->drivers() as $tmm) $drivernames_array[] = $tmm->user()->name();
                $drivers_string = implode(", ", $drivernames_array);
                $html .= "<option value=\"TeamCar{$driver->id()}\" $selected>";
                $html .= _("Team") . " {$driver->team()->abbreviation()} - (";
                $html .= $drivers_string . ")";
                $html .= "</option>";

            } else if (is_a($driver, "\\DbEntry\\User")) {
                $html .= "<option value=\"User{$driver->id()}\" $selected>";
                $html .= _("Driver") . " - {$driver->name()}";
                $html .= "</option>";
            } else {
                \Core\Log::error("Unexpected Type!");
            }
        }
        $html .= "</select></td></tr>";

        // cause
        $html .= "<tr>";
        $html .= "<th>" . _("Cause") . "</th>";
        $html .= "<td><textarea name=\"Cause\">";
        if ($spen) $html .= $spen->cause();
        $html .= "</textarea></td>";
        $html .= "</tr>";

        // PenSf
        $html .= "<tr>";
        $html .= "<th>" . _("Penalty Safety Points") . "</th>";
        $v = ($spen) ? $spen->penSf() : 0;
        $html .= "<td><input type=\"number\" name=\"PenSf\" value=\"$v\"></td>";
        $html .= "</tr>";

        // PenTime
        $html .= "<tr>";
        $html .= "<th>" . _("Penalty Time") . "</th>";
        $v = ($spen) ? $spen->penTime() : 0;
        $html .= "<td><input type=\"number\" name=\"PenTime\" value=\"$v\"></td>";
        $html .= "</tr>";

        // PenLaps
        $html .= "<tr>";
        $html .= "<th>" . _("Penalty Laps") . "</th>";
        $v = ($spen) ? $spen->penLaps() : 0;
        $html .= "<td><input type=\"number\" name=\"PenLaps\" value=\"$v\"></td>";
        $html .= "</tr>";

        // PenPts
        $html .= "<tr>";
        $html .= "<th>" . _("Penalty Points") . "</th>";
        $v = ($spen) ? $spen->penPts() : 0;
        $html .= "<td><input type=\"number\" name=\"PenPts\" value=\"$v\"></td>";
        $html .= "</tr>";

        // PenDnf
        $html .= "<tr>";
        $html .= "<th>" . _("Penalty DNF") . "</th>";
        $checked = ($spen && $spen->penDnf()) ? "checked=\"yes\"": "";
        $html .= "<td><input type=\"checkbox\" name=\"PenDnf\" $checked></td>";
        $html .= "</tr>";

        // PenDsq
        $html .= "<tr>";
        $html .= "<th>" . _("Penalty DSQ") . "</th>";
        $checked = ($spen && $spen->penDsq()) ? "checked=\"yes\"": "";
        $html .= "<td><input type=\"checkbox\" name=\"PenDsq\" $checked></td>";
        $html .= "</tr>";

        $html .= "</table>";

        $html .= "<button type=\"submit\" name=\"Action\" value=\"SavePenalty\">" . _("Save Penalty") . "</button>";

        $html .= "</form>";
        return $html;
    }


    private function getHtmlSessionOverview() : string {
        $html = "";
        $this->checkFilters();
        $html .= $this->sessionSlector();
        if ($this->CurrentSession !== NULL) {
            $html .= $this->listSessionInformation();
            $html .= $this->listResults();
            $html .= $this->listPenalties();
            $html .= $this->listBestlap();
            $html .= $this->listCollisions();
            $html .= $this->listLaps();
        }
        return $html;
    }


    private function listBestlap() {
        $user = \core\UserManager::currentUser();
        $html = "<h1>" . _("Best Laps") . "</h1>";

        // laptime diagram
        $html .= "<div id=\"LaptimeDistributionDiagram\">";
        $title = _("Laptime Distribution Diagram");
        $axis_y_title = _("Driven laps [in percent]");
        $axis_x_title = _("Laptime distance to best lap in session [in seconds]");
        $max_delta = $user->getParam("UserLaptimeDistriDiaMaxDelta");
        $axis_type = $user->getParam("UserLaptimeDistriDiaAxis");
        $html .= "<canvas axYTitle=\"$axis_y_title\" axXTitle=\"$axis_x_title\" title=\"$title\" maxDelta=\"$max_delta\" axisType=\"$axis_type\"></canvas>";
        $html .= "</div>";

        // header
        $html .= "<table>";
        $html .= "<tr>";
        $html .= "<th>" . _("Driver") . "</th>";
        $html .= "<th>" . _("Laptime") . "</th>";
        $html .= "<th>" . _("Delta") . "</th>";
        $html .= "<th>" . _("Cuts") . "</th>";
        $html .= "<th>" . _("Car") . "</th>";
        $html .= "<th>" . _("BOP") . "</th>";
        $html .= "<th>" . _("Grip") . "</th>";
        $html .= "<th>" . _("Lap") . "</th>";
        $html .= "<tr>";

        // scan best laps
        $best_laps = array();
        foreach ($this->CurrentSession->users() as $u) {
            $laps = $this->CurrentSession->laps($u, TRUE);  // valid laps
            if (count($laps) == 0) $laps = $this->CurrentSession->laps($u, FALSE);  // all laps
            usort($laps, "\DbEntry\Lap::compareLaptime");
            $best_laps[] = $laps[0];
        }
        usort($best_laps, "\DbEntry\Lap::compareLaptime");

        // list best laps
        $best_laptime = NULL;
        foreach ($best_laps as $lap) {
            if ($best_laptime === NULL) $best_laptime = $lap->laptime();

            $tr_css_class = ($lap->cuts() > 0) ? "InvalidLap" : "ValidLap";
            $html .= "<tr class=\"$tr_css_class\">";

            $html .= "<td class=\"Driver\">";
            $html .= $lap->user()->parameterCollection()->child("UserCountry")->valueLabel();
            $html .= $lap->user()->html();
            $html .= "</td>";

            $html .= "<td>" . $user->formatLaptime($lap->laptime()) . "</td>";
            $html .= "<td>" . $user->formatLaptimeDelta(($lap->laptime() - $best_laptime)) . "</td>";
            $html .= "<td>" . $lap->cuts() . "</td>";
            $html .= "<td class=\"CarSkin\">" . $lap->carSkin()->html(TRUE, FALSE, TRUE) . "</td>";
            $html .= "<td>" . sprintf("%+dkg, %+d&percnt;", $lap->ballast(), $lap->restrictor()) . "</td>";
            $html .= "<td>" . sprintf("%0.1f", 100*$lap->grip()) . " &percnt;</td>";
            $html .= "<td>" . $lap->id() . "</td>";

            $html .= "<td>";
            if ($lap->user()->privacyFulfilled()) {
                $type_list = $user->parameterCollection()->child("UserLaptimeDistriDiaType")->valueList();
                if (in_array("hist", $type_list)) {
                    $html .= "<button type=\"button\" sessionId=\"" . $this->CurrentSession->id() . "\" userId=\"" . $lap->user()->id() . "\" onclick=\"LaptimeDistributionDiagramLoadData(this, 'bar')\" title=\"" . _("Load Laptime Distribution Data") . "\">";
                    $html .= "&#x1f4ca;</button> ";
                }
                if (in_array("gauss", $type_list)) {
                    $html .= "<button type=\"button\" sessionId=\"" . $this->CurrentSession->id() . "\" userId=\"" . $lap->user()->id() . "\" onclick=\"LaptimeDistributionDiagramLoadData(this, 'line')\" title=\"" . _("Load Laptime Distribution Data") . "\">";
                    $html .= "&#x1f4c8;</button> ";
                }
            }
            $html .= "</td>";

            $html .= "</tr>";
        }

        $html .= "</table>";
        return $html;
    }


    private function listCollisions() {
        $html = "<h1>" . _("Collisions") . "</h1>";
        $html .= "<button type=\"button\" onclick=\"SessionOverviewLoadCollisions(this)\" sessionId=\"" . $this->CurrentSession->id() . "\">" . _("Load Collisions") . "</button>";
        $html .= "<table id=\"SessionCollisions\"></table>";
        return $html;
    }


    private function listLaps() {
        $html = "<h1>" . _("All Laps") . "</h1>";
        $html .= "<button type=\"button\" onclick=\"SessionOverviewLoadLaps(this)\" sessionId=\"" . $this->CurrentSession->id() . "\">" . _("Load Laps") . "</button>";
        $html .= "<table id=\"SessionLaps\"></table>";
        return $html;
    }


    private function listPenalties() {
        $html = "<h1>" . _("Session Penalties") . "</h1>";

        $html .= "<table>";
        $html .= "<tr>";
        $html .= "<th colspan=\"2\">" . _("Driver")  ."</th>";
        $html .= "<th>" . _("Penalty")  ."</th>";
        $html .= "<th>" . _("Cause")  ."</th>";
        $html .= "<th>" . _("Officer")  ."</th>";
        $html .= "</tr>";

        foreach (\DbEntry\SessionPenalty::listPenalties($this->CurrentSession) as $spen) {
            $html .= "<tr>";

            // driver
            if (is_a($spen->driver(), "\\DbEntry\\TeamCar")) {
                $html .= "<td class=\"TeamLogo\">{$spen->driver()->team()->html(TRUE, FALSE, TRUE, FALSE)}</td>";
                $html .= "<td class=\"DriverName\">";
                $drivers = $spen->driver()->drivers();
                for ($i=0; $i < count($drivers); ++$i) {
                    $tmm = $drivers[$i];
                    if ($i > 0) $html .= ", ";
                    $html .= $tmm->user()->html(TRUE, FALSE, TRUE);
                }
                $html .= "</td>";
            } else if (is_a($spen->driver(), "\\DbEntry\\User")) {
                $html .= "<td class=\"NationalFlag\">{$spen->driver()->nationalFlag()}</td>";
                $html .= "<td class=\"DriverName\">{$spen->driver()->html()}</td>";
            } else {
                $html .= "<td></td><td></td>";
            }

            // penalty
            $penalties = array();
            $css_good = "PenaltyGood";
            $css_bad = "PenaltyBad";
            if ($spen->penSf() != 0) {
                $css = ($spen->penSf() < 0) ? $css_bad : $css_good;
                $penalties[] = "<span class=\"$css\">" . sprintf("%+dSF", $spen->penSf()) . "</span>";
            }
            if ($spen->penTime() != 0) {
                $css = ($spen->penTime() > 0) ? $css_bad : $css_good;
                $penalties[] = "<span class=\"$css\">" . sprintf("%+ds", $spen->penTime()) . "</span>";
            }
            if ($spen->penPts() != 0) {
                $css = ($spen->penPts() < 0) ? $css_bad : $css_good;
                $penalties[] = "<span class=\"$css\">" . sprintf("%+dpts", $spen->penPts()) . "</span>";
            }
            if ($spen->penLaps() != 0) {
                $css = ($spen->penLaps() < 0) ? $css_bad : $css_good;
                $penalties[] = "<span class=\"$css\">" . sprintf("%+dL", $spen->penLaps()) . "</span>";
            }
            if ($spen->penDnf() != 0) $penalties[] = "<span class=\"$css_bad\">DNF</span>";
            if ($spen->penDsq() != 0) $penalties[] = "<span class=\"$css_bad\">DSQ</span>";
            $html .= "<td>" . implode(", ", $penalties) . "</td>";

            // cause
            $html .= "<td>{$spen->cause()}</td>";

            // officer
            $officer = $spen->officer();
            if ($officer === NULL) $html .= "<td>-</td>";
            else  $html .= "<td>{$officer->html()}</td>";

            // edit
            if ($this->CanEditPenalties) {
                $url = $this->url(['SessionId' => $this->CurrentSession->id(),
                                   'Action'=>'EditPenalty',
                                   'PenaltyId'=>$spen->id()]);
                $html .= "<td><a href=\"{$url}\">&#x1f4dd;</a></td>";
            }

            $html .= "</tr>";

        }

        $html .= "</table>";

        if ($this->CanEditPenalties) {
            $html .= "<a href=\"{$this->url(['SessionId'=>$this->CurrentSession->id(), 'Action'=>'AddPenalty'])}\"\">" . _("Add Penalty") . "</a>";
        }

        return $html;
    }


    private function listResults() {
        $user = \core\UserManager::currentUser();
        $html = "<h1>" . _("Session Results") . "</h1>";

        $session_results = \DbEntry\SessionResultFinal::listResults($this->CurrentSession);

        // laptime diagram
        $positions = count($session_results);
        $html .= "<div id=\"SessionPositionDiagram\">";
        $title = _("Session Position Diagram");
        $axis_x_title = ($this->CurrentSession->type() == \DbEntry\Session::TypeRace) ? _("Laps") : _("Minutes");
        if ($user->getParam("UserSessionPositionDiaType") == "place") {
            $axis_y_title = _("Position");
        } if ($user->getParam("UserSessionPositionDiaType") == "gap") {
            $axis_y_title = _("Gap");
        }
        $sid = $this->CurrentSession->id();
        $html .= "<canvas axYTitle=\"$axis_y_title\" axXTitle=\"$axis_x_title\" title=\"$title\" sessionId=\"$sid\" positions=\"$positions\" diagramType=\"" . $user->getParam("UserSessionPositionDiaType") . "\"></canvas>";
        $html .= "</div>";

        $html .= "<table>";
        $html .= "<tr>";
        $html .= "<th rowspan=\"2\">"  . _("Position") . "</th>";
        $html .= "<th rowspan=\"2\">"  . _("Driver") . "</th>";
        $html .= "<th rowspan=\"2\">"  . _("Car") . "</th>";
        $html .= "<th rowspan=\"2\">"  . _("Best Lap") . "</th>";
        $html .= "<th colspan=\"3\">"  . _("Driven") . "</th>";
        // $html .= "<th rowspan=\"2\">"  . _("Cuts") . "</th>";
        // $html .= "<th colspan=\"2\">"  . _("Collisions") . "</th>";
        $html .= "<th colspan=\"2\">"  . _("Result") . "</th>";
        $html .= "</tr>";

        $html .= "<tr>";
        $html .= "<th>"  . _("Time") . "</th>";
        $html .= "<th>"  . _("Laps") . "</th>";
        $html .= "<th>"  . _("Distance") . "</th>";
        // $html .= "<th>"  . _("Environment") . "</th>";
        // $html .= "<th>"  . _("Other Cars") . "</th>";
        $html .= "<th>"  . _("Rank") . "</th>";
        $html .= "<th>"  . _("Pen") . "</th>";
        $html .= "<tr>";

        // list all results
        foreach ($session_results as $r) {
            $html .= "<tr>";
            $html .= "<td>" . $r->position() ."</td>";

            // driver
            $html .= "<td>" . $r->driver()->getHtml() . "</td>";

            $html .= "<td class=\"SessionResultsCarSkinCell\">" . $r->carSkin()->html(TRUE, FALSE) ."</td>";
            $html .= "<td>" . $user->formatLaptime($r->bestLaptime()) . "</td>";
            $html .= "<td>" . $user->formatLaptime($r->finalTime()) . "</td>";
            $html .= "<td>" . $r->finalLaps() . "</td>";
            $html .= "<td>" . $user->formatDistance($r->finalLaps() * $r->session()->track()->length()) . "</td>";
            // $html .= "<td>" . $r->amountCuts() . "</td>";
            // $html .= "<td>" . $r->amountCollisionEnv() . "</td>";
            // $html .= "<td>" . $r->amountCollisionCar() . "</td>";

            // ranking points
            $rp = $r->rankingPoints();
            $html .= "<td>";
            $title =  "XP:" . $rp['XP']['Sum'] . "\n";
            $title .= "SX:" . $rp['SX']['Sum'] . "\n";
            $title .= "SF:" . $rp['SF']['Sum'];
            $sum = $rp['XP']['Sum'] + $rp['SX']['Sum'] + $rp['SF']['Sum'];
            $css_class = ($sum > 0.0) ? "PositiveRankingPoints" : "NagativeRankingPoints";
            $html .= "<span title=\"$title\" class=\"$css_class\">" . sprintf("%+0.1f", round($sum, 1)) . "</span>";
            $html .= "</td>";

            // penalties
            $pens = new \Compound\SessionPenaltiesSum($this->CurrentSession, $r->driver());
            $html .= "<td>{$pens->getHtml()}</td>";

            $html .= "<tr>";
        }
        $html .= "</table>";

        return $html;
    }


    private function listSessionInformation() {
        $html = "<h1>" . _("Session Information") . "</h1>";

        // predecessor/successor chain
        $html .= "<div id=\"session_predecessor_chain\">";
        $html .= $this->predecessorChain($this->CurrentSession);
        $html .= $this->CurrentSession->name();
        $html .= $this->successorChain($this->CurrentSession);
        $html .= "</div>";

        // information datalets
        $html .= "<div id=\"SessionInformationDatalets\">";

            if ($this->CurrentSession->serverSlot()) {
                $html .= "<div>";
                $html .= "<strong>" . _("Server Slot") . "</strong>";
                $html .= $this->CurrentSession->serverSlot()->name();
                $html .= "</div>";
            }

            $html .= "<div>";
            $html .= "<strong>" . _("Grip") . "</strong>";
            $html .= _("Max") . ": " . sprintf("%0.1f", 100 * $this->CurrentSession->grip()[1]) . "&percnt;<br>";
            $html .= _("Min") . ": " . sprintf("%0.1f", 100 * $this->CurrentSession->grip()[0]) . "&percnt;";
            $html .= "</div>";

            $html .= $this->CurrentSession->track()->html();

            $html .= "<div>";
            $html .= "<strong>" . _("Weahter") . "</strong>";
            $html .= _("Graphics") . ": " . $this->CurrentSession->weather() . "<br>";
            $html .= _("Ambient") . ": " . $this->CurrentSession->tempAmb() . "&deg;C<br>";
            $html .= _("Road") . ": " . $this->CurrentSession->tempRoad() . "&deg;C";
            $html .= "</div>";

        $html .= "</div>";


        return $html;
    }


    private function sessionSlector() {
        $html = "";

        // filter select
        $html .= $this->newHtmlForm("POST");
        $html .= "<div id=\"SessionOverviewFilter\">";

        $checked = ($this->FilterShowPractice) ? "checked=\"Yes\"" : "";
        $html .= "<input type=\"Checkbox\" name=\"ShowPractice\" value=\"True\" id=\"SessionOverviewFilterPractice\" $checked>";
        $html .= "<label for=\"SessionOverviewFilterPractice\">" . _("Practice") . "</label>";

        $checked = ($this->FilterShowQualifying) ? "checked=\"Yes\"" : "";
        $html .= "<input type=\"Checkbox\" name=\"ShowQualifying\" value=\"True\" id=\"SessionOverviewFilterQualifying\" $checked>";
        $html .= "<label for=\"SessionOverviewFilterQualifying\">" . _("Qualifying") . "</label>";

        $checked = ($this->FilterShowRace) ? "checked=\"Yes\"" : "";
        $html .= "<input type=\"Checkbox\" name=\"ShowRace\" value=\"True\" id=\"SessionOverviewFilterRace\" $checked>";
        $html .= "<label for=\"SessionOverviewFilterRace\">" . _("Race") . "</label>";

        $html .= "<button type=\"submit\" name=\"ApplyFilter\" value=\"True\">" . _("Apply Filter")  . "</button>";
        $html .= "</form></div>";


        // session select
        $html .= $this->newHtmlForm("GET");
        $html .= _("Session Select") . ": ";
        $html .= "<select name=\"SessionId\" onchange=\"this.form.submit()\">";
        $current_session_listed = FALSE;
        foreach (\DbEntry\Session::listSessions($this->FilterShowRace, $this->FilterShowQualifying, $this->FilterShowPractice) as $s) {

            // force insert current session if filtered out
            if ($this->CurrentSession !== NULL && $s->id() == $this->CurrentSession->id()) $current_session_listed = TRUE;
            if ($this->CurrentSession !== NULL && !$current_session_listed && $s->id() < $this->CurrentSession->id()) {
                $html .= $this->sessionSlectorOption($this->CurrentSession);
                $current_session_listed = TRUE;
            }

            $html .= $this->sessionSlectorOption($s);
        }
        $html .= "</select>";

        $html .= "</form>";
        return $html;
    }


    private function sessionSlectorOption($session) {
        $user = \core\UserManager::currentUser();
        $html = "";
        $selected = ($this->CurrentSession !== NULL && $this->CurrentSession->id() == $session->id()) ? "selected=\"Yes\"" : "";
        $html .= "<option value=\"" . $session->id() . "\" $selected>";
        $html .= " [" . $session->id() . "] ";
        $html .= $user->formatDateTimeNoSeconds($session->timestamp());
        $html .= " (" . $session->typeChar() . ") ";
        $html .= $session->name() . " @ ";
        $html .= $session->track()->name();
        $html .= "</option>";
        return $html;
    }


    private function predecessorChain(\DbEntry\Session $s) {
        $html = "";

        if ($s->predecessor() !== NULL) {

            if ($s->predecessor()->predecessor() !== NULL)
                $html .= $this->predecessorChain($s->predecessor());

            $html .= $s->predecessor()->htmlName();
            $html .= " -&gt; ";
        }

        return $html;
    }


    private function successorChain(\DbEntry\Session $s) {
        $html = "";

        if ($s->successor() !== NULL) {

            $html .= " -&gt;";
            $html .= $s->successor()->htmlName();

            if ($s->successor()->successor() !== NULL)
                $html .= $this->successorChain($s->successor());
        }

        return $html;
    }
}
