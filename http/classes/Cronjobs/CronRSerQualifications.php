<?php

namespace Cronjobs;

class CronRSerQualifications extends \Core\Cronjob {

    public function __construct() {
        parent::__construct(\Core\Cronjob::IntervalSession);
    }

    protected function process() {

        // list all possible splits that can deliver qualification results
        // also list all registrations from these splits
        // also list all events of the splits
        $splits = array();
        $registrations = array();
        $events = array();
        $query = "SELECT RSerSplits.Id FROM RSerSplits WHERE Start>=Executed";
        foreach (\Core\Database::fetchRaw($query) as $row_split) {
            $s = \DbEntry\RSerSplit::fromId($row_split['Id']);
            $splits[] = $s;

            if (!in_array($s->event(), $events)) $events[] = $s->event();

            foreach ($s->event()->season()->listRegistrations(NULL, TRUE) as $reg) {
                if (!in_array($reg, $registrations)) $registrations[] = $reg;
            }
        }

        foreach ($events as $event) {
            $this->verboseOutput("Scanning Series='{$event->season()->series()->name()}', Season='{$event->season()->name()}', Event={$event->order()}<br>");

            foreach ($registrations as $reg) {

                foreach ($event->listSplits() as $split) {

                    // find best lap
                    $bestlap = NULL;
                    $query = "SELECT Laps.Id FROM Laps";
                    $query .= " INNER JOIN Sessions ON Laps.Session=Sessions.Id";
                    $query .= " WHERE Laps.RSerRegistration={$reg->id()}";
                    $query .= " AND Laps.Cuts=0";
                    $query .= " AND Sessions.RSerSplit={$split->id()}";
                    $query .= " ORDER BY Laps.Laptime ASC LIMIT 1;";
                    $res = \Core\Database::fetchRaw($query);
                    if (count($res)) {
                        $lap = \DbEntry\Lap::fromId((int) $res[0]['Id']);
                        \DbEntry\RSerQualification::qualify($event, $reg, $lap);
                    }
                }
            }
        }
    }
}
