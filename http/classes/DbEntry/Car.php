<?php

namespace DbEntry;

/**
 * Cached wrapper to car databse Cars table element
 */
class Car extends DbEntry {

    // local cache
    private $Model = NULL;
    private $Name = NULL;
    private $Brand = NULL;
    private $Skins = NULL;
    private $Deprecated = NULL;
    private $Torque = NULL;
    private $TorqueCurve = NULL;
    private $Power = NULL;
    private $PowerCurve = NULL;
    private $PowerHarmonized = NULL;
    private $Weight = NULL;


    /**
     * Construct a new object
     * @param $id Database table id
     */
    public function __construct(int $id) {
        parent::__construct("Cars", $id);
    }


    //! @return User friendly brand name of the car
    public function brand() {
        if ($this->Brand === NULL) {
            $this->Brand = CarBrand::fromId($this->loadColumn("Brand"));
        }
        return $this->Brand;
    }


    //! @return TRUE when this car is deprected
    public function deprecated() {
        if ($this->Deprecated === NULL)
            $this->Deprecated = ($this->loadColumn('Deprecated') == 0) ? FALSE : TRUE;
        return $this->Deprecated;
    }


    //! @return Description of the car_id
    public function description() {
        return $this->loadColumn("Description");
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) {
        return parent::getCachedObject("Cars", "Car", $id);
    }



    //! @return calculating power [W], averaged from peak torque revolutions to peap power revolutions
    public function harmonizedPower() {

        if ($this->PowerHarmonized === NULL) {
            $this->PowerHarmonized = 0;

            // determine rpm at peak torque and peak power
            $peak_torque = 0;
            $rpm_peak_torque = 0;
            foreach ($this->torqueCurve() as [$rpm, $trq]) {
                if ($trq > $peak_torque) {
                    $peak_torque = $trq;
                    $rpm_peak_torque = $rpm;
                }
            }

            // determine rpm at peak torque and peak power
            $peak_power = 0;
            $rpm_peak_power = 0;
            foreach ($this->powerCurve() as [$rpm, $pwr]) {
                if ($pwr > $peak_power) {
                    $peak_power = $pwr;
                    $rpm_peak_power = $rpm;
                }
            }

            // expect that peak of torque is always before peak of power
            if ($rpm_peak_torque > $rpm_peak_power) {
                \Core\Log::error("Calculating harmonizedPower() does not work for " . $this);
            }

            // calculate harmonized average power by segmented integration
            $last_rpm = 0;
            $last_pwr = 0;
            $first_rpm = NULL;
            foreach ($this->powerCurve() as [$rpm, $pwr]) {
                if ($rpm >= $rpm_peak_torque) {
                    if ($first_rpm === NULL) $first_rpm = $last_rpm;
                    $segment_rpm = $rpm - $last_rpm;
                    $this->PowerHarmonized +=  $segment_rpm * $last_pwr; // square since last rpm
                    $this->PowerHarmonized += $segment_rpm * ($pwr - $last_pwr) / 2;  // triangle section
                }

                $last_rpm = $rpm;
                $last_pwr = $pwr;
                if ($rpm > $rpm_peak_power) break;
            }
            $this->PowerHarmonized /= $last_rpm - $first_rpm;

//             echo "Peak Torque = $peak_torque @$rpm_peak_torque<br>";
//             echo "Peak Power = $peak_power @$rpm_peak_power<br>";
//             echo "Harmonized Power = " . $this->PowerHarmonized . " @($first_rpm to $last_rpm)<br>";

            $this->PowerHarmonized *= 1e3;
        }

        return $this->PowerHarmonized;
    }

    //! @return Html img tag containing preview image
    public function htmlImg() {

        $car_id = $this->id();
        $car_name = $this->name();
        $car_model = $this->model();
        $img_id = "CarModel$car_id";

        // try to find first available CarSkin
        $skins = $this->skins();
        $preview_path = NULL;
        $hover_path = NULL;
        if (count($skins)) {
            $skin = $skins[0];
            $preview_path = \Core\Config::RelPathHtdata . "/htmlimg/car_skins/" . $skin->id() . ".png";
            $hover_path = \Core\Config::RelPathHtdata . "/htmlimg/car_skins/" . $skin->id() . ".hover.png";
        }

        $html = "<a class=\"CarModelLink\" href=\"" . $this->htmlUrl() . "\">";
        $html .= "<label for=\"$img_id\">$car_name</label>";
        if ($preview_path !== NULL) {
            $html .= "<img src=\"$preview_path\" id=\"$img_id\" alt=\"$car_name\" title=\"$car_name\">";
        } else {
            $html .= "<br>No skin for " . $this . " found!<br>";
        }
        $html .= "</a>";

        $html .= "<script>";
        $html .= "var e = document.getElementById('$img_id');";

        # show different hover image
        $html .= "e.addEventListener('mouseover', function() {";
        $html .= "this.src='$hover_path';";
        $html .= "});";

        # show track;
        $html .= "e.addEventListener('mouseout', function() {";
        $html .= "this.src='$preview_path';";
        $html .= "});";

        $html .= "</script>";

        return $html;
    }


    //! @return The URL to the HTML view page for this car
    public function htmlUrl() {
        return "index.php?HtmlContent=CarModel&Id=" . $this->id();
    }


    //! @return An svg image xml string
    public function htmlTorquePowerSvg() {
        $svg = new \Svg\XYChart();

        // torque axis
        $yax_trq = new \Svg\YAxis("YAxisTorque", _("Torque") . " [Nm]");
        $yax_trq->setYTick(50);
        $yax_trq->setSideLeft();
        $svg->addYAxis($yax_trq);

        // torque plot
        $data = $this->torqueCurve();
        $plot = new \Svg\DataPlot($data, "Torque", "PlotTorque");
        $yax_trq->addPlot($plot);

        // power axis
        $yax_pwr = new \Svg\YAxis("YAxisPower", _("Power") . " kW");
        $yax_pwr->setSideRight();
        $yax_pwr->setYTick(50);
        $svg->addYAxis($yax_pwr);

        // power plot
        $data = $this->powerCurve();
        $plot = new \Svg\DataPlot($data, "Power", "PlotPower");
        $yax_pwr->addPlot($plot);

        $ax_x = new \Svg\XAxis("XAxisRevolutions", _("Revolutions") . " [RPM]");
        $ax_x->setXTick(1000);
        $svg->setXAxis($ax_x);

        return $svg->drawHtml("Torque / Power Chart", "CarTorquePowerChart", 0.1, 1);
    }


    /**
     * @param $inculde_deprecated If set to TRUE, also deprectaed items are listed (Default: False)
     * @return An array of all available Car objects, ordered by name
     */
    public static function listCars($inculde_deprecated=FALSE) {

        // query db
        $where = array();
        if ($inculde_deprecated !== TRUE) $where['Deprecated'] = 0;
        $res = \core\Database::fetch("Cars", ["Id"], $where);

        // extract values
        $carlist = array();
        foreach ($res as $row) {
            $id = (int) $row['Id'];
            $carlist[] = Car::fromId($id);
        }

        return $carlist;
    }


    //! @return model name of the car
    public function model() {
        if ($this->Model === NULL) $this->Model = $this->loadColumn("Car");
        return $this->Model;
    }


    //! @return User friendly name of the car
    public function name() {
        if ($this->Name === NULL) $this->Name = $this->loadColumn("Name");
        return $this->Name;
    }


    //! @return Maximum power in [W]
    public function power() {
        if ($this->Power === NULL) {
            $this->Power = 0;
            foreach ($this->powerCurve() as [$rpm, $pwr]) {
                $pwr *= 1000;
                if ($pwr > $this->Power) $this->Power = $pwr;
            }
        }
        return $this->Power;
    }


    //! @return Array of (revolution, power) value pairs
    public function powerCurve() {
        if ($this->PowerCurve === NULL) {
            $this->PowerCurve = json_decode($this->loadColumn("PowerCurve"));
        }

        return $this->PowerCurve;
    }


    /**
     * @param $inculde_deprecated If set to TRUE, also deprectaed skins are listed (Default: False)
     * @return A List of according CarSkin objects
     */
    public function skins($inculde_deprecated=FALSE) {

        // update cache
        if ($this->Skins == NULL) {

            $query = "SELECT Id FROM CarSkins WHERE Car = " . $this->id();
            if ($inculde_deprecated !== TRUE) $query .= " AND Deprecated = 0";

            $res = \core\Database::fetchRaw($query);

            // extract values
            $this->Skins = array();
            foreach ($res as $row) {
                $id = (int) $row['Id'];
                $this->Skins[] = CarSkin::fromId($id);
            }
        }

        return $this->Skins;
    }


    //! @return Maximum torque in [Nm]
    public function torque() {
        if ($this->Torque === NULL) {
            $this->Torque = 0;
            foreach ($this->torqueCurve() as [$rpm, $trq]) {
                if ($trq > $this->Torque) $this->Torque = $trq;
            }
        }
        return $this->Torque;
    }


    //! @return Array of (revolution, torque) value pairs
    public function torqueCurve() {
        if ($this->TorqueCurve === NULL) {
            $this->TorqueCurve = json_decode($this->loadColumn("TorqueCurve"));
        }

        return $this->TorqueCurve;
    }


    //! @return The weight of the car in kg
    public function weight() {
        if ($this->Weight === NULL) {
            $this->Weight = (int) $this->loadColumn("Weight");
        }
        return $this->Weight;
    }

}
