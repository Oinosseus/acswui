<?php

namespace Content\Html;

class CarModel extends \core\HtmlContent {

    private $CurrentBrand = NULL;
    private $CurrentCar = NULL;

    public function __construct() {
        parent::__construct(_("Car Model"),  _("Car Model"));
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

            $html .= "<h1>" . $car->name() . "</h1>";

            $html .= "<table id=\"CarModelInformation\">";
            $html .= "<caption>" . _("General Info") . "</caption>";
            $html .= "<tr><th>" . _("Brand") . "</th><td><a href=\"?HtmlContent=CarBrand&Id=" . $car->brand()->id() . "\">" . $car->brand()->name() . "</a></td></tr>";
            $html .= "<tr><th>" . _("Name") . "</th><td>" . $car->name() . "</td></tr>";
            $html .= "<tr><th>" . _("Weight") . "</th><td>" . \Core\HumanValue::format($car->weight(), "kg") . "</td></tr>";
            $html .= "<tr><th>" . _("Torque") . "</th><td>" . \Core\HumanValue::format($car->torque(), "Nm") . "</td></tr>";
            $html .= "<tr><th>" . _("Power") . "</th><td>" . \Core\HumanValue::format($car->power(), "W") . "</td></tr>";
            $html .= "<tr><th>" . _("Specific Power") . "</th><td>" . \Core\HumanValue::format(1e3 * $car->weight() / $car->power(), "g/W") . "</td></tr>";
            $html .= "</table>";

            $html .= "<table id=\"CarModelRevision\">";
            $html .= "<caption>" . _("Revision Info") . "</caption>";
            $html .= "<tr><th>" . _("Database Id") . "</th><td>". $car->id() . "</td></tr>";
            $html .= "<tr><th>AC-Directory</th><td>content/cars/" . $car->model() . "</td></tr>";
            $html .= "<tr><th>" . _("Deprecated") . "</th><td>". (($car->deprecated()) ? _("yes") : ("no")) . "</td></tr>";
            $html .= "</table>";

            $html .= "<div id=\"CarModelTorquePowerChart\">";
            $html .= $car->htmlTorquePowerSvg();
            $html .= "</div>";

            $html .= "<div id=\"CarModelDescription\">";
            $html .= $car->description();
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
