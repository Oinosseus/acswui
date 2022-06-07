<?php

declare(strict_types=1);

namespace Core;

class GeoLocation {

    private float $Lon = 0.0;
    private float $Lat = 0.0;

    public function __construct(float $latitude, float $longitude) {
        $this->Lat = $latitude;
        $this->Lon = $longitude;
    }


    //! @return URL to openstreetmap
    public function osmUrl() : string {
        $lat = sprintf("%F", $this->Lat);
        $lon = sprintf("%F", $this->Lon);
        return "https://www.openstreetmap.org/#map=15/$lat/$lon\"";
    }


    //! @return html link to OSM with coordinates in name
    public function htmlLink() : string {

        $label = "";

        // latitude
        if ($this->Lat >= 0.0) {
            $label .= "N";
            $label .= GeoLocation::float2string($this->Lat);
        } else {
            $label .= "S";
            $label .= GeoLocation::float2string(-1 * $this->Lat);
        }

        $label .= " ";

        // longitude
        if ($this->Lon >= 0.0) {
            $label .= "E";
            $label .= GeoLocation::float2string($this->Lon);
        } else {
            $label .= "W";
            $label .= GeoLocation::float2string(-1 * $this->Lon);
        }

        return "<a href=\"{$this->osmUrl()}\">$label</a>";
    }


    //! @return string with iframe openstreetmap map embed
    public function htmlOsmEmbed() : string {

        $view_angle_lat = 0.025;
        $view_angle_long = 0.025;
        $bbox  = sprintf("%0.F%%2C%0.F%%2C%0.F%%2C%0.F",
                         $this->Lon - $view_angle_long/2,
                         $this->Lat - $view_angle_lat/2,
                         $this->Lon + $view_angle_long/2,
                         $this->Lat + $view_angle_lat/2);

        return "<iframe class=\"OsmEmbed\" src=\"https://www.openstreetmap.org/export/embed.html?bbox=$bbox&amp;layer=mapnik\"></iframe>";
    }


    /**
     * Chops a floating number into degree, minutes and seconds
     * @param $value are degrees
     * @return string
     */
    public static function float2string(float $value) : string {

        $degree = $value % 60;
        $value -= $degree;

        $value *= 60;
        $minutes = $value % 60;
        $value -= $minutes;

        $seconds = $value;

        return sprintf("%d°%d'%0.1f\"", $degree, $minutes, $seconds);
    }
}
