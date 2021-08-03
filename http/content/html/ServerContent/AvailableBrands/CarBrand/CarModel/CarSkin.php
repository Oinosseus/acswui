<?php

namespace Content\Html;

class CarSkin extends \core\HtmlContent {

    private $CurrentBrand = NULL;
    private $CurrentCar = NULL;

    public function __construct() {
        parent::__construct(_("Car Skin"),  _("Car Skins"));
    }

    public function getHtml() {
        $html  = '';

        // retrieve requests
        if (array_key_exists("Id", $_REQUEST) && $_REQUEST['Id'] != "") {
            $skin = \DbEntry\CarSkin::fromId($_REQUEST['Id']);

            $brand = $skin->car()->brand();
            $html .= "<div id=\"BrandInfo\">";
            $html .= $brand->htmlImg();
            $html .= "<label>" . $brand->name() . "</label>";
            $html .= "</div>";

            $car = $skin->car();
            $html .= "<div id=\"CarInfo\">";
            $html .= "<a href=\"" . $this->url("CarModel", ['Id'=>$car->id()]) . "\">";
            $html .= "<label>" . $car->name() . "</label>";
            $html .= "</a>";
            $html .= "</div>";

            $html .= "<div id=\"SkinInfo\">";
            $html .= "<label id=\"SkinName\">" . $skin->name() . "</label>";
            $html .= "<label id=\"SkinNumber\">" . $skin->number() . "</label>";
            $html .= "<img src=\"" . $skin->previewPath() . "\" title=\"" . $skin->skin() . "\"\">";
            $html .= "</div>";


        } else {
            \Core\Log::warning("No Id parameter given!");
        }

        return $html;
    }
}

?>
