<?php

namespace Content\Html;

class AboutDriverRanking extends \core\HtmlContent {

    public function __construct() {
        parent::__construct(_("Driver Ranking"), _("Driver Ranking"));
    }

    public function getHtml() {
        $html = "";

        $t = _("The ACswui system does integrate a driver ranking system.
                With that system it is possible to rate the performance of drivers.\n
                This page gives an overview how ranking points are determined.");
        $html .= nl2br(htmlentities($t));


        $html .= $this->rankingPoints();
        $html .= $this->rankingPeriod();
        $html .= $this->rankingGroups();


        return $html;
    }


    private function rankingPoints() {
        $html = "";
        $html .= "<h1>" . _("Ranking Points") . "</h1>";

        $t = _("The driver ranking system applies points for experience, success and safety of drivers.
                Each of these categories are rated per driver and they sum up to the total ranking points per driver.");
        $html .= nl2br(htmlentities($t));


        $html .= "<h2>" . _("Experience") . "</h2>";

        $t = _("Every driven distance counts as experience.\n
                Depending on the session type, there are different points earned per distance.
                The experience points are granted relative to one million driven meters (Points / Mm).");
        $html .= nl2br(htmlentities($t));
        $html .= "<br><br>";

        $html .= _("Practice") . ": <strong>" . sprintf("%0.2f", \Core\ACswui::getParam("DriverRankingXpP")) . "</strong> Points / Mm<br>";
        $html .= _("Qualifying") . ": <strong>" . sprintf("%0.2f", \Core\ACswui::getParam("DriverRankingXpQ")) . "</strong> Points / Mm<br>";
        $html .= _("Race") . ": <strong>" . sprintf("%0.2f", \Core\ACswui::getParam("DriverRankingXpR")) . "</strong> Points / Mm<br>";



        $html .= "<h2>" . _("Sucess") . "</h2>";

        $t = _("By leading positions in race, qualifying and record tables, a driver can gain success points.\n
                Leading positions are counted as places prior to other drivers in a table.
                Think of it as counting positions from the bottom of a table.\n
                If for example a driver takes a second place in a race of five drivers, he has three leading positions (he is prior three other drivers in the result table).
                Was this second place in a race of ten drivers he would get eight leading positions.\n
                This automatically weights sessions with high driver amount as more rewarding,
                while beeing the only driver in a table gains no leading positions.\n
                Additionally the driver with the best race time earns points.");
        $html .= nl2br(htmlentities($t));
        if (\Core\ACswui::getParam("DriverRankingSxBtAvrg")) {
            $html .= "<br>";
            $t = _("Since points for best times will not automatically vanish over time, these will cummulate a lot.
                    New drivers will have a hard time to follow drivers that took place in record tables of many car-class / track combinations.
                    Thus, the best time points are averaged / relativized. This means the amount of best time points are divided by the amount of record tables each driver takes place in.");
            $html .= nl2br(htmlentities($t));
        }
        $html .= "<br><br>";

        $html .= _("Best Time") . ": <strong>" . sprintf("%0.2f", \Core\ACswui::getParam("DriverRankingSxBt")) . "</strong> Points / Position<br>";
        $html .= _("Race Time") . ": <strong>" . sprintf("%0.2f", \Core\ACswui::getParam("DriverRankingSxRt")) . "</strong> Points / Position<br>";
        $html .= _("Qualifying") . ": <strong>" . sprintf("%0.2f", \Core\ACswui::getParam("DriverRankingSxQ")) . "</strong> Points / Position<br>";
        $html .= _("Race") . ": <strong>" . sprintf("%0.2f", \Core\ACswui::getParam("DriverRankingSxR")) . "</strong> Points / Position<br>";



        $html .= "<h2>" . _("Safety") . "</h2>";

        $t = _("To restrict rough driving and encourage fair driving the ranking system counts points for unsave driving maneuvers.
                These points are typically negative, so they reduce the overall ranking points of a driver.\n
                Penalized maneuvers are cuts and collisions. Collisions are are related to the speed at which they happen.
                A collision at twice the normalized speed costs twice the penalty points, while a collision at a quarter of the normalized speed costs only a quarter of the points.");
        $html .= nl2br(htmlentities($t));
        $html .= "<br><br>";

        $html .= _("Cuts") . ": <strong>" . sprintf("%0.2f", \Core\ACswui::getParam("DriverRankingSfCt")) . "</strong> Points / Cut<br>";
        $html .= _("Collisions with Environment") . ": <strong>" . sprintf("%0.2f", \Core\ACswui::getParam("DriverRankingSfCe")) . "</strong> Points / Normspeed-Collision<br>";
        $html .= _("Collisions with other Cars") . ": <strong>" . sprintf("%0.2f", \Core\ACswui::getParam("DriverRankingSfCc")) . "</strong> Points / Normspeed-Collision<br>";
        $html .= _("Normalized Collision Speed") . ": <strong>" . sprintf("%0.2f", \Core\ACswui::getParam("DriverRankingCollNormSpeed")) . "</strong> km / h<br>";

        return $html;
    }


    private function rankingPeriod() {
        $html = "";
        $html .= "<h1>" . _("Ranking Period") . "</h1>";

        $t = _("The ranking points are not calculated over all sessions. Instead they are cumulated over a period of time.
                Sessions wich are older than this period are not considered for the ranking points anymore.\n
                An exception are positions in record tables. Since these records last, the ranking points for best times will last.
                ... well, at least until a slower driver gets faster.");
        $html .= nl2br(htmlentities($t));
        $html .= "<br><br>";

        $html .= _("Ranking Period") . ": <strong>" . \Core\ACswui::getParam("DriverRankingDays") . "</strong> " . _("Days") . "<br>";

        return $html;
    }


    private function rankingGroups() {
        if (\Core\Config::DriverRankingGroups == 0) return "";
        $html = "";
        $html .= "<h1>" . _("Ranking Groups") . "</h1>";

        $t = _("The driver ranking table is split into several groups.
                This allows drivers to compete in equal performance categories and allows performance ballancing on the server.");
        $html .= nl2br(htmlentities($t));
        $html .= "<br><br>";

        switch (\Core\ACswui::getParam("DriverRankingGroupType")) {
            case 'points':
                $t = _("The group assignment of each driver is defined by reaching an absolute amount of points.");
                break;
            case 'drivers':
                $t = _("Each group contains a certain amount of drivers.
                        Drivers with most ranking points are in the top group.
                        When the maximum amount of drivers is reached for a group, drivers are moved to the next lower group.");
                break;
            case 'percent':
                $t = _("The group assignment of each driver is defined by amount of points relative to the driver with the most.");
                break;
            default:
                \Core\Log::error("Unexpected ranking group type: " . \Core\ACswui::getParam("DriverRankingGroupType"));
                $t = "";
        }
        $html .= nl2br(htmlentities($t));
        $html .= "<br><br>";

        $t = _("The minimum requirement for each group can be viewed in the driver ranking.");
        $html .= nl2br(htmlentities($t));


        return $html;
    }
}
