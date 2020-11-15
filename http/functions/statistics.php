<?php

function stats_driver_best_lap($user_id, $track_id, $cars_id_list) {
    // return the best lap of a user on a certain track with one of the cars in list
    // If no best lap is found, Null is returned.
    // Else an array with keys LapId, Laptime, Grip, Timestamp, Car is returned
    global $acswuiDatabase;

    $laptime = Null;
    $grip = Null;
    $timestamp = Null;
    $car = Null;
    $lapid = Null;

    $query = "SELECT Laps.Id, Laps.Laptime, Laps.Grip, Laps.Timestamp, Laps.CarSkin, CarSkins.Car FROM `Laps`";
    $query .= " INNER JOIN Sessions ON Laps.Session=Sessions.Id";
    $query .= " INNER JOIN CarSkins ON Laps.CarSkin=CarSkins.Id";
    $query .= " WHERE Sessions.Track=$track_id AND Laps.User=$user_id AND Laps.Cuts=0 ORDER BY Laps.Id ASC;";
    foreach ($acswuiDatabase->fetch_raw_select($query) as $row) {

        // ingore not requested cars
        if (!in_array($row['Car'], $cars_id_list)) continue;

        if ($laptime === Null || $row['Laptime'] < $laptime) {
            $laptime = $row['Laptime'];
            $grip = $row['Grip'];
            $timestamp = $row['Timestamp'];
            $car = $row['Car'];
            $lapid = $row['Id'];
        }
    }

    if ($laptime === Null) {
        return Null;
    } else {
        $ret = array();
        $ret['Laptime'] = $laptime;
        $ret['Grip'] = $grip;
        $ret['Timestamp'] = $timestamp;
        $ret['Car'] = $car;
        $ret['LapId'] = $lapid;
        return $ret;
    }
}

?>
