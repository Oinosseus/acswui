<?php

namespace Parameter;

class ParamWeatherGraphic extends ParamEnum {

    public function __construct(?Deriveable $base, ?Collection $parent, string $key = "", string $label = "", string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description);
        foreach (\Core\Config::WeatherGraphics as $w) {
            new EnumItem($this, $w, $w);
        }
        $this->setValue($this->EnumItemList[0]->value());
    }


    protected function cloneXtraAttributes($base) {
    }


    public function getHtmlInput() {
        $html = "";

        $key = $this->key();
        $value = $this->value();

        // weather preview image
//         $img_path = \Core\Config::AbsPathHtdata . "/content/weather/$value/preview.jpg";
//         if (file_exists($img_path)) {

            $img_src = \Core\Config::RelPathHtdata . "/content/weather/$value/preview.jpg";
            $html .= "<img src=\"$img_src\">";
//         }

        $html .= "<select name=\"ParameterValue_$key\" onchange=\"toggleParamWeatherGraphic('$key')\" id=\"ParameterWeatherGraphic$key\">";
        foreach ($this->EnumItemList as $enum_item) {
            $img_src = \Core\Config::RelPathHtdata . "/content/weather/" . $enum_item->value() . "/preview.jpg";
            $selected = ($value == $enum_item->value()) ? "selected" : "";
            $html .= "<option value=\"" . $enum_item->value() . "\" $selected img_src=\"$img_src\">" . $enum_item->label() . "</option>";
        }
        $html .= "</select>";

        return $html;
    }


    public function value2Label($value) {
        if ($value === NULL || $value === "") {
            return "";
        } else if (!array_key_exists($value, $this->EnumItemHash)) {
            \Core\Log::error("Undefined enum value '$value'!");
        }

        $html = "";
        $img_src = \Core\Config::RelPathHtdata . "/content/weather/$value/preview.jpg";
        $html .= "<img src=\"$img_src\" title=\"$value\">";
        return $html;;
    }
}
