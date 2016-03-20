<?php

function getImgTrack($track_id) {

    global $acswuiDatabase;

    $res = $acswuiDatabase->fetch_2d_array("Tracks", ["Track", "Config", "Name"], ["Id"], [$track_id]);

    if (count($res) <= 0) {
        $acswuiLog->LogError("Could not find Track Id $track_id!");
        return "";
    }

    if (strlen($res[0]['Config']) > 0) {
        $path = "acs_content/tracks/" . $res[0]['Track'] . "/ui/" . $res[0]['Config'] . "/preview.png";
    } else {
        $path = "acs_content/tracks/" . $res[0]['Track'] . "/ui/preview.png";
    }

    $ret = "<img src=\"$path\">";

    return $ret;
}

function getImgCarSkin($carskin_id, $car = NULL) {

    global $acswuiDatabase;
    global $acswuiLog;

    $res = $acswuiDatabase->fetch_2d_array("CarSkins", ["Car", "Skin"], ["Id"], [$carskin_id]);

    if (count($res) <= 0) {
        $acswuiLog->LogError("Could not find CarSkin Id $carskin_id!");
        return "";
    }

    $car_id = $res[0]['Car'];
    $skin   = $res[0]['Skin'];

    # get car
    if (is_null($car)) {
        $res = $acswuiDatabase->fetch_2d_array("Cars", ["Car"], ["Id"], [$car_id]);
        if (count($res) <= 0) {
            $acswuiLog->LogError("Could not find Car Id " . $car_id . "!");
            return "";
        }

        $car = $res[0]['Car'];
    }

    $path = "acs_content/cars/$car/skins/$skin/preview.jpg";

    $ret = "<img src=\"$path\">";

    return $ret;
}

?>
