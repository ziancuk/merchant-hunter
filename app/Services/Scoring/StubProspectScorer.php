<?php

declare(strict_types=1);

namespace App\Services\Scoring;

use App\DTO\ScoreInput;
use App\DTO\ScoreResult;

/** Placeholder bound in M2.0; replaced by ScoreCalculator in M2.2. */
final class StubProspectScorer implements ProspectScorer
{
    public function score(ScoreInput $input): ScoreResult
    {
        return new ScoreResult(min(100, $input->categoryWeight), []);
    }
}
