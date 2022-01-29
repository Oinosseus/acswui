<?php

namespace Core;

//! Internal helper class
class SessionBestTime {
    public $UserId = NULL;
    public $BestLaptime = NULL;

    public static function compare($sbt1, $sbt2) {
        if ($sbt1->BestLaptime > $sbt2->BestLaptime) return 1;
        else if ($sbt1->BestLaptime < $sbt2->BestLaptime) return -1;
        else return 0;
    }
}
