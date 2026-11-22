<?php
declare(strict_types=1);

namespace App\Security;

use App\Core\ValidationException;

/**
 * Rule-based input validator.
 *
 *   Validator::make($input, [
 *       'receiver_phone' => 'required|bd_phone',
 *       'weight_kg'      => 'required|decimal:3|min_value:0.01|max_value:100',
 *       'service_type'   => ['required', 'in:REG,EXP,SDD,FRG'],
 *   ])->validate();
 *
 * Messages are translation keys (validation.<rule>) resolved through __()
 * when the bilingual engine is loaded, with English fallbacks.
 */
final class Validator
{
    private const DEFAULT_MESSAGES = [
        'required'   => 'The :field field is required.',
        'string'     => 'The :field must be text.',
        'email'      => 'The :field must be a valid email address.',
        'bd_phone'   => 'The :field must be a valid Bangladeshi mobile number (01XXXXXXXXX).',
        'numeric'    => 'The :field must be a number.',
        'integer'    => 'The :field must be a whole number.',
        'decimal'    => 'The :field may have at most :param decimal places.',
        'min'        => 'The :field must be at least :param characters.',
        'max'        => 'The :field may not be greater than :param characters.',
        'min_value'  => 'The :field must be at least :param.',
        'max_value'  => 'The :field may not be greater than :param.',
        'in'         => 'The selected :field is invalid.',
        'date'       => 'The :field must be a valid date (YYYY-MM-DD).',
        'time'       => 'The :field must be a valid time (HH:MM).',
        'after_time' => 'The :field must be later than :param.',
        'after_or_equal' => 'The :field must be a date after or equal to :param.',
        'confirmed'  => 'The :field confirmation does not match.',
        'regex'      => 'The :field format is invalid.',
        'alpha_dash' => 'The :field may only contain letters, numbers, dashes and underscores.',
        'boolean'    => 'The :field must be true or false.',
        'array'      => 'The :field must be a list.',
        'tracking_no'=> 'The :field must be a valid tracking number.',
        'same'       => 'The :field must match :param.',
        'different'  => 'The :field must be different from :param.',
    ];

    /** @var array<string,list<string>> */
    private array $errors = [];

    /**
     * @param array<string,mixed> $data
     * @param array<string,string|list<string>> $rules
     */
    private function __construct(private readonly array $data, private readonly array $rules)
    {
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,string|list<string>> $rules
     */
    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    public function passes(): bool
    {
        $this->errors = [];
        foreach ($this->rules as $field => $ruleSet) {
            $rules = is_array($ruleSet) ? $ruleSet : explode('|', $ruleSet);
            $value = $this->data[$field] ?? null;
            $isEmpty = $value === null || $value === '' || $value === [];

            if (in_array('nullable', $rules, true) && $isEmpty) {
                continue;
            }
            if (!in_array('required', $rules, true) && $isEmpty) {
                continue;
            }
            foreach ($rules as $rule) {
                if ($rule === 'nullable') {
                    continue;
                }
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                if (!$this->check($name, $value, $param, $field)) {
                    $this->addError($field, $name, $param);
                    if ($name === 'required') {
                        break;
                    }
                }
            }
        }
        return $this->errors === [];
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    /** @return array<string,mixed> */
    public function validate(): array
    {
        if (!$this->passes()) {
            throw new ValidationException($this->errors);
        }
        return array_intersect_key($this->data, $this->rules);
    }

    /** @return array<string,list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    private function check(string $rule, mixed $value, ?string $param, string $field): bool
    {
        $str = is_scalar($value) ? trim((string) $value) : '';
        return match ($rule) {
            'required'   => !($value === null || (is_string($value) && trim($value) === '') || $value === []),
            'string'     => is_string($value),
            'email'      => filter_var($str, FILTER_VALIDATE_EMAIL) !== false,
            'bd_phone'   => (bool) preg_match('/^(?:\+?88)?01[3-9]\d{8}$/', preg_replace('/[\s-]/', '', $str) ?? ''),
            'numeric'    => is_numeric($str),
            'integer'    => filter_var($str, FILTER_VALIDATE_INT) !== false,
            'decimal'    => (bool) preg_match('/^-?\d+(?:\.\d{1,' . max(0, (int) $param) . '})?$/', $str),
            'min'        => mb_strlen($str) >= (int) $param,
            'max'        => mb_strlen($str) <= (int) $param,
            'min_value'  => is_numeric($str) && (float) $str >= (float) $param,
            'max_value'  => is_numeric($str) && (float) $str <= (float) $param,
            'in'         => in_array($str, explode(',', (string) $param), true),
            'date'       => $this->isDate($str),
            'time'       => (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $str),
            'after_time' => (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d/', $str) && $str > (string) ($this->data[(string) $param] ?? ''),
            'after_or_equal' => $this->isDate($str) && $str >= $this->resolveDateParam((string) $param),
            'confirmed'  => isset($this->data[$field . '_confirmation']) && $value === $this->data[$field . '_confirmation'],
            'same'       => ($this->data[(string) $param] ?? null) === $value,
            'different'  => ($this->data[(string) $param] ?? null) !== $value,
            'regex'      => (bool) @preg_match((string) $param, $str),
            'alpha_dash' => (bool) preg_match('/^[\p{L}\p{N}_-]+$/u', $str),
            'boolean'    => in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false', 'on', 'off'], true),
            'array'      => is_array($value),
            'tracking_no'=> (bool) preg_match('/^[A-Z]{2,5}-\d{8}-\d{6}$/', strtoupper($str)),
            default      => throw new \InvalidArgumentException("Unknown validation rule [{$rule}]."),
        };
    }

    private function isDate(string $value): bool
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $d !== false && $d->format('Y-m-d') === $value;
    }

    private function resolveDateParam(string $param): string
    {
        if ($param === 'today') {
            return date('Y-m-d');
        }
        $other = $this->data[$param] ?? null;
        return is_string($other) && $this->isDate($other) ? $other : $param;
    }

    private function addError(string $field, string $rule, ?string $param): void
    {
        $label = str_replace('_', ' ', $field);
        $replace = [':field' => $label, ':param' => (string) $param];
        $message = null;
        if (function_exists('__')) {
            $key = 'validation.' . $rule;
            // Phase 3 signature: __(string $key, array $replace = [], ?string $default = null)
            $translated = __($key, ['field' => __('fields.' . $field, [], $label), 'param' => (string) $param]);
            if ($translated !== $key) {
                $message = $translated;
            }
        }
        $this->errors[$field][] = $message ?? strtr(self::DEFAULT_MESSAGES[$rule] ?? 'The :field is invalid.', $replace);
    }
}
