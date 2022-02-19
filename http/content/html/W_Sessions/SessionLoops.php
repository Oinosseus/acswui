<?php

namespace Content\Html;

class SessionLoops extends \core\HtmlContent {

    private $CanEdit = FALSE;


    public function __construct() {
        parent::__construct(_("Loops"),  "Session Loops");
        $this->requirePermission("Sessions_Loops_View");
    }


    public function getHtml() {
        $this->CanEdit = \Core\UserManager::permitted("Sessions_Loops_Edit");
        $html = "";

        // --------------------------------------------------------------------
        //                           Save Loop Items
        // --------------------------------------------------------------------

        if ($this->CanEdit && array_key_exists("Action", $_POST) && $_POST['Action'] == "Save") {

            // check for deletions
            foreach (\DbEntry\SessionLoop::listLoops() as $sl) {
                $slid = $sl->id();
                if (array_key_exists("SessionLoopDelete$slid", $_POST)) {
                    $sl->delete();
                }
            }

            // save items
            foreach (\DbEntry\SessionLoop::listLoops() as $sl) {
                $slid = $sl->id();

                // ena
                $sl->setEnabled(array_key_exists("SessionLoopEna$slid", $_POST));

                // name
                if (array_key_exists("SessionLoopName$slid", $_POST) && $_POST["SessionLoopName$slid"] !== "")
                    $sl->setName($_POST["SessionLoopName$slid"]);

                // slot
                if (array_key_exists("SessionLoopSlot$slid", $_POST) && $_POST["SessionLoopSlot$slid"] !== "")
                    $sl->setServerSlot(\Core\ServerSlot::fromId($_POST["SessionLoopSlot$slid"]));

                // preset
                if (array_key_exists("SessionLoopPreset$slid", $_POST) && $_POST["SessionLoopPreset$slid"] !== "")
                    $sl->setServerPreset(\DbEntry\ServerPreset::fromId($_POST["SessionLoopPreset$slid"]));

                // carclass
                if (array_key_exists("SessionLoopCarClass$slid", $_POST) && $_POST["SessionLoopCarClass$slid"] !== "")
                    $sl->setCarClass(\DbEntry\CarClass::fromId($_POST["SessionLoopCarClass$slid"]));

                // track
                if (array_key_exists("SessionLoopCarTrack$slid", $_POST) && $_POST["SessionLoopCarTrack$slid"] !== "")
                    $sl->setTrack(\DbEntry\Track::fromId($_POST["SessionLoopCarTrack$slid"]));
            }


            // add new items
            if (array_key_exists("NewSessionLoopName", $_POST) && strlen($_POST["NewSessionLoopName"]) > 0) {
                $sl = \DbEntry\SessionLoop::createNew(trim($_POST["NewSessionLoopName"]));
            }
        }


        // --------------------------------------------------------------------
        //                           Show Loop Items
        // --------------------------------------------------------------------

        $html .= $this->newHtmlForm("POST");

        $server_slots = \Core\ServerSlot::listSlots();
        $server_presets = \DbEntry\ServerPreset::listPresets();
        $car_classes = \DbEntry\CarClass::listClasses();
        $tracks = \DbEntry\Track::listTracks();

        $last_slot_id = -1;
        $session_loops = \DbEntry\SessionLoop::listLoops();
        for ($i=0; $i < count($session_loops); ++$i) {
            $sl = $session_loops[$i];
            $slid = $sl->id();

            // headline for each slot
            $current_slot_id = $sl->serverSlot();
            $server_slot_name = "";
            if ($sl->serverSlot() !== NULL) {
                $current_slot_id = $sl->serverSlot()->id();
                $server_slot_name = $sl->serverSlot()->name();
            }

            // begin table
            if ($i == 0) {
                $html .= "<h1>$server_slot_name</h1>";
                $html .= "<table>";
                $html .= $this->tableHeadRow();

            } else if ($current_slot_id !== $last_slot_id) {
                $html .= "</table>";
                $html .= "<h1>$server_slot_name</h1>";
                $html .= "<table>";
                $html .= $this->tableHeadRow();
            }

            // show loop item
            $html .= "<tr>";
            $checked = ($sl->enabled()) ? "checked=\"yes\"" : "";
            $disabled = ($this->CanEdit) ? "" : "disabled=\"yes\"";
            $html .= "<td><input type=\"checkbox\" name=\"SessionLoopEna$slid\" $checked $disabled></td>";
            $html .= "<td><input type=\"text\" name=\"SessionLoopName$slid\" value=\"" . $sl->name() . "\"></td>";
            $html .= $this->selectColumn("SessionLoopSlot$slid", $server_slots, $sl->serverSlot());
            $html .= $this->selectColumn("SessionLoopPreset$slid", $server_presets, $sl->serverPreset());
            $html .= $this->selectColumn("SessionLoopCarClass$slid", $car_classes, $sl->carClass());
            $html .= $this->selectColumn("SessionLoopCarTrack$slid", $tracks, $sl->track());
            $html .= "<td><small>" . \Core\UserManager::currentUser()->formatDateTime($sl->lastStart()) . "</small></td>";

            // delete item
            if ($this->CanEdit) {
                $html .= "<td>";
                $html .= $this->newHtmlTableRowDeleteCheckbox("SessionLoopDelete$slid");
                $html .= "</td>";
            }

            $html .= "</tr>";


            // end table
            if (($i + 1) == count($session_loops)) {
                $html .= "</table>";
            }
            $last_slot_id = $current_slot_id;
        }


        if ($this->CanEdit) {
            $html .= "<h1>" . _("New Loop Item") . "</h1>";
            $html .= _("New Loop Item Name") .": <input type=\"text\" name=\"NewSessionLoopName\" value=\"\"><br>";
            $html .= "<button type=\"submit\" name=\"Action\" value=\"Save\">" . _("Save Session Loops") . "</button>";
        }

        $html .= "</form>";

        return $html;
    }


    private function tableHeadRow() {
        $html = "";
        $html .= "<tr>";
        $html .= "<th><span id=\"" . _("Enable") . "\">" . _("Ena") . "</span></th>";
        $html .= "<th>" . _("Name") . "</th>";
        $html .= "<th>" . _("Slot") . "</th>";
        $html .= "<th>" . _("Preset") . "</th>";
        $html .= "<th>" . _("Car Class") . "</th>";
        $html .= "<th>" . _("Track") . "</th>";
        $html .= "<th>" . _("Last Start") . "</th>";
        $html .= "</tr>";
        return $html;
    }


    private function selectColumn($post_name, $object_list, $session_loop_item_object) {

        if ($this->CanEdit) {
            $html = "<td><select name=\"$post_name\">";
            $any_selected = FALSE;
            foreach ($object_list as $obj) {
                $selected = "";
                if ($session_loop_item_object !== NULL && $session_loop_item_object->id() == $obj->id()) {
                    $selected = "selected=\"yes\"";
                    $any_selected = TRUE;
                }
                $html .= "<option value=\"" . $obj->id() . "\" $selected>" . $obj->name() . "</option>";
            }
            if (!$any_selected) {
                $html .= "<option value=\"\" disabled selected=\"yes\"></option>";
            }
            $html .= "</select></td>";

        } else {
            $name = ($session_loop_item_object === NULL) ? "" : $session_loop_item_object->name();
            $html = "<td>$name</td>";
        }

        return $html;
    }
}
