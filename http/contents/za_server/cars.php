<?php

class cars extends cContentPage {

    private $CurrentBrand = NULL;

    public function __construct() {
        $this->MenuName   = _("Cars");
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_ServerContent"];
    }

    public function getHtml() {
        global $acswuiDatabase;
        $html  = '';


        // --------------------------------------------------------------------
        //                           REQUEST Data
        // --------------------------------------------------------------------

        if (array_key_exists("Brand", $_REQUEST)) {
            $this->CurrentBrand = $_REQUEST['Brand'];
        } else if (array_key_exists("Brand", $_SESSION)) {
            $this->CurrentBrand = $_SESSION['Brand'];
        }

        if ($this->CurrentBrand !== NULL) {
            $_SESSION['Brand'] = $this->CurrentBrand;
        }


        // --------------------------------------------------------------------
        //                           Brands Select
        // --------------------------------------------------------------------

        $html .= "<form method=\"post\">";

        $html .= "<select name=\"Brand\" onchange=\"this.form.submit()\">";
        foreach (Car::listBrands() as $brand) {
            if ($this->CurrentBrand === NULL) $this->CurrentBrand = $brand;
            $selected = ($brand == $this->CurrentBrand) ? "selected" : "";
            $html .= "<option value=\"$brand\" $selected>$brand</option>";
        }
        $html .= "</select>";
        $html .= "</form>";


        // --------------------------------------------------------------------
        //                           Show Cars
        // --------------------------------------------------------------------

        foreach (Car::listCars() as $c) {
            if ($this->CurrentBrand !== NULL && $c->brand() != $this->CurrentBrand) continue;
            $html .= '<div style="display:inline-block; margin: 5px;">';
            $html .= "<strong>" . $c->name() . "</strong><br>";
            if (count($c->skins()) > 0) {
                $skin = $c->skins()[0];
                $html .= $skin->htmlImg("", 300);
            }
            $html .= "</div>";
        }

        return $html;
    }
}

?>
