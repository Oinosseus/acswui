<?php

class groups extends cContentPage {

    public function __construct() {
        $this->MenuName    = _("Groups");
        $this->PageTitle   = _("Groups Management");
        $this->TextDomain  = "acswui";
        $this->RequireRoot = true;
    }

    public function getHtml() {

        // save data
        if (isset($_POST['SAVE_ALL'])) {
            $this->save_data();
        }

        // delet group
        if (isset($_POST['DELETE_GROUP'])) {
            $this->delete_group($_POST['DELETE_GROUP']);
        }

        // access global data
        global $acswuiUser;
        global $acswuiDatabase;

        // get groups and permissions
        $groups = $acswuiDatabase->fetch_2d_array("Groups", NULL);
        $permissions = array();
        foreach ($acswuiDatabase->fetch_column_names("Groups") as $col)
            if ($col != "Id" && $col != "Name")
                $permissions[count($permissions)] = $col;


        // initialize the html output
        $html  = "";


        $html .= "<form action=\"?action=process\" method=\"post\">";
        $html .= "<center><table>";

        // group names
        $html .= "<tr>";
        $html .= "<th>" . _("Group") . "</th>";
        foreach ($groups as $g) {
            $html .= "<td><input type=\"text\" name=\"GROUP_" . $g['Id'] . "_NAME\" value=\"" . $g['Name'] . "\"></td>";
        }
        $html .= "<td><input type=\"text\" name=\"GROUP_NEW_Name\" value=\"\"/></td>";
        $html .= "</tr>";

        // permissions
        foreach ($permissions as $p) {
            $html .= "<tr><th>$p</th>";
            foreach ($groups as $g) {
                if ($g[$p] > 0)
                    $checked = "checked=\"checked\"";
                else
                    $checked = "";
                $html .= "<td><input type=\"checkbox\" $checked name=\"GROUP_" . $g['Id'] . "_PERMISSION_$p\" value=\"TRUE\"></td>";
            }
            $html .= "<td><input type=\"checkbox\" name=\"GROUP_NEW_PERMISSION_$p\" value=\"TRUE\"></td>";
            $html .= "</tr>";
        }

        // delete groups
        $html .= "<tr><td></td>";
        foreach ($groups as $g) {
            $html .= "<td><button type=\"submit\" name=\"DELETE_GROUP\" value=\"" . $g['Id'] . "\">" . _("delete") . "</button></td>";
        }
        $html .= "<td><button type=\"submit\" name=\"SAVE_ALL\" value=\"TRUE\">" . _("save all") . "</button></td>";
        $html .= "</tr>";



        $html .= "</table></center>";
        $html .= "</form>";


        return $html;
    }

    private function save_data() {
        // access global data
        global $acswuiDatabase;

        // get groups and permissions
        $groups = $acswuiDatabase->fetch_2d_array("Groups", NULL);
        $permissions = array();
        foreach ($acswuiDatabase->fetch_column_names("Groups") as $col)
            if ($col != "Id" && $col != "Name")
                $permissions[count($permissions)] = $col;

        // update existing groups
        foreach ($groups as $g) {

            // list of fields to update
            $group_update_fields = array();

            // update name
            if (isset($_POST['GROUP_' . $g['Id'] . '_NAME'])) {
                $group_update_fields['Name'] = $_POST['GROUP_' . $g['Id'] . '_NAME'];
            }

            // update permissions
            foreach ($permissions as $p) {
                if (isset($_POST['GROUP_' . $g['Id'] . '_PERMISSION_' . $p])) {
                    $group_update_fields[$p] = 1;
                } else {
                    $group_update_fields[$p] = 0;
                }
            }

            // update database
            $acswuiDatabase->update_row("Groups", $g['Id'], $group_update_fields);
        }

        // insert new group
        if (isset($_POST['GROUP_NEW_Name']) && strlen($_POST['GROUP_NEW_Name']) > 0) {

            // list of fields to insert
            $group_insert_fields = array();
            $group_insert_fields['Name'] = $_POST['GROUP_NEW_Name'];

            // update permissions
            foreach ($permissions as $p) {
                if (isset($_POST["GROUP_NEW_PERMISSION_$p"])) {
                    $group_insert_fields[$p] = 1;
                } else {
                    $group_insert_fields[$p] = 0;
                }
            }

            // insert into database
            $acswuiDatabase->insert_row("Groups", $group_insert_fields);

        }

    }

    private function delete_group($Id) {
        global $acswuiDatabase;
        $acswuiDatabase->delete_row("Groups", $Id);
    }
}

?>
