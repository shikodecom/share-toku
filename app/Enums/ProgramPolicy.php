<?php

namespace App\Enums;

enum ProgramPolicy: string
{
    case Approved = 'approved';
    case NeedsReview = 'needs_review';
    case Restricted = 'restricted';
    case Prohibited = 'prohibited';
    case Suspended = 'suspended';
}
