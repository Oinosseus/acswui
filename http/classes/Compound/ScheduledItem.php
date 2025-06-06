<?php

declare(strict_types=1);
namespace Compound;

/**
 * This either represents a \DbEntry\SessionSchedule or a \DbEntry\RSerSplit
 */
class ScheduledItem {

    // SessionSchedule
    private static $CacheSessionSchedule = array();
    private $SessionSchedule = NULL;

    // RSerSplit
    private static $CacheRSerSplit = array();
    private $RSerSplit = NULL;

    // independent cache
    private $Start = NULL;



    private function __construct() {
    }


    //! @return The string representation of the table (just info, no serialization)
    public function __toString() {
        if ($this->SessionSchedule) {
            return "ScheduledItem[{$this->SessionSchedule}]";

        } else if ($this->RSerSplit) {
            return "ScheduledItem[{$this->RSerSplit}]";

        } else {
            // you should not end here
            \Core\Log::error("Unknown type!");
            return "ScheduledItem[???]";
        }
    }


    //! @return The BOP that shall be used
    public function bopMap() : \Core\BopMap {
        if ($this->SessionSchedule) {
            return $this->SessionSchedule->bopMap();

        } else if ($this->RSerSplit) {
            return $this->RSerSplit->event()->bopMap();

        } else {
            // you should not end here
            \Core\Log::error("Unknown type!");
            return new \Core\BopMap();
        }
    }


    //! @return A message that is transmitted to discord at the event start
    public function discordMessage() : string {
        $msg = "";

        // head
        if ($this->RSerSplit) {
            $msg .= "**{$this->RSerSplit->event()->season()->series()->name()}**\n";
            $msg .= _("Season") . ": {$this->RSerSplit->event()->season()->name()}\n";
            $msg .= "*E{$this->RSerSplit->event()->order()} S{$this->RSerSplit->order()}*\n";
        }
        if ($this->SessionSchedule) {
            $msg .= "**{$this->SessionSchedule->name()}**\n";
        }

        // settings
        $msg .= "\n";
        $msg .= "*" . _("Server Slot") . ": {$this->serverSlot()->name()}*\n";
        $msg .= "*" . _("Server Preset") . ": {$this->serverPreset()->name()}*\n";
        $msg .= "*" . _("Track") . ": {$this->track()->name()}*\n";
        if ($this->SessionSchedule)
            $msg .= "*" . _("Car Class") . ": {$this->SessionSchedule->carClass()->name()}*\n";

        // get carclass (if available)
        $carclass = NULL;
        if ($this->SessionSchedule)
            $carclass = $this->SessionSchedule->carClass();

        // schedule
        $schedules = $this->serverPreset()->schedule($this->track(), $carclass);
        $time = new \DateTime("now");
        for ($i = 0; $i < count($schedules); ++$i) {
            [$interval, $uncertainty, $type, $name] = $schedules[$i];

            if ($type == \Enums\SessionType::Invalid && ($i+1) < count($schedules)) continue; // do not care for intermediate break

            $content_line = \Core\UserManager::currentUser()->formatTimeNoSeconds($time) . " - $name";
            if (($i + 1) == count($schedules)) {
                $msg .= "*$content_line*";
            } else if ($type == \Enums\SessionType::Race) {
                $msg .= "**$content_line**";
            } else {
                $msg .= $content_line;
            }

            if (($i + 1) < count($schedules))
                $msg .= " *(" . \Core\UserManager::currentUser()->formatTimeInterval($interval) . ")*";
            $msg .= "\n";
            $time->add($interval->toDateInterval());
        }
        $msg .= "\n*" . _("Time Zone") . ": " . \Core\UserManager::currentUser()->getParam("UserTimezone") . "*\n";

        // done
        return $msg;
    }


    //! @return The entry list object to be used
    public function entryList() : \Core\EntryList {
        if ($this->SessionSchedule) {
            return $this->SessionSchedule->entryList();

        } else if ($this->RSerSplit) {
            return $this->RSerSplit->entryList();

        } else {
            // you should not end here
            \Core\Log::error("Unknown type!");
            return new \Core\EntryList();
        }
    }


    public static function fromSessionSchedule(\DbEntry\SessionSchedule $item) : ScheduledItem {

        if (!array_key_exists($item->id(), ScheduledItem::$CacheSessionSchedule)) {
            $se = new ScheduledItem();
            $se->SessionSchedule = $item;
            ScheduledItem::$CacheSessionSchedule[$item->id()] = $se;
        }

        return ScheduledItem::$CacheSessionSchedule[$item->id()];
    }


    public static function fromRSerSplit(\DbEntry\RSerSplit $item) : ScheduledItem {
        if (!array_key_exists($item->id(), ScheduledItem::$CacheRSerSplit)) {
            $se = new ScheduledItem();
            $se->RSerSplit = $item;
            ScheduledItem::$CacheRSerSplit[$item->id()] = $se;
        }

        return ScheduledItem::$CacheRSerSplit[$item->id()];
    }


    //! @return The wrapped RSerSplit object (can be NULL)
    public function getRSerSplit() : ?\DbEntry\RSerSplit {
        return $this->RSerSplit;
    }


    //! @return The wrapped SessionSchedule object (can be NULL)
    public function getSessionSchedule() : ?\DbEntry\SessionSchedule {
        return $this->SessionSchedule;
    }


    //! @return The schedule in very comapct format, HTML ready formatted
    public function htmlCompactSchedule() : string {
        $cuser = \Core\UserManager::currentUser();
        $html = "";

        // print date
        $time = $this->start();
        $html .= "<div class=\"CompoundScheduledItemCompactSchedule\">";
        $html .= "<div class=\"CompoundScheduledItemCompactScheduleDate\">" . $cuser->formatDate($time) . "</div>";

        // print time
        $schedules = $this->serverPreset()->schedule();
        for ($i = 0; $i < count($schedules); ++$i) {
            [$interval, $uncertainty, $type, $name] = $schedules[$i];

            switch ($type) {
                case \Enums\SessionType::Practice:
                    $html .= "<div class=\"CompoundScheduledItemCompactScheduleTime\">P: ". $cuser->formatTimeNoSeconds($time) . "</div>";
                    break;

                case \Enums\SessionType::Qualifying:
                    $html .= "<div class=\"CompoundScheduledItemCompactScheduleTime\">Q: ". $cuser->formatTimeNoSeconds($time) . "</div>";
                    break;

                case \Enums\SessionType::Race:
                    $html .= "<div class=\"CompoundScheduledItemCompactScheduleTime\">R: ". $cuser->formatTimeNoSeconds($time) . "</div>";
                    break;

                default:
                    break;
            }

            $time->add($interval->toDateInterval());
        }

        // done
        $html .= "</div>";
        return $html;
    }


    //! @return The Id of the referenced SessionSchedule element (can be 0)
    public function idSessionSchedule() : int {
        return ($this->SessionSchedule) ? $this->SessionSchedule->id() : 0;
    }


    //! @return The Id of the referenced RSerSplit element (can be 0)
    public function idSessionRSerSplit() : int {
        return ($this->RSerSplit) ? $this->RSerSplit->id() : 0;
    }


    //! @return A list of CarClass objects that were used in this scheduled item
    public function listCarClasses() : array {
        $car_classes = array();
        if ($this->SessionSchedule) {
            $car_classes[] = $this->SessionSchedule->carClass();
        }
        if ($this->RSerSplit) {
            foreach ($this->RSerSplit->event()->season()->series()->listClasses() as $rsc) {
                $car_classes[] = $rsc->carClass();
            }
        }
        return $car_classes;
    }


    /**
     * List ScheduledItem objects
     *
     * @param $server_slot If given, only items for this slot are listed
     * @param $start_after Only items which are starting after this date are listed (NULL means all items)
     * @param $only_not_executed If TRUE (default FALSE), then only items which are not already executed are returned
     * @return A list of all schedule events
     */
    public static function listItems(?\Core\ServerSlot $server_slot=NULL,
                                     ?\DateTime $start_after=NULL,
                                     bool $only_not_executed=FALSE) : array {

        if ($start_after) $start_after = \Core\Database::timestamp($start_after);
        $list = array();

        // find all SessionSchedule items
        $query = "SELECT Id FROM SessionSchedule WHERE Id>0";
        if ($server_slot) $query .= " AND Slot={$server_slot->id()}";
        if ($start_after) $query .= " AND Start>='$start_after'";
        if ($only_not_executed) $query .= " AND Executed<Start";
        foreach (\Core\Database::fetchRaw($query) as $row) {
            $item = \DbEntry\SessionSchedule::fromId((int) $row['Id']);
            $list[] = ScheduledItem::fromSessionSchedule($item);
        }

        // find all RSerSplit items
        $query = "SELECT RSerSplits.Id FROM RSerSplits INNER JOIN RSerEvents ON RSerEvents.Id=RSerSplits.Event WHERE RSerSplits.Id>0 AND RSerEvents.Season>0";
        if ($server_slot) $query .= " AND RSerSplits.ServerSlot={$server_slot->id()}";
        if ($start_after) $query .= " AND RSerSplits.Start>='$start_after'";
        if ($only_not_executed) $query .= " AND RSerSplits.Executed<Start";
        foreach (\Core\Database::fetchRaw($query) as $row) {
            $item = \DbEntry\RSerSplit::fromId((int) $row['Id']);
            $list[] = ScheduledItem::fromRSerSplit($item);
        }

        // sort by start
        usort($list, "\\Compound\\ScheduledItem::usortByStartAsc");

        return $list;
    }


    //! @return The name
    public function name() : string {
        if ($this->SessionSchedule) {
            return $this->SessionSchedule->name();

        } else if ($this->RSerSplit) {
            $split = $this->RSerSplit;
            $event = $split->event();
            $season = $event->season();
            $series = $season->series();
            return "{$series->name()} / {$season->name()} / E{$event->order()}/S{$split->order()}";

        } else {
            // you should not end here
            \Core\Log::error("Unknown type!");
            return "";
        }
    }


    //! @return A HTML string including the name and a link
    public function nameLink() : string {
        if ($this->SessionSchedule) {
            $url = "index.php?HtmlContent=SessionSchedules&SessionSchedule={$this->SessionSchedule->id()}&Action=ShowRoster";
            $label = $this->SessionSchedule->name();
            return "<a href=\"$url\">$label</a>";

        } else if ($this->RSerSplit) {
            $split = $this->RSerSplit;
            $event = $split->event();
            $season = $event->season();
            $series = $season->series();
            $url = "index.php?HtmlContent=RSer&RSerSeries={$series->id()}&RSerSeason={$season->id()}&View=SeasonOverview";
            $label = $series->name() . "<br>";
            $label .= "<small>{$season->name()} - E{$event->order()}/S{$split->order()}</small>";
            return "<a href=\"$url\">$label</a>";

        } else {
            // you should not end here
            \Core\Log::error("Unknown type!");
            return "";
        }
    }


    //! @return TRUE when SessionSchedule->start() is before current time
    public function obsolete() {
        return $this->start() < new \DateTime("now");
    }


    //! @return A ServerPreset, if this schedule item has a practice- or qualification-loop before the actual event start
    public function preloopServerPreset() : ?\DbEntry\ServerPreset {
        $preset = NULL;

        if ($this->SessionSchedule) {
            if ($this->SessionSchedule->getParamValue("PracticeEna")) {
                $preset = $this->SessionSchedule->parameterCollection()->child("PracticePreset")->serverPreset();
            }

        } else if ($this->RSerSplit) {
            $series = $this->RSerSplit->event()->season()->series();
            $preset = $series->parameterCollection()->child("SessionPresetQual")->serverPreset();

        } else {
            // you should not end here
            \Core\Log::error("Unknown type!");
        }

        return $preset;
    }


    /**
     * Check if a user is registered
     * @param $user The requested user
     * @return TRUE if the user is registered
     */
    public function registered(\DbEntry\User $user) : bool {
        if ($this->SessionSchedule) {
            $ssr = \DbEntry\SessionScheduleRegistration::getRegistrations($this->SessionSchedule, $user);
            return count($ssr) > 0;

        } else if ($this->RSerSplit) {
            $regs = $this->RSerSplit->event()->season()->listRegistrations(NULL, TRUE);
            $registered = FALSE;
            foreach ($regs as $reg) {
                if ($reg->user() ==$user) $registered = TRUE;
                if ($reg->teamCar()) {
                    foreach ($reg->teamCar()->drivers() as $d) {
                        if ($d->user() ==$user) {
                            $registered = TRUE;
                            break;
                        }
                    }
                }
                if ($registered) break;
            }
            return $registered;

        } else {
            // you should not end here
            \Core\Log::error("Unknown type!");
            return FALSE;
        }
    }


    //! @return A list of CarSkin objects that are used for registrations
    public function registeredCarSkins() : array {
        $carskins = array();

        if ($this->SessionSchedule) {
            foreach (\DbEntry\SessionScheduleRegistration::listRegistrations($this->SessionSchedule) as $reg) {
                $carskins[] = $reg->carSkin();
            }
        } else if ($this->RSerSplit) {
            $rser_season = $this->RSerSplit->event()->season();
            foreach ($rser_season->listRegistrations(NULL, TRUE) as $rser_reg) {
                $carskins[] = $rser_reg->carSkin();
            }
        }

        return $carskins;
    }


    //! @return The amount of drivers who are registered
    public function registrations() : int {
        if ($this->SessionSchedule) {
            return count($this->SessionSchedule->registrations());

        } else if ($this->RSerSplit) {
            return count($this->RSerSplit->event()->season()->listRegistrations(NULL, TRUE));

        } else {
            // you should not end here
            \Core\Log::error("Unknown type!");
            return NULL;
        }
    }


    //! @return Can be used by usort() to order ScheduledItem objects by their start time
    public static function usortByStartAsc(ScheduledItem $a, ScheduledItem $b) : int {
        if ($a->start() < $b->start()) return -1;
        else if ($a->start() > $b->start()) return 1;
        else return 0;
    }


    //! @return The assigned ServerPreset object
    public function serverPreset() : ?\DbEntry\ServerPreset {
        if ($this->SessionSchedule) {
            return $this->SessionSchedule->serverPreset();

        } else if ($this->RSerSplit) {
            $series = $this->RSerSplit->event()->season()->series();
            return \DbEntry\ServerPreset::fromId((int) $series->getParam("SessionPresetRace"));

        } else {
            // you should not end here
            \Core\Log::error("Unknown type!");
            return NULL;
        }
    }


    //! @return The assigned ServerSlot object
    public function serverSlot() : ?\Core\ServerSlot {
        if ($this->SessionSchedule) {
            return $this->SessionSchedule->serverSlot();

        } else if ($this->RSerSplit) {
            return $this->RSerSplit->serverSlot();

        } else {
            // you should not end here
            \Core\Log::error("Unknown type!");
            return NULL;
        }
    }


    /**
     * Set the SessionSchedule as executed
     * @param $t DateTime object of when the item has been executed
     */
    public function setExecuted(\DateTime $t) {
        if ($this->SessionSchedule) {
            return $this->SessionSchedule->setExecuted($t);

        } else if ($this->RSerSplit) {
            return $this->RSerSplit->setExecuted($t);

        } else {
            // you should not end here
            \Core\Log::error("Unknown type!");
        }
    }


    //! @return A DateTime object with the information when this item is planned to start
    public function start() : \DateTime {
        if ($this->SessionSchedule) return $this->SessionSchedule->start();
        if ($this->RSerSplit) return $this->RSerSplit->start();

        return NULL; // should never reach here
    }


    //! @return The track which is driven at this item
    public function track() : \DbEntry\Track {
        if ($this->SessionSchedule) {
            return $this->SessionSchedule->track();

        } else if ($this->RSerSplit) {
            return $this->RSerSplit->event()->track();

        } else {
            // you should not end here
            \Core\Log::error("Unknown type!");
            return NULL;
        }
    }
}
