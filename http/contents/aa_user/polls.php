<?php


class polls extends cContentPage {

    private $CurrentPoll = NULL;

    private $CanCreate = FALSE;
    private $CanManage = FALSE;
    private $CanVote = FALSE;

    public function __construct() {
        $this->MenuName   = _("Polls");
        $this->PageTitle  = "Polls";
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["Polls_View"];
    }



    private function canEditPoll(Poll $p) {
        global $acswuiUser;
        return ($p->creator() !== NULL && $p->creator()->id() == $acswuiUser->Id) ? TRUE : FALSE;
    }



    public function getHtml() {
        global $acswuiUser;

        $html = "";

        // find requested poll
        if (array_key_exists('PollId', $_REQUEST)) {
            $this->CurrentPoll = new Poll($_REQUEST['PollId']);
        }

        // check enhanced permissions
        if ($acswuiUser->hasPermission('Polls_Vote')) $this->CanVote = TRUE;
        if ($acswuiUser->hasPermission('Polls_Create')) $this->CanCreate = TRUE;
        if ($acswuiUser->hasPermission('Polls_Manage')) $this->CanManage = TRUE;


        // --------------------------------------------------------------------
        //                          Process Form Data
        // --------------------------------------------------------------------

        // SavePollEdit
        if (array_key_exists("SavePollEdit", $_POST)) {
            $p = $this->CurrentPoll;
            if ($this->CanManage || $this->canEditPoll($p)) {

                if (!$p->isClosed()) {
                    $p->setName($_POST['PollName']);
                    $p->setDescription($_POST['PollDescription']);
                    $p->setSecret(array_key_exists("PollIsSecret", $_POST));
                    $p->setPointsForTracks($_POST['PollPointsForTracks']);
                    $p->setPointsPerTrack($_POST['PollPointsPerTrack']);
                    $p->setPointsForCarClasses($_POST['PollPointsForCarClasses']);
                    $p->setPointsPerCarClass($_POST['PollPointsPerCarClass']);

                    // remove tracks
                    foreach ($p->tracks() as $t) {
                        $input_id = "TrackId" . $t->id();
                        if (!array_key_exists($input_id, $_POST)) {
                            $p->removeTrack($t);
                        }
                    }

                    // remove carclasses
                    foreach ($p->carClasses() as $cc) {
                        $input_id = "CarClassId" . $cc->id();
                        if (!array_key_exists($input_id, $_POST)) {
                            $p->removeCarClass($cc);
                        }
                    }
                }

                $new_closing = $_POST["PollClosingDate"] . " " . $_POST["PollClosingTime"] . ":00";
                $new_closing = new DateTime($new_closing);
                $p->setClosing($new_closing);
            }
        }

        // SaveAddedTracks
        if (array_key_exists("SaveAddedTracks", $_POST)) {
            $p = $this->CurrentPoll;
            if ($this->CanManage || $this->canEditPoll($p)) {
                if (!$p->isClosed()) {
                    foreach (Track::listTracks() as $t) {
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

        // SaveAddedCarClasses
        if (array_key_exists("SaveAddedCarClasses", $_POST)) {
            $p = $this->CurrentPoll;
            if ($this->CanManage || $this->canEditPoll($p)) {
                if (!$p->isClosed()) {
                    foreach (CarClass::listClasses() as $cc) {
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

        // SavePollVotes
        if (array_key_exists("SavePollVotes", $_POST)) {
            $p = $this->CurrentPoll;
            $user = $acswuiUser->user();

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


        // --------------------------------------------------------------------
        //                  Call Requested Action
        // --------------------------------------------------------------------

        // create new poll
        if (array_key_exists("Action", $_REQUEST) && $_REQUEST['Action'] == "CreateNewPoll" && $this->CanCreate) {
            $this->CurrentPoll = Poll::createNew();
            $html .= $this->getHtmlEditPoll();

        // add track
        } else if (array_key_exists("Action", $_REQUEST) && $_REQUEST['Action'] == "AddTracks"){
            $html .= $this->getHtmlAddTracks();

        // add car class
        } else if (array_key_exists("Action", $_REQUEST) && $_REQUEST['Action'] == "AddCarClasses"){
            $html .= $this->getHtmlAddCarClasses();

        // edit poll
        } else if (array_key_exists("Action", $_REQUEST) && $_REQUEST['Action'] == "EditPoll"){
            $html .= $this->getHtmlEditPoll();

        // show poll
        } else if (array_key_exists("Action", $_REQUEST) && $_REQUEST['Action'] == "ShowPoll"){
            $html .= $this->getHtmlShowPoll();

        // list polls
        } else {
            $html .= $this->getHtmlListPolls();
        }


        return $html;
    }



    private function getHtmlListPolls() {
        $html = "";

        $html .= "<table>";

        $html .= "<tr>";
        $html .= "<th>" . _("Id") . "</th>";
        $html .= "<th>" . _("Name") . "</th>";
        $html .= "<th>" . _("Creator") . "</th>";
        $html .= "<th>" . _("Votes") . "</th>";
        $html .= "<th>" . _("Closing Date") . "</th>";
        if ($this->CanManage) {
            $html .= "<th>" . _("Edit") . "</th>";
        }
        $html .= "</tr>";

        foreach (Poll::listPolls() as $p) {
            $class = ($p->isClosed()) ? "closed_poll":"";
            $creator = ($p->creator() !== NULL) ? $p->creator()->displayName() : "?";

            $html .= "<tr class=\"$class\">";
            $html .= "<td>" . $p->id() . "</td>";
            $html .= "<td><a href=\"?Action=ShowPoll&PollId=" . $p->id() . "\">" . $p->name() . "</a></td>";
            $html .= "<td>$creator</td>";
            $html .= "<td>" . count($p->votedUsers()) . "</td>";
            $html .= "<td>" . $p->closing()->format("Y-m-d H:i") . "</td>";
            if ($this->CanManage || $this->canEditPoll($p)) {
                $html .= "<td><form action=\"\" method=\"post\">";
                $html .= "<input type=\"hidden\" name=\"Action\" value=\"EditPoll\">";
                $html .= "<input type=\"hidden\" name=\"PollId\" value=\"" . $p->id() . "\">";
                $html .= "<button type=\"submit\">" . _("Edit") . "</button>";
                $html .= "</form></td>";
            }
            $html .= "</tr>";
        }

        $html .= "</table>";

        // button to add new item
        if ($this->CanCreate) {
            $html .= "<br>";
            $html .= "<form action=\"\" method=\"post\">";
            $html .= "<input type=\"hidden\" name=\"Action\" value=\"CreateNewPoll\">";
            $html .= "<button type=\"submit\">" . _("Create New Poll") . "</button>";
            $html .= "</form>";
        }

        return $html;
    }



    private function getHtmlEditPoll() {
        if ($this->CurrentPoll === NULL) return "";
        if (!$this->CanManage && !$this->canEditPoll($this->CurrentPoll)) return "";

        $p = $this->CurrentPoll;
        $html = "";

        $html .= "<form action=\"\" method=\"post\" id=\"EditPollForm\">";
        $html .= "<input type=\"hidden\" name=\"SavePollEdit\" value=\"True\">";
        $html .= "<input type=\"hidden\" name=\"Action\" value=\"EditPoll\">";
        $html .= "<input type=\"hidden\" name=\"PollId\" value=\"" . $p->id() . "\">";
        $html .= "<button type=\"submit\">" . _("Save Poll") . "</button>";

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
        $html .= "<input type=\"date\" name=\"PollClosingDate\" value=\"" . $p->closing()->format("Y-m-d") . "\">";
        $html .= " ";
        $html .= "<input type=\"time\" name=\"PollClosingTime\" value=\"" . $p->closing()->format("H:i") . "\">";
        $html .= "</td></tr>";

        $html .= "<tr><th>" . _("Description") . "</th>";
        $html .= "<td><textarea name=\"PollDescription\" $close_disabled>" . $p->description() . "</textarea></td></tr>";

        $html .= "</table>";
        $html .= "</fieldset>";

        $html .= "</form>";



        // --------------------------------------------------------------------
        //                              Tracks
        // --------------------------------------------------------------------

        $html .= "<fieldset>";
        $html .= "<legend>" . _("Tracks") . "</legend>";
        $html .= "<table>";

        $html .= "<tr><th>" . _("Points for all Tracks") . "</th>";
        $html .= "<td><input type=\"number\" name=\"PollPointsForTracks\" min=\"0\" step=\"1\" value=\"" . $p->pointsForTracks() . "\" form=\"EditPollForm\" $close_disabled></td></tr>";

        $html .= "<tr><th>" . _("Points per Track") . "</th>";
        $html .= "<td><input type=\"number\" name=\"PollPointsPerTrack\" min=\"0\" step=\"1\" value=\"" . $p->pointsPerTrack() . "\" form=\"EditPollForm\" $close_disabled></td></tr>";


        $html .= "<tr><td colspan=\"2\">";
        foreach ($p->tracks() as $t) {
            $input_id = "TrackId" . $t->id();
            $input_value = $t->id();

            $html .= "<div class=\"poll_item\">";
            $html .= "<input type=\"checkbox\" id=\"$input_id\" name=\"$input_id\" value=\"TRUE\" form=\"EditPollForm\" checked $close_disabled>";
            $html .= "<label for=\"$input_id\">";
            $html .= "<div class=\"track_name\">" . $t->name() . "</div>";
            $html .= $t->htmlImg("", 100);
            $html .= "</label>";
            $html .= "</div>";
        }
        $html .= "</td></tr>";
        $html .= "</table>";
        $html .= "</fieldset>";

        if (!$p->isClosed()) {
            $html .= "<form action=\"\" method=\"post\" id=\"AddTrackForm\">";
            $html .= "<input type=\"hidden\" name=\"Action\" value=\"AddTracks\">";
            $html .= "<input type=\"hidden\" name=\"PollId\" value=\"" . $p->id() . "\">";
            $html .= "<button type=\"submit\">" . _("Add Tracks") . "</button>";
            $html .= "</form>";
        }



        // --------------------------------------------------------------------
        //                              Car Classes
        // --------------------------------------------------------------------

        $html .= "<fieldset>";
        $html .= "<legend>" . _("Car Classes") . "</legend>";
        $html .= "<table>";

        $html .= "<tr><th>" . _("Points for all Car Classes") . "</th>";
        $html .= "<td><input type=\"number\" name=\"PollPointsForCarClasses\" min=\"0\" step=\"1\" value=\"" . $p->pointsForCarClasses() . "\" form=\"EditPollForm\" $close_disabled></td></tr>";

        $html .= "<tr><th>" . _("Points per Car Class") . "</th>";
        $html .= "<td><input type=\"number\" name=\"PollPointsPerCarClass\" min=\"0\" step=\"1\" value=\"" . $p->pointsPerCarClass() . "\" form=\"EditPollForm\" $close_disabled></td></tr>";


        $html .= "<tr><td colspan=\"2\">";
        foreach ($p->carClasses() as $cc) {
            $input_id = "CarClassId" . $cc->id();
            $input_value = $cc->id();

            $html .= "<div class=\"poll_item\">";
            $html .= "<input type=\"checkbox\" id=\"$input_id\" name=\"$input_id\" value=\"TRUE\" form=\"EditPollForm\" checked $close_disabled>";
            $html .= "<label for=\"$input_id\">";
            $html .= "<div class=\"poll_item_name\">" . $cc->name() . "</div>";
            $html .= $cc->htmlImg("", 100);
            $html .= "</label>";
            $html .= "</div>";
        }
        $html .= "</td></tr>";
        $html .= "</table>";
        $html .= "</fieldset>";

        if (!$p->isClosed()) {
            $html .= "<form action=\"\" method=\"post\" id=\"AddCarClassForm\">";
            $html .= "<input type=\"hidden\" name=\"Action\" value=\"AddCarClasses\">";
            $html .= "<input type=\"hidden\" name=\"PollId\" value=\"" . $p->id() . "\">";
            $html .= "<button type=\"submit\">" . _("Add Car Class") . "</button>";
            $html .= "</form>";
        }

        return $html;
    }



    private function getHtmlAddTracks() {
        $p = $this->CurrentPoll;
        if ($this->CurrentPoll === NULL) return "";
        if ($p->isClosed()) return "";
        if (!$this->CanManage && !$this->canEditPoll($p)) return "";

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
        foreach (Track::listTracks() as $t) {

            // check for section
            $current_section = strtoupper(substr($t->name(), 0, 1));
            if ($current_section != $last_section) {
                $html .= "<h2>$current_section</h2>";
                $last_section = $current_section;
            }

            $input_id = "TrackId" . $t->id();
            $input_value = $t->id();
            $checked = (in_array($t->id(), $existing_tracks)) ? "checked" : "";

            $html .= "<div class=\"poll_item\">";
            $html .= "<input type=\"checkbox\" id=\"$input_id\" name=\"$input_id\" value=\"TRUE\" $checked>";
            $html .= "<label for=\"$input_id\">";
            $html .= "<div class=\"poll_item_name\">" . $t->name() . "</div>";
            $html .= $t->htmlImg("", 100);
            $html .= "</label>";
            $html .= "</div>";

        }

        $html .= "<br><button type=\"submit\">" . _("Save") . "</button>";
        $html .= "</form>";

        return $html;
    }



    private function getHtmlAddCarClasses() {
        $p = $this->CurrentPoll;
        if ($this->CurrentPoll === NULL) return "";
        if ($p->isClosed()) return "";
        if (!$this->CanManage && !$this->canEditPoll($p)) return "";

        $p = $this->CurrentPoll;
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
        foreach (CarClass::listClasses() as $cc) {

            $input_id = "CarClassId" . $cc->id();
            $input_value = $cc->id();
            $checked = (in_array($cc->id(), $existing_carclasses)) ? "checked" : "";

            $html .= "<div class=\"poll_item\">";
            $html .= "<input type=\"checkbox\" id=\"$input_id\" name=\"$input_id\" value=\"TRUE\" $checked>";
            $html .= "<label for=\"$input_id\">";
            $html .= "<div class=\"poll_item_name\">" . $cc->name() . "</div>";
            $html .= $cc->htmlImg("", 100);
            $html .= "</label>";
            $html .= "</div>";

        }

        $html .= "<br><button type=\"submit\">" . _("Save") . "</button>";
        $html .= "</form>";

        return $html;
    }



    private function getHtmlShowPoll() {
        global $acswuiUser;
        if ($this->CurrentPoll === NULL) return "";

        $p = $this->CurrentPoll;
        $u = $acswuiUser->user();
        $html = "";


        // --------------------------------------------------------------------
        //                           Voting Form
        // --------------------------------------------------------------------

        $html .= '<script src="' . $this->getRelPath() . 'polls.js"></script>';
        $html .= "<h1>" . $p->name() . "</h1>";

        $html .= "<p>";
        $html .= nl2br(htmlentities($p->description()));
        $html .= "</p>";

        if (!$p->isClosed() && $this->CanVote) {
            $html .= "<form action=\"?Action=ShowPoll&PollId=" . $p->id() . "\" method=\"post\">";
            $html .= "<input type=\"hidden\" name=\"SavePollVotes\" value=\"True\">";
            $html .= "<button type=\"submit\">" . _("Save Poll") . "</button>";
        }


        // tracks
        $tracks = $p->tracks();
        if (!$p->isClosed() && $this->CanVote && count($tracks)) {
            $ppt = $p->pointsPerTrack();
            $pft = $p->pointsForTracks();

            $html .= "<h2>" . _("Vote Tracks") . "</h2>";

            $html .= "<p>";
            $html .= _("Points per track") . ": $ppt<br>";
            $html .= _("Points in sum") . ": $pft<br>";
            $html .= _("Points left to vote") . ": <span id=\"PointsTrackLeftToVote\" class=\"\">$pft</span><br>";
            $html .= "</p>";

            foreach ($tracks as $t) {
                $points = $p->pointsOfTrack($u, $t);
                $iname = "PointsTrackId" . $t->id();

                $html .= "<div class=\"poll_item\">";
                $html .= "<label>" . $t->name() . "<label>";
                $html .= $t->htmlImg("", 150);
                $html .= "<input type=\"range\" min=\"0\" max=\"$ppt\" step=\"1\" value=\"$points\" name=\"$iname\" id=\"$iname\">";
                $html .= "</div>";
            }

        }

        // java script global vars for tracks
        $html .= "<script>";
        $PointsTrackId = [];
        if (!$p->isClosed() && $this->CanVote) {
            foreach ($tracks as $t) {
                $PointsTrackId[] = "'PointsTrackId" . $t->id() . "'";
            }
        }
        $html .= "let PointsTrackId = [" . implode(", ", $PointsTrackId) . "]\n";
        $html .= "let PointsForTracks = " . $p->pointsForTracks() . ";\n";
        $html .= "</script>";


        // car classes
        $carclasses = $p->carClasses();
        if (!$p->isClosed() && $this->CanVote && count($carclasses)) {
            $ppcc = $p->pointsPerCarClass();
            $pfcc = $p->pointsForCarClasses();

            $html .= "<h2>" . _("Vote Car Classes") . "</h2>";

            $html .= "<p>";
            $html .= _("Points per car class") . ": $ppcc<br>";
            $html .= _("Points in sum") . ": $pfcc<br>";
            $html .= _("Points left to vote") . ": <span id=\"PointsCarClassLeftToVote\">$pfcc</span><br>";
            $html .= "</p>";

            foreach ($carclasses as $cc) {
                $points = $p->pointsOfCarClass($u, $cc);
                $iname = "PointsCarClassId" . $cc->id();

                $html .= "<div class=\"poll_item\">";
                $html .= "<label>" . $cc->name() . "<label>";
                $html .= $cc->htmlImg("", 150);
                $html .= "<input type=\"range\" min=\"0\" max=\"$ppcc\" step=\"1\" value=\"$points\" name=\"$iname\" id=\"$iname\">";
                $html .= "</div>";
            }

        }

        // java script global vars for carclasses
        $html .= "<script>";
        $PointsCarClassId = [];
        if (!$p->isClosed() && $this->CanVote) {
            foreach ($carclasses as $t) {
                $PointsCarClassId[] = "'PointsCarClassId" . $t->id() . "'";
            }
        }
        $html .= "let PointsCarClassId = [" . implode(", ", $PointsCarClassId) . "]\n";
        $html .= "let PointsForCarClasses = " . $p->pointsForCarClasses() . ";\n";
        $html .= "</script>";


        // save
        if (!$p->isClosed() && $this->CanVote) {
            $html .= "<br>";
            $html .= "<button type=\"submit\">" . _("Save Poll") . "</button>";
            $html .= "</form>";
        }



        // --------------------------------------------------------------------
        //                             Poll Results
        // --------------------------------------------------------------------

        if ($p->isClosed() || !$p->isSecret()) {

            // track results
            if (count($tracks) > 0) {
                $html .= "<h2>" . _("Result Tracks") . "</h2>";
                $html .= "<table>";
                $html .= "<tr>";
                $html .= "<th>" . _("Pos") . "</th>";
                $html .= "<th>" . _("Sum Points") . "</th>";
                $html .= "<th>" . _("Track") . "</th>";
                $html .= "<th>" . _("Users Voted") . "</th>";
                $html .= "<th>" . _("My Points") . "</th>";
                $html .= "</tr>";
                $pos = 0;
                foreach ($p->tracksOrdered() as $t) {
                    $pos += 1;

                    // find voted users
                    $user_votes = "";
                    $user_votes_sum = 0;
                    foreach ($p->votedUsers(TRUE) as $user) {
                        $user_vote = $p->pointsOfTrack($user, $t);
                        if ($user_vote > 0) {
                            $user_votes_sum += 1;
                            $user_votes .= $user->displayName() . ": $user_vote\n";
                        }
                    }

                    $html .= "<tr>";
                    $html .= "<td>$pos</td>";
                    $html .= "<td>" . $p->pointsOfTrack(NULL, $t) . "</td>";
                    $html .= "<td>" . $t->name() . "</td>";
                    $html .= "<td><span title=\"$user_votes\">$user_votes_sum</span></td>";
                    $html .= "<td>" . $p->pointsOfTrack($u, $t) . "</td>";
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
                $html .= "<th>" . _("Car Class") . "</th>";
                $html .= "<th>" . _("Users Voted") . "</th>";
                $html .= "<th>" . _("My Points") . "</th>";
                $html .= "</tr>";
                $pos = 0;
                foreach ($p->carClassesOrdered() as $cc) {
                    $pos += 1;

                    // find voted users
                    $user_votes = "";
                    $user_votes_sum = 0;
                    foreach ($p->votedUsers(TRUE) as $user) {
                        $user_vote = $p->pointsOfCarClass($user, $cc);
                        if ($user_vote > 0) {
                            $user_votes_sum += 1;
                            $user_votes .= $user->displayName() . ": $user_vote\n";
                        }
                    }

                    $html .= "<tr>";
                    $html .= "<td>$pos</td>";
                    $html .= "<td>" . $p->pointsOfCarClass(NULL, $cc) . "</td>";
                    $html .= "<td>" . $cc->name() . "</td>";
                    $html .= "<td><span title=\"$user_votes\">$user_votes_sum</span></td>";
                    $html .= "<td>" . $p->pointsOfCarClass($u, $cc) . "</td>";
                    $html .= "</tr>";
                }
                $html .= "<table>";
            }

        }

        return $html;
    }
}

?>
