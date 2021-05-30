<?php

class zm_session extends cContentPage {

    private $CanStart = NULL;
    private $CanStop = NULL;
    private $CanKill = NULL;
    private $CanAccessRestricted = FALSE;

    private $CacheServerPresets = NULL;
    private $CacheCarClasses = NULL;
    private $CacheTracks = NULL;

    private $RequestPreset = Null;
    private $RequestCarClass = Null;
    private $RequestTrack = Null;



    public function __construct() {
        $this->MenuName   = _("Session");
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["Session"];
    }



    public function getHtml() {
        global $acswuiUser;

        // --------------------------------------------------------------------
        //                       Check Permissions
        // --------------------------------------------------------------------

        # per slot
        foreach (ServerSlot::listSlots() as $ss) {
            $this->CanStart[$ss->id()] = $acswuiUser->hasPermission("Session_Slot" . $ss->id() . "_Start");
            $this->CanStop[$ss->id()] = $acswuiUser->hasPermission("Session_Slot" . $ss->id() . "_Stop");
            $this->CanKill[$ss->id()] = $acswuiUser->hasPermission("Session_Slot" . $ss->id() . "_Kill");
        }

        # general
        $this->CanAccessRestricted = $acswuiUser->hasPermission("Server_PresetAccessRestricted");



        // --------------------------------------------------------------------
        //                     Save Requested Variables
        // --------------------------------------------------------------------

        # ServerPreset
        if (array_key_exists('ServerPreset', $_POST)) {
            $this->RequestPreset = new ServerPreset((int) $_POST['ServerPreset']);
            $_SESSION['ServerPreset'] = $this->RequestPreset->id();
        } else if (array_key_exists('ServerPreset', $_SESSION)) {
            $this->RequestPreset = new ServerPreset((int) $_SESSION['ServerPreset']);
        }

        # CarClass
        if (array_key_exists('CarClass', $_POST)) {
            $this->RequestCarClass = new CarClass((int) $_POST['CarClass']);
            $_SESSION['CarClass'] = $this->RequestCarClass->id();
        } else if (array_key_exists('CarClass', $_SESSION)) {
            $this->RequestCarClass = new CarClass((int) $_SESSION['CarClass']);
        }

        # Track
        if (array_key_exists('Track', $_POST)) {
            $this->RequestTrack = new Track((int) $_POST['Track']);
            $_SESSION['Track'] = $this->RequestTrack->id();
        } else if (array_key_exists('Track', $_SESSION)) {
            $this->RequestTrack = new Track((int) $_SESSION['Track']);
        }



        // --------------------------------------------------------------------
        //                     Process Request
        // --------------------------------------------------------------------

        # start server
        if (array_key_exists('StartServerOnSlot', $_POST)) {

            $ss = new ServerSlot($_POST['StartServerOnSlot']);
            if ($ss->online() === FALSE
                && $this->CanStart[$ss->id()]
                && ($this->RequestPreset->restricted() === FALSE || $this->CanAccessRestricted)
                ) {
                $ss->start($this->RequestPreset,
                           $this->RequestCarClass,
                           $this->RequestTrack);
            }

            // send webhooks
            Webhooks::manualServerStart($ss,
                                        $this->RequestPreset,
                                        $this->RequestCarClass,
                                        $this->RequestTrack);
        }

        # stop server
        if (array_key_exists('StopServerSlot', $_POST)) {
            $ss = new ServerSlot($_POST['StopServerSlot']);
            if ($ss->online() === TRUE
                && $this->CanStop[$ss->id()]) {
                $ss->stop();
            }
        }

        # kill server
        if (array_key_exists('KillServerSlot', $_POST)) {
            $ss = new ServerSlot($_POST['KillServerSlot']);
            if ($ss->online() === TRUE
                && $this->CanKill[$ss->id()]) {
                $ss->stop(TRUE);
            }
        }



        // --------------------------------------------------------------------
        //                             Generate HTML
        // --------------------------------------------------------------------

        $html = "";

        foreach (ServerSlot::listSlots() as $ss) {
            $html .= "<h1>" . $ss->name() . "</h1>";

            if (!$ss->online()) {
                if ($this->CanStart) {
                    $html .= $this->startServerForm($ss);
                } else {
                    $html .= "<div class=\"SlotOffline\">" . _("Offline") . "</div>";
                }

            } else {
                $html .= $this->sessionInfo($ss);
            }
        }

        return $html;
    }


    private function sessionInfo(ServerSlot $ss) {
        $html = "";

        $session = $ss->currentSession();
        if ($session === NULL) return $html;

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

        # stop session
        if ($this->CanStop[$ss->id()] && $ss->currentSession()->type() != 3 && count($ss->driversOnline()) == 0) {
            $html .= '<form action="" method="post">';
            $html .= '<button type="submit" name="StopServerSlot" value="' . $ss->id() . '">' . _("Stop Server") . '</button>';
            $html .= '</form>';

        # kill session
        } else if ($this->CanKill[$ss->id()]) {
            $html .= '<form action="" method="post">';
            $html .= '<button type="submit" name="KillServerSlot" value="' . $ss->id() . '">' . _("Kill Server") . '</button>';
            $html .= '</form>';
        }

        return $html;
    }



    private function startServerForm(ServerSlot $ss) {
        $html = "";
        if (!$this->CanStart[$ss->id()]) return $html;

        $this->loadCaches();

        $html .= '<form action="" method="post">';

        // preset
        $html .= _("Server Preset");
        $html .= ' <select name="ServerPreset">';
        foreach ($this->CacheServerPresets as $sp) {
            if ($this->RequestPreset === NULL) $this->RequestPreset = $sp;
            $selected = ($this->RequestPreset->id() == $sp->id()) ? "selected" : "";
            $html .= '<option value="' . $sp->id() . '"' . $selected . '>' . $sp->name() . '</option>';
        }
        $html .= '</select>';
        $html .= '<br>';

        # car class
        $html .= _("Car Class");
        $html .= ' <select name="CarClass">';
        foreach ($this->CacheCarClasses as $cc) {
            if ($this->RequestCarClass === NULL) $this->RequestCarClass = $cc;
            $selected = ($this->RequestCarClass->id() == $cc->id()) ? "selected" : "";
            $html .= '<option value="' . $cc->id() . '"' . $selected . '>' . $cc->name() . '</option>';
        }
        $html .= '</select>';
        $html .= '<br>';

        # track
        $html .= _("Track");
        $html .= ' <select name="Track">';
        foreach ($this->CacheTracks as $t) {
            if ($this->RequestTrack === NULL) $this->RequestTrack = $t;
            $selected = ($this->RequestTrack->id() == $t->id()) ? "selected" : "";
            $name_str = $t->name();
            $name_str .= " (" . sprintf("%0.1f", $t->length()/1000) . "km";
            $name_str .= ", " . $t->pitboxes() . "pits)";
            $html .= '<option value="' . $t->id() . '"' . $selected . ">" . $name_str . "</option>";
        }
        $html .= '</select>';
        $html .= '<br>';

        # start
        $html .= '<button type="submit" name="StartServerOnSlot" value="' . $ss->id() . '">' . _("Start Server") . '</button>';

        $html .= '</form>';

        return $html;
    }



    private function loadCaches() {
        if ($this->CacheServerPresets === NULL) {
            $this->CacheServerPresets = ServerPreset::listPresets($this->CanAccessRestricted);
        }

        if ($this->CacheCarClasses === NULL) {
            $this->CacheCarClasses = CarClass::listClasses();
        }

        if ($this->CacheTracks === NULL) {
            $this->CacheTracks = Track::listTracks();
        }
    }
}

?>
