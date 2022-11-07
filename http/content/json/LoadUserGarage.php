<?php

namespace Content\Json;

/**
 */
class LoadUserGarage extends \Core\JsonContent {


    public function __construct() {
        $this->requirePermission("Json");
    }


    public function getDataArray() {

        if (array_key_exists("UserId", $_REQUEST)) {
            $user = \DbEntry\User::fromId($_REQUEST['UserId']);
        } else {
            return "";
        }

        // scan CarSkins
        $query = "SELECT Id FROM CarSkins WHERE Owner = {$user->id()} ORDER By Car ASC;";
        $res = \Core\Database::fetchRaw($query);
        $html = "";
        $last_car_id = NULL;
        foreach ($res as $row) {
            $carskin = \DbEntry\CarSkin::fromId($row['Id']);

            // new heading
            if ($last_car_id === NULL || $last_car_id != $carskin->car()->id()) {
                $html .= "<h2>";
                $html .= $carskin->car()->html(NULL, TRUE, TRUE, FALSE);
                $html .= "</h2>";
            }
            $last_car_id = $carskin->car()->id();

            // dump carskin
            $html .= $carskin->html();
        }

        $data = array();
        $data[] = $html;
        return $data;
    }

}
