<?php

namespace App\Enums;

enum ScoreAggregation: string
{
    case Average = 'average';
    case Median = 'median';
}
