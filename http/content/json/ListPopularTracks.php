<?php

namespace Content\Json;

/**
 */
class ListPopularTracks extends \Core\JsonContent {


    public function __construct() {
        $this->requirePermission("Json");
    }


    public function getDataArray() {
        $u = \Core\UserManager::currentUser();
        $html = "";

        $html .= "<table>";
        $html .= "<tr>";
        $html .= "<td rowspan=\"2\" colspan=\"2\">" . _("Track") . "</td>";
        $html .= "<td rowspan=\"2\">" . _("Pits") . "</td>";
        $html .= "<td rowspan=\"2\">" . _("Length") . "</td>";
        $html .= "<td colspan=\"2\">" . _("Driven") . "</td>";
        $html .= "</tr>";

        $html .= "<tr>";
        $html .= "<td>" . _("Laps") . "</td>";
        $html .= "<td>" . _("Length") . "</td>";
        $html .= "</tr>";


        $tracks = \DbEntry\Track::listTracks();
        usort($tracks, "\DbEntry\Track::compareDrivenLength");
        foreach ($tracks as $t) {

            // ingore non-driven tracks
            if ($t->drivenLaps() == 0) break;

            $html .= "<tr>";
            $html .= "<td class=\"TrackImage\">{$t->html(TRUE, FALSE, TRUE)}</td>";
            $html .= "<td>{$t->html(TRUE, TRUE, FALSE)}</td>";
            $html .= "<td>{$t->pitboxes()}</td>";
            $html .= "<td>{$u->formatLength($t->length())}</td>";
            $html .= "<td>{$t->drivenLaps()}</td>";
            $html .= "<td>{$u->formatLength($t->drivenLength())}</td>";
            $html .= "</tr>";
        }


        $html .= "</table>";


        $data = array();
        $data[] = $html;
        return $data;
    }

}
