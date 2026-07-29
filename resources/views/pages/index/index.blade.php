<div class="flex h-screen min-h-[720px] w-full flex-col overflow-hidden bg-[#f6f7f4]">
    <x-app-header mark="V" brand="Verbally" tagline="English corrections &amp; coaching" session-label="Session · {{ count($attempts) }} corrections" clear-label="Clear session" clear-action="clearSession" :disabled="$processing" />
    <main class="grid min-h-0 flex-1 grid-cols-1 lg:grid-cols-[440px_1fr]">
        <x-editor-panel title="Write in English" description="Paste or type a sentence. Verbally corrects it and explains every change." placeholder="e.g. Yesterday I go to the park and I have seen many peoples running…" :value="$text" count-label="{{ mb_strlen($text) }} / 2000" shortcut-label="⏎ to send · ⇧⏎ new line" model="text" :disabled="$processing || count($attempts) >= 20">
            <x-slot:action><x-primary-button label="Correct my text" wire:click="submitText" :disabled="$processing || count($attempts) >= 20" /></x-slot:action>
        </x-editor-panel>
        <x-conversation-panel title="Conversation" date="Today">
            @forelse ($attempts as $attempt)
                <x-user-bubble :message="$attempt['text']" />
                <div wire:key="attempt-{{ $attempt['id'] }}" @if ($attempt['pending']) wire:init="completeCorrection({{ $attempt['id'] }})" @endif>
                    <x-correction-card mark="V" label="Corrected" :segments="$attempt['segments']" :explanations="$attempt['explanations']" rewrite-label="Rewrite naturally" examples-label="More examples" :attempt-id="$attempt['id']" :disabled="$processing" :pending="$attempt['pending']" :follow-ups="$attempt['followUps']" />
                </div>
            @empty
                <div class="flex flex-1 items-center justify-center text-center text-[14px] text-[#8a9184]">Your corrections will appear here.</div>
            @endforelse
        </x-conversation-panel>
    </main>
</div>
