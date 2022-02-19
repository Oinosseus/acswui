<?php

namespace Parameter;

/**
 * Select multiplie enum items.
 */
class ParamEnumMonthly extends ParamEnumMulti {

    public function __construct(?Deriveable $base, ?Collection $parent, string $key = "", string $label = "", string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description);
        new \Parameter\EnumItem($this, "1", _("1st Day"));
        new \Parameter\EnumItem($this, "2", _("2nd Day"));
        new \Parameter\EnumItem($this, "3", _("3rd Day"));
        new \Parameter\EnumItem($this, "4", _("4th Day"));
        new \Parameter\EnumItem($this, "5", _("5th Day"));
        new \Parameter\EnumItem($this, "6", _("6th Day"));
        new \Parameter\EnumItem($this, "7", _("7th Day"));
        new \Parameter\EnumItem($this, "8", _("8th Day"));
        new \Parameter\EnumItem($this, "9", _("9th Day"));
        new \Parameter\EnumItem($this, "10", _("10th Day"));
        new \Parameter\EnumItem($this, "11", _("11th Day"));
        new \Parameter\EnumItem($this, "12", _("12th Day"));
        new \Parameter\EnumItem($this, "13", _("13th Day"));
        new \Parameter\EnumItem($this, "14", _("14th Day"));
        new \Parameter\EnumItem($this, "15", _("15th Day"));
        new \Parameter\EnumItem($this, "16", _("16th Day"));
        new \Parameter\EnumItem($this, "17", _("17th Day"));
        new \Parameter\EnumItem($this, "18", _("18th Day"));
        new \Parameter\EnumItem($this, "19", _("19th Day"));
        new \Parameter\EnumItem($this, "20", _("20th Day"));
        new \Parameter\EnumItem($this, "21", _("21th Day"));
        new \Parameter\EnumItem($this, "22", _("22th Day"));
        new \Parameter\EnumItem($this, "23", _("23th Day"));
        new \Parameter\EnumItem($this, "24", _("24th Day"));
        new \Parameter\EnumItem($this, "25", _("25th Day"));
        new \Parameter\EnumItem($this, "26", _("26th Day"));
        new \Parameter\EnumItem($this, "27", _("27th Day"));
        new \Parameter\EnumItem($this, "28", _("28th Day"));
        new \Parameter\EnumItem($this, "29", _("29th Day"));
        new \Parameter\EnumItem($this, "30", _("30th Day"));
        new \Parameter\EnumItem($this, "31", _("31th Day"));
        new \Parameter\EnumItem($this, "1Mon", _("1st Monday"));
        new \Parameter\EnumItem($this, "2Mon", _("2nd Monday"));
        new \Parameter\EnumItem($this, "3Mon", _("3rd Monday"));
        new \Parameter\EnumItem($this, "4Mon", _("4th Monday"));
        new \Parameter\EnumItem($this, "5Mon", _("5th Monday"));
        new \Parameter\EnumItem($this, "MonEven", _("Monday Even Weeks"));
        new \Parameter\EnumItem($this, "MonOdd", _("Monday Odd Weeks"));
        new \Parameter\EnumItem($this, "1Tue", _("1st Tuesday"));
        new \Parameter\EnumItem($this, "2Tue", _("2nd Tuesday"));
        new \Parameter\EnumItem($this, "3Tue", _("3rd Tuesday"));
        new \Parameter\EnumItem($this, "4Tue", _("4th Tuesday"));
        new \Parameter\EnumItem($this, "5Tue", _("5th Tuesday"));
        new \Parameter\EnumItem($this, "TueEven", _("Tuesday Even Weeks"));
        new \Parameter\EnumItem($this, "TueOdd", _("Tuesday Odd Weeks"));
        new \Parameter\EnumItem($this, "1Wed", _("1st Wednesday"));
        new \Parameter\EnumItem($this, "2Wed", _("2nd Wednesday"));
        new \Parameter\EnumItem($this, "3Wed", _("3rd Wednesday"));
        new \Parameter\EnumItem($this, "4Wed", _("4th Wednesday"));
        new \Parameter\EnumItem($this, "5Wed", _("5th Wednesday"));
        new \Parameter\EnumItem($this, "WedEven", _("Wednesday Even Weeks"));
        new \Parameter\EnumItem($this, "WedOdd", _("Wednesday Odd Weeks"));
        new \Parameter\EnumItem($this, "1Thu", _("1st Thursday"));
        new \Parameter\EnumItem($this, "2Thu", _("2nd Thursday"));
        new \Parameter\EnumItem($this, "3Thu", _("3rd Thursday"));
        new \Parameter\EnumItem($this, "4Thu", _("4th Thursday"));
        new \Parameter\EnumItem($this, "5Thu", _("5th Thursday"));
        new \Parameter\EnumItem($this, "ThuEven", _("Thursday Even Weeks"));
        new \Parameter\EnumItem($this, "ThuOdd", _("Thursday Odd Weeks"));
        new \Parameter\EnumItem($this, "1Fri", _("1st Friday"));
        new \Parameter\EnumItem($this, "2Fri", _("2nd Friday"));
        new \Parameter\EnumItem($this, "3Fri", _("3rd Friday"));
        new \Parameter\EnumItem($this, "4Fri", _("4th Friday"));
        new \Parameter\EnumItem($this, "5Fri", _("5th Friday"));
        new \Parameter\EnumItem($this, "FriEven", _("Friday Even Weeks"));
        new \Parameter\EnumItem($this, "FriOdd", _("Friday Odd Weeks"));
        new \Parameter\EnumItem($this, "1Sat", _("1st Saturday"));
        new \Parameter\EnumItem($this, "2Sat", _("2nd Saturday"));
        new \Parameter\EnumItem($this, "3Sat", _("3rd Saturday"));
        new \Parameter\EnumItem($this, "4Sat", _("4th Saturday"));
        new \Parameter\EnumItem($this, "5Sat", _("5th Saturday"));
        new \Parameter\EnumItem($this, "SatEven", _("Saturday Even Weeks"));
        new \Parameter\EnumItem($this, "SatOdd", _("Saturday Odd Weeks"));
        new \Parameter\EnumItem($this, "1Sun", _("1st Sunday"));
        new \Parameter\EnumItem($this, "2Sun", _("2nd Sunday"));
        new \Parameter\EnumItem($this, "3Sun", _("3rd Sunday"));
        new \Parameter\EnumItem($this, "4Sun", _("4th Sunday"));
        new \Parameter\EnumItem($this, "5Sun", _("5th Sunday"));
        new \Parameter\EnumItem($this, "SunEven", _("Sunday Even Weeks"));
        new \Parameter\EnumItem($this, "SunOdd", _("Sunday Odd Weeks"));
        $this->setValue("1");
    }

    protected function cloneXtraAttributes($base) {
        // overload to prevent copying enum itmes
    }
}
