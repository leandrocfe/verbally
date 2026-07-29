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
        return 'Return correction details for the supplied English text. The corrected value must exactly match the previously streamed corrected text. '
            .'Set is_off_topic true only for non-English text or a question without a text submission. Do not include HTML. '
            .'The diff field is an ordered list of segments describing exactly how the original text becomes the corrected text. '
            .'Each segment has a type field, which must be literally one of these three strings: "unchanged", "removed", or "added". No other type value is allowed. '
            .'A "unchanged" segment has original and replacement, and they must be identical. '
            .'A "removed" segment has only original (no replacement field): a word or phrase deleted from the original text. '
            .'A "added" segment has only replacement (no original field): a word or phrase inserted into the corrected text that was not in the original. '
            .'A word substitution is expressed as a "removed" segment immediately followed by an "added" segment, in that order (never the reverse). '
            .'Concatenating the replacement value of every segment that is not "removed", in list order, must reconstruct the corrected text exactly. '
            .'Example: original "I have went", corrected "I went" -> diff [{"type":"unchanged","original":"I ","replacement":"I "},{"type":"removed","original":"have "},{"type":"unchanged","original":"went","replacement":"went"}]. '
            .'When is_off_topic is true, diff and explanations must both be the empty array [], with no segments and no explanations at all.';
    }
}
