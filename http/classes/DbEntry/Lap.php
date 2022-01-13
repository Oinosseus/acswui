<?php

namespace DbEntry;

/**
 * Cached wrapper to databse Lap table element
 */
class Lap extends DbEntry {

    /**
     * @param $id Database table id
     * @param $session The according Session object (saves DB request if given)
     */
    protected function __construct($id, $session=NULL) {
        parent::__construct("Laps", $id);
    }


    //! @return The amount of ballast at this lap
    public function ballast() {
        return (int) $this->loadColumn("Ballast");
    }


    //! @return The CarSkin object used for this lap
    public function carSkin() {
        $id = $this->loadColumn("CarSkin");
        return CarSkin::fromId($id);
    }


    /**
     * Compares to Lap objects for better laptime.
     * This is intended for usort() of arrays with Lap objects
     * @param $l1 Lap object
     * @param $l2 Lap object
     * @return -1 if $l1 is quicker, +1 when $l2 is quicker, 0 if both are equal
     */
    public static function compareLaptime(Lap $l1, Lap $l2) {
        if ($l1->laptime() < $l2->laptime()) return -1;
        if ($l1->laptime() > $l2->laptime()) return 1;
        return 0;
    }


    //! @return The amount of cuts in this lap
    public function cuts() {
        return (int) $this->loadColumn("Cuts");
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) {
        return parent::getCachedObject("Laps", "Lap", $id);
    }


    //! @return The amount of grip at this lap
    public function grip() {
        return (float) $this->loadColumn("Grip");
    }


    //! @return The lap time in milliseconds
    public function laptime() {
        return (int) $this->loadColumn("Laptime");
    }


    //! @return The amount of restrictor at this lap
    public function restrictor() {
        return (int) $this->loadColumn("Restrictor");
    }


    //! @return The Session object of this lap
    public function session() {
        $id = $this->loadColumn("Session");
        return Session::fromId($id);
    }


    //! @return A DateTime object
    public function timestamp() {
        $dt = $this->loadColumn("Timestamp");
        return \Core\Database::timestamp2DateTime($dt);
    }


    //! @return The User object representing the driver of this lapt
    public function user() {
        $id = $this->loadColumn("User");
        return User::fromId($id);
    }
}

?>
