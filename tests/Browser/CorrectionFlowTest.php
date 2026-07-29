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
        ->assertScript("(() => { const avatar = document.querySelector('.verbally-correction-avatar img'); return avatar?.getAttribute('src')?.includes('/images/verbally-logo-icon.png') && avatar?.getAttribute('alt') === 'Verbally'; })()", true)
        ->assertScript("(() => { const bubble = document.querySelector('.verbally-attempt > .flex.justify-end'); const correction = document.querySelector('.verbally-correction'); return correction.getBoundingClientRect().top - bubble.getBoundingClientRect().bottom >= 20; })()", true)
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

    $page->assertSee('Session · 0 corrections')
        ->assertScript('document.querySelector(\'textarea[aria-label="Write in English"]\').disabled', false);

    clickBrowserButton($page, 'Try again');

    $page
        ->wait(1)
        ->assertSee('I need a correction retry.')
        ->assertSee('The sentence is clear and correct.')
        ->assertNoJavaScriptErrors();
});

it('keeps off-topic attempts outside the counter and allows another submission', function (): void {
    $page = visit('/');

    submitBrowserCorrection($page, 'What is the difference between affect and effect?');

    $page->wait(1)
        ->assertSee('Off topic')
        ->assertSee('Session · 0 corrections')
        ->assertScript('document.querySelector(\'textarea[aria-label="Write in English"]\').disabled', false);

    submitBrowserCorrection($page, 'A sentence for correction.');

    $page->wait(1)
        ->assertSee('Corrected')
        ->assertSee('Session · 1 corrections')
        ->assertNoJavaScriptErrors();
});

it('blocks a twenty-first submission after twenty completed corrections', function (): void {
    $page = visit('/');

    foreach (range(1, 20) as $number) {
        submitBrowserCorrection($page, "Completed sentence {$number}.");
        $page->wait(1);
    }

    $page->assertSee('Session · 20 corrections')
        ->assertScript('document.querySelector(\'textarea[aria-label="Write in English"]\').disabled', true)
        ->assertScript('document.querySelectorAll(\'article\').length', 20);

    $page->script("document.querySelector('textarea[aria-label=\"Write in English\"]').value = 'The blocked twenty-first sentence.';");
    clickBrowserButton($page, 'Correct my text');
    $page->assertScript('document.querySelectorAll(\'article\').length', 20)
        ->assertSee('Session · 20 corrections');
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

it('keeps the responsive layout in one column and aligns the conversation with the clear action', function (): void {
    $page = visit('/');

    foreach ([800, 600, 500] as $width) {
        $page->resize($width, 800)
            ->assertScript('getComputedStyle(document.querySelector(\'main\')).gridTemplateColumns.split(\' \').length', 1)
            ->assertScript('document.querySelector(\'main > section\').getBoundingClientRect().top < document.querySelector(\'main > section:nth-child(2)\').getBoundingClientRect().top', true)
            ->assertScript("(() => { const clearButton = [...document.querySelectorAll('button')].find((button) => button.textContent.includes('Clear session')); const conversation = document.querySelector('.verbally-conversation'); return Math.abs(clearButton.getBoundingClientRect().right - conversation.getBoundingClientRect().right) <= 1; })()", true);
    }

    $page->assertNoJavaScriptErrors();
});

it('keeps a distant reader in place and follows a reader near the conversation bottom', function (): void {
    $page = visit('/');

    foreach (range(1, 4) as $number) {
        submitBrowserCorrection($page, "Sentence {$number} is correct.");
        $page->wait(1);
    }

    $page->assertScript("(() => { const scroll = document.querySelector('[data-conversation-scroll]'); return document.querySelectorAll('.verbally-attempt').length === 4 && parseFloat(getComputedStyle(scroll).rowGap) >= 38; })()", true);

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
