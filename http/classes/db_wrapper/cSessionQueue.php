<?php

class SessionQueue {

    private $Id = NULL;
    private $Name = NULL;
    private $Enabled = NULL;
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


    //! @return A new created ServerQueue object
    public static function createNew() {
        global $acswuiDatabase;

        $id = $acswuiDatabase->insert_row("SessionQueue", []);
        return new SessionQueue($id);
    }


    /**
     * Compare two SessionQueue objects according to their Id.
     * This can be used with array sort functions: usort($session_queue_list, SessionQueue::compareId)
     * @param $sq1 A SessionQueue object
     * @param $sq2 A SessionQueue object
     * @return 1 if $sq1 has greater Id, 0 if Ids are equal, -1 if $sq2 has greater Id
     */
    public static function compareId($sq1, $sq2) {
        if ($sq1->id() > $sq2->id()) return 1;
        else if ($sq1->id() < $sq2->id()) return -1;
        else return 0;
    }


    //! @return TRUE if this item is enabled
    public function enabled() {
        if ($this->Enabled === NULL) $this->updateFromDb();
        return $this->Enabled;
    }


    //! @return The Id of the queue item
    public function id() {
        return $this->Id;
    }


    /**
     * List available ServerQueue objects
     * @param $sslot When given, only queues for this slot are listed
     * @return An array of ServerQueue objects
     */
    public static function listQueues(ServerSlot $sslot = NULL) {
        global $acswuiDatabase;
        $ret = array();

        // create query
        $query = "SELECT Id FROM SessionQueue";
        if ($sslot !== NULL) {
            $query .= " WHERE Slot = " . $sslot->id();
        }
        $query .= " ORDER BY Id ASC";

        // execute query
        $res = $acswuiDatabase->fetch_raw_select($query);
        foreach ($res as $row) {
            $ret[] = new SessionQueue($row['Id']);
        }

        return $ret;
    }


    //! @return Name of the queue item
    public function name() {
        if ($this->Name === NULL) $this->updateFromDb();
        return $this->Name;
    }


    /**
     * This returns the next SessionQueue object that shall be started on a certain server slot.
     * The state is saved internally.
     * With every call the next available item is returned.
     * When all items are returned, this starts with the first item.
     * When no queue item is available, NULL is returned.
     *
     * @param $sslot The server slot for which the next queue item is requested
     * @return The next SessionQueue item or NULL
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
        $queue_list = SessionQueue::listQueues($sslot);
        usort($queue_list, "SessionQueue::compareId");
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
        $acswuiDatabase->update_row("SessionQueue", $this->Id, $cols);

        $this->CarClass = $car_class;
    }


    //! @param $enabled Set if the queue item shall be enabled
    public function setEnabled(bool $enabled) {
        global $acswuiDatabase;

        $cols = array();
        $cols['Enabled'] = ($enabled == TRUE) ? 1 : 0;
        $acswuiDatabase->update_row("SessionQueue", $this->Id, $cols);

        $this->Enabled = $enabled;
    }


    //! @param $name Set new name for the queue item
    public function setName(string $name) {
        global $acswuiDatabase;

        $cols = array();
        $cols['Name'] = $name;
        $acswuiDatabase->update_row("SessionQueue", $this->Id, $cols);

        $this->Name = $name;
    }


    //! @param $preset The required ServerPreset object for this item
    public function setPreset(ServerPreset $preset) {
        global $acswuiDatabase;

        $cols = array();
        $cols['Preset'] = $preset->id();
        $acswuiDatabase->update_row("SessionQueue", $this->Id, $cols);

        $this->Preset = $preset;
    }


    //! @param $occupation Set if seat occupations shall be enabled
    public function setSeatOccupations(bool $occupation) {
        global $acswuiDatabase;

        $cols = array();
        $cols['SeatOccupations'] = ($occupation) ? 1 : 0;
        $acswuiDatabase->update_row("SessionQueue", $this->Id, $cols);

        $this->SeatOccupations = $occupation;
    }


    //! @param $slot The required ServerSlot object for this item
    public function setSlot(ServerSlot $slot) {
        global $acswuiDatabase;

        $cols = array();
        $cols['Slot'] = $slot->id();
        $acswuiDatabase->update_row("SessionQueue", $this->Id, $cols);

        $this->Slot = $slot;
    }


    //! @param $track The required Track object for this item
    public function setTrack(Track $track) {
        global $acswuiDatabase;

        $cols = array();
        $cols['Track'] = $track->id();
        $acswuiDatabase->update_row("SessionQueue", $this->Id, $cols);

        $this->Track = $track;
    }


    //! @return ServerSlot object for this queue item
    public function slot() {
        if ($this->Slot === NULL) $this->updateFromDb();
        return $this->Slot;
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
        $columns[] = 'Enabled';
        $columns[] = 'SeatOccupations';
        $columns[] = 'Slot';
        $columns[] = 'Preset';
        $columns[] = 'CarClass';
        $columns[] = 'Track';
        $res = $acswuiDatabase->fetch_2d_array("SessionQueue", $columns, ['Id'=>$this->Id]);
        if (count($res) !== 1) {
            $acswuiLog->logError("Cannot find SessionQueue.Id=" . $this->Id);
            return;
        }

        // read values
        $this->Name = $res[0]['Name'];
        $this->Enabled = ($res[0]['Enabled'] == 0) ? FALSE : TRUE;
        $this->SeatOccupations = ($res[0]['SeatOccupations'] == 0) ? FALSE : TRUE;
        $this->Slot = new ServerSlot($res[0]['Slot']);
        $this->Preset = new ServerPreset($res[0]['Preset']);
        $this->CarClass = new CarClass($res[0]['CarClass']);
        $this->Track = new Track($res[0]['Track']);
    }
}

?>
