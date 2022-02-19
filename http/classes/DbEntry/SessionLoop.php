<?php

namespace DbEntry;

/**
 * Cached wrapper to car databse CarBrands table element
 */
class SessionLoop extends DbEntry {


    /**
     * Construct a new object
     * @param $id Database table id
     */
    protected function __construct(?int $id) {
        parent::__construct("SessionLoops", $id);
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) {
        return parent::getCachedObject("SessionLoops", "SessionLoop", $id);
    }


    //! @return The assigned carclass object (or NULL if invalid)
    public function carClass() {
        $id = (int) $this->loadColumn("CarClass");
        if ($id < 1) return NULL;
        return CarClass::fromId($id);
    }


    /**
     * Create new SessionLoop item
     * @param $name An arbitrary name for the new item
     * @return The newly created SessionLoop item
     */
    public static function createNew(string $name) {
        $sl = new SessionLoop(NULL);
        $sl->storeColumns(["Name"=>$name]);
        $id = $sl->id();
        return SessionLoop::fromId($id);
    }


    //! Will delete this item from the database
    public function delete() {
        $this->deleteFromDb();
    }


    //! @return TRUE if enabled, else FALSE
    public function enabled() {
        return ($this->loadColumn("Enabled") == 0) ? FALSE : TRUE;
    }


    //! @return DateTime from last start of this item
    public function lastStart() {
        $t = $this->loadColumn("LastStart");
        return \Core\Database::timestamp2DateTime($t);
    }


    //! @return An array of SessionLoop objects (ordered by ServerSLot)
    public static function listLoops() {
        $list = array();
        $query = "SELECT Id from SessionLoops ORDER BY Slot, Id ASC";
        foreach (\Core\Database::fetchRaw($query) as $row)  {
            $list[] = SessionLoop::fromId($row['Id']);
        }
        return $list;
    }


    //! @return The name of the loop item
    public function name() {
        return $this->loadColumn("Name");
    }


    //! @param $car_class The CarClass which shall be used for this loop item
    public function setCarClass(CarClass $car_class) {
        $this->storeColumns(["CarClass"=>$car_class->id()]);
    }


    //! @param $enabled Defines if the slot shall be enabled or disabled
    public function setEnabled(bool $enabled) {
        $val = ($enabled) ? 1 : 0;
        $this->storeColumns(["Enabled"=>$val]);
    }


    //! @param $name A arbitrary new name for the loop item
    public function setName(string $name) {
        $this->storeColumns(['Name'=>$name]);
    }


    //! @return The assigned ServerPreset object (can be NULL if invalid)
    public function serverPreset() {
        $pid = (int) $this->loadColumn("Preset");
        if ($pid < 1) return NULL;
        return ServerPreset::fromId($pid);
    }


    public function setLastStart(\DateTime $lasst_start) {
        $t = \Core\Database::dateTime2timestamp($lasst_start);
        $this->storeColumns(["LastStart"=>$t]);
    }


    //! @param $server_preset The ServerPreset which this loop item shall use
    public function setServerPreset(ServerPreset $server_preset) {
        $this->storeColumns(["Preset"=>$server_preset->id()]);
    }


    //! @param $server_slot The ServerSlot on which this loop item shall run on
    public function setServerSlot(\Core\ServerSlot $server_slot) {
        $this->storeColumns(["Slot"=>$server_slot->id()]);
    }


    //! @param $track The Track object that shall be used for this loop item
    public function setTrack(Track $track) {
        $this->storeColumns(["Track"=>$track->id()]);
    }


    //! @return The assigned ServerSlot object (can be NULL if invalid)
    public function serverSlot() {
        $slot_id = (int) $this->loadColumn("Slot");
        if ($slot_id < 1) return NULL;
        if ($slot_id > \Core\Config::ServerSlotAmount) return NULL;
        return \Core\ServerSlot::fromId($slot_id);
    }


    //! @return The assigned track object (or NULL if invalid)
    public function track() {
        $id = (int) $this->loadColumn("Track");
        if ($id < 1) return NULL;
        return Track::fromId($id);
    }
}
