<?php

namespace Parameter;

final class ParamInt extends Parameter {

    private $MinVal = NULL;
    private $MaxVal = NULL;


    final protected function cloneXtraAttributes($base) {
        $this->MinVal = $base->MinVal;
        $this->MaxVal = $base->MaxVal;
    }


    final public function getHtmlInput() {
        $html = "";

        $key_snake = $this->keySnake();
        $value = $this->value();
        $min = ($this->MinVal !== NULL) ? "min=\"$this->MinVal\"" : "";
        $max = ($this->MaxVal !== NULL) ? "max=\"$this->MaxVal\"" : "";

        $html .= "<input type=\"number\" name=\"ParameterValue_$key_snake\" value=\"$value\" $min $max step=\"1\">";

        return $html;
    }


    final public function formatValue($value) {
        return "$value";
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
