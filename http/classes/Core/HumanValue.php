<?php

namespace Core;

/**
 * Formating numbers to friednly human readable format
 */
class HumanValue {

    private $Value = NULL;
    private $UnitPrefix = NULL;
    private $Unit = NULL;

    public function __construct($value, $unit="") {

        if ($unit === "LAPTIME") {
            $this->formatLaptime($value, $unit);

        } else if ($unit === "s") {
            $this->formatSeconds($value, $unit);

        } else if ($unit === "ms") {
            $this->formatSeconds($value / 1000, "s");

        } else if ($unit === "%") {
            $this->formatPercent($value, $unit);

        } else {
            $this->formatArbitrary($value, $unit);
        }

    }


    //! @return A string well formatted from the given value and unit
    public static function format($value, $unit="") {
        return (new HumanValue($value, $unit))->string();
    }

    //! @return A string well formatted from the given value and unit
    public function string() {
        return $this->Value . " " . $this->UnitPrefix . $this->Unit;
    }

    private function formatLaptime($laptime_ms, $unit) {
        $milliseconds = $laptime_ms % 1000;
        $laptime_ms /= 1000;
        $seconds = $laptime_ms % 60;
        $minutes = $laptime_ms / 60;
        $this->Value = sprintf("%0d:%02s.%03d" , $minutes, $seconds, $milliseconds);
        $this->Unit = "";
        $this->UnitPrefix = "";
    }

    private function formatSeconds(float $value, $unit) {
        if ($value >= 3153600e2) {
            $this->Value = sprintf("%d", $value / 3153600e2);
            $this->Unit = "y";

        } else if ($value >= 3153600e1) {
            $this->Value = sprintf("%0.1f", $value / 3153600e1);
            $this->Unit = "y";

        } else if ($value >= 3153600) {
            $this->Value = sprintf("%0.2f", $value / 3153600);
            $this->Unit = "y";

        } else if ($value >= 86400e2) {
            $this->Value = sprintf("%d", $value / 86400e2);
            $this->Unit = "d";

        } else if ($value >= 86400e1) {
            $this->Value = sprintf("%0.1f", $value / 86400e1);
            $this->Unit = "d";

        } else if ($value >= 86400) {
            $this->Value = sprintf("%0.2f", $value / 86400);
            $this->Unit = "d";

        } else if ($value >= 3600) {
            $seconds = $value % 60;
            $duration_minutes = ($value - $seconds) / 60;
            $minutes = $duration_minutes % 60;
            $hours = ($duration_minutes - $minutes) / 60;
            $this->Value = sprintf("%d:%02d", $hours, $minutes);
            $this->Unit = "h";

        } else if ($value >= 60) {
            $seconds = $value % 60;
            $minutes = ($value - $seconds) / 60;
            $this->Value = sprintf("%d:%02d", $minutes, $seconds);
            $this->Unit = "min";

        } else if ($value >= 10) {
            $seconds = $value;
            $this->Value = sprintf("%0.1f", $seconds);
            $this->Unit = "s";

        } else if ($value >= 1) {
            $seconds = $value;
            $this->Value = sprintf("%0.2f", $seconds);
            $this->Unit = "s";

        } else if ($value >= 1e-3) {
            $mseconds = $value * 1e3;
            $this->Value = sprintf("%d", $mseconds);
            $this->Unit = "ms";

        } else {
            $this->Value = $value;
            $this->Unit = "s";
        }

        $this->UnitPrefix = "";
    }

    private function formatPercent(float $value, $unit) {
        if ($value >= 100 || $value == 0) {
            $this->Value = sprintf("%d", $value);
            $this->UnitPrefix = "";
            $this->Unit = "&percnt;";
        } else if ($value >= 10) {
            $this->Value = sprintf("%0.1f", $value);
            $this->UnitPrefix = "";
            $this->Unit = "&percnt;";
        } else if ($value >= 1) {
            $this->Value = sprintf("%0.2f", $value);
            $this->UnitPrefix = "";
            $this->Unit = "&percnt;";
        } else if ($value >= 0.1) {
            $this->Value = sprintf("%0.2f", $value*10);
            $this->UnitPrefix = "";
            $this->Unit = "&permil;";
        } else if ($value >= 0.001) {
            $this->Value = sprintf("%d", $value*10e3);
            $this->UnitPrefix = "";
            $this->Unit = "ppm";
        } else {
            $this->Value = sprintf("%0.2f", $value*10e3);
            $this->UnitPrefix = "";
            $this->Unit = "ppm";
        }

    }

    private function formatArbitrary(int $value, $unit) {
        if ($value >= 1e12) {
            $this->Value = sprintf("%.0f", $value / 1e9);
            $this->UnitPrefix = "G";
            $this->Unit = $unit;

        } else if ($value >= 100e9) {
            $this->Value = sprintf("%.0f", $value / 1e9);
            $this->UnitPrefix = "G";
            $this->Unit = $unit;

        } else if ($value >= 10e9) {
            $this->Value = sprintf("%.1f", $value / 1e9);
            $this->UnitPrefix = "G";
            $this->Unit = $unit;

        } else if ($value >= 1e9) {
            $this->Value = sprintf("%.2f", $value / 1e9);
            $this->UnitPrefix = "G";
            $this->Unit = $unit;

        } else if ($value >= 100e6) {
            $this->Value = sprintf("%.0f", $value / 1e6);
            $this->UnitPrefix = "M";
            $this->Unit = $unit;

        } else if ($value >= 10e6) {
            $this->Value = sprintf("%.1f", $value / 1e6);
            $this->UnitPrefix = "M";
            $this->Unit = $unit;

        } else if ($value >= 1e6) {
            $this->Value = sprintf("%.2f", $value / 1e6);
            $this->UnitPrefix = "M";
            $this->Unit = $unit;

        } else if ($value >= 100e3) {
            $this->Value = sprintf("%.0f", $value / 1e3);
            $this->UnitPrefix = "k";
            $this->Unit = $unit;

        } else if ($value >= 10e3) {
            $this->Value = sprintf("%.1f", $value / 1e3);
            $this->UnitPrefix = "k";
            $this->Unit = $unit;

        } else if ($value >= 1e3) {
            $this->Value = sprintf("%.2f", $value / 1e3);
            $this->UnitPrefix = "k";
            $this->Unit = $unit;

        } else if ($value >= 0) {
            $this->Value = sprintf("%d", $value);
            $this->UnitPrefix = "";
            $this->Unit = $unit;

        }
    }

}
