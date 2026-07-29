<?php

use App\Actions\GenerateCorrection;
use Livewire\Component;

new class extends Component
{
    public string $text = '';

    public bool $processing = false;

    /** @var array<int, array{id: int, text: string, corrected: string, segments: array<int, array{type: string, original?: string, replacement?: string}>, explanations: array<int, array{tag: string, text: string}>, followUps: array<int, array{label: string, text: string}>, pending: bool, error: string|null, off_topic: bool}> */
    public array $attempts = [];

    public function submitText(): void
    {
        if ($this->processing || count($this->attempts) >= 20) {
            return;
        }

        $this->text = trim($this->text);
        $this->validate(['text' => ['required', 'string', 'max:2000']]);

        $submission = $this->text;

        $this->processing = true;
        $attemptId = count($this->attempts);
        $this->attempts[] = [
            'id' => $attemptId,
            'text' => $submission,
            'corrected' => '',
            'segments' => [],
            'explanations' => [],
            'followUps' => [],
            'pending' => true,
            'error' => null,
            'off_topic' => false,
        ];
        $this->text = '';

        $result = app(GenerateCorrection::class)->stream($submission, function (string $delta) use ($attemptId): void {
            $this->attempts[$attemptId]['corrected'] .= $delta;
            $this->stream(htmlspecialchars($this->attempts[$attemptId]['corrected'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), replace: true, to: 'corrected-'.$attemptId);
        });

        $this->attempts[$attemptId]['pending'] = false;
        $this->processing = false;
        $this->attempts[$attemptId]['error'] = $result['error'];
        if ($result['details'] !== null) {
            $this->attempts[$attemptId]['segments'] = $result['details']['diff'];
            $this->attempts[$attemptId]['explanations'] = $result['details']['explanations'];
            $this->attempts[$attemptId]['off_topic'] = $result['details']['is_off_topic'];
        }
    }

    public function completeCorrection(int $attemptId): void
    {
        if (! $this->processing || ! isset($this->attempts[$attemptId])) {
            return;
        }

        $this->attempts[$attemptId]['pending'] = false;
        $this->processing = false;
    }

    public function retryDetails(int $attemptId): void
    {
        if ($this->processing || ! isset($this->attempts[$attemptId]) || blank($this->attempts[$attemptId]['corrected'])) {
            return;
        }

        $this->processing = true;
        $attempt = $this->attempts[$attemptId];
        $result = app(GenerateCorrection::class)->details($attempt['text'], $attempt['corrected']);
        $this->processing = false;
        $this->attempts[$attemptId]['error'] = $result['error'];
        if ($result['details'] !== null) {
            $this->attempts[$attemptId]['segments'] = $result['details']['diff'];
            $this->attempts[$attemptId]['explanations'] = $result['details']['explanations'];
        }
    }

    public function clearSession(): void
    {
        if ($this->processing) {
            return;
        }

        $this->attempts = [];
        $this->text = '';
    }

    public function rewriteNaturally(int $attemptId): void
    {
        if ($this->processing || ! isset($this->attempts[$attemptId])) {
            return;
        }

        $this->processing = true;
        $this->attempts[$attemptId]['followUps'][] = [
            'label' => 'Natural rewrite',
            'text' => 'I went to the store yesterday, but it was closed, so my friend and I decided to come back the next day.',
        ];
        $this->processing = false;
    }

    public function moreExamples(int $attemptId): void
    {
        if ($this->processing || ! isset($this->attempts[$attemptId])) {
            return;
        }

        $this->processing = true;
        $this->attempts[$attemptId]['followUps'][] = [
            'label' => 'Example',
            'text' => 'She went home early because the weather was getting worse.',
        ];
        $this->processing = false;
    }
};
