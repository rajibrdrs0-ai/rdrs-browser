<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Course fee lookup. For each fee head the newest row with
 * effective_from <= date wins; a center-specific row overrides the
 * all-centers default for that head. History is never overwritten.
 */
final class FeeStructure
{
    /**
     * @return list<array{fee_head_id:int,code:string,name_en:string,name_bn:string,amount:float,is_optional:int,source:string,effective_from:string}>
     */
    public static function current(int $courseId, ?int $centerId, ?string $date = null): array
    {
        $date ??= date('Y-m-d');
        $rows = Database::all(
            'SELECT cf.fee_head_id, cf.center_id, cf.amount, cf.is_optional, cf.effective_from,
                    fh.code, fh.name_en, fh.name_bn, fh.sort_order
               FROM course_fees cf
               JOIN fee_heads fh ON fh.id = cf.fee_head_id AND fh.is_active = 1
              WHERE cf.course_id = ? AND cf.effective_from <= ? AND (cf.center_id IS NULL OR cf.center_id = ?)
              ORDER BY fh.sort_order, cf.effective_from DESC, cf.id DESC',
            [$courseId, $date, $centerId ?? 0]
        );
        $default = $override = [];
        foreach ($rows as $row) {
            $head = (int) $row['fee_head_id'];
            if ($row['center_id'] === null) {
                $default[$head] ??= $row;
            } else {
                $override[$head] ??= $row;
            }
        }
        $result = [];
        foreach ($default + $override as $head => $_) {
            $row = $override[$head] ?? $default[$head];
            $result[] = [
                'fee_head_id'    => $head,
                'code'           => (string) $row['code'],
                'name_en'        => (string) $row['name_en'],
                'name_bn'        => (string) $row['name_bn'],
                'amount'         => (float) $row['amount'],
                'is_optional'    => (int) $row['is_optional'],
                'source'         => isset($override[$head]) ? 'center' : 'default',
                'effective_from' => (string) $row['effective_from'],
                'sort_order'     => (int) $row['sort_order'],
            ];
        }
        usort($result, static fn(array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);
        return $result;
    }

    /** Sum of mandatory heads (+ optional ones when requested, e.g. residential). */
    public static function total(array $fees, bool $includeOptional = false): float
    {
        $sum = 0.0;
        foreach ($fees as $fee) {
            if ($includeOptional || (int) $fee['is_optional'] === 0) {
                $sum += (float) $fee['amount'];
            }
        }
        return round($sum, 2);
    }
}
