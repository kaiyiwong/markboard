<?php

namespace App\Night;

/**
 * The night run's `orders/costs.tsv`: one row per session, with a header line. On the plan login
 * nothing is billed; `api_equiv_usd` is what the tokens would cost at API prices, a measure of how
 * much of the plan's limits a night uses.
 */
final class Costs
{
    /** The night's usage at API prices, from every session whose time falls in it. */
    public static function forNight(string $text, string $night): float
    {
        $lines = preg_split('/\r\n|\n|\r/', $text) ?: [];
        $header = explode("\t", (string) array_shift($lines));
        $timeAt = array_search('time', $header, true);
        $usdAt = array_search('api_equiv_usd', $header, true);
        if ($timeAt === false || $usdAt === false) {
            return 0.0;
        }
        $total = 0.0;
        foreach ($lines as $line) {
            $cells = explode("\t", $line);
            $time = $cells[$timeAt] ?? '';
            $usd = $cells[$usdAt] ?? '';
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $time) === 1 && is_numeric($usd) && StatusLog::nightOf($time) === $night) {
                $total += (float) $usd;
            }
        }

        return round($total, 2);
    }
}
