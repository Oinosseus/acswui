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
        $query = "SELECT RSerSplits.Id FROM RSerSplits INNER JOIN RSerEvents ON RSerEvents.Id=RSerSplits.Event WHERE Start>=Executed AND RSerEvents.Season!=0;";
        foreach (\Core\Database::fetchRaw($query) as $row_split) {
            $s = \DbEntry\RSerSplit::fromId($row_split['Id']);
            $splits[] = $s;

            if (!in_array($s->event(), $events)) $events[] = $s->event();

            foreach ($s->event()->season()->listRegistrations(NULL, FALSE) as $reg) {
                if (!in_array($reg, $registrations)) $registrations[] = $reg;
            }
        }

        // update qualifications for each event
        foreach ($events as $event) {
            $this->verboseOutput("Scanning Series='{$event->season()->series()->name()}', Season='{$event->season()->name()}', Event={$event->order()}<br>");

            foreach ($registrations as $reg) {

                $bop_bal = $reg->bopBallast();
                $bop_res = $reg->bopRestrictor();

                foreach ($event->listSplits() as $split) {

                    // find best lap
                    // $bestlap = NULL;
                    $query = "SELECT Laps.Id FROM Laps";
                    $query .= " INNER JOIN Sessions ON Laps.Session=Sessions.Id";
                    $query .= " WHERE Laps.RSerRegistration={$reg->id()}";
                    $query .= " AND Sessions.Track={$event->track()->id()}";
                    $query .= " AND Sessions.ServerPreset={$event->season()->series()->parameterCollection()->child('SessionPresetQual')->serverPreset()->id()}";
                    $query .= " AND Laps.Cuts=0";
                    $query .= " AND Laps.Ballast>=$bop_bal";
                    $query .= " AND Laps.Restrictor>=$bop_res";
                    $query .= " AND Sessions.RSerSplit={$split->id()}";
                    $query .= " ORDER BY Laps.Laptime ASC LIMIT 1;";
                    $res = \Core\Database::fetchRaw($query);

                    if (count($res)) {  // update qualification
                        $lap = \DbEntry\Lap::fromId((int) $res[0]['Id']);
                        \DbEntry\RSerQualification::qualify($event, $reg, $lap);

                    } else {  // remove existing qualifications if not matching laps found
                        $qual = $reg->getQualification($event);
                        if ($qual) $qual->delete();
                    }
                }
            }
        }
    }
}
