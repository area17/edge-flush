<?php

namespace A17\CDN\Support;

class Constants
{
    const MILLISECOND = 1;

    const SECOND = self::MILLISECOND * 1000;

    const MINUTE = self::SECOND * 60;

    const HOUR = self::MINUTE * 60;

    const DAY = self::HOUR * 24;

    const WEEK = self::DAY * 7;
}
