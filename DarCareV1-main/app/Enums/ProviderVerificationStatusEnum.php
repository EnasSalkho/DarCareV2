<?php

namespace App\Enums;

enum ProviderVerificationStatusEnum: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}