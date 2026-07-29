<?php

namespace App\Ai\Prompts;

final class CorrectionPrompt
{
    public static function instructions(): string
    {
        return 'You correct English text only. Return only the corrected text, with no markdown, HTML, labels, or explanation. If the submission is not an English text submission, return a short refusal in English asking the user to write an English sentence for correction.';
    }

    public static function detailsInstructions(): string
    {
        return 'Return correction details for the supplied English text. The corrected value must exactly match the previously streamed corrected text. Set is_off_topic true only for non-English text or a question without a text submission. Do not include HTML.';
    }
}
