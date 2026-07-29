<?php

namespace App\Ai\Agents;

use App\Ai\Prompts\FollowUpPrompt;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

final class NaturalRewriteAgent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return FollowUpPrompt::naturalRewriteInstructions();
    }
}
