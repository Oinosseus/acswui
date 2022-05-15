<?php

namespace Core;

/**
 * This class can generate iCalendar entries as string
 */
class VCalendar {


    private $Events = array();


    public function addEvent(VEvent $e) {
        $this->Events[] = $e;
    }


    /**
     * @return A string in icalendar ics format
     */
    public function ics() : string {
        $lines = array();
        $now = new \DateTime("now", new \DateTimezone("UTC"));

        $lines[] = "BEGIN:VCALENDAR\r\n";
        $lines[] = "VERSION:2.0\r\n";
        $lines[] = "PRODID:{$_SERVER['HTTP_HOST']}-ACswui\r\n";
        $lines[] = "METHOD:PUBLISH\r\n";
        foreach ($this->Events as $e) {
            foreach ($e->icsLines() as $l) {
                $lines[]= $l;
            }
        }
        $lines[] = "END:VCALENDAR\r\n";

        // create string
        $s = "";
        foreach ($lines as $l) {
            $s .= self::chopLine75($l);
        }

        return $s;
    }


    /**
     * Chops a line to ensure it is not longer than 75 chars
     * @param $line The line that shall be chopped
     * @return A string with the chopped line
     */
    private static function chopLine75(string $line) : string {
        $ret = "";

        // line is too long
        if (strlen($line) > 75) {
            $splits = str_split($line, 72);
            $ret = implode("\r\n ", $splits);

        // line length is okay
        } else {
            $ret = $line;
        }

        return $ret;
    }
}
