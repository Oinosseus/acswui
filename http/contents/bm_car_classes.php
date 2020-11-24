<?php

class bm_car_classes extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Car Classes");
        $this->TextDomain = "acswui";
        $this->PageTitle  = _("Car Classes");
        $this->RequirePermissions = ["View_ServerContent"];
        $this->CurrentCarClass = NULL;
        $this->CanEdit = FALSE;
    }

    public function getHtml() {

        // access global data
        global $acswuiDatabase;
        global $acswuiUser;
        global $acswuiLog;

        // reused variables
        $html  = '';
        $carclass_id = 0;
        $carclass_name = "";
        $carclass_cars = array(); // a list of cars that are in the car class

        // check permisstions
        if ($acswuiUser->hasPermission('CarClass_Edit')) $this->CanEdit = TRUE;


        // --------------------------------------------------------------------
        //                        Determine Requested Class
        // --------------------------------------------------------------------

        // get requested class
        if (isset($_REQUEST['CARCLASS_ID'])) {
            $this->CurrentCarClass = new CarClass((int) $_REQUEST['CARCLASS_ID']);
        } else if (isset($_SESSION['CARCLASS_ID'])) {
            $this->CurrentCarClass = new CarClass((int) $_SESSION['CARCLASS_ID']);
        }

//         // check requested car class
//         if ($carclass_id !== 'NEW_CARCLASS') {
//             $carclass_id_exists = False;
//             $carclass_id_first  = Null;
//             foreach ($acswuiDatabase->fetch_2d_array("CarClasses", ['Id', 'Name'], []) as $rs) {
//                 // check if $carclass_id exists
//                 if ($rs['Id'] === $carclass_id) {
//                     $carclass_id_exists = True;
//                     $carclass_name = $rs['Name'];
//                 }
//                 // save first existing Id
//                 if (is_null($carclass_id_first)) {
//                     $carclass_id_first = $rs['Id'];
//                 }
//             }
//
//             // set race class if request is invalid
//             if ($carclass_id_exists !== True)
//                 $carclass_id = $carclass_id_first;
//
//             // save last requested race class
//             $_SESSION['CARCLASS_ID'] = $carclass_id;
//         }



        // --------------------------------------------------------------------
        //                             SAVE ACTION
        // --------------------------------------------------------------------


        // delete car class
        if (   $this->CanEdit
            && isset($_REQUEST['ACTION'])
            && $this->CurrentCarClass !== NULL
            && $_REQUEST['ACTION'] == "DELETE") {
            $this->CurrentCarClass->delete();
            $this->CurrentCarClass = NULL;
        }

        // new car class
        if (   $this->CanEdit
            && isset($_REQUEST['ACTION'])
            && $_REQUEST['ACTION'] == "NEW") {
            $this->CurrentCarClass = CarClass::createNew(_("New Car Class Name"));
        }

        // delete car
        if ($this->CurrentCarClass !== NULL && $this->CanEdit && isset($_REQUEST['DELETE_CAR'])) {
            $car = new Car((int) $_REQUEST['DELETE_CAR']);
            $this->CurrentCarClass->removeCar($car);
        }

        // save
        if (   $this->CanEdit
            && isset($_REQUEST['ACTION'])
            && $this->CurrentCarClass !== NULL
            && $_REQUEST['ACTION'] == "SAVE") {

            // save name
            $this->CurrentCarClass->rename($_POST['CARCLASS_NAME']);

            // save ballast/restrictor
            foreach ($this->CurrentCarClass->cars() as $car) {
                $id = $car->id();
                $this->CurrentCarClass->setBallast($car, (int) $_POST["BALLAST_$id"]);
                $this->CurrentCarClass->setRestrictor($car, (int) $_POST["RESTRICTOR_$id"]);
            }

            // add cars
            foreach (Car::listCars() as $car) {
                if (!isset($_POST["ADD_CAR_" . $car->id()])) continue;
                $this->CurrentCarClass->addCar($car);
            }
        }



        // --------------------------------------------------------------------
        //                     Car Class Selection
        // --------------------------------------------------------------------

        $html .= "<form method=\"post\">";
        $html .= "<select name=\"CARCLASS_ID\" onchange=\"this.form.submit()\">";

        # list existing classes
        foreach (CarClass::listClasses() as $cc) {
            if ($this->CurrentCarClass === NULL) $this->CurrentCarClass = $cc;
            $selected = ($cc->id() == $this->CurrentCarClass->id()) ? "selected" : "";
            $html .= "<option value=\"" . $cc->id() . "\" $selected>" . $cc->name() ."</option>";
        }

//         # add new class
//         if ($this->CanEdit) {
//             # insert empty select if no classes availale
//             # workaround for creating a new class when no class is available
//             if (count(CarClass::listClasses()) == 0)
//                 $html .= "<option value=\"\" selected></option>";
//
//             # create new
//             $html .= "<option value=\"NEW_CARCLASS\">&lt;" . _("Create New Car Class") . "&gt;</option>";
//         }

        $html .= "</select>";
        $html .= "</form>";

        // add/delete class
        $html .= "<form method=\"post\">";
        if ($this->CurrentCarClass !== NULL) {
            $id = $this->CurrentCarClass->id();
            $html .= "<input type=\"hidden\" name=\"CARCLASS_ID\" value=\"$id\">";
            $html .= "<button type=\"submit\" name=\"ACTION\" value=\"DELETE\">" . _("Delete Car Class") . "</button>";
        }
        $html .= " ";
        $html .= "<button type=\"submit\" name=\"ACTION\" value=\"NEW\">" . _("Add New Car Class") . "</button>";
        $html .= "</form>";



        // --------------------------------------------------------------------
        //                        General Class Setup
        // --------------------------------------------------------------------

        $html .= "<form method=\"post\">";

        if ($this->CurrentCarClass !== NULL) {
            $id = $this->CurrentCarClass->id();
            $name = $this->CurrentCarClass->name();

            $html .= "<input type=\"hidden\" name=\"CARCLASS_ID\" value=\"$id\">";

            $html .= "<h1>" . _("General Car Class Setup") . "</h1>";

            # class name
            if ($this->CanEdit) {
                $html .= "Name: <input type=\"text\" name=\"CARCLASS_NAME\" value=\"$name\" /> ";
                $html .= "<button type=\"submit\" name=\"ACTION\" value=\"SAVE\">" . _("Save Car Class") . "</button> ";
            } else {
                $html .= "Name: <span class=\"disabled_input\">$name</span>";
            }

        }



        // --------------------------------------------------------------------
        //                           Available Cars
        // --------------------------------------------------------------------

        if ($this->CurrentCarClass !== NULL) {

            $html .= "<h1>" . _("Cars Assinged To Class") . "</h1>";

            // race class cars
            $html .= "<table id=\"available_cars\">";
            $html .= "<tr>";
            $html .= "<th>". _("Car") ."</th>";
            $html .= "<th>". _("Ballast") ."</th>";
            $html .= "<th>". _("Restrictor") ."</th>";
            $html .= "</tr>";

            foreach ($this->CurrentCarClass->cars() as $car)  {
//                 // remeber existing car
//                 $carclass_cars[] = $ccm['Car'];

                // get values
                $ballast = $this->CurrentCarClass->ballast($car);
                $restrictor = $this->CurrentCarClass->restrictor($car);

                // output row
                $car_id = $car->id();
                $html .= "<tr>";
                $html .= "<td>" . $car->name() . getImgCarSkin($car->skins()[0]->id(), $car->id()) . "</td>";
                if ($this->CanEdit) {
                    $html .= "<td><input type=\"number\"   name=\"BALLAST_$car_id\" min=\"0\" max=\"9999\" step=\"1\" size=\"5\" value=\"$ballast\"> kg</td>";
                    $html .= "<td><input type=\"number\"   name=\"RESTRICTOR_$car_id\" max=\"100\" min=\"0\" step=\"1\" size=\"3\" value=\"$restrictor\"> &percnt;</td>";
                    $html .= "<td><button type=\"submit\"  name=\"DELETE_CAR\" value=\"$car_id\" >" . _("delete") . "</button></td>";
                } else {
                    $html .= "<td><span class=\"disabled_input\">". HumanValue::format($ballast, "kg") . "</span></td>";
                    $html .= "<td><span class=\"disabled_input\">". HumanValue::format($restrictor, "%") . "</span></td>";
                }
                $html .= "</tr>";
            }

            $html .= '</table>';
        }

        // --------------------------------------------------------------------
        //                           Car Add Form
        // --------------------------------------------------------------------

        if ($this->CanEdit && $this->CurrentCarClass !== NULL) {

            $html .= "<h1>" . _("Add New Cars") . "</h1>";
            $html .= _("Select below all cars that shall be added to the car class.");

            // find all brands
            $brands = array();
            foreach (Car::listCars() as $car) {
                if (!in_array($car->brand(), $brands)) $brands[] = $car->brand();
            }
            natsort($brands);

            // view cars of each brand
            foreach ($brands as $b) {
                // separate heading for each brand
                $html .= "<h2>$b</h2>";

                // scan all cars of a brand
                foreach(Car::listCars() as $car) {
                    if ($car->brand() != $b) continue;

                    // view car
                    $html .= '<div class="car_add_option">';
                    if ($this->CurrentCarClass->validCar($car)) {
                        $html .= "<input type=\"checkbox\" id=\"car_add_option_id_" . $car->id() . "\" checked disabled></input>";
                    } else {
                        $html .= "<input type=\"checkbox\" id=\"car_add_option_id_" . $car->id() . "\" name=\"ADD_CAR_" . $car->id() . "\" value=\"TRUE\"></input>";
                    }
                    $html .= "<label for=\"car_add_option_id_" . $car->id() . "\">";
                    $html .= $car->name();
                    if (count($car->skins()) > 0) $html .= $car->skins()[0]->htmlImg();
                    $html .= "</label></div>";
                }
            }
        }



        $html .= '</form>';

        return $html;
    }
}

?>
