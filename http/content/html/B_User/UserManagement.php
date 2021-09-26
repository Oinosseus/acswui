<?php

namespace Content\Html;

class UserManagement extends \Core\HtmlContent {

    public function __construct() {
        parent::__construct(_("User Management"), _("User Management"));
        $this->requirePermission("Admin_User_Management");
    }


    public function getHtml() {
        $html = "";

        // process data
        if (array_key_exists("SaveUsers", $_POST) && $_POST['SaveUsers'] == "TRUE") {
            foreach (\DbEntry\User::listUsers() as $u) {
                foreach (\DbEntry\Group::listGroups() as $g) {
                    $post_id = "USER_" . $u->id() . "_GROUP_" . $g->id();
                    if (array_key_exists($post_id, $_POST) && $_POST[$post_id] == "TRUE") {
                        $g->addUser($u);
                    } else {
                        $g->removeUser($u);
                    }
                }
            }
        }

        $html .= '<form action="" method="post" id="UserManagementForm">';
        $html .= "<input type=\"hidden\" name=\"SaveUsers\" value=\"TRUE\" />";
        $html .= '<table>';

        $rowcount = 0;
        foreach (\DbEntry\User::listUsers() as $u) {

            if (($rowcount%16) == 0) $html .= $this->tableHeader();
            ++$rowcount;

            $html .= "<tr>";
            $html .= "<td>" . $u->id() . "</td>";
            $html .= "<td>" . $u->name() . "</td>";
            $html .= "<td>" . $u->steam64GUID() . "</td>";
            foreach (\DbEntry\Group::listGroups() as $g) {
                if (in_array($g, $u->groups())) {
                    $checked ="checked=\"checked\"";
                } else {
                    $checked ="";
                }
                $html .= "<td><input type=\"checkbox\" name=\"USER_" . $u->id() . "_GROUP_" . $g->id() . "\" value=\"TRUE\" $checked></td>";
            }
            $html .= "</tr>";
        }

        $html .= "</table>";
        $html .= "<button type=\"submit\">" . _("Save All") . "</button>";
        $html .= "</form>";

        return $html;
    }

    private function tableHeader() {
        $html = "";
        $html .= "<tr>";
        $html .= "<th>" . _("Id") . "</th>";
        $html .= "<th>" . _("Login") . "</th>";
        $html .= "<th>Steam64GUID</th>";
        foreach (\DbEntry\Group::listGroups() as $g) {
            $html .= "<th>" . $g->name() . "</th>";
        }
        $html .= "</tr>";
        return $html;
    }
}
