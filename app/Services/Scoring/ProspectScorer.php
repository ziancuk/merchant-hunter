<?php

declare(strict_types=1);

namespace App\Services\Scoring;

use App\DTO\ScoreInput;
use App\DTO\ScoreResult;

interface ProspectScorer
{
    public function score(ScoreInput $input): ScoreResult;
}
