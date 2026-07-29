<div>
    @island(name: 'attempt', always: true)
        <div @if ($pending) x-init="$nextTick(() => $wire.completeCorrection().catch(() => $wire.reportStaleOperation()))" @endif>
            <x-correction-card
                mark="V"
                label="Corrected"
                :segments="$segments"
                :explanations="$explanations"
                :corrected="$corrected"
                :error="$error"
                :off-topic="$offTopic"
                rewrite-label="Rewrite naturally"
                examples-label="More examples"
                :attempt-id="$attemptId"
                :disabled="$sessionProcessing"
                :pending="$pending"
                :follow-ups="$followUps"
                :follow-up-pending="$followUpPending"
                :follow-up-kind="$followUpKind"
                :follow-up-error="$followUpError"
                :retry-action="'$wire.$parent.retryAttempt('.$attemptId.', \''.($errorStage ?? 'stream').'\')'"
                :rewrite-action="'$wire.$parent.startFollowUp('.$attemptId.', \'rewrite\')'"
                :examples-action="'$wire.$parent.startFollowUp('.$attemptId.', \'example\')'"
            />
        </div>
    @endisland
</div>
