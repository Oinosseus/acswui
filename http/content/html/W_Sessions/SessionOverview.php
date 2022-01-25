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
        $html .= "<canvas width=\"100\" height=\"20\" axYTitle=\"$axis_y_title\" axXTitle=\"$axis_x_title\" title=\"$title\" maxDelta=\"$max_delta\"></canvas>";
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
                if ($user->getParam("UserLaptimeDistriDiaEnaHist")) {
                    $html .= "<button type=\"button\" sessionId=\"" . $this->CurrentSession->id() . "\" userId=\"" . $lap->user()->id() . "\" onclick=\"LaptimeDistributionDiagramLoadData(this, 'bar')\" title=\"" . _("Load Laptime Distribution Data") . "\">";
                    $html .= "&#x1f4ca;</button> ";
                }
                $html .= "<button type=\"button\" sessionId=\"" . $this->CurrentSession->id() . "\" userId=\"" . $lap->user()->id() . "\" onclick=\"LaptimeDistributionDiagramLoadData(this, 'line')\" title=\"" . _("Load Laptime Distribution Data") . "\">";
                $html .= "&#x1f4c8;</button> ";
            }
            $html .= "</td>";

            $html .= "</tr>";
        }

        $html .= "</table>";
        return $html;
    }


    private function listCollisions() {
        $user = \core\UserManager::currentUser();

        $html = "<h1>" . _("Collisions") . "</h1>";
        $html .= "<table>";

        // header
        $html .= "<tr>";
        $html .= "<th>" . _("Timestamp") . "</th>";
        $html .= "<th>" . _("Suspect") . "</th>";
        $html .= "<th>" . _("Victim") . "</th>";
        $html .= "<th>" . _("Speed") . "</th>";
        $html .= "<th>" . _("Safety-Points") . "</th>";
        $html .= "<th>" . _("Id") . "</th>";
        $html .= "<tr>";

        $collisions = $this->CurrentSession->collisions();
        for ($i = (count($collisions) - 1); $i >= 0; --$i) {
            $c = $collisions[$i];

            $html .= "<tr>";
            $html .= "<td>" . $user->formatDateTime($c->timestamp()) . "</td>";
            $html .= "<td>" . $c->user()->html() . "</td>";
            $html .= "<td>" . (($c instanceof \DbEntry\CollisionCar) ? $c->otheruser()->html() : "" ) . "</td>";
            $html .= "<td>" . sprintf("%0.1f", $c->speed()) . " km/h</td>";

            $distance = $this->CurrentSession->drivenDistance($c->user());
            if ($distance > 0) {
                $sf_coll = \Core\ACswui::getParam("DriverRankingCollNormSpeed") * $c->speed();
                $sf_coll *= \Core\ACswui::getParam(($c instanceof \DbEntry\CollisionEnv) ? "DriverRankingSfCe" : "DriverRankingSfCc");
                $sf_coll /= $distance;
                $html .= "<td>" . sprintf("%0.4f", $sf_coll) . "</td>";
            } else {
                $html .= "<td>&#x221e;</td>";
            }

            $html .= "<td>" . $c->id() . "</td>";
            $html .= "</tr>";
        }

        $html .= "</table>";
        return $html;
    }


    private function listLaps() {
        $user = \core\UserManager::currentUser();
        $html = "<h1>" . _("All Laps") . "</h1>";
        $html .= "<table>";

        // header
        $html .= "<tr>";
        $html .= "<th>" . _("Lap") . "</th>";
        $html .= "<th>" . _("Laptime") . "</th>";
        $html .= "<th><span title=\"" . _("Difference to session best lap") . "\">" . _("Delta") . "</span></th>";
        $html .= "<th>" . _("Cuts") . "</th>";
        $html .= "<th>" . _("Driver") . "</th>";
        $html .= "<th>" . _("Car") . "</th>";
        $html .= "<th>" . _("Ballast") . "</th>";
        $html .= "<th>" . _("Restrictor") . "</th>";
        $html .= "<th>" . _("Traction") . "</th>";
        $html .= "<th>" . _("Lap ID") . "</th>";
        $html .= "<tr>";

        $laps = $this->CurrentSession->laps();
        $bestlap = $this->CurrentSession->lapBest();
        $besttime = ($bestlap) ? $bestlap->laptime() : 0;
        for ($i = (count($laps) - 1); $i >= 0; --$i) {
            $lap = $laps[$i];

            $tr_css_class = ($lap->cuts() > 0) ? "InvalidLap" : "ValidLap";
            $html .= "<tr class=\"$tr_css_class\">";
            $html .= "<td>" . ($lap->id() - $laps[0]->id() + 1) . "</td>";
            $html .= "<td>" . $user->formatLaptime($lap->laptime()) . "</td>";
            $html .= "<td>" . $user->formatLaptimeDelta($lap->laptime() - $besttime) . "</td>";
            $html .= "<td>" . $lap->cuts() . "</td>";
            $html .= "<td>" . $lap->user()->html() . "</td>";
            $html .= "<td>" . $lap->carSkin()->car()->name() . "</td>";
            $html .= "<td>" . $lap->ballast() . " kg</td>";
            $html .= "<td>" . $lap->restrictor() . " &percnt;</td>";
            $html .= "<td>" . sprintf("%0.1f", 100 * $lap->grip()) . " &percnt;</td>";
            $html .= "<td>" . $lap->id() . "</td>";
            $html .= "</tr>";
        }

        $html .= "</table>";
        return $html;
    }


    private function sessionSlector() {
        $html = "";
        $user = \core\UserManager::currentUser();

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
        foreach (\DbEntry\Session::listSessions($this->FilterShowRace, $this->FilterShowQualifying, $this->FilterShowPractice) as $s) {
            $selected = ($this->CurrentSession !== NULL && $this->CurrentSession->id() == $s->id()) ? "selected=\"Yes\"" : "";
            $html .= "<option value=\"" . $s->id() . "\" $selected>";
            $html .= " [" . $s->id() . "] ";
            $html .= $user->formatDateTimeNoSeconds($s->timestamp());
            $html .= " (" . $s->typeChar() . ") ";
            $html .= $s->name();
            $html .= "</option>";
        }
        $html .= "</select>";

        $html .= "</form>";
        return $html;
    }


}
