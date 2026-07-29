<div class="verbally-shell">
    @island(name: 'header', always: true)
        <header class="verbally-nav" aria-label="Primary navigation">
            <a href="{{ url('/') }}" class="verbally-brand" aria-label="Verbally home">
                <span class="verbally-logo-wrap"><img src="{{ asset('images/verbally-logo-lockup.png') }}" alt="Verbally, English corrections and coaching" width="207" height="178"></span>
            </a>
            <div class="verbally-nav-meta">
                <span class="verbally-session">Session · {{ $this->completedCorrections() }} corrections</span>
                <button type="button" x-on:click="$wire.clearSession()" @disabled($processing) class="verbally-clear" aria-label="Clear correction session">
                    Clear session
                </button>
            </div>
        </header>
    @endisland

    <main class="verbally-main">
        @island(name: 'editor', always: true)
            <section class="verbally-editor" aria-labelledby="editor-title">
                <div class="verbally-editor-intro">
                    <p class="verbally-eyebrow">English practice, made personal</p>
                    <h1 id="editor-title">Make your English<br><em>sound like you.</em></h1>
                    <p class="verbally-lede">Write freely. Get thoughtful corrections, clear explanations, and a better way to say what you mean.</p>
                </div>

                <form wire:submit="submitText" class="verbally-compose">
                    <label for="verbally-text" class="verbally-compose-label">Write in English</label>
                    <textarea id="verbally-text" aria-label="Write in English" aria-describedby="verbally-hint verbally-count" placeholder="Try: Yesterday I go to the park..." wire:model="text" wire:loading.attr="disabled" @disabled($processing || $this->completedCorrections() >= 20)></textarea>
                    <div class="verbally-compose-footer">
                        <span id="verbally-count" class="verbally-count">{{ mb_strlen($text) }} / 2000</span>
                        <span id="verbally-hint" class="verbally-hint">Enter to correct · Shift + Enter for a new line</span>
                    </div>
                </form>

                @error('text')
                    <p class="verbally-form-error" role="alert">{{ $message }}</p>
                @enderror

                <button type="button" x-on:click="$wire.submitText()" wire:loading.attr="disabled" @disabled($processing || $this->completedCorrections() >= 20) class="verbally-submit">
                    <span wire:loading.remove>Correct my text</span>
                    <span wire:loading>Correcting…</span>
                </button>

                <div class="verbally-editor-note"><span>Corrections are for learning, not perfection.</span></div>
            </section>
        @endisland

        @island(name: 'conversation', always: true)
            <section class="verbally-conversation" aria-labelledby="conversation-title">
                <div class="verbally-conversation-head">
                    <div><p class="verbally-eyebrow">Your practice space</p><h2 id="conversation-title">Conversation</h2></div>
                    <span class="verbally-date">Today</span>
                </div>
                <div data-conversation-scroll class="verbally-conversation-scroll" aria-live="polite">
                    @forelse ($attempts as $attempt)
                        <div class="verbally-attempt">
                            <div class="verbally-user-label">You wrote</div>
                            <x-user-bubble :message="$attempt['text']" />
                            <div class="verbally-correction">
                                <livewire:correction-attempt :attempt-id="$attempt['id']" :submission="$attempt['text']" :session-processing="$processing" :wire:key="'attempt-'.$attempt['id']" />
                            </div>
                        </div>
                    @empty
                        <div class="verbally-empty" role="status">
                            <p>Your corrections will appear here.</p>
                            <span>Start with a sentence on the left.</span>
                        </div>
                    @endforelse
                </div>
            </section>
        @endisland
    </main>
</div>
