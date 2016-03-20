<?php

class cars extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Cars");
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_ServerContent"];
    }

    public function getHtml() {

        // access global data
        global $acswuiDatabase;

        // initialize the html output
        $html  = "";

        foreach($acswuiDatabase->fetch_2d_array("Cars", ["Id", "Car", "Brand", "Name"], [], [], "Name") as $c) {
            $html .= '<div>';
            $html .= "<strong>" . $c["Name"] . "</strong>";
            $skins = $acswuiDatabase->fetch_2d_array("CarSkins", ['Id'], ['Car'], [$c['Id']]);
            if (count($skins) > 0) $html .= getImgCarSkin($skins[0]['Id']);
            $html .= "</div>";
        }

        return $html;
    }
}

?>
