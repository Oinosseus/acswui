<?php

namespace Content\Html;

class W_Sessions extends \core\HtmlContent {

    private $CanControl = FALSE;

    public function __construct() {
        parent::__construct(_("Sessions"),  "");
        $this->requirePermission("Sessions_View");
    }


    public function getHtml() {
        $this->CanControl = \Core\UserManager::loggedUser()->permitted("Sessions_Control");
        $html = "";

        if ($this->CanControl && array_key_exists("StopSlot", $_POST)) {
            $slot = \Core\ServerSlot::fromId($_POST["StopSlot"]);
            $slot->stop();
        }
        if ($this->CanControl && array_key_exists("StartSlot", $_POST)) {
            $slot = \Core\ServerSlot::fromId($_POST["StartSlot"]);
            $slot->start();
        }


        $html .= $this->newHtmlForm("POST");
        $html .= "<table>";
        $html .= "<tr><th>Slot</th><th>Status</th><th>Control</th></tr>";
        for ($i=1; $i <= \Core\Config::ServerSlotAmount; ++$i) {
            $slot = \Core\ServerSlot::fromId($i);
            $html .= "<tr>";
            $html .= "<td>" . $slot->name() . "</td>";
            $html .= "<td>" . (($slot->online())  ? "ONLINE" : "OFFLINE") . "</td>";
            if ($this->CanControl) {
                $html .= "<td>";
                if ($slot->online()) {
                    $html .= "<button type=\"submit\" name=\"StopSlot\" value=\"" . $slot->id() . "\">Stop</button>";
                } else {
                    $html .= "<button type=\"submit\" name=\"StartSlot\" value=\"" . $slot->id() . "\">Start</button>";
                }
                $html .= "</td>";
            }
            $html .= "</tr>";
        }
        $html .= "</table></form>";

        return $html;
    }
}
