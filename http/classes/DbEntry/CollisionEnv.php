<?php

namespace DbEntry;

//! Collsions with Environment
class CollisionEnv extends Collision {

    protected function __construct($id) {
        parent::__construct("CollisionEnv", $id);
    }

    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) {
        return parent::getCachedObject("CollisionEnv", "CollisionEnv", $id);
    }
}
