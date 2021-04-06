<?php


class schedule extends cContentPage {

    private $CanEdit = FALSE;

    public function __construct() {
        $this->MenuName   = _("Schedule");
        $this->PageTitle  = "Session Schedule";
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["Session", "Session_Schedule_View"];
    }

    public function getHtml() {

        // access global data
        global $acswuiConfig;
        global $acswuiLog;
        global $acswuiDatabase;
        global $acswuiUser;

        // check permissions
        if ($acswuiUser->hasPermission("Session_Schedule_Edit")) $this->CanEdit = TRUE;

        // process data
        $this->processData();

        // html content
        $html = "";
        $html .= "<form action=\"\" method=\"post\">";
        $html .= "<input type=\"hidden\" name=\"Action\" value=\"Save\">";
        if ($this->CanEdit) $html .= "<button type=\"submit\">" . _("Save Session Schedule") . "</button>";
        if ($this->CanEdit) $html .= $this->newItemForm();
        $html .= $this->existingItemForm();
        if ($this->CanEdit) $html .= "<button type=\"submit\">" . _("Save Session Schedule") . "</button>";
        $html .= "</form>";

        return $html;
    }



    private function tableHeader() {
        $html = "";
        $html .= "<tr>";
        $html .= "<th>" . _("Id") . "</th>";
        $html .= "<th>" . _("Date") . "</th>";
        $html .= "<th>" . _("Time") . "</th>";
        $html .= "<th>" . _("Name") . "</th>";
        $html .= "<th><span title=\"" . _("Enable Seat Occupations") . "\">Seat</span></th>";
        $html .= "<th>" . _("Preset") . "</th>";
        $html .= "<th>" . _("Car Class") . "</th>";
        $html .= "<th>" . _("Track") . "</th>";
        $html .= "<th>" . _("Slot") . "</th>";
        $html .= "</tr>";
        return $html;
    }



    private function existingItemForm() {
        $html = "";

        $disabled = ($this->CanEdit) ? "" : "disabled";

        $html .= "<h1>" . _("Scheduled Sessions") . "</h1>";

        $html .= "<table>";
        $html .= $this->tableHeader();

        foreach (SessionSchedule::listSchedules(new DateInterval("P7D")) as $sq) {
            $sq_id = $sq->id();

            $html .= "<tr>";
            $html .= "<td>$sq_id</td>";

            // start
            $start = $sq->start();
            $html .= "<td><input type=\"date\" name=\"Date$sq_id\" value=\"" . $start->format("Y-m-d") . "\"></td>";
            $html .= "<td><input type=\"time\" name=\"Time$sq_id\" value=\"" . $start->format("H:i") . "\"></td>";

            // name
            $html .= "<td><input type=\"text\" name=\"Name$sq_id\" value=\"" . $sq->name() . "\" $disabled></td>";

            // seat
            $checked = ($sq->seatOccupations() == TRUE) ? "checked" : "";
            $html .= "<td><input type=\"checkbox\" name=\"SeatOccupations$sq_id\" value=\"TRUE\" $checked $disabled></td>";

            // preset
            $html .= "<td>";
            $html .= "<select name=\"Preset$sq_id\" $disabled>";
            foreach (ServerPreset::listPresets(TRUE) as $sp) {
                $selected = ($sp->id() == $sq->preset()->id()) ? "selected" : "";
                $html .= "<option value=\"" . $sp->id() . "\" $selected>" . $sp->name() . "</option>";
            }
            $html .= "</select>";
            $html .= "</td>";

            // carclass
            $html .= "<td>";
            $html .= "<select name=\"CarClass$sq_id\" $disabled>";
            foreach (CarClass::listClasses() as $cc) {
                $selected = ($cc->id() == $sq->carClass()->id()) ? "selected" : "";
                $html .= "<option value=\"" . $cc->id() . "\" $selected>" . $cc->name() . "</option>";
            }
            $html .= "</select>";
            $html .= "</td>";

            // track
            $html .= "<td>";
            $html .= "<select name=\"Track$sq_id\" $disabled>";
            foreach (Track::listTracks() as $t) {
                $selected = ($t->id() == $sq->track()->id()) ? "selected" : "";
                $html .= "<option value=\"" . $t->id() . "\" $selected>" . $t->name() . "</option>";
            }
            $html .= "</select>";
            $html .= "</td>";

            // slot
            $html .= "<td>";
            $html .= "<select name=\"Slot$sq_id\" $disabled>";
            foreach (ServerSlot::listSlots() as $s) {
                $selected = ($s->id() == $sq->slot()->id()) ? "selected" : "";
                $html .= "<option value=\"" . $s->id() . "\" $selected>" . $s->name() . "</option>";
            }
            $html .= "</select>";
            $html .= "</td>";

            // delete
            if ($this->CanEdit) {
                $html .= "<td>";
                $html .= "<button type=\"submit\" name=\"Delete\" value=\"$sq_id\">" . _("Delete") . "</button>";
                $html .= "</td>";
            }

            $html .= "</tr>";
        }

        $html .= "</table>";

        return $html;
    }



    private function newItemForm() {
        $html = "";

        $html .= "<h1>" . _("Create New Schedule Item") . "</h1>";

        $html .= "<table>";
        $html .= $this->tableHeader();

        $html .= "<tr>";

        // id
        $html .= "<td></td>";

        // start
        $now = new DateTimeImmutable();
        $html .= "<td><input type=\"date\" name=\"NewItemDate\" value=\"" . $now->format("Y-m-d") . "\"></td>";
        $html .= "<td><input type=\"time\" name=\"NewItemTime\" value=\"" . $now->format("H:i") . "\"></td>";

        // name, seat
        $html .= "<td><input type=\"text\" name=\"NewItemName\"></td>";
        $html .= "<td><input type=\"checkbox\" name=\"NewItemSeatOccupations\" value=\"TRUE\"></td>";

        // preset
        $html .= "<td>";
        $html .= "<select name=\"NewItemPreset\">";
        $html .= "<option value=\"\" selected> </option>";
        foreach (ServerPreset::listPresets(TRUE) as $sp) {
            $html .= "<option value=\"" . $sp->id() . "\">" . $sp->name() . "</option>";
        }
        $html .= "</select>";
        $html .= "</td>";

        // carclass
        $html .= "<td>";
        $html .= "<select name=\"NewItemCarClass\">";
        $html .= "<option value=\"\" selected> </option>";
        foreach (CarClass::listClasses() as $cc) {
            $html .= "<option value=\"" . $cc->id() . "\">" . $cc->name() . "</option>";
        }
        $html .= "</select>";
        $html .= "</td>";

        // track
        $html .= "<td>";
        $html .= "<select name=\"NewItemTrack\">";
        $html .= "<option value=\"\" selected> </option>";
        foreach (Track::listTracks() as $t) {
            $html .= "<option value=\"" . $t->id() . "\">" . $t->name() . "</option>";
        }
        $html .= "</select>";
        $html .= "</td>";

        // slot
        $html .= "<td>";
        $html .= "<select name=\"NewItemSlot\">";
        $html .= "<option value=\"\" selected> </option>";
        foreach (ServerSlot::listSlots() as $sslot) {
            $html .= "<option value=\"" . $sslot->id() . "\">" . $sslot->name() . "</option>";
        }
        $html .= "</select>";
        $html .= "</td>";

        $html .= "</tr>";

        $html .= "</table>";

        return $html;
    }


    private function processData() {
        if ($this->CanEdit !== TRUE) return;

        if (isset($_POST['Action']) && $_POST['Action'] == "Save") {

            // change items
            foreach (SessionSchedule::listSchedules(new DateInterval("P7D")) as $sq) {
                $sq_id = $sq->id();

                // start
                if (isset($_POST["Date$sq_id"]) && isset($_POST["Time$sq_id"])) {
                    $new_value = $_POST["Date$sq_id"] . " " . $_POST["Time$sq_id"] . ":00";
                    $new_value = new DateTime($new_value);
                    $sq->setStart($new_value);
                }

                // seat
                $post_id = "SeatOccupations$sq_id";
                $new_value = (isset($_POST[$post_id]) && $_POST[$post_id] == "TRUE") ? TRUE : FALSE;
                $sq->setSeatOccupations($new_value);

                // name
                $post_id = "Name$sq_id";
                if (isset($_POST[$post_id])) {
                    $new_value = $_POST[$post_id];
                    $sq->setName($new_value);
                }

                // slot
                $post_id = "Slot$sq_id";
                if (isset($_POST[$post_id])) {
                    $id = $_POST[$post_id];
                    $obj = new ServerSLot($id);
                    $sq->setSlot($obj);
                }

                // preset
                $post_id = "Preset$sq_id";
                if (isset($_POST[$post_id])) {
                    $id = $_POST[$post_id];
                    $obj = new ServerPreset($id);
                    $sq->setPreset($obj);
                }

                // carclass
                $post_id = "CarClass$sq_id";
                if (isset($_POST[$post_id])) {
                    $id = $_POST[$post_id];
                    $obj = new CarClass($id);
                    $sq->setCarClass($obj);
                }

                // track
                $post_id = "Track$sq_id";
                if (isset($_POST[$post_id])) {
                    $id = $_POST[$post_id];
                    $obj = new Track($id);
                    $sq->setTrack($obj);
                }
            }

            // create new
            if (isset($_POST['NewItemSlot']) && $_POST['NewItemSlot'] != "") {

                // get vars
                $start = new DateTime($_POST['NewItemDate'] . " " . $_POST['NewItemTime'] . ":00");
                $name = $_POST['NewItemName'];
                $seat = (isset($_POST['NewItemSeatOccupations']) && $_POST['NewItemSeatOccupations'] == "TRUE") ? TRUE : FALSE;
                $slot = new ServerSlot($_POST['NewItemSlot']);
                $prst = $_POST['NewItemPreset'];
                $cc = $_POST['NewItemCarClass'];
                $t = $_POST['NewItemTrack'];

                if ($prst != "" && $cc != "" && $t != "") {
                    $sq = SessionSchedule::createNew();
                    $sq->setName($name);
                    $sq->setStart($start);
                    $sq->setSeatOccupations($seat);
                    $sq->setSlot($slot);
                    $sq->setPreset(new ServerPreset($prst));
                    $sq->setCarClass(new CarClass($cc));
                    $sq->setTrack(new Track($t));
                }
            }
        }


        if (isset($_POST['Delete'])) {
            $sq = new SessionSchedule($_POST['Delete']);
            $sq->delete();
        }

    }

}

?>
