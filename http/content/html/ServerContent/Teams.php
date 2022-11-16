<?php

namespace Content\Html;

class Teams extends \core\HtmlContent {

    private $CurrentTeam = NULL;
    private $CanFound = FALSE;
    private $IsOwner = FALSE;
    private $IsManager = FALSE;
    private $IsSponsor = FALSE;


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
        $this->IsOwner = ($this->CurrentTeam !== NULL && $this->CurrentTeam->owner()->id() == \Core\UserManager::currentUser()->id());  // only owner can edit
        if ($this->CurrentTeam && $tmbr = $this->CurrentTeam->findMember(\Core\UserManager::currentUser())) {
            $this->IsManager |= $tmbr->permissionManage();
            $this->IsSponsor |= $tmbr->permissionSponsor();
        }


        // process actions
        if (array_key_exists("Action", $_REQUEST)) {

            // cancel actions
            if ($_REQUEST['Action'] == "ResetSponsorForTeamCarClass") {
                unset($_GET['SponsorForTeamCarClass']);
            }
            if ($_REQUEST['Action'] == "DoNotDeleteCar") {
                unset($_GET['AskDeleteTeamCar']);
            }

            // found new team
            if ($_REQUEST['Action'] == "FoundNewTeam") {
                $team = \DbEntry\Team::createNew(\Core\UserManager::currentUser());
                $this->reload(["Id"=>$team->id()]);
            }

            // Save Team
            if ($_REQUEST['Action'] == "SaveTeam") {

                if ($this->IsOwner) {

                    // save team core data
                    $this->CurrentTeam->setName($_POST['TeamName']);
                    $this->CurrentTeam->setAbbreviation($_POST['TeamAbbreviation']);

                    // add car classes
                    if (strlen($_POST['AddCarClass']) > 0) {
                        $cc = \DbEntry\CarClass::fromid($_POST['AddCarClass']);
                        if ($cc) \DbEntry\TeamCarClass::createNew($this->CurrentTeam, $cc);
                    }

                    // delete car classes
                    foreach ($this->CurrentTeam->carClasses() as $tcc) {
                        if (array_key_exists("DeleteClass{$tcc->id()}", $_POST)) $tcc->setActive(FALSE);
                    }

                    // upload logo
                    if (array_key_exists("TeamLogoFile", $_FILES) && strlen($_FILES['TeamLogoFile']['name']) > 0) {
                        $this->CurrentTeam->uploadLogoFile($_FILES['TeamLogoFile']['tmp_name'], $_FILES['TeamLogoFile']['name']);
                    }
                }

                if ($this->IsManager || $this->IsOwner) {

                    // add members
                    if (strlen($_POST['AddTeamMember']) > 0) {
                        $member = \DbEntry\User::fromid($_POST['AddTeamMember']);
                        if ($member) $this->CurrentTeam->addMember($member);
                    }

                    // apply permissions
                    foreach ($this->CurrentTeam->members() as $tmbr) {
                        // $tmbr->setPermissionRegister(array_key_exists("PermissionRegister{$tmbr->id()}", $_POST));
                        $tmbr->setPermissionSponsor(array_key_exists("PermissionSponsor{$tmbr->id()}", $_POST));
                        $tmbr->setPermissionManage(array_key_exists("PermissionManage{$tmbr->id()}", $_POST));
                    }

                    // add/delete driver
                    foreach ($this->CurrentTeam->carClasses() as $tcc) {
                        foreach ($tcc->listCars() as $tc) {

                            // add driver
                            if (array_key_exists("AddTeamCarDriver_{$tc->id()}", $_POST)) {
                                $tm = \DbEntry\TeamMember::fromId((int) $_POST["AddTeamCarDriver_{$tc->id()}"]);
                                if ($tm) $tc->addDriver($tm);
                            }

                            // delete driver
                            foreach ($tc->drivers() as $tmm) {
                                if (array_key_exists("DeleteCarDriver_{$tc->id()}_{$tmm->id()}", $_POST)) {
                                    $tc->removeDriver($tmm);
                                }
                            }
                        }
                    }

                    // delete team members
                    foreach ($this->CurrentTeam->members() as $tmbr) {
                        if (array_key_exists("LeaveMember{$tmbr->id()}", $_POST)) $tmbr->leaveTeam();
                    }

                }

                $this->reload(["Id" => $this->CurrentTeam->id()]);
            }

            // sponsor car skin
            if ($_REQUEST['Action'] == "SponsorCarSkin" && $this->IsSponsor) {
                if (array_key_exists("TeamCarClass", $_POST) && array_key_exists("CarSkinId", $_POST)) {
                    if ($tcc = \DbEntry\TeamCarClass::fromId($_POST['TeamCarClass'])) {
                        if ($cs = \DbEntry\CarSkin::fromId($_POST['CarSkinId'])) {
                            \DbEntry\TeamCar::createNew($tcc, $cs);
                        }
                    }
                }
                $this->reload(["Id" => $this->CurrentTeam->id()]);
            }

            // delete car skin
            if ($_REQUEST['Action'] == "DeleteTeamCar") {
                $tc = \DbEntry\TeamCar::fromId($_POST['TeamCarId']);
                if ($tc) {
                    if ($this->IsManager || $this->canDelete($tc)) {
                        $tc->setActive(FALSE);
                    }
                }
                unset($_GET['AskDeleteTeamCar']);
            }
        }


        $html = "";
        if ($this->CurrentTeam === NULL) {
            $html .= $this->showAllTeams();
        } else if (array_key_exists("SponsorForTeamCarClass", $_GET)) {
            $tcc = \DbEntry\TeamCarClass::fromId($_GET['SponsorForTeamCarClass']);
            $html .= $this->showSponsorCar($tcc);
        } else if (array_key_exists("AskDeleteTeamCar", $_GET)) {
            $tc = \DbEntry\TeamCar::fromId($_GET['AskDeleteTeamCar']);
            $html .= $this->showAskDeleteTeamCar($tc);
        } else {
            $html .= $this->showExplicitTeam();
        }

        return $html;
    }


    //! @return TRUE if current user is allowed to delete this car
    private function canDelete(\DbEntry\TeamCar $tc) {
        if (\Core\UserManager::loggedUser() === NULL) return FALSE;
        return \Core\UserManager::currentUser()->id() == $tc->carSkin()->owner()->id();
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


    private function showAskDeleteTeamCar(\DbEntry\TeamCar $tc) : string {
        if (!$this->IsManager && !$this->canDelete($tc)) return "";
        $html = "";
        $html .= $this->newHtmlForm("POST");
        $html .= $tc->carSkin()->html();
        $html .= "<br>";
        $html .= _("Do you really want to remove this car from the team?");
        $html .= "<br>";
        $html .= "<input type=\"hidden\" name=\"TeamCarId\" value=\"{$tc->id()}\" />";
        $html .= "<button type=\"submit\" name=\"Action\" value=\"DeleteTeamCar\">" . _("Delete Car") . "</button> ";
        $html .= "<button type=\"submit\" name=\"Action\" value=\"DoNotDeleteCar\">" . _("Cancel") . "</button>";
        $html .= "</form>";
        return $html;
    }


    private function showExplicitTeam() : string {
        $html = "";

        $tm = $this->CurrentTeam;

        // ====================================================================
        //  General Team Info
        // ====================================================================

        $html .= "<h1>{$tm->name()}</h1>";
        $html .= $this->newHtmlForm("POST", "", TRUE);
        $html .= "<input type=\"hidden\" name=\"Id\" value=\"{$tm->id()}\">";

        $html .= "<table id=\"TeamInformation\">";
        $html .= "<caption>" . _("Team Information") . "</caption>";
        $html .= "<tr>";
        $html .= "<td rowspan=\"4\"><img src=\"{$tm->logoPath()}\"></td>";
        $html .= "<th>" . _("Owner") . "</th><td>{$tm->owner()->html()}</td></tr>";
        if ($this->IsOwner) {
            $html .= "<tr><th>" . _("Name") . "</th><td><input type=\"text\" name=\"TeamName\" value=\"{$tm->name()}\" /></td></tr>";
            $html .= "<tr><th>" . _("Abbreviation") . "</th><td><input type=\"text\" name=\"TeamAbbreviation\" value=\"{$tm->abbreviation()}\" maxlength=\"5\" /></td></tr>";
            $html .= "<tr><th>" . _("Logo") . "</th><td>";
            $html .= "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"524288\" />";
            $html .= "<input type=\"file\" name=\"TeamLogoFile\"><br>";
            $html .= "</td></tr>";
        } else {
            $html .= "<tr><th>" . _("Name") . "</th><td>{$tm->name()}</td></tr>";
            $html .= "<tr><th>" . _("Abbreviation") . "</th><td>{$tm->abbreviation()}</td></tr>";
        }
        $html .= "</table>";


        // ====================================================================
        //  Team Members
        // ====================================================================

        $html .= "<h1>" . _("Team Members") . "</h1>";
        $html .= "<table id=\"TeamMembers\">";
        $html .= "<caption>" . _("Team Members") . "</caption>";
        $html .= "<tr>";
        $html .= "<th>" . _("User") . "</th>";
        $html .= "<th title=\"" . _("When this user was added by a team manager") . "\">" . _("Hiring") . "</th>";
        // $html .= "<th title=\"" . _("User can register for events") . "\">" . _("Register") . "</th>";
        $html .= "<th title=\"" . _("User can add cars to the team car pool") . "\">" . _("Sponsor") . "</th>";
        $html .= "<th title=\"" . _("User can manage team members") . "\">" . _("Manager") . "</th>";
        if ($this->IsManager || $this->IsOwner) $html .= "<th></th>";
        $html .= "</tr>";
        foreach ($tm->members() as $tmbr) {
            $html .= "<tr>";
            $html .= "<td>{$tmbr->user()->html()}</td>";
            $html .= "<td>" . \Core\UserManager::currentUser()->formatDateTimeNoSeconds($tmbr->hiring()) . "</td>";
            $disabled = ($this->IsManager || $this->IsOwner) ? "" : "disabled=\"yes\"";
            // $checked = ($tmbr->permissionRegister()) ? "checked=\"yes\"" : "";
            // $html .= "<td><input type=\"checkbox\" $checked $disabled name=\"PermissionRegister{$tmbr->id()}\"/></td>";
            $checked = ($tmbr->permissionSponsor()) ? "checked=\"yes\"" : "";
            $html .= "<td><input type=\"checkbox\" $checked $disabled name=\"PermissionSponsor{$tmbr->id()}\"/></td>";
            $checked = ($tmbr->permissionManage()) ? "checked=\"yes\"" : "";
            $html .= "<td><input type=\"checkbox\" $checked $disabled name=\"PermissionManage{$tmbr->id()}\"/></td>";
            if ($this->IsManager || $this->IsOwner) $html .= "<td>" . $this->newHtmlTableRowDeleteCheckbox("LeaveMember{$tmbr->id()}") . "</td>";
            $html .= "</tr>";
        }
        if ($this->IsManager || $this->IsOwner) {
            $html .= "<tr><td><select name=\"AddTeamMember\">";
            $html .= "<option value=\"\" selected=\"yes\"></option>";
            foreach (\DbEntry\User::listDrivers() as $drv) {
                $html .= "<option value=\"{$drv->id()}\">{$drv->name()}</option>";
            }
            $html .= "</select></td><td colspan=\"3\">" . _("add new team member") . "</td></tr>";
        }
        $html .= "</table>";


        // ====================================================================
        //  Car Pool
        // ====================================================================

        $html .= "<h1>" . _("Car Pool") . "</h1>";

        if ($this->IsOwner) {
            $html .= "<table id=\"CarClasses\">";
            $html .= "<caption>" . _("Team Car Classes") . "</caption>";
            $html .= "<tr>";
            $html .= "<th>" . _("Car Class") . "</th>";
            $html .= "<th></th>";
            $html .= "</tr>";

            // list existing classes
            foreach ($tm->carClasses() as $tcc) {
                $html .= "<tr>";
                $html .= "<td>{$tcc->carClass()->html(TRUE, TRUE, FALSE)}</td>";
                $html .= "<td>" . $this->newHtmlTableRowDeleteCheckbox("DeleteClass{$tcc->id()}") . "</td>";
                $html .= "</tr>";
            }

            // add new class
            $html .= "<tr><td><select name=\"AddCarClass\">";
            $html .= "<option value=\"\" selected=\"yes\"></option>";
            foreach (\DbEntry\CarClass::listClasses() as $cc) {
                $html .= "<option value=\"{$cc->id()}\">{$cc->name()}</option>";
            }
            $html .= "</select></td></tr>";
        $html .= "</table>";
        }


        // --------------------------------------------------------------------
        //  Car Classes

        foreach ($tm->carClasses() as $tcc) {
            $html .= "<h2>{$tcc->carClass()->name()}</h2>";

            if ($this->IsSponsor) {
                $url = $this->url(["Id"=>$tm->id(), "SponsorForTeamCarClass"=>$tcc->id()]);
                $html .= "<a href=\"{$url}\">" . _("Sponsor a new car") . "</a><br>";
            }

            // list all available team cars of a class
            foreach ($tcc->listCars() as $tc) {
                $html .= "<table class=\"TeamCar\">";
                $html .= "<caption>" . _("Team Car") . " {$tc->carSkin()->name()}</caption>";
                $html .= "<tr>";
                $rowspan = 1 + count($tc->drivers());
                $colspan = 1;
                if ($this->IsManager) {
                    ++$rowspan;
                    ++$colspan;
                }
                $html .= "<td rowspan=\"$rowspan\">";
                $html .= _("Owner: ") . $tc->carSkin()->owner()->html() . "<br>";
                $html .= $tc->carSkin()->html(TRUE, FALSE, TRUE);
                if ($this->IsManager || $this->canDelete($tc)) {
                    $html .= "<br><a href=\"" . $this->url(["Id"=>$this->CurrentTeam->id(), "AskDeleteTeamCar"=>$tc->id()]) . "\">" . _("Delete Car") . "</a>";
                }
                $html .= "</td>";

                $html .= "<th colspan=\"$colspan\">" . _("Driver") . "</th>";
                $html .= "</tr>";

                // list drivers
                $listed_drivers = array();
                foreach ($tc->drivers() as $tmm) {
                    $listed_drivers[] = $tmm;
                    $html .= "<tr>";
                    $html .= "<td>{$tmm->user()->html()}</td>";
                    if ($this->IsManager) {
                        $html .= "<td>" . $this->newHtmlTableRowDeleteCheckbox("DeleteCarDriver_{$tc->id()}_{$tmm->id()}") . "</td>";
                    }
                    $html .= "</tr>";
                }

                // add driver
                if ($this->IsManager) {
                    $html .= "<tr><td colspan=\"2\">";
                    $html .= "<select name=\"AddTeamCarDriver_{$tc->id()}\">";
                    $html .= "<option value=\"\" selected=\"yes\"></option>";
                    foreach ($tm->members() as $tmm) {
                        if (in_array($tmm, $listed_drivers)) continue;
                        $html .= "<option value=\"{$tmm->id()}\">{$tmm->user()->name()}</option>";
                    }
                    $html .= "</select>";
                    $html .= "</td></tr>";
                }

                $html .= "</table>";
            }
        }


        $html .= "<br><br>";
        if ($this->IsManager || $this->IsOwner) {
            $html .= "<button type=\"submit\" name=\"Action\" value=\"SaveTeam\">" . _("Save") . "</button>";
        }
        $html .= "</form>";

        return $html;
    }


    private function showSponsorCar(\DbEntry\TeamCarClass $tcc) : string {
        if (!$this->IsSponsor) return "";

        $html = "";
        $html .= $this->newHtmlForm("POST");

        $html .= "<h1>{$tcc->carClass()->name()}</h1>";
        $html .= "<input type=\"hidden\" name=\"Id\" value=\"{$this->CurrentTeam->id()}\">";
        $html .= "<input type=\"hidden\" name=\"TeamCarClass\" value=\"{$tcc->id()}\">";

        // get list of owned cars for a certain carclass
        $car_skin_list = array();
        $car_skin_list_raw = \DbEntry\CarSkin::listOwnedSkins(\Core\UserManager::currentUser());
        foreach ($car_skin_list_raw as $cs) {
            if ($tcc->carClass()->validCar($cs->car())) $car_skin_list[] = $cs;
        }

        // list available skins
        if (count($car_skin_list) == 0) {
            $html .= _("You cannot sponsor any cars, since you don't own any in this class.");
        } else {
            foreach ($car_skin_list as $cs) {
                $html .= $this->newHtmlContentRadio("CarSkinId", $cs->id(), $cs->html(FALSE));
            }
        }

        $html .= "<br>";
        if (count($car_skin_list) > 0) {
            $html .= "<button type=\"submit\" name=\"Action\" value=\"SponsorCarSkin\">" . _("Sponsor Car") . "</button> ";
        }
        $html .= "<button type=\"submit\" name=\"Action\" value=\"ResetSponsorForTeamCarClass\">" . _("Cancel") . "</button>";
        $html .= "</form>";

        return $html;
    }
}
