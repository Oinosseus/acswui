<?php

/**
 * Generated Json:
 * {
 *     Meta : {
 *     },
 *     Data {
 *         TrackId : {
 *             UserId : {
 *                  CarId: LapId,
 *                  ...
 *             }
 *         },
 *         ...
 *     }
 * }
 *
 *
 * The cronjob scans every lap for track records.
 * To speed things up, the last scanned lap is saved.
 * Intermediate results are dumped after each lap scan in json format.
 * So the cronjob starts execution from the last state on crahses (eg. php script timeout)
 */
class CronTrackRecords extends Cronjob {
    private $JsonPath = "";


    public function __construct() {
        global $acswuiConfig;
        parent::__construct(new DateInterval("PT1M"));
        $this->JsonPath = $acswuiConfig->AbsPathData. "/htcache/stats_track_records.json";
    }


    //! usort compare function
    private static function compareBestLaps($l1, $l2) {
        if ($l1->laptime() > $l2->laptime()) return 1;
        else if ($l1->laptime() == $l2->laptime()) return 0;
        return -1;
    }


    public function execute() {
        global $acswuiConfig, $acswuiDatabase;

        // load previous data
        $records = $this->jsonLoad();
        $records_data = $records['Data'];
        $records_meta = $records['Meta'];


        // iterate over laps
        $query = "SELECT Id FROM Laps";
        $query .= " WHERE Cuts = 0 AND Id > " . $records_meta['LastScannedLapId'];
        foreach ($acswuiDatabase->fetch_raw_select($query) as $row) {
            $lap = new Lap($row['Id']);
            $lid = $lap->id();
            $uid = $lap->user()->id();
            $cid = $lap->carSkin()->car()->id();
            $tid = $lap->session()->track()->id();

            // initialize data
            if (!array_key_exists($tid, $records_data)) {
                $records_data[$tid] = array();
            }
            if (!array_key_exists($uid, $records_data[$tid])) {
                $records_data[$tid][$uid] = array();
            }

            // add new lap record for user
            if (!array_key_exists($cid, $records_data[$tid][$uid])) {
                $records_data[$tid][$uid][$cid] = $lid;
                $this->log("New record Lap.Id=$lid");
            } else {
                $old_lap = new Lap($records_data[$tid][$uid][$cid]);
                if ($lap->laptime() < $old_lap->laptime()) {
                    $records_data[$tid][$uid][$cid] = $lid;
                    $this->log("Updated record Lap.Id=$lid");
                }
            }

            // save current state
            $records_meta['LastScannedLapId'] = (int) $lap->id();
            $records['Meta'] = $records_meta;
            $records['Data'] = $records_data;
            $this->jsonSave($records);
        }

        // save (update timestamp)
        $records['Meta'] = $records_meta;
        $records['Data'] = $records_data;
        $this->jsonSave($records);
    }


    private function jsonLoad() {

        // get last calculated records
        @ $json_string = file_get_contents($this->JsonPath);
        $json_data = ($json_string === FALSE) ? array() : json_decode($json_string, TRUE);

        // prepare meta
        if (!array_key_exists("Meta", $json_data)) {
            $json_data['Meta'] = array();
        }
        if (!array_key_exists('LastScannedLapId', $json_data['Meta'])) {
            $json_data['Meta']['LastScannedLapId'] = 0;
        }
        $json_data['Meta']['Timestamp'] = (new Datetime())->format("Y-m-d H:i:s");

        // prepare data
        if (!array_key_exists("Data", $json_data)) {
            $json_data['Data'] = array();
        }


        return $json_data;
    }


    private function jsonSave($json_data) {
        $f = fopen($this->JsonPath, 'w');
        fwrite($f, json_encode($json_data, JSON_PRETTY_PRINT));
        fclose($f);
    }
}

?>
