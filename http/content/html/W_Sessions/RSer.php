<?php

declare(strict_types=1);
namespace Content\Html;

class RSer extends \core\HtmlContent {

    private $CanCreate = FALSE;
    private $CanEdit = FALSE;
    private $CanRegister = FALSE;

    private $CurrentSeries = NULL;
    private $CurrentSeason = NULL;


    public function __construct() {
        parent::__construct(_("Race Series"),  _("Race Series"));
        $this->requirePermission("ServerContent_RaceSeries_View");
        $this->addScript("Content_RSer.js");
    }


    public function getHtml() {
        $html = "";

        // check permissions
        $this->CanRegister = \Core\UserManager::currentUser()->permitted("ServerContent_RaceSeries_Register");
        $this->CanEdit = \Core\UserManager::currentUser()->permitted("ServerContent_RaceSeries_Edit");
        $this->CanCreate = \Core\UserManager::currentUser()->permitted("ServerContent_RaceSeries_Create");

        // get current requested series/season
        if (array_key_exists("RSerSeries", $_REQUEST))
                $this->CurrentSeries = \DbEntry\RSerSeries::fromId((int) $_REQUEST['RSerSeries']);
        if (array_key_exists("RSerSeason", $_REQUEST))
            $this->CurrentSeason = \DbEntry\RSerSeason::fromId((int) $_REQUEST['RSerSeason']);

        // process actions
        if (array_key_exists("Action", $_REQUEST)) {

            // add split
            if ($this->CanEdit && $_REQUEST['Action'] == "AddSplit") {
                $rser_e = \DbEntry\RSerEvent::fromId((int) $_REQUEST['RSerEvent']);
                \DbEntry\RSerSplit::createNew($rser_e);

                $this->reload(["RSerSeries"=>$this->CurrentSeries->id(),
                               "RSerSeason"=>$this->CurrentSeason->id(),
                               "View"=>"EditSeason"]);
            }

            // add event
            if ($this->CanEdit && $_REQUEST['Action'] == "AddEvent") {
                \DbEntry\RSerEvent::createNew($this->CurrentSeason);
                $this->reload(["RSerSeries"=>$this->CurrentSeries->id(),
                               "RSerSeason"=>$this->CurrentSeason->id(),
                               "View"=>"EditSeason"]);
            }

            // create new series
            if ($this->CanCreate && $_REQUEST['Action'] == "CreateNewSeries") {
                $this->CurrentSeries = \DbEntry\RSerSeries::createNew();
                $this->reload(["RSerSeries"=>$this->CurrentSeries->id(),
                               "View"=>"EditSeries"]);
            }

            // create new season
            if ($this->CanCreate && $this->CanEdit && $_REQUEST['Action'] == "CreateNewSeason") {
                $rser_s = \DbEntry\RSerSeason::createNew($this->CurrentSeries);
                $this->reload(["RSerSeries"=>$this->CurrentSeries->id(),
                               "RSerSeason"=>$rser_s->id(),
                               "View"=>"EditSeason"]);
            }

            // save series settings
            if ($this->CanEdit && $_REQUEST['Action'] == "SaveRaceSeries") {

                // update name
                $this->CurrentSeries->setName($_POST['SeriesName']);

                // update logo
                if (array_key_exists("SeriesLogoFile", $_FILES) && strlen($_FILES['SeriesLogoFile']['name']) > 0) {
                    $this->CurrentSeries->uploadLogoFile($_FILES['SeriesLogoFile']['tmp_name'], $_FILES['SeriesLogoFile']['name']);
                }

                // parameter collection
                $this->CurrentSeries->parameterCollection()->storeHttpRequest();
                $this->CurrentSeries->save();

                // save car class parameters
                foreach ($this->CurrentSeries->listClasses() as $rser_c) {
                    $rser_c->parameterCollection()->storeHttpRequest("Class{$rser_c->id()}_");
                    $rser_c->save();
                }

                // add car classes
                $cc_id = $_POST['AddCarClass'];
                if (strlen($cc_id) > 0) {
                    $cc = \DbEntry\CarClass::fromId((INT) $cc_id);
                    \DbEntry\RSerClass::createNew($this->CurrentSeries, $cc);
                }

                // reload
                $this->reload(['RSerSeries'=>$this->CurrentSeries->id(),
                               "View"=>"EditSeries"]);
            }

            // save season
            if ($this->CanEdit && $_REQUEST['Action'] == "SaveSeason") {

                if (array_key_exists("RSerSeasonName", $_POST)) {
                    $new_name = $_POST['RSerSeasonName'];
                    $this->CurrentSeason->setName($new_name);
                }

                // events
                foreach ($this->CurrentSeason->listEvents() as $rser_e) {
                    $html_id = "RSerEvent{$rser_e->id()}Track";
                    $track = \DbEntry\Track::fromId((int) $_POST[$html_id]);
                    $rser_e->setTrack($track);

                    // splits
                    foreach ($rser_e->listSplits() as $rser_sp) {
                        $date = $_POST["RSerSplit{$rser_sp->id()}Date"];
                        $time = $_POST["RSerSplit{$rser_sp->id()}Time"];
                        $dt = new \DateTime("$date $time", new \DateTimeZone(\Core\UserManager::currentUser()->getParam("UserTimezone")));
                        $rser_sp->setStart($dt);

                        $server_slot_id = (int) $_POST["RSerSplit{$rser_sp->id()}Slot"];
                        $server_slot = \Core\ServerSlot::fromId($server_slot_id);
                        $rser_sp->setServerSlot($server_slot);
                    }
                }

                // reload
                $this->reload(["RSerSeries"=>$this->CurrentSeries->id(),
                               "RSerSeason"=>$this->CurrentSeason->id(),
                               "View"=>"EditSeason"]);
            }

            // remove car class
            if ($this->CanEdit && $_REQUEST['Action'] == "DeactivateClass") {

                // get car class
                $rser_c = \DbEntry\RSerClass::fromId((int) $_REQUEST['Class']);

                // check if class is valid
                $valid = FALSE;
                foreach ($this->CurrentSeries->listClasses() as $c) {
                    if ($c->id() == $rser_c->id()) {
                        $valid = TRUE;
                        break;
                    }
                }

                // deactivate
                if ($valid) {
                    $rser_c->deactivate();
                    $this->reload(['RSerSeries'=>$this->CurrentSeries->id(),
                                   'View'=>"EditSeries"]);
                }
            }

        }

        // determine HTML output
        if ($this->CurrentSeries == NULL) {
            $html .= $this->getHtmlOverview();

        } else if (array_key_exists("View", $_REQUEST)) {

            if ($_REQUEST["View"] == "EditSeries") {
                $html .= $this->getHtmlEditSeries();

            } else if ($_REQUEST["View"] == "EditSeason") {
                $html .= $this->getHtmlEditSeason();
            }

        } else {
            $html .= $this->gehtHtmlShowSeries();
        }

        return $html;
    }


    // edit settings of a race series season
    private function getHtmlEditSeason() : string {
        $html = "";

        // back link
        $url = $this->url(['RSerSeries'=>$this->CurrentSeries->id()]);
        $html .= "<a href=\"$url\">&lt;&lt; " . _("Back") . "</a><br><br>";

        // permission check
        if (!$this->CanEdit) return "";
        if ($this->CurrentSeries === NULL) return "";
        if ($this->CurrentSeason === NULL) return "";

        // create new form
        $html .= $this->newHtmlForm("POST");
        $html .= "<input type=\"hidden\" name=\"RSerSeries\" value=\"{$this->CurrentSeries->id()}\">";
        $html .= "<input type=\"hidden\" name=\"RSerSeason\" value=\"{$this->CurrentSeason->id()}\">";

        // name
        $html .= "<h1>" . _("Season") . " ";
        $html .= "<div style=\"display: inline-block;\" id=\"LabelRSerSeasonName\">{$this->CurrentSeason->name()}</div>";
        if ($this->CanEdit) {
            $html .= " <div style=\"display: inline-block; cursor: pointer;\" id=\"EnableEditRSerSeasonNameButton\">&#x270e;</div>";
        }
        $html .= "</h1>";

        // add event
        $url = $this->url(['RSerSeries'=>$this->CurrentSeries->id(),
                            "RSerSeason"=>$this->CurrentSeason->id(),
                            "Action"=>"AddEvent"]);
        $html .= "<a href=\"$url\">" . _("Add Event") . "</a> ";
        $html .= "<br><br>";

        // list events
        $html .= "<h1>" . _("Events") . "</h1>";
        $events = \DbEntry\RSerEvent::listEvents($this->CurrentSeason);
        for ($event_idx=0; $event_idx < count($events); ++$event_idx) {

            $html .= "<table>";
            $html .= "<caption>";
            $html .= _("Event") . " " . ($event_idx+1) . " - ";
            $track = $events[$event_idx]->track();
            if ($track) $html .= $track->name();
            $html .= "</caption>";

            // track select
            $html .= "<tr><th>" . _("Track") . "</th><td colspan=\"2\"><select name=\"RSerEvent{$events[$event_idx]->id()}Track\">";
            foreach (\DbEntry\Track::listTracks() as $track) {
                $selected = ($events[$event_idx]->track() == $track) ? "selected=\"yes\"" : "";
                $html .= "<option value=\"{$track->id()}\" $selected>{$track->name()}</option>";
            }
            $html .= "</select></td></tr>";

            // list splits
            $html .= "<tr><th>" . _("Split") . "</th><th>" . _("Start") . "</th><th>" . _("Server Slot") . "</th></tr>";
            $splits = \DbEntry\RSerSplit::listSplits($events[$event_idx]);
            for ($split_idx=0; $split_idx < count($splits); ++$split_idx) {
                $html .= "<tr>";
                $html .= "<td>" . _("Split") . " " . ($split_idx+1) . "</td>";

                $html .= "<td>";
                $dt = $splits[$split_idx]->start();
                $dt->setTimezone(new \DateTimeZone(\Core\UserManager::currentUser()->getParam("UserTimezone")));
                $html .= "<input type=\"date\" name=\"RSerSplit{$splits[$split_idx]->id()}Date\" value=\"{$dt->format('Y-m-d')}\"> ";
                $html .= "<input type=\"time\" name=\"RSerSplit{$splits[$split_idx]->id()}Time\" value=\"{$dt->format('H:i')}\">";
                $html .= "</td>";

                $html .= "<td><select name=\"RSerSplit{$splits[$split_idx]->id()}Slot\">";
                foreach (\Core\ServerSlot::listSlots() as $server_slot) {
                    $selected = ($server_slot == $splits[$split_idx]->serverSlot()) ? "selected=\"yes\"" : "";
                    $html .= "<option value=\"{$server_slot->id()}\" $selected>{$server_slot->name()}</option>";
                }
                $html .= "</select></td>";

                $html .= "</tr>";

            }

            // add split
            $url = $this->url(['RSerSeries'=>$this->CurrentSeries->id(),
                                "RSerSeason"=>$this->CurrentSeason->id(),
                                "RSerEvent"=>$events[$event_idx]->id(),
                                "Action"=>"AddSplit"]);
            $html .= "<tr><td><a href=\"$url\">" . _("Add Spit") . "</a></td></tr>";

            $html .= "<br>";
            $html .= "</table>";
        }


        //  Fisnish Form
        $html .= "<br><br><br><br>";
        $html .= "<button type=\"submit\" name=\"Action\" value=\"SaveSeason\">";
        $html .=_("Save Season");
        $html .= "</button>";
        $html .= "</form>";

        return $html;
    }

    // edit settings of a race series
    private function getHtmlEditSeries() : string {
        $html = "";

        // back link
        $url = $this->url(['RSerSeries'=>$this->CurrentSeries->id()]);
        $html .= "<a href=\"$url\">&lt;&lt; " . _("Back") . "</a><br><br>";

        // permission check
        if (!$this->CanEdit) return "";
        if ($this->CurrentSeries === NULL) return "";

        // create new form
        $html .= $this->newHtmlForm("POST");
        $html .= "<input type=\"hidden\" name=\"RSerSeries\" value=\"{$this->CurrentSeries->id()}\">";


        // --------------------------------------------------------------------
        //  General Settings
        // --------------------------------------------------------------------

        $html .= "<h1>" . _("General Setup") . "</h1>";
        $html .= "<table id=\"RSerSeriesEditGeneralSetup\">";
        $html .= "<caption>" . _("General Setup") . "</caption>";

        $html .= "<tr>";
        $html .= "<td rowspan=\"2\"><img src=\"{$this->CurrentSeries->logoPath()}\"></td>";

        $html .= "<th>" . _("Name") . "</th>";
        $html .= "<td><input type=\"text\" name=\"SeriesName\" value=\"{$this->CurrentSeries->name()}\" /></td>";
        $html .= "</tr>";

        $html .= "<tr>";
        $html .= "<th>" . _("Logo") . "</th>";
        $html .= "<td><input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"524288\" />";
        $html .= "<input type=\"file\" name=\"SeriesLogoFile\"></td>";
        $html .= "</tr>";

        $html .= "</table>";


        // --------------------------------------------------------------------
        //  Parameters
        // --------------------------------------------------------------------

        $html .= "<h1>" . _("Parameters") . "</h1>";
        $html .= $this->CurrentSeries->parameterCollection()->getHtml(TRUE, FALSE);

        $html .= "<br>" . _("Race Result Points") . ": ";
        $html .= "<div id=\"RaceResultPointsList\">";
        for ($position=1; TRUE; ++$position) {
            $points = $this->CurrentSeries->raceResultPoints($position);
            $html .= "<div>$position: <strong>{$points}</strong>pts</div>";
            if ($points <= 0) break;
        }
        $html .= "</div>";


        // --------------------------------------------------------------------
        //  Classes
        // --------------------------------------------------------------------

        $html .= "<h1>" . _("Classes") . "</h2>";

        // add new
        $html .= _("Add Car Class") . ": ";
        $html .= "<select name=\"AddCarClass\">";
        $html .= "<option value=\"\"> </option>";
        foreach (\DbEntry\CarClass::listClasses() as $cc) {
            $html .= "<option value=\"{$cc->id()}\">{$cc->name()}</option>";
        }
        $html .= "</select>";

        // show classes
        foreach ($this->CurrentSeries->listClasses() as $rser_c) {
            $html .= "<h2>{$rser_c->name()}</h2>";

            $url = $this->url(['RSerSeries'=>$this->CurrentSeries->id(),
                               'Action'=>"DeactivateClass",
                               'Class'=>$rser_c->id()]);
            $html .= "<a href=\"$url\">" . _("Remove Class") . "</a><br>";

            $html .= $rser_c->carClass()->html();
            $html .= $rser_c->parameterCollection()->getHtml(TRUE, FALSE, "Class{$rser_c->id()}_");

            $html .= "<br>" . _("BOP") . ": ";
            $html .= "<div id=\"RaceResultPointsList\">";
            $pos_end = 1 + max($rser_c->getParam("BopRestrictorPosition"), $rser_c->getParam("BopBallastPosition"));
            for ($position=1; $position <= $pos_end; ++$position) {
                $ballast= $rser_c->bopBallast($position);
                $restrictor= $rser_c->bopRestrictor($position);
                $html .= "<div>$position: <strong>{$ballast}kg {$restrictor}&percnt;</strong></div>";
            }
            $html .= "</div>";
        }


        // --------------------------------------------------------------------
        //  Fisnish Form
        // --------------------------------------------------------------------

        $html .= "<br><br><br><br>";
        $html .= "<button type=\"submit\" name=\"Action\" value=\"SaveRaceSeries\">";
        $html .=_("Save Race Series");
        $html .= "</button>";
        $html .= "</form>";

        return $html;
    }


    // Overview of available race series
    private function getHtmlOverview() : string {
        $html = "";

        // list available series
        foreach (\DbEntry\RSerSeries::listSeries() as $rser_s) {
            $html .= $rser_s->html();
        }


        // found new team
        if ($this->CanCreate) {
            $html .= "<br><br>";
            $url = $this->url(['Action'=>"CreateNewSeries"]);
            $html .= "<a href=\"$url\">" . _("Create new Race Series") . "</a> ";
        }

        return $html;
    }


    // Show a certain race series
    private function gehtHtmlShowSeries() : string {
        $html = "";

        // back link
        $url = $this->url(['RSerSeries'=>0]);
        $html .= "<a href=\"$url\">&lt;&lt; " . _("Back") . "</a><br><br>";


        $html .= "<h1>{$this->CurrentSeries->name()}</h1>";

        // edit option
        if ($this->CanEdit) {
            $url = $this->url(['RSerSeries'=>$this->CurrentSeries->id(),
                               'View'=>"EditSeries"]);
            $html .= "<a href=\"$url\">" . _("Edit Race Series") . "</a> ";

            // add season
            if ($this->CanCreate) {
                $url = $this->url(['RSerSeries'=>$this->CurrentSeries->id(),
                                   'Action'=>"CreateNewSeason"]);
                $html .= "<a href=\"$url\">" . _("Create New Season") . "</a> ";
            }

            $html .= "<br><br>";
        }

        // list seasons
        $html .= "<h1>" . _("Seasons") . "</h1>";
        foreach (\DbEntry\RSerSeason::listSeasons($this->CurrentSeries) as $rser_s) {
            $html .= "<h2>{$rser_s->name()}</h2>";

            // edit link
            if ($this->CanEdit) {
                $url = $this->url(['RSerSeries'=>$this->CurrentSeries->id(),
                                   "RSerSeason"=>$rser_s->id(),
                                   "View"=>"EditSeason"]);
                $html .= "<a href=\"$url\">" . _("Edit Season") . "</a> ";
                $html .= "<br><br>";
            }
        }

        return $html;
    }

}

