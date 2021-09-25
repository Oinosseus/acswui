<?php

namespace Content\Html;

class CarClass extends \core\HtmlContent {

    private $CurrentBrand = NULL;
    private $CurrentCar = NULL;
    private $CanEdit = FALSE;

    public function __construct() {
        parent::__construct(_("Car Class"),  _("Car CarClass"));
        $this->requirePermission("ViewServerContent_CarClasses");
    }

    public function getHtml() {
        $this->CanEdit = \Core\UserManager::permitted("CarClass_Edit");
        $html  = '';

        if (!array_key_exists("Id", $_REQUEST) && $_REQUEST['Id'] != "") {
            \Core\Log::warning("No Id parameter given!");
        }

        // save carclass
        if ($this->CanEdit && array_key_exists("SaveCarClass", $_POST)) {
            $cc = \DbEntry\CarClass::fromId($_POST['SaveCarClass']);
            foreach ($cc->cars() as $car) {

                // delete car from class
                $key = "Car" . $car->id() . "Delete";
                if (array_key_exists($key, $_POST)) {
                    $cc->removeCar($car);
                    continue;
                }


                // save ballast
                $key = "Car" . $car->id() . "Ballast";
                $cc->setBallast($car, $_POST[$key]);

                // save restrictor
                $key = "Car" . $car->id() . "Restrictor";
                $cc->setRestrictor($car, $_POST[$key]);
            }
        }

        // retrieve requests
        $cc = \DbEntry\CarClass::fromId($_REQUEST['Id']);

        $html .= "<h1>" . $cc->name() . "</h1>";

        $html .= "<div id=\"CarClassDescription\">";
        $html .= $cc->description();
        $html .= "</div>";

        $html .= "<h2>" . _("Technical Data") . "</h2>";

        if ($this->CanEdit) {
            $html .= "<form method=\"post\">";
            $html .= "<input type=\"hidden\" name=\"SaveCarClass\" value=\"" . $cc->id() . "\">";
        }

        $html .= "<table id=\"CarClassCars\">";
        $html .= "<tr>";
        $html .= "<th>" . _("Car") . "</th>";
        $html .= "<th>" . _("Torque") . "</th>";
        $html .= "<th>" . _("Power") . "</th>";
        $html .= "<th>" . _("Weight") . "</th>";
        $html .= "<th>" . _("Ballast") . "</th>";
        $html .= "<th>" . _("Restrictor") . "</th>";
        $html .= "<th>" . _("Harmonized Power") . "</th>";
        $html .= "</tr>";

        foreach ($cc->cars() as $car) {
            $html .= "<tr>";

            $html .= "<td><a href=\"" . $car->htmlUrl($cc->restrictor($car)) . "\">" . $car->name() . "</a></td>";
            $html .= "<td>" . \Core\HumanValue::format($car->torque(), "Nm") . "</td>";
            $html .= "<td>" . \Core\HumanValue::format($car->power(), "W") . "</td>";
            $html .= "<td>" . \Core\HumanValue::format($car->weight(), "kg") . "</td>";

            if ($this->CanEdit) {
                $html .= "<td><input type=\"number\" name=\"Car" . $car->id() . "Ballast\" value=\"" . $cc->ballast($car) . "\" min=\"0\" max=\"1000\"></td>";
                $html .= "<td><input type=\"number\" name=\"Car" . $car->id() . "Restrictor\" value=\"" . $cc->restrictor($car) . "\" min=\"0\" max=\"100\"></td>";
            } else {
                $html .= "<td>" . \Core\HumanValue::format($cc->ballast($car), "kg") . "</td>";
                $html .= "<td>" . \Core\HumanValue::format($cc->restrictor($car), "%") . "</td>";
            }

            $html .= "<td>" . \Core\HumanValue::format($cc->harmonizedPowerRatio($car), "g/W") . "</td>";

            if ($this->CanEdit) {
                $html .= "<td>" . $this->newHtmlTableRowDeleteCheckbox("Car" . $car->id() . "Delete") . "</td>";
            }

            $html .= "</tr>";
        }
        $html .= "</table>";

        if ($this->CanEdit) {
            $html .= "<button type=\"submit\">" . _("Save Car Class") . "</button>";
            $html .= "</form>";
        }


        $html .= "<h2>" . _("Cars Overview") . "</h2>";
        foreach ($cc->cars() as $car) {
            $html .= $car->htmlImg($cc->restrictor($car));
        }


        return $html;
    }


}
