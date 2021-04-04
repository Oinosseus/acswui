<?php

class CronSessionAutomatic extends Cronjob {


    public function __construct() {
        parent::__construct(new DateInterval("PT1S"));
    }


    public function execute() {

        foreach (ServerSlot::listSlots() as $sslot) {

            // check if slot is available
            if ($sslot->online()) continue;

            // get next session queue item
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

?>
