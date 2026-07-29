<?php

namespace App\Actions;

use App\Ai\Agents\ExampleAgent;
use App\Ai\Agents\NaturalRewriteAgent;
use App\Ai\GeminiRuntime;
use Laravel\Ai\Contracts\Agent;
use Throwable;

final class GenerateFollowUp
{
    /** @return array{label: string, text: string|null, error: string|null} */
    public function rewrite(string $submission, string $corrected): array
    {
        return $this->generate(NaturalRewriteAgent::make(), 'Natural rewrite', $submission, $corrected);
    }

    /** @return array{label: string, text: string|null, error: string|null} */
    public function example(string $submission, string $corrected): array
    {
        return $this->generate(ExampleAgent::make(), 'Example', $submission, $corrected);
    }

    /** @return array{label: string, text: string|null, error: string|null} */
    private function generate(Agent $agent, string $label, string $submission, string $corrected): array
    {
        $model = GeminiRuntime::model();
        $timeout = GeminiRuntime::timeout();

        if (blank($model)) {
            return ['label' => $label, 'text' => null, 'error' => 'Gemini is not configured. Try again later.'];
        }

        try {
            $response = $agent->prompt(
                "Original text:\n{$submission}\n\nCorrected text:\n{$corrected}",
                provider: 'gemini', model: $model, timeout: $timeout,
            );

            $text = trim((string) $response);

            if (blank($text)) {
                return ['label' => $label, 'text' => null, 'error' => 'Gemini returned an empty response. Try again.'];
            }

            if (mb_strlen($text) > 300) {
                return ['label' => $label, 'text' => null, 'error' => 'Gemini returned a response that was too long. Try again.'];
            }

            return ['label' => $label, 'text' => $text, 'error' => null];
        } catch (Throwable $exception) {
            return ['label' => $label, 'text' => null, 'error' => GeminiRuntime::errorMessage($exception)];
        }
    }
}
