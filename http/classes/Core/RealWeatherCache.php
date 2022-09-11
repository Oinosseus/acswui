<?php

declare(strict_types=1);
namespace Core;


class RealWeatherCache {

    // accociative array of already instantiated objects
    // Key = TrackLocation::id()
    // value = RealWeatherCache
    private static $InstantiatedObjects = array();

    private $TrackLocation = NULL;
    private $Conditions = array();
    private $LastUpdate = 0;


    private function __construct(\DbEntry\TrackLocation $track_location) {
        $this->TrackLocation = $track_location;
    }


    //! @return the path to the cache file
    private function cacheFilePath() : string {
        $path = Config::AbsPathData . "/htcache/real_weather_cache/{$this->TrackLocation->id()}.json";
        return $path;
    }


    //! @return An array of available RealWeatherCondition[] objects
    public function conditions() : array {
        return $this->Conditions;
    }


    //! @return A RealWeatherCache object
    public static function fromTrackLocation(\DbEntry\TrackLocation $track_location) : RealWeatherCache {

        // check if object already exists
        if (array_key_exists($track_location->id(), RealWeatherCache::$InstantiatedObjects)) {
            return RealWeatherCache::$InstantiatedObjects[$track_location->id()];
        }

        // create new object
        $obj = new RealWeatherCache($track_location);
        RealWeatherCache::$InstantiatedObjects[$track_location->id()] = $obj;

        // read cache file
        $file_path = $obj->cacheFilePath($track_location);
        if (file_exists($file_path)) {
            $ret = file_get_contents($file_path);
            if ($ret === FALSE) {
                \Core\Log::error("Cannot read cache file '$file_path'!");

            } else {

                // decode json
                $data = json_decode($ret, TRUE);
                if ($data === NULL) {
                    \Core\Log::error("Cannot decode json in file '$file_path'!");

                } else {

                    // check if geo location is still valid
                    if ($data['Meta']['Lat'] != $obj->TrackLocation->geoLocation()->latitude() ||
                        $data['Meta']['Lon'] != $obj->TrackLocation->geoLocation()->longitude()) {
                            \Core\Log::debug("Stored weather cache is invalid for TrackLocation #{$obj->TrackLocation->id()} because geo location has changed");
                            $obj->LastUpdate = 0;

                    } else {

                        // extract meta data
                        if (array_key_exists('LastUp', $data['Meta'])) {
                            $obj->LastUpdate = (int) $data['Meta']['LastUp'];
                        }

                        // extrack RealWeatherCondition objects
                        foreach ($data['Conditions'] as $dt=>$condition_data) {
                            $rwc = RealWeatherCondition::fromCacheArray($condition_data);
                            if ($rwc === NULL) continue;
                            $obj->Conditions[] = $rwc;
                        }

                        // sort by timestamp
                        usort($obj->Conditions, "\Core\RealWeatherCondition::compareTimestamp");
                    }
                }
            }
        }

        return $obj;
    }


    //! @return RealWeatherCondition at specific point in time (NULL if not availabe)
    public function getCondition(\DateTime $dt) : ?RealWeatherCondition {

        // get unix timestamp
        $dt->setTimezone(new \DateTimeZone("UTC"));
        $dt_unix = (int) $dt->format("U");

        // find conditions before and after
        $rwc_before = NULL;
        $rwc_after = NULL;
        foreach ($this->Conditions as $rwc) {
            if ($rwc->dt() <= $dt_unix) {
                $rwc_before = $rwc;
            } else {
                $rwc_after = $rwc;
                break;
            }
        }

        // ensure if interpolation is possible
        if ($rwc_before === NULL) return NULL;
        if ($rwc_after === NULL) return NULL;

        $rwc = RealWeatherCondition::interpolate($dt_unix, $rwc_before, $rwc_after);
        return $rwc;
    }


    //! @return UNIX timestamp of last update
    public function lastUpdate() : int {
        return $this->LastUpdate;
    }


    //! @return UNIX timestamp of the latest contained RealWeatherCondition
    public function latestConditionTimestamp() : int {
        $ret = 0;

        $amount_conditions = count($this->Conditions);
        if ($amount_conditions > 0)
            $ret = $this->Conditions[$amount_conditions - 1]->dt();

        return $ret;
    }


    //! @return Request data from OpenWeatherMap and return RealWeatherCondition[]
    private function requestOpenWeatherMap() : array {
        // get API-Key
        $api_key = trim(ACswui::getParam("OpenWeatherMapApiKey"));
        if (strlen($api_key) == "") return [];

        // get geo location
        $gl = $this->TrackLocation->geoLocation();
        if ($gl === NULL) return [];
        $lat = sprintf("%0.F", $gl->latitude());
        $lon = sprintf("%0.F", $gl->longitude());

        // create request
        $url = "http://api.openweathermap.org/data/2.5/forecast?lat=$lat&lon=$lon&appid=$api_key";
        $ret = file_get_contents($url);
        if ($ret === False) {
            \Core\Log::warning("Fail to request weather data from '$url' for track location {$this->TrackLocation->id()}!");
            return [];
        }
        $data = json_decode($ret, True);

        // extract list data
        $weather_conditions = array();
        foreach ($data['list'] as $list_item) {
            $nrwc = RealWeatherCondition::fromOpenWeatherMapListElement($this->TrackLocation, $list_item);
            $weather_conditions[] = $nrwc;
        }

        return $weather_conditions;
    }


    /**
     * Store current data into cache
     */
    private function storeCache() : void {

        $cache_array = array();
        $cache_array['Meta'] = array();
        $cache_array['Conditions'] = array();

        // sanity check
        foreach ($this->Conditions as $rwc) {
            if (!($rwc instanceof RealWeatherCondition)) {
                \Core\Log::error("Type Error!");
                return;
            }

            if ($this->TrackLocation->id() != $rwc->trackLocation()->id()) {
                \Core\Log::error("Mix of different TrackLocations!");
                return;
            }

            $cache_array['Conditions'][$rwc->dt()] = $rwc->toCacheArray();
        }
        if (count($cache_array['Conditions']) == 0) return;

        // update meta data
        $cache_array['Meta']['Lat'] = $this->TrackLocation->geoLocation()->latitude();
        $cache_array['Meta']['Lon'] = $this->TrackLocation->geoLocation()->longitude();
        $cache_array['Meta']['LastUp'] = $this->LastUpdate;

        // encode as json
        $json_string = json_encode($cache_array, JSON_PRETTY_PRINT);
        if ($json_string === FALSE) {
            \Core\Log::error("Cannot encode json!");
            return;
        }

        // save to file
        $file_path = $this->cacheFilePath();
        $ret = file_put_contents($file_path, $json_string);
        if ($ret === FALSE) {
            \Core\Log::error("Cannot write cache file '$file_path'!");
        }
    }


    //! @return Accociated TrackLocation object
    public function trackLocation() : \DbEntry\TrackLocation {
        return $this->TrackLocation;
    }


    /**
     * Request weather data from OpenWeatherMap
     * Will also remove outdated RealWeatherCondition objects (older than 40h)
     */
    public function update() : void {

        // get new weather data
        $requested_conditions = $this->requestOpenWeatherMap();
        $lowest_new_timestamp = NULL;
        foreach ($requested_conditions as $nrwc) {
            if ($lowest_new_timestamp === NULL || $nrwc->dt() < $lowest_new_timestamp) {
                $lowest_new_timestamp = $nrwc->dt();
            }
        }

        // copy current weather conditions which are not outdated or replaced
        $outdate_threshold = time() - 40*60*60;
        $new_conditions = array();
        foreach ($this->Conditions as $rwc) {
            if ($rwc->dt() <= $outdate_threshold) continue;
            if ($lowest_new_timestamp !== NULL && $rwc->dt() >= $lowest_new_timestamp) continue;
            $new_conditions[] = $rwc;
        }

        // append requested conditions
        foreach ($requested_conditions as $rwc) {
            $new_conditions[] = $rwc;
        }

        // store new weather conditions
        $this->Conditions = $new_conditions;
        usort($this->Conditions, "\Core\RealWeatherCondition::compareTimestamp");
        if (count($requested_conditions) > 0) $this->LastUpdate = time();

        // save cache
        $this->storeCache();
    }
}
