<?php

namespace App\Enums;

use App\Enums\Attributes\Complete;
use App\Enums\Attributes\Pending;
use App\Enums\Concerns\HasProgress;
use App\Enums\Concerns\HasValues;

/**
 * The outcome of a single checklist item.
 *
 * A repair is QC-complete when every item is `passed`; a `failed` item
 * (void) blocks completion until it is re-checked and re-passed.
 */
enum ChecklistStatus: string
{
    use HasProgress, HasValues;

    /**
     * The item has not yet been checked.
     *
     * @default
     */
    #[Pending]
    case Pending = 'pending';

    /**
     * The item passed the check.
     */
    #[Complete]
    case Passed = 'passed';

    /**
     * The item failed the check and needs to be reworked.
     */
    case Failed = 'failed';
}
