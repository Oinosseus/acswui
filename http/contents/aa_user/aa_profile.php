<?php
//  aa_profile

class aa_profile extends cContentPage {

    private $CurrentUser = NULL;

    public function __construct() {
        $this->MenuName   = _("Profile");
        $this->PageTitle  = "Driver Profile";
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_Profile"];
    }


    public function getHtml() {
        global $acswuiUser, $acswuiDatabase;;

        if ($acswuiUser->IsLogged !== TRUE) return "";

        $html = "";


        // --------------------------------------------------------------------
        //                         Process Forms
        // --------------------------------------------------------------------

        if (array_key_exists("Action", $_REQUEST) && $_REQUEST['Action'] == "Save") {
            $acswuiUser->user()->setColor($_REQUEST['Color']);
            $acswuiUser->user()->setPrivacy($_REQUEST['Privacy']);
        }



        // --------------------------------------------------------------------
        //                            Options
        // --------------------------------------------------------------------

        $html .= "<form method=\"post\">";

        // privacy
        $html .= _("Pirvacy") . ": ";
        $html .= "<select name=\"Privacy\">";
        $value = 0;
        $select = ($acswuiUser->user()->privacy() == $value) ? "selected" : "";
        $html .= "<option value=\"$value\" $select>Private (No personal information visible)</option>";
        $value = 1;
        $select = ($acswuiUser->user()->privacy() == $value) ? "selected" : "";
        $html .= "<option value=\"$value\" $select>Community (Personal information only visible to logged users)</option>";
        $value = 2;
        $select = ($acswuiUser->user()->privacy() == $value) ? "selected" : "";
        $html .= "<option value=\"$value\" $select>Public (Personal information visible to anyone)</option>";
        $html .= "</select>";
        $html .= "<br>";

        $html .= _("Personal Color") . ": ";
        $html .= "<input type=\"color\" name =\"Color\" value=\"" . $acswuiUser->user()->color() . "\">";
        $html .= "<br>";

        $html .= "<input type=\"hidden\" name=\"Action\" value=\"Save\">";
        $html .= "<button type=\"submit\">" . _("Save Personal Data") . "</button>";
        $html .= "</form>";


        return $html;
    }
}

?>
