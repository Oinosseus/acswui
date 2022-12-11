<?php

namespace Parameter;

/**
 * A DateTime parameter.
 * The value is stored in server-timezone,
 * but the input elements are in user-timezone.
 *
 * If no default is given, the current time is used as default
 *
 * The DateTime value is internally stored in UTC time-zone
 */
class ParamDateTime extends Parameter {

    public function __construct(?Deriveable $base, ?Collection $parent, string $key = "", string $label = "", string $description = "", $unit="", $default=NULL) {
        parent::__construct($base, $parent, $key, $label, $description, $unit, $default);

        if ($default === NULL) {
            $dt = new \DateTime("now");
            $this->setValue($dt->format("Y-m-d H:i"));
        }
    }


    final protected function cloneXtraAttributes($base) {
    }


    public function getHtmlInput(string $html_id_prefix = "") {
        $html = "";

        $key = $html_id_prefix . $this->key();
        $value = $this->value();

        // transform to user-time
        $dt = new \DateTime($value, new \DateTimeZone("UTC"));
        $dt->setTimezone(new \DateTimeZone(\Core\UserManager::currentUser()->getParam("UserTimezone")));

        $html .= "<input type=\"date\" name=\"ParameterValue_{$key}Date\" value=\"{$dt->format('Y-m-d')}\"> ";
        $html .= "<input type=\"time\" name=\"ParameterValue_{$key}Time\" value=\"{$dt->format('H:i')}\">";

        return $html;
    }


    public function formatValue($value) {

        // ensure minimal length
        if (strlen($value) < 16) $value .= "0000000000000000";

        $year = substr($value, 0, 4);
        if ($year == 0) $year = 1;

        $month = substr($value, 5, 2);
        if ($month <= 0 || $month > 12) $month = 1;

        $day = substr($value, 8, 2);
        if ($day <= 0 || $day > 31) $day = 1;

        $hour = substr($value, 11, 2);
        if ($hour <= 0 || $hour > 23) $hour = 0;

        $minute = substr($value, 14, 2);
        if ($minute <= 0 || $minute > 59) $minute = 0;


        return sprintf("%04d-%02d-%02d %02d:%02d", $year, $month, $day, $hour, $minute);
    }


    final public function value2Label($value) {
        $t = new \DateTime($value, new \DateTimeZone(\Core\UserManager::currentUser()->getParam("UserTimezone")));
        return \Core\UserManager::currentUser()->formatDateTimeNoSeconds($t)/* . " {$t->format('c')} {$t->getTimezone()->getName()}"*/;
    }


    public function storeHttpRequest(string $html_id_prefix = "") {
        parent::storeHttpRequest();
        $key = $html_id_prefix . $this->key();

        // my inherit value
        $this->InheritValue = (array_key_exists("ParameterInheritValueCheckbox_$key", $_REQUEST)) ? TRUE : FALSE;

        // retrieve values
        $date = "0000-00-00";
        if (array_key_exists("ParameterValue_{$key}Date", $_REQUEST))
            $date = $_REQUEST["ParameterValue_{$key}Date"];
        $time = "00:00";
        if (array_key_exists("ParameterValue_{$key}Time", $_REQUEST))
            $time = $_REQUEST["ParameterValue_{$key}Time"];

        // convert to server time
        $value = $this->formatValue("$date $time");
        $dt = new \DateTime($value, new \DateTimeZone(\Core\UserManager::currentUser()->getParam("UserTimezone")));
        $dt->setTimezone(new \DateTimeZone("UTC"));

        $this->setValue($dt->format("Y-m-d H:i"));

    }
}
