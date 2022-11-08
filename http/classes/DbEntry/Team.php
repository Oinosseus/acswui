<?php

namespace DbEntry;

//! Wrapper for database table element
class Team extends DbEntry {

    /**
     * Construct a new object
     * @param $id Database table id
     */
    protected function __construct(int $id) {
        parent::__construct("Teams", $id);
    }


    //! @return The abbreviation for the team
    public function abbreviation() : string {
        return $this->loadColumn("Abbreviation");
    }


    /**
     * Adding new team member.
     * Will be ignorediIf already existent.
     */
    public function addMember(User $user) {
        // check if already existent
        $query = "SELECT Id FROM TeamMembers WHERE Team={$this->id()} AND User={$user->id()};";
        $res = \Core\Database::fetchRaw($query);
        if (count($res) > 0) return;

        // add member
        $columns = array();
        $columns['User'] = $user->id();
        $columns['Team'] = $this->id();
        \Core\Database::insert("TeamMembers", $columns);
    }


    /**
     * Creates a new Team that is owned by a certain user
     * @param $owner The User, that owns this Team
     * @return The new created Team object
     */
    public static function createNew(\DbEntry\User $owner) : Team {
        $columns = array();
        $columns['Owner'] = $owner->id();
        $columns['Name'] = $owner->name() . "'s Super Team";
        $columns['Abbreviation'] = substr($owner->name(), 0, 1) . "ST";
        $id = \Core\Database::insert("Teams", $columns);
        return Team::fromId($id);
    }


    /**
     * Find the TemMember object of a certain user.
     * If the user is not a member of the team, NULL is returned
     * @param $user The requested User
     * @return The according TeamMember object or NULL
     */
    public function findMember(User $user) : ?TeamMember {
        $query = "SELECT Id FROM TeamMembers WHERE Team={$this->id()} AND User={$user->id()} LIMIT 1;";
        $res = \Core\Database::fetchRaw($query);
        if (count($res) > 0) return TeamMember::fromId($res[0]['Id']);
        else return NULL;
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) : ?Team {
        return parent::getCachedObject("Teams", "Team", $id);
    }



    /**
     * @param $include_link Include a link
     * @param $show_name Include full name
     * @param $show_img Include a preview image
     * @return Html content for this object
     */
    public function html(bool $include_link = TRUE, bool $show_name = TRUE, bool $show_img = TRUE) {

        $html = "";

        // if ($show_img) $html .= "<img class=\"HoverPreviewImage\" src=\"$preview_path\" id=\"Team{$this->id()}\" alt=\"$skin_name\" title=\"{$this->car()->name()}\n$skin_name\">";

        // label
        $html .= "<label for=\"Team{$this->id()}\">";
        if ($show_name) {
            $html .= "<strong>{$this->abbreviation()}</strong><br>";
            $html .= $this->name();
        } else {
            $html .= $this->abbreviation;
        }
        $html .= "</label>";

        if ($include_link) {
            $html = "<a href=\"index.php?HtmlContent=Teams&Id={$this->id()}\">$html</a>";
        }

        $html = "<div class=\"DbEntryHtml\">$html</div>";
        return $html;
    }


    //! @return A list of Team objects, ordered by Id
    public static function listTeams() : array {
        $teams = array();
        $query = "SELECT Id FROM Teams ORDER BY Id ASC;";
        $res = \Core\Database::fetchRaw($query);
        foreach ($res as $row) {
            $teams[] = Team::fromId($row['Id']);
        }
        return $teams;
    }


    //! @return A list of TemMember objects that are team members
    public function members() : array {
        $members = array();
        $query = "SELECT Id FROM TeamMembers WHERE Team={$this->id()} ORDER BY User ASC;";
        $res = \Core\Database::fetchRaw($query);
        foreach ($res as  $row) {
            $u = TeamMember::fromId($row['Id']);
            if ($u) $members[] = $u;
        }
        return $members;
    }


    //! @return The name of the team
    public function name() : string {
        return $this->loadColumn("Name");
    }


    //! @return The owner of the team (an User object)
    public function owner() : User {
        $uid = $this->loadColumn("Owner");
        $u = User::fromId($uid);
        return $u;
    }


    /**
     * Define a new abbreviation for the team
     * @param $new_abbreviation The new team abbreviation (will be trimmed)
     */
    public function setAbbreviation(string $new_abbreviation) {
        $this->storeColumns(["Abbreviation"=>trim($new_abbreviation)]);
    }


    /**
     * Define a new name for the team
     * @param $new_name The new team name (will be trimmed)
     */
    public function setName(string $new_name) {
        $this->storeColumns(["Name"=>trim($new_name)]);
    }
}
