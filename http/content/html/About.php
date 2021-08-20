<?php

namespace Content\Html;

class About extends \core\HtmlContent {

    public function __construct() {
        parent::__construct(_("About"), _("About the ACswui System"));
    }

    public function getHtml() {
        $html = "";

        $html .= "<h1>ACswui</h1>";
        $html .= "<p>The acronym 'ACswui' stands for Assetto Corsa Server Web User Interface.</p>";
        $html .= "<p>The letters 'AC' are written in capitals intentionally. ";
        $html .= "Since <a href=\"https://www.assettocorsa.it\">Assetto Corsa</a> is THE greatest racining simulation in the current decade (at least in my humble oppinion),
                  the capital latters shall show my respect to <a href=\"http://www.kunos-simulazioni.com\">KUNOS Simulationi</a>.
                  The remaining 'swui' is just a user interface around the simulation.
                  </p>";
        $html .= "<p>It must be said, that the ACswui system stands in no legal conjunction with KUNOZ Simulationi.</p>";


        $html .= "<h1>Third Party Software</h1>";
        $html .= "<p>If I have seen further it is by standing on ye sholders of Giants <a href=\"https://en.wikiquote.org/wiki/Isaac_Newton\">[Isaac Newton]</a>.<p>";
        $html .= "<p>The idea of ACswui is to have a comfortable user interface.
                  If others already developed techniques that support this,
                  it makes sense to make use of these if applicable.
                  </p>";

        $html .= "<h2>php-steam-openid</h2>";
        $html .= "<p>This library provides an interface to make use of Steam as Open-ID provider for user login.</p>";
        $html .= "<p>Source: <a href=\"https://github.com/fisuku/php-steam-openid\">github</a><br>
                  License: MIT License, see submodules source directory for a copy oif the license
                  </p>";


        return $html;
    }
}
