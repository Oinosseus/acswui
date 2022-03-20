<?php

namespace Core;


//! For ACswui better suitable variant of original \DateInterval class
class TimeInterval {

    private $Secs = 0;
    private $Mils = 0;


    //! @param seconds can contain milliseconds
    public function __construct(float $seconds=0) {
        $this->Secs = (int) $seconds;
        $this->Mils = (int) (($seconds - $this->Secs) * 1000);
        while ($this->Mils > 1000) {
            $this->Secs += 1;
            $this->Mils -= 1000;
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
        while ($this->Mils > 1000) {
            $this->Secs += 1;
            $this->Mils -= 1000;
        }
    }


    //! @return The amount of days in this interval (float)
    public function days() {
        return $this->Secs / 60 / 60 / 24;
    }


    //! @return A TimeInterval object
    public static function fromDateInterval(\DateInterval $i) {
        $factor = ($i->invert == 0) ? 1 : -1;

        $ti = new TimeInterval();
        $ti->Secs += $factor * $i->y * 365 * 24 * 60 * 60;
        $ti->Secs += $factor * $i->m * 30.5 * 24 * 60 * 60;
        $ti->Secs += $factor * $i->d * 24 * 60 * 60;
        $ti->Secs += $factor * $i->h * 60 * 60;
        $ti->Secs += $factor * $i->i * 60;
        $ti->Secs += $factor * $i->s;
        $ti->add($factor * $i->f / 1e6);

        return $ti;
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
