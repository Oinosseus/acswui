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

            $html .= "<h1>" . $car->name() . "</h1>";

            $html .= "<table id=\"CarModelInformation\">";
            $html .= "<caption>" . _("General Info") . "</caption>";
            $html .= "<tr><th>" . _("Brand") . "</th><td><a href=\"?HtmlContent=CarBrand&Id=" . $car->brand()->id() . "\">" . $car->brand()->name() . "</a></td></tr>";
            $html .= "<tr><th>" . _("Name") . "</th><td>" . $car->name() . "</td></tr>";
            $html .= "</table>";

            $html .= "<table id=\"CarModelRevision\">";
            $html .= "<caption>" . _("Revision Info") . "</caption>";
            $html .= "<tr><th>" . _("Database Id") . "</th><td>". $car->id() . "</td></tr>";
            $html .= "<tr><th>AC-Directory</th><td>content/cars/" . $car->model() . "</td></tr>";
            $html .= "<tr><th>" . _("Deprecated") . "</th><td>". (($car->deprecated()) ? _("yes") : ("no")) . "</td></tr>";
            $html .= "</table>";

            # calculate and torque/power table data
            $tbldata = array();
            $trqcrv = $car->torqueCurve();
            $pwrcrv = $car->powerCurve();
            $trq_idx = 0;
            $pwr_idx = 0;
            while (TRUE) {

                $rev = 0;

                // check next torque data
                $next_trq_rev = 0;
                $next_trq_val = 0;
                if ($trq_idx < count($trqcrv)) {
                    $next_trq_rev = $trqcrv[$trq_idx][0];
                    $next_trq_val = $trqcrv[$trq_idx][1];
                }

                // check next power data
                $next_pwr_rev = 0;
                $next_pwr_val = 0;
                if ($pwr_idx < count($pwrcrv)) {
                    $next_pwr_rev = $pwrcrv[$pwr_idx][0];
                    $next_pwr_val = $pwrcrv[$pwr_idx][1];
                }

                if ($next_trq_rev < $next_pwr_rev) {
                    $tbldata[] = [$next_trq_rev, $next_trq_val, ""];
                    ++$trq_idx;
                } else if ($next_trq_rev > $next_pwr_rev) {
                    $tbldata[] = [$next_trq_rev, "", $next_pwr_val];
                    ++$pwr_idx;
                } else if ($next_trq_rev == $next_pwr_rev){
                    $tbldata[] = [$next_trq_rev, $next_trq_val, $next_pwr_val];
                    ++$pwr_idx;
                    ++$trq_idx;
                }

                // break
                if (($trq_idx >= count($trqcrv)) && ($pwr_idx >= count($pwrcrv))) {
                    break;
                }
            }

            # dump torque/power table
            $html .= "<table id=\"CarModelCurveTbl\">";
            $html .= "<caption>" . _("Torque/Power Curve") . "</caption>";
            $html .= "<tr><th>" . _("Power") . "</th>";
            foreach ($tbldata as $t) {
                $html .= "<td>" . $t[2] . "</td>";
            }
            $html .= "</tr>";
            $html .= "<tr><th>" . _("Torque") . "</th>";
            foreach ($tbldata as $t) {
                $html .= "<td>" . $t[1] . "</td>";
            }
            $html .= "</tr>";
            $html .= "<tr><th>" . _("Revolutions") . "</th>";
            foreach ($tbldata as $t) {
                $html .= "<td>" . $t[0] . "</td>";
            }
            $html .= "</tr>";
            $html .= "</table>";



//             $html .= "<table id=\"CarModelTorque\">";
//             $html .= "<caption>" . _("Torque Curve") . "</caption>";
//             $html .= "<tr><th>" . _("Revolutions") . "</th><td>". _("Torque") . "</td></tr>";
//             foreach ($car->torqueCurve() as $tc) {
//                 $rev = $tc[0];
//                 $trq = $tc[1];
//                 $html .= "<tr><td>$rev</td><td>$trq</td></tr>";
//
//             }
//             $html .= "</table>";
//
//             $html .= "<table id=\"CarModelPower\">";
//             $html .= "<caption>" . _("Power Curve") . "</caption>";
//             $html .= "<tr><th>" . _("Revolutions") . "</th><td>". _("Power") . "</td></tr>";
//             foreach ($car->powerCurve() as $tc) {
//                 $rev = $tc[0];
//                 $pwr = $tc[1];
//                 $html .= "<tr><td>$rev</td><td>$pwr</td></tr>";
//
//             }
//             $html .= "</table>";
//
//             $html .= "<div id=\"CarModelDescription\">";
//             $html .= nl2br(htmlentities($car->description()));
//             $html .= "</div>";

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
