<?php

namespace Parameter;

class ParamSpecialUserCountry extends ParamEnum {

    public function __construct(?Deriveable $base, ?Collection $parent, string $key = "", string $label = "", string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description, "", "");

        new EnumItem($this, "_", _(""));
        foreach (\Core\Config::Countries as $key=>$name) {
            new EnumItem($this, $key, $name);
        }
        $this->setValue("_");
    }


    final protected function cloneXtraAttributes($base) {
    }


    public function getHtmlInput() {
        $html = "";

        $key = $this->key();
        $value = $this->value();

//         if ($value !== "_") {
        $html .= "<img src=\"https://flagcdn.com/$value.svg\" class=\"ParameterUserCountry\">";
//         }

        $html .= "<select name=\"ParameterValue_$key\" onchange=\"toggleParamUserCountry('$key')\" id=\"ParameterUserCountry$key\">";
        foreach ($this->EnumItemList as $enum_item) {
            $selected = ($value == $enum_item->value()) ? "selected" : "";
            $html .= "<option value=\"" . $enum_item->value() . "\" $selected>" . $enum_item->label() . "</option>";
        }
        $html .= "</select>";

        return $html;
    }


    public function value2Label($value) {
        $html = "";

        if ($value !== "_") {
            $country_name = \Core\Config::Countries[$value];
            $html .= "<img src=\"https://flagcdn.com/$value.svg\" class=\"ParameterUserCountry\" title=\"$country_name\">";
        }

        return $html;;
    }
}
