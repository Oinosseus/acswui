<?php

namespace Content\Html;

class Cronjobs extends \core\HtmlContent {

    private $CanForce = False;


    public function __construct() {
        parent::__construct(_("Cronjobs"), _("Cronjobs Overview"));
        $this->requirePermission("Cronjobs_View");
    }

    public function getHtml() {
        $user = \Core\UserManager::currentUser();
        $this->CanForce = \Core\UserManager::permitted("Cronjobs_Force");
        $html = "";

        $html .= "<table>";
        $html .= "<tr>";
        $html .= "<th>" . _("Cronjob") . "</th>";
        $html .= "<th>" . _("Status") . "</th>";
        $html .= "<th>" . _("Last Execution") . "</th>";
        $html .= "<th>" . _("Last Duration") . "</th>";
        $html .= "<th>" . _("Interval") . "</th>";
        $html .= "</tr>";

        foreach (\Core\Cronjob::listCronjobNames() as $cj_name) {
            $cj = \Core\Cronjob::fromName($cj_name);

            $html .= "<tr>";
            $html .= "<td>" . $cj->name() . "</td>";
            $html .= "<td>" . \Core\Cronjob::status2str($cj->status()) . "</td>";
            $html .= "<td>" . $user->formatDateTime($cj->lastExecutionTimestamp()) . "</td>";
            $html .= "<td>" . $user->formatLaptime(1e3 * $cj->lastExecutionDuration()) . "</td>";
            $html .= "<td>" . $cj->intervalStr() . "</td>";

            if ($this->CanForce) {
                $html .= "<td><a href=\"cron.php?VERBOSE&FORCE=" . $cj->name() . "\" title=\"" . _("Force execute") . "\">&#x2699;</a></th>";
            }

            $html .= "</tr>";

            $cj = NULL;  // since cronjobs are locked, this ensures the CJ to be free
        }

        $html .= "</table>";

        return $html;
    }

}
