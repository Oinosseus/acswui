<?php

declare(strict_types=1);
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
     * Will reactive TeamMembers if existent in the past.
     */
    public function addMember(User $user) : TeamMember {
        // check if already existent
        $query = "SELECT Id FROM TeamMembers WHERE Team={$this->id()} AND User={$user->id()};";
        $res = \Core\Database::fetchRaw($query);
        if (count($res) > 0) {
            $tmm = TeamMember::fromId((int) $res[0]['Id']);
            $tmm->setActive(TRUE);
            return $tmm;
        }

        // add member
        $columns = array();
        $columns['User'] = $user->id();
        $columns['Team'] = $this->id();
        $columns['Active'] = 1;
        $id = \Core\Database::insert("TeamMembers", $columns);
        return TeamMember::fromId($id);
    }


    //! @return A list of TeamCar objects
    public function cars() : array {
        return TeamCar::listTeamCars(team:$this);
    }


    //! @return A list of TeamCarClass objects
    public function carClasses() : array {
        return TeamCarClass::listTeamCarClasses(team:$this);
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
        $query = "SELECT Id FROM TeamMembers WHERE Team={$this->id()} AND User={$user->id()} AND Active=1 LIMIT 1;";
        $res = \Core\Database::fetchRaw($query);
        if (count($res) > 0) return TeamMember::fromId((int) $res[0]['Id']);
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
     * @param $show_abbreviation Show the abbreviation of the Team name
     * @return Html content for this object
     */
    public function html(bool $include_link = TRUE,
                         bool $show_name = TRUE,
                         bool $show_img = TRUE,
                         bool $show_abbreviation = TRUE) {

        $html = "";

        if ($show_img) $html .= "<img class=\"TeamLogoImage\" src=\"{$this->logoPath()}\" id=\"Team{$this->id()}\" alt=\"{$this->abbreviation()}\" title=\"{$this->name()}\">";

        // label
        $html .= "<label for=\"Team{$this->id()}\">";
        if ($show_name && $show_abbreviation) {
            $html .= "<strong>{$this->abbreviation()}</strong><br>";
            $html .= $this->name();
        } else if ($show_name) {
            $html .= $this->name();
        } else if ($show_abbreviation) {
            $html .= $this->abbreviation();
        }
        $html .= "</label>";

        if ($include_link) {
            $html = "<a href=\"index.php?HtmlContent=Teams&Id={$this->id()}\">$html</a>";
        }

        $html = "<div class=\"DbEntryHtml DbEntryHtmlTeam\" title=\"{$this->name()}\">$html</div>";
        return $html;
    }


    /**
     * List available teams
     * @param $member Only list teams where this user is member
     * @param $manager Only list teams where this user is manager
     * @param $carclass Only list teams which drives in this CarClass
     * @return A list of Team objects
     */
    public static function listTeams(User $member=NULL,
                                     User $manager=NULL,
                                     CarClass $carclass=NULL) : array {

        // find teams with a certain member
        $team_ids_membered = array();
        if ($member) {
            $query  = "SELECT DISTINCT(Teams.Id) FROM TeamMembers";
            $query .= " INNER JOIN Teams ON Teams.Id=TeamMembers.Team";
            $query .= " WHERE TeamMembers.User={$member->id()}";
            $query .= " AND TeamMembers.Active=1";
            $query .= " ORDER BY TeamMembers.Id;";
            foreach (\Core\Database::fetchRaw($query) as $row) {
                $team_ids_membered[] = (int) $row['Id'];
            }
        }

        // find teams with a certain manager
        $team_ids_managed = array();
        if ($manager) {
            $query  = "SELECT DISTINCT(Teams.Id) FROM TeamMembers";
            $query .= " INNER JOIN Teams ON Teams.Id=TeamMembers.Team";
            $query .= " WHERE TeamMembers.User={$manager->id()}";
            $query .= " AND TeamMembers.Active=1";
            $query .= " AND TeamMembers.PermissionManage=1";
            $query .= " ORDER BY TeamMembers.Id;";
            foreach (\Core\Database::fetchRaw($query) as $row) {
                $team_ids_managed[] = (int) $row['Id'];
            }
        }

        // find teams with a certain carclass
        $team_ids_classed = array();
        if ($carclass) {
            $query  = "SELECT DISTINCT(Teams.Id) FROM TeamCarClasses";
            $query .= " INNER JOIN Teams ON Teams.Id=TeamCarClasses.Team";
            $query .= " WHERE TeamCarClasses.CarClass={$carclass->id()}";
            $query .= " AND TeamCarClasses.Active=1";
            $query .= " ORDER BY TeamCarClasses.Id;";
            foreach (\Core\Database::fetchRaw($query) as $row) {
                $team_ids_classed[] = (int) $row['Id'];
            }
        }

        // scan for teams
        $return_list = array();
        $query  = "SELECT Id FROM Teams ORDER BY ID ASC;";
        foreach (\Core\Database::fetchRaw($query) as $row) {
            $id = (int) $row['Id'];

            // check matches
            if ($member && !in_array($id, $team_ids_membered)) continue;
            if ($manager && !in_array($id, $team_ids_managed)) continue;
            if ($carclass && !in_array($id, $team_ids_classed)) continue;

            $return_list[] = Team::fromId($id);
        }

        return $return_list;
    }


    //! @return The path to the team logo image
    public function logoPath($html_relative = TRUE) {
        $path = ($html_relative) ? \Core\Config::RelPathHtdata : \Core\Config::AbsPathHtdata;
        $path .= "/htmlimg/team_logos/{$this->id()}.png";
        return $path;
    }


    //! @return A list of TemMember objects that are team members
    public function members() : array {
        $members = array();
        $query = "SELECT Id FROM TeamMembers WHERE Team={$this->id()} AND Active=1 ORDER BY User ASC;";
        $res = \Core\Database::fetchRaw($query);
        foreach ($res as  $row) {
            $u = TeamMember::fromId((int) $row['Id']);
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
        $uid = (int) $this->loadColumn("Owner");
        $u = User::fromId($uid);
        return $u;
    }


    /**
     * Define a new abbreviation for the team
     * @param $new_abbreviation The new team abbreviation (will be trimmed)
     */
    public function setAbbreviation(string $new_abbreviation) {
        $new_abbreviation = substr(trim($new_abbreviation), 0, 5);
        $this->storeColumns(["Abbreviation"=>trim($new_abbreviation)]);
    }


    /**
     * Define a new name for the team
     * @param $new_name The new team name (will be trimmed)
     */
    public function setName(string $new_name) {
        $this->storeColumns(["Name"=>trim($new_name)]);
    }


    /**
     * Copies a TEAM LOGO from the temporary upload directory into the htdata/htmlimg/team_logos directory.
     *
     * @param $upload_path Path of the temporary upload location eg. $_FILES["xxx"]["tmp_name"]
     * @param $target_name The target filename (to identify image format) eg. $_FILES["xxx"]["name"]
     * @return True on success
     */
    public function uploadLogoFile(string $upload_path, string $target_name) : bool {

        // check for valid uploaded file (attack prevention)
        if (!is_uploaded_file($upload_path)) {
            \Core\Log::warning("Ignore not uploaded file '" . $upload_path . "'!");
            return False;
        }

        // identify format
        $format = NULL;
        $suffix = strtolower(substr($target_name, -4, 4));
        if (in_array($suffix, [".jpg", "jpeg"])) $format = "jpg";
        else if ($suffix == ".png") $format = "png";
        else \Core\Log::error("Cannot identify format from '{$target_name}'!");

        // load new logo
        $img = new \Core\ImageMerger(300, 200);
        $img->merge($upload_path, TRUE, 1.0, $format);
        $img->save($this->logoPath(FALSE));

        // if reached here, upload was successfull
        return True;
    }
}
