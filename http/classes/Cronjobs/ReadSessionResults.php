<?php

namespace Cronjobs;

class ReadSessionResults extends \Core\Cronjob {

    public function __construct() {
        parent::__construct(\Core\Cronjob::IntervalSession);
    }

    protected function process() {

        // determine sessions to be checked
        $max_session_id = \Core\Cronjob::lastCompletedSession();
        $last_session_id = (int) $this->loadData("LastScannedSession", 0);
        $this->verboseOutput("Scanning $last_session_id < Session-Id <= $max_session_id<br>");

        // find all empty sessions
        $empty_session_ids = array();
        $query = "SELECT Id, ResultFile FROM Sessions WHERE Id > $last_session_id AND Id <= $max_session_id";
        $res = \Core\Database::fetchRaw($query);
        foreach ($res as $row) {
            $file = trim($row['ResultFile']);
            if (strlen($file)) {
                $this->readResultFile($row['Id'], $file);
            }
        }

        // save last checked session
        $this->saveData("LastScannedSession", $max_session_id);
    }


    private function readResultFile($session_id, $result_file) {

        // ensure to delete all previous results
        $query = "DELETE FROM SessionResults WHERE Session = $session_id";
        \Core\Database::query($query);

        // find result file
        $result_file_path = \Core\Config::AbsPathData . "/acserver/" . $result_file;
        if (!file_exists($result_file_path)) {
            \Core\Log::warning("Result file '$result_file' of Session.Id=$session_id does not exist.");
            return;
        }

        // read result file
        echo "HERE: $result_file_path<br>";
        $ret = file_get_contents($result_file_path);
        if ($ret === FALSE) {
            \Core\Log::error("Cannot read from file '$result_file_path'!");
            return;
        }
        $file_data = json_decode($ret, TRUE);
        if ($file_data == NULL) {
            \Core\Log::debug("Decoding NULL from json file '$result_file_path'.");
            return;
        }

        // parse data
        if (!array_key_exists("Result", $file_data)) {
            \Core\Log::debug("'Result' does not exist in file '$result_file_path'.");
            return;
        }
        $position = 0;
        foreach ($file_data['Result'] as $rslt) {
            $position += 1;

            // find user
            $steam64guid = $rslt['DriverGuid'];
            if (strpos($steam64guid, "kicked") !== FALSE) continue;
            $user = \DbEntry\User::fromSteam64GUID($steam64guid);
            if ($user === NULL) continue;

            // find CarSkin
            $rslt_car_id = $rslt['CarId'];
            $rslt_car_model = $file_data['Cars'][$rslt_car_id]['Model'];
            $car = \DbEntry\Car::fromModel($rslt_car_model);
            if ($car === NULL) {
                \Core\Log::error("Unkown car model '$rslt_car_model' in result file '$result_file_path'!");
                continue;
            }
            $rlst_car_skin = $file_data['Cars'][$rslt_car_id]['Skin'];
            $skin = \DbEntry\CarSkin::fromSkin($car, $rlst_car_skin);
            if ($skin === NULL) {
                \Core\Log::error("Unkown car skin '$rlst_car_skin' for car model '$rslt_car_model' in result file '$result_file_path'!");
                continue;
            }

            // stroe result
            $fields = array();
            $fields['Position'] = $position;
            $fields['Session'] = $session_id;
            $fields['User'] = $user->id();
            $fields['CarSkin'] = $skin->id();
            $fields['BestLap'] = $rslt['BestLap'];
            $fields['TotalTime'] = $rslt['TotalTime'];
            $fields['Ballast'] = $rslt['BallastKG'];
            $fields['Restrictor'] = $rslt['Restrictor'];
            \Core\Database::insert("SessionResults", $fields);
        }
    }

}
