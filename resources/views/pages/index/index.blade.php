
@php
    $diff = [
        ['type' => 'removed', 'original' => 'I have went', 'replacement' => ''],
        ['type' => 'added', 'original' => '', 'replacement' => 'I went'],
        ['type' => 'unchanged', 'original' => ' to the store yesterday, but ', 'replacement' => ' to the store yesterday, but '],
        ['type' => 'removed', 'original' => 'they was', 'replacement' => ''],
        ['type' => 'added', 'original' => '', 'replacement' => 'it was'],
        ['type' => 'unchanged', 'original' => ' closed, so ', 'replacement' => ' closed, so '],
        ['type' => 'removed', 'original' => 'me and my friend decides to comes', 'replacement' => ''],
        ['type' => 'added', 'original' => '', 'replacement' => 'my friend and I decided to come'],
        ['type' => 'unchanged', 'original' => ' back ', 'replacement' => ' back '],
        ['type' => 'removed', 'original' => 'tomorrow', 'replacement' => ''],
        ['type' => 'added', 'original' => '', 'replacement' => 'the next day'],
        ['type' => 'unchanged', 'original' => '.', 'replacement' => '.'],
    ];
    $explanations = [
        ['tag' => 'Past tense', 'text' => 'With yesterday, use the simple past went — not the present perfect “have went”.'],
        ['tag' => 'Agreement', 'text' => '“The store” is singular, so use it was. Keep every verb in the past: decided to come.'],
        ['tag' => 'Word order', 'text' => 'Put yourself last and use the subject form: my friend and I.'],
    ];
@endphp

<div class="flex h-screen min-h-[720px] w-full flex-col overflow-hidden bg-[#f6f7f4]">
    <x-app-header mark="V" brand="Verbally" tagline="English corrections &amp; coaching" session-label="Session · 4 corrections" clear-label="Clear session" />
    <main class="grid min-h-0 flex-1 grid-cols-1 lg:grid-cols-[440px_1fr]">
        <x-editor-panel title="Write in English" description="Paste or type a sentence. Verbally corrects it and explains every change." placeholder="e.g. Yesterday I go to the park and I have seen many peoples running…" value="I have went to the store yesterday but they was closed, so me and my friend decides to comes back tomorrow." count-label="112 / 2000" shortcut-label="⏎ to send · ⇧⏎ new line">
            <x-slot:action><x-primary-button label="Correct my text" /></x-slot:action>
        </x-editor-panel>
        <x-conversation-panel title="Conversation" date="Today">
            <x-user-bubble message="My english are improving but I still make mistake with the verbs sometime." />
            <x-correction-card mark="V" label="Corrected" :segments="$diff" :explanations="$explanations" rewrite-label="Rewrite naturally" examples-label="More examples" />
        </x-conversation-panel>
    </main>
</div>
