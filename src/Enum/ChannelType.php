<?php

namespace App\Enum;

enum ChannelType: string
{
    case Text = 'text';
    case Voice = 'voice';
}
