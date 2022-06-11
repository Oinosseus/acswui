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


    //! @return An html img tag with the weather icon
    public function htmlImg() : string {
        $html = "<img src=\"http://openweathermap.org/img/wn/{$this->OWMIcon}@2x.png\">";
        return $html;
    }


    //! @return humidityd (between 0 and 1)
    public function humidity() : float {
        return $this->Humidity;
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


    //! @return The timestamp of this condition as php DateTime object
    public function timestamp() : \DateTime {
        return new \DateTime("@" . $this->Dt);
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


    //! @return The wind direction i8n degrees
    public function windDirection() : int {
        return $this->WindDirection;
    }


    //! @return The speed of wind in m/s
    public function windSpeed() : float {
        return $this->WindSpeed;
    }
}
