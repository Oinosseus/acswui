<?php

class champ_mange extends cContentPage {

    private $Championship = NULL;
    private $CanCreateNew = FALSE;
    private $CanDelete = FALSE;
    private $CanEdit = FALSE;

    public function __construct() {
        $this->MenuName   = _("Manage");
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["Championship_View"];
    }

    public function getHtml() {
        global $acswuiUser;

        $html = "";


        // --------------------------------------------------------------------
        //                            Check Permissions
        // --------------------------------------------------------------------

        if ($acswuiUser->hasPermission('Championship_Create')) $this->CanCreateNew = TRUE;
        if ($acswuiUser->hasPermission('Championship_Delete')) $this->CanDelete = TRUE;
        if ($acswuiUser->hasPermission('Championship_Edit')) $this->CanEdit = TRUE;



        // --------------------------------------------------------------------
        //                             Process Actions
        // --------------------------------------------------------------------

        // determine requested championship
        if (array_key_exists("CHMP_ID", $_REQUEST)) {
            $this->Championship = new Championship($_REQUEST['CHMP_ID']);
            $_SESSION['CHAMPIONCHIP'] = $this->Championship->id();
        } else if (array_key_exists('CHAMPIONCHIP', $_SESSION) && $_SESSION['CHAMPIONCHIP'] !== NULL) {
            $this->Championship = new Championship($_SESSION['CHAMPIONCHIP']);
        }

        // check for valid Championship
        if ($this->Championship !== NULL && !$this->Championship->isValid()) {
            $this->Championship = NULL;
            $_SESSION['CHAMPIONCHIP'] = NULL;
        }

        // delete championship
        if (array_key_exists("DELETE", $_REQUEST) && $this->CanDelete && $this->Championship !== NULL) {
            $this->Championship->delete();
            $this->Championship = NULL;
        }

        if (array_key_exists("ACTION", $_REQUEST)) {

            if ($_REQUEST['ACTION'] == "NEW" && $this->CanCreateNew) {
                $this->Championship = Championship::createNew();

            } else if ($_REQUEST['ACTION'] == "SAVE" && $this->CanEdit && $this->Championship !== NULL) {
                $this->Championship->setName($_REQUEST['CHMP_NAME']);

                // add/remove car classes
                $car_classes = array();
                foreach ($this->Championship->carClasses() as $cc) {
                    $cc_id = $cc->id();
                    if (array_key_exists("CARCLASS_ID_$cc_id", $_REQUEST))
                        $car_classes[] = new CarClass($cc_id);
                }
                if ($_REQUEST['ADD_CARCLASS_ID'] != "") {
                    $car_classes[] = new CarClass($_REQUEST['ADD_CARCLASS_ID']);
                }
                $this->Championship->setCarClasses($car_classes);

                // qualifying position points
                $position_points = array();
                for ($pos=0; TRUE; ++$pos) {
                    if (!array_key_exists("CHMP_QUALPOS_$pos", $_REQUEST)) break;
                    if ($_REQUEST["CHMP_QUALPOS_$pos"] == 0) break;
                    $position_points[] = $_REQUEST["CHMP_QUALPOS_$pos"];
                }
                $this->Championship->setQualifyPositionPoints($position_points);

                // race position points
                $position_points = array();
                for ($pos=0; TRUE; ++$pos) {
                    if (!array_key_exists("CHMP_RACEPOS_$pos", $_REQUEST)) break;
                    if ($_REQUEST["CHMP_RACEPOS_$pos"] == 0) break;
                    $position_points[] = $_REQUEST["CHMP_RACEPOS_$pos"];
                }
                $this->Championship->setRacePositionPoints($position_points);

                // race time points
                $position_points = array();
                for ($pos=0; TRUE; ++$pos) {
                    if (!array_key_exists("CHMP_RACETIME_$pos", $_REQUEST)) break;
                    if ($_REQUEST["CHMP_RACETIME_$pos"] == 0) break;
                    $position_points[] = $_REQUEST["CHMP_RACETIME_$pos"];
                }
                $this->Championship->setRaceTimePoints($position_points);

                // race lead lap points
                $position_points = array();
                for ($pos=0; TRUE; ++$pos) {
                    if (!array_key_exists("CHMP_RACELEADLAP_$pos", $_REQUEST)) break;
                    if ($_REQUEST["CHMP_RACELEADLAP_$pos"] == 0) break;
                    $position_points[] = $_REQUEST["CHMP_RACELEADLAP_$pos"];
                }
                $this->Championship->setRaceLeadLapPoints($position_points);

                // ballance ballast
                $position_points = array();
                for ($pos=0; TRUE; ++$pos) {
                    if (!array_key_exists("CHMP_BALLAST_$pos", $_REQUEST)) break;
                    if ($_REQUEST["CHMP_BALLAST_$pos"] == 0) break;
                    $position_points[] = $_REQUEST["CHMP_BALLAST_$pos"];
                }
                $this->Championship->setBallanceBallast($position_points);

                // ballance restrictor
                $position_points = array();
                for ($pos=0; TRUE; ++$pos) {
                    if (!array_key_exists("CHMP_RESTRICTOR_$pos", $_REQUEST)) break;
                    if ($_REQUEST["CHMP_RESTRICTOR_$pos"] == 0) break;
                    $position_points[] = $_REQUEST["CHMP_RESTRICTOR_$pos"];
                }
                $this->Championship->setBallanceRestrictor($position_points);

                // add/remove tracks
                $tracks = array();
                foreach ($this->Championship->tracks() as $t) {
                    $t_id = $t->id();
                    if (array_key_exists("TRACK_ID_$t_id", $_REQUEST))
                        $tracks[] = new Track($t_id);
                }
                if ($_REQUEST['ADD_TRACK_ID'] != "") {
                    $tracks[] = new Track($_REQUEST['ADD_TRACK_ID']);
                }
                $this->Championship->setTracks($tracks);
            }
        }


        // --------------------------------------------------------------------
        //                          Chmp Select
        // --------------------------------------------------------------------

        $html .= "<form method=\"post\">";

        $html .= "<select name=\"CHMP_ID\" onchange=\"this.form.submit()\">";
        foreach (Championship::list() as $chmp) {
            if ($this->Championship === NULL) $this->Championship = $chmp;
            $selected = ($chmp->id() == $this->Championship->id()) ? "selected" : "";
            $html .= "<option value=\"" . $chmp->id() . "\" $selected>" . $chmp->name() ."</option>";
        }
        $html .= "</select>";
        $html .= "<br>";

        if ($this->CanDelete && $this->Championship !== NULL)
            $html .= " <button type=\"submit\" name=\"DELETE\" value=\"" . $this->Championship->id() . "\">" . _("Delete This Championship") . "</button>";
        if ($this->CanCreateNew)
            $html .= " <button type=\"submit\" name=\"ACTION\" value=\"NEW\">" . _("Create New Championship") . "</button>";
        $html .= "</form>";



        // --------------------------------------------------------------------
        //                          General Setup
        // --------------------------------------------------------------------

        if ($this->Championship !== NULL && $this->CanEdit) {
            $html .= "<h1>" . _("General Setup") . "</h1>";

            $html .= "<form method=\"post\" id=\"general_setup\">";
            $html .= "<input type=\"hidden\" name=\"CHMP_ID\" value=\"" . $this->Championship->id() . "\">";
            $html .= "<input type=\"hidden\" name=\"ACTION\" value=\"SAVE\">";

            // name
            $html .= "<label>Name</label>";
            $html .= "<input type=\"text\" name=\"CHMP_NAME\" value=\"" . $this->Championship->name() . "\" /> ";

            // server preset
            $html .= "<label>Server Prest</label>";
            $html .= "<select name=\"PRESET_ID\">";
            foreach (ServerPreset::listPresets() as $prst) {
                $selected = ($this->Championship->serverPreset() !== NULL && $this->Championship->serverPreset()->id() == $prst->id()) ? "selected" : "";
                $html .= "<option value=\"" . $prst->id() . "\" $selected>" . $prst->name() ."</option>";
            }
            $html .= "</select>";

            // car classes
            $html .= "<label>Car Classes</label>";
            $html .= "<ul>";
            foreach ($this->Championship->carClasses() as $cc) {
                $html .= "<li>";
                $html .= "<input type=\"checkbox\" name=\"CARCLASS_ID_" . $cc->id() . "\" value=\"TRUE\" checked=\"yes\"> ";
                $html .= $cc->name();
                $html .= "</li>";
            }
            $html .= "<li><select name=\"ADD_CARCLASS_ID\" onchange=\"this.form.submit()\">";
            $html .= "<option value=\"\" selected> </option>";
            foreach (CarClass::listClasses() as $cc) {
                $html .= "<option value=\"" . $cc->id() . "\">" . $cc->name() . "</option>";
            }
            $html .= "</select></li>";
            $html .= "</ul>";

            // qualifying position points
            $html .= "<label>Qualifing Position Points</label>";
            $html .= "<ol>";
            $position = 0;
            foreach ($this->Championship->qualifyPositionPoints() as $p) {
                $html .= "<li><input name=\"CHMP_QUALPOS_$position\" type=\"number\" min=\"0\" max=\"1000\" step=\"1\" value=\"$p\" title=\"Set to zero to remove\"></li>";
                $position += 1;
            }
            $html .= "<li><input name=\"CHMP_QUALPOS_$position\" type=\"number\" min=\"0\" max=\"1000\" step=\"1\" value=\"0\"></li>";
            $html .= "</ol>";

            // race position points
            $html .= "<label>Race Position Points</label>";
            $html .= "<ol>";
            $position = 0;
            foreach ($this->Championship->racePositionPoints() as $p) {
                $html .= "<li><input name=\"CHMP_RACEPOS_$position\" type=\"number\" min=\"0\" max=\"1000\" step=\"1\" value=\"$p\" title=\"Set to zero to remove\"></li>";
                $position += 1;
            }
            $html .= "<li><input name=\"CHMP_RACEPOS_$position\" type=\"number\" min=\"0\" max=\"1000\" step=\"1\" value=\"0\"></li>";
            $html .= "</ol>";

            // race time points
            $html .= "<label>Race Time Points</label>";
            $html .= "<ol>";
            $position = 0;
            foreach ($this->Championship->raceTimePoints() as $p) {
                $html .= "<li><input name=\"CHMP_RACETIME_$position\" type=\"number\" min=\"0\" max=\"1000\" step=\"1\" value=\"$p\" title=\"Set to zero to remove\"></li>";
                $position += 1;
            }
            $html .= "<li><input name=\"CHMP_RACETIME_$position\" type=\"number\" min=\"0\" max=\"1000\" step=\"1\" value=\"0\"></li>";
            $html .= "</ol>";

            // race lead laps points
            $html .= "<label>Race Lead Lap Points</label>";
            $html .= "<ol>";
            $position = 0;
            foreach ($this->Championship->raceLeadLapPoints() as $p) {
                $html .= "<li><input name=\"CHMP_RACELEADLAP_$position\" type=\"number\" min=\"0\" max=\"1000\" step=\"1\" value=\"$p\" title=\"Set to zero to remove\"></li>";
                $position += 1;
            }
            $html .= "<li><input name=\"CHMP_RACELEADLAP_$position\" type=\"number\" min=\"0\" max=\"1000\" step=\"1\" value=\"0\"></li>";
            $html .= "</ol>";

            // ballance ballast
            $html .= "<label>Ballance Ballast</label>";
            $html .= "<ol>";
            $position = 0;
            foreach ($this->Championship->ballanceBallast() as $p) {
                $html .= "<li><input name=\"CHMP_BALLAST_$position\" type=\"number\" min=\"0\" max=\"1000\" step=\"1\" value=\"$p\" title=\"Set to zero to remove\"></li>";
                $position += 1;
            }
            $html .= "<li><input name=\"CHMP_BALLAST_$position\" type=\"number\" min=\"0\" max=\"1000\" step=\"1\" value=\"0\"></li>";
            $html .= "</ol>";

            // ballance restrcitor
            $html .= "<label>Ballance Restrictor</label>";
            $html .= "<ol>";
            $position = 0;
            foreach ($this->Championship->ballanceRestrictor() as $p) {
                $html .= "<li><input name=\"CHMP_RESTRICTOR_$position\" type=\"number\" min=\"0\" max=\"100\" step=\"1\" value=\"$p\" title=\"Set to zero to remove\"></li>";
                $position += 1;
            }
            $html .= "<li><input name=\"CHMP_RESTRICTOR_$position\" type=\"number\" min=\"0\" max=\"100\" step=\"1\" value=\"0\"></li>";
            $html .= "</ol>";

            // tracks
            $html .= "<label>Tracks</label>";
            $html .= "<ul>";
            foreach ($this->Championship->tracks() as $t) {
                $name = $t->name() . " (" . HumanValue::format($t->length(), "m") . ", " . $t->pitboxes() . "Pits)";
                $html .= "<li>";
                $html .= "<input type=\"checkbox\" name=\"TRACK_ID_" . $t->id() . "\" value=\"TRUE\" checked=\"yes\"> ";
                $html .= $name;
                $html .= "</li>";
            }
            $html .= "<li><select name=\"ADD_TRACK_ID\" onchange=\"this.form.submit()\">";
            $html .= "<option value=\"\" selected> </option>";
            foreach (Track::listTracks() as $t) {
                $name = $t->name() . " (" . HumanValue::format($t->length(), "m") . ", " . $t->pitboxes() . "Pits)";
                $html .= "<option value=\"" . $t->id() . "\">$name</option>";
            }
            $html .= "</select></li>";
            $html .= "</ul>";

            // save button
            $html .= " <button type=\"submit\">" . _("Save Championship") . "</button>";

            $html .= "</form>";
        }


        return $html;
    }
}

?>
