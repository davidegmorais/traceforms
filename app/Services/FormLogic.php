<?php

namespace App\Services;

use App\Models\Form;
use App\Models\FormField;

/**
 * Lógica condicional: um campo pode ser exibido somente quando
 * regras sobre respostas anteriores forem satisfeitas.
 *
 * settings.logic = { enabled: bool, match: "all"|"any", rules: [{field, op, value}] }
 * O mesmo algoritmo roda no navegador (resources/views/public/partials/script).
 */
class FormLogic
{
    public const OPERATORS = [
        'eq' => 'é igual a',
        'neq' => 'é diferente de',
        'contains' => 'contém',
        'not_contains' => 'não contém',
        'gt' => 'é maior que',
        'lt' => 'é menor que',
        'empty' => 'está vazio',
        'not_empty' => 'está preenchido',
    ];

    public static function isVisible(FormField $field, array $answers): bool
    {
        $logic = $field->setting('logic');
        if (! is_array($logic) || empty($logic['enabled']) || empty($logic['rules'])) {
            return true;
        }
        $results = array_map(fn ($r) => self::test($r, $answers), $logic['rules']);

        return ($logic['match'] ?? 'all') === 'any' ? in_array(true, $results, true) : ! in_array(false, $results, true);
    }

    public static function test(array $rule, array $answers): bool
    {
        $v = $answers[$rule['field'] ?? ''] ?? null;
        $target = (string) ($rule['value'] ?? '');
        // Normaliza para lista de textos; respostas compostas (arquivos, endereço) contam como "preenchido".
        $values = is_array($v)
            ? array_values(array_map(fn ($x) => is_scalar($x) ? (string) $x : '[arquivo]', array_filter($v, fn ($x) => $x !== null && $x !== '')))
            : ($v === null || $v === '' || $v === false ? [] : [(string) $v]);
        $low = fn ($s) => mb_strtolower($s);

        return match ($rule['op'] ?? 'eq') {
            'eq' => in_array($low($target), array_map($low, $values), true),
            'neq' => ! in_array($low($target), array_map($low, $values), true),
            'contains' => (bool) array_filter($values, fn ($x) => str_contains($low($x), $low($target))),
            'not_contains' => ! array_filter($values, fn ($x) => str_contains($low($x), $low($target))),
            'gt' => $values && is_numeric($values[0]) && (float) $values[0] > (float) $target,
            'lt' => $values && is_numeric($values[0]) && (float) $values[0] < (float) $target,
            'empty' => $values === [],
            'not_empty' => $values !== [],
            default => true,
        };
    }

    /** Campos visíveis dado o conjunto de respostas (avaliação em ordem). */
    public static function visibleFields(Form $form, array $answers): array
    {
        $visible = [];
        foreach ($form->fields as $field) {
            if (self::isVisible($field, $answers)) {
                $visible[$field->key] = $field;
            }
        }

        return $visible;
    }
}
