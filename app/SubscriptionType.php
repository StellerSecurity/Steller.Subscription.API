<?php

namespace app;

enum SubscriptionType: int
{

    case PHONE = 0;
    case VPN = 1;
    case ANTIVIRUS = 2;
    case PROTECT = 3;
    case CLOUD = 4;
    case PHOTOVAULT = 5;

    case STELLAROS = 6;

    case SIMCARD = 7;

    case NOTES = 8;

    case VOICECHANGER = 9;

}
