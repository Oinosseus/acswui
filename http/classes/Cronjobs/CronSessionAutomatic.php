<?php

namespace Cronjobs;

class CronSessionAutomatic extends \Core\Cronjob {

    public function __construct() {
        parent::__construct(\Core\Cronjob::IntervalAlways);
    }

    protected function process() {

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

            // check if loop items exist for this slot
            if (!array_key_exists($ss->id(), $session_loop_ids_per_slot_id)) continue;

            // continue if slot is busy
            if ($ss->online()) continue;

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

                    // start slot
                    if ($track && $car_class && $preset) {
                        $this->verboseOutput("Starting $sl on $ss<br>");
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
