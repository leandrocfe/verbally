<?php

namespace App\Actions;

use App\Ai\Agents\CorrectionDetailsAgent;
use App\Ai\Agents\CorrectionTextAgent;
use App\Ai\GeminiRuntime;
use Laravel\Ai\Streaming\Events\TextDelta;
use Throwable;

final class GenerateCorrection
{
    /** @return array{corrected:string, details:array<string,mixed>|null, error:string|null, stage:string|null} */
    public function stream(string $submission, callable $onDelta): array
    {
        $model = GeminiRuntime::model();
        $timeout = GeminiRuntime::timeout();

        if (blank($model)) {
            return ['corrected' => '', 'details' => null, 'error' => 'Gemini is not configured. Try again later.', 'stage' => 'stream'];
        }

        $corrected = '';

        try {
            foreach (CorrectionTextAgent::make()->stream($submission, provider: 'gemini', model: $model, timeout: $timeout) as $event) {
                if ($event instanceof TextDelta) {
                    $corrected .= $event->delta;
                    $onDelta($event->delta);
                }
            }

            if (blank($corrected)) {
                return ['corrected' => '', 'details' => null, 'error' => 'Gemini returned an empty response. Try again.', 'stage' => 'stream'];
            }

            $detailResult = $this->details($submission, $corrected);

            return ['corrected' => $corrected, 'details' => $detailResult['details'], 'error' => $detailResult['error'], 'stage' => $detailResult['error'] === null ? null : 'details'];
        } catch (Throwable $exception) {
            return ['corrected' => $corrected, 'details' => null, 'error' => GeminiRuntime::errorMessage($exception), 'stage' => 'stream'];
        }
    }

    /** @return array{details:array<string,mixed>|null,error:string|null} */
    public function details(string $submission, string $corrected): array
    {
        $model = GeminiRuntime::model();
        $timeout = GeminiRuntime::timeout();
        if (blank($model)) {
            return ['details' => null, 'error' => 'Gemini is not configured. Try again later.'];
        }
        try {
            $response = CorrectionDetailsAgent::make()->prompt(
                "Original text:\n{$submission}\n\nPreviously streamed corrected text:\n{$corrected}",
                provider: 'gemini', model: $model, timeout: $timeout,
            );
            $details = $response->toArray();

            return $this->validDetails($details, $corrected)
                ? ['details' => $details, 'error' => null]
                : ['details' => null, 'error' => 'Gemini returned invalid correction details. Try again.'];
        } catch (Throwable $exception) {
            return ['details' => null, 'error' => GeminiRuntime::errorMessage($exception)];
        }
    }

    /** @param array<string,mixed> $details */
    private function validDetails(array $details, string $corrected): bool
    {
        if (array_diff(array_keys($details), ['corrected', 'diff', 'explanations', 'is_off_topic']) !== [] || count($details) !== 4 || ! is_string($details['corrected'] ?? null) || ($details['corrected'] ?? null) !== $corrected || ! is_bool($details['is_off_topic'] ?? null) || ! is_array($details['diff'] ?? null) || ! is_array($details['explanations'] ?? null)) {
            return false;
        }

        if ($details['is_off_topic']) {
            return is_array($details['diff'] ?? null) && is_array($details['explanations'] ?? null)
                && $details['diff'] === [] && $details['explanations'] === [];
        }

        $reconstructed = '';
        $previousType = null;
        foreach ($details['diff'] as $segment) {
            if (! is_array($segment) || ! in_array($segment['type'] ?? null, ['unchanged', 'removed', 'added'], true)) {
                return false;
            }
            $type = $segment['type'];
            $allowed = match ($type) {
                'unchanged' => ['type', 'original', 'replacement'],
                'removed' => ['type', 'original'],
                'added' => ['type', 'replacement'],
            };
            if (array_diff(array_keys($segment), $allowed) !== []) {
                return false;
            }
            if ($type === 'unchanged' && (! is_string($segment['original'] ?? null) || ! is_string($segment['replacement'] ?? null) || $segment['original'] !== $segment['replacement'])) {
                return false;
            }
            if ($type === 'removed' && (! array_key_exists('original', $segment) || ! is_string($segment['original']))) {
                return false;
            }
            if ($type === 'added' && (! array_key_exists('replacement', $segment) || ! is_string($segment['replacement']))) {
                return false;
            }
            if ($type === 'removed' && $previousType === 'added') {
                return false;
            }
            if ($type !== 'removed') {
                $reconstructed .= $segment['replacement'];
            }
            $previousType = $type;
        }

        foreach ($details['explanations'] ?? [] as $explanation) {
            if (! is_array($explanation) || blank($explanation['tag'] ?? null) || blank($explanation['text'] ?? null)) {
                return false;
            }
        }

        return is_array($details['diff'] ?? null) && is_array($details['explanations'] ?? null) && $reconstructed === $corrected;
    }
}
