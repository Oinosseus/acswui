<?php

namespace Cronjobs;

class CronRealWeather extends \Core\Cronjob {

    public function __construct() {
        parent::__construct(\Core\Cronjob::IntervalAlways);
    }


    protected function process() {

        $max_amount_per_minute = \Core\ACswui::getParam("OpenWeatherMapRPM");

        $last_request_minute = $this->loadData("LastRequestMinute", "0000-00-00T00:00");
        $last_request_amount = (int) $this->loadData("LastRequestAmount", 0);

        // reset counter every new minute
        $this->verboseOutput("Last requested minute: $last_request_minute<br>\n");
        $current_minute = (new \DateTime("now", new \DateTimeZone("UTC")))->format("y-m-d H:i");
        $this->verboseOutput("Current request minute: $current_minute<br>\n");
        if ($current_minute != $last_request_minute) {
            $last_request_amount = 0;
        }
        $this->verboseOutput("Last requests within this minute: $last_request_amount<br>\n");

        $last_track_location_id = (int) $this->loadData("LastTrackLocationId", 0);
        $this->verboseOutput("Last requested track location #$last_track_location_id<br>\n");

        $query = "SELECT Id FROM TrackLocations WHERE Deprecated = 0 AND Id > $last_track_location_id";
        $res = \Core\Database::fetchRaw($query);
        if (count($res) == 0) {
            $last_track_location_id = 0;
        } else {

            // get all related RealWeatherCache objects
            $real_weaterh_caches = array();
            foreach ($res as $row) {
                $tl = \DbEntry\TrackLocation::fromId($row['Id']);
                $real_weaterh_caches[] = \Core\RealWeatherCache::fromTrackLocation($tl);
            }

            // at first update only when no weather conditions are available at all
            foreach ($real_weaterh_caches as $rwche) {

                // limit request amount
                if ($last_request_amount >= $max_amount_per_minute) break;

                if ($rwche->lastUpdate() == 0 ||
                    $rwche->latestConditionTimestamp() == 0 ||
                    count ($rwche->conditions()) == 0
                    ) {
                    $rwche->update();
                    $this->verboseOutput("Update real weather data for track location #{$rwche->trackLocation()->id()} / '{$rwche->trackLocation()->name()}'<br>\n");
                    $last_request_amount += 1;
                }
            }


            // update periodically
            $last_update_threshold = time() - 6*60*60;  // every 6 hours
            $latest_forecast_threshold = time() + 3*24*60*60;  // aim for 3 days forecast
            foreach ($real_weaterh_caches as $rwche) {

                // limit request amount
                if ($last_request_amount >= $max_amount_per_minute) break;

                if ($rwche->lastUpdate() < $last_update_threshold ||
                    $rwche->latestConditionTimestamp() < $latest_forecast_threshold
                   ) {

                    $real_weaterh_cache->update();
                    $this->verboseOutput("Update real weather data for track location #{$rwche->trackLocation()->id()} / '{$rwche->trackLocation()->name()}'<br>\n");
                    $last_request_amount += 1;
                }

                $last_track_location_id = $rwche->trackLocation()->id();
            }
        }

        $this->verboseOutput("Current requests within this minute: $last_request_amount<br>\n");

        $this->saveData("LastTrackLocationId", $last_track_location_id);
        $this->saveData("LastRequestMinute", $current_minute);
        $this->saveData("LastRequestAmount", $last_request_amount);
    }
}
