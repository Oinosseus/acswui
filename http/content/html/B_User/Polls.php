<?php

namespace Content\Html;

class Polls extends \core\HtmlContent {

    private $CanVote = FALSE;
    private $CanEdit = FALSE;
    private $CurrentPoll = NULL;


    public function __construct() {
        parent::__construct(_("Polls"),  _("Polls"));
        $this->requirePermission("User_Polls_View");
        $this->addScript("polls.js");
    }



    public function getHtml() {
        $this->CanVote = \Core\UserManager::loggedUser()->permitted("User_Polls_Vote");
        $this->CanEdit = \Core\UserManager::loggedUser()->permitted("User_Polls_Edit");
        $html = "";


        // --------------------------------------------------------------------
        //                          Process Form Data
        // --------------------------------------------------------------------

        // get requested poll
        if (array_key_exists('PollId', $_REQUEST)) {
            $this->CurrentPoll = \DbEntry\Poll::fromId($_REQUEST['PollId']);
        }

        // SavePollEdit
        if (array_key_exists("SavePollEdit", $_POST)) {
            $this->processSavePollEdit();
        }

        // SaveAddedTracks
        if (array_key_exists("SaveAddedTracks", $_POST)) {
            $this->processSaveAddedTracks();
        }

        // SaveAddedCarClasses
        if (array_key_exists("SaveAddedCarClasses", $_POST)) {
            $this->processSaveAddedCarClasses();
        }

        // SavePollVotes
        if (array_key_exists("SavePollVotes", $_POST)) {
            $this->processSavePollVotes();
        }


        // --------------------------------------------------------------------
        //                  Call Requested Action
        // --------------------------------------------------------------------

        // create new poll
        if (array_key_exists("Action", $_REQUEST) && $_REQUEST['Action'] == "CreateNewPoll" && $this->CanEdit) {
            $this->CurrentPoll = \DbEntry\Poll::createNew();
            $html .= $this->getHtmlEditPoll();

        // add track
        } else if (array_key_exists("Action", $_REQUEST) && $_REQUEST['Action'] == "EditTracks") {
            $html .= $this->getHtmlEditTracks();

        // add car class
        } else if (array_key_exists("Action", $_REQUEST) && $_REQUEST['Action'] == "EdirCarClasses") {
            $html .= $this->getHtmlEdirCarClasses();

        // edit poll
        } else if (array_key_exists("Action", $_REQUEST) && $_REQUEST['Action'] == "EditPoll") {
            $html .= $this->getHtmlEditPoll();

        // show poll
        } else if (array_key_exists("Action", $_REQUEST) && $_REQUEST['Action'] == "ShowPoll") {
            $html .= $this->getHtmlShowPoll();

        // delete poll
        } else if (array_key_exists("Action", $_REQUEST) && $_REQUEST['Action'] == "AskDeletePoll") {
            $html .= $this->getHtmlAskDeletePoll();
        } else if (array_key_exists("Action", $_REQUEST) && $_REQUEST['Action'] == "DoDeletePoll") {
            $this->processDoDeletePoll();
            $html .= $this->getHtmlListPolls();

        // list polls
        } else {
            $html .= $this->getHtmlListPolls();
        }


        return $html;
    }



    private function getHtmlAskDeletePoll() {
        $p = $this->CurrentPoll;
        if ($this->CurrentPoll === NULL) return "";
        if (!$this->CanEdit) return "";
        $html = "<h1>" . $p->name() . "</h1>";

        $html .= _("Do you really want to delete the Poll?");

        $html .= $this->newHtmlForm("POST");
        $html .= "<input type=\"hidden\" name=\"PollId\" value=\"" . $p->id() . "\">";
        $html .= "<button type=\"submit\" name=\"Action\" value=\"DoDeletePoll\">" . _("Yes") . "</button>";
        $html .= "<button type=\"submit\" name=\"Action\" value=\"EditPoll\">" . _("No") . "</button>";
        $html .= "</form>";


        return $html;
    }



    private function getHtmlEdirCarClasses() {
        $p = $this->CurrentPoll;
        if ($this->CurrentPoll === NULL) return "";
        if ($p->isClosed()) return "";
        if (!$this->CanEdit) return "";

        $html = "<h1>" . $p->name() . "</h1>";

        $html .= "<form action=\"\" method=\"post\">";
        $html .= "<input type=\"hidden\" name=\"SaveAddedCarClasses\" value=\"True\">";
        $html .= "<input type=\"hidden\" name=\"Action\" value=\"EditPoll\">";
        $html .= "<input type=\"hidden\" name=\"PollId\" value=\"" . $p->id() . "\">";
        $html .= "<button type=\"submit\">" . _("Save") . "</button><br>";

        # remember existing tracks
        $existing_carclasses = array();
        foreach ($p->carClasses() as $cc) {
            $existing_carclasses[] = $cc->id();
        }

        # list all tracks
        foreach (\DbEntry\CarClass::listClasses() as $cc) {

            $input_id = "CarClassId" . $cc->id();
            $html .= $this->newHtmlContentCheckbox($input_id,
                                                   $cc->html(FALSE, TRUE, TRUE),
                                                   in_array($cc->id(), $existing_carclasses));

//             $html .= "<div class=\"poll_item\">";
//             $html .= "<input type=\"checkbox\" id=\"$input_id\" name=\"$input_id\" value=\"TRUE\" $checked>";
//             $html .= "<label for=\"$input_id\">";
//             $html .= "<div class=\"poll_item_name\">" . $cc->name() . "</div>";
//             $html .= $cc->html(FALSE, TRUE, TRUE);
//             $html .= "</label>";
//             $html .= "</div>";

        }

        $html .= "<br><button type=\"submit\">" . _("Save") . "</button>";
        $html .= "</form>";

        return $html;
    }



    private function getHtmlEditTracks() {
        $p = $this->CurrentPoll;
        if ($this->CurrentPoll === NULL) return "";
        if ($p->isClosed()) return "";
        if (!$this->CanEdit) return "";

        $html = "<h1>" . $p->name() . "</h1>";

        $html .= "<form action=\"\" method=\"post\">";
        $html .= "<input type=\"hidden\" name=\"SaveAddedTracks\" value=\"True\">";
        $html .= "<input type=\"hidden\" name=\"Action\" value=\"EditPoll\">";
        $html .= "<input type=\"hidden\" name=\"PollId\" value=\"" . $p->id() . "\">";
        $html .= "<button type=\"submit\">" . _("Save") . "</button><br>";

        # remember existing tracks
        $existing_tracks = array();
        foreach ($p->tracks() as $t) {
            $existing_tracks[] = $t->id();
        }

        # list all tracks
        $last_section = "";
        foreach (\DbEntry\Track::listTracks() as $t) {

            // check for section
            $current_section = strtoupper(substr($t->name(), 0, 1));
            if ($current_section != $last_section) {
                $html .= "<h2>$current_section</h2>";
                $last_section = $current_section;
            }

            $input_id = "TrackId" . $t->id();
            $html .= $this->newHtmlContentCheckbox($input_id,
                                                   $t->html(FALSE, TRUE, TRUE),
                                                   in_array($t->id(), $existing_tracks));
        }

        $html .= "<br><button type=\"submit\">" . _("Save") . "</button>";
        $html .= "</form>";

        return $html;
    }


    private function getHtmlListPolls() {
        $html = "";

        $html .= "<table>";

        $html .= "<tr>";
        $html .= "<th>" . _("Id") . "</th>";
        $html .= "<th>" . _("Name") . "</th>";
//         $html .= "<th>" . _("Creator") . "</th>";
        $html .= "<th>" . _("Votes") . "</th>";
        $html .= "<th>" . _("Closing Date") . "</th>";
//         if ($this->CanEdit) {
//             $html .= "<th>" . _("Edit") . "</th>";
//         }
        $html .= "</tr>";

        foreach (\DbEntry\Poll::listPolls() as $p) {
            $class = ($p->isClosed()) ? "ClosedPoll":"";
//             $creator = ($p->creator() !== NULL) ? $p->creator()->displayName() : "?";

            $html .= "<tr class=\"$class\">";
            $html .= "<td>" . $p->id() . "</td>";
            $html .= "<td><a href=\"{$this->url(['Action'=>'ShowPoll', 'PollId'=>$p->id()])}\">" . $p->name() . "</a></td>";
//             $html .= "<td>$creator</td>";
            $html .= "<td>" . count($p->votedUsers()) . "</td>";
            $html .= "<td>" . \Core\UserManager::currentUser()->formatDateTime($p->closing()) . "</td>";
            if ($this->CanEdit) {
                $html .= "<td>";
                $html .= "<a href=\"{$this->url(['PollId'=>$p->id(), 'Action'=>'EditPoll'])}\">&#x1f4dd;</a>";
                $html .= "</td>";
//                 $html .= "<td><form action=\"\" method=\"post\">";
//                 $html .= "<input type=\"hidden\" name=\"Action\" value=\"EditPoll\">";
//                 $html .= "<input type=\"hidden\" name=\"PollId\" value=\"" . $p->id() . "\">";
//                 $html .= "<button type=\"submit\">" . _("Edit") . "</button>";
//                 $html .= "</form></td>";
            }
            $html .= "</tr>";
        }

        $html .= "</table>";

        // button to add new item
        if ($this->CanEdit) {
            $html .= "<br>";
            $html .= $this->newHtmlForm("POST");
            $html .= "<input type=\"hidden\" name=\"Action\" value=\"CreateNewPoll\">";
            $html .= "<button type=\"submit\">" . _("Create New Poll") . "</button>";
            $html .= "</form>";
        }

        return $html;
    }

    private function getHtmlShowPoll() {
        $poll = $this->CurrentPoll;
        if ($poll === NULL) return "";
        $u = \Core\UserManager::currentUser();
        $html = "";


        // --------------------------------------------------------------------
        //                           Voting Form
        // --------------------------------------------------------------------

        $html .= "<h1>" . $poll->name() . "</h1>";

        $html .= "<p>";
        $html .= nl2br(htmlentities($poll->description()));
        $html .= "</p>";

        if (!$poll->isClosed() && $this->CanVote) {
            $html .= $this->newHtmlForm("POST");
            $html .= "<input type=\"hidden\" name=\"PollId\" value=\"{$poll->id()}\">";
            $html .= "<input type=\"hidden\" name=\"Action\" value=\"ShowPoll\">";
            $html .= "<button type=\"submit\" name=\"SavePollVotes\" value=\"True\">" . _("Save Poll") . "</button>";
        }


        // tracks
        $tracks = $poll->tracks();
        if (!$poll->isClosed() && $this->CanVote && count($tracks)) {
            $ppt = $poll->pointsPerTrack();
            $pft = $poll->pointsForTracks();

            $html .= "<h2>" . _("Vote Tracks") . "</h2>";

            $html .= "<p>";
            $html .= _("Points per track") . ": $ppt<br>";
            $html .= _("Points in sum") . ": $pft<br>";
            $html .= _("Points left to vote") . ": <span id=\"PointsTrackLeftToVote\">$pft</span><br>";
            $html .= "</p>";

            foreach ($tracks as $t) {
                $points = $poll->pointsOfTrack($u, $t);
                $iname = "PointsTrackId" . $t->id();

                $html .= "<div class=\"PollItem\">";
                $html .= $t->html();
                $html .= "<input type=\"range\" min=\"0\" max=\"$ppt\" step=\"1\" value=\"$points\" name=\"$iname\" id=\"$iname\">";
                $html .= "</div>";
            }

        }

        // java script global vars for tracks
        $html .= "<script>";
        $PointsTrackId = [];
        if (!$poll->isClosed() && $this->CanVote) {
            foreach ($tracks as $t) {
                $PointsTrackId[] = "'PointsTrackId" . $t->id() . "'";
            }
        }
        $html .= "let PointsTrackId = [" . implode(", ", $PointsTrackId) . "]\n";
        $html .= "let PointsForTracks = " . $poll->pointsForTracks() . ";\n";
        $html .= "</script>";


        // car classes
        $carclasses = $poll->carClasses();
        if (!$poll->isClosed() && $this->CanVote && count($carclasses)) {
            $ppcc = $poll->pointsPerCarClass();
            $pfcc = $poll->pointsForCarClasses();

            $html .= "<h2>" . _("Vote Car Classes") . "</h2>";

            $html .= "<p>";
            $html .= _("Points per car class") . ": $ppcc<br>";
            $html .= _("Points in sum") . ": $pfcc<br>";
            $html .= _("Points left to vote") . ": <span id=\"PointsCarClassLeftToVote\">$pfcc</span><br>";
            $html .= "</p>";

            foreach ($carclasses as $cc) {
                $points = $poll->pointsOfCarClass($u, $cc);
                $iname = "PointsCarClassId" . $cc->id();

                $html .= "<div class=\"PollItem\">";
                $html .= $cc->html();
                $html .= "<input type=\"range\" min=\"0\" max=\"$ppcc\" step=\"1\" value=\"$points\" name=\"$iname\" id=\"$iname\">";
                $html .= "</div>";
            }

        }

        // java script global vars for carclasses
        $html .= "<script>";
        $PointsCarClassId = [];
        if (!$poll->isClosed() && $this->CanVote) {
            foreach ($carclasses as $t) {
                $PointsCarClassId[] = "'PointsCarClassId" . $t->id() . "'";
            }
        }
        $html .= "let PointsCarClassId = [" . implode(", ", $PointsCarClassId) . "]\n";
        $html .= "let PointsForCarClasses = " . $poll->pointsForCarClasses() . ";\n";
        $html .= "</script>";



        // --------------------------------------------------------------------
        //                             Poll Results
        // --------------------------------------------------------------------

        if ($poll->isClosed() || !$poll->isSecret()) {

            // track results
            if (count($tracks) > 0) {
                $html .= "<h2>" . _("Result Tracks") . "</h2>";
                $html .= "<table>";
                $html .= "<tr>";
                $html .= "<th>" . _("Pos") . "</th>";
                $html .= "<th>" . _("Sum Points") . "</th>";
                $html .= "<th colspan=\"2\">" . _("Track") . "</th>";
                $html .= "<th>" . _("Users Voted") . "</th>";
                $html .= "<th>" . _("My Points") . "</th>";
                $html .= "</tr>";
                $pos = 0;
                foreach ($poll->tracksOrdered() as $t) {
                    $pos += 1;

                    // find voted users
                    $user_votes = "";
                    $user_votes_sum = 0;
                    if ($t) {  // track can be NULL if deleted
                        foreach ($poll->votedUsers(TRUE) as $user) {
                            $user_vote = $poll->pointsOfTrack($user, $t);
                            if ($user_vote > 0) {
                                $user_votes_sum += 1;
                                $user_votes .= $user->name() . ": $user_vote\n";
                            }
                        }
                    }

                    $html .= "<tr>";
                    $html .= "<td>$pos</td>";
                    if ($t) {  // track can be NULL if deleted
                        $html .= "<td>" . $poll->pointsOfTrack(NULL, $t) . "</td>";
                        $html .= "<td class=\"TrackCell\">" . $t->html(TRUE, FALSE, TRUE) . "</td>";
                        $html .= "<td>" . $t->html(TRUE, TRUE, FALSE) . "</td>";
                        $html .= "<td><span title=\"$user_votes\">$user_votes_sum</span></td>";
                        $html .= "<td>" . $poll->pointsOfTrack($u, $t) . "</td>";
                    } else {
                        $html .= "<td colspan=\"2\">" . _("invalid track") . "</td>";
                    }
                    $html .= "</tr>";
                }
                $html .= "<table>";
            }


            // carclass results
            if (count($carclasses) > 0) {
                $html .= "<h2>" . _("Result CarClasses") . "</h2>";
                $html .= "<table>";
                $html .= "<tr>";
                $html .= "<th>" . _("Pos") . "</th>";
                $html .= "<th>" . _("Sum Points") . "</th>";
                $html .= "<th colspan=\"2\">" . _("Car Class") . "</th>";
                $html .= "<th>" . _("Users Voted") . "</th>";
                $html .= "<th>" . _("My Points") . "</th>";
                $html .= "</tr>";
                $pos = 0;
                foreach ($poll->carClassesOrdered() as $cc) {
                    $pos += 1;

                    // find voted users
                    $user_votes = "";
                    $user_votes_sum = 0;
                    if ($cc) {  // carclass can be NULL if deleted
                        foreach ($poll->votedUsers(TRUE) as $user) {
                                $user_vote = $poll->pointsOfCarClass($user, $cc);
                            if ($user_vote > 0) {
                                $user_votes_sum += 1;
                                $user_votes .= $user->name() . ": $user_vote\n";
                            }
                        }
                    }

                    $html .= "<tr>";
                    $html .= "<td>$pos</td>";
                    if ($cc) {  // carclass can be NULL if deleted
                        $html .= "<td>" . $poll->pointsOfCarClass(NULL, $cc) . "</td>";
                        $html .= "<td class=\"CarClassCell\">" . $cc->html(TRUE, FALSE, TRUE) . "</td>";
                        $html .= "<td>" . $cc->html(TRUE, TRUE, FALSE) . "</td>";
                        $html .= "<td class=\"CarClassCell\"><span title=\"$user_votes\">$user_votes_sum</span></td>";
                        $html .= "<td>" . $poll->pointsOfCarClass($u, $cc) . "</td>";
                    } else {
                        $html .= "<td colspan=\"2\">" . _("invalid car class") . "</td>";
                    }
                    $html .= "</tr>";
                }
                $html .= "<table>";
            }

        }

        return $html;
    }



    private function getHtmlEditPoll() {
        if ($this->CurrentPoll === NULL) return "";
        if (!$this->CanEdit) return "";

        $p = $this->CurrentPoll;
        $html = "";

        $html .= $this->newHtmlForm("POST");
        $html .= "<input type=\"hidden\" name=\"SavePollEdit\" value=\"True\">";
        $html .= "<input type=\"hidden\" name=\"PollId\" value=\"" . $p->id() . "\">";
        $html .= "<button type=\"submit\" name=\"Action\" value=\"EditPoll\">" . _("Save Poll") . "</button>";

        $close_disabled = ($p->isClosed()) ? "disabled" : "";

        // --------------------------------------------------------------------
        //                       General Settings
        // --------------------------------------------------------------------

        $html .= "<fieldset>";
        $html .= "<legend>" . _("General Settings") . "</legend>";
        $html .= "<table>";

        $html .= "<tr><th>" . _("Name") . "</th>";
        $html .= "<td><input type=\"text\" name=\"PollName\" maxlength=\"50\" value=\"" . $p->name() . "\" $close_disabled></td></tr>";

        $html .= "<tr><th>" . _("Secret Poll") . "</th>";
        $checked = ($p->isSecret()) ? "checked" : "";
        $html .= "<td><input type=\"checkbox\" name=\"PollIsSecret\" value=\"TRUE\" $checked $close_disabled></td></tr>";

        $html .= "<tr><th>" . _("Poll Closing") . "</th><td>";
        $closing_date = $p->closing()->setTimezone(new \DateTimezone(\Core\UserManager::currentUser()->getParam("UserTimezone")));
        $html .= "<input type=\"date\" name=\"PollClosingDate\" value=\"" . $closing_date->format("Y-m-d") . "\">";
        $html .= " ";
        $html .= "<input type=\"time\" name=\"PollClosingTime\" value=\"" . $closing_date->format("H:i") . "\">";
        $html .= "</td></tr>";

        $html .= "<tr><th>" . _("Description") . "</th>";
        $html .= "<td><textarea name=\"PollDescription\" $close_disabled>" . $p->description() . "</textarea></td></tr>";

        $html .= "</table>";
        $html .= "</fieldset>";




        // --------------------------------------------------------------------
        //                              Tracks
        // --------------------------------------------------------------------

        $html .= "<fieldset>";
        $html .= "<legend>" . _("Tracks") . "</legend>";
        $html .= "<table>";

        $html .= "<tr><th>" . _("Points for all Tracks") . "</th>";
        $html .= "<td><input type=\"number\" name=\"PollPointsForTracks\" min=\"0\" step=\"1\" value=\"" . $p->pointsForTracks() . "\" $close_disabled></td></tr>";

        $html .= "<tr><th>" . _("Points per Track") . "</th>";
        $html .= "<td><input type=\"number\" name=\"PollPointsPerTrack\" min=\"0\" step=\"1\" value=\"" . $p->pointsPerTrack() . "\" $close_disabled></td></tr>";


        $html .= "<tr><td colspan=\"2\">";
        foreach ($p->tracks() as $t) {
            if ($t) {  // can be deleted over time
                $html .= "<div class=\"PollItem\">";
                $html .= $t->html();
                $html .= "</div>";
            }
        }
        $html .= "</td></tr>";
        $html .= "</table>";
        $html .= "</fieldset>";

        if (!$p->isClosed()) {
            $html .= "<button type=\"submit\" name=\"Action\" value=\"EditTracks\">" . _("Edit Tracks") . "</button>";
        }



        // --------------------------------------------------------------------
        //                              Car Classes
        // --------------------------------------------------------------------

        $html .= "<fieldset>";
        $html .= "<legend>" . _("Car Classes") . "</legend>";
        $html .= "<table>";

        $html .= "<tr><th>" . _("Points for all Car Classes") . "</th>";
        $html .= "<td><input type=\"number\" name=\"PollPointsForCarClasses\" min=\"0\" step=\"1\" value=\"" . $p->pointsForCarClasses() . "\" $close_disabled></td></tr>";

        $html .= "<tr><th>" . _("Points per Car Class") . "</th>";
        $html .= "<td><input type=\"number\" name=\"PollPointsPerCarClass\" min=\"0\" step=\"1\" value=\"" . $p->pointsPerCarClass() . "\" $close_disabled></td></tr>";


        $html .= "<tr><td colspan=\"2\">";
        foreach ($p->carClasses() as $cc) {
            if ($cc) {  // can be NULL when deleted over time
                $html .= "<div class=\"PollItem\">";
                $html .= $cc->html();
                $html .= "</div>";
            }
        }
        $html .= "</td></tr>";
        $html .= "</table>";
        $html .= "</fieldset>";

        if (!$p->isClosed()) {
            $html .= "<button type=\"submit\" name=\"Action\" value=\"EdirCarClasses\">" . _("Edit Car Classes") . "</button>";
        }

        $html .= "</form>";


        // --------------------------------------------------------------------
        //                           Delete Poll
        // --------------------------------------------------------------------

        $html .= $this->newHtmlForm("POST");
        $html .= "<input type=\"hidden\" name=\"PollId\" value=\"" . $p->id() . "\">";
        $html .= "<button type=\"submit\" name=\"Action\" value=\"AskDeletePoll\">" . _("Delete Poll") . "</button>";
        $html .= "</form>";



        return $html;
    }



    private function processDoDeletePoll() {
        $p = $this->CurrentPoll;
        if ($this->CanEdit) {
            $p->deleteFromDb();
            $this->CurrentPoll = NULL;
        }
    }


    private function processSavePollEdit() {
        $p = $this->CurrentPoll;
        if ($this->CanEdit) {

            if (!$p->isClosed()) {
                $p->setName($_POST['PollName']);
                $p->setDescription($_POST['PollDescription']);
                $p->setSecret(array_key_exists("PollIsSecret", $_POST));
                $p->setPointsForTracks($_POST['PollPointsForTracks']);
                $p->setPointsPerTrack($_POST['PollPointsPerTrack']);
                $p->setPointsForCarClasses($_POST['PollPointsForCarClasses']);
                $p->setPointsPerCarClass($_POST['PollPointsPerCarClass']);
            }

            $new_closing = $_POST["PollClosingDate"] . " " . $_POST["PollClosingTime"] . ":00";
            $tz = new \DateTimezone(\Core\UserManager::currentUser()->getParam("UserTimezone"));
            $new_closing = new \DateTime($new_closing, $tz);
            $p->setClosing($new_closing);
        }
    }


    private function processSaveAddedCarClasses() {
        $p = $this->CurrentPoll;
        if ($this->CanEdit) {
            if (!$p->isClosed()) {
                foreach (\DbEntry\CarClass::listClasses() as $cc) {
                    $input_id = "CarClassId" . $cc->id();
                    if (array_key_exists($input_id, $_POST)) {
                        $p->addCarClass($cc);
                    } else {
                        $p->removeCarClass($cc);
                    }
                }
            }
        }
    }


    private function processSaveAddedTracks() {
        $p = $this->CurrentPoll;
        if ($this->CanEdit) {
            if (!$p->isClosed()) {
                foreach (\DbEntry\Track::listTracks() as $t) {
                    $input_id = "TrackId" . $t->id();
                    if (array_key_exists($input_id, $_POST)) {
                        $p->addTrack($t);
                    } else {
                        $p->removeTrack($t);
                    }
                }
            }
        }
    }



    private function processSavePollVotes() {
        $p = $this->CurrentPoll;
        $user = \Core\UserManager::currentUser();

        if (!$p->isClosed()) {

            // save tracks
            $track_votes = array();
            foreach ($p->tracks() as $t) {
                $track_votes[$t->id()] = (int) $_POST["PointsTrackId" . $t->id()];
            }
            $p->saveTrackVotes($user, $track_votes);

            // save car classes
            $carclass_votes = array();
            foreach ($p->carClasses() as $cc) {
                $carclass_votes[$cc->id()] = (int) $_POST["PointsCarClassId" . $cc->id()];
            }
            $p->saveCarClassVotes($user, $carclass_votes);
        }
    }
}
