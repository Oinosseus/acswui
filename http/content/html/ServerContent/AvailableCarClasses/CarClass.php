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

        // get requested carclass
        if (!array_key_exists("Id", $_REQUEST) && $_REQUEST['Id'] != "") {
            \Core\Log::warning("No Id parameter given!");
            return "";
        }
        $this->CarClass = \DbEntry\CarClass::fromId($_REQUEST['Id']);

        // delete carclass
        if ($this->CanEdit && array_key_exists("DeleteCarClass", $_POST)) {
            $cc = \DbEntry\CarClass::fromId($_POST['DeleteCarClass']);
            if ($cc) $cc->delete();
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

            $this->reload(["Id"=>$cc->id()]);
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
            $this->reload(["Id"=>$cc->id()]);
        }

        // show content
        if ($this->CanEdit && array_key_exists("Action", $_REQUEST) && $_REQUEST['Action'] == "AddCars") {
            return $this->showAddCars();
        } else {
            $html = "";
            $html .= $this->showCarClassOverview();
            $html .= $this->getHtmlFilterLaptimes();
            $html .= $this->gtHtmlCarsOverview();
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

        if (!$this->CarClass) return "";

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

            $html .= "<td>";
            if ($car->power() > 0) $html .= \Core\UserManager::currentUser()->formatPowerSpecific(1e3 * $car->weight() / $car->power());
            $html .= "</td>";

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
        if (!$this->CarClass) return "";

        $html = "";
        $html .= "<h1>" . _("CarClass Records") . "</h1>";
        $html .= "<button type=\"button\" carClassId=\"" . $this->CarClass->id() . "\" onclick=\"CarClassLoadRecords(this)\">" . _("Load CarClass Records") . "</button>";
        $html .= "<div id=\"CarClassRecordsList\"></div>";

        return $html;
    }


    private function gtHtmlCarsOverview() : string {
        $html = "";

        $html .= "<h1>" . _("Cars Overview") . "</h1>";
        foreach ($this->CarClass->cars() as $car) {
            $html .= $car->html($this->CarClass);
        }

        return $html;
    }


    private function getHtmlFilterLaptimes() : string {
        $html = "";
        $html .= "<h1>" . _("Filter Laptimes") . "</h1>";
        $current_user = \Core\UserManager::currentUser();

        // retrive filter vars
        $filtered_track = (array_key_exists("LaptimeFilterTrack", $_POST)) ? \DbEntry\Track::fromId((int) $_POST['LaptimeFilterTrack']) : \DbEntry\Track::getImola();
        $filtered_drivers = array();
        foreach (\DbEntry\User::listDrivers() as $user) {
            if (!array_key_exists("LaptimeFilterDrivers", $_POST) || in_array($user->id(), $_POST['LaptimeFilterDrivers'])) {
                $filtered_drivers[] = $user;
            }
        }
        $filtered_temp_amb_min  = (array_key_exists("LaptimeFilterTempAmbMin",  $_POST)) ? (int) $_POST['LaptimeFilterTempAmbMin']  : 0;
        $filtered_temp_amb_max  = (array_key_exists("LaptimeFilterTempAmbMax",  $_POST)) ? (int) $_POST['LaptimeFilterTempAmbMax']  : 50;
        $filtered_temp_road_min = (array_key_exists("LaptimeFilterTempRoadMin", $_POST)) ? (int) $_POST['LaptimeFilterTempRoadMin'] : 0;
        $filtered_temp_road_max = (array_key_exists("LaptimeFilterTempRoadMax", $_POST)) ? (int) $_POST['LaptimeFilterTempRoadMax'] : 80;
        $filtered_bop_ballast = (array_key_exists("LaptimeFilterBopBallast", $_POST)) ? (int) $_POST['LaptimeFilterBopBallast'] : 20;
        $filtered_bop_restrictor = (array_key_exists("LaptimeFilterBopRestrictor", $_POST)) ? (int) $_POST['LaptimeFilterBopRestrictor'] : 10;

        // filter
        $html .= $this->newHtmlForm("POST");
        $html .= "<div id=\"CarClassLaptimeFilter\">";

        // track
        $html .= "<table>";
        $html .= "<caption>" . _("Track") . "</caption>";
        $html .= "<tr><td><select name=\"LaptimeFilterTrack\">";
        foreach (\DbEntry\Track::listTracks() as $t) {
            $selected = ($filtered_track->id() == $t->id()) ? "selected" : "";
            $html .= "<option value=\"" . $t->id() . "\" $selected>" . $t->name() . "</option>";
        }
        $html .= "</select></td></tr>";
        $html .= "</table>";

        // driver
        $html .= "<table>";
        $html .= "<caption>" . _("Driver") . "</caption>";
        $html .= "<tr><td><select name=\"LaptimeFilterDrivers[]\" size=\"5\" multiple>";
        foreach (\DbEntry\User::listDrivers() as $user) {
            $selected = (in_array($user, $filtered_drivers)) ? "selected" : "";
            $html .= "<option value=\"{$user->id()}\" $selected>" . $user->name() . "</option>";
        }
        $html .= "</select></td></tr>";
        $html .= "</table>";

        // temperature
        $html .= "<table>";
        $html .= "<caption>" . _("Temperature") . "</caption>";
        $html .= "<tr>";
        $html .= "<td><input type=\"number\" name=\"LaptimeFilterTempAmbMin\" min=\"0\" max=\"99\" step=\"1\" value=\"$filtered_temp_amb_min\">°C ≤</td>";
        $html .= "<td>" . _("Ambient") . "</td>";
        $html .= "<td>≤ <input type=\"number\" name=\"LaptimeFilterTempAmbMax\" min=\"0\" max=\"99\" step=\"1\" value=\"$filtered_temp_amb_max\">°C</td>";
        $html .= "</tr>";
        $html .= "<tr>";
        $html .= "<td><input type=\"number\" name=\"LaptimeFilterTempRoadMin\" min=\"0\" max=\"99\" step=\"1\" value=\"$filtered_temp_road_min\">°C ≤</td>";
        $html .= "<td>" . _("Road") . "</td>";
        $html .= "<td>≤ <input type=\"number\" name=\"LaptimeFilterTempRoadMax\" min=\"0\" max=\"99\" step=\"1\" value=\"$filtered_temp_road_max\">°C</td>";
        $html .= "</tr>";
        $html .= "</table>";

        // filtered BOP
        $html .= "<table>";
        $html .= "<caption>" . _("Filtered BOP") . "</caption>";
        $html .= "<tr>";
        $html .= "<td>" . _("Ballast") . "</td>";
        $html .= "<td>≥ <input type=\"number\" name=\"LaptimeFilterBopBallast\" min=\"0\" max=\"99\" step=\"1\" value=\"$filtered_bop_ballast\">kg</td>";
        $html .= "</tr>";
        $html .= "<tr>";
        $html .= "<td>" . _("Restrictor") . "</td>";
        $html .= "<td>≥ <input type=\"number\" min=\"0\" name=\"LaptimeFilterBopRestrictor\" max=\"99\" step=\"1\" value=\"$filtered_bop_restrictor\">&percnt;</td>";
        $html .= "</tr>";
        $html .= "</table>";

        $html .= "</div>";


        // results table
        $html .= "<table>";
        $html .= "<tr>";
        $html .= "<th rowspan=\"2\" colspan=\"2\">" . _("Car") . "</th>";
        $html .= "<th colspan=\"6\">" . _("No BOP") . "</th>";
        $html .= "<th colspan=\"6\">" . _("Filtered BOP") . "</th>";
        $html .= "<th colspan=\"6\">" . _("Class BOP") . "</th>";
        $html .= "</tr>";
        $html .= "<tr>";
        for ($i=0; $i<3; ++$i) {
            $html .= "<th>" . _("Amb") . "</th>";
            $html .= "<th>" . _("Road") . "</th>";
            $html .= "<th>" . _("Bal") . "</th>";
            $html .= "<th>" . _("Rstr") . "</th>";
            $html .= "<th>" . _("Driver") . "</th>";
            $html .= "<th>" . _("Laptime") . "</th>";
        }
        $html .= "</tr>";

        // results
        if (array_key_exists("LaptimeFilter", $_POST)) {
            foreach ($this->CarClass->cars() as $car) {
                $html .= "<tr>";

                // car filter
                $key = "LaptimeFilterCar{$car->id()}";
                $car_is_requested = array_key_exists($key, $_POST) && $_POST[$key]=="TRUE";
                $checked = ($car_is_requested) ?  "checked" : "";
                $html .= "<td><input type=\"checkbox\" name=\"{$key}\" value=\"TRUE\" $checked></td>";
                $html .= "<td>{$car->name()}</td>";

                // find laps
                if ($car_is_requested) {
                    $lap_bop_none = \DbEntry\Lap::findBestLap($filtered_track, $car, $filtered_drivers,
                                                            $filtered_temp_amb_min, $filtered_temp_amb_max,
                                                            $filtered_temp_road_min, $filtered_temp_road_max,
                                                            0, 0);
                    $lap_bop_filtered = \DbEntry\Lap::findBestLap($filtered_track, $car, $filtered_drivers,
                                                                $filtered_temp_amb_min, $filtered_temp_amb_max,
                                                                $filtered_temp_road_min, $filtered_temp_road_max,
                                                                $filtered_bop_ballast, $filtered_bop_restrictor);
                    $lap_bop_class = \DbEntry\Lap::findBestLap($filtered_track, $car, $filtered_drivers,
                                                            $filtered_temp_amb_min, $filtered_temp_amb_max,
                                                            $filtered_temp_road_min, $filtered_temp_road_max,
                                                            $this->CarClass->restrictor($car), $this->CarClass->ballast($car));

                    // show laps
                    foreach ([$lap_bop_none, $lap_bop_filtered, $lap_bop_class] as $lap) {
                        if ($lap === NULL) {
                            $html .= "<td colspan=\"6\"></td>";
                        } else {
                            $html .= "<td>{$lap->session()->tempAmb()} °C</td>";
                            $html .= "<td>{$lap->session()->tempRoad()} °C</td>";
                            $html .= "<td>{$lap->ballast()} kg</td>";
                            $html .= "<td>{$lap->restrictor()} &percnt;</td>";
                            $html .= "<td>{$lap->user()->name()}</td>";
                            $html .= "<td>{$lap->html()}</td>";
                        }
                    }
                }

                $html .= "</tr>";
            }
        }
        $html .= "</table>";
        $html .= "<br><button type=\"submit\" name=\"LaptimeFilter\" value=\"TRUE\">" . _("Update Filter") . "</button>";
        $html .= "</form>";


        return $html;
    }
}
