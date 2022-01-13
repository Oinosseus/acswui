<?php

namespace DbEntry;

//! Collsions with Environment
class CollisionCar extends Collision {

    protected function __construct($id) {
        parent::__construct("CollisionCar", $id);
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) {
        return parent::getCachedObject("CollisionCar", "CollisionCar", $id);
    }


    //! @return The CarSkin object of the victim
    public function otherCarSkin() {
        return CarSKin::fromId($this->loadColumn("OtherCarSkin"));
    }


    //! @return The User object of the victim
    public function otherUser() {
        return User::fromId($this->loadColumn("OtherUser"));
    }
}
