<?php

class zm_session extends cContentPage {

    private $CanStart = NULL;
    private $CanStop = NULL;
    private $CanKill = NULL;



    public function __construct() {
        $this->MenuName   = _("Session");
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["Session"];
    }



    public function getHtml() {
        global $acswuiUser;

        // check permissions
        foreach (ServerSlot::listSlots() as $ss) {
            $this->CanStart[$ss->id()] = $acswuiUser->hasPermission("Session_Slot" . $ss->id() . "_Start");
            $this->CanStop[$ss->id()] = $acswuiUser->hasPermission("Session_Slot" . $ss->id() . "_Stop");
            $this->CanKill[$ss->id()] = $acswuiUser->hasPermission("Session_Slot" . $ss->id() . "_Kill");
        }

        // start html
        $html = "";


        foreach (ServerSlot::listSlots() as $ss) {
            $html .= "<h1>" . $ss->name() . "</h1>";

            if (!$ss->online()) {
                $html .= "<div class=\"SlotOffline\">" . _("Offline") . "</div>";
                continue;
            }

            $session = $ss->currentSession();
            if ($session === NULL) continue;

            // find carskins of all users
            $user_carskins = array();
            foreach ($session->drivenLaps() as $lap) {
                $uid = $lap->user()->id();
                if (array_key_exists($uid, $user_carskins)) continue;
                $user_carskins[$uid] = $lap->carSkin();
            }


            $html .= "<p>";
            $html .= $session->preset()->name() . " (" . $session->typeName() . ")<br>";
            $html .= $session->carClass()->name() . "<br>";
            $html .= $session->timestamp()->format("Y-m-d H:i:s") . "<br>";
            $html .= "</p>";

            $html .= "<div class=\"SessionImages\">";

            $html .= "<div class=\"TrackImage\">";
            $html .= $session->track()->htmlImg("", NULL, 300);
            $html .= "</div>";

            $html .= "<div class=\"DriverItems\">";
            foreach ($ss->driversOnline() as $user) {
                $html .= "<div class=\"DrivertItem\">";
                $html .= "<label>" . $user->displayName() . "</label>";
                if (array_key_exists($user->id(), $user_carskins)) {
                    $carskin = $user_carskins[$user->id()];
                    $html .= $carskin->htmlImg("", 100);
                }
                $html .= "</div>";
            }
            $html .= "</div>";
            $html .= "</div>";

            $html .= "<div class=\"SessionId\">Session-ID: ";
            $html .= $session->id();
            $html .= "</div>";
        }


        return $html;
    }
}

?>
