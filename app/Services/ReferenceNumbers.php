<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

/**
 * Sequential, human-readable document numbers: GRN-2609-0007, EXP-2609-0012.
 */
class ReferenceNumbers
{
    /**
     * @param  class-string<Model>  $model
     */
    public function next(string $model, string $prefix, string $column = 'reference_no'): string
    {
        $period = now()->format('ym');

        $last = $model::query()
            ->where($column, 'like', "{$prefix}-{$period}-%")
            ->orderByDesc($column)
            ->value($column);

        $sequence = $last ? ((int) substr((string) $last, -4)) + 1 : 1;

        return sprintf('%s-%s-%04d', $prefix, $period, $sequence);
    }
}
