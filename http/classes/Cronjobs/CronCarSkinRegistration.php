<?php

namespace Cronjobs;

class CronCarSkinRegistration extends \Core\Cronjob {

    public function __construct() {
        parent::__construct(\Core\Cronjob::IntervalAlways);
    }


    protected function process() {

        // $last_processed_id = $this->loadData("LastProcessedId", 0);
        $csr = \DbEntry\CarSkinRegistration::nextRegistration2BProcessed();
        if ($csr !== NULL) {
            $this->verboseOutput("$csr<br>");
            $this->verboseOutput("{$csr->CarSkin()->car()->model()}/skins/{$csr->CarSkin()->skin()}<br>");
            $csr->processRegistration();
        }
    }
}
