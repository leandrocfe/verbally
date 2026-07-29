<?php

use App\Actions\GenerateCorrection;
use App\Ai\Agents\CorrectionDetailsAgent;
use App\Ai\Agents\CorrectionTextAgent;

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

it('rejects invalid structured and diff output', function () {
    fakeCorrection('Hello', ['corrected' => 'Different', 'diff' => [], 'explanations' => [], 'is_off_topic' => false]);
    expect(app(GenerateCorrection::class)->stream('Hello', fn () => null)['stage'])->toBe('details');
});

it('accepts off topic with empty details', function () {
    fakeCorrection('Please write an English sentence for correction.', ['corrected' => 'Please write an English sentence for correction.', 'diff' => [], 'explanations' => [], 'is_off_topic' => true]);
    expect(app(GenerateCorrection::class)->stream('What is physics?', fn () => null)['error'])->toBeNull();
});

it('reports empty stream and provider failures as recoverable', function (string $message): void {
    CorrectionTextAgent::fake(function () use ($message): string {
        throw new RuntimeException($message);
    });
    expect(app(GenerateCorrection::class)->stream('Hello', fn () => null)['error'])->toContain('Try again');
})->with(['timeout', 'rate limit']);
