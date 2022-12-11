<?php

namespace Parameter;

class ParamInt extends Parameter {

    protected $MinVal = NULL;
    protected $MaxVal = NULL;


    final protected function cloneXtraAttributes($base) {
        $this->MinVal = $base->MinVal;
        $this->MaxVal = $base->MaxVal;
    }


    public function getHtmlInput(string $html_id_prefix = "") {
        $html = "";

        $key = $html_id_prefix . $this->key();
        $value = $this->value();
        $min = ($this->MinVal !== NULL) ? "min=\"$this->MinVal\"" : "";
        $max = ($this->MaxVal !== NULL) ? "max=\"$this->MaxVal\"" : "";

        $html .= "<input type=\"number\" name=\"ParameterValue_$key\" value=\"$value\" $min $max step=\"1\">";

        return $html;
    }


    public function formatValue($value) {
        return (int) $value;
    }


    final public function value2Label($value) {
        return $value;
    }


    public function setMin(int $min) {
        $this->MinVal = $min;
    }


    public function setMax(int $max) {
        $this->MaxVal = $max;
    }
}
