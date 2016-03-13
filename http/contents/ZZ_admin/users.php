<?php

class users extends cContentPage {

    public function __construct() {
        $this->MenuName    = _("Users");
        $this->PageTitle   = _("Users Management");
        $this->TextDomain  = "acswui";
        $this->RequireRoot = true;
    }

    public function getHtml() {

        // save data
        if (isset($_POST['SAVE_ALL'])) {
            $this->save_all();
        }

        // access global data
        global $acswuiUser;
        global $acswuiDatabase;

        // get users
        $users = $acswuiDatabase->fetch_2d_array("Users", NULL);
        $groups = $acswuiDatabase->fetch_2d_array("Groups", NULL);


        // initialize the html output
        $html  = "";
        $html .= "<form action=\"?action=process\" method=\"post\">";
        $html .= "<center><table>";

        // table header
        $html .= "<tr>";
        $html .= "<th>" . _("Login") . "</th>";
        $html .= "<th>" . _("Password") . "</th>";
        $html .= "<th>Steam64GUID</th>";
        foreach ($groups as $g) {
            $html .= "<th>" . $g['Name'] . "</th>";
        }
        $html .= "</tr>";

        // settings
        foreach ($users as $u) {
            $html .= "<tr>";
            $html .= "<th><input type=\"text\" name=\"USER_" . $u["Id"] . "_LOGIN\" value=\"" . $u['Login'] . "\"></th>";
            $html .= "<th><input type=\"password\" name=\"USER_" . $u["Id"] . "_PASSWD\" value=\"\"></th>";
            $html .= "<th><input type=\"text\" name=\"USER_" . $u["Id"] . "_GUID\" value=\"" . $u['Steam64GUID'] . "\"></th>";
            $usergroups = $this->get_group_ids_of_user($u["Id"]);
            foreach ($groups as $g) {
                if (in_array($g['Id'], $usergroups)) {
                    $checked ="checked=\"checked\"";
                } else {
                    $checked ="";
                }
                $html .= "<th><input type=\"checkbox\" name=\"USER_" . $u["Id"] . "_GROUP_" . $g['Id'] . "\" value=\"TRUE\" $checked></th>";
            }
            $html .= "</tr>";
        }

        // new user
        $html .= "<tr>";
        $html .= "<th><input type=\"text\" name=\"NEWUSER_LOGIN\" value=\"\"></th>";
        $html .= "<th><input type=\"password\" name=\"NEWUSER_PASSWD\" value=\"\"></th>";
        $html .= "<th><input type=\"text\" name=\"NEWUSER_GUID\" value=\"\"></th>";
        foreach ($groups as $g) {
            $html .= "<th><input type=\"checkbox\" name=\"NEWUSER_GROUP_" . $g['Id'] . "\" value=\"TRUE\"></th>";
        }
        $html .= "</tr>";

        // delete groups
        $html .= "<tr><td colspan=\"" . (3 + count($groups)) . "\">";
        $html .= "<button type=\"submit\" name=\"SAVE_ALL\" value=\"TRUE\">" . _("save all") . "</button>";
        $html .= "</td></tr>";



        $html .= "</table></center>";
        $html .= "</form>";


        return $html;
    }

    private function save_all() {
        // access global data
        global $acswuiUser;
        global $acswuiDatabase;

        // get users
        $users = $acswuiDatabase->fetch_2d_array("Users", NULL);
        $groups = $acswuiDatabase->fetch_2d_array("Groups", NULL);

        // save existing users
        foreach ($users as $u) {

            // update user info
            $user_update_fields = array();
            if (isset($_POST["USER_" . $u["Id"] . "_LOGIN"])) {
                $user_update_fields['Login'] = $_POST["USER_" . $u["Id"] . "_LOGIN"];
            }
            if (isset($_POST["USER_" . $u["Id"] . "_PASSWD"])) {
                $user_update_fields['Password'] = password_hash($_POST["USER_" . $u["Id"] . "_PASSWD"], PASSWORD_BCRYPT);
            }
            if (isset($_POST["USER_" . $u["Id"] . "_GUID"])) {
                $user_update_fields['Steam64GUID'] = $_POST["USER_" . $u["Id"] . "_GUID"];
            }
            $acswuiDatabase->update_row("Users", $u['Id'], $user_update_fields);

            // update groups
            $user_groups = $this->get_group_ids_of_user($u['Id']);
            foreach ($groups as $g) {

                // set group membership
                if (isset($_POST["USER_" . $u["Id"] . "_GROUP_" . $g['Id']])) {
                    if (!in_array($g['Id'], $user_groups)) {
                        $map = array();
                        $map['User']  = $u['Id'];
                        $map['Group'] = $g['Id'];
                        $acswuiDatabase->insert_row("UserGroupMap",$map);
                    }

                // delete group membership
                } else {
                    if (in_array($g['Id'], $user_groups)) {
                        $ugm = $acswuiDatabase->fetch_2d_array("UserGroupMap", ['Id'], ['User', 'Group'], [$u['Id'], $g['Id']]);
                        foreach ($ugm as $x) {
                            $acswuiDatabase->delete_row("UserGroupMap", $x['Id']);
                        }
                    }
                }
            }
        }

        // create new user
        if (isset($_POST['NEWUSER_LOGIN']) && strlen($_POST['NEWUSER_LOGIN']) > 0 &&
            isset($_POST['NEWUSER_PASSWD']) && strlen($_POST['NEWUSER_PASSWD']) > 0) {

            // check if Login not already exists
            if (count($acswuiDatabase->fetch_2d_array("Users", ['Id'], ['Login'], [$_POST['NEWUSER_LOGIN']])) == 0) {

                // set user info
                $new_user_fields = array();
                $new_user_fields['Login'] = $_POST['NEWUSER_LOGIN'];
                $new_user_fields['Password'] = password_hash($_POST['NEWUSER_PASSWD'], PASSWORD_BCRYPT);
                $new_user_fields['Steam64GUID'] = "";
                if (isset($_POST["NEWUSER_GUID"])) {
                    $new_user_fields['Steam64GUID'] = $_POST["NEWUSER_GUID"];
                }
                $new_user_id = $acswuiDatabase->insert_row("Users", $new_user_fields);

                // set groups
                foreach ($groups as $g) {

                    // set group membership
                    if (isset($_POST["NEWUSER_GROUP_" . $g['Id']])) {
                        $map = array();
                        $map['User']  = $new_user_id;
                        $map['Group'] = $g['Id'];
                        $acswuiDatabase->insert_row("UserGroupMap",$map);
                    }
                }
            }
          }
    }

    private function get_group_ids_of_user($user_id) {
        global $acswuiDatabase;
        $ret = array();
        $ugm = $acswuiDatabase->fetch_2d_array("UserGroupMap", ["Group"], ["User"], [$user_id]);
        foreach ($ugm as $x) {
            $ret[count($ret)] = $x['Group'];
        }
//         echo "<br>User=$user_id ";
//         print_r($ret);
        return $ret;
    }


}

?>
