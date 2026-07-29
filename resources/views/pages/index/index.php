<?php

use App\Actions\GenerateCorrection;
use App\Actions\GenerateFollowUp;
use Livewire\Component;

new class extends Component
{
    public string $text = '';

    public bool $processing = false;

    /** @var array<int, array{id: int, text: string, corrected: string, segments: array<int, array{type: string, original?: string, replacement?: string}>, explanations: array<int, array{tag: string, text: string}>, followUps: array<int, array{label: string, text: string}>, followUpPending: bool, followUpKind: string|null, followUpError: string|null, pending: bool, error: string|null, error_stage: string|null, off_topic: bool}> */
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
            'followUpPending' => false,
            'followUpKind' => null,
            'followUpError' => null,
            'pending' => true,
            'error' => null,
            'off_topic' => false,
            'error_stage' => null,
        ];
        $this->text = '';

        $this->js('$wire.completeCorrection('.$attemptId.').catch(() => $wire.releaseStaleOperation('.$attemptId.'))');
    }

    public function completeCorrection(int $attemptId): void
    {
        if (! $this->processing || ! isset($this->attempts[$attemptId]) || ! $this->attempts[$attemptId]['pending']) {
            return;
        }

        $result = app(GenerateCorrection::class)->stream($this->attempts[$attemptId]['text'], function (string $delta) use ($attemptId): void {
            $this->attempts[$attemptId]['corrected'] .= $delta;
            $this->stream(htmlspecialchars($this->attempts[$attemptId]['corrected'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), replace: true, to: 'corrected-'.$attemptId);
        });

        $this->attempts[$attemptId]['pending'] = false;
        $this->processing = false;
        $this->attempts[$attemptId]['error'] = $result['error'];
        $this->attempts[$attemptId]['error_stage'] = $result['stage'];
        if ($result['details'] !== null) {
            $this->attempts[$attemptId]['segments'] = $result['details']['diff'];
            $this->attempts[$attemptId]['explanations'] = $result['details']['explanations'];
            $this->attempts[$attemptId]['off_topic'] = $result['details']['is_off_topic'];
        }
    }

    public function retryDetails(int $attemptId): void
    {
        if ($this->processing || ! isset($this->attempts[$attemptId])) {
            return;
        }

        if ($this->attempts[$attemptId]['error_stage'] === 'stream') {
            $this->attempts[$attemptId]['pending'] = true;
            $this->attempts[$attemptId]['corrected'] = '';
            $this->attempts[$attemptId]['segments'] = [];
            $this->attempts[$attemptId]['explanations'] = [];
            $this->attempts[$attemptId]['error'] = null;
            $this->processing = true;
            $this->completeCorrection($attemptId);

            return;
        }
        if (blank($this->attempts[$attemptId]['corrected'])) {
            return;
        }

        $this->processing = true;
        $attempt = $this->attempts[$attemptId];
        $result = app(GenerateCorrection::class)->details($attempt['text'], $attempt['corrected']);
        $this->processing = false;
        $this->attempts[$attemptId]['error'] = $result['error'];
        $this->attempts[$attemptId]['error_stage'] = $result['error'] === null ? null : 'details';
        if ($result['details'] !== null) {
            $this->attempts[$attemptId]['segments'] = $result['details']['diff'];
            $this->attempts[$attemptId]['explanations'] = $result['details']['explanations'];
            $this->attempts[$attemptId]['off_topic'] = $result['details']['is_off_topic'];
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

    public function startFollowUp(int $attemptId, string $kind): void
    {
        if ($this->processing || ! isset($this->attempts[$attemptId]) || ! in_array($kind, ['rewrite', 'example'], true)) {
            return;
        }

        $attempt = $this->attempts[$attemptId];
        if ($attempt['pending'] || $attempt['error'] !== null || $attempt['off_topic'] || blank($attempt['corrected'])) {
            return;
        }

        $this->processing = true;
        $this->attempts[$attemptId]['followUpKind'] = $kind;
        $this->attempts[$attemptId]['followUpPending'] = true;
        $this->attempts[$attemptId]['followUpError'] = null;

        $this->js('$wire.completeFollowUp('.$attemptId.').catch(() => $wire.releaseStaleOperation('.$attemptId.'))');

        /* A follow-up click resolves to the conversation island, so the header and editor islands
        must ship in the same response or their controls would stay enabled while the session is locked. */
        $this->renderIsland('header');
        $this->renderIsland('editor');
    }

    public function completeFollowUp(int $attemptId): void
    {
        if (! $this->processing || ! isset($this->attempts[$attemptId]) || ! $this->attempts[$attemptId]['followUpPending']) {
            return;
        }

        $attempt = $this->attempts[$attemptId];
        $followUps = app(GenerateFollowUp::class);
        $result = $attempt['followUpKind'] === 'rewrite'
            ? $followUps->rewrite($attempt['text'], $attempt['corrected'])
            : $followUps->example($attempt['text'], $attempt['corrected']);

        $this->attempts[$attemptId]['followUpPending'] = false;
        $this->processing = false;
        $this->attempts[$attemptId]['followUpError'] = $result['error'];

        if ($result['text'] !== null) {
            $this->attempts[$attemptId]['followUps'][] = ['label' => $result['label'], 'text' => $result['text']];
            $this->attempts[$attemptId]['followUpKind'] = null;
        }
    }

    /**
     * Unlock the session when the follow-up request that should finish an operation never lands.
     *
     * Both operations run in two hops, and the browser holds the only copy of the session, so a
     * request that fails or is cancelled leaves the lock set with nothing left to release it.
     */
    public function releaseStaleOperation(int $attemptId): void
    {
        $message = 'Verbally lost contact with the server. Try again.';

        if (isset($this->attempts[$attemptId]) && $this->attempts[$attemptId]['followUpPending']) {
            $this->attempts[$attemptId]['followUpPending'] = false;
            $this->attempts[$attemptId]['followUpError'] = $message;
        } elseif (isset($this->attempts[$attemptId]) && $this->attempts[$attemptId]['pending']) {
            $this->attempts[$attemptId]['pending'] = false;
            $this->attempts[$attemptId]['error'] = $message;
            $this->attempts[$attemptId]['error_stage'] = 'stream';
        }

        $this->processing = false;
    }
};
