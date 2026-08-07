<?php

namespace App\Enums;

enum SchoolLeadParentMatchConfirmation: string
{
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
