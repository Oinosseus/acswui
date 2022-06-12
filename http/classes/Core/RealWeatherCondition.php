<?php

declare(strict_types=1);
namespace Core;


class RealWeatherCondition {

    private $TrackLocation = NULL;
    private $Dt = NULL;  // unix timestamp
    private $Temperature = NULL;
    private $Humidity = NULL;
    private $OWMId = NULL;
    private $OWMIcon = NULL;
    private $Description = NULL;
    private $Cloudiness = NULL;
    private $WindSpeed = NULL;
    private $WindDirection = NULL;
    private $Precipitation = NULL;

    private $Weather = NULL;


    //! Please instantiate objects from factory functions
    private function __construct(\DbEntry\TrackLocation $tl) {
        $this->TrackLocation = $tl;
    }


    //! @return The cloudiness (between 0 and 1)
    public function cloudiness() : float {
        return $this->Cloudiness;
    }


    /**
     * Compares to objects for their timestamp.
     * This is intended for usort() of arrays with objects
     * @param $o1 First object
     * @param $o2 Second object
     * @return -1, 0 or +1
     */
    public static function compareTimestamp(RealWeatherCondition $o1, RealWeatherCondition $o2) : int {
        if      ($o1->Dt < $o2->Dt) return -1;
        else if ($o1->Dt > $o2->Dt) return 1;
        else return 0;
    }


    //! @return Weather Description
    public function description() : string {
        return $this->Description;
    }


    //! @return unix timestamp
    public function dt() : int {
        return $this->Dt;
    }


    //! @retutrn A RealWeatherCondition object created from an cache array
    public static function fromCacheArray(array $a) : ?RealWeatherCondition{

        // check mandatory array keys
        foreach (['TLoc', 'Dt', 'Temp', 'Hum', 'OWMId', 'OWMIcon', 'Descr', 'Cloudi', 'WS', 'WD', 'P'] as $key)
            if (!array_key_exists($key, $a)) return NULL;

        // get TrackLocation
        $tl = \DbEntry\TrackLocation::fromId($a['TLoc']);
        if ($tl === NULL) return NULL;

        // create RealWeatherCondition object
        $rwc = new RealWeatherCondition($tl);
        $rwc->Dt = (int) $a['Dt'];
        $rwc->Temperature = (int) $a['Temp'];
        $rwc->Humidity = (float) $a['Hum'];
        $rwc->OWMId = (int) $a['OWMId'];
        $rwc->OWMIcon = $a['OWMIcon'];
        $rwc->Description = $a['Descr'];
        $rwc->Cloudiness = (float) $a['Cloudi'];
        $rwc->WindSpeed = (float) $a['WS'];
        $rwc->WindDirection = (int) $a['WD'];
        $rwc->Precipitation = (float) $a['P'];

        return $rwc;
    }


    /**
     * Creates a RealWeatherCondition object from data received at a request to openweathermap
     * This decodes only one list element from the response
     * @param $tl The TrackLocation this condition is related to
     * @param $data Data array from openweathermap
     * @return A new RealWeatherCondition object (or NULL on error)
     */
    public static function fromOpenWeatherMapListElement(\DbEntry\TrackLocation $tl,
                                                         array $data
                                                         ) : ?RealWeatherCondition {
        $rwc = new RealWeatherCondition($tl);
        $rwc->Dt = (int) $data['dt'];
        $rwc->Temperature = ((float) $data['main']['temp']) - 273.15;
        $rwc->Humidity = 0.01 * (float) $data['main']['humidity'];
        $rwc->OWMId = (int) $data['weather'][0]['id'];
        $rwc->OWMIcon = $data['weather'][0]['icon'];
        $rwc->Description = $data['weather'][0]['description'];
        $rwc->Cloudiness = 0.01 * (float) $data['clouds']['all'];
        $rwc->WindSpeed = (float) $data['wind']['speed'];
        $rwc->WindDirection = (int) $data['wind']['deg'];

        $rwc->Precipitation = 0.0;
        if (array_key_exists("rain", $data)) {
            $rwc->Precipitation = (float) $data['rain']['3h'];
            $rwc->Precipitation /= 3.0;
        }

        return $rwc;
    }


    //! @return An html img tag with the weather icon
    public function htmlImg() : string {
        $html = "<img src=\"http://openweathermap.org/img/wn/{$this->OWMIcon}@2x.png\" class=\"OWMWeatherIcon\">";
        return $html;
    }


    //! @return humidityd (between 0 and 1)
    public function humidity() : float {
        return $this->Humidity;
    }


    /**
     * Creates a new RealWeatherCondition object by interrpolating between two others.
     * Extrapolation is not supported - this will return NULL
     * @param $utc_unix_timestamp timestamp for interpolation between first and second RealWeatherCondition object
     * @param $rwc1 Left or Right RealWeatherCondition object for interpolation
     * @param $rwc2 Left or Right RealWeatherCondition object for interpolation
     * @return A RealWeatherCondition object on sucess otherwise NULL
     */
    public static function interpolate(int $utc_unix_timestamp,
                                       RealWeatherCondition $rwc1,
                                       RealWeatherCondition $rwc2) : ?RealWeatherCondition {
        // ensure to have equal track conditions
        if ($rwc1->TrackLocation->id() !== $rwc2->TrackLocation->id()) {
            $id1 = $rwc1->TrackLocation->id();
            $id2 = $rwc2->TrackLocation->id();
            \Core\Log::error("Ambigous TrackLocation objects: #$id1 and #$id2!");
            return NULL;
        }

        // ensure rwc1 is left
        if ($rwc1->Dt > $rwc2->Dt) {
            $help = $rwc1;
            $rwc1 = $rwc2;
            $rwc1 = $help;
        }

        // calculate interpolation weight factors
        $f2 = ($utc_unix_timestamp - $rwc1->Dt) / ($rwc2->Dt - $rwc1->Dt);
        $f1 = ($rwc2->Dt - $utc_unix_timestamp) / ($rwc2->Dt - $rwc1->Dt);

        // create new object
        $obj = new RealWeatherCondition($rwc1->TrackLocation);
        $obj->Dt = $utc_unix_timestamp;
        $obj->Temperature = $rwc1->Temperature * $f1 + $rwc2->Temperature * $f2;
        $obj->Humidity = $rwc1->Humidity * $f1 + $rwc2->Humidity * $f2;
        $obj->OWMId = ($f1 > $f2) ? $rwc1->OWMId : $rwc2->OWMId;
        $obj->OWMIcon = ($f1 > $f2) ? $rwc1->OWMIcon : $rwc2->OWMIcon;
        $obj->Description = ($f1 > $f2) ? $rwc1->Description : $rwc2->Description;
        $obj->Cloudiness = $rwc1->Cloudiness * $f1 + $rwc2->Cloudiness * $f2;
        $obj->WindSpeed = $rwc1->WindSpeed * $f1 + $rwc2->WindSpeed * $f2;
        $obj->WindDirection = $rwc1->WindDirection * $f1 + $rwc2->WindDirection * $f2;
        $obj->Precipitation = $rwc1->Precipitation * $f1 + $rwc2->Precipitation * $f2;

        return $obj;
    }


    //! @return The OpenWeatherMap Weather-Icon name
    public function owmIcon() : string {
        return $this->OWMIcon;
    }


    //! @return The OpenWeatherMap Weather-Id
    public function owmId() : int {
        return $This->OWMId;
    }


    //! @return temperature in °C
    public function temperature() : float {
        return $this->Temperature;
    }


    //! @return The timestamp of this condition as php DateTime object (with geo local timezone)
    public function timestamp() : \DateTime {
        $dt = new \DateTime("@" . $this->Dt);
        $tz = $this->TrackLocation->geoLocation()->geoLocalTimeZone();
        $dt = $dt->setTimeZone($tz);
        return $dt;
    }


    //! @return serialized array
    public function toCacheArray() {
        $a = array();

        $a['TLoc'] = $this->TrackLocation->id();
        $a['Dt'] = $this->Dt;
        $a['Temp'] = $this->Temperature;
        $a['Hum'] = $this->Humidity;
        $a['OWMId'] = $this->OWMId;
        $a['OWMIcon'] = $this->OWMIcon;
        $a['Descr'] = $this->Description;
        $a['Cloudi'] = $this->Cloudiness;
        $a['WS'] = $this->WindSpeed;
        $a['WD'] = $this->WindDirection;
        $a['P'] = $this->Precipitation;

        return $a;
    }


    //! @return the amount of Precipitation in mm/h
    public function precipitation() : float {
        return $this->Precipitation;
    }


    //! @return the related TrackLocation
    public function trackLocation() : \DbEntry\TrackLocation {
        return $this->TrackLocation;
    }


    //! @return A \DbEntry\Weather object that represent a weather condition for AC
    public function weather() : \DbEntry\Weather {

        if ($this->Weather === NULL) {
            $this->Weather = new \DbEntry\Weather(NULL);
            $this->Weather->parameterCollection()->child("Name")->setValue(_("Real Weather"));
            $this->Weather->parameterCollection()->child("Graphic")->setValue($this->weatherGraphicValue());
            $this->Weather->parameterCollection()->child("AmbientBase")->setValue(round($this->Temperature));
            $this->Weather->parameterCollection()->child("AmbientVar")->setValue(0);
            $this->Weather->parameterCollection()->child("RoadBase")->setValue(round($this->weatherRoadHeating()));
            $this->Weather->parameterCollection()->child("RoadVar")->setValue(0);
            $this->Weather->parameterCollection()->child("WindBaseMin")->setValue(round($this->WindSpeed));
            $this->Weather->parameterCollection()->child("WindBaseMax")->setValue(round($this->WindSpeed));
            $this->Weather->parameterCollection()->child("WindDirection")->setValue(round($this->WindDirection));
            $this->Weather->parameterCollection()->child("WindDirectionVar")->setValue(0);
        }

        return $this->Weather;
    }


    //! @return Tghe value for \Parameter\ParamSpecialWeatherGraphic
    private function weatherGraphicValue() : string {
        // deterimine weather graphic
        $weather_graphic_value = "";
        switch ($this->OWMId) {

            case 200:  // thunderstorm with light rain
            case 210:  // light thunderstorm
            case 230:  // thunderstorm with light drizzle
                $weather_graphic_value = "csp_0";
                break;

            case 201:  // thunderstorm with rain
            case 211:  // thunderstorm
            case 221:  // ragged thunderstorm
            case 231:  // thunderstorm with drizzle
                $weather_graphic_value = "csp_1";
                break;

            case 202:  // thunderstorm with heavy rain
            case 212:  // heavy thunderstorm
            case 232:  // thunderstorm with heavy drizzle
                $weather_graphic_value = "csp_2";
                break;

            case 300:  //  light intensity drizzle
            case 310:  //  light intensity drizzle rain
                $weather_graphic_value = "csp_3";
                break;

            case 301:  // drizzle
            case 311:  // drizzle rain
            case 321:  // shower drizzle
            case 511:  // freezing rain
                $weather_graphic_value = "csp_4";
                break;

            case 302:  // heavy intensity drizzle
            case 312:  // heavy intensity drizzle rain
            case 313:  // shower rain and drizzle
            case 314:  // heavy shower rain and drizzle
                $weather_graphic_value = "csp_5";
                break;

            case 500:  // light rain
            case 520:  // light intensity shower rain
            case 615:  //  Light rain and snow
                $weather_graphic_value = "csp_6";
                break;

            case 501:  // moderate rain
            case 502:  // heavy intensity rain
            case 521:  // shower rain
            case 531:  // ragged shower rain
            case 616:  //  Rain and snow
                $weather_graphic_value = "csp_7";
                break;

            case 503:  // very heavy rain
            case 504:  // extreme rain
            case 522:  // heavy intensity shower rain
                $weather_graphic_value = "csp_8";
                break;

            case 600:  // light snow
            case 620:  // Light shower snow
                $weather_graphic_value = "csp_9";
                break;

            case 601:  // Snow
            case 621:  // Shower snow
                $weather_graphic_value = "csp_10";
                break;

            case 602:  // Heavy snow
            case 622:  // Heavy shower snow
                $weather_graphic_value = "csp_11";
                break;

            case 611:  // Sleet
                $weather_graphic_value = "csp_12";
                break;

            case 612:  // Light shower sleet
                $weather_graphic_value = "csp_13";
                break;

            case 613:  // Shower sleet
                $weather_graphic_value = "csp_14";
                break;

            case 800:  // clear sky
                $weather_graphic_value = "csp_15";
                break;

            case 801: // few clouds: 11-25%
                $weather_graphic_value = "csp_16";
                break;

            case 802:  // scattered clouds: 25-50%
                $weather_graphic_value = "csp_17";
                break;

            case 803:  // broken clouds: 51-84%
                $weather_graphic_value = "csp_18";
                break;

            case 804:  // overcast clouds: 85-100%
                $weather_graphic_value = "csp_19";
                break;

            case 741:  // fog
                $weather_graphic_value = "csp_20";
                break;

            case 701:  // mist
                $weather_graphic_value = "csp_21";
                break;

            case 711:  // smoke
                $weather_graphic_value = "csp_22";
                break;

            case 721:  // haze
                $weather_graphic_value = "csp_23";
                break;

            case 731:  // sand/ dust whirls
            case 751:  // sand
                $weather_graphic_value = "csp_24";
                break;

            case 761:  // dust
            case 762:  // volcanic ash
                $weather_graphic_value = "csp_25";
                break;

            case 771: // squalls
                $weather_graphic_value = "csp_26";
                break;

            case 781: // tornado
                $weather_graphic_value = "csp_27";
                break;

//                 case :
//                     $weather_graphic_value = "csp_28";
//                     break;

//                 case :
//                     $weather_graphic_value = "csp_29";
//                     break;

//                 case :
//                     $weather_graphic_value = "csp_30";
//                     break;

//                 case :
//                     $weather_graphic_value = "csp_31";
//                     break;

//                 case :
//                     $weather_graphic_value = "csp_32";
//                     break;

            default:
                \Core\Log::error("Unknwon OWM weather id '{$this->OWMId}'!");
                $weather_graphic_value = "csp_-1";
        }

        return $weather_graphic_value;
    }


    //! @return The amount of °C that the track is heated against environment temperature
    private function weatherRoadHeating() : float {

        // caluclate hour of the day
        $local_time = $this->timestamp();
        $hours_of_day = (float) $local_time->format("H");
        $hours_of_day += ((float) $local_time->format("i")) / 60.0;

        // sun intensity (depending on daytime)
        // using gaussian bell curve (norm-density without sigma spread)
        $characteristic = 30;
        $sun_intensity = exp(-1 * ($hours_of_day - 12)**2 / $characteristic );

        // reduce sun intensity at coulds
        $sun_intensity *= (1 - $this->Cloudiness * 0.8);

        // sun can heat the track twice the ambient temperature
        $road_temp_increase = $this->Temperature * $sun_intensity;

        // rain can cool down the track surface
        // 1mm rain reduce the track temperature by 1°C
        $road_temp_increase -= $this->Precipitation;

        return $road_temp_increase;
    }


    //! @return The wind direction i8n degrees
    public function windDirection() : int {
        return $this->WindDirection;
    }


    //! @return The speed of wind in m/s
    public function windSpeed() : float {
        return $this->WindSpeed;
    }
}
