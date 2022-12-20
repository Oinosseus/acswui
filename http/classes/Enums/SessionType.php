<?php

declare(strict_types=1);
namespace Enums;

enum SessionType : int {
    case Invalid = -1;
    case Booking = 0;
    case Practice = 1;
    case Qualifying = 2;
    case Race = 3;
}
