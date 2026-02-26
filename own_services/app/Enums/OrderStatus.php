<?php

namespace App\Enums;

enum OrderStatus: string
{
    case RECEIVED = 'RECEIVED';
    case SUBMITTED = 'SUBMITTED';
    case PENDING = 'PENDING';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
}
