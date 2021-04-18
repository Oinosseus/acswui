<?php

class CronSessionAutomatic extends Cronjob {


    public function __construct() {
        parent::__construct(new DateInterval("PT1S"));
    }


    public function execute() {

        // find next scheduled session for each slot
        $schedules = array();  // key=slot-id, value=SessionSchedule
        foreach (ServerSlot::listSlots() as $sslot) {
            $schedules[$sslot->id()] = NULL;
        }
        foreach (SessionSchedule::listSchedules() as $ss) {
            if ($ss->isOverdue()) continue; // ignore schedules on overdue
            $slot_id = $ss->slot()->id();
            if ($schedules[$slot_id] === NULL) $schedules[$slot_id] = $ss;
        }

//         // debug dump
//         foreach ($schedules as $ss) {
//             if ($ss == NULL) continue;
//             $id = $ss->id();
//             $name = $ss->name();
//             $slot = $ss->slot()->name();
//             $this->log("HERE: Next Schedule ID$id '$name' on slot '$slot'");
//         }

        // check automatic starts for each slot
        foreach (ServerSlot::listSlots() as $sslot) {

            $ss = $schedules[$sslot->id()];

            // check if slot is already in use
            if ($sslot->online()) {

                // write error message when schedule cannot be startet
                if ($ss !== NULL && $ss->isDue()) {
                    global $acswuiLog;
                    $msg = "Cannot start ServerSchedule.Id=" . $ss->id();
                    $msg .= " because ServerSlot.Id=" . $sslot->id();
                    $msg .= " is already running!";
                    $acswuiLog->logError($msg);
                }

                continue;
            }

            // check if a session schedule item exists
            $can_start_next_queue_item = TRUE;
            if ($ss !== NULL) {

                // check if scheduled item needs to start now
                if ($ss->isDue()) {
                    $can_start_next_queue_item = FALSE;
                    $this->log("Starting ServerSchedule.Id=" . $ss->id());
                    $sslot->start($ss->preset(),
                                  $ss->carClass(),
                                  $ss->track(),
                                  $ss->seatOccupations());
                    $ss->setExecuted();
                    Webhooks::scheduleServerStart($ss);

                // check if enough time to start next queue item
                } else {

                    $sq = SessionQueue::next($sslot, FALSE);
                    if ($sq !== NULL) {

                        // calculate when session queue item would end (with 10Min maring)
                        $sq_duration = round($sq->preset()->durationMax($sq->track()));
                        $sq_end = new DateTime();
                        $sq_end->add(new DateInterval("PT$sq_duration" . "S"));
                        $sq_end->add(new DateInterval("PT10M"));
//                         echo "HERE " . $sq_end->format("c") . "<br>";

                        $diff = $ss->start()->diff($sq_end);
                        if ($diff->invert == 0) {
                            $can_start_next_queue_item = FALSE;
                        }
                    }
                }
            }

            // check if next queue item can be startet
            if ($can_start_next_queue_item) {
                $sq = SessionQueue::next($sslot);
                if ($sq !== NULL) {
                    $this->log("Starting ServerQueue.Id=" . $sq->id());
                    $sslot->start($sq->preset(),
                                $sq->carClass(),
                                $sq->track(),
                                $sq->seatOccupations());
                }
            }
        }
    }


}

?>
