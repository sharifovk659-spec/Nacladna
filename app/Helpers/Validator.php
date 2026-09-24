<?php

namespace App\Helpers;

class Validator
{
    private array $errors = [];
    private array $data   = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function make(array $data): self
    {
        return new self($data);
    }

    public function required(string $field, string $label = ''): self
    {
        $label = $label ?: $field;
        $value = trim((string)($this->data[$field] ?? ''));
        if ($value === '') {
            $this->errors[$field] = "Поле «{$label}» обязательно.";
        }
        return $this;
    }

    public function maxLen(string $field, int $max, string $label = ''): self
    {
        $label = $label ?: $field;
        $value = $this->data[$field] ?? '';
        if (mb_strlen((string)$value) > $max) {
            $this->errors[$field] = "Поле «{$label}» не должно превышать {$max} символов.";
        }
        return $this;
    }

    public function phone(string $field, string $label = 'Телефон'): self
    {
        $value = preg_replace('/\D/', '', (string)($this->data[$field] ?? ''));
        if ($value !== '' && !preg_match('/^(992|0)\d{8,9}$/', $value)) {
            $this->errors[$field] = "Поле «{$label}» содержит некорректный номер.";
        }
        return $this;
    }

    public function numeric(string $field, string $label = ''): self
    {
        $label = $label ?: $field;
        $value = $this->data[$field] ?? '';
        if ($value !== '' && !is_numeric($value)) {
            $this->errors[$field] = "Поле «{$label}» должно быть числом.";
        }
        return $this;
    }

    public function min(string $field, float $min, string $label = ''): self
    {
        $label = $label ?: $field;
        $value = $this->data[$field] ?? '';
        if (is_numeric($value) && (float)$value < $min) {
            $this->errors[$field] = "Поле «{$label}» должно быть не менее {$min}.";
        }
        return $this;
    }

    public function isValid(): bool
    {
        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function firstError(): string
    {
        return reset($this->errors) ?: '';
    }

    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (str_starts_with($digits, '992')) {
            return '+' . $digits;
        }
        if (str_starts_with($digits, '0') && strlen($digits) >= 9) {
            return '+992' . ltrim($digits, '0');
        }
        return '+992' . $digits;
    }
}
