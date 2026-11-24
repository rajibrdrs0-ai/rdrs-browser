<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Rules: required, numeric, int, min:n, max:n, email, date, mobile, in:a,b,c,
 *        unique:table,column[,ignoreId], exists:table, maxlen:n, nid
 * Field rule string example: 'name' => 'required|maxlen:150'
 * Labels: 'name|Student Name' => 'required'
 */
final class Validator
{
    public function __construct(private array $data)
    {
    }

    public function check(array $rules): array
    {
        $errors = [];
        foreach ($rules as $fieldSpec => $ruleStr) {
            [$field, $label] = array_pad(explode('|', $fieldSpec, 2), 2, null);
            $label ??= ucwords(str_replace('_', ' ', $field));
            $value = $this->data[$field] ?? null;
            $value = is_string($value) ? trim($value) : $value;
            $empty = $value === null || $value === '' || $value === [];
            foreach (explode('|', $ruleStr) as $rule) {
                if ($rule === '') {
                    continue;
                }
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                if ($name === 'required') {
                    if ($empty) {
                        $errors[$field] = "$label is required.";
                        break;
                    }
                    continue;
                }
                if ($empty) {
                    continue;
                }
                $err = match ($name) {
                    'numeric' => is_numeric(str_replace(',', '', (string)$value)) ? null : "$label must be a number.",
                    'int' => filter_var($value, FILTER_VALIDATE_INT) !== false ? null : "$label must be a whole number.",
                    'min' => (float)str_replace(',', '', (string)$value) >= (float)$arg ? null : "$label must be at least $arg.",
                    'max' => (float)str_replace(',', '', (string)$value) <= (float)$arg ? null : "$label must not exceed $arg.",
                    'maxlen' => mb_strlen((string)$value) <= (int)$arg ? null : "$label must be at most $arg characters.",
                    'email' => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : "$label must be a valid email address.",
                    'date' => $this->isDate((string)$value) ? null : "$label must be a valid date.",
                    'mobile' => preg_match('/^(\+?88)?01[3-9]\d{8}$/', preg_replace('/[\s-]/', '', (string)$value)) ? null : "$label must be a valid Bangladeshi mobile number (01XXXXXXXXX).",
                    'nid' => preg_match('/^(\d{10}|\d{13}|\d{17})$/', (string)$value) ? null : "$label must be 10, 13 or 17 digits.",
                    'brn' => preg_match('/^\d{17}$/', (string)$value) ? null : "$label must be 17 digits.",
                    'in' => in_array((string)$value, explode(',', (string)$arg), true) ? null : "$label has an invalid value.",
                    'unique' => $this->unique((string)$arg, $value) ? null : "$label '$value' already exists.",
                    'exists' => $this->exists((string)$arg, $value) ? null : "$label is invalid.",
                    default => null,
                };
                if ($err) {
                    $errors[$field] = $err;
                    break;
                }
            }
        }
        return $errors;
    }

    private function isDate(string $v): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $v);
        return $d && $d->format('Y-m-d') === $v;
    }

    private function unique(string $arg, $value): bool
    {
        $p = explode(',', $arg);
        DB::assertIdent($p[0]);
        DB::assertIdent($p[1]);
        $sql = "SELECT 1 FROM `{$p[0]}` WHERE `{$p[1]}` = :v";
        $params = ['v' => $value];
        if (!empty($p[2])) {
            $sql .= ' AND id <> :id';
            $params['id'] = (int)$p[2];
        }
        return !DB::exists($sql . ' LIMIT 1', $params);
    }

    private function exists(string $table, $value): bool
    {
        DB::assertIdent($table);
        return DB::exists("SELECT 1 FROM `$table` WHERE id = :id", ['id' => (int)$value]);
    }
}
