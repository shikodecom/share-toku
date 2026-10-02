<?php

namespace App\Enums;

enum ReferralOfferStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Published = 'published';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
    case Expired = 'expired';
}
