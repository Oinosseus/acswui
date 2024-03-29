<?php

namespace ParameterSpecial;

class WeatherGraphic extends \Parameter\ParamEnum {

    private const AcWeather = array(
                                    "ac_1" => "1_heavy_fog",
                                    "ac_2" => "2_light_fog",
                                    "ac_3" => "3_clear",
                                    "ac_4" => "4_mid_clear",
                                    "ac_5" => "5_light_clouds",
                                    "ac_6" => "6_mid_clouds",
                                    "ac_7" => "7_heavy_clouds",
                                    );


    public function __construct(?\Parameter\Deriveable $base,
                                ?\Parameter\Collection $parent,
                                string $key = "",
                                string $label = "",
                                string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description);

        // vanilla AC weathers
        new \Parameter\EnumItem($this, "ac_1", _("(AC) Heavy Fog"));
        new \Parameter\EnumItem($this, "ac_2", _("(AC) Light Fog"));
        new \Parameter\EnumItem($this, "ac_3", _("(AC) Clear"));
        new \Parameter\EnumItem($this, "ac_4", _("(AC) Mid Clear"));
        new \Parameter\EnumItem($this, "ac_5", _("(AC) Light Clouds"));
        new \Parameter\EnumItem($this, "ac_6", _("(AC) Mid Clouds"));
        new \Parameter\EnumItem($this, "ac_7", _("(AC) Heavy Clouds"));

        // CSP weathers
        new \Parameter\EnumItem($this, 'csp_0',  _("(CSP) Light Thunderstorm"));
        new \Parameter\EnumItem($this, 'csp_1',  _("(CSP) Thunderstorm"));
        new \Parameter\EnumItem($this, 'csp_2',  _("(CSP) Heavy Thunderstorm"));
        new \Parameter\EnumItem($this, 'csp_3',  _("(CSP) Light Drizzle"));
        new \Parameter\EnumItem($this, 'csp_4',  _("(CSP) Drizzle"));
        new \Parameter\EnumItem($this, 'csp_5',  _("(CSP) Heavy Drizzle"));
        new \Parameter\EnumItem($this, 'csp_6',  _("(CSP) Light Rain"));
        new \Parameter\EnumItem($this, 'csp_7',  _("(CSP) Rain"));
        new \Parameter\EnumItem($this, 'csp_8',  _("(CSP) Heavy Rain"));
        new \Parameter\EnumItem($this, 'csp_9',  _("(CSP) Light Snow"));
        new \Parameter\EnumItem($this, 'csp_10', _("(CSP) Snow"));
        new \Parameter\EnumItem($this, 'csp_11', _("(CSP) Heavy Snow"));
        new \Parameter\EnumItem($this, 'csp_12', _("(CSP) Light Sleet"));
        new \Parameter\EnumItem($this, 'csp_13', _("(CSP) Sleet"));
        new \Parameter\EnumItem($this, 'csp_14', _("(CSP) Heavy Sleet"));
        new \Parameter\EnumItem($this, 'csp_15', _("(CSP) Clear"));
        new \Parameter\EnumItem($this, 'csp_16', _("(CSP) Few Clear"));
        new \Parameter\EnumItem($this, 'csp_17', _("(CSP) Scattered Clouds"));
        new \Parameter\EnumItem($this, 'csp_18', _("(CSP) Broken Clouds"));
        new \Parameter\EnumItem($this, 'csp_19', _("(CSP) Overcast Clouds"));
        new \Parameter\EnumItem($this, 'csp_20', _("(CSP) Fog"));
        new \Parameter\EnumItem($this, 'csp_21', _("(CSP) Mist"));
        new \Parameter\EnumItem($this, 'csp_22', _("(CSP) Smoke"));
        new \Parameter\EnumItem($this, 'csp_23', _("(CSP) Haze"));
        new \Parameter\EnumItem($this, 'csp_24', _("(CSP) Sand"));
        new \Parameter\EnumItem($this, 'csp_25', _("(CSP) Dust"));
        new \Parameter\EnumItem($this, 'csp_26', _("(CSP) Squalls"));
        new \Parameter\EnumItem($this, 'csp_27', _("(CSP) Tornado"));
        new \Parameter\EnumItem($this, 'csp_28', _("(CSP) Hurricane"));
        new \Parameter\EnumItem($this, 'csp_29', _("(CSP) Cold"));
        new \Parameter\EnumItem($this, 'csp_30', _("(CSP) Hot"));
        new \Parameter\EnumItem($this, 'csp_31', _("(CSP) Windy"));
        new \Parameter\EnumItem($this, 'csp_32', _("(CSP) Hail"));
        new \Parameter\EnumItem($this, 'csp_-1', _("(CSP) None"));

        $this->setValue($this->EnumItemList[0]->value("ac_3"));
    }


    //! @return TRUE when the selected weather is using custom shader patch weather (FALSE for vanilla AC weather)
    public function csp() {
        return (substr($this->value(), 0, 3) == "csp") ? TRUE : FALSE;
    }


    protected function cloneXtraAttributes($base) {
    }


    //! @return The first part of the string that goes into server_cfg.ini [WEATHER] section (If CSP is used, the succeeding time information still needs to be added)
    public function getGraphic() {
        if ($this->csp()) {
            $g = "3_clear_type=";
            $g .= substr($this->value(), 4, strlen($this->value()));
            return $g;
        } else {
            return WeatherGraphic::AcWeather[$this->value()];
        }
    }


    public function getHtmlInput(string $html_id_prefix = "") {
        $html = "";

        $key = $html_id_prefix . $this->key();
        $value = $this->value();

//         $img_src = \Core\Config::RelPathHtdata . "/content/weather/$value/preview.jpg";
//         $html .= "<img src=\"$img_src\">";

        $html .= "<select name=\"ParameterValue_$key\" onchange=\"toggleParamWeatherGraphic('$key')\" id=\"ParameterWeatherGraphic$key\">";
        foreach ($this->EnumItemList as $enum_item) {
            $img_src = \Core\Config::RelPathHtdata . "/content/weather/" . $enum_item->value() . "/preview.jpg";
            $selected = ($value == $enum_item->value()) ? "selected" : "";
            $html .= "<option value=\"" . $enum_item->value() . "\" $selected img_src=\"$img_src\">" . $enum_item->label() . "</option>";
        }
        $html .= "</select>";

        return $html;
    }


//     public function value2Label($value) {
//         if ($value === NULL || $value === "") {
//             return "";
//         } else if (!array_key_exists($value, $this->EnumItemHash)) {
//             \Core\Log::error("Undefined enum value '$value'!");
//         }
//
//         $html = "";
//         $img_src = \Core\Config::RelPathHtdata . "/content/weather/$value/preview.jpg";
//         $html .= "<img src=\"$img_src\" title=\"$value\">";
//         return $html;;
//     }
}
