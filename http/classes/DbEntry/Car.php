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
    private $Deprecated = NULL;
    private $Torque = NULL;
    private $TorqueCurve = NULL;
    private $Power = NULL;
    private $PowerCurve = NULL;
    private $PowerHarmonized = NULL;
    private $Weight = NULL;
    private $MaxRpm = NULL;
    private $CarClasses = NULL;


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


    //! @return A list of all CarClass objects, this car is included
    public function classes() {
        if ($this->CarClasses === NULL) {
            $this->CarClasses = array();
            $query = "SELECT DISTINCT CarClass FROM CarClassesMap WHERE Car = " . $this->id();
            foreach (\Core\Database::fetchRaw($query) as $row) {
                $this->CarClasses[] = CarClass::fromId($row['CarClass']);
            }
        }
        return $this->CarClasses;
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



    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @param $model The name of the content/cars/$model directory
     * @return A Car object (or NULL)
     */
    public static function fromModel(string $model) {
        $model = \Core\Database::escape($model);
        $query = "SELECT Id FROM Cars WHERE Car = '$model';";
        $res = \Core\Database::fetchRaw($query);
        if (count($res) == 0) {
            return NULL;
        } else if (count($res) == 1) {
            return parent::getCachedObject("Cars", "Car", $res[0]['Id']);
        } else {
            \Core\Log::error("Ambigous cars with model '$model'!");
            return NULL;
        }
    }



    /**
     * @param $restrictor An optional applied restrictor
     * @return calculating power [W], averaged from peak torque revolutions to peap power revolutions
     */
    public function harmonizedPower(int $restrictor = 0) {

        if ($this->PowerHarmonized === NULL) {
            $this->PowerHarmonized = array();
        }

        if (!array_key_exists($restrictor, $this->PowerHarmonized)) {
            $this->PowerHarmonized[$restrictor] = 0;

            // determine rpm at peak torque and peak power
            $peak_torque = 0;
            $rpm_peak_torque = 0;
            foreach ($this->torqueCurve($restrictor) as [$rpm, $trq]) {
                if ($trq > $peak_torque) {
                    $peak_torque = $trq;
                    $rpm_peak_torque = $rpm;
                }
            }

            // determine rpm at peak torque and peak power
            $peak_power = 0;
            $rpm_peak_power = 0;
            foreach ($this->powerCurve($restrictor) as [$rpm, $pwr]) {
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
            foreach ($this->powerCurve($restrictor) as [$rpm, $pwr]) {
                if ($rpm >= $rpm_peak_torque) {
                    if ($first_rpm === NULL) $first_rpm = $last_rpm;
                    $segment_rpm = $rpm - $last_rpm;
                    $this->PowerHarmonized[$restrictor] +=  $segment_rpm * $last_pwr; // square since last rpm
                    $this->PowerHarmonized[$restrictor] += $segment_rpm * ($pwr - $last_pwr) / 2;  // triangle section
                }

                $last_rpm = $rpm;
                $last_pwr = $pwr;
                if ($rpm > $rpm_peak_power) break;
            }
            if ($first_rpm !== NULL) $this->PowerHarmonized[$restrictor] /= $last_rpm - $first_rpm;
            else $this->PowerHarmonized[$restrictor] = 0;

//             echo "Peak Torque = $peak_torque @$rpm_peak_torque<br>";
//             echo "Peak Power = $peak_power @$rpm_peak_power<br>";
//             echo "Harmonized Power = " . $this->PowerHarmonized[$restrictor] . " @($first_rpm to $last_rpm)<br>";

            $this->PowerHarmonized[$restrictor] *= 1e3;
        }

        return $this->PowerHarmonized[$restrictor];
    }


    /**
     * @param $carclass A carclass for which this car is linked (optional)
     * @param $include_link Include a link
     * @param $show_label Include a label
     * @param $show_img Include a preview image
     * @return Html content for this object
     */
    public function html(\DbEntry\CarClass $carclass = NULL, bool $include_link = TRUE, bool $show_label = TRUE, bool $show_img = TRUE) {

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

        $html = "";

        if ($show_label) $html .= "<label for=\"$img_id\">$car_name</label>";

        if ($show_img) {
            if ($preview_path !== NULL) {
                $html .= "<img class=\"HoverPreviewImage\" src=\"$preview_path\" id=\"$img_id\" alt=\"$car_name\" title=\"$car_name\">";
            } else {
                $html .= "<br>No skin for " . $this . " found!<br>";
            }
            }

        if ($include_link) {
            $html = "<a href=\"" . $this->htmlUrl($carclass) . "\">$html</a>";
        }

        $html = "<div class=\"DbEntryHtml\">$html</div>";

        return $html;
    }


    /**
     * @param $carclass A carclass for which this car is linked (optional)
     * @return The URL to the HTML view page for this car
     */
    public function htmlUrl(\DbEntry\CarClass $carclass = NULL) {

        $url = "index.php?HtmlContent=CarModel&Id=" . $this->id();
        if ($carclass !== NULL) {
            $restrictor = $carclass->restrictor($this);
            $url .= "&Restrictor=$restrictor";
        }
        return $url;
    }


    /**
     * @param $restrictor Current restrictor level
     * @return An svg image xml string
     */
    public function htmlTorquePowerSvg(int $restrictor = 0) {
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

        $data = $this->torqueCurve($restrictor);
        $plot = new \Svg\DataPlot($data, "Torque", "PlotRestrictedTorque");
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

        $data = $this->powerCurve($restrictor);
        $plot = new \Svg\DataPlot($data, "Power", "PlotRestrictedPower");
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


    //! @return The maximum RPM of this car (can be NULL
    public function maxRpm() {
        if ($this->MaxRpm === NULL) {

            foreach ($this->powerCurve() as [$rpm, $power]) {
                if ($this->MaxRpm === NULL || $rpm > $this->MaxRpm)
                        $this->MaxRpm = $rpm;
            }

            foreach ($this->torqueCurve() as [$rpm, $power]) {
                if ($this->MaxRpm === NULL || $rpm > $this->MaxRpm)
                    $this->MaxRpm = $rpm;
            }
        }

        return $this->MaxRpm;
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


    /**
     * @param $restrictor Current restrictor level
     * @return Array of (revolution, power) value pairs
     */
    public function powerCurve(int $restrictor = 0) {

        // cache torqwue with no restrictor
        if ($this->PowerCurve === NULL) {
            $this->PowerCurve = array();
            $this->PowerCurve[0] = json_decode($this->loadColumn("PowerCurve"));
        }

        // determine restricted torque
        if (!array_key_exists($restrictor, $this->PowerCurve)) {
            $this->PowerCurve[$restrictor] = array();
            foreach($this->PowerCurve[0] as [$rpm, $pwr]) {
                $pwr *= $this->restrictorFactor($restrictor, $rpm);
                $this->PowerCurve[$restrictor][] = [$rpm, $pwr];
            }
        }

        return $this->PowerCurve[$restrictor];
    }


    /**
     * @param $inculde_deprecated If set to TRUE, also deprectaed skins are listed (Default: False)
     * @param $owner If set, only cars which are owned by this user will be listed
     * @return A List of according CarSkin objects
     */
    public function skins($inculde_deprecated=FALSE, User $owner = NULL) {

        $query = "SELECT Id FROM CarSkins WHERE Car = " . $this->id();
        if ($inculde_deprecated !== TRUE) $query .= " AND Deprecated = 0";
        if ($owner !== NULL) $query .= " AND Owner = {$owner->id()}";

        $res = \core\Database::fetchRaw($query);

        // extract values
        $skin_list = array();
        foreach ($res as $row) {
            $id = (int) $row['Id'];
            $skin_list[] = CarSkin::fromId($id);
        }

        return $skin_list;
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


    /**
     * @param $restrictor Current restrictor level
     * @return Array of (revolution, torque) value pairs
     */
    public function torqueCurve(int $restrictor = 0) {

        // cache torqwue with no restrictor
        if ($this->TorqueCurve === NULL) {
            $this->TorqueCurve = array();
            $this->TorqueCurve[0] = json_decode($this->loadColumn("TorqueCurve"));
        }

        // determine restricted torque
        if (!array_key_exists($restrictor, $this->TorqueCurve)) {
            $this->TorqueCurve[$restrictor] = array();
            foreach($this->TorqueCurve[0] as [$rpm, $trq]) {
                $trq *= $this->restrictorFactor($restrictor, $rpm);
                $this->TorqueCurve[$restrictor][] = [$rpm, $trq];
            }
        }

        return $this->TorqueCurve[$restrictor];
    }


    //! @return The weight of the car in kg
    public function weight() {
        if ($this->Weight === NULL) {
            $this->Weight = (int) $this->loadColumn("Weight");
        }
        return $this->Weight;
    }


    /**
     * Calculates a factor that reduces power/torque because of an restricor.
     * @param $restrictor The restrictor in [%]
     * @param $rpm revolution per minute
     */
    public function restrictorFactor(int $restrictor, int $rpm) {

        if ($restrictor < 0 || $restrictor > 100) {
            \Core\Log::error("Invalid restrictor value '$restrictor'!");
        }

        $restrictor *= 0.3;

        if ($this->maxRpm() !== NULL) {
            $restrictor *= exp($rpm / $this->maxRpm() - 1.0);
        }

        $restrictor = 1.0 - $restrictor / 100.0;

        return $restrictor;
    }

}
