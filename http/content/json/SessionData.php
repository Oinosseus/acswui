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
        $data['UserInfo'] = array();
        $data['Positions'] = $session->dynamicPositions();

        // entries
        foreach ($session->entries() as $e) {
            $data_user = array();
            $data_user = array();
            $data_user['Name'] = $e->name();
            $data_user['Color'] = (new \Parameter\ParamColor(NULL, NULL))->value();
            $data['UserInfo'][$e->id()] = $data_user;
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
            $data_row[] = round($c->speed()) . " km/h";

            $distance = $session->drivenDistance($c->user());
            if ($session->type() != \Enums\SessionType::Practice || \Core\ACswui::getPAram('DriverRankingSfAP') == FALSE) {
                $sf_coll = \Core\ACswui::getParam(($c instanceof \DbEntry\CollisionEnv) ? "DriverRankingSfCe" : "DriverRankingSfCc");
                $sf_coll *= $c->speed() / \Core\ACswui::getParam("DriverRankingCollNormSpeed");
            } else {
                $sf_coll = 0;
            }
            $data_row[] = sprintf("%0.1f", round($sf_coll, 1));

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
        $header[] = _("BOP");
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
            $html .= "<td>" . sprintf("%+dkg, %+d&percnt;", $lap->ballast(), $lap->restrictor()) . "</td>";
            $html .= "<td>" . sprintf("%0.1f", 100 * $lap->grip()) . " &percnt;</td>";
            $html .= "<td>" . $lap->id() . "</td>";

            $data['Rows'][] = $html;
        }

        return $data;
    }
}
