<?php

use App\Ai\Agents\CorrectionDetailsAgent;
use App\Ai\Agents\CorrectionTextAgent;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config(['ai.verbally.gemini_model' => 'gemini-test']);
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

it('clears the session and keeps follow-ups on the same correction', function () {
    $component = Livewire::test('pages::index')->set('text', 'I have went home.')->call('submitText')->call('completeCorrection', 0);
    $component->call('rewriteNaturally', 0)->call('moreExamples', 0)->assertCount('attempts', 1)->assertSee('Natural rewrite')->assertSee('Example');
    $component->call('clearSession')->assertCount('attempts', 0)->assertSee('Your corrections will appear here.');
});

it('starts a fresh in-memory session on a new component', function () {
    Livewire::test('pages::index')->set('text', 'A sentence.')->call('submitText')->call('completeCorrection', 0);
    Livewire::test('pages::index')->assertCount('attempts', 0)->assertSee('Session · 0 corrections');
});
