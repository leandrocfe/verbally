<?php

namespace App\Ai\Agents;

use App\Ai\Prompts\CorrectionPrompt;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

final class CorrectionTextAgent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return CorrectionPrompt::instructions();
    }
}
