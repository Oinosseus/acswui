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

    public function __toString() {
        return "GeoLocation({$this->label()})";
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


    /**
     * Create an GeoLocation object from an geo-url
     * @param $geo_url The geo-url eg: "geo:44.3421,11.7117?z=15" (zoom is ignored and is optional)
     * @return A new GeoLocation object (or NULL if not able to parse)
     */
    public static function fromGeoUrl(string $geo_url) : ?GeoLocation {

        // find end of resource specifier ':'
        $pos_rsc_end = strpos($geo_url, ":");
        if ($pos_rsc_end === False) return NULL;

        // find separator of latitude and longitude ','
        $pos_lat_long_sep = strpos($geo_url, ",", $pos_rsc_end);
        if ($pos_lat_long_sep === False) return NULL;

        // find begin of zoom
        $pos_zoom_start = strpos($geo_url, ",", $pos_lat_long_sep);

        // extract latitude
        $pos_lat_start = $pos_rsc_end + 1;
        $len_lat = $pos_lat_long_sep - $pos_lat_start - 1;
        $lat = (float) substr($geo_url, $pos_lat_start, $len_lat);

        // extract longitude
        $pos_lon_start = $pos_lat_long_sep + 1;
        $len_lon = ($pos_zoom_start === False) ? strlen($geo_url) - $pos_lon_start - 1 : $pos_zoom_start - $pos_lon_start - 1;
        $lon = (float) substr($geo_url, $pos_lon_start, $len_lon);

        return new GeoLocation($lat, $lon);
    }


    /**
     * This does NOT return the correct local time zone,
     * but an offset depending on the geological longitude.
     * @return The DateTimeZone defined by hours and minutes
     */
    public function geoLocalTimeZone() : \DateTimeZone {

        // get longitude
        $lon = $this->longitude();

        // restrict to [-180, +180]
        $lon += 180;
        $lon = $lon % 360;
        $lon -= 180;

        // calculate geo-UTC offset in minutes
        $minutes = round(12*60 * $lon / 180);
        $sign = ($minutes < 0) ? "-" : "+";
        $minutes = ($minutes < 0) ? -1 * $minutes : $minutes;

        // extract single minutes and hours
        $m = $minutes % 60;
        $h = ($minutes - $m) / 60;

        return new \DateTimeZone($sign . sprintf("%02d%02d", $h, $m));
    }


    //! @return A human readable label of the coordinates
    public function label() : string {
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

        return $label;
    }


    //! @return The latitude in degree as float
    public function latitude() : float {
        return $this->Lat;
    }


    //! @return The longitude in degree as float
    public function longitude() : float {
        return $this->Lon;
    }


    //! @return html link to OSM with coordinates in name
    public function htmlLink() : string {
        return "<a href=\"{$this->osmUrl()}\">{$this->label()}</a>";
    }


    /**
     * @param $marker If True, a marker is added in the middle
     * @return string with iframe openstreetmap map embed
     */
    public function htmlOsmEmbed(bool $marker = True) : string {

        $view_angle_lat = 0.025;
        $view_angle_long = 0.025;
        $bbox  = sprintf("%0.F%%2C%0.F%%2C%0.F%%2C%0.F",
                         $this->Lon - $view_angle_long/2,
                         $this->Lat - $view_angle_lat/2,
                         $this->Lon + $view_angle_long/2,
                         $this->Lat + $view_angle_lat/2);


        $marker_url = "";
        if ($marker) {
            $marker_url .= sprintf("&amp;marker=%0.F%%2C%0.F",
                                   $this->Lat,
                                   $this->Lon);
        }

        return "<iframe class=\"OsmEmbed\" src=\"https://www.openstreetmap.org/export/embed.html?bbox=$bbox&amp;layer=mapnik$marker_url\"></iframe>";
    }


    //! @return URL to openstreetmap
    public function osmUrl() : string {
        $lat = sprintf("%F", $this->Lat);
        $lon = sprintf("%F", $this->Lon);
        return "https://www.openstreetmap.org/#map=15/$lat/$lon\"";
    }
}
