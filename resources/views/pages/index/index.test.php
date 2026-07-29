<?php

use App\Ai\Agents\CorrectionDetailsAgent;
use App\Ai\Agents\CorrectionTextAgent;
use App\Ai\Agents\ExampleAgent;
use App\Ai\Agents\NaturalRewriteAgent;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config(['ai.verbally.gemini_model' => 'gemini-test']);
    NaturalRewriteAgent::fake(['A more natural way to say it.']);
    ExampleAgent::fake(['A short example sentence.']);
    CorrectionTextAgent::fake(function (string $prompt): string {
        return str_replace('I have went', 'I went', $prompt);
    });
    CorrectionDetailsAgent::fake(function (string $prompt): array {
        preg_match('/Original text:\n(.*?)\n\nPreviously streamed corrected text:\n(.*)$/s', $prompt, $matches);
        $original = $matches[1] ?? '';
        $corrected = $matches[2] ?? $original;

        return [
            'corrected' => $corrected,
            'diff' => $original === $corrected
                ? [['type' => 'unchanged', 'original' => $original, 'replacement' => $corrected]]
                : [['type' => 'removed', 'original' => $original], ['type' => 'added', 'replacement' => $corrected]],
            'explanations' => [['tag' => 'Grammar', 'text' => 'The sentence is clear and correct.']],
            'is_off_topic' => false,
        ];
    });
});

it('shows the empty session', function () {
    Livewire::test('pages::index')->assertSee('Your corrections will appear here.')->assertSee('Session · 0 corrections');
});

it('validates and trims submissions while preserving newlines', function () {
    Livewire::test('pages::index')
        ->set('text', "  I have went\n to the store.  ")
        ->call('submitText')
        ->assertSet('text', '')
        ->assertSee('I have went')
        ->assertSee('Correcting…')
        ->assertJs('$wire.completeCorrection(0).catch(() => $wire.releaseStaleOperation(0))')
        ->assertSet('processing', true)
        ->call('completeCorrection', 0)
        ->assertSet('processing', false)
        ->assertSee('Session · 1 corrections');
});

it('rejects empty and oversized submissions', function () {
    Livewire::test('pages::index')->set('text', '   ')->call('submitText')->assertHasErrors('text');
    Livewire::test('pages::index')->set('text', str_repeat('a', 2000))->call('submitText')->assertSet('processing', true);
    Livewire::test('pages::index')->set('text', str_repeat('a', 2001))->call('submitText')->assertHasErrors(['text' => ['max:2000']]);
});

it('limits the session to twenty corrections before creating another attempt', function () {
    $component = Livewire::test('pages::index');
    foreach (range(1, 20) as $number) {
        $component->set('text', "Sentence {$number}.")->call('submitText')->call('completeCorrection', $number - 1);
    }

    $component->set('text', 'The twenty-first sentence.')->call('submitText')->assertCount('attempts', 20)->assertSee('Session · 20 corrections');
});

function correctedComponent(string $text = 'I have went home.'): Testable
{
    return Livewire::test('pages::index')->set('text', $text)->call('submitText')->call('completeCorrection', 0);
}

it('adds a single natural rewrite without creating another correction', function () {
    $component = correctedComponent()
        ->call('startFollowUp', 0, 'rewrite')
        ->assertSet('processing', true)
        ->assertJs('$wire.completeFollowUp(0).catch(() => $wire.releaseStaleOperation(0))')
        ->assertSee('Writing follow-up…')
        ->call('completeFollowUp', 0)
        ->assertSet('processing', false)
        ->assertCount('attempts', 1)
        ->assertSee('Natural rewrite')
        ->assertSee('A more natural way to say it.')
        ->assertSee('Session · 1 corrections');

    expect($component->get('attempts')[0]['followUps'])->toHaveCount(1);
});

it('adds a single example and accumulates follow-ups on the same correction in order', function () {
    $component = correctedComponent()
        ->call('startFollowUp', 0, 'example')->call('completeFollowUp', 0)
        ->assertCount('attempts', 1)
        ->assertSee('A short example sentence.')
        ->call('startFollowUp', 0, 'rewrite')->call('completeFollowUp', 0)
        ->assertCount('attempts', 1)
        ->assertSee('Session · 1 corrections');

    expect(array_column($component->get('attempts')[0]['followUps'], 'label'))->toBe(['Example', 'Natural rewrite']);
});

it('locks submissions, card actions, and clearing while a follow-up runs', function () {
    $component = correctedComponent()->set('text', 'Another sentence.')->call('startFollowUp', 0, 'rewrite');

    $component->call('submitText')->assertCount('attempts', 1)->assertSet('text', 'Another sentence.');
    $component->call('clearSession')->assertCount('attempts', 1)->assertSet('text', 'Another sentence.');
    $component->call('retryDetails', 0)->assertSet('processing', true);
    $component->call('startFollowUp', 0, 'example');
    expect($component->get('attempts')[0]['followUpKind'])->toBe('rewrite');

    $component->call('completeFollowUp', 0)->assertSet('processing', false);
    $component->call('clearSession')->assertCount('attempts', 0);
});

it('refuses follow-ups on attempts that are not completed corrections', function () {
    /* Release the session lock so the refusal has to come from the attempt still being pending. */
    $pending = Livewire::test('pages::index')->set('text', 'A sentence.')->call('submitText')->set('processing', false)->call('startFollowUp', 0, 'rewrite');
    expect($pending->get('attempts')[0]['pending'])->toBeTrue()
        ->and($pending->get('attempts')[0]['followUpPending'])->toBeFalse()
        ->and($pending->get('processing'))->toBeFalse();

    config(['ai.verbally.gemini_model' => null]);
    $failed = Livewire::test('pages::index')->set('text', 'A sentence.')->call('submitText')->call('completeCorrection', 0)->call('startFollowUp', 0, 'rewrite');
    expect($failed->get('attempts')[0]['error'])->not->toBeNull()
        ->and($failed->get('attempts')[0]['followUpPending'])->toBeFalse()
        ->and($failed->get('processing'))->toBeFalse();
});

it('refuses follow-ups on an off-topic response', function () {
    CorrectionDetailsAgent::fake(fn (): array => ['corrected' => 'Please write an English sentence.', 'diff' => [], 'explanations' => [], 'is_off_topic' => true]);
    CorrectionTextAgent::fake(['Please write an English sentence.']);

    $component = Livewire::test('pages::index')->set('text', 'Was ist Physik?')->call('submitText')->call('completeCorrection', 0)->call('startFollowUp', 0, 'example');

    expect($component->get('attempts')[0]['off_topic'])->toBeTrue()
        ->and($component->get('attempts')[0]['followUpPending'])->toBeFalse();
});

it('keeps the correction visible when a follow-up fails and retries only the follow-up', function () {
    $detailCalls = 0;
    CorrectionDetailsAgent::fake(function () use (&$detailCalls): array {
        $detailCalls++;

        return ['corrected' => 'I went home.', 'diff' => [['type' => 'unchanged', 'original' => 'I went home.', 'replacement' => 'I went home.']], 'explanations' => [['tag' => 'Past tense', 'text' => 'Use the simple past.']], 'is_off_topic' => false];
    });
    CorrectionTextAgent::fake(['I went home.']);

    $rewriteCalls = 0;
    NaturalRewriteAgent::fake(function () use (&$rewriteCalls): string {
        $rewriteCalls++;

        if ($rewriteCalls === 1) {
            throw new RuntimeException('timeout');
        }

        return 'A more natural way to say it.';
    });

    $component = correctedComponent('I have went home.')
        ->call('startFollowUp', 0, 'rewrite')->call('completeFollowUp', 0)
        ->assertSet('processing', false)
        ->assertSee('Try again')
        ->assertSee('I went home.')
        ->assertSee('Past tense');

    expect($component->get('attempts')[0]['followUpError'])->toContain('Try again')
        ->and($component->get('attempts')[0]['followUps'])->toBe([]);

    $component->call('startFollowUp', 0, 'rewrite')->call('completeFollowUp', 0)->assertSee('A more natural way to say it.');

    expect($component->get('attempts')[0]['followUpError'])->toBeNull()
        ->and($component->get('attempts')[0]['followUps'])->toHaveCount(1)
        ->and($rewriteCalls)->toBe(2)
        ->and($detailCalls)->toBe(1);
});

it('refreshes the header and editor islands when a follow-up locks the session', function () {
    $fragments = correctedComponent()->call('startFollowUp', 0, 'rewrite')->effects['islandFragments'] ?? [];

    expect(implode('', $fragments))->toContain('name=header')->toContain('name=editor');
});

it('unlocks the session and reports a recoverable error when a follow-up never completes', function () {
    $component = correctedComponent()
        ->call('startFollowUp', 0, 'rewrite')
        ->assertSet('processing', true)
        ->call('releaseStaleOperation', 0)
        ->assertSet('processing', false)
        ->assertSee('Try again');

    expect($component->get('attempts')[0]['followUpPending'])->toBeFalse()
        ->and($component->get('attempts')[0]['followUpError'])->toContain('Try again')
        ->and($component->get('attempts')[0]['followUps'])->toBe([]);

    $component->call('startFollowUp', 0, 'rewrite')->call('completeFollowUp', 0)->assertSee('A more natural way to say it.');
    $component->call('clearSession')->assertCount('attempts', 0);
});

it('unlocks the session and keeps the card retryable when a correction never completes', function () {
    $component = Livewire::test('pages::index')
        ->set('text', 'I have went home.')
        ->call('submitText')
        ->assertSet('processing', true)
        ->call('releaseStaleOperation', 0)
        ->assertSet('processing', false)
        ->assertSee('Try again');

    expect($component->get('attempts')[0]['pending'])->toBeFalse()
        ->and($component->get('attempts')[0]['error'])->toContain('Try again')
        ->and($component->get('attempts')[0]['error_stage'])->toBe('stream');

    $component->call('retryDetails', 0);

    expect($component->get('attempts')[0]['error'])->toBeNull()
        ->and($component->get('attempts')[0]['corrected'])->toContain('I went home.')
        ->and($component->get('processing'))->toBeFalse();
});

it('clears the session after follow-ups', function () {
    correctedComponent()
        ->call('startFollowUp', 0, 'rewrite')->call('completeFollowUp', 0)
        ->call('clearSession')->assertCount('attempts', 0)->assertSee('Your corrections will appear here.');
});

it('starts a fresh in-memory session on a new component', function () {
    Livewire::test('pages::index')->set('text', 'A sentence.')->call('submitText')->call('completeCorrection', 0);
    Livewire::test('pages::index')->assertCount('attempts', 0)->assertSee('Session · 0 corrections');
});
