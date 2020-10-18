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
        $html  = '';

        // find all brands
        $brands = array();
        foreach ($acswuiDatabase->fetch_2d_array("Cars", ["Brand"], [], [], "Brand") as $b) {
            if (!in_array($b['Brand'], $brands)) $brands[count($brands)] = $b['Brand'];
        }

        // view cars of each brand
        foreach ($brands as $b) {
            $html .= "<h1>$b</h1></br>";
            foreach($acswuiDatabase->fetch_2d_array("Cars", ["Id", "Car", "Brand", "Name"], ['Brand'], [$b], "Name") as $c) {
                $html .= '<div style="display:inline-block; margin: 5px;">';
                $html .= "<strong>" . $c["Name"] . "</strong><br>";
                $skins = $acswuiDatabase->fetch_2d_array("CarSkins", ['Id', 'Skin'], ['Car'], [$c['Id']]);
//                 if (count($skins) > 0) $html .= getImgCarSkin($skins[0]['Id'], $c['Car']);
                if (count($skins) > 0) $html .= '<img src="acs_content/cars/' . $c['Car'] . '/skins/' . $skins[0]['Skin'] . '/preview.jpg">';
                $html .= "</div>";
            }
        }

        return $html;
    }
}

?>
