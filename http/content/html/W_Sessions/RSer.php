<?php

declare(strict_types=1);
namespace Content\Html;

class RSer extends \core\HtmlContent {

    private $CanCreate = FALSE;
    private $CanEdit = FALSE;
    private $CanRegister = FALSE;

    private $CurrentSeries = NULL;
    private $CurrentSeason = NULL;
    private $CurrentClass = NULL;
    private $CurrentEvent = NULL;


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
        if (array_key_exists("RSerSeries", $_REQUEST)) {
            $this->CurrentSeries = \DbEntry\RSerSeries::fromId((int) $_REQUEST['RSerSeries']);
        }
        if (array_key_exists("RSerSeason", $_REQUEST)) {
            $this->CurrentSeason = \DbEntry\RSerSeason::fromId((int) $_REQUEST['RSerSeason']);
            $this->CurrentSeries = $this->CurrentSeason->series();
        }
        if (array_key_exists("RSerClass", $_REQUEST)) {
            $this->CurrentClass = \DbEntry\RSerClass::fromId((int) $_REQUEST['RSerClass']);
            $this->CurrentSeries = $this->CurrentClass->series();
        }
        if (array_key_exists("RSerEvent", $_REQUEST)) {
            $this->CurrentEvent = \DbEntry\RSerEvent::fromId((int) $_REQUEST['RSerEvent']);
            $this->CurrentSeason = $this->CurrentEvent->season();
            $this->CurrentSeries = $this->CurrentSeason->series();
        }


        // --------------------------------------------------------------------
        //  Process Actions
        // --------------------------------------------------------------------

        if (array_key_exists("Action", $_REQUEST)) {


            // ----------------------------------------------------------------
            //  Add Split

            // if ($this->CanEdit && $_REQUEST['Action'] == "AddSplit") {
            //     $rser_e = \DbEntry\RSerEvent::fromId((int) $_REQUEST['RSerEvent']);
            //     \DbEntry\RSerSplit::createNew($rser_e);
            //
            //     $this->reload(["RSerSeries"=>$this->CurrentSeries->id(),
            //                    "RSerSeason"=>$this->CurrentSeason->id(),
            //                    "View"=>"SeasonEdit"]);
            // }


            // ----------------------------------------------------------------
            //  Add Event

            if ($this->CanEdit && $_REQUEST['Action'] == "AddEvent") {
                \DbEntry\RSerEvent::createNew($this->CurrentSeason);
                $this->reload(["RSerSeries"=>$this->CurrentSeries->id(),
                               "RSerSeason"=>$this->CurrentSeason->id(),
                               "View"=>"SeasonEdit"]);
            }


            // ----------------------------------------------------------------
            //  Delete Event

            if ($this->CanEdit && $_REQUEST['Action'] == "DoDeleteEvent") {
                $event = \DbEntry\RSerEvent::fromId((int) $_POST["RSerEvent"]);
                $id_series = $event->season()->series()->id();
                $id_season = $event->season()->id();
                $event->delete();
                $this->reload(["RSerSeries"=>$id_series,
                               "RSerSeason"=>$id_season,
                               "View"=>"SeasonEdit"]);
            }


            // ----------------------------------------------------------------
            //  Update Results

            if ($this->CanEdit && $_REQUEST['Action'] == "UpdateSeasonResults") {
                foreach ($this->CurrentSeason->listEvents() as $rs_event) {
                    \DbEntry\RSerResult::calculateFromEvent($rs_event);
                    \DbEntry\RSerResultDriver::calculateFromEvent($rs_event, FALSE);
                }

                \DbEntry\RSerStanding::calculateFromSeason($this->CurrentSeason);
                \DbEntry\RSerStandingDriver::calculateFromSeason($this->CurrentSeason);
            }

            if ($this->CanEdit && $_REQUEST['Action'] == "UpdateEventResults") {
                \DbEntry\RSerResult::calculateFromEvent($this->CurrentEvent);
                \DbEntry\RSerResultDriver::calculateFromEvent($this->CurrentEvent);
            }


            // ----------------------------------------------------------------
            //  Create New Series
            if ($this->CanCreate && $_REQUEST['Action'] == "CreateNewSeries") {
                $this->CurrentSeries = \DbEntry\RSerSeries::createNew();
                $this->reload(["RSerSeries"=>$this->CurrentSeries->id(),
                               "View"=>"SeriesEdit"]);
            }


            // ----------------------------------------------------------------
            //  Create New Season

            if ($this->CanCreate && $this->CanEdit && $_REQUEST['Action'] == "CreateNewSeason") {
                $rser_s = \DbEntry\RSerSeason::createNew($this->CurrentSeries);
                $this->reload(["RSerSeries"=>$this->CurrentSeries->id(),
                               "RSerSeason"=>$rser_s->id(),
                               "View"=>"SeasonEdit"]);
            }


            // ----------------------------------------------------------------
            //  Register Single Driver

            if (\Core\UserManager::loggedUser() && $_REQUEST['Action'] == "RegisterDriver") {
                $user = \Core\UserManager::loggedUser();
                $carskin = \DbEntry\CarSkin::fromId((int) $_POST['RegistrationCarSkin']);

                // check if already occupied
                $already_occupied = FALSE;
                foreach ($this->CurrentSeason->listRegistrations(NULL, TRUE) as $reg) {
                    if ($reg->carSkin() == $carskin) {
                        $already_occupied = TRUE;
                        break;
                    }
                }

                if ($already_occupied) {
                    \Core\Log::warning("Prevent over-occupation of $carskin from $user");
                } else if (!$this->CurrentClass->active()) {
                    \Core\Log::warning("Prevent occupation of inactive class {$this->CurrentClass} from $user");
                } else {
                    \DbEntry\RSerRegistration::createNew($this->CurrentSeason,
                                                        $this->CurrentClass,
                                                        NULL, $user, $carskin);
                }

                $this->reload(["RSerSeries"=>$this->CurrentSeries->id(),
                               "RSerSeason"=>$this->CurrentSeason->id(),
                               "View"=>"SeasonOverview"]);
            }


            // ----------------------------------------------------------------
            //  Unregister Single Driver

            if (\Core\UserManager::loggedUser() && $_REQUEST['Action'] == "UnregisterDriver") {
                $reg = \DbEntry\RSerRegistration::fromId((int) $_REQUEST['RSerRegistration']);
                if ($reg->user() == \Core\UserManager::loggedUser()) {
                    $reg->deactivate();
                }
                $this->reload(["RSerSeries"=>$this->CurrentSeries->id(),
                               "RSerSeason"=>$this->CurrentSeason->id(),
                               "View"=>"SeasonOverview"]);
            }


            // ----------------------------------------------------------------
            //  Register TeamCar

            if (\Core\UserManager::loggedUser() && $_REQUEST['Action'] == "RegisterTeam") {
                $teamcar = \DbEntry\TeamCar::fromId((int) $_POST['RegisterTeamCar']);

                // only matching carclass
                if (!$this->CurrentClass->active()) {
                    \Core\Log::warning("Prevent occupation of inactive class {$this->CurrentClass} from $user");
                } else if ($teamcar->carClass()->carClass() === $this->CurrentClass->carClass()) {
                    \DbEntry\RSerRegistration::createNew($this->CurrentSeason,
                                                        $this->CurrentClass,
                                                        $teamcar);
                }

                $this->reload(["RSerSeries"=>$this->CurrentSeries->id(),
                               "RSerSeason"=>$this->CurrentSeason->id(),
                               "View"=>"SeasonOverview"]);
            }


            // ----------------------------------------------------------------
            //  Unregister TeamCar

            if (\Core\UserManager::loggedUser() && $_REQUEST['Action'] == "UnregisterTeamCar") {
                $reg = \DbEntry\RSerRegistration::fromId((int) $_REQUEST['RSerRegistration']);
                $tmm = $reg->teamCar()->team()->findMember(\Core\UserManager::currentUser());
                if ($tmm && $tmm->permissionManage()) {
                    $reg->deactivate();
                }
                $this->reload(["RSerSeries"=>$this->CurrentSeries->id(),
                               "RSerSeason"=>$this->CurrentSeason->id(),
                               "View"=>"SeasonOverview"]);
            }


            // ----------------------------------------------------------------
            //  Save Series Settings
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

                    // fixed setup
                    if (array_key_exists("FixedSetup_{$rser_c->id()}", $_POST)) {
                        $fixed_setup = $_POST["FixedSetup_{$rser_c->id()}"];
                        $rser_c->setFixedSetup($fixed_setup);
                    }
                }

                // add car classes
                $cc_id = $_POST['AddCarClass'];
                if (strlen($cc_id) > 0) {
                    $cc = \DbEntry\CarClass::fromId((INT) $cc_id);
                    \DbEntry\RSerClass::createNew($this->CurrentSeries, $cc);
                }

                // reload
                $this->reload(['RSerSeries'=>$this->CurrentSeries->id(),
                               "View"=>"SeriesEdit"]);
            }


            // ----------------------------------------------------------------
            //  Save Season

            if ($this->CanEdit && $_REQUEST['Action'] == "SaveSeason") {

                if (array_key_exists("RSerSeasonName", $_POST)) {
                    $new_name = $_POST['RSerSeasonName'];
                    $this->CurrentSeason->setName($new_name);
                }

                // events
                foreach ($this->CurrentSeason->listEvents() as $rser_e) {

                    // save track
                    $html_id = "RSerEvent{$rser_e->id()}Track";
                    $track = \DbEntry\Track::fromId((int) $_POST[$html_id]);
                    $rser_e->setTrack($track);

                    // save valuation
                    $html_id = "RSerEvent{$rser_e->id()}Valuation";
                    $val = (float) $_POST[$html_id] / 100.0;
                    if (array_key_exists($html_id, $_POST)) $rser_e->setValuation($val);

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
                               "View"=>"SeasonEdit"]);
            }


            // ----------------------------------------------------------------
            //  Remove Car Class

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

                if ($valid) {
                    // deactivate class
                    $rser_c->deactivate();

                    // disable registrations from all seasons with future splits
                    foreach ($this->CurrentSeries->listSeasons() as $season) {
                        foreach ($season->listRegistrations($rser_c, TRUE) as $reg) {
                            $reg->deactivate();
                        }
                    }

                    // reload
                    $this->reload(['RSerSeries'=>$this->CurrentSeries->id(),
                                   'View'=>"SeriesEdit"]);
                }
            }

        }


        // --------------------------------------------------------------------
        //  Determine HTML Output
        // --------------------------------------------------------------------

        if ($this->CurrentSeries == NULL) {

            // list available series
            foreach (\DbEntry\RSerSeries::listSeries() as $rser_s) {
                $html .= $rser_s->html();
            }

            // create new series
            if ($this->CanCreate) {
                $html .= "<br><br>";
                $url = $this->url(['Action'=>"CreateNewSeries"]);
                $html .= "<a href=\"$url\">" . _("Create new Race Series") . "</a> ";
            }

        } else if (array_key_exists("View", $_REQUEST)) {

            switch ($_REQUEST["View"]) {

                case "SeriesEdit":
                    $html .= $this->getHtmlSeriesEdit();
                    break;

                case "SeasonEdit":
                    $html .= $this->gehtHtmlSeasonEdit();
                    break;

                case "SeasonOverview":
                    $html .= $this->getHtmlSeasonOverview();
                    break;

                case "SeasonRegisterDriver":
                    $html .= $this->getHtmlRegisterDriver();
                    break;

                case "SeasonRegisterTeam":
                    $html .= $this->getHtmlRegisterTeam();
                    break;

                case "EventOverview":
                    $html .= $this->getHtmlEventOverview();
                    break;

                case "AskDeleteEvent":
                    $html .= $this->getHtmlEventDeleteAsk();
                    break;

                default:
                    \Core\Log::error("Unknown view {$_REQUEST['View']}!");
            }

        } else {
            $html .= $this->gehtHtmlSeriesOverview();
        }

        return $html;
    }


    private function getHtmlEventOverview() {
        $html = "";
        $cu = \Core\UserManager::currentUser();

        // breadcrumps
        $url = $this->url(['RSerSeries'=>0]);
        $html .= "<a href=\"$url\">" . _("Race Series List") . "</a> &lt;&lt; ";
        $url = $this->url(['RSerSeries'=>$this->CurrentSeries->id()]);
        $html .= "<a href=\"$url\">{$this->CurrentSeries->name()}</a> &lt;&lt; ";
        $url = $this->url(['RSerSeries'=>$this->CurrentSeries->id(),
                           "RSerSeason"=>$this->CurrentSeason->id(),
                           "View"=>"SeasonOverview"]);
        $html .= "<a href=\"$url\">" . _("Season") . " {$this->CurrentSeason->name()}</a> &lt;&lt; ";
        $html .= _("Event") . " E{$this->CurrentEvent->order()}";
        $html .= "<br>";

        // header
        $html .= $this->getHtmlPageHeader();

        // permission check
        if ($this->CurrentEvent === NULL) return "";

        // update results
        if ($this->CanEdit) {
            $url = $this->url(['RSerSeries'=>$this->CurrentSeries->id(),
                                "RSerSeason"=>$this->CurrentSeason->id(),
                                "RSerEvent"=>$this->CurrentEvent->id(),
                                "Action"=>"UpdateEventResults",
                                "View"=>"EventOverview"]);
            $html .= "<a href=\"$url\">" . _("Update Results") . "</a> ";
            $html .= "<br><br>";
        }

        // race results
        $html .= "<h1>" . _("Race Results") . "</h1>";
        foreach ($this->CurrentSeries->listClasses(active_only:FALSE) as $rs_class) {
            $result_list = $this->CurrentEvent->listResultsDriver($rs_class);

            $html .= "<h2>{$rs_class->name()}</h2>";
            $html .= _("Races") . ": ";
            $session_list = array();
            foreach ($this->CurrentEvent->listSplits() as $split) {
                foreach ($split->listRaces() as $session) {
                    $session_list[] = $session->htmlName();
                }
            }

            if (count($result_list) > 0) {

                $html .= implode(", ", $session_list);
                $html .= "<br>";

                $html .= "<table>";
                $html .= "<tr>";
                $html .= "<th>" . _("Pos") . "</th>";
                $html .= "<th>" . _("Driver") . "</th>";
                $html .= "<th>" . _("Points") . "</th>";
                $html .= "</tr>";

                foreach ($result_list as $rslt) {
                    $html .= "<tr>";
                    $html .= "<td>{$rslt->position()}</td>";
                    $html .= "<td>{$rslt->user()->nationalFlag()} {$rslt->user()->html()}</td>";
                    $html .= "<td>{$rslt->pointsValuated()}</td>";
                    $html .= "</tr>";
                }

                $html .= "</table>";
            }
        }

        // qualifying
        $html .= "<h1>" . _("Qualifications") . "</h1>";
        foreach ($this->CurrentSeries->listClasses(active_only:FALSE) as $rs_class) {
            $html .= "<h2>{$rs_class->name()}</h2>";
            $html .= "<table>";
            $html .= "<tr>";
            $html .= "<th>" . _("Pos") . "</th>";
            $html .= "<th colspan=\"2\">" . _("Entry") . "</th>";
            $html .= "<th colspan=\"2\">" . _("Laptime") . "</th>";
            $html .= "<th>" . _("BOP") . "</th>";
            $html .= "</tr>";

            $pos = 1;
            $best_laptime = NULL;
            foreach ($this->CurrentEvent->listQualifications($rs_class) as $rs_qual) {
                $reg = $rs_qual->registration();
                $lap = $rs_qual->lap();

                $html .= "<tr>";
                $html .= "<td>$pos</td>";

                if ($reg->teamCar()) {
                    $html .= "<td class=\"ZeroPadding\">{$reg->teamCar()->team()->html(TRUE, FALSE, FALSE, TRUE)}</td>";
                    $html .= "<td>";
                    $drivers = $reg->teamCar()->drivers();
                    for ($i=0; $i < count($drivers); ++$i) {
                        $tmm = $drivers[$i];
                        if ($i > 0) $html .= ",<br>";
                        $html .= $tmm->user()->nationalFlag() . " ";
                        $html .= $tmm->user()->html();
                    }
                    $html .= "</td>";
                } else {
                    $html .= "<td></td>";
                    $html .= "<td>{$reg->user()->nationalFlag()} {$reg->user()->html()}</td>";
                }

                $html .= "<td>{$lap->html()}</td>";
                if ($best_laptime === NULL) $html .= "<td></td>";
                else $html .= "<td>{$cu->formatLaptimeDelta($lap->laptime() - $best_laptime)}</td>";
                $html .= "<td>" . sprintf("%+dkg, %+d&percnt;", $lap->ballast(), $lap->restrictor()) . "</td>";

                $html .= "</tr>";
                ++$pos;

                // remember best laptime
                if ($best_laptime === NULL) $best_laptime = $lap->laptime();
            }

            $html .= "</table>";
        }

        return $html;
    }



    private function getHtmlEventDeleteAsk() : string {

        // get event
        $post_key = "RSerEvent";
        if (!array_key_exists($post_key, $_POST)) return "";
        $event = \DbEntry\RSerEvent::fromId((int) $_POST[$post_key]);
        if ($event == NULL) return "";

        // create HTML
        $html = "";
        $html .= "<strong>E{$event->order()}</strong> ";
        $html .= "{$event->track()->name()}<br>";
        $html .= _("Really delete Event?");
        $html .= "<br><br>";
        $html .= "<div class=\"DeleteEventForm\">";

        // delete button
        $html .= $this->newHtmlForm("POST");
        $html .= "<input type=\"hidden\" name=\"RSerEvent\" value=\"{$event->id()}\">";
        $html .= "<button type=\"submit\" name=\"Action\" value=\"DoDeleteEvent\">" . _("Delete") . "</button>";
        $html .= "</form>";

        $html .= " ";

        // cancel button
        $html .= $this->newHtmlForm("GET");
        $html .= "<input type=\"hidden\" name=\"HtmlContent\" value=\"RSer\">";
        $html .= "<input type=\"hidden\" name=\"RSerSeries\" value=\"{$event->season()->series()->id()}\">";
        $html .= "<input type=\"hidden\" name=\"RSerSeason\" value=\"{$event->season()->id()}\">";
        $html .= "<input type=\"hidden\" name=\"View\" value=\"SeasonEdit\">";
        $html .= "<button type=\"submit\">" . _("Cancel") . "</button>";
        $html .= "</form>";

        $html .= "</div>";

        return $html;
    }



    private function getHtmlDriverRanking(\DbEntry\RSerSeason $season, \DbEntry\RSerClass $rs_class) : string {
        $html = "";

        // prepare table
        $html .= "<table>";
        $html .= "<tr>";
        $html .= "<th>" . _("Pos") . "</th>";
        $html .= "<th>" . _("Driver") . "</th>";
        foreach ($this->CurrentSeason->listEvents() as $rs_event) {
            $css_class = ($rs_event->valuation() == 0.0) ? " class=\"Unscored\"":"";
            $url = $this->url(["RSerEvent"=>$rs_event->id(),
                                "View"=>"EventOverview"]);
            $valuation = $rs_event->valuation() * 100;
            $html .= "<th$css_class><a href=\"$url\">E{$rs_event->order()}<br><small>{$valuation}&percnt;</small></a></th>";
        }
        $html .= "<th>" . _("Points") . "</th>";
        $html .= "<th>" . _("BOP") . "</th>";
        $html .= "</tr>";

        // list drivers
        foreach ($season->listStandingsDriver($rs_class) as $stdg) {
            $html .= "<tr>";
            $html .= "<th>{$stdg->position()}</th>";
            $html .= "<td>{$stdg->user()->nationalFlag()} {$stdg->user()->html()}</td>";
            foreach ($this->CurrentSeason->listEvents() as $rs_event) {
                $rslt = \DbEntry\RSerResultDriver::findResult($stdg->user(), $rs_event, $rs_class);
                if ($rslt === NULL) {
                    $html .= "<td></td>";
                } else {
                    $css_class = ($rslt->strikeResult()) ? " class=\"Unscored\"":"";
                    $html .= "<td$css_class>{$rslt->points()}</td>";
                }
            }
            $html .= "<td>{$stdg->points()}</td>";
            $html .= "</tr>";
        }


        $html .= "</table>";
        return $html;
    }


    private function getHtmlOverallStandings(\DbEntry\RSerSeason $season) : string {
        $html = "";

        $html .= "<table>";
        $html .= "<caption>" . _("Standings") . " " . _("Season") . " {$season->name()}</caption>";
        $html .= "<tr>";
        $html .= "<th>" . _("Pos") . "</th>";
        $html .= "<th>" . _("Team") . "</th>";
        $html .= "<th>" . _("Driver") . "</th>";
        $html .= "<th>" . _("Points") . "</th>";
        $html .= "</tr>";
        $position = 1;
        $position_last = 1;
        $points_last = 0;
        foreach (\Compound\RSerTeamStanding::listStadnings($season) as $rs_ts) {
            if ($rs_ts->points() == $points_last) {
                $local_position = $position_last;
            } else {
                $local_position = $position;
                $position_last = $position;
            }

            $html .= "<tr>";
            $html .= "<td>$local_position</td>";

            if (is_a($rs_ts->entry(), "\\DbEntry\\User")) {
                $html .= "<td></td>";
                $html .= "<td>{$rs_ts->entry()->nationalFlag()} {$rs_ts->entry()->html()}</td>";

            } else if (is_a($rs_ts->entry(), "\\DbEntry\\Team")) {
                $drivers = "";
                foreach ($season->listRegistrations(NULL, TRUE) as $reg) {
                    if (!$reg->teamCar() || $reg->teamCar()->team() != $rs_ts->entry()) continue;
                    foreach ($reg->teamCar()->drivers() as $d) {
                        if (strlen($drivers) > 0) $drivers .= ",<br>";
                        $drivers .= $d->user()->nationalFlag() . " ";
                        $drivers .= $d->user()->html();
                    }
                }
                $html .= "<td class=\"ZeroPadding\">{$rs_ts->entry()->html(TRUE, FALSE, TRUE, FALSE)}</td>";

                $html .= "<td>";
                // $html .= "{$rs_ts->entry()->html(TRUE, TRUE, FALSE, FALSE)}<br>";
                $html .= "<small>$drivers</small>";
                $html .= "</td>";
            }

            $html .= "<td>" . $rs_ts->points() . "</td>";
            $html .= "</tr>";

            ++$position;
            $points_last = $rs_ts->points();
        }
        $html .= "</table>";

        return $html;
    }


    private function getHtmlPageHeader() {
        $html = "";

        $html .= "<div class=\"RSerPageHeader\">";
        $html .= "<img src=\"{$this->CurrentSeries->logoPath()}\"> ";
        $html .= "<div>{$this->CurrentSeries->name()}</div>";
        if ($this->CurrentSeason) $html .= "<div class=\"RSerSeason\">" . _("Season") . " {$this->CurrentSeason->name()}</div>";
        if ($this->CurrentClass) $html .= "<div class=\"RSerClass\">{$this->CurrentClass->name()}</div>";
        if ($this->CurrentEvent) $html .= "<div class=\"RSerEvent\">E{$this->CurrentEvent->order()} {$this->CurrentEvent->track()->name()}</div>";
        $html .= "</div>";

        return $html;
    }


    private function getHtmlRegisterDriver() {
        $html = "";

        // back link
        $url = $this->url(['RSerSeries'=>$this->CurrentSeries->id(),
                           "RSerSeason"=>$this->CurrentSeason->id(),
                           "View"=>"SeasonOverview"]);
        $html .= "<a href=\"$url\">&lt;&lt; " . _("Season Overview") . "</a>";

        // header
        $html .= $this->getHtmlPageHeader();

        // permission check
        if ($this->CurrentSeries === NULL) return "";
        if ($this->CurrentSeason === NULL) return "";
        if ($this->CurrentClass === NULL) return "";
        if (\Core\UserManager::loggedUser() == NULL) return;

        // create new form
        $html .= $this->newHtmlForm("POST");
        $html .= "<input type=\"hidden\" name=\"RSerSeries\" value=\"{$this->CurrentSeries->id()}\">";
        $html .= "<input type=\"hidden\" name=\"RSerSeason\" value=\"{$this->CurrentSeason->id()}\">";
        $html .= "<input type=\"hidden\" name=\"RSerClass\" value=\"{$this->CurrentClass->id()}\">";

        // save button
        $html .= "<button type=\"submit\" name=\"Action\" value=\"RegisterDriver\">" . _("Save Registration") . "</button>";
        $html .= "<br>";

        // list already occupied skins
        $occupied_skins = array();
        foreach ($this->CurrentSeason->listRegistrations(NULL, TRUE) as $reg) {
            $occupied_skins[] = $reg->carSkin();
        }

        // cars for driver registration
        foreach ($this->CurrentClass->carClass()->cars() as $car) {

            $html .= "<h2>{$car->name()}</h2>";
            foreach ($car->skins() as $skin) {

                // skip already occupied skins
                //! @todo TBD Filter already occupied cars
                if (in_array($skin, $occupied_skins)) continue;

                // skip owned skins
                if ($skin->owner() && $skin->owner()->id() != \Core\UserManager::currentUser()->id()) continue;

                // offer skin as radio button
                $skin_img = $skin->html(FALSE, TRUE, TRUE);
                $checked = FALSE;
                $disabled = FALSE;
                $html .= $this->newHtmlContentRadio("RegistrationCarSkin",
                                                    (string) $skin->id(),
                                                    $skin_img,
                                                    $checked,
                                                    $disabled);
            }
        }

        // save button
        $html .= "<br>";
        $html .= "<br>";
        $html .= "<button type=\"submit\" name=\"Action\" value=\"RegisterDriver\">" . _("Save Registration") . "</button>";
        $html .= "<br>";

        $html .= "</form>";
        return $html;
    }


    private function getHtmlRegisterTeam() {
        $html = "";

        // back link
        $url = $this->url(['RSerSeries'=>$this->CurrentSeries->id(),
                           "RSerSeason"=>$this->CurrentSeason->id(),
                           "View"=>"SeasonOverview"]);
        $html .= "<a href=\"$url\">&lt;&lt; " . _("Season Overview") . "</a>";

        // header
        $html .= $this->getHtmlPageHeader();

        // permission check
        if ($this->CurrentSeries === NULL) return "";
        if ($this->CurrentSeason === NULL) return "";
        if ($this->CurrentClass === NULL) return "";
        if (\Core\UserManager::loggedUser() == NULL) return;
        $cu = \Core\UserManager::currentUser();

        // create new form
        $html .= $this->newHtmlForm("POST");
        $html .= "<input type=\"hidden\" name=\"RSerSeries\" value=\"{$this->CurrentSeries->id()}\">";
        $html .= "<input type=\"hidden\" name=\"RSerSeason\" value=\"{$this->CurrentSeason->id()}\">";
        $html .= "<input type=\"hidden\" name=\"RSerClass\" value=\"{$this->CurrentClass->id()}\">";

        // save button
        $html .= "<button type=\"submit\" name=\"Action\" value=\"RegisterTeam\">" . _("Save Registration") . "</button>";
        $html .= "<br>";

        // list already registered carskin IDs
        $already_registered_carskin_ids = [];
        foreach ($this->CurrentSeason->listRegistrations($this->CurrentClass, TRUE) as $reg) {
            $already_registered_carskin_ids[] = $reg->carSkin()->id();
        }

        // cars for team registration
        $html .= "<div id=\"RegistrationTypeTeamCars\">";
        $any_team_car_found = FALSE;
        foreach (\DbEntry\Team::listTeams(manager:$cu, carclass:$this->CurrentClass->carClass()) as $tm) {
            $html .= "<h1>{$tm->name()}</h1>";

            foreach ($tm->cars() as $tc) {

                // only matching carclass
                if ($tc->carClass()->carClass() != $this->CurrentClass->carClass()) continue;

                // check if car is already registered
                if (in_array($tc->carSkin()->id(), $already_registered_carskin_ids)) continue;


                $skin_img = $tc->html();
                $checked = ($any_team_car_found == FALSE) ? TRUE : FALSE;
                $disabled = FALSE;
                $html .= $this->newHtmlContentRadio("RegisterTeamCar",
                                                    (string) $tc->id(),
                                                    $skin_img,
                                                    $checked,
                                                    $disabled);
                $any_team_car_found |= TRUE;
            }
        }
        $html .= "</div>";

        // consolation message
        if (!$any_team_car_found) {
            $html .= "<br><br>";
            $html .= _("You either have no permission to manage a team or none of your teams has a car in the correct class");
            $html .= "<br><br>";
            $html .= _("Checkout the tems here") . ": ";
            $html .= "<a href=\"{$this->url([], 'Teams')}\">" . _("Teams") . "</a>";
        }

        // save button
        $html .= "<br>";
        $html .= "<br>";
        $html .= "<button type=\"submit\" name=\"Action\" value=\"RegisterTeam\">" . _("Save Registration") . "</button>";
        $html .= "<br>";

        $html .= "</form>";
        return $html;
    }


    // edit settings of a race series season
    private function gehtHtmlSeasonEdit() : string {
        $html = "";

        // breadcrumps
        $url = $this->url(['RSerSeries'=>0]);
        $html .= "<a href=\"$url\">" . _("Race Series List") . "</a> &lt;&lt; ";
        $url = $this->url(['RSerSeries'=>$this->CurrentSeries->id()]);
        $html .= "<a href=\"$url\">{$this->CurrentSeries->name()}</a> &lt;&lt; ";
        $url = $this->url(['RSerSeries'=>$this->CurrentSeries->id(),
                           "RSerSeason"=>$this->CurrentSeason->id(),
                           "View"=>"SeasonOverview"]);
        $html .= "<a href=\"$url\">" . _("Season") . " {$this->CurrentSeason->name()}</a> &lt;&lt; ";
        $html .= _("Season Edit");
        $html .= "<br>";

        // header
        $html .= $this->getHtmlPageHeader();

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

            // valuation
            $html .= "<tr><th>" . _("Valuation") . "</th><td colspan=\"2\">";
            $valuation = $events[$event_idx]->valuation() * 100;
            $html .= "<input type=\"number\" name=\"RSerEvent{$events[$event_idx]->id()}Valuation\" value=\"$valuation\" min=\"-999\" max=\"999\">";
            $html .= " [%]";
            $html .= "</td></tr>";

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
            // $html .= "<tr><td><a href=\"$url\">" . _("Add Spit") . "</a></td></tr>";

            // delete event
            $html .= "<tr><td></td><td colspan=\"2\">";
            $html .= $this->newHtmlForm("POST");
            $html .= "<input type=\"hidden\" name=\"RSerEvent\" value=\"{$events[$event_idx]->id()}\">";
            $html .= "<button type=\"submit\" name=\"View\" value=\"AskDeleteEvent\">" . _("Delete Event") . "</button>";
            $html .= "</form>";
            $html .= "</td></tr>";

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


    // edit settings of a race series season
    private function getHtmlSeasonOverview() : string {
        $html = "";
        $cu = \Core\UserManager::currentUser();

        // breadcrumps
        $url = $this->url(['RSerSeries'=>0]);
        $html .= "<a href=\"$url\">" . _("Race Series List") . "</a> &lt;&lt; ";
        $url = $this->url(['RSerSeries'=>$this->CurrentSeries->id()]);
        $html .= "<a href=\"$url\">{$this->CurrentSeries->name()}</a> &lt;&lt; ";
        $html .= _("Season") . " ";
        $html .= $this->CurrentSeason->name();
        $html .= "<br>";

        // Header
        $html .= $this->getHtmlPageHeader();

        // edit season
        if ($this->CanEdit) {
            $url = $this->url(['RSerSeries'=>$this->CurrentSeries->id(),
                                "RSerSeason"=>$this->CurrentSeason->id(),
                                "View"=>"SeasonEdit"]);
            $html .= "<a href=\"$url\">" . _("Edit Season") . "</a> ";
            $html .= "<br><br>";
        }

        // update results
        if ($this->CanEdit) {
            $url = $this->url(['RSerSeries'=>$this->CurrentSeries->id(),
                                "RSerSeason"=>$this->CurrentSeason->id(),
                                "Action"=>"UpdateSeasonResults",
                                "View"=>"SeasonOverview"]);
            $html .= "<a href=\"$url\">" . _("Update Season Results") . "</a> ";
            $html .= "<br><br>";
        }

        // standings
        $html .= "<h1>" . _("Standings") . "</h1>";

        // overall standings
        $html .= $this->getHtmlOverallStandings($this->CurrentSeason);

        // driver ranking per class
        foreach ($this->CurrentSeries->listClasses(active_only:FALSE) as $rs_class) {

            // only  if class has active registrations
            $html .= "<h2>" . _("Driver Ranking") . " - {$rs_class->name()}</h2>";
            $html .= $this->getHtmlDriverRanking($this->CurrentSeason, $rs_class);

        }

        // // per class
        // foreach ($this->CurrentSeries->listClasses(active_only:FALSE) as $rs_class) {
        //
        //     $standings = $this->CurrentSeason->listStandings($rs_class);
        //
        //     // skip classes without active registrations
        //     $has_active_registrations = FALSE;
        //     foreach ($standings as $std) {
        //         if ($std->registration()->active()) {
        //             $has_active_registrations = TRUE;
        //             break;
        //         }
        //     }
        //     if (!$has_active_registrations) continue;
        //
        //     $html .= "<h2>{$rs_class->name()}</h2>";
        //     $html .= "<table>";
        //     // $html .= "<caption>{$rser_c->name()} <small>({$rser_c->carClass()->name()})</small></caption>";
        //     $html .= "<tr>";
        //     $html .= "<th>" . _("Pos") . "</th>";
        //     $html .= "<th colspan=\"2\">" . _("Entry") . "</th>";
        //     foreach ($this->CurrentSeason->listEvents() as $rs_event) {
        //         $css_class = ($rs_event->valuation() == 0.0) ? " class=\"Unscored\"":"";
        //         $url = $this->url(["RSerEvent"=>$rs_event->id(),
        //                             "View"=>"EventOverview"]);
        //         $valuation = $rs_event->valuation() * 100;
        //         $html .= "<th$css_class><a href=\"$url\">E{$rs_event->order()}<br><small>{$valuation}&percnt;</small></a></th>";
        //     }
        //     $html .= "<th>" . _("Points") . "</th>";
        //     $html .= "<th>" . _("BOP") . "</th>";
        //     $html .= "</tr>";
        //
        //     foreach ($standings as $std) {
        //         $reg = $std->registration();
        //         if (!$reg->active() && $std->points()==0) continue;
        //
        //         $html .= "<tr>";
        //         $html .= "<td>{$std->position()}</td>";
        //
        //         if ($reg->teamCar()) {
        //             $html .= "<td class=\"ZeroPadding\">{$reg->teamCar()->team()->html(TRUE, FALSE, FALSE, TRUE)}</td>";
        //             $html .= "<td>";
        //             $drivers = $reg->teamCar()->drivers();
        //             for ($i=0; $i < count($drivers); ++$i) {
        //                 $tmm = $drivers[$i];
        //                 if ($i > 0) $html .= ",<br>";
        //                 $html .= $tmm->user()->nationalFlag() . " ";
        //                 $html .= $tmm->user()->html();
        //             }
        //             $html .= "</td>";
        //         } else {
        //             $html .= "<td></td>";
        //             $html .= "<td>{$reg->user()->nationalFlag()} {$reg->user()->html()}</td>";
        //         }
        //
        //         foreach ($this->CurrentSeason->listEvents() as $rs_event) {
        //             $css_class = ($rs_event->valuation() == 0.0) ? " class=\"Unscored\"":"";
        //             $html .= "<td$css_class>";
        //             $rslt = $rs_event->getResult($reg);
        //             if ($rslt) $html .= $rslt->pointsValuated();
        //             $html .= "</td>";
        //         }
        //
        //         $html .= "<td>{$std->points()}</td>";
        //         $html .= "<td>{$reg->bopBallast()}kg / {$reg->bopRestrictor()}&percnt;</td>";
        //
        //         $html .= "</tr>";
        //     }
        //
        //     $html .= "</table>";
        // }

        // events
        $html .= "<h1>" . _("Events") . "</h1>";
        $html .= "<table>";
        $html .= "<tr>";
        $html .= "<th>" . _("ID") . "</th>";
        $html .= "<th>" . _("Track") . "</th>";
        $html .= "<th colspan=\"3\">" . _("Splits") . "</th>";
        $html .= "</tr>";
        foreach ($this->CurrentSeason->listEvents() as $rs_event) {
            $splits = $rs_event->listSplits();
            $rowspan = count($splits);
            if ($rowspan == 0) $rowspan = 1;

            $css_score_class = ($rs_event->valuation() == 0.0) ? "Unscored":"";
            $html .= "<tr class=\"$css_score_class\">";

            $url = $this->url(["RSerEvent"=>$rs_event->id(),
                                "View"=>"EventOverview"]);
            $html .= "<td rowspan=\"$rowspan\"><a href=\"$url\">E{$rs_event->order()}</a></td>";

            $html .= "<td rowspan=\"$rowspan\" class=\"ZeroPadding\">{$rs_event->track()->html(TRUE, TRUE, FALSE)}</td>";
            if (count($splits) > 0) {
                $html .= "<td>" . _("Split") . " 1</td>";
                $html .= "<td>{$cu->formatDateTimeNoSeconds($splits[0]->start())}</td>";
                $html .= "<td>{$splits[0]->serverSlot()->name()}</td>";
            }
            $html .= "</tr>";

            for ($split_idx=1; $split_idx < count($splits); ++$split_idx) {
                $html .= "<tr class=\"$css_score_class\">";
                $html .= "<td>" . _("Split") . " " . ($split_idx+1) . "</td>";
                $html .= "<td>{$cu->formatDateTimeNoSeconds($splits[$split_idx]->start())}</td>";
                $html .= "<td>{$splits[$split_idx]->serverSlot()->name()}</td>";
                $html .= "</tr>";
            }
        }
        $html .= "</table>";

        // registrations
        $html .= "<h1>" . _("Registrations") . "</h1>";

        // list classes
        foreach ($this->CurrentSeason->listClasses() as $rser_c) {
            $html .= "<div class=\"TableWrapper\">";

            // current registrations
            $html .= "<table>";
            $html .= "<caption>{$rser_c->name()} <small>({$rser_c->carClass()->name()})</small></caption>";
            $html .= "<tr><th>" . _("ID") . "</th><th>" . _("Team") . "</th><th>" . _("Car") . "</th><th>" . _("Drivers") . "</th><td></td></tr>";
            foreach ($this->CurrentSeason->listRegistrations($rser_c) as $rser_reg) {
                if (!$rser_reg->active()) continue;
                $html .= "<tr>";

                if ($rser_reg->teamCar()) {
                    $html .= "<td>{$rser_reg->id()}</td>";
                    $html .= "<td class=\"ZeroPadding\">{$rser_reg->teamCar()->team()->html(TRUE, FALSE, TRUE, FALSE)}</td>";
                    $html .= "<td class=\"ZeroPadding\">{$rser_reg->teamCar()->carSkin()->html(TRUE, FALSE, TRUE)}</td>";
                    $html .= "<td>";
                    $drivers = $rser_reg->teamCar()->drivers();
                    for ($i=0; $i < count($drivers); ++$i) {
                        $tmm = $drivers[$i];
                        if ($i > 0) $html .= ",<br>";
                        $html .= $tmm->user()->nationalFlag() . " ";
                        $html .= $tmm->user()->html();
                    }
                    $html .= "</td>";

                    // unregister team
                    $tmm = $rser_reg->teamCar()->team()->findMember(\Core\UserManager::currentUser());
                    if ($tmm && $tmm->permissionManage()) {
                        $url = $this->url(['RSerSeries'=>$this->CurrentSeries->id(),
                                           "RSerSeason"=>$this->CurrentSeason->id(),
                                           "RSerRegistration"=>$rser_reg->id(),
                                           "Action"=>"UnregisterTeamCar"]);
                        $html .= "<td><a href=\"$url\" title=\"" . _("Unregister") . "\" class=\"Unregister\">&#x2716;</a></td>";
                    }


                } else {
                    $html .= "<td>{$rser_reg->id()}</td>";
                    $html .= "<td></td>";
                    $html .= "<td class=\"ZeroPadding\">{$rser_reg->carSkin()->html(TRUE, FALSE, TRUE)}</td>";
                    $html .= "<td>{$rser_reg->user()->nationalFlag()} {$rser_reg->user()->html()}</td>";

                    // unregister driver
                    if ($rser_reg->user() == \Core\UserManager::currentUser()) {
                        $url = $this->url(['RSerSeries'=>$this->CurrentSeries->id(),
                                           "RSerSeason"=>$this->CurrentSeason->id(),
                                           "RSerRegistration"=>$rser_reg->id(),
                                           "Action"=>"UnregisterDriver"]);
                        $html .= "<td><a href=\"$url\" title=\"" . _("Unregister") . "\" class=\"Unregister\">&#x2716;</a></td>";
                    }
                }


                $html .= "</tr>";
            }
            $html .= "</table>";

            // registration
            if ($rser_c->active()) {

                // teams
                $url = $this->url(['RSerSeries'=>$this->CurrentSeries->id(),
                                    "RSerSeason"=>$this->CurrentSeason->id(),
                                    "RSerClass"=>$rser_c->id(),
                                    "View"=>"SeasonRegisterTeam"]);
                $html .= "<a href=\"$url\">" . _("Register Team") . "</a> ";

                // signle driver
                $url = $this->url(['RSerSeries'=>$this->CurrentSeries->id(),
                                    "RSerSeason"=>$this->CurrentSeason->id(),
                                    "RSerClass"=>$rser_c->id(),
                                    "View"=>"SeasonRegisterDriver"]);
                $html .= "<a href=\"$url\">" . _("Register Single Driver") . "</a> ";
            }

            $html .= "</div>";
        }

        return $html;
    }


    // edit settings of a race series
    private function getHtmlSeriesEdit() : string {
        $html = "";

        // breadcrumps
        $url = $this->url(['RSerSeries'=>0]);
        $html .= "<a href=\"$url\">" . _("Race Series List") . "</a> &lt;&lt; ";
        $url = $this->url(['RSerSeries'=>$this->CurrentSeries->id()]);
        $html .= "<a href=\"$url\">{$this->CurrentSeries->name()}</a> &lt;&lt; ";
        $html .= _("Race Series Edit");
        $html .= "<br>";

        // heading
        $html .= $this->getHtmlPageHeader();

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

            $html .= "<br>" . _("Fixed Setup") . ":<br>";
            $html .= "<textarea name=\"FixedSetup_{$rser_c->id()}\">{$rser_c->fixedSetup()}</textarea>";
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



    // Show a certain race series
    private function gehtHtmlSeriesOverview() : string {
        $html = "";

        // breadcrumps
        $url = $this->url(['RSerSeries'=>0]);
        $html .= "<a href=\"$url\">" . _("Race Series List") . "</a> &lt;&lt; ";
        $html .= $this->CurrentSeries->name();
        $html .= "<br>";

        // Header
        $html .= $this->getHtmlPageHeader();

        // get some informations
        $server_preset = $this->CurrentSeries->parameterCollection()->child("SessionPresetRace")->serverPreset();
        $next_split = $this->CurrentSeries->nextSplit();
        $any_slot_uses_real_penalty = FALSE;
        $next_event = NULL;
        if ($next_split) {
            $next_event = $next_split->event();
            foreach ($next_event->listSplits() as $split) {
                if ($split->serverSlot()->parameterCollection()->child("RPGeneralEnable")->value()) {
                    $any_slot_uses_real_penalty = TRUE;
                    break;
                }
            }
        }
        $race_is_lap_based = ($server_preset->parameterCollection()->child("AcServerRaceLaps")->value() > 0) ? TRUE : FALSE;



        // --------------------------------------------------------------------
        //  Edit Option
        // --------------------------------------------------------------------

        if ($this->CanEdit) {
            $url = $this->url(['RSerSeries'=>$this->CurrentSeries->id(),
                               'View'=>"SeriesEdit"]);
            $html .= "<a href=\"$url\">" . _("Edit Race Series") . "</a> ";

            // add season
            if ($this->CanCreate) {
                $url = $this->url(['RSerSeries'=>$this->CurrentSeries->id(),
                                   'Action'=>"CreateNewSeason"]);
                $html .= "<a href=\"$url\">" . _("Create New Season") . "</a> ";
            }
        }

        // --------------------------------------------------------------------
        //  Submission
        // --------------------------------------------------------------------

        $html .= "<h1>" . _("Submission") . "</h1>";

        $html .= "<table id=\"Points\">";
        $html .= "<caption>" . _("Result Assessment") . "</caption>";
        $html .= "<tr>";
        $html .= "<th>" . _("Race Result Points") . "</th>";
        $html .= "<td>";
        // $html .= "<div id=\"RaceResultPointsList\">";
        for ($position=1; TRUE; ++$position) {
            $points = $this->CurrentSeries->raceResultPoints($position);
            $html .= "<div>$position: <strong>{$points}</strong>pts</div>";
            if ($points <= 0) break;
        }
        // $html .= "</div>";
        $html .= "</td>";
        $html .= "</tr>";
        $html .= "<tr>";
        $html .= "<th>" . _("In-Class team assessment") . "</th>";
        $html .= "<td>{$this->CurrentSeries->parameterCollection()->child('PtsClassSum')->valueLabel()}</td>";
        $html .= "</tr>";

        // car classes
        $html .= "<table id=\"CarClasses\">";
        $html .= "<caption>" . _("Car Classes") . "</caption>";
        $html .= "<tr>";
        $html .= "<th colspan=\"2\">" . _("Car Class") . "</th>";
        $html .= "<th>" . _("BOP") . "</th>";
        $html .= "</tr>";
        foreach ($this->CurrentSeries->listClasses() as $rser_c) {
            $html .= "<tr>";
            $html .= "<td>{$rser_c->name()} [ID {$rser_c->id()}]</td>";
            $html .= "<td>{$rser_c->carClass()->html(TRUE, FALSE, TRUE)}</td>";
            $html .= "<td>";
            $html .= "<div id=\"RaceResultPointsList\">";
            $pos_end = 1 + max($rser_c->getParam("BopRestrictorPosition"), $rser_c->getParam("BopBallastPosition"));
            for ($position=1; $position <= $pos_end; ++$position) {
                $ballast= $rser_c->bopBallast($position);
                $restrictor= $rser_c->bopRestrictor($position);
                $html .= "<div>$position: <strong>{$ballast}kg {$restrictor}&percnt;</strong></div>";
            }
            $html .= "</div>";
            $html .= "</td>";
            $html .= "</tr>";
        }
        $html .= "</table>";



        // --------------------------------------------------------------------
        //  Settings
        // --------------------------------------------------------------------

        $html .= "<h1>" . _("Settings") . "</h1>";

        $html .= "<table id=\"ServerSettings\">";
        $html .= "<caption>" . _("Server Settings") . "</caption>";

        // damage
        $html .= "<tr>";
        $html .= "<td>" . _("Damage Multiplier") . "</td>";
        $html .= "<td>{$server_preset->parameterCollection()->child("AcServerDamageMultiplier")->valueLabel()} &percnt;</td>";
        $html .= "</tr>";

        // // fuel
        // $html .= "<tr>";
        // $html .= "<td>" . _("Fuel Rate") . "</td>";
        // $html .= "<td>{$server_preset->parameterCollection()->child("AcServerFuelRate")->valueLabel()} &percnt;</td>";
        // $html .= "</tr>";

        // // tyres
        // $html .= "<tr>";
        // $html .= "<td>" . _("Tyre Wear Rate") . "</td>";
        // $html .= "<td>{$server_preset->parameterCollection()->child("AcServerTyreWearRate")->valueLabel()} &percnt;</td>";
        // $html .= "</tr>";

        // pit window
        $html .= "<tr>";
        $html .= "<td>" . _("Pit Window") . "</td>";
        $html .= "<td>{$server_preset->parameterCollection()->child("AcServerPitWinOpen")->valueLabel()} ... ";
        $html .= "{$server_preset->parameterCollection()->child("AcServerPitWinClose")->valueLabel() } ";
        $html .= ($race_is_lap_based) ? _("Laps") : _("Minutes");
        $html .= "</td>";
        $html .= "</tr>";

        // Extra Lap
        $html .= "<tr>";
        $html .= "<td>" . _("Extra Lap") . "</td>";
        $html .= "<td>{$server_preset->parameterCollection()->child("AcServerExtraLap")->valueLabel()}</td>";
        $html .= "</tr>";

        // Reversed Grid
        $html .= "<tr>";
        $html .= "<td>" . _("Reversed Grid") . "</td>";
        $html .= "<td>{$server_preset->parameterCollection()->child("AcServerReversedGrid")->valueLabel()}</td>";
        $html .= "</tr>";

        $html .= "</table>";


        $html .= "<table id=\"PenaltySettings\">";
        $html .= "<caption>" . _("Penalty Settings") . "</caption>";

        // Min Laps
        $html .= "<tr>";
        $html .= "<td>" . _("DNF Mininum Laps") . "</td>";
        $html .= "<td>{$server_preset->parameterCollection()->child("AccswuiAutoDnfLevel")->valueLabel()} &percnt;</td>";
        $html .= "</tr>";

        // Allowed Tyres Out
        $html .= "<tr>";
        $html .= "<td>" . _("Allowed Tyres Out") . "</td>";
        $html .= "<td>{$server_preset->parameterCollection()->child("AcServerTyresOut")->valueLabel()} " . _("Tyres") . "</td>";
        $html .= "</tr>";

        if ($any_slot_uses_real_penalty) {

            // Laps To Take Penalty
            $html .= "<tr>";
            $html .= "<td>" . _("Laps To Take Penalty") . "</td>";
            $html .= "<td>{$server_preset->parameterCollection()->child("RpPsGeneralLapsToTake")->valueLabel()} " . _("Laps") . "</td>";
            $html .= "</tr>";

            // Total Cut Warnings
            $html .= "<tr>";
            $html .= "<td>" . _("Total Cut Warnings") . "</td>";
            $html .= "<td>{$server_preset->parameterCollection()->child("RpPsCuttingTotCtWarn")->valueLabel()}</td>";
            $html .= "</tr>";

            // Pit Speed Limit
            $html .= "<tr>";
            $html .= "<td>" . _("Pit Speed Limit") . "</td>";
            $html .= "<td>{$server_preset->parameterCollection()->child("RpPsSpeedingPitLaneSpeed")->valueLabel()} km/h</td>";
            $html .= "</tr>";

            // Minimum Pit Speed Penalty
            $html .= "<tr>";
            $html .= "<td>" . _("Minimum Pit Speed Penalty") . "</td>";
            $html .= "<td>{$server_preset->parameterCollection()->child("RpPsSpeedingPenType0")->valueLabel()}</td>";
            $html .= "</tr>";
        }

        $html .= "</table>";


        // --------------------------------------------------------------------
        //  Schedule
        // --------------------------------------------------------------------

        $html .= "<h1>" . _("Schedule") . "</h1>";

        // list schedule for all splits of next event
        if ($next_event) {

            $html .= "<p>";
            $html .= _("Virtual session start time") . ": ";
            $html .= $server_preset->parameterCollection()->child("SessionStartTime")->valueLabel();
            $html .= " (" . _("Time Factor") . " x";
            $html .= $server_preset->parameterCollection()->child("AcServerTimeMultiplier")->valueLabel();
            $html .= ")";
            $html .= "</p>";

            $html .= "<p>";
            $html .= _("The following table(s) show the schedule for the next event.");
            $html .= "</p>";

            foreach ($next_event->listSplits() as $split) {

                $schedule = $server_preset->schedule();
                $html .= "<table id=\"EstimatedSchedule\">";
                $html .= "<caption>{$next_event->season()->name()} E{$next_event->order()}S{$split->order()}</caption>";
                $html .= "<tr>";
                $html .= "<th>" . _("Start") . "</th>";
                $html .= "<th>" . _("Entry") . "</th>";
                $html .= "<th>" . _("Duration") . "</th>";
                $html .= "</tr>";

                // iterate over schedule items
                $time = $split->start();
                foreach ($schedule as [$interval, $uncertainty, $type, $name]) {

                    // only show short schedule
                    if ($type != \Enums\SessionType::Invalid) {
                        $html .= "<tr>";
                        $html .= "<td>" . \Core\UserManager::currentUser()->formatDateTimeNoSeconds($time) . "</td>";
                        $html .= "<td>$name</td>";
                        $html .= "<td>" . \Core\UserManager::currentUser()->formatTimeInterval($interval) . "</td>";
                        $html .= "</tr>";
                    }

                    // add duration to next start time
                    $time->add($interval->toDateInterval());
                }

                $html .= "</table>";
            }
        } else {
            $html .= _("No upcomming event scheduled");
        }


        // --------------------------------------------------------------------
        //  List Seasons
        // --------------------------------------------------------------------

        $html .= "<h1>" . _("Seasons") . "</h1>";
        $is_first = TRUE;
        foreach (\DbEntry\RSerSeason::listSeasons($this->CurrentSeries) as $rser_s) {

            if (!$is_first) $html .= ", ";

            $url = $this->url(['RSerSeries'=>$this->CurrentSeries->id(),
                                "RSerSeason"=>$rser_s->id(),
                                "View"=>"SeasonOverview"]);

            $html .= "<a href=\"$url\">{$rser_s->name()}</a>";

            $is_first &= FALSE;
        }


        return $html;
    }
}
