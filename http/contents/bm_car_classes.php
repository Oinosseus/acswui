<?php

class bm_car_classes extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Car Classes");
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_ServerContent"];
    }

    public function getHtml() {
        $html = "";

        foreach (CarClass::listClasses() as $cc) {

            $html .= "<div class=\"CarClassBox\">";
            $html .= "<label>" . $cc->name() . "</label>";
            $html .= $cc->htmlImg("", 200);
            $html .= "</div>";
        }

        return $html;
    }
}

?>
