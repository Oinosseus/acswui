<?php

function getImgTrack($track_id) {

    global $acswuiDatabase;
    global $acswuiLog;

    $res = $acswuiDatabase->fetch_2d_array("Tracks", ["Track", "Config", "Name"], ["Id" => $track_id]);

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

function getImgCarSkin($carskin_id, $img_id = "") {
    //
    // Parameters
    // ----------
    //
    //   $carskin_id : int
    //       Id of the CarSkins table
    //
    //   $img_id : string
    //       The 'id' attribute content of the <img> tag.
    //

    global $acswuiDatabase;
    global $acswuiLog;

    $id = (strlen($img_id)) ? "id=\"$img_id\"" : "";

    $res = $acswuiDatabase->fetch_2d_array("CarSkins", ["Car", "Skin"], ["Id" => $carskin_id]);

    if (count($res) <= 0) {
        $acswuiLog->LogError("Could not find CarSkin Id $carskin_id!");
        return "<img src=\"\" $id>";
    }

    $car_id = $res[0]['Car'];
    $skin   = $res[0]['Skin'];

    # get car
    $res = $acswuiDatabase->fetch_2d_array("Cars", ["Car", "Name", "Brand"], ["Id" => $car_id]);
    if (count($res) <= 0) {
        $acswuiLog->LogError("Could not find Car Id " . $car_id . "!");
        return "<img src=\"\" $id>";
    }

    $car       = $res[0]['Car'];
    $car_name  = $res[0]['Name'];
    $car_brand = $res[0]['Brand'];

    $path = "acs_content/cars/$car/skins/$skin/preview.jpg";


    $ret = "<img src=\"$path\" $id alt=\"$car $skin\" title=\"Brand: $car_brand\nCar: $car_name ($car)\nSkin: $skin\">";

    return $ret;
}

?>
