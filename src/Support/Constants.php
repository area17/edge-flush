<?php declare(strict_types=1);

namespace A17\EdgeFlush\Support;

class Constants
{
    // ----- tags

    const ALL_TAGS = '--all--';

    // ----- time by milliseconds

    const MILLISECOND = 1;

    const MS_SECOND = self::MILLISECOND * 1000;

    const MS_MINUTE = self::MS_SECOND * 60;

    const MS_HOUR = self::MS_MINUTE * 60;

    const MS_DAY = self::MS_HOUR * 24;

    const MS_WEEK = self::MS_DAY * 7;

    // ----- time by seconds

    const SECOND = 1;

    const MINUTE = self::SECOND * 60;

    const HOUR = self::MINUTE * 60;

    const DAY = self::HOUR * 24;

    const WEEK = self::DAY * 7;

    const MONTH = self::DAY * 30;

    const YEAR = self::MONTH * 12;

    // -------- invalidation types

    const INVALIDATION_TYPE_MODEL = 'model';

    const INVALIDATION_TYPE_TAG = 'tag';

    const INVALIDATION_TYPE_PATH = 'path';
}
