<?php

namespace Content\Html;

class CarClass extends \core\HtmlContent {

    private $CurrentBrand = NULL;
    private $CurrentCar = NULL;

    public function __construct() {
        parent::__construct(_("Car Class"),  _("Car CarClass"));
        $this->requirePermission("ViewServerContent_CarClasses");
    }

    public function getHtml() {
        $html  = '';

        if (!array_key_exists("Id", $_REQUEST) && $_REQUEST['Id'] != "") {
            \Core\Log::warning("No Id parameter given!");
        }

        // retrieve requests
        $cc = \DbEntry\CarClass::fromId($_REQUEST['Id']);

        $html .= "<h1>" . $cc->name() . "</h1>";

        $html .= "<div id=\"CarClassDescription\">";
        $html .= $cc->description();
        $html .= "</div>";

        $html .= "<h2>" . _("Technical Data") . "</h2>";

        $html .= "<table id=\"CarClassCars\">";
        $html .= "<tr>";
        $html .= "<th>" . _("Car") . "</th>";
        $html .= "<th>" . _("Torque") . "</th>";
        $html .= "<th>" . _("Power") . "</th>";
        $html .= "<th>" . _("Weight") . "</th>";
        $html .= "<th>" . _("Specific Power") . "</th>";
        $html .= "<th>" . _("Harmonized Power") . "</th>";
        $html .= "</tr>";

        foreach ($cc->cars() as $car) {
            $html .= "<tr>";

            $html .= "<td><a href=\"" . $car->htmlUrl() . "\">" . $car->name() . "</a></td>";
            $html .= "<td>" . \Core\HumanValue::format($car->torque(), "Nm") . "</td>";
            $html .= "<td>" . \Core\HumanValue::format($car->power(), "W") . "</td>";
            $html .= "<td>" . \Core\HumanValue::format($car->weight(), "kg") . "</td>";
            $html .= "<td>" . \Core\HumanValue::format(1e3 * $car->weight() / $car->power(), "g/W") . "</td>";
            $html .= "<td>" . \Core\HumanValue::format(1e3 * $car->weight() / $car->harmonizedPower(), "g/W") . "</td>";

            $html .= "</tr>";
        }
        $html .= "</table>";


        $html .= "<h2>" . _("Cars Overview") . "</h2>";
        foreach ($cc->cars() as $car) {
            $html .= $car->htmlImg();
        }


        return $html;
    }
}
