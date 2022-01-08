<?php

namespace Content\Html;

class UserManagement extends \Core\HtmlContent {

    public function __construct() {
        parent::__construct(_("User Management"), _("User Management"));
        $this->requirePermission("User_Management_View");
    }


    public function getHtml() {
        $this->CanEdit = \Core\UserManager::loggedUser()->permitted("User_Management_Edit");
        $html = "";

        // get list of groups without automatic groups
        $group_list = array();
        foreach (\DbEntry\Group::listGroups() as $g) {
            if ($g->name() == \Core\Config::GuestGroup || $g->name() == \Core\Config::DriverGroup) continue;
            $group_list[] = $g;
        }


        // get list of users/drivers
        //! @todo implement differennt approach to directly read drivers to save execution time
        \DbEntry\User::listDrivers();


        // process data
        if (array_key_exists("SaveUsers", $_POST) && $_POST['SaveUsers'] == "TRUE" && $this->CanEdit) {
            foreach (\DbEntry\User::listDrivers() as $u) {
                foreach ($group_list as $g) {

                    // This line is actually not necessary.
                    // But to prevent that someone in future iterates over all groups (also the automatic ones) this stays for safety.
                    if ($g->name() == \Core\Config::GuestGroup || $g->name() == \Core\Config::DriverGroup) continue;

                    $post_id = "USER_" . $u->id() . "_GROUP_" . $g->id();
                    if (array_key_exists($post_id, $_POST) && $_POST[$post_id] == "TRUE") {
                        $g->addUser($u);
                    } else {
                        $g->removeUser($u);
                    }
                }
            }
        }

        $html .= $this->newHtmlForm("POST", "UserManagementForm");
        $html .= "<input type=\"hidden\" name=\"SaveUsers\" value=\"TRUE\" />";
        $html .= '<table>';

        $rowcount = 0;
        foreach (\DbEntry\User::listDrivers() as $u) {

            if (($rowcount%16) == 0) $html .= $this->tableHeader($group_list);
            ++$rowcount;

            $html .= "<tr>";
            $html .= "<td>" . $u->id() . "</td>";
            $html .= "<td>" . $u->name() . "</td>";
            $html .= "<td>" . $u->steam64GUID() . "</td>";
            foreach ($group_list as $g) {

                if (in_array($g, $u->groups())) {
                    $checked ="checked=\"checked\"";
                } else {
                    $checked ="";
                }
                $disabled = ($g->name() == \Core\Config::GuestGroup || $g->name() == \Core\Config::DriverGroup || !$this->CanEdit) ? "disabled=\"yes\"":"";
                $html .= "<td><input type=\"checkbox\" name=\"USER_" . $u->id() . "_GROUP_" . $g->id() . "\" value=\"TRUE\" $checked $disabled></td>";
            }
            $html .= "</tr>";
        }

        $html .= "</table>";
        if ($this->CanEdit) $html .= "<button type=\"submit\">" . _("Save All") . "</button>";
        $html .= "</form>";

        return $html;
    }

    private function tableHeader($group_list) {
        $html = "";
        $html .= "<tr>";
        $html .= "<th>" . _("Id") . "</th>";
        $html .= "<th>" . _("Login") . "</th>";
        $html .= "<th>Steam64GUID</th>";
        foreach ($group_list as $g) {
            $html .= "<th>" . $g->name() . "</th>";
        }
        $html .= "</tr>";
        return $html;
    }
}
