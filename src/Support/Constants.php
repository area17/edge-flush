<?php

namespace A17\EdgeFlush\Support;

class Constants
{
    // ----- by milliseconds

    const MILLISECOND = 1;

    const MS_SECOND = self::MILLISECOND * 1000;

    const MS_MINUTE = self::MS_SECOND * 60;

    const MS_HOUR = self::MS_MINUTE * 60;

    const MS_DAY = self::MS_HOUR * 24;

    const MS_WEEK = self::MS_DAY * 7;

    // ----- by seconds

    const SECOND = 1;

    const MINUTE = self::SECOND * 60;

    const HOUR = self::MINUTE * 60;

    const DAY = self::HOUR * 24;

    const WEEK = self::DAY * 7;
}
