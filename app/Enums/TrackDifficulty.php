<?php

namespace App\Enums;

enum TrackDifficulty: string
{
    case Beginner = 'beginner';
    case Intermediate = 'intermediate';
    case IntermediateAdvanced = 'intermediate_advanced';
    case Advanced = 'advanced';
}
