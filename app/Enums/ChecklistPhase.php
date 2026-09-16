<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * The stage of the repair a checklist item belongs to.
 */
enum ChecklistPhase: string
{
    use HasValues;

    /**
     * Condition documentation captured before repair work begins.
     *
     * @default
     */
    case PreRepair = 'pre_repair';

    /**
     * Quality-control sign-off performed after the repair is complete.
     */
    case PostRepair = 'post_repair';
}
