<?php

class groups extends cContentPage {

    public function __construct() {
        $this->MenuName    = _("Groups");
        $this->PageTitle   = _("Groups Management");
        $this->TextDomain  = "acswui";
        $this->RequireRoot = true;
    }

    public function getHtml() {

        // access global data
        global $acswuiUser;
        global $acswuiDatabase;

        $groups = $acswuiDatabase->getGroups();

        // initialize the html output
        $html  = "";


        $html .="<form action=\"?action=save\" method=\"post\">";
        $html .= "<table>";

//         foreach ($acswuiDatabase->getGroups()

        // group names
        $html .= "<tr><td></td>";
        foreach ($groups as $g) {
            $html .= "<th>" . $g['Name'] . "</th>";
        }
        $html .= "<td><input type=\"text\" value=\"\"/></td>";
        $html .= "</tr>";

        // permissions
        foreach ($acswuiDatabase->getPermissions() as $p) {
            $html .= "<tr><th>$p</th>";
            foreach ($groups as $g) {
                $html .= "<th>" . $g[$p] . "</th>";
            }
            $html .= "<td></td>";
            $html .= "</tr>";
        }


        $html .= "</table>";
        $html .= "</form>";


        return $html;
    }
}

?>
