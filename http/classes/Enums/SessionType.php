<?php

declare(strict_types=1);
namespace Enums;

enum SessionType : int {

    // Placeholder to indicate invalids
    case Invalid = -1;

    // Original AC defined states
    case Booking = 0;
    case Practice = 1;
    case Qualifying = 2;
    case Race = 3;

    // Pseudo Types
    case QualiRaceWait = 10;  // wait time between qualifying and race
    case SessionFinish = 11;  // finish of last session
}
