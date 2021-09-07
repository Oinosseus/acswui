<?php

namespace Content\Html;

class GroupManagement extends \core\HtmlContent {

    public function __construct() {
        parent::__construct(_("Group Management"), _("Group Management"));
    }

    public function getHtml() {


        if (array_key_exists("DeleteGroup", $_POST)) {
            foreach (\DbEntry\Group::listGroups() as $g) {
                if ($_POST['DeleteGroup'] == $g->id()) $g->delete();
            }
        }

        if (array_key_exists("Action", $_POST) && $_POST['Action'] == "SaveGroups") {

            // update group permissions
            foreach (\DbEntry\Group::listPermissions() as $p) {
                foreach (\DbEntry\Group::listGroups() as $g) {
                    $post_id = "GROUP_" . $g->id() . "_PERMISSION_$p";
                    $grant = (array_key_exists($post_id, $_POST) && $_POST[$post_id] == "TRUE") ? TRUE : FALSE;
                    $g->setPermission($p, $grant);
                }

            }

            // update names
            foreach (\DbEntry\Group::listGroups() as $g) {
                if (in_array($g->name(), \DbEntry\Group::reservedGroupNames())) continue;
                $new_name = $_POST["GROUP_" . $g->id() . "_NAME"];
                if ($new_name != $g->name()) $g->setName($new_name);
            }

            // insert new group
            if ($_POST['GROUP_NEW_NAME'] != "") {
                $g = \DbEntry\Group::new();
                $g->setName($_POST['GROUP_NEW_NAME']);
                // set new group permissions
                foreach (\DbEntry\Group::listPermissions() as $p) {
                    $post_id = "GROUP_NEW_PERMISSION_$p";
                    $grant = (array_key_exists($post_id, $_POST) && $_POST[$post_id] == "TRUE") ? TRUE : FALSE;
                    $g->setPermission($p, $grant);
                }
            }
        }


        $html = "";

        $html .= '<form action="" method="post" id="GroupManagementForm">';
        $html .= "<input type=\"hidden\" name=\"Action\" value=\"SaveGroups\">";
        $html .= "<table>";

        // group names
        $html .= "<tr>";
        $html .= "<th>" . _("Group") . "</th>";
        foreach (\DbEntry\Group::listGroups() as $g) {
            if (in_array($g->name(), \DbEntry\Group::reservedGroupNames())) {
                $html .= "<td><input type=\"text\" name=\"GROUP_" . $g->id() . "_NAME\" value=\"" . $g->name() . "\" disabled=\"yes\"></td>";
            } else {
                $html .= "<td><input type=\"text\" name=\"GROUP_" . $g->id() . "_NAME\" value=\"" . $g->name() . "\"></td>";
            }
        }
        $html .= "<td><input type=\"text\" name=\"GROUP_NEW_NAME\" value=\"\" placeholder=\"" . _("New Group Name") . "\"/></td>";
        $html .= "</tr>";

        // permissions
        foreach (\DbEntry\Group::listPermissions() as $p) {
            $html .= "<tr><th>$p</th>";
            foreach (\DbEntry\Group::listGroups() as $g) {
                $checked = ($g->grants($p)) ? "checked=\"checked\"" : "";
                $html .= "<td><input type=\"checkbox\" $checked name=\"GROUP_" . $g->id() . "_PERMISSION_$p\" value=\"TRUE\"></td>";
            }
            $html .= "<td><input type=\"checkbox\" name=\"GROUP_NEW_PERMISSION_$p\" value=\"TRUE\"></td>";
            $html .= "</tr>";
        }

        // delete groups
        $html .= "<tr><td></td>";
        foreach (\DbEntry\Group::listGroups() as $g) {
            $g_id = $g->id();
            if (in_array($g->name(), \DbEntry\Group::reservedGroupNames())) {
                $html .= "<td></td>";
            } else {
                $html .= "<td><button type=\"submit\" name=\"DeleteGroup\" value=\"$g_id\">" . _("Delete Group") . "</button></td>";
            }
        }
        $html .= "<td><button type=\"submit\" name=\"SAVE_ALL\" value=\"TRUE\">" . _("Save All Groups") . "</button></td>";
        $html .= "</tr>";



        $html .= "</table>";
        $html .= "</form>";



        return $html;
    }
}

?>
