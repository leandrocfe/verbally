<?php

use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class);

it('shows the empty session', function (): void {
    Livewire::test('pages::index')
        ->assertSee('Your corrections will appear here.')
        ->assertSee('Session · 0 corrections');
});

it('creates an attempt descriptor, locks the session, and mounts a keyed child', function (): void {
    Livewire::test('pages::index')
        ->set('text', "  I have went\n to the store.  ")
        ->call('submitText')
        ->assertSet('text', '')
        ->assertSet('processing', true)
        ->assertSet('attempts', [['id' => 0, 'text' => "I have went\n to the store."]])
        ->assertSeeLivewire('correction-attempt')
        ->assertSee('wire:key="attempt-0"', false);
});

it('validates and limits session submissions', function (): void {
    Livewire::test('pages::index')->set('text', '   ')->call('submitText')->assertHasErrors('text');
    Livewire::test('pages::index')->set('text', str_repeat('a', 2001))->call('submitText')->assertHasErrors(['text' => ['max:2000']]);

    $component = Livewire::test('pages::index');
    foreach (range(1, 20) as $number) {
        $component->set('text', "Sentence {$number}.")->call('submitText')->dispatch('correction-attempt-finished', attemptId: $number - 1);
    }

    $component->set('text', 'The twenty-first sentence.')->call('submitText')
        ->assertCount('attempts', 20)
        ->assertSee('Session · 20 corrections');
});

it('locks submissions, follow-ups, retries, and clearing until a child reports completion', function (): void {
    $component = Livewire::test('pages::index')
        ->set('text', 'A sentence.')
        ->call('submitText')
        ->set('text', 'Another sentence.');

    $component->call('submitText')->assertCount('attempts', 1)->assertSet('text', 'Another sentence.');
    $component->call('startFollowUp', 0, 'rewrite')->assertNotDispatched('correction-follow-up.0');
    $component->call('retryAttempt', 0, 'stream')->assertNotDispatched('correction-retry.0');
    $component->call('clearSession')->assertCount('attempts', 1);

    $component->dispatch('correction-attempt-finished', attemptId: 0)->assertSet('processing', false);
    $component->call('startFollowUp', 0, 'rewrite')
        ->assertSet('processing', true)
        ->assertDispatched('correction-follow-up.0', kind: 'rewrite');
});

it('routes retries only to a known attempt while holding the global lock', function (): void {
    $component = Livewire::test('pages::index')
        ->set('text', 'A sentence.')
        ->call('submitText')
        ->dispatch('correction-attempt-finished', attemptId: 0)
        ->call('retryAttempt', 0, 'details')
        ->assertSet('processing', true)
        ->assertDispatched('correction-retry.0', stage: 'details');

    $component->dispatch('correction-attempt-finished', attemptId: 0)
        ->call('retryAttempt', 99, 'stream')
        ->assertNotDispatched('correction-retry.99');
});

it('clears the descriptors only when no child operation is active', function (): void {
    Livewire::test('pages::index')
        ->set('text', 'A sentence.')
        ->call('submitText')
        ->dispatch('correction-attempt-finished', attemptId: 0)
        ->call('clearSession')
        ->assertCount('attempts', 0)
        ->assertSee('Your corrections will appear here.');
});
