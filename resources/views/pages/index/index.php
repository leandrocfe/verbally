<?php

use Livewire\Component;

new class extends Component
{
    public string $text = '';

    public bool $processing = false;

    /** @var array<int, array{id: int, text: string, corrected: string, segments: array<int, array{type: string, original: string, replacement: string}>, explanations: array<int, array{tag: string, text: string}>, followUps: array<int, array{label: string, text: string}>, pending: bool}> */
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
        $corrected = $this->controlledCorrection($submission);

        $this->attempts[] = [
            'id' => $attemptId,
            'text' => $submission,
            'corrected' => $corrected,
            'segments' => $this->segments($submission, $corrected),
            'explanations' => $this->explanations($submission, $corrected),
            'followUps' => [],
            'pending' => true,
        ];
        $this->text = '';
    }

    public function completeCorrection(int $attemptId): void
    {
        if (! $this->processing || ! isset($this->attempts[$attemptId])) {
            return;
        }

        $this->attempts[$attemptId]['pending'] = false;
        $this->processing = false;
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

    private function controlledCorrection(string $submission): string
    {
        return str_replace(
            ['I have went', 'they was', 'me and my friend decides to comes', 'tomorrow'],
            ['I went', 'it was', 'my friend and I decided to come', 'the next day'],
            $submission,
        );
    }

    /** @return array<int, array{type: string, original: string, replacement: string}> */
    private function segments(string $original, string $corrected): array
    {
        if ($original === $corrected) {
            return [['type' => 'unchanged', 'original' => $original, 'replacement' => $corrected]];
        }

        return [
            ['type' => 'removed', 'original' => $original, 'replacement' => ''],
            ['type' => 'added', 'original' => '', 'replacement' => $corrected],
        ];
    }

    /** @return array<int, array{tag: string, text: string}> */
    private function explanations(string $original, string $corrected): array
    {
        if ($original === $corrected) {
            return [['tag' => 'Looks good', 'text' => 'This sentence is clear and grammatically correct.']];
        }

        return [['tag' => 'Grammar', 'text' => 'The controlled correction fixes verb tense and subject–verb agreement while preserving your meaning.']];
    }
};
