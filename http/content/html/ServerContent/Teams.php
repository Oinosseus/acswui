<?php

namespace Content\Html;

class Teams extends \core\HtmlContent {

    private $CurrentTeam = NULL;

    public function __construct() {
        parent::__construct(_("Teams"),  _("Teams"));
        $this->requirePermission("ServerContent_Teams_View");
    }

    public function getHtml() {
        $html = "";

        $html .= "<p>";
        $html .= _("Teams are identified by the 'team' attribute in ui_skin.json of each car skin.
                    To avoid presenting community unrelevant skins,
                    only teams are respected where the 'Steam64GUID' attribute is given in the ui_skin.json.
                    This 'Steam64GUID' attribute is specific for the ACswui system and identifies the preserved driver of the car skin.
                   ");
        $html .= "</p>";

        if (array_key_exists("TeamId", $_REQUEST)) {
            $this->CurrentTeam = \DbEntry\Team::fromId($_REQUEST['TeamId']);
        }


        if ($this->CurrentTeam === NULL) {
            $html .= $this->listTeams();
        } else {
            $html .= $this->presentTeam();
        }


        return $html;
    }


    private function listTeams() {
        $html = "";

        $html .= "<ul>";
        foreach (\DbEntry\Team::listTeams() as $team) {
            $html .= "<li>" . $team->htmlName() . "</li>";
        }
        $html .= "</ul>";

        return $html;
    }


    public function presentTeam() {
        $html = "";

        $html .= "<h1>" . _("Tem Members") . "</h1>";
        $html .= "<ul>";
        foreach ($this->CurrentTeam->users() as $u) {
            $html .= "<li>" . $u->html() . "</li>";
        }
        $html .= "</ul>";

        $html .= "<h1>" . _("Team Cars") . "</h1>";
        $classed_carskins = \DbEntry\CarClass::groupCarSkins($this->CurrentTeam->carSkins());
        foreach ($classed_carskins as $carclass_id=>$carskins) {

            if ($carclass_id) {
                $carclass = \DbEntry\CarClass::fromId((int) $carclass_id);
                $html .= "<h2>" . $carclass->name() . "</h2>";
            } else {
                $html .= "<h2>" . _("Unclassed Cars") . "</h2>";
            }

            foreach ($carskins as $carskin) {
                $html .= $carskin->html();
            }
        }

        return $html;
    }
}
