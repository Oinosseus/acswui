<?php

namespace Core;

/**
 * A collection of basic features
 */
class Core {


    //! @return TRUE if executed from commandline itnerface (not HTTP)
    public static function cli() {
        return stripos(php_sapi_name(), "cli") !== FALSE;
    }

    //! @return current local DateTime object
    public static function now() {
        return new \DateTime("now", new \DateTimeZone(\Core\Config::LocalTimeZone));
    }
}
