<?php

namespace DbEntry;

//! Collsions
abstract class Collision extends DbEntry {

    protected function __construct(string $table, int $id) {
        parent::__construct($table, $id);
    }


    //! @return The CarSkin object used for this lap
    public function carSkin() {
        $id = $this->loadColumn("CarSkin");
        return CarSkin::fromId($id);
    }


    /**
     * Compares to objects by their timestamp
     * This is intended for usort() of arrays with Lap objects
     * @param $c1 Collision object
     * @param $c2 Collision object
     * @return -1 if $c1 is earlier, +1 when $c2 is earlier, 0 if both are equal
     */
    public static function compareTimestamp(Collision $c1, Collision $c2) {
        if ($c1->timestamp() < $c2->timestamp()) return -1;
        if ($c1->timestamp() > $c2->timestamp()) return 1;
        return 0;
    }


    //! @return The according Session object
    public function session() {
        $id = $this->loadColumn("Session");
        return Session::fromId($id);
    }


    //! @return The speed at the collision [km/h]
    public function speed() {
        return (float) $this->loadColumn("Speed");
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
