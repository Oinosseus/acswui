<?php

namespace DbEntry;

/**
 * Cached wrapper to car databse Tracks table element
 */
class Group extends DbEntry {

    private static $ReservedGroups = NULL;
    private static $GroupsList = NULL;
    private Static $AvailablePermissions = NULL;
    private $GrantedPermissions = NULL;


    /**
     * @param $id Database table id
     */
    protected function __construct($id) {
        parent::__construct("Groups", $id);
    }


    //! Delete this group from database
    public function delete() {

        if (in_array($this->name(), Group::reservedGroupNames())) {
            \Core\Log::error("Deny deleting group '" . $this->name() . "'.");
            return;
        }

        $this->deleteFromDb();
        Group::$GroupsList = NULL;
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) {
        return parent::getCachedObject("Groups", "Group", $id);
    }


    //! @return TRUE if the requested permission is set for the group, in any other case FALSE
    public function grants(string $permission) {
        if ($this->GrantedPermissions === NULL) {
            $this->GrantedPermissions = array();
            foreach (Group::listPermissions() as $p) {
                if ($this->loadColumn($p) == 1) $this->GrantedPermissions[] = $p;
            }
        }

        return in_array($permission, $this->GrantedPermissions);
    }


    //! @return A list of all available groups (ordered by name)
    public static function listGroups() {

        if (Group::$GroupsList === NULL) {
            Group::$GroupsList = array();
            foreach (\Core\Database::fetch("Groups", ['Id'], [], 'Name') as $g) {
                Group::$GroupsList[] = Group::fromId($g['Id']);
            }
        }

        return Group::$GroupsList;
    }


    //! @return A string array of all available permissions that exist
    public static function listPermissions() {
        if (Group::$AvailablePermissions === NULL) {
            Group::$AvailablePermissions = array();
            foreach (\Core\Database::columns("Groups") as $column) {
                if ($column != "Id" && $column != "Name") {
                    Group::$AvailablePermissions[] = $column;
                }
            }
            natcasesort(Group::$AvailablePermissions);
        }

        return Group::$AvailablePermissions;
    }


    //! @return The name of the group
    public function name() {
        return $this->loadColumn("Name");
    }


    //! @return A new Group object (must be save by setting name or permission)
    public static function new() {
        Group::$GroupsList = NULL;
        return new Group(NULL);
    }


    /**
     * Reserved group names cannot be changed.
     * But permissions can still be granted or denied.
     * @return A list of group names that are reserved
     */
    public static function reservedGroupNames() {
        if (Group::$ReservedGroups === NULL) {
            Group::$ReservedGroups = array();
            Group::$ReservedGroups[] = \Core\Config::GuestGroup;
            Group::$ReservedGroups[] = \Core\Config::DriverGroup;
        }

        return Group::$ReservedGroups;
    }


    /**
     * Define a new name.
     * If this name already exists or is invalid, this will be ignored.
     * @param $new_name The new name for the group
     */
    public function setName(string $new_name) {

        // check reserved groups
        if (in_array($this->name(), Group::reservedGroupNames())) {
            \Core\Log::error("Deny renaming group '" . $this->name() . "'.");
            return;
        }

        // sanity check
        $new_name = trim($new_name);
        if ($new_name == "") {
            \Core\Log::warning("Ignore changing group name of " . $this . " to empty string.");
            return;
        }

        // check if new name is unique
        $res = \Core\Database::fetch($this->tableName(), ['Id'], ['Name'=>$new_name]);
        if (count($res)) {
            \Core\Log::warning("Ignore changing group name of " . $this . " to '$new_name' because ambiguous");
            return;
        }

        $this->storeColumns(['Name'=>$new_name]);
    }


    /**
     * Grant or Deny a permission
     * @param $permission The name of thje requested permission
     * @param $grant TRUE if permission shall be set, else FALSE
     */
    public function setPermission(string $permission, bool $grant) {
        $column_values = array();
        $column_values[$permission] = ($grant) ? 1 : 0;
        $this->storeColumns($column_values);
    }
}
