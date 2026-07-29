<?php

use App\Actions\GenerateFollowUp;
use App\Ai\Agents\ExampleAgent;
use App\Ai\Agents\NaturalRewriteAgent;

beforeEach(function (): void {
    config(['ai.verbally.gemini_model' => 'test-model']);
});

it('returns exactly one natural rewrite alternative', function () {
    NaturalRewriteAgent::fake(['I went home early because it was raining.']);
    ExampleAgent::fake()->preventStrayPrompts();

    $result = app(GenerateFollowUp::class)->rewrite('I go home early', 'I went home early because it was raining.');

    expect($result)->toBe(['label' => 'Natural rewrite', 'text' => 'I went home early because it was raining.', 'error' => null]);
});

it('returns exactly one example', function () {
    ExampleAgent::fake(['She has lived here since 2020.']);
    NaturalRewriteAgent::fake()->preventStrayPrompts();

    $result = app(GenerateFollowUp::class)->example('She live here since 2020', 'She has lived here since 2020.');

    expect($result)->toBe(['label' => 'Example', 'text' => 'She has lived here since 2020.', 'error' => null]);
});

it('handles missing configuration without calling any agent', function () {
    config(['ai.verbally.gemini_model' => null]);
    NaturalRewriteAgent::fake()->preventStrayPrompts();
    ExampleAgent::fake()->preventStrayPrompts();

    $result = app(GenerateFollowUp::class)->rewrite('Hello', 'Hello.');

    expect($result)->toBe(['label' => 'Natural rewrite', 'text' => null, 'error' => 'Gemini is not configured. Try again later.']);
});

it('reports provider failures as recoverable', function (string $message): void {
    NaturalRewriteAgent::fake(function () use ($message): string {
        throw new RuntimeException($message);
    });

    $result = app(GenerateFollowUp::class)->rewrite('Hello', 'Hello.');

    expect($result['text'])->toBeNull()->and($result['error'])->toContain('Try again');
})->with(['timeout', 'rate limit', 'anything else']);

it('reports an empty response as recoverable', function (string $response) {
    ExampleAgent::fake([$response]);

    $result = app(GenerateFollowUp::class)->example('Hello', 'Hello.');

    expect($result)->toBe(['label' => 'Example', 'text' => null, 'error' => 'Gemini returned an empty response. Try again.']);
})->with(['', '   ']);

it('reports a response over 300 characters as recoverable', function () {
    NaturalRewriteAgent::fake([str_repeat('a', 301)]);

    $result = app(GenerateFollowUp::class)->rewrite('Hello', 'Hello.');

    expect($result['text'])->toBeNull()->and($result['error'])->toContain('Try again');
});

it('sends the original submission and the corrected text in the prompt', function () {
    ExampleAgent::fake(['An example sentence.']);

    app(GenerateFollowUp::class)->example('She go home', 'She goes home.');

    ExampleAgent::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, 'She go home') && str_contains($prompt->prompt, 'She goes home.'));
});
