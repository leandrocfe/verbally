<?php

function submitBrowserCorrection(object $page, string $text): void
{
    $page->fill('textarea[aria-label="Write in English"]', $text);
    clickBrowserButton($page, 'Correct my text');
}

function clickBrowserButton(object $page, string $label): void
{
    $page->script(sprintf(
        "[...document.querySelectorAll('button')].find((button) => button.textContent.includes(%s)).click()",
        json_encode($label, JSON_THROW_ON_ERROR),
    ));
}

it('shows the empty conversation and has no browser errors', function (): void {
    visit('/')
        ->assertSee('Your corrections will appear here.')
        ->assertSee('Write in English')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

it('keeps the global lock while a correction is pending, then streams fixture-backed details', function (): void {
    $page = visit('/');

    submitBrowserCorrection($page, 'I am waiting for a correction.');

    $page->assertSee('Correcting…')
        ->assertScript('document.querySelector(\'article\').classList.contains(\'motion-safe:animate-pulse\')', true)
        ->assertScript('document.querySelector(\'textarea[aria-label="Write in English"]\').disabled', true)
        ->assertScript('[...document.querySelectorAll(\'button\')].find((button) => button.textContent.includes(\'Clear session\')).disabled', true)
        ->wait(1)
        ->assertSee('Corrected')
        ->assertSee('I am waiting for a correction.')
        ->assertScript('document.querySelector(\'article\').classList.contains(\'motion-safe:animate-pulse\')', false)
        ->assertSee('The sentence is clear and correct.')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

it('streams a correction, supports a successful follow-up, and preserves the correction', function (): void {
    $page = visit('/');

    submitBrowserCorrection($page, 'She go home.');

    $page->wait(1)
        ->assertSee('Corrected')
        ->assertSee('She goes home.')
        ->assertSee('Use goes with she.');

    clickBrowserButton($page, 'Rewrite naturally');

    $page
        ->wait(1)
        ->assertSee('Natural rewrite:')
        ->assertSee('She went home early.')
        ->assertSee('She goes home.')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

it('retries structured details without re-streaming the correction', function (): void {
    $page = visit('/');

    submitBrowserCorrection($page, 'I needs a details retry.');

    $page->wait(1)
        ->assertSee('Gemini returned invalid correction details. Try again.');

    clickBrowserButton($page, 'Try again');

    $page
        ->wait(1)
        ->assertSee('I need a details retry.')
        ->assertSee('The sentence is clear and correct.')
        ->assertNoJavaScriptErrors();
});

it('renders a recoverable stream error and retries it', function (): void {
    $page = visit('/');

    submitBrowserCorrection($page, 'I need a correction retry.');

    $page->wait(1)
        ->assertSee('Gemini timed out. Try again.');

    clickBrowserButton($page, 'Try again');

    $page
        ->wait(1)
        ->assertSee('I need a correction retry.')
        ->assertSee('The sentence is clear and correct.')
        ->assertNoJavaScriptErrors();
});

it('renders a recoverable follow-up error and retries only that follow-up', function (): void {
    $page = visit('/');

    submitBrowserCorrection($page, 'I needs a follow-up retry.');

    $page->wait(1)
        ->assertSee('I need a follow-up retry.');

    clickBrowserButton($page, 'Rewrite naturally');

    $page
        ->wait(1)
        ->assertSee('Gemini timed out. Try again.');

    clickBrowserButton($page, 'Try again');

    $page
        ->wait(1)
        ->assertSee('Natural rewrite:')
        ->assertSee('I need a clearer, more natural follow-up.')
        ->assertSee('I need a follow-up retry.')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

it('stacks the editor above the conversation on a narrow viewport', function (): void {
    $page = visit('/')->resize(600, 800);

    $page->assertScript('getComputedStyle(document.querySelector(\'main\')).gridTemplateColumns', '600px')
        ->assertScript('document.querySelector(\'main > section\').getBoundingClientRect().top < document.querySelector(\'main > section:nth-child(2)\').getBoundingClientRect().top', true)
        ->assertNoJavaScriptErrors();
});

it('keeps a distant reader in place and follows a reader near the conversation bottom', function (): void {
    $page = visit('/');

    foreach (range(1, 4) as $number) {
        submitBrowserCorrection($page, "Sentence {$number} is correct.");
        $page->wait(1);
    }

    $page->script("const container = document.querySelector('[data-conversation-scroll]'); container.scrollTop = 0; container.dispatchEvent(new Event('scroll'));");
    submitBrowserCorrection($page, 'Sentence five is correct.');

    $page->wait(1)
        ->assertScript("document.querySelector('[data-conversation-scroll]').scrollTop", 0);

    $page->script("const container = document.querySelector('[data-conversation-scroll]'); container.scrollTop = container.scrollHeight; container.dispatchEvent(new Event('scroll'));");
    submitBrowserCorrection($page, 'Sentence six is correct.');

    $page->wait(1)
        ->assertScript("(() => { const container = document.querySelector('[data-conversation-scroll]'); return container.scrollHeight - container.scrollTop - container.clientHeight < 2; })()", true)
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});
