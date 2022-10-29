<?php

namespace Cronjobs;

class CronCarSkinRegistration extends \Core\Cronjob {

    public function __construct() {
        parent::__construct(\Core\Cronjob::IntervalDaily);//Always);
    }


    protected function process() {

        // $last_processed_id = $this->loadData("LastProcessedId", 0);
        $csr = \DbEntry\CarSkinRegistration::nextRegistration2BProcessed();
        if ($csr !== NULL) {
            $this->verboseOutput("$csr<br>");
            $this->verboseOutput("{$csr->CarSkin()->car()->model()}/skins/{$csr->CarSkin()->skin()}<br>");
            $csr->processRegistration();
        }

        // $res = \Core\Database::fetchRaw("SELECT Id FROM CarSkinRegistrations WHERE Processed < Requested AND Id > $last_processed_id ORDER BY Id ASC LIMIT 1;");
        // if (count($res) > 0) {
        //     $csr->processRegistration();
        //
        //     $skin_dst_dir = \Core\Config::AbsPathHtdata . "/content/cars/" . $cs->car()->model() . "/skins/" . $cs->skin();
        //
        //
        //     $this->saveData("LastProcessedId", $csr->id();
        // }

        // $any_skin_found = False;
        // foreach ($res as $row) {
        //     $any_skin_found = True;
        //     $cs = \DbEntry\CarSkin::fromId($row['Id']);
        //     $last_registered = $cs->id();
        //     $this->verboseOutput("Register CarSkin Id {$cs->id()}<br>");
        //
        //     // get old name and replace with new name (version portion change)
        //     $skin_name_old = $cs->skin();
        //     $matches = NULL;
        //     if (preg_match('/(acswui_\d+_\d+_[\da-fA-F]+_v)(\d+)/', $skin_name_old, $matches) === False) {
        //         \Core\Log::error("Cannot match carskin name");
        //
        //     }
        //     // $skin_name_new = "acswui_{$cs->owner()->id()}_{$cs->id()}
        //
        //     // install preview
        //     // $file_preview_src = \Core\Config::AbsPathHtdata;
        //     $skin_dst_dir = \Core\Config::AbsPathHtdata . "/content/cars/" . $cs->car()->model() . "/skins/" . $cs->skin();
        //     $this->verboseOutput($skin_dst_dir . "<br>");
        //
        //     break;  // only register one car per cronjob execution
        // }
        // if ($any_skin_found == False) $last_registered = 0;


    }


    // private function
}
