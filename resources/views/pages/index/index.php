<?php

use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public string $text = '';

    public bool $processing = false;

    /** @var array<int, array{id: int, text: string, completed: bool}> */
    public array $attempts = [];

    public function submitText(): void
    {
        if ($this->processing || $this->completedCorrections() >= 20) {
            return;
        }

        $this->text = trim($this->text);
        $this->validate(['text' => ['required', 'string', 'max:2000']]);

        $this->processing = true;
        $this->attempts[] = [
            'id' => $this->nextAttemptId(),
            'text' => $this->text,
            'completed' => false,
        ];
        $this->text = '';
    }

    public function clearSession(): void
    {
        if ($this->processing) {
            return;
        }

        $this->attempts = [];
        $this->text = '';
    }

    public function startFollowUp(int $attemptId, string $kind): void
    {
        if ($this->processing || ! $this->hasAttempt($attemptId) || ! in_array($kind, ['rewrite', 'example'], true)) {
            return;
        }

        $this->processing = true;
        $this->dispatch("correction-follow-up.{$attemptId}", kind: $kind);
    }

    public function retryAttempt(int $attemptId, string $stage): void
    {
        if ($this->processing || ! $this->hasAttempt($attemptId) || ! in_array($stage, ['stream', 'details'], true)) {
            return;
        }

        $this->processing = true;
        $this->dispatch("correction-retry.{$attemptId}", stage: $stage);
    }

    #[On('correction-attempt-finished')]
    public function finishAttempt(int $attemptId, bool $correctionCompleted = false): void
    {
        if ($this->hasAttempt($attemptId)) {
            $attemptIndex = array_find_key($this->attempts, fn (array $attempt): bool => $attempt['id'] === $attemptId);

            if ($correctionCompleted && $attemptIndex !== null) {
                $this->attempts[$attemptIndex]['completed'] = true;
            }

            $this->processing = false;
        }
    }

    public function completedCorrections(): int
    {
        return count(array_filter($this->attempts, fn (array $attempt): bool => $attempt['completed']));
    }

    private function hasAttempt(int $attemptId): bool
    {
        return collect($this->attempts)->contains('id', $attemptId);
    }

    private function nextAttemptId(): int
    {
        return empty($this->attempts) ? 0 : max(array_column($this->attempts, 'id')) + 1;
    }
};
