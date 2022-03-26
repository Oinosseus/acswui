<?php

namespace Cronjobs;

class CronSessionAutomatic extends \Core\Cronjob {

    public function __construct() {
        parent::__construct(\Core\Cronjob::IntervalAlways);
    }

    protected function process() {

        // check if session automatic is disabled
        if (!\Core\ACswui::getParam("SessionAutomatic")) return;

        // find session loop items per server slot ID
        $session_loop_ids_per_slot_id = array();
        foreach (\DbEntry\SessionLoop::listLoops() as $sl) {
            if ($sl->serverSLot() === NULL) continue;
            $ssid = $sl->serverSLot()->id();
            if (!array_key_exists($ssid, $session_loop_ids_per_slot_id)) $session_loop_ids_per_slot_id[$ssid] = array();
            $session_loop_ids_per_slot_id[$ssid][] = $sl->id();
        }

        // walk trhough server slots and find session loops
        foreach (\Core\ServerSlot::listSlots() as $ss) {
            $ssid = $ss->id();

            // continue if slot is busy
            if ($ss->online()) continue;


            ////////////////////
            // Session Schedule

            // check for scheduled sessions
            $schd = $this->nextScheduleItem($ss);
            if ($schd) {

                // start scheduled execution
                $now = \Core\Core::now();
                if ($schd->start() <= $now) {
                    $this->verboseOutput("Starting SessioSchedule $schd on $ss<br>");
                    $ss->start($schd->track(),
                               $schd->carClass(),
                               $schd->serverPreset(),
                               $schd->entryList(),
                               $schd->mapBallasts(),
                               $schd->mapRestrictors(),
                               $schd->id());
                    $schd->setExecuted(\Core\Core::now());
                    continue;
                }

                // start practice loops
                if ($schd->getParamValue("PracticeEna")) {
                    $preset = $schd->parameterCollection()->child("PracticePreset")->serverPreset();
                    if ($preset->finishedBefore($schd->start()->sub(new \DateInterval("PT10M")))) {
                        $this->verboseOutput("Starting SessioSchedule Practice-Loop $schd on $ss<br>");

                        // reset qualifying and reace
                        $preset->parameterCollection()->child("AcServerQualifyingTime")->setValue(0);
                        $preset->parameterCollection()->child("AcServerRaceLaps")->setValue(0);
                        $preset->parameterCollection()->child("AcServerRaceTime")->setValue(0);

                        // start session
                        $ss->start($schd->track(),
                                   $schd->carClass(),
                                   $preset,
                                   $schd->entryList(),
                                   $schd->mapBallasts(),
                                   $schd->mapRestrictors());
                        continue;
                    }
                }
            }


            /////////////////
            // Session Loops

            // check if loop items exist for this slot
            if (!array_key_exists($ss->id(), $session_loop_ids_per_slot_id)) continue;

            // ensure loop items are ordered
            $loop_ids = $session_loop_ids_per_slot_id[$ssid];
            sort($loop_ids);

            // determine last executed loop item
            $last_loop_id = $this->loadData("LastSessionLoopIdOnSlot$ssid", 0);
            for ($i = 0; $i < count($loop_ids); ++$i) {
                if ($loop_ids[$i] > $last_loop_id) {

                    // unpack
                    $sl = \DbEntry\SessionLoop::fromId($loop_ids[$i]);
                    $track = $sl->track();
                    $car_class = $sl->carClass();
                    $preset = $sl->serverPreset();

                    // check if enabled
                    if (!$sl->enabled()) continue;

                    // check to not overlap next schedule item
                    if (!$schd || $preset->finishedBefore($schd->start()->sub(new \DateInterval("PT10M")))) {

                        // start slot
                        if ($track && $car_class && $preset) {
                            $this->verboseOutput("Starting SessionLoop $sl on $ss<br>");
                            $sl->setLastStart(new \DateTime("now"));
                            $ss->start($track, $car_class, $preset);
                        }

                        // set next loop if
                        if (($i + 1) == count($loop_ids)) {
                            $this->saveData("LastSessionLoopIdOnSlot$ssid", 0);  // reset if last element
                        } else {
                            $this->saveData("LastSessionLoopIdOnSlot$ssid", $loop_ids[$i]);
                        }
                        break;
                    }
                }
            }
        }
    }


    /**
     * Returns the next schedule item that shall be executed
     * @param $server_slot The requested server slot for the next item (can be NULL)
     * @return A SessionSchedule object (or NULL)
     */
    private function nextScheduleItem(\Core\ServerSlot $server_slot) {

        // create query
        $now = \Core\Core::now();
        $now->sub(new \DateInterval("PT1M")); // ad one minute uncertainty
        $now_str = \Core\Database::dateTime2timestamp($now);
        $query = "SELECT Id FROM SessionSchedule WHERE Executed < Start AND Start >= '$now_str'";
        if ($server_slot) {
            $query .= " AND Slot = {$server_slot->id()}";
        }
        $query .= "  ORDER BY Start ASC LIMIT 1;";

        // get result
        $res = \Core\Database::fetchRaw($query);
        if (count($res) > 0) return \DbEntry\SessionSchedule::fromId($res[0]['Id']);

        return NULL;
    }
}
