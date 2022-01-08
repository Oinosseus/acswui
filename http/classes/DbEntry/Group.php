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


    /**
     * Add a user to this group.
     * Ignored if the user is already in this group
     * @param $user The user to be added
     */
    public function addUser(User $user) {

        // check if user is already in the group
        $res = \Core\Database::fetch("UserGroupMap", ['Id'], ['User'=>$user->id(), 'Group'=>$this->id()]);
        if (count($res) == 0) {
            \Core\Database::insert("UserGroupMap", ['User'=>$user->id(), 'Group'=>$this->id()]);
        }
    }


    //! Delete this group from database
    public function delete() {

        if (in_array($this->name(), Group::reservedGroupNames())) {
            \Core\Log::error("Deny deleting group '" . $this->name() . "'.");
            return;
        }

        // delete all maps with this group
        \Core\Database::query("DELETE FROM UserGroupMap WHERE UserGroupMap.Group = " . $this->id() . ";");

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


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same Name the same object.
     * @return An object by requested name of the group
     */
    public static function fromName(string $group_name) {
        //! @todo This could be cached to save loop iterations when many groups are present (which I do not expect)
        foreach (Group::listGroups() as $g) {
            if ($g->name() == $group_name) return $g;
        }
        \Core\Log::error("Unknown group '$group_name'!");
        return NULL;
    }


    //! @return A string list containing all granted permissions for this group
    public function grantedPermissions() {
        if ($this->GrantedPermissions === NULL) {
            $this->GrantedPermissions = array();
            foreach (Group::listPermissions() as $p) {
                if ($this->loadColumn($p) == 1) $this->GrantedPermissions[] = $p;
            }
        }
        return $this->GrantedPermissions;
    }


    //! @return TRUE if the requested permission is set for the group, in any other case FALSE
    public function grants(string $permission) {
        return in_array($permission, $this->grantedPermissions());
    }


    //! @return A list of all available group objects (ordered by name)
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
     * Remove a user from this group.
     * Ignored if the user is not member of  this group
     * @param $user The user to be removed
     */
    public function removeUser(User $user) {

        $res = \Core\Database::fetch("UserGroupMap", ['Id'], ['User'=>$user->id(), 'Group'=>$this->id()]);
        foreach ($res as $row) {
            \Core\Database::delete("UserGroupMap", $row['Id']);
        }
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
