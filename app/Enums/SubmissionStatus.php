<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Judged = 'judged';
    case Finalist = 'finalist';
    case Rejected = 'rejected';
}
