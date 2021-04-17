<?php

class SessionSchedule {

    private $Id = NULL;
    private $Name = NULL;
    private $Start = NULL;
    private $SeatOccupations = NULL;
    private $Slot = NULL;
    private $Preset = NULL;
    private $CarClass = NULL;
    private $Track = NULL;

    public function __construct(int $id) {
        $this->Id = $id;
    }


    //! @return CarClass object for this queue item
    public function carClass() {
        if ($this->CarClass === NULL) $this->updateFromDb();
        return $this->CarClass;
    }


    //! @return A new created ServerSchedule object
    public static function createNew() {
        global $acswuiDatabase;

        $id = $acswuiDatabase->insert_row("SessionSchedule", []);
        return new SessionSchedule($id);
    }


    /**
     * Compare two SessionSchedule objects according to their Id.
     * This can be used with array sort functions: usort($session_queue_list, SessionSchedule::compareId)
     * @param $sq1 A SessionSchedule object
     * @param $sq2 A SessionSchedule object
     * @return 1 if $sq1 has greater Id, 0 if Ids are equal, -1 if $sq2 has greater Id
     */
    public static function compareId($sq1, $sq2) {
        if ($sq1->id() > $sq2->id()) return 1;
        else if ($sq1->id() < $sq2->id()) return -1;
        else return 0;
    }


    /**
     * Deletes the SessionSchedule object.
     * After this call the SessionSchedule object is invalid and must not be used anymore!
     */
    public function delete() {
        global $acswuiDatabase;
        $acswuiDatabase->delete_row("SessionSchedule", $this->Id);
        $this->Id = NULL;
    }


    //! @return The Id of the queue item
    public function id() {
        return $this->Id;
    }


    /**
     * List available SessionSchedule objects
     * @param $show_passed_interval A DateInterval in the past from now (zero by default)
     * @return An array of SessionSchedule objects
     */
    public static function listSchedules(DateInterval $show_passed_interval = NULL) {
        global $acswuiDatabase;
        $ret = array();

        if ($show_passed_interval === NULL) $show_passed_interval = new DateInterval("P0D");

        // create query
        $query = "SELECT Id FROM SessionSchedule";
        $date_threshold = (new DateTimeImmutable())->sub($show_passed_interval);
        $query .= " WHERE Start >= '" . $date_threshold->format("Y-m-d H:i:s") . "'";
        $query .= " ORDER BY Start ASC";

        // execute query
        $res = $acswuiDatabase->fetch_raw_select($query);
        foreach ($res as $row) {
            $ret[] = new SessionSchedule($row['Id']);
        }

        return $ret;
    }


    //! @return Name of the queue item
    public function name() {
        if ($this->Name === NULL) $this->updateFromDb();
        return $this->Name;
    }


    /**
     * This returns the next SessionSchedule object that shall be started on a certain server slot.
     * The state is saved internally.
     * With every call the next available item is returned.
     * When all items are returned, this starts with the first item.
     * When no queue item is available, NULL is returned.
     *
     * @param $sslot The server slot for which the next queue item is requested
     * @return The next SessionSchedule item or NULL
     */
    public static function next(ServerSlot $sslot) {
        global $acswuiConfig;

        $sq_next = NULL;

        // get last state
        $json_path = $acswuiConfig->AbsPathData. "/htcache/session_queue.json";
        @ $json_string = file_get_contents($json_path);
        if ($json_string === FALSE) {
            $json_data = array();
        } else {
            $json_data = json_decode($json_string, TRUE);
        }

        // get last Id of this server slot
        if (!array_key_exists($sslot->id(), $json_data))
            $json_data[$sslot->id()] = 0;
        $last_id = $json_data[$sslot->id()];

        // find next enabled queue item
        $queue_list = SessionSchedule::listQueues($sslot);
        usort($queue_list, "SessionSchedule::compareId");
        foreach ($queue_list as $sq) {
            if ($sq->id() <= $last_id) continue;
            if ($sq->enabled()) {
                $last_id = $sq->id();
                $sq_next = $sq;
                break;
            }
        }

        // scan a second time when no item was found
        if ($sq_next === NULL && $last_id > 0) {
            $last_id = 0;
            foreach ($queue_list as $sq) {
                if ($sq->id() <= $last_id) continue;
                if ($sq->enabled()) {
                    $last_id = $sq->id();
                    $sq_next = $sq;
                    break;
                }
            }
        }

        // save current state
        $f = fopen($json_path, 'w');
        $json_data[$sslot->id()] = $last_id;
        fwrite($f, json_encode($json_data));
        fclose($f);

        // done
        return $sq_next;
    }


    //! @return ServerPreset object for this queue item
    public function preset() {
        if ($this->Preset === NULL) $this->updateFromDb();
        return $this->Preset;
    }


    //! @return TRUE if this item allows seat occupations
    public function seatOccupations() {
        if ($this->SeatOccupations === NULL) $this->updateFromDb();
        return $this->SeatOccupations;
    }


    //! @param $car_class The required CarClass object for this item
    public function setCarClass(CarClass $car_class) {
        global $acswuiDatabase;

        $cols = array();
        $cols['CarClass'] = $car_class->id();
        $acswuiDatabase->update_row("SessionSchedule", $this->Id, $cols);

        $this->CarClass = $car_class;
    }


    //! @param $name Set new name for the queue item
    public function setName(string $name) {
        global $acswuiDatabase;

        $cols = array();
        $cols['Name'] = $name;
        $acswuiDatabase->update_row("SessionSchedule", $this->Id, $cols);

        $this->Name = $name;
    }


    //! @param $preset The required ServerPreset object for this item
    public function setPreset(ServerPreset $preset) {
        global $acswuiDatabase;

        $cols = array();
        $cols['Preset'] = $preset->id();
        $acswuiDatabase->update_row("SessionSchedule", $this->Id, $cols);

        $this->Preset = $preset;
    }


    //! @param $occupation Set if seat occupations shall be enabled
    public function setSeatOccupations(bool $occupation) {
        global $acswuiDatabase;

        $cols = array();
        $cols['SeatOccupations'] = ($occupation) ? 1 : 0;
        $acswuiDatabase->update_row("SessionSchedule", $this->Id, $cols);

        $this->SeatOccupations = $occupation;
    }


    //! @param $slot The required ServerSlot object for this item
    public function setSlot(ServerSlot $slot) {
        global $acswuiDatabase;

        $cols = array();
        $cols['Slot'] = $slot->id();
        $acswuiDatabase->update_row("SessionSchedule", $this->Id, $cols);

        $this->Slot = $slot;
    }


    //! @param $start The time when the session shall be startet
    public function setStart(DateTime $start) {
        global $acswuiDatabase;

        $cols = array();
        $cols['Start'] = $start->format("Y-m-d H:i:s");
        $acswuiDatabase->update_row("SessionSchedule", $this->Id, $cols);

        $this->Sart = $start;
    }


    //! @param $track The required Track object for this item
    public function setTrack(Track $track) {
        global $acswuiDatabase;

        $cols = array();
        $cols['Track'] = $track->id();
        $acswuiDatabase->update_row("SessionSchedule", $this->Id, $cols);

        $this->Track = $track;
    }


    //! @return ServerSlot object for this queue item
    public function slot() {
        if ($this->Slot === NULL) $this->updateFromDb();
        return $this->Slot;
    }


    //! @return DateTime object when session shall start
    public function start() {
        if ($this->Start === NULL) $this->updateFromDb();
        return $this->Start;
    }


    //! @return Track object for this queue item
    public function track() {
        if ($this->Track === NULL) $this->updateFromDb();
        return $this->Track;
    }


    private function updateFromDb() {
        global $acswuiDatabase;
        global $acswuiLog;

        // request from db
        $columns = array();
        $columns[] = 'Name';
        $columns[] = 'Start';
        $columns[] = 'SeatOccupations';
        $columns[] = 'Slot';
        $columns[] = 'Preset';
        $columns[] = 'CarClass';
        $columns[] = 'Track';
        $res = $acswuiDatabase->fetch_2d_array("SessionSchedule", $columns, ['Id'=>$this->Id]);
        if (count($res) !== 1) {
            $acswuiLog->logError("Cannot find SessionSchedule.Id=" . $this->Id);
            return;
        }

        // read values
        $this->Name = $res[0]['Name'];
        $this->Start = new DateTime($res[0]['Start']);
        $this->SeatOccupations = ($res[0]['SeatOccupations'] == 0) ? FALSE : TRUE;
        $this->Slot = new ServerSlot($res[0]['Slot']);
        $this->Preset = new ServerPreset($res[0]['Preset']);
        $this->CarClass = new CarClass($res[0]['CarClass']);
        $this->Track = new Track($res[0]['Track']);
    }
}

?>