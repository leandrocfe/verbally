<?php

namespace App\Ai;

use Illuminate\Support\Facades\Config;
use Throwable;

final class GeminiRuntime
{
    public static function model(): ?string
    {
        return Config::get('ai.verbally.gemini_model');
    }

    public static function timeout(): int
    {
        return (int) Config::get('ai.verbally.timeout_seconds', 30);
    }

    public static function errorMessage(Throwable $exception): string
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'rate') ? 'Gemini rate limit reached. Try again.' : (str_contains($message, 'timeout') ? 'Gemini timed out. Try again.' : 'Gemini could not complete this correction. Try again.');
    }
}
