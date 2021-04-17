<?php

class zm_session extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Session");
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["Session"];
    }

    public function getHtml() {

        $html = "";

        $html .= "<table>";
        $html .= "<tr><th rowspan=\"2\">" . _("Server Slot") . "</th><th rowspan=\"2\">" . _("Status") . "</th><th rowspan=\"2\">" . _("Server Preset") . "</th><th rowspan=\"2\">" . _("Car Class") . "</th><th rowspan=\"2\">" . _("Track") . "</th><th colspan=\"3\">" . _("Session") . "</th></tr>";
        $html .= "<tr><th>" . _("Id") . "</th><th>" . _("Type") . "</th><th>" . ("Start Time") . "</th></tr>";
        foreach (ServerSlot::listSlots() as $ss) {
            $html .= "<tr>";
            $html .= "<td>" . $ss->name() . "</td>";
            if ($ss->online()) {
                $html .= '<td style="color:#0d0; font-weight:bold;">online</td>';
            } else {
                $html .= '<td style="color:#d00; font-weight:bold;">offline</td>';
            }
            $session = $ss->currentSession();
            if ($session !== NULL) {
                $html .= "<td>" . $session->preset()->name() . "</td>";
                $html .= "<td>" . $session->carClass()->name() . "</td>";
                $html .= "<td>" . $session->track()->name() . "</td>";
                $html .= "<td>" . $session->id() . "</td>";
                $html .= "<td>" . $session->typeName() . "</td>";
                $html .= "<td>" . $session->timestamp()->format("Y-m-d H:i:s") . "</td>";
            }
            $html .= "</tr>";
        }
        $html .= "</table>";


        return $html;
    }
}

?>
