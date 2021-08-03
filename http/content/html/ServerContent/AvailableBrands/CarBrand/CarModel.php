<?php

namespace Content\Html;

class CarModel extends \core\HtmlContent {

    private $CurrentBrand = NULL;
    private $CurrentCar = NULL;

    public function __construct() {
        parent::__construct(_("Car Skins"),  _("Available Skins"), 'CarModels');
    }

    public function getHtml() {
        $html  = '';

        // retrieve requests
        if (array_key_exists("Id", $_REQUEST) && $_REQUEST['Id'] != "") {
            $car = \DbEntry\Car::fromId($_REQUEST['Id']);

            $brand = $car->brand();
            $html .= "<div id=\"BrandInfo\">";
            $html .= $brand->htmlImg();
            $html .= "<label>" . $brand->name() . "</label>";
            $html .= "</div>";



            $html .= "<div id=\"AvailableSkins\">";
            foreach ($car->skins() as $skin) {
                $skin_name = $skin->skin();
                $html .= $skin->htmlImg();
            }
            $html .= "</div>";


        } else {
            \Core\Log::warning("No Id parameter given!");
        }

        return $html;
    }
}

?>
