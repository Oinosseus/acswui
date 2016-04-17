<?php

class aa_user extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("User");
        $this->TextDomain = "acswui";
    }

    public function getHtml() {

        // access global data
        global $acswuiUser;

        // logout
        if (isset($_POST['LOGOUT'])) {
            $acswuiUser->logout();
        }

        // initialize the html output
        $html  = "";

        // logout form
        if ($acswuiUser->IsLogged) {
            $html .= '<form id="user_logout_form" onsubmit="return false;"><center><table>';
            $html .= "<tr>";
            $html .= "<th>" . _("Login") . "</th>";
            $html .= "<td>" . $acswuiUser->Login . "</td>";
            $html .= "</tr>";
            $html .= "<tr>";
            $html .= "<td></td>";
            $html .= "<td><button type=\"submit\" name=\"LOGOUT\" value=\"TRUE\">" . _("logout") . "</button></td>";
            $html .= "</tr>";
            $html .= "</table></center></form>";

        // login form
        } else {
            $html .= '<form id="user_login_form" onsubmit="return false;"><center><table>';
            $html .= '<tr>';
            $html .= '<th>' . _("Login") . '</th>';
            $html .= '<td><input type="text" id="username" placeholder="login"/></td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<th>' . _("Password") . '</th>';
            $html .= '<td><input type="password" id="userpass" placeholder="password"/></td>';
            $html .= '</tr>';
            $html .= "<tr>";
            $html .= "<td></td>";
            $html .= '<td><input type="submit"></td>';
            $html .= '</tr>';
            $html .= '</table></center></form>';
        }

        // load java script
        $html .= '<script src="' . $this->getRelPath() . 'aa_user.js"></script>';

        return $html;
    }
}

?>
