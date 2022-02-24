<?php

namespace Content\Html;

class CarClass extends \core\HtmlContent {

    private $CurrentBrand = NULL;
    private $CurrentCar = NULL;
    private $CanEdit = FALSE;
    private $CarClass = NULL;


    public function __construct() {
        parent::__construct(_("Car Class"),  _("Car CarClass"));
        $this->requirePermission("ServerContent_CarClasses_View");
        $this->addScript("Content_CarClass.js");
    }


    public function getHtml() {
        $this->CanEdit = \Core\UserManager::permitted("ServerContent_CarClasses_Edit");

        // delete carclass
        if ($this->CanEdit && array_key_exists("DeleteCarClass", $_POST)) {
            $cc = \DbEntry\CarClass::fromId($_POST['DeleteCarClass']);
            $cc->delete();
            return _("Car Class Deleted");
        }

        // save carclass
        if ($this->CanEdit && array_key_exists("SaveCarClass", $_POST)) {
            $cc = \DbEntry\CarClass::fromId($_POST['SaveCarClass']);

            // save name
            $key = "CarClassName";
            if (array_key_exists($key, $_POST)) {
                $cc->rename($_POST[$key]);
            }

            // save description
            $key = "CarClassDescription";
            if (array_key_exists($key, $_POST)) {
                $cc->setDescription($_POST[$key]);
            }

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

        // add cars
        if ($this->CanEdit && array_key_exists("SaveAddedCars", $_POST)) {
            $cc = \DbEntry\CarClass::fromId($_POST['SaveAddedCars']);
            foreach (\DbEntry\Car::listCars() as $car) {
                $key = "Car" . $car->id() . "Add";
                if (array_key_exists($key, $_POST)) {
                    $cc->addCar($car);
                }
            }
        }

        // get requested carclass
        if (!array_key_exists("Id", $_REQUEST) && $_REQUEST['Id'] != "") {
            \Core\Log::warning("No Id parameter given!");
            return "";
        }
        $this->CarClass = \DbEntry\CarClass::fromId($_REQUEST['Id']);

        // show content
        if ($this->CanEdit && array_key_exists("Action", $_REQUEST) && $_REQUEST['Action'] == "AddCars") {
            return $this->showAddCars();
        } else {
            $html = "";
            $html .= $this->showCarClassOverview();
            $html .= $this->showRecords();
            return $html;
        }
    }


    private function showAddCars() {
        if ($this->CanEdit !== TRUE) return "";
        $html = "";

        $html .= "<form action=\"" . $this->url(['Id'=>$this->CarClass->id()]) . "\" method=\"post\">";
        $html .= "<input type=\"hidden\" name=\"SaveAddedCars\" value=\"" . $this->CarClass->id() . "\">";
        foreach (\DbEntry\CarBrand::listBrands() as $brand) {
            $html .= "<h1>" . $brand->name() . "</h1>";
            foreach ($brand->listCars() as $car) {
                $car_img = $car->html($this->CarClass, FALSE, TRUE, TRUE);
                $valid = $this->CarClass->validCar($car);
                $html .= $this->newHtmlContentCheckbox("Car" . $car->id() . "Add", $car_img, $valid, $valid);
            }
        }
        $html .= "<br><button>" . _("Add Cars") . "</button>";
        $html .= "</form>";

        return $html;
    }


    private function showCarClassOverview() {
        $html  = '';

        if ($this->CanEdit) {
            $html .= "<form method=\"post\">";
            $html .= "<input type=\"hidden\" name=\"SaveCarClass\" value=\"" . $this->CarClass->id() . "\">";
        }

        // name
        $html .= "<h1>";
        $html .= "<div style=\"display: inline-block;\" id=\"LabelCarClassName\">" . $this->CarClass->name() . "</div>";
        if ($this->CanEdit) {
            $html .= " <div style=\"display: inline-block; cursor: pointer;\" id=\"EnableEditCarClassNameButton\">&#x270e;</div>";
        }
        $html .= "</h1>";

        // description
        $html .= "<div id=\"CarClassDescriptionHtml\" style=\"display:inline-block;\">";
        $html .= $this->parseMarkdown($this->CarClass->description());
        $html .= "</div>";
        if ($this->CanEdit) {
            $html .= " <div style=\"display: inline-block; cursor: pointer;\" id=\"EnableEditCarClassDescriptionButton\">&#x270e;</div>";
            $html .= "<textarea id=\"CarClassDescriptionMarkdown\" style=\"display:none;\" name=\"CarClassDescription\">";
            $html .= $this->CarClass->description();
            $html .= "</textarea>";
        }

        $html .= "<h2>" . _("Technical Data") . "</h2>";

        $html .= "<table id=\"CarClassCars\">";
        $html .= "<tr>";
        $html .= "<th>" . _("Car") . "</th>";
        $html .= "<th>" . _("Torque") . "</th>";
        $html .= "<th>" . _("Power") . "</th>";
        $html .= "<th>" . _("Weight") . "</th>";
        $html .= "<th>" . _("Ballast") . "</th>";
        $html .= "<th>" . _("Restrictor") . "</th>";
        $html .= "<th>" . _("Specific Power") . "</th>";
        $html .= "<th>" . _("Harmonized Power") . "</th>";
        $html .= "</tr>";

        foreach ($this->CarClass->cars() as $car) {
            $html .= "<tr>";

            $html .= "<td><a href=\"" . $car->htmlUrl($this->CarClass) . "\">" . $car->name() . "</a></td>";
            $html .= "<td>" . (new \Core\SiPrefix($car->torque()))->humanValue("N m") . "</td>";
            $html .= "<td>" . \Core\UserManager::currentUser()->formatPower($car->power()) . "</td>";
            $html .= "<td>" . \Core\UserManager::currentUser()->formatWeight($car->weight()) . "</td>";

            if ($this->CanEdit) {
                $html .= "<td><input type=\"number\" name=\"Car" . $car->id() . "Ballast\" value=\"" . $this->CarClass->ballast($car) . "\" min=\"0\" max=\"1000\"></td>";
                $html .= "<td><input type=\"number\" name=\"Car" . $car->id() . "Restrictor\" value=\"" . $this->CarClass->restrictor($car) . "\" min=\"0\" max=\"100\"></td>";
            } else {
                $html .= "<td>" . \Core\HumanValue::format($this->CarClass->ballast($car), "kg") . "</td>";
                $html .= "<td>" . \Core\HumanValue::format($this->CarClass->restrictor($car), "%") . "</td>";
            }

            $html .= "<td>" . \Core\UserManager::currentUser()->formatPowerSpecific(1e3 * $car->weight() / $car->power()) . "</td>";
            $html .= "<td>" . \Core\UserManager::currentUser()->formatPowerSpecific($this->CarClass->harmonizedPowerRatio($car)) . "</td>";

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


        $html .= "<h2>" . _("Teams") . "</h2>";
        $html .= "<ul>";
        foreach ($this->CarClass->teams() as $team) {
            $html .= "<li>" . $team->htmlName() . "</li>";
        }
        $html .= "</ul>";
        if (count($this->CarClass->teams()) == 0) {
            $html .= _("No teams in this car class");
        }


        $html .= "<h2>" . _("Cars Overview") . "</h2>";
        foreach ($this->CarClass->cars() as $car) {
            $html .= $car->html($this->CarClass);
        }


        // add cars
        if ($this->CanEdit) {
            $html .= "<br>";
            $html .= "<a href=\"" . $this->url(['Id'=>$this->CarClass->id(), 'Action'=>'AddCars']) . "\">" . _("Add Cars") . "</a>";
        }


        // delete car class
        if ($this->CanEdit) {
            $html .= "<br><br><br><br>";
            $html .= "<form method=\"post\">";
            $html .= "<input type=\"hidden\" name=\"DeleteCarClass\" value=\"" . $this->CarClass->id() . "\">";
            $html .= "<button type=\"button\" id=\"DeleteCarClassButton\">" . _("Delete Car Class") . "</button>";
            $html .= "</form>";
        }


        return $html;
    }


    private function showRecords() {
        $html = "";
        $html .= "<h1>" . _("CarClass Records") . "</h1>";
        $html .= "<button type=\"button\" carClassId=\"" . $this->CarClass->id() . "\" onclick=\"CarClassLoadRecords(this)\">" . _("Load CarClass Records") . "</button>";
        $html .= "<span id=\"CarClassRecordsList\"></span>";
        return $html;
    }
}
