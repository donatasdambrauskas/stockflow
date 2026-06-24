<?php

namespace App\Entity;

enum OrderStatus: string
{
    case Pending = 'pending';
    case PartiallyReserved = 'partially_reserved';
    case Reserved = 'reserved';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';
}
