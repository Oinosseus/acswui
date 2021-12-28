<?php

namespace DbEntry;

/**
 * Cached wrapper to car databse Tracks table element
 */
class User extends DbEntry { #implements JsonSerializable {

    private $Steam64GUID = NULL;
    private $Color = NULL;
    private $Privacy = NULL;
    private $Locale = NULL;
    private $Groups = NULL;
    private $Permissions = NULL;

    private static $UserList = NULL;


    /**
     * @param $id Database table id
     */
    protected function __construct($id) {
        parent::__construct("Users", $id);
    }


    //! @return The color code for this user as string (eg #12ab9f)
    public function color() {
        if ($this->isRoot()) return "#000000";
        if ($this->Color === NULL) {
            $this->Color = $this->loadColumn("Color");
            if ($this->Color == "") $this->Color = "#000000";
        }
        return $this->Color;
    }


    /**
     * @return The username that shall be displayed on HTML output (depending on privacy settings)
     */
    public function name() {
        if ($this->isRoot()) return "root";
        return ($this->privacyFulfilled()) ? $this->loadColumn("Name") : "***";
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) {
        return parent::getCachedObject("Users", "User", $id);
    }


    //! @return A list of Group objects where the user is member in
    public function groups() {
        if ($this->isRoot()) {
            return Group::listGroups();

        } else if ($this->Groups === NULL) {
            $this->Groups = array();
            $res = \Core\Database::fetch("UserGroupMap", ['Group'], ['User'=>$this->id()]);
            foreach ($res as $row) {
                $this->Groups[] = \DbEntry\Group::fromId($row['Group']);
            }
        }
        return $this->Groups;
    }


    //! @return True, if this represents the root user, else False
    public function isRoot() {
        return $this->id() === 0;
    }


    //! @return The preferred locale of the user
    public function locale() {
        if ($this->isRoot()) return "";
        if ($this->Locale === NULL) {
            $this->Locale = $this->loadColumn('Locale');
        }
        return $this->Locale;
    }


    /**
     * Set a new color for the user
     * @param $color E.g. '#12ab98'
     */
    public function setColor(string $color) {

        if (strlen($color) !== 7 || substr($color, 0, 1) != "#") {
            \Core\Log::error("Malformed color: '$color'!");
            return;
        }

        \Core\Database::update("Users", $this->id(), ['Color'=>$color]);
        $this->Color = $color;
    }


    public function setLocale(string $new_locale) {

        // sanity check
        if (!in_array($new_locale, \Core\Config::Locales)) {
            $new_locale = "";
        }
        if ($new_locale === $this->Locale) return;

        // update DB
        \Core\Database::update("Users", $this->id(), ['Locale'=>$new_locale]);
        $this->Locale = $new_locale;
    }


    public function setPrivacy(int $privacy) {

        if ($privacy < -1 || $privacy > 1) {
            \Core\Log::error("Invalid privacy value: $privacy!");
            return;
        }

        \Core\Database::update("Users", $this->id(), ['Privacy'=>$privacy]);
        $this->Privacy = $privacy;
    }


    //! @return TRUE if this user has a certain permission (else FALSE)
    public function permitted(string $permission) {

        // root has all permissions
        if ($this->id() == 0 && $this->name() == "root") return TRUE;

        // check granted permissions of user
        if ($this->Permissions === NULL) {
            $this->Permissions = array();
            foreach ($this->groups() as $g) {
                foreach ($g->grantedPermissions() as $p) {
                    $this->Permissions[] = $p;
                }
            }
        }
        return in_array($permission, $this->Permissions);
    }


    /**
     * Following values are possible:
     * -1 (Private): Absolute privacy (not showing any personal information to public
     * 0 (Community): Only show personal information to logged in users
     * 1 (Public): Allow anyone to see personal information
     * @return The privacy level for this user
     */
    public function privacy() {
        if ($this->isRoot()) return 0;
        if ($this->Privacy == NULL) $this->Privacy = (int) $this->loadColumn("Privacy");
        return $this->Privacy;
    }


    //! @return TRUE if the requsted privacy level is currenlty fulfuilled
    public function privacyFulfilled() {

        // root user
        if ($this->isRoot()) return TRUE;

        $current_user = \Core\UserManager::loggedUser();
        $privacy = $this->privacy();

        if ($current_user && $current_user->id() == $this->id()) {
            return TRUE;
        } else if ($privacy > 0) {
            return TRUE;
        } else if ($privacy == 0 && $current_user) {
            return TRUE;
        }

        return FALSE;
    }


    //! @return The Steam64GUID of the user
    public function steam64GUID() {
        if ($this->isRoot()) return 0;
        if ($this->Steam64GUID === NULL) $this->Steam64GUID = $this->loadColumn('Steam64GUID');
        return $this->Steam64GUID;
    }


    //! @return A list of all users
    public static function listUsers() {

        if (User::$UserList === NULL) {
            User::$UserList = array();
            foreach (\Core\Database::fetch("Users", ['Id']) as $u) {
                User::$UserList[] = User::fromId($u['Id']);
            }
        }

        return User::$UserList;
    }
}

?>