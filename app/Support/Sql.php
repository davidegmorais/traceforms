<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Expressões SQL portáveis (SQLite, MySQL/MariaDB, PostgreSQL) para agrupar por data/hora
 * no fuso horário da conta.
 */
class Sql
{
    public static function driver(): string
    {
        return DB::connection()->getDriverName();
    }

    private static function shifted(string $col, int $offsetMinutes): string
    {
        return match (self::driver()) {
            'sqlite' => "datetime($col, '".($offsetMinutes >= 0 ? '+' : '')."$offsetMinutes minutes')",
            'pgsql' => "($col + interval '$offsetMinutes minutes')",
            default => "DATE_ADD($col, INTERVAL $offsetMinutes MINUTE)",
        };
    }

    public static function date(string $col, int $offsetMinutes = 0): string
    {
        $c = self::shifted($col, $offsetMinutes);

        return match (self::driver()) {
            'sqlite' => "date($c)",
            'pgsql' => "to_char($c, 'YYYY-MM-DD')",
            default => "DATE_FORMAT($c, '%Y-%m-%d')",
        };
    }

    public static function hour(string $col, int $offsetMinutes = 0): string
    {
        $c = self::shifted($col, $offsetMinutes);

        return match (self::driver()) {
            'sqlite' => "CAST(strftime('%H', $c) AS INTEGER)",
            'pgsql' => "CAST(EXTRACT(HOUR FROM $c) AS INTEGER)",
            default => "HOUR($c)",
        };
    }

    /** 0 = domingo … 6 = sábado */
    public static function weekday(string $col, int $offsetMinutes = 0): string
    {
        $c = self::shifted($col, $offsetMinutes);

        return match (self::driver()) {
            'sqlite' => "CAST(strftime('%w', $c) AS INTEGER)",
            'pgsql' => "CAST(EXTRACT(DOW FROM $c) AS INTEGER)",
            default => "(DAYOFWEEK($c) - 1)",
        };
    }

    public static function offsetMinutes(string $timezone): int
    {
        return (int) round((new \DateTimeZone($timezone))->getOffset(new \DateTime('now', new \DateTimeZone('UTC'))) / 60);
    }
}
