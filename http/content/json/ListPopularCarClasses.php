<?php

namespace Content\Json;

/**
 */
class ListPopularCarClasses extends \Core\JsonContent {


    public function __construct() {
        $this->requirePermission("Json");
    }


    public function getDataArray() {
        $u = \Core\UserManager::currentUser();
        $html = "";

        $html .= "<table>";
        $html .= "<tr>";
        $html .= "<td colspan=\"2\">" . _("Car Class") . "</td>";
        $html .= "<td>" . _("Laps") . "</td>";
        $html .= "<td>" . _("Length") . "</td>";
        $html .= "</tr>";


        $carclasses = \DbEntry\CarClass::listClasses();
        usort($carclasses, "\DbEntry\CarClass::compareDrivenLength");
        foreach ($carclasses as $cc) {

            // ingore non-driven tracks
            if ($cc->drivenLaps() == 0) break;

            $html .= "<tr>";
            $html .= "<td class=\"CarClassImage\">{$cc->html(TRUE, FALSE, TRUE)}</td>";
            $html .= "<td>{$cc->html(TRUE, TRUE, FALSE)}</td>";
            $html .= "<td>{$cc->drivenLaps()}</td>";
            $html .= "<td>{$u->formatLength($cc->drivenLength())}</td>";
            $html .= "</tr>";
        }


        $html .= "</table>";


        $data = array();
        $data[] = $html;
        return $data;
    }

}
