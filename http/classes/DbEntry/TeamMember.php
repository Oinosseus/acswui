<?php

declare(strict_types=1);
namespace DbEntry;

//! Wrapper for database table element
class TeamMember extends DbEntry {

    /**
     * Construct a new object
     * @param $id Database table id
     */
    protected function __construct(int $id) {
        parent::__construct("TeamMembers", $id);
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) : ?TeamMember {
        return parent::getCachedObject("TeamMembers", "TeamMember", $id);
    }


    //! @return Timestamp of when the member was added to the team
    public function hiring() : \DateTime {
        $t = $this->loadColumn("Hiring");
        return new \DateTime($t);
    }


    //! @return A html string with the user name and containing a link to the user profile page
    public function html() {
        $html = "";
        $html .= $this->user()->nationalFlag();
        $html .= "&nbsp;";
        $html .= "<a href=\"index.php?HtmlContent=B_User&UserId={$this->user()->id()}\">{$this->user()->name()}</a>";
        return $html;
    }


    //! same as setActive(FALSE)
    public function leaveTeam() {
        $this->setActive(FALSE);
    }


    //! @return TRUE if the team member is manager
    public function permissionManage() : bool {
        return ($this->loadColumn("PermissionManage") == 1) ? TRUE : FALSE;
    }


    // //! @return TRUE if the team member can register for events
    // public function permissionRegister() : bool {
    //     return ($this->loadColumn("PermissionRegister") == 1) ? TRUE : FALSE;
    // }


    //! @return TRUE if the team member is sponsor
    public function permissionSponsor() : bool {
        return ($this->loadColumn("PermissionSponsor") == 1) ? TRUE : FALSE;
    }


    //! @param $permitted If TRUE, the permision is set to be granted
    public function setPermissionManage(bool $permitted) {
        $this->storeColumns(["PermissionManage"=> ($permitted) ? 1:0]);
    }


    // //! @param $permitted If TRUE, the permision is set to be granted
    // public function setPermissionRegister(bool $permitted) {
    //     $this->storeColumns(["PermissionRegister"=> ($permitted) ? 1:0]);
    // }


    //! @param $active TRUE if this object shall be active, else FALSE
    public function setActive(bool $active) {

        // inactivate TeamCarOccupations
        if (!$active) {
            foreach ($this->team()->carClasses() as $tcc) {
                foreach ($tcc->listCars() as $tc) {
                    $tc->removeDriver($this);
                }
            }
        }

        // set this (in)active
        $columns = array();
        $columns['Active'] = ($active) ? 1:0;
        $this->storeColumns($columns);
    }


    //! @param $permitted If TRUE, the permision is set to be granted
    public function setPermissionSponsor(bool $permitted) {
        $this->storeColumns(["PermissionSponsor"=> ($permitted) ? 1:0]);
    }


    //! @return The according Team object
    public function team() : Team {
        return Team::fromId((int) $this->loadColumn("Team"));
    }


    //! @return The according User object of the team member
    public function user() : User {
        return User::fromId((int) $this->loadColumn("User"));
    }

}
