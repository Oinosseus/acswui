<?php

namespace Content\Html;

class Z_About extends \core\HtmlContent {

    public function __construct() {
        parent::__construct(_("About"), _("About the ACswui System"));
    }

    public function getHtml() {
        $html = "";

        $html .= "<h1>ACswui</h1>";
        $html .= "<p>" . _("The acronym 'ACswui' stands for Assetto Corsa Server Web User Interface.") . "</p>";

        $html .= "<p>";
        $t = _("The letters 'AC' are written in capitals intentionally.
                Since <a href=\"https://www.assettocorsa.it\">Assetto Corsa</a> is THE greatest racing simulator in the current decade (at least in my humble oppinion), the capital letters shall show my respect to <a href=\"http://www.kunos-simulazioni.com\">KUNOS Simulazioni</a>.
                The remaining 'swui' is just a user interface around the simulation.");
        $html .= nl2br($t);
        $html .= "</p>";

        $html .= "<p>" . _("It must be said, that the ACswui system stands in no legal conjunction with KUNOS Simulazioni.") . "</p>";


        return $html;
    }
}
