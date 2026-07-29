<?php

use App\Actions\GenerateCorrection;
use App\Ai\Agents\CorrectionDetailsAgent;
use App\Ai\Agents\CorrectionTextAgent;
use App\Ai\Prompts\CorrectionPrompt;

beforeEach(function (): void {
    config(['ai.verbally.gemini_model' => 'test-model']);
});

function fakeCorrection(string $text, array $details): void
{
    CorrectionTextAgent::fake([$text]);
    CorrectionDetailsAgent::fake([$details]);
}

it('handles missing configuration', function () {
    config(['ai.verbally.gemini_model' => null]);
    expect(app(GenerateCorrection::class)->stream('Hello', fn () => null)['stage'])->toBe('stream');
});

it('streams text and validates unchanged output', function () {
    fakeCorrection('Hello', ['corrected' => 'Hello', 'diff' => [['type' => 'unchanged', 'original' => 'Hello', 'replacement' => 'Hello']], 'explanations' => [['tag' => 'Looks good', 'text' => 'Correct.']], 'is_off_topic' => false]);
    $deltas = '';
    $result = app(GenerateCorrection::class)->stream('Hello', function (string $delta) use (&$deltas): void {
        $deltas .= $delta;
    });
    expect($deltas)->toBe('Hello')->and($result['error'])->toBeNull();
});

it('accepts pure deletions, pure insertions, and ordered substitutions', function (string $corrected, array $diff): void {
    fakeCorrection($corrected, [
        'corrected' => $corrected,
        'diff' => $diff,
        'explanations' => [['tag' => 'Grammar', 'text' => 'Correction details.']],
        'is_off_topic' => false,
    ]);

    expect(app(GenerateCorrection::class)->stream('Original text', fn () => null)['error'])->toBeNull();
})->with([
    'pure deletion' => [
        'The cat',
        [
            ['type' => 'unchanged', 'original' => 'The ', 'replacement' => 'The '],
            ['type' => 'removed', 'original' => 'the '],
            ['type' => 'unchanged', 'original' => 'cat', 'replacement' => 'cat'],
        ],
    ],
    'pure insertion' => [
        'I do go',
        [
            ['type' => 'unchanged', 'original' => 'I ', 'replacement' => 'I '],
            ['type' => 'added', 'replacement' => 'do '],
            ['type' => 'unchanged', 'original' => 'go', 'replacement' => 'go'],
        ],
    ],
    'substitution' => [
        'I have',
        [
            ['type' => 'unchanged', 'original' => 'I ', 'replacement' => 'I '],
            ['type' => 'removed', 'original' => 'has'],
            ['type' => 'added', 'replacement' => 'have'],
        ],
    ],
]);

it('rejects substitutions ordered as added then removed', function () {
    fakeCorrection('I have', [
        'corrected' => 'I have',
        'diff' => [
            ['type' => 'unchanged', 'original' => 'I ', 'replacement' => 'I '],
            ['type' => 'added', 'replacement' => 'have'],
            ['type' => 'removed', 'original' => 'has'],
        ],
        'explanations' => [['tag' => 'Grammar', 'text' => 'Correction details.']],
        'is_off_topic' => false,
    ]);

    expect(app(GenerateCorrection::class)->stream('I has', fn () => null)['stage'])->toBe('details');
});

it('rejects invalid structured and diff output', function () {
    fakeCorrection('Hello', ['corrected' => 'Different', 'diff' => [], 'explanations' => [], 'is_off_topic' => false]);
    expect(app(GenerateCorrection::class)->stream('Hello', fn () => null)['stage'])->toBe('details');
});

it('accepts off topic with empty details', function () {
    fakeCorrection('Please write an English sentence for correction.', ['corrected' => 'Please write an English sentence for correction.', 'diff' => [], 'explanations' => [], 'is_off_topic' => true]);
    expect(app(GenerateCorrection::class)->stream('What is physics?', fn () => null)['error'])->toBeNull();
});

it('instructs the model to reject educational affect and effect questions but correct interrogative text', function (): void {
    expect(CorrectionPrompt::instructions())
        ->toContain('What is the difference between affect and effect?')
        ->toContain('Do not classify a sentence as off-topic merely because it is a question.');
    expect(CorrectionPrompt::detailsInstructions())
        ->toContain('an interrogative sentence submitted for correction remains in scope');
});

it('rejects the invented same/replacement vocabulary observed from the real Gemini provider', function (): void {
    fakeCorrection('I went to the store', [
        'corrected' => 'I went to the store',
        'diff' => [
            ['type' => 'same', 'original' => 'I '],
            ['type' => 'replacement', 'original' => 'have went', 'replacement' => 'went'],
            ['type' => 'same', 'original' => ' to the store'],
        ],
        'explanations' => [['tag' => 'Grammar', 'text' => 'Correction details.']],
        'is_off_topic' => false,
    ]);

    expect(app(GenerateCorrection::class)->stream('I have went to the store', fn () => null)['stage'])->toBe('details');
});

it('rejects off-topic details carrying a diff and explanations observed from the real Gemini provider', function (): void {
    fakeCorrection('Please provide an English text for correction.', [
        'corrected' => 'Please provide an English text for correction.',
        'diff' => [
            ['type' => 'removed', 'original' => 'Qual e a capital do Brasil e por que voce acha isso?'],
            ['type' => 'added', 'replacement' => 'Please provide an English text for correction.'],
        ],
        'explanations' => [['tag' => 'off-topic', 'text' => 'The submitted text is in Portuguese.']],
        'is_off_topic' => true,
    ]);

    expect(app(GenerateCorrection::class)->stream('Qual e a capital do Brasil e por que voce acha isso?', fn () => null)['stage'])->toBe('details');
});

it('reports empty stream and provider failures as recoverable', function (string $message): void {
    CorrectionTextAgent::fake(function () use ($message): string {
        throw new RuntimeException($message);
    });
    expect(app(GenerateCorrection::class)->stream('Hello', fn () => null)['error'])->toContain('Try again');
})->with(['timeout', 'rate limit']);

it('reports an empty stream', function () {
    CorrectionTextAgent::fake(['']);
    expect(app(GenerateCorrection::class)->stream('Hello', fn () => null)['stage'])->toBe('stream');
});

it('marks a stream exception after deltas as stream and does not call details', function () {
    CorrectionTextAgent::fake(function (): string {
        throw new RuntimeException('timeout');
    });
    CorrectionDetailsAgent::fake()->preventStrayPrompts();
    $result = app(GenerateCorrection::class)->stream('Hello', fn () => null);
    expect($result['stage'])->toBe('stream');
});

it('escapes streamed markup before it reaches the callback', function () {
    fakeCorrection('<script>alert(1)</script>', ['corrected' => '<script>alert(1)</script>', 'diff' => [['type' => 'unchanged', 'original' => '<script>alert(1)</script>', 'replacement' => '<script>alert(1)</script>']], 'explanations' => [['tag' => 'Looks good', 'text' => 'Correct.']], 'is_off_topic' => false]);
    $streamed = '';
    app(GenerateCorrection::class)->stream('Hello', function (string $delta) use (&$streamed): void {
        $streamed .= htmlspecialchars($delta, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    });
    expect($streamed)->toContain('&lt;script&gt;');
});

it('retries only structured details after a streamed correction', function () {
    $textCalls = 0;
    $detailCalls = 0;
    CorrectionTextAgent::fake(function () use (&$textCalls): string {
        $textCalls++;

        return 'Hello';
    });
    CorrectionDetailsAgent::fake(function () use (&$detailCalls): array {
        $detailCalls++;

        return $detailCalls === 1
            ? ['corrected' => 'invalid', 'diff' => [], 'explanations' => [], 'is_off_topic' => false]
            : ['corrected' => 'Hello', 'diff' => [['type' => 'unchanged', 'original' => 'Hello', 'replacement' => 'Hello']], 'explanations' => [['tag' => 'Looks good', 'text' => 'Correct.']], 'is_off_topic' => false];
    });
    $action = app(GenerateCorrection::class);
    $first = $action->stream('Hello', fn () => null);
    $second = $action->details('Hello', $first['corrected']);
    expect($first['error'])->not->toBeNull()->and($second['error'])->toBeNull()->and($textCalls)->toBe(1)->and($detailCalls)->toBe(2);
});
