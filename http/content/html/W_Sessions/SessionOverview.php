<?php

namespace Content\Html;

class SessionOverview extends \core\HtmlContent {

    private $CurrentSession = NULL;

    private $FilterShowPractice = FALSE;
    private $FilterShowQualifying = FALSE;
    private $FilterShowRace = TRUE;

    public function __construct() {
        parent::__construct(_("Overview"),  "Session OVerview");
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

        $this->checkFilters();

        // retrieve requested session
        if (array_key_exists("SessionId", $_REQUEST)) {
            $this->CurrentSession = \DbEntry\Session::fromId($_REQUEST['SessionId']);
        } else {
            $this->CurrentSession = \DbEntry\Session::latestSession();
        }

        $html .= $this->sessionSlector();

        if ($this->CurrentSession !== NULL) {
            $html .= $this->listSessionInformation();
            $html .= $this->listResults();
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
        $html .= "<th>" . _("Ballast") . "</th>";
        $html .= "<th>" . _("Restrictor") . "</th>";
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
            $html .= "<td>" . $lap->ballast() . " kg</td>";
            $html .= "<td>" . $lap->restrictor() . " &percnt;</td>";
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


    private function listResults() {
        $user = \core\UserManager::currentUser();
        $html = "<h1>" . _("Session Results") . "</h1>";

        // laptime diagram
        $positions = count(\DbEntry\SessionResult::listSessionResults($this->CurrentSession));
        $max_height = (1.2 * $positions + 6) . "em";
        $html .= "<div id=\"SessionPositionDiagram\">";
        $title = _("Session Position Diagram");
        $axis_x_title = ($this->CurrentSession->type() == \DbEntry\Session::TypeRace) ? _("Laps") : _("Minutes");
        $axis_y_title = _("Position");
        $sid = $this->CurrentSession->id();
        $height = $positions*5;
        $html .= "<canvas axYTitle=\"$axis_y_title\" axXTitle=\"$axis_x_title\" title=\"$title\" sessionId=\"$sid\" positions=\"$positions\" width=\"100\" height=\"$height\" style=\"max-height: $max_height;\"></canvas>";
        $html .= "</div>";

        $html .= "<table>";
        $html .= "<tr>";
        $html .= "<th rowspan=\"2\">"  . _("Position") . "</th>";
        $html .= "<th colspan=\"2\" rowspan=\"2\">"  . _("Driver") . "</th>";
        $html .= "<th rowspan=\"2\">"  . _("Car") . "</th>";
        $html .= "<th rowspan=\"2\">"  . _("Best Lap") . "</th>";
        $html .= "<th rowspan=\"2\">"  . _("Total Time") . "</th>";
        $html .= "<th rowspan=\"2\">"  . _("Ballast") . "</th>";
        $html .= "<th rowspan=\"2\">"  . _("Restrictor") . "</th>";
        $html .= "<th colspan=\"2\">"  . _("Driven") . "</th>";
        $html .= "<th rowspan=\"2\">"  . _("Cuts") . "</th>";
        $html .= "<th colspan=\"2\">"  . _("Collisions") . "</th>";
        $html .= "<th rowspan=\"2\">"  . _("Ranking Points") . "</th>";
        $html .= "</tr>";

        $html .= "<tr>";
        $html .= "<th>"  . _("Laps") . "</th>";
        $html .= "<th>"  . _("Distance") . "</th>";
        $html .= "<th>"  . _("Environment") . "</th>";
        $html .= "<th>"  . _("Other Cars") . "</th>";
        $html .= "<tr>";

        // list all results
        $results = $this->CurrentSession->results();
        usort($results, "\DbEntry\SessionResult::comparePosition");
        foreach ($results as $r) {
            $html .= "<tr>";
            $html .= "<td>" . $r->position() ."</td>";

            $html .= "<td class=\"SessionResultsDriverFlagCell\">" . $r->user()->parameterCollection()->child("UserCountry")->valueLabel() . "</td>";
            $html .= "<td>" . $r->user()->html() . "</td>";
            $html .= "</td>";

            $html .= "<td class=\"SessionResultsCarSkinCell\">" . $r->carSkin()->html(TRUE, FALSE) ."</td>";
            $html .= "<td>" . $user->formatLaptime($r->bestLaptime()) . "</td>";
            $html .= "<td>" . $user->formatLaptime($r->totalTime()) . "</td>";
            $html .= "<td>" . $r->ballast() . " kg</td>";
            $html .= "<td>" . $r->restrictor() . " &percnt;</td>";
            $html .= "<td>" . $r->amountLaps() . "</td>";
            $html .= "<td>" . $user->formatDistance($r->amountLaps() * $r->session()->track()->length()) . "</td>";
            $html .= "<td>" . $r->amountCuts() . "</td>";
            $html .= "<td>" . $r->amountCollisionEnv() . "</td>";
            $html .= "<td>" . $r->amountCollisionCar() . "</td>";

            // ranking points
            $rp = $r->rankingPoints();
            $html .= "<td>";
            $title =  "XP:" . $rp['XP']['Sum'] . "\n";
            $title .= "SX:" . $rp['SX']['Sum'] . "\n";
            $title .= "SF:" . $rp['SF']['Sum'];
            $html .= "<span title=\"$title\">" . ($rp['XP']['Sum'] + $rp['SX']['Sum'] + $rp['SF']['Sum']) . "</span>";
            $html .= "</td>";

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
