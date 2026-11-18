<?php

namespace App\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InputRules
{
    public static function rules(array $p): array
    {
        $rules = [];
        foreach ($p['fields'] as $f) {
            $rules[$f['name']] = $f['rules'];
            if ($f['type'] === 'multiselect') {
                $rules[$f['name'] . '.*'] = ['in:' . implode(',', array_keys($f['options']))];
            }
        }
        return $rules;
    }

    public static function attributes(array $p): array
    {
        return collect($p['fields'])->mapWithKeys(fn ($f) => [$f['name'] => strtolower(preg_replace('/\s*\(.*\)$/', '', $f['label']))])->all();
    }

    /** @throws ValidationException */
    public static function validate(array $p, array $data): array
    {
        foreach ($p['fields'] as $f) {
            if ($f['type'] === 'checkbox') $data[$f['name']] = filter_var($data[$f['name']] ?? false, FILTER_VALIDATE_BOOLEAN);
        }
        $v = Validator::make($data, self::rules($p), ['regex' => 'That :attribute doesn\'t look right. Check it and try again.', 'size' => 'The :attribute must be exactly :size characters.', 'digits' => 'The :attribute must be :digits digits.'], self::attributes($p));
        if (! empty($p['require_one'])) {
            $v->after(function ($v) use ($p, $data) {
                $any = collect($p['require_one'])->contains(fn ($k) => filled($data[$k] ?? null));
                if (! $any) $v->errors()->add($p['require_one'][0], $p['hint'] ?? 'Fill at least one of: ' . implode(', ', $p['require_one']));
            });
        }
        return array_intersect_key($v->validate(), array_flip(array_column($p['fields'], 'name')));
    }
}
