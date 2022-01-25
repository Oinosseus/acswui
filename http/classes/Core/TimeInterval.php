<?php

namespace Core;


//! For ACswui better suitable variant of original \DateInterval class
class TimeInterval {

    private $Secs = 0;
    private $Mils = 0;


    //! @param seconds can contain milliseconds
    public function __construct(float $seconds=0) {
        $this->Secs = (int) $seconds;
        $this->Mils = (int) (($seconds - $this->Secs) * 1000000);
        while ($this->Mils > 1000000) {
            $this->Secs += 1;
            $this->Mils -= 1000000;
        }
    }


    /**
     * Add other interval to this
     * @param $other can be a TimeInterval object or a float as seconds
     */
    public function add($other) {
        if (is_float($other) || is_int($other)) {
            $other = new TimeInterval($other);
        }
        if (!($other instanceof TimeInterval)) {
            \Core\Log::error("Unexpected type: " . gettype($other));
        }

        $this->Secs += $other->Secs;
        $this->Mils += $other->Mils;
        while ($this->Mils > 1000000) {
            $this->Secs += 1;
            $this->Mils -= 1000000;
        }
    }


    //! @return The amount of milliseconds in this interval
    public function milliSecs() {
        return $this->Mils;
    }


    //! @return The amount of seconds in this interval
    public function seconds() {
        return $this->Secs;
    }


    //! An original php DateInterval object
    public function toDateInterval() {
        $remaining_seconds = $this->Secs;

        $seconds = $remaining_seconds % 60;
        $remaining_minutes = $remaining_seconds / 60;

        $minutes = $remaining_minutes % 60;
        $remaining_hours = $remaining_minutes / 60;

        $hours = $remaining_hours % 24;
        $remaining_days = $remaining_hours / 24;

        $duration = sprintf("P%dDT%dH%dM%dS", $remaining_days, $hours, $minutes, $seconds);
        return new \DateInterval($duration);
    }
}
