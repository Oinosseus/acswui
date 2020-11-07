<?php

class chpwd extends cContentPage {

    public function __construct() {
        $this->MenuName    = _("Password");
        $this->PageTitle   = _("Change Password");
        $this->TextDomain  = "acswui";
        $this->RequireRoot = false;
        $this->RequirePermissions = ["ChangePassword"];
    }

    public function getHtml() {
        global $acswuiUser;
        global $acswuiDatabase;

        $html = "";

        // save data
        if (isset($_POST['ACTION']) && $_POST['ACTION'] == "CHANGE_PASWORD") {
            $password_current = $_POST['PASSWORD_CURRENT'];
            $password_new = $_POST['PASSWORD_NEW'];
            $password_confirm = $_POST['PASSWORD_CONFIRM'];

            if ($acswuiUser->confirmPassword($password_current) !== TRUE) {
                $html .= "ERROR: Wrong password!";

            } else if ($password_new !== $password_confirm) {
                $html .= "ERROR: New passwords do not match!";

            } else {
                $password_hash = password_hash($password_new, PASSWORD_BCRYPT);
                $acswuiDatabase->update_row("Users", $acswuiUser->Id, ['Password'=>$password_hash]);
                $html .= "Password changed";
            }
        }

        // form
        $html .= "<form action=\"#\" method=\"post\"><table>";
        $html .= "<tr><td>Current Password</td><td><input type=\"password\" name=\"PASSWORD_CURRENT\" /></td></tr>";
        $html .= "<tr><td>New Password</td><td><input type=\"password\" name=\"PASSWORD_NEW\" /></td></tr>";
        $html .= "<tr><td>Confirm New Password</td><td><input type=\"password\" name=\"PASSWORD_CONFIRM\" /></td></tr>";
        $html .= "<tr><td></td><td><button type=\"submit\" name=\"ACTION\" value=\"CHANGE_PASWORD\" />Save</button></td></tr>";
        $html .= "</table></form>";


        return $html;
    }

}

?>
