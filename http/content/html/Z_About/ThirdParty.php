<?php

namespace Content\Html;

class ThirdParty extends \core\HtmlContent {

    public function __construct() {
        parent::__construct(_("Third Party"), _("Integration of Third Party Software"));
    }

    public function getHtml() {
        $html = "";

        $html .= "<p>If I have seen further it is by standing on ye sholders of Giants <a href=\"https://en.wikiquote.org/wiki/Isaac_Newton\">[Isaac Newton]</a>.<p>";
        $html .= "<p>The idea of ACswui is to have a comfortable user interface.
                  If others have already developed techniques that support this,
                  it makes sense to make use of these if applicable.
                  </p>";

        // php-steam-openid
        $html .= "<h1>php-steam-openid</h1>";
        $html .= "<p>This library provides an interface to make use of Steam as Open-ID provider for user login.</p>";
        $html .= "<p>Source: <a href=\"https://github.com/fisuku/php-steam-openid\">github</a><br>
                  License: MIT License, see submodules source directory for a copy of the license
                  </p>";

        // Flagpedia
        $html .= "<h1>Flagpedia</h1>";
        $html .= "<p>This is a website wich provides acess to international country codes and flag images.</p>";
        $html .= "<p>Source: <a href=\"https://flagpedia.net/\">flagpedia.net</a><br>
                  ACswui uses this to make users/drivers present their representation country.
                  The little flag symbols are not hosted on this server, they are loaded from flagpedia.net.
                  </p>";

        // real penalty
        $html .= "<h1>Real Penalty</h1>";
        $html .= "<p>A UDP plugin for the assetto corsa server, created by <a href=\"https://www.patreon.com/DavideBolognesi/posts\">Davide Bolognesi</a>.
                  It helps to ensure fair racing by monitoring drivers behavior and automatially apply penalties (like drivetru).
                  </p>";
        $html .= "<p>The actually Real Penalty software is not integrated into ACswui.
                  You have to procure it separately.
                  But ACswui is prepared to configure and operate the Real Penalty plugin.
                  </p>";

        // Chart.js
        $html .= "<h1>Chart.js</h1>";
        $html .= "<p>A javascript library to generate diagrams.</p>";
        $html .= "<p>To be honest, javascript is not my favourite pet.
                  So, I am really glad that a lot of contributors created <a href=\"https://www.chartjs.org/\">Chart.js</a>,
                  which makes it easy to present diagrams in ACswui.
                  At the moment this javascript library is included via cloudflare and not self hosted.
                  This makes it easier to integrate - hopefully only the diagrams are loaded from the foreign server.
                  </p>";


        return $html;
    }
}
