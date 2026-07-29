<?php

namespace App\Ai\Agents;

use App\Ai\Prompts\CorrectionPrompt;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

final class CorrectionDetailsAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return CorrectionPrompt::detailsInstructions();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'corrected' => $schema->string()->required(),
            'diff' => $schema->array()->items($schema->object([
                'type' => $schema->string()->required(),
                'original' => $schema->string(),
                'replacement' => $schema->string(),
            ]))->required(),
            'explanations' => $schema->array()->items($schema->object([
                'tag' => $schema->string()->required(),
                'text' => $schema->string()->required(),
            ]))->required(),
            'is_off_topic' => $schema->boolean()->required(),
        ];
    }
}
