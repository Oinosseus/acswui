<?php

namespace Parameter;

class ParamTime extends Parameter {

    final protected function cloneXtraAttributes($base) {
    }


    public function getHtmlInput() {
        $html = "";

        $key = $this->key();
        $value = $this->value();

        $html .= "<input type=\"time\" name=\"ParameterValue_$key\" value=\"$value\">";

        return $html;
    }


    public function formatValue($value) {
        $clean_value = "";

        $val = explode(":", $value);
        if (count($val) == 2) {
            $minutes = (int) $val[1];
            if ($minutes < 0 || $minutes > 59) $minutes = 0;

            $hours = (int) $val[0];
            if ($hours < 0 || $hours > 23) $hours = 0;

            $clean_value = sprintf("%02d:%02d", $hours, $minutes);
        }

        return $clean_value;
    }


    final public function value2Label($value) {
        return $value;
    }


    //! @return The AC sun angle for this time (13:00 == 0°, clipped to -80° ... 80°)
    public function valueSunAngle() {
        $delta_to_one_o_clock = $this->valueSeconds() - 46800;
        $degree_per_second = 160 / (10 * 3600);  // ac goes from -80° (8:00) to +80° (18:00) -> 160° in 10h
        $angle = 0 + $delta_to_one_o_clock * $degree_per_second;
        if ($angle < -80) $angle = -80;
        if ($angle > 80) $angle = 80;
        return $angle;
    }


    //! @return The time in seconds since 0:00am (example seconds(03:00) = 10800
    public function valueSeconds() {
        $val = explode(":", $this->value());
        $seconds = (60 * $val[0] + $val[1]) * 60;
        return $seconds;
    }


    public function storeHttpRequest() {
        parent::storeHttpRequest();
        $key = $this->key();

        // my inherit value
        $this->InheritValue = (array_key_exists("ParameterInheritValueCheckbox_$key", $_REQUEST)) ? TRUE : FALSE;

        // my value
        if (array_key_exists("ParameterValue_$key", $_REQUEST)) {
            $val = $_REQUEST["ParameterValue_$key"];
            $this->setValue($val);
        }

    }
}
