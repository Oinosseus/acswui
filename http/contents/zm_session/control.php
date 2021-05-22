<?php


class control extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Control");
        $this->PageTitle  = "Session Control";
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["Session", "Session_Control"];

        // class local vars
        $this->CurrentServerSlot = Null;
        $this->CurrentPreset = Null;
        $this->CurrentCarClass = Null;
        $this->CurrentTrack = Null;
    }

    private function get_fixed($group_name, $server_cfg_field) {
        // returns the fixed value of a server preset element
        // return False, when element is not fixed
        global $acswuiConfig;

        $key = $group_name . "_" . $server_cfg_field['TAG'];
        if (array_key_exists($key, $acswuiConfig->FixedServerConfig) === TRUE) {
            return $acswuiConfig->FixedServerConfig[$key];
        } else {
            return FALSE;
        }
    }

    public function getHtml() {

        // access global data
        global $acswuiConfig;
        global $acswuiLog;
        global $acswuiDatabase;
        global $acswuiUser;


        // check permissions
        $CanStartSlot = array();
        $CanStopSlot = array();
        $CanKillSlot = array();
        $CanAccessRestricted = $acswuiUser->hasPermission("Server_PresetAccessRestricted");
        foreach (ServerSlot::listSlots() as $slot) {
            $CanStartSlot[$slot->id()] = $acswuiUser->hasPermission("Session_Slot" . $slot->id() . "_Start");
            $CanStopSlot[$slot->id()] = $acswuiUser->hasPermission("Session_Slot" . $slot->id() . "_Stop");
            $CanKillSlot[$slot->id()] = $acswuiUser->hasPermission("Session_Slot" . $slot->id() . "_Kill");
        }


        // --------------------------------------------------------------------
        //                        Process Post Data
        // --------------------------------------------------------------------

        if (isset($_POST['SERVERSLOT_ID'])) {
            $this->CurrentServerSlot = new ServerSlot((int) $_POST['SERVERSLOT_ID']);
            $_SESSION['SERVER_CONTROL_SERVERSLOT_ID'] = $this->CurrentServerSlot->id();
        } else if (isset($_SESSION['SERVER_CONTROL_SERVERSLOT_ID'])) {
            $this->CurrentServerSlot = new ServerSlot((int) $_SESSION['SERVER_CONTROL_SERVERSLOT_ID']);
        }

        if (isset($_POST['PRESET_ID'])) {
            $this->CurrentPreset = new ServerPreset((int) $_POST['PRESET_ID']);
            $_SESSION['SERVER_CONTROL_PRESET_ID'] = $this->CurrentPreset->id();
        } else if (isset($_SESSION['SERVER_CONTROL_PRESET_ID'])) {
            $this->CurrentPreset = new ServerPreset((int) $_SESSION['SERVER_CONTROL_PRESET_ID']);
        }

        if (isset($_POST['CARCLASS_ID'])) {
            $this->CurrentCarClass = new CarClass((int) $_POST['CARCLASS_ID']);
            $_SESSION['SERVER_CONTROL_CARCLASS_ID'] = $this->CurrentCarClass->id();
        } else if (isset($_SESSION['SERVER_CONTROL_CARCLASS_ID'])) {
            $this->CurrentCarClass = new CarClass((int) $_SESSION['SERVER_CONTROL_CARCLASS_ID']);
        }

        if (isset($_POST['TRACK_ID'])) {
            $this->CurrentTrack = new Track((int) $_POST['TRACK_ID']);
            $_SESSION['SERVER_CONTROL_TRACK_ID'] = $this->CurrentTrack->id();
        } else if (isset($_SESSION['SERVER_CONTROL_TRACK_ID'])) {
            $this->CurrentTrack = new Track((int) $_SESSION['SERVER_CONTROL_TRACK_ID']);
        }



        // --------------------------------------------------------------------
        //                     Process Requested Action
        // --------------------------------------------------------------------

        if (isset($_POST['ACTION'])) {

            if ($_POST['ACTION'] == "START_SERVER") {

                if ($this->CurrentServerSlot->online() === FALSE
                    && $CanStartSlot[$this->CurrentServerSlot->id()]
                    && ($this->CurrentPreset->restricted() === FALSE || $CanAccessRestricted)
                    ) {
                    $this->CurrentServerSlot->start($this->CurrentPreset,
                                                    $this->CurrentCarClass,
                                                    $this->CurrentTrack);
                }

                // send webhooks
                Webhooks::manualServerStart($this->CurrentServerSlot,
                                            $this->CurrentPreset,
                                            $this->CurrentCarClass,
                                            $this->CurrentTrack);


            } else if ($_POST['ACTION'] == "STOP_SERVER") {
                if ($this->CurrentServerSlot->online() === TRUE
                    && $CanStopSlot[$this->CurrentServerSlot->id()]) {
                    $this->CurrentServerSlot->stop();
                }

            } else if ($_POST['ACTION'] == "KILL_SERVER") {

                if ($this->CurrentServerSlot->online() === TRUE
                    && $CanKillSlot[$this->CurrentServerSlot->id()]) {
                    $this->CurrentServerSlot->stop(TRUE);
                }
            }
        }


        // initialize the html output
        $html  = "";



        // --------------------------------------------------------------------
        //                            Server Control
        // --------------------------------------------------------------------


        foreach (ServerSlot::listSlots() as $ss) {

            if ($ss->online()) {

                $html .= "<h1>Control Server &quot;" . $ss->name() . "&quot;</h1>";
                $html .= '<form action="" method="post">';
                $html .= '<input type="hidden" name="SERVERSLOT_ID" value="' . $ss->id() . '">';
                $html .= '<span style="color:#0d0; font-weight:bold; font-size: 1.5em;">online</span><br>';

                // stop slot
                if ($CanStopSlot[$ss->id()] && $ss->currentSession()->type() != 3 && count($ss->driversOnline()) == 0) {
                    $html .= '<button type="submit" name="ACTION" value="STOP_SERVER">' . _("Stop Server") . '</button>';

                // kill slot
                } else if ($CanKillSlot[$ss->id()]) {
                    $html .= '<button type="submit" name="ACTION" value="KILL_SERVER">' . _("Kill Server") . '</button>';
                }

                $html .= '</form>';

            } else {

                if ($CanStartSlot[$ss->id()]) {
                    $html .= "<h1>Control Server &quot;" . $ss->name() . "&quot;</h1>";
                    $html .= '<form action="" method="post">';
                    $html .= '<input type="hidden" name="SERVERSLOT_ID" value="' . $ss->id() . '">';

                    $html .= '<span style="color:#d00; font-weight:bold; font-size: 1.0em;">offline</span>';

                    // preset
                    $html .= "Server Preset";
                    $html .= '<select name="PRESET_ID">';
                    foreach (ServerPreset::listPresets($CanAccessRestricted) as $sp) {
                        if ($this->CurrentPreset === NULL) $this->CurrentPreset = $sp;
                        $selected = ($this->CurrentPreset->id() == $sp->id()) ? "selected" : "";
                        $html .= '<option value="' . $sp->id() . '"' . $selected . '>' . $sp->name() . '</option>';
                    }
                    $html .= '</select>';
                    $html .= '<br>';

                    # car class
                    $html .= "Car Class";
                    $html .= '<select name="CARCLASS_ID">';
                    foreach (CarClass::listClasses() as $cc) {
                        if ($this->CurrentCarClass === NULL) $this->CurrentCarClass = $cc;
                        $selected = ($this->CurrentCarClass->id() == $cc->id()) ? "selected" : "";
                        $html .= '<option value="' . $cc->id() . '"' . $selected . '>' . $cc->name() . '</option>';
                    }
                    $html .= '</select>';
                    $html .= '<br>';

                    # track
                    $html .= "Track";
                    $html .= '<select name="TRACK_ID">';
                    foreach (Track::listTracks() as $t) {
                        if ($this->CurrentTrack === NULL) $this->CurrentTrack = $t;
                        $selected = ($this->CurrentTrack->id() == $t->id()) ? "selected" : "";
                        $name_str = $t->name();
                        $name_str .= " (" . sprintf("%0.1f", $t->length()/1000) . "km";
                        $name_str .= ", " . $t->pitboxes() . "pits)";
                        $html .= '<option value="' . $t->id() . '"' . $selected . ">" . $name_str . "</option>";
                    }
                    $html .= '</select>';
                    $html .= '<br>';

                    # start
                    $html .= '<button type="submit" name="ACTION" value="START_SERVER">' . _("Start Server") . '</button>';

                    $html .= '</form>';
                }
            }

        }


        return $html;
    }

}

?>
