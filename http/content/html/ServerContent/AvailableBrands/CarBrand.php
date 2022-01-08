<?php

namespace Content\Html;

class CarBrand extends \core\HtmlContent {


    public function __construct() {
        parent::__construct(_("Car Brand"),  _("Car Brand"));
        $this->requirePermission("ServerContent_Cars_View");
    }


    public function getHtml() {
        $html  = '';

        // retrieve requests
        if (array_key_exists("Id", $_REQUEST) && $_REQUEST['Id'] != "") {
            $brand = \DbEntry\CarBrand::fromId($_REQUEST['Id']);

            $html .= "<div id=\"BrandInfo\">";
            $html .= $brand->html(FALSE, FALSE, TRUE);
            $html .= "<label>" . $brand->name() . "</label>";
            $html .= "</div>";

            $html .= "<div id=\"AvailableCars\">";
            foreach ($brand->listCars() as $car) {
                $car_id = $car->id();
                $car_name = $car->name();
                $html .= $car->html();
            }
            $html .= "</div>";

        } else {
            \Core\Log::warning("No brand Id parameter given!");
        }

        return $html;
    }
}

?>
