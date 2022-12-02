<?php

namespace Core;

/**
 * This class can generate iCalendar entries as string
 */
class VEvent {


    private $DtStart = NULL;
    private $DtEnd = NULL;
    private $Summary = NULL;
    private $Description = NULL;
    private $Uid = NULL;
    private $Attendees = array();
    private $Location = NULL;


    /**
     * Creates a ICalendar object from a SessionSchedule object
     * @param $ss The SessionSchedule object
     * @param $user A user to dervie settings from (if NULL, the current logged user will be used)
     * @return A ICalendar object
     */
    public static function fromSessionSchedule(\DbEntry\SessionSchedule $ss, $user = NULL) {
        if ($user === NULL) $user = \Core\UserManager::currentUser();
        $ical = new VEvent();

        // start
        $ical->DtStart = $ss->start();
        $ical->DtStart->setTimezone(new \DateTimezone("UTC"));

        // UID
        $ical->Uid = $_SERVER['HTTP_HOST'] . "-SessionSchedule-" . $ss->id();

        // find all attendees
//         foreach ($ss->registrations() as $ssr) {
//             $ical->Attendees[] = $ssr->user()->name();
//         }
        $ical->Attendees[] = count($ss->registrations()) . " " . _("Drivers");

        // summary
        $ical->Summary = $ss->name();

        // location
        $ical->Location = $ss->track()->name();

        // time schedule / description
        $ical->Description = "";
        $t = $ss->start();
        $schedules = $ss->serverPreset()->schedule($ss->track(), $ss->carClass());
        for ($i = 0; $i < count($schedules); ++$i) {
            [$interval, $uncertainty, $type, $name] = $schedules[$i];
            if ($type == \Enums\SessionType::Invalid && ($i+1) < count($schedules)) continue; // do not care for intermediate break
            $ical->Description .= $user->formatTimeNoSeconds($t) . " - $name\\n";
            $t->add($interval->toDateInterval());
        }
        $ical->DtEnd = $t;
        $ical->DtEnd->setTimezone(new \DateTimezone("UTC"));

        return $ical;
    }


    //! @return The VEVENT icalendar as array string (each element one line)
    public function icsLines() : array {
        $lines = array();
        $now = new \DateTime("now", new \DateTimezone("UTC"));

        $lines[] = "BEGIN:VEVENT\r\n";
        $lines[] = "UID:{$this->Uid}\r\n";
        $lines[] = "LOCATION:{$this->Location}\r\n";
        $lines[] = "SUMMARY:{$this->Summary}\r\n";
        $lines[] = "DESCRIPTION:{$this->Description}\r\n";
        $lines[] = "CLASS:PUBLIC\r\n";
        $lines[] = "DTSTART:{$this->formatDT($this->DtStart)}\r\n";
        $lines[] = "DTEND:{$this->formatDT($this->DtEnd)}\r\n";
        $lines[] = "DTSTAMP:{$this->formatDT($now)}\r\n";
        foreach ($this->Attendees as $a) {
            $lines[] = "ATTENDEE:$a\r\n";
        }
        $lines[] = "END:VEVENT\r\n";

        return $lines;
    }


    private function formatDT(\DateTime $dt) {
        return $dt->format("Ymd") . "T" . $dt->format("His") . "Z";
    }

}
