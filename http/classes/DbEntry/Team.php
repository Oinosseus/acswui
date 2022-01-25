<?php

namespace DbEntry;

/**
 * A Team class is actually not a DbEntry object, since it is not presented by a dedicated database table.
 * However, the Tams class copies the DbEntry handling interface and is retrieved from database data.
 */
class Team {

    private static $TeamsCache = array();
    private static $AllTeams = NULL;

    private $CarSkins = NULL;
    private $Id = NULL;
    private $Users = NULL;

    private function __construct(string $id) {
        $this->Id = $id;
    }


    //! @return A list of CarSkin objects, that are used by this team
    public function carSkins() {
        if ($this->CarSkins === NULL) {
            $this->CarSkins = array();
            $query = "SELECT Id FROM `CarSkins` WHERE Team = '" . \Core\Database::escape($this->id()) . "';";
            $res = \Core\Database::fetchRaw($query);
            foreach ($res as $row) {
                $this->CarSkins[] = CarSkin::fromId($row['Id']);
            }
        }
        return $this->CarSkins;
    }


    /**
     * This function is cached and returns for same IDs the same object.
     * @return An Team object
     */
    public static function fromId(string $id) {
        // get from cache
        if (!array_key_exists($id, Team::$TeamsCache)) {

            // request database
            $query = "SELECT DISTINCT Team FROM `CarSkins` WHERE Team = '" . \Core\Database::escape($id) . "';";
            $res = \Core\Database::fetchRaw($query);
            if (count($res) > 0) {
                Team::$TeamsCache[$id] = new Team($id);
            } else {
                Team::$TeamsCache[$id] = NULL;
            }
        }

        return Team::$TeamsCache[$id];
    }


    //! The Id/Name of the team
    public function id() {
        return $this->Id;
    }


    //! A list of all available Team objects
    public static function listTeams() {
        if (Team::$AllTeams === NULL) {
            Team::$AllTeams = array();
            $query = "SELECT DISTINCT Team FROM `CarSkins` WHERE Steam64GUID != '' AND Deprecated = 0;";
            $res = \Core\Database::fetchRaw($query);
            foreach ($res as $row) {
                Team::$AllTeams[] = Team::fromId($row['Team']);
            }
        }

        return Team::$AllTeams;
    }


    //! The teamname intended to be inserted into html
    public function htmlName() {
        return "<a href=\"index.php?HtmlContent=Teams&TeamId=" . $this->id() . "\">" . $this->id() . "</a>";
    }


    //! @return A list of User objects that are in this team
    public function users() {
        if ($this->Users === NULL) {
            $this->Users = array();
            $query = "SELECT DISTINCT Steam64GUID FROM `CarSkins` WHERE Team = '" . \Core\Database::escape($this->id()) . "';";
            $res = \Core\Database::fetchRaw($query);
            foreach ($res as $row) {
                $user = User::fromSteam64GUID($row['Steam64GUID']);
                if ($user !== NULL) $this->Users[] = $user;
            }
        }
        return $this->Users;
    }
}
