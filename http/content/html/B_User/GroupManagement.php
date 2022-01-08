<?php

namespace Content\Html;

class GroupManagement extends \core\HtmlContent {

    private $CanEdit = FALSE;

    public function __construct() {
        parent::__construct(_("Group Management"), _("Group Management"));
        $this->requirePermission("User_Groups_View");
    }

    public function getHtml() {
        $this->CanEdit = \Core\UserManager::loggedUser()->permitted("User_Groups_Edit");
        $html = "";

        // save groups
        if (array_key_exists("Action", $_POST) && $_POST['Action'] == "Save" && $this->CanEdit) {

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

            // delete groups
            foreach (\DbEntry\Group::listGroups() as $g) {
                $post_key = "DELETE_GROUP_" . $g->id();
                if (in_array($g->name(), \DbEntry\Group::reservedGroupNames())) continue;
                if (array_key_exists($post_key, $_POST)) {
                    $g->delete();
                }
            }
        }



        $html .= '<form action="" method="post" id="GroupManagementForm">';
        $html .= "<input type=\"hidden\" name=\"Action\" value=\"SaveGroups\">";
        $html .= "<table>";

        // group names
        $html .= "<tr>";
        $html .= "<th>" . _("Group") . "</th>";
        foreach (\DbEntry\Group::listGroups() as $g) {
            $html .= "<th>";
            if (in_array($g->name(), \DbEntry\Group::reservedGroupNames())) {
                $html .= "<input type=\"text\" name=\"GROUP_" . $g->id() . "_NAME\" value=\"" . $g->name() . "\" disabled=\"yes\">";
            } else {
                $html .= "<input type=\"text\" name=\"GROUP_" . $g->id() . "_NAME\" value=\"" . $g->name() . "\">";
            }
            $html .= "</th>";
        }
        if ($this->CanEdit)
            $html .= "<td><input type=\"text\" name=\"GROUP_NEW_NAME\" value=\"\" placeholder=\"" . _("New Group Name") . "\"/></td>";
        $html .= "</tr>";

        // permissions
        foreach (\DbEntry\Group::listPermissions() as $p) {
            $html .= "<tr><th>$p</th>";
            foreach (\DbEntry\Group::listGroups() as $g) {
                $checked = ($g->grants($p)) ? "checked=\"checked\"" : "";
                $disabled = ($this->CanEdit) ? "" : "disabled=\"yes\"";
                $html .= "<td><input type=\"checkbox\" $checked name=\"GROUP_" . $g->id() . "_PERMISSION_$p\" value=\"TRUE\" $disabled></td>";
            }
            if ($this->CanEdit)
                $html .= "<td><input type=\"checkbox\" name=\"GROUP_NEW_PERMISSION_$p\" value=\"TRUE\"></td>";
            $html .= "</tr>";
        }

        // delete groups
        if ($this->CanEdit) {
            $html .= "<tr>";
            $html .= "<td>" . _("Delete Group") . "</td>";
            foreach (\DbEntry\Group::listGroups() as $g) {
                $html .= "<td>";
                $html .= $this->newHtmlTableColumnDeleteCheckbox("DELETE_GROUP_" . $g->id());
                $html .= "</td>";
            }
            $html .= "</tr>";
        }



        $html .= "</table>";
        $html .= "<br><button type=\"submit\" name=\"Action\" value=\"Save\">" . _("Save") . "</button>";
        $html .= "</form>";



        return $html;
    }
}

?>
