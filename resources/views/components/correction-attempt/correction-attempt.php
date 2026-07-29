<?php

use App\Actions\GenerateCorrection;
use App\Actions\GenerateFollowUp;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Component;

new class extends Component
{
    public int $attemptId;

    public string $submission;

    #[Reactive]
    public bool $sessionProcessing;

    public string $corrected = '';

    /** @var array<int, array{type: string, original?: string, replacement?: string}> */
    public array $segments = [];

    /** @var array<int, array{tag: string, text: string}> */
    public array $explanations = [];

    /** @var array<int, array{label: string, text: string}> */
    public array $followUps = [];

    public bool $pending = true;

    public ?string $error = null;

    public ?string $errorStage = null;

    public bool $offTopic = false;

    public bool $followUpPending = false;

    public ?string $followUpKind = null;

    public ?string $followUpError = null;

    public function completeCorrection(): void
    {
        if (! $this->pending) {
            return;
        }

        $result = app(GenerateCorrection::class)->stream($this->submission, function (string $delta): void {
            $this->corrected .= $delta;
            $this->stream(htmlspecialchars($this->corrected, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), replace: true, ref: 'corrected');
        });

        $this->pending = false;
        $this->error = $result['error'];
        $this->errorStage = $result['stage'];

        if ($result['details'] !== null) {
            $this->setDetails($result['details']);
        }

        $this->finish($this->error === null && ! $this->offTopic);
    }

    #[On('correction-follow-up.{attemptId}')]
    public function startFollowUp(string $kind): void
    {
        if (! in_array($kind, ['rewrite', 'example'], true) || $this->pending || $this->error !== null || $this->offTopic || blank($this->corrected)) {
            $this->finish($this->error === null && ! $this->offTopic);

            return;
        }

        $this->followUpKind = $kind;
        $this->followUpPending = true;
        $this->followUpError = null;
        $this->js('$wire.completeFollowUp().catch(() => $wire.reportStaleOperation())');
    }

    public function completeFollowUp(): void
    {
        if (! $this->followUpPending || $this->followUpKind === null) {
            return;
        }

        $followUps = app(GenerateFollowUp::class);
        $result = $this->followUpKind === 'rewrite'
            ? $followUps->rewrite($this->submission, $this->corrected)
            : $followUps->example($this->submission, $this->corrected);

        $this->followUpPending = false;
        $this->followUpError = $result['error'];

        if ($result['text'] !== null) {
            $this->followUps[] = ['label' => $result['label'], 'text' => $result['text']];
            $this->followUpKind = null;
        }

        $this->finish($this->error === null && ! $this->offTopic);
    }

    #[On('correction-retry.{attemptId}')]
    public function retry(string $stage): void
    {
        if (! in_array($stage, ['stream', 'details'], true)) {
            $this->finish(false);

            return;
        }

        if ($stage === 'stream') {
            $this->pending = true;
            $this->corrected = '';
            $this->segments = [];
            $this->explanations = [];
            $this->error = null;
            $this->errorStage = null;
            $this->js('$wire.completeCorrection().catch(() => $wire.reportStaleOperation())');

            return;
        }

        if (blank($this->corrected)) {
            $this->finish(false);

            return;
        }

        $result = app(GenerateCorrection::class)->details($this->submission, $this->corrected);
        $this->error = $result['error'];
        $this->errorStage = $result['error'] === null ? null : 'details';

        if ($result['details'] !== null) {
            $this->setDetails($result['details']);
        }

        $this->finish($this->error === null && ! $this->offTopic);
    }

    public function reportStaleOperation(): void
    {
        $message = 'Verbally lost contact with the server. Try again.';

        if ($this->followUpPending) {
            $this->followUpPending = false;
            $this->followUpError = $message;
        } elseif ($this->pending) {
            $this->pending = false;
            $this->error = $message;
            $this->errorStage = 'stream';
        }

        $this->finish(false);
    }

    /** @param array{diff: array<int, array{type: string, original?: string, replacement?: string}>, explanations: array<int, array{tag: string, text: string}>, is_off_topic: bool} $details */
    private function setDetails(array $details): void
    {
        $this->segments = $details['diff'];
        $this->explanations = $details['explanations'];
        $this->offTopic = $details['is_off_topic'];
    }

    private function finish(bool $correctionCompleted = false): void
    {
        $this->dispatch('correction-attempt-finished', attemptId: $this->attemptId, correctionCompleted: $correctionCompleted);
    }
};
