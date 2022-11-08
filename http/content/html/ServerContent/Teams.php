<?php

namespace Content\Html;

class Teams extends \core\HtmlContent {

    private $CurrentTeam = NULL;
    private $CanFound = FALSE;
    private $CanEdit = FALSE;
    private $CanManage = FALSE;


    public function __construct() {
        parent::__construct(_("Teams"),  _("Teams"));
        $this->requirePermission("ServerContent_Teams_View");
    }


    public function getHtml() {

        // identify requested team
        if (array_key_exists("Id", $_REQUEST)) {
            $this->CurrentTeam = \DbEntry\Team::fromId($_REQUEST['Id']);
        }

        // check permissions
        $this->CanFound = \Core\UserManager::currentUser()->permitted("ServerContent_Teams_Found");
        $this->CanEdit = ($this->CurrentTeam !== NULL && $this->CurrentTeam->owner()->id() == \Core\UserManager::currentUser()->id());  // only owner can edit
        $this->CanManage = $this->CanEdit;
        if ($tmbr = $this->CurrentTeam->findMember(\Core\UserManager::currentUser())) {
            $this->CanManage |= $tmbr->permissionManage();
        }


        // process actions
        if (array_key_exists("Action", $_REQUEST)) {

            // found new team
            if ($_REQUEST['Action'] == "FoundNewTeam") {
                $team = \DbEntry\Team::createNew(\Core\UserManager::currentUser());
                $this->reload(["Id"=>$team->id()]);
            }

            // Save Team
            if ($_REQUEST['Action'] == "SaveTeam") {

                // save team core data
                if ($this->CanEdit) {
                    $this->CurrentTeam->setName($_POST['TeamName']);
                    $this->CurrentTeam->setAbbreviation($_POST['TeamAbbreviation']);
                }

                if ($this->CanManage) {

                    // add members
                    if (strlen($_POST['AddTeamMember']) > 0) {
                        $member = \DbEntry\User::fromid($_POST['AddTeamMember']);
                        if ($member) $this->CurrentTeam->addMember($member);
                    }

                    // apply permissions
                    foreach ($this->CurrentTeam->members() as $tmbr) {
                        $tmbr->setPermissionDrive(array_key_exists("PermissionDrive{$tmbr->id()}", $_POST));
                        $tmbr->setPermissionRegister(array_key_exists("PermissionRegister{$tmbr->id()}", $_POST));
                        $tmbr->setPermissionSponsor(array_key_exists("PermissionSponsor{$tmbr->id()}", $_POST));
                        $tmbr->setPermissionManage(array_key_exists("PermissionManage{$tmbr->id()}", $_POST));
                    }

                    // delete team members
                    foreach ($this->CurrentTeam->members() as $tmbr) {
                        if (array_key_exists("LeaveMember{$tmbr->id()}", $_POST)) $tmbr->leaveTeam();
                    }

                }

                $this->reload(["Id" => $this->CurrentTeam->id()]);
            }
        }


        $html = "";
        if ($this->CurrentTeam === NULL) {
            $html .= $this->showAllTeams();
        } else {
            $html .= $this->showExplicitTeam();
        }

        return $html;
    }


    private function showAllTeams() : string {
        $html = "";

        // list teams
        foreach (\DbEntry\Team::listTeams() as $team) {
            $html .= $team->html();
        }

        $html .= "<br><br>";

        // found new team
        if ($this->CanFound) {
            $url = $this->url(['Action'=>"FoundNewTeam"]);
            $html .= "<a href=\"$url\">" . _("Found new Team") . "</a>";
        }

        return $html;
    }


    private function showExplicitTeam() : string {
        $html = "";

        $tm = $this->CurrentTeam;

        $html .= "<h1>" . _("Team Organisation") . "</h1>";
        $html .= $this->newHtmlForm("POST");
        $html .= "<input type=\"hidden\" name=\"Id\" value=\"{$tm->id()}\">";

        $html .= "<table id=\"TeamInformation\">";
        $html .= "<caption>" . _("Team Information") . "</caption>";
        $html .= "<tr><th>" . _("Owner") . "</th><td>{$tm->owner()->html()}</td></tr>";
        if ($this->CanEdit) {
            $html .= "<tr><th>" . _("Name") . "</th><td><input type=\"text\" name=\"TeamName\" value=\"{$tm->name()}\" /></td></tr>";
            $html .= "<tr><th>" . _("Abbreviation") . "</th><td><input type=\"text\" name=\"TeamAbbreviation\" value=\"{$tm->abbreviation()}\" /></td></tr>";
        } else {
            $html .= "<tr><th>" . _("Name") . "</th><td>{$tm->name()}</td></tr>";
            $html .= "<tr><th>" . _("Abbreviation") . "</th><td>{$tm->abbreviation()}</td></tr>";
        }
        $html .= "</table>";

        $html .= "<table id=\"TeamMembers\">";
        $html .= "<caption>" . _("Team Members") . "</caption>";
        $html .= "<tr>";
        $html .= "<th>" . _("User") . "</th>";
        $html .= "<th title=\"" . _("When this user was added by a team manager") . "\">" . _("Hiring") . "</th>";
        $html .= "<th title=\"" . _("User can drive cars of the team") . "\">" . _("Drive") . "</th>";
        $html .= "<th title=\"" . _("User can register for events") . "\">" . _("Register") . "</th>";
        $html .= "<th title=\"" . _("User can add cars to the team car pool") . "\">" . _("Sponsor") . "</th>";
        $html .= "<th title=\"" . _("User can manage team members") . "\">" . _("Manage") . "</th>";
        $html .= "</tr>";
        foreach ($tm->members() as $tmbr) {
            $html .= "<tr>";
            $html .= "<td>{$tmbr->user()->html()}</td>";
            $html .= "<td>" . \Core\UserManager::currentUser()->formatDateTimeNoSeconds($tmbr->hiring()) . "</td>";
            $disabled = ($this->CanManage) ? "" : "disabled=\"yes\"";
            $checked = ($tmbr->permissionDrive()) ? "checked=\"yes\"" : "";
            $html .= "<td><input type=\"checkbox\" $checked $disabled name=\"PermissionDrive{$tmbr->id()}\"/></td>";
            $checked = ($tmbr->permissionRegister()) ? "checked=\"yes\"" : "";
            $html .= "<td><input type=\"checkbox\" $checked $disabled name=\"PermissionRegister{$tmbr->id()}\"/></td>";
            $checked = ($tmbr->permissionSponsor()) ? "checked=\"yes\"" : "";
            $html .= "<td><input type=\"checkbox\" $checked $disabled name=\"PermissionSponsor{$tmbr->id()}\"/></td>";
            $checked = ($tmbr->permissionManage()) ? "checked=\"yes\"" : "";
            $html .= "<td><input type=\"checkbox\" $checked $disabled name=\"PermissionManage{$tmbr->id()}\"/></td>";
            $html .= "<td>" . $this->newHtmlTableRowDeleteCheckbox("LeaveMember{$tmbr->id()}") . "</td>";
            $html .= "</tr>";
        }
        if ($this->CanManage) {
            $html .= "<tr><td><select name=\"AddTeamMember\">";
            $html .= "<option value=\"\" selected=\"yes\"></option>";
            foreach (\DbEntry\User::listDrivers() as $drv) {
                $html .= "<option value=\"{$drv->id()}\">{$drv->name()}</option>";
            }
            $html .= "</select></td><td colspan=\"3\">" . _("add new team member") . "</td></tr>";
        }
        $html .= "</table>";


        $html .= "<button type=\"submit\" name=\"Action\" value=\"SaveTeam\">" . _("Save") . "</button>";
        $html .= "</form>";

        return $html;
    }
}
