<?php

use App\Ai\Agents\CorrectionDetailsAgent;
use App\Ai\Agents\CorrectionTextAgent;
use App\Ai\Agents\ExampleAgent;
use App\Ai\Agents\NaturalRewriteAgent;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config(['ai.verbally.gemini_model' => 'gemini-test']);
    NaturalRewriteAgent::fake(['A more natural way to say it.']);
    ExampleAgent::fake(['A short example sentence.']);
    CorrectionTextAgent::fake(fn (string $prompt): string => str_replace('I have went', 'I went', $prompt));
    CorrectionDetailsAgent::fake(function (string $prompt): array {
        preg_match('/Original text:\n(.*?)\n\nPreviously streamed corrected text:\n(.*)$/s', $prompt, $matches);
        $original = $matches[1] ?? '';
        $corrected = $matches[2] ?? $original;

        return [
            'corrected' => $corrected,
            'diff' => [['type' => 'unchanged', 'original' => $corrected, 'replacement' => $corrected]],
            'explanations' => [['tag' => 'Grammar', 'text' => 'The sentence is clear and correct.']],
            'is_off_topic' => false,
        ];
    });
});

function correctionAttempt(): Testable
{
    return Livewire::test('correction-attempt', [
        'attemptId' => 0,
        'submission' => 'I have went home.',
        'sessionProcessing' => true,
    ]);
}

it('mounts one independent attempt island and starts only after its stream target exists', function (): void {
    correctionAttempt()
        ->assertSet('pending', true)
        ->assertSee('Correcting…')
        ->assertSee('$nextTick(() => $wire.completeCorrection().catch(() => $wire.reportStaleOperation()))', false)
        ->assertSee('FRAGMENT:type=island|name=attempt', false);
});

it('streams and completes one correction without re-rendering its parent', function (): void {
    correctionAttempt()
        ->call('completeCorrection')
        ->assertSet('pending', false)
        ->assertSet('corrected', 'I went home.')
        ->assertSee('Grammar')
        ->assertDispatched('correction-attempt-finished', attemptId: 0);
});

it('processes a follow-up after the parent grants the global operation lock', function (): void {
    correctionAttempt()
        ->call('completeCorrection')
        ->dispatch('correction-follow-up.0', kind: 'rewrite')
        ->assertSet('followUpPending', true)
        ->assertJs('$wire.completeFollowUp().catch(() => $wire.reportStaleOperation())')
        ->call('completeFollowUp')
        ->assertSet('followUpPending', false)
        ->assertSet('followUpKind', null)
        ->assertSee('A more natural way to say it.')
        ->assertDispatched('correction-attempt-finished', attemptId: 0);
});

it('keeps a failed follow-up on the same child and allows the parent to grant another retry', function (): void {
    NaturalRewriteAgent::fake(function (): string {
        throw new RuntimeException('timeout');
    });

    $component = correctionAttempt()
        ->call('completeCorrection')
        ->dispatch('correction-follow-up.0', kind: 'rewrite')
        ->call('completeFollowUp')
        ->assertSet('followUpPending', false)
        ->assertSee('Try again');

    expect($component->get('followUps'))->toBe([])
        ->and($component->get('followUpKind'))->toBe('rewrite');
});

it('retries only the requested correction stage inside the same child boundary', function (): void {
    correctionAttempt()
        ->call('completeCorrection')
        ->set('error', 'Gemini returned invalid correction details. Try again.')
        ->set('errorStage', 'details')
        ->dispatch('correction-retry.0', stage: 'details')
        ->assertSet('error', null)
        ->assertSet('errorStage', null)
        ->assertDispatched('correction-attempt-finished', attemptId: 0);
});

it('turns an interrupted child operation into a retryable error and releases the session lock', function (): void {
    correctionAttempt()
        ->call('reportStaleOperation')
        ->assertSet('pending', false)
        ->assertSet('errorStage', 'stream')
        ->assertSee('Verbally lost contact with the server. Try again.')
        ->assertDispatched('correction-attempt-finished', attemptId: 0);
});

it('does not start a follow-up for an off-topic response', function (): void {
    CorrectionTextAgent::fake(['Please write an English sentence.']);
    CorrectionDetailsAgent::fake(fn (): array => [
        'corrected' => 'Please write an English sentence.',
        'diff' => [],
        'explanations' => [],
        'is_off_topic' => true,
    ]);

    correctionAttempt()
        ->call('completeCorrection')
        ->dispatch('correction-follow-up.0', kind: 'example')
        ->assertSet('offTopic', true)
        ->assertSet('followUpPending', false)
        ->assertSet('followUps', []);
});
