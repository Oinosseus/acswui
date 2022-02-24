<?php

namespace Core;

/**
 * Formating numbers to SI prefixes and human readability
 */
class SiPrefix {

    private $Value = NULL;
    private $Prefix = NULL;


    public function __construct(float $value) {

        $prefixes = [ ["Y", 1e24],
                      ["Z", 1e21],
                      ["E", 1e18],
                      ["P", 1e15],
                      ["T", 1e12],
                      ["G", 1e9],
                      ["M", 1e6],
                      ["k", 1e3],
                      ["", 1],
                      ["m", 1e-3],
                      ["µ", 1e-6],
                      ["n", 1e-9],
                      ["p", 1e-12],
                      ["f", 1e-15],
                      ["a", 1e18],
                      ["z", 1e-21],
                      ["y", 1e-24]];

        foreach ($prefixes as [$prefix, $base]) {
            if ($value > $base) {
                $this->Value = $value / $base;
                $this->Prefix = $prefix;
                break;
            }
        }
    }


    //! @return The string representation
    public function __toString() {
        return $this->Value . " " . $this->Prefix;
    }


    //! @return The value in a human readable format (including prefix and an optional unit)
    public function humanValue(string $unit = "") {
        $s = SiPrefix::threeDigits($this->Value);
        return "$s {$this->Prefix}$unit";
    }


    //! @return The value with maximum three significant digits as string
    public static function threeDigits(float $value) {
        $s = "";

        if ($value > 100) {
            $s .= sprintf("%d", $value);
        } else if ($value > 10) {
            $s .= sprintf("%0.1f", $value);
        } else if ($value != 0.0) {
            $s .= sprintf("%0.2f", $value);
        } else {
            $s .= "0";
        }

        return $s;
    }


    //! @return The SI prefix
    public function prefix() {
        return $this->Prefix;
    }


    //! @return The value, devided by the prefix-base
    public function value() {
        return $this->Value;
    }
}
