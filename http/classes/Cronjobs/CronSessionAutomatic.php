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

        // walk trhough server slots and find scheduled items
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
                $now = new \DateTime("now");
                if ($schd->start() <= $now) {
                    $this->verboseOutput("Starting SessioSchedule $schd on $ss<br>");
                    $ss->start($schd->track(),
                               $schd->serverPreset(),
                               $schd->entryList(),
                               $schd->bopMap(),
                               $schd->id());
                    $schd->setExecuted(new \DateTime("now"));
                    \Core\Discord::messageScheduleStart($ss, $schd);
                    continue;
                }

                // start practice loops
                if ($schd->getParamValue("PracticeEna")) {
                    $preset = $schd->parameterCollection()->child("PracticePreset")->serverPreset();
                    if ($preset->finishedBefore($schd->start()->sub(new \DateInterval("PT10M")))) {
                        $this->verboseOutput("Starting SessioSchedule Practice-Loop $schd on $ss<br>");

                        // reset qualifying and reace
                        $preset->parameterCollection()->child("AcServerQualifyingTime")->setValue(0, TRUE);
                        $preset->parameterCollection()->child("AcServerRaceLaps")->setValue(0, TRUE);
                        $preset->parameterCollection()->child("AcServerRaceTime")->setValue(0, TRUE);

                        // start session
                        $ss->start($schd->track(),
                                   $preset,
                                   $schd->entryList(),
                                   $schd->bopMap());
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

                            // create EntryList
                            $el = new \Core\EntryList();
                            $el->addTvCar();
                            $el->fillSkins($car_class, $track->pitboxes());
                            $el->reverse();

                            // cretae BopMap
                            $bm = new \Core\BopMap();
                            foreach ($car_class->cars() as $c) {
                                $b = $car_class->ballast($c);
                                $r = $car_class->restrictor($c);
                                $bm->update($b, $r, $c);
                            }

                            // start
                            $ss->start($track, $preset, $el, $bm);
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
     * @return A ScheduledItem object (or NULL)
     */
    private function nextScheduleItem(\Core\ServerSlot $server_slot) : ?\Compound\ScheduledItem {
        $now = new \DateTime("now");
        $now->sub(new \DateInterval("PT1M")); // ad one minute uncertainty
        $items = \Compound\ScheduledItem::listItems($server_slot, $now);
        if (count($items)) return $items[0];
        return NULL;
    }
}
