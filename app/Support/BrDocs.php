<?php

namespace App\Support;

/** Validação e formatação de CPF, CNPJ e CEP. */
class BrDocs
{
    public static function digits(?string $v): string
    {
        return preg_replace('/\D/', '', (string) $v);
    }

    public static function isCpf(?string $v): bool
    {
        $c = self::digits($v);
        if (strlen($c) !== 11 || preg_match('/^(\d)\1{10}$/', $c)) {
            return false;
        }
        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($i = 0; $i < $t; $i++) {
                $d += $c[$i] * (($t + 1) - $i);
            }
            if ((int) $c[$t] !== ((10 * $d) % 11) % 10) {
                return false;
            }
        }

        return true;
    }

    public static function isCnpj(?string $v): bool
    {
        $c = self::digits($v);
        if (strlen($c) !== 14 || preg_match('/^(\d)\1{13}$/', $c)) {
            return false;
        }
        foreach ([12, 13] as $len) {
            $weights = $len === 12 ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2] : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
            $sum = 0;
            for ($i = 0; $i < $len; $i++) {
                $sum += $c[$i] * $weights[$i];
            }
            $r = $sum % 11;
            if ((int) $c[$len] !== ($r < 2 ? 0 : 11 - $r)) {
                return false;
            }
        }

        return true;
    }

    public static function format(?string $v): string
    {
        $d = self::digits($v);

        return match (strlen($d)) {
            11 => preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $d),
            14 => preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $d),
            8 => preg_replace('/(\d{5})(\d{3})/', '$1-$2', $d),
            default => (string) $v,
        };
    }

    public static function kind(?string $v): ?string
    {
        return match (strlen(self::digits($v))) {
            11 => 'CPF',
            14 => 'CNPJ',
            default => null,
        };
    }
}
