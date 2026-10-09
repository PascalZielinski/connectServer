<?php

namespace App\Enum;

enum MemberRole: string
{
    case Member = 'member';
    case Owner = 'owner';
    case Editor = 'editor';
}
