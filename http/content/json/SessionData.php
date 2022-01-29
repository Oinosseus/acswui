<?php

namespace Content\Json;

/**
 */
class SessionData extends \Core\JsonContent {


    public function __construct() {
        $this->requirePermission("Json");
    }


    public function getDataArray() {

        // get session
        if (!array_key_exists("SessionId", $_GET)) {
            \Core\Log::warning("No SessionId given!");
            return [];
        }
        $session = \DbEntry\Session::fromId($_GET["SessionId"]);
        if ($session === NULL) {
            \Core\Log::warning("Invalid SessionId '" . $_GET["SessionId"] . "'!");
            return [];
        }


        // get requested data
        $data = NULL;
        if (array_key_exists("Request", $_GET)) {

            switch ($_GET['Request']) {

                case "DriverPositions":
                    $data = $this->requestDriverPositions($session);
                    break;

                case "Collisions":
                    $data = $this->requestCollisions($session);
                    break;

                case "Laps":
                    $data = $this->requestLaps($session);
                    break;

                default:
                    \Core\Log::warning("Unknown request '" . $_GET['Request'] . "'");
                    break;
            }
        }

        return $data;
    }


    private function requestDriverPositions($session) {
        $data = array();

        foreach ($session->drivers() as $user) {
            if (!$user->privacyFulfilled()) continue;

            $data_user = array();
            $data_user['User'] = array();
            $data_user['User']['Name'] = $user->name();
            $data_user['User']['Color'] = $user->getParam("UserColor");

            $data_user['Positions'] = array();
            $index = 0;
            foreach ($session->dynamicPositions($user) as $pos) {
                if ($pos != 0)
                    $data_user['Positions'][] = array('x'=>$index, 'y'=>$pos);
                ++$index;
            }

            $data[] = $data_user;
        }

        return $data;
    }


    private function requestCollisions($session) {
        $user = \core\UserManager::currentUser();
        $data = array();

        // head row
        $header = array();
        $header[] = _("Timestamp");
        $header[] = _("Suspect");
        $header[] = _("Victim");
        $header[] = _("Speed");
        $header[] = _("Safety-Points");
        $header[] = _("Collision Id");
        $data['Header'] = $header;

        // data rows
        $data['Rows'] = array();
        $collisions = $session->collisions();
        for ($i = (count($collisions) - 1); $i >= 0; --$i) {
            $data_row = array();
            $c = $collisions[$i];

            $data_row[] = $user->formatDateTime($c->timestamp());
            $data_row[] = $c->user()->html();
            $data_row[] = (($c instanceof \DbEntry\CollisionCar) ? $c->otheruser()->html() : "" );
            $data_row[] = sprintf("%0.1f", $c->speed()) . " km/h";

            $distance = $session->drivenDistance($c->user());
            $sf_coll = \Core\ACswui::getParam(($c instanceof \DbEntry\CollisionEnv) ? "DriverRankingSfCe" : "DriverRankingSfCc");
            $sf_coll *= $c->speed() / \Core\ACswui::getParam("DriverRankingCollNormSpeed");
            $data_row[] = sprintf("%0.4f", $sf_coll);

            $data_row[] = $c->id();

            $data['Rows'][] = $data_row;
        }

        return $data;
    }


    private function requestLaps($session) {
        $user = \core\UserManager::currentUser();
        $data = array();

        // head row
        $header = array();
        $header[] = _("Lap");
        $header[] = _("Laptime");
        $header[] = "<span title=\"" . _("Difference to session best lap") . "\">" . _("Delta") . "</span>";
        $header[] = _("Cuts");
        $header[] = _("Driver");
        $header[] = _("Car");
        $header[] = _("Ballast");
        $header[] = _("Restrictor");
        $header[] = _("Traction");
        $header[] = _("Lap ID");
        $data['Header'] = $header;

        // data rows
        $data['Rows'] = array();
        $laps = $session->laps();
        $bestlap = $session->lapBest();
        $besttime = ($bestlap) ? $bestlap->laptime() : 0;
        for ($i = (count($laps) - 1); $i >= 0; --$i) {
            $data_row = array();
            $lap = $laps[$i];

            $distance = $session->drivenDistance($lap->user());

            $tr_css_class = ($lap->cuts() > 0) ? "InvalidLap" : "ValidLap";
            $html = "";
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

            $data['Rows'][] = $html;
        }

        return $data;
    }
}
