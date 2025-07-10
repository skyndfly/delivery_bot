<?php

namespace enums;

enum StateEnum: string
{
    case FIRM_SELECT = 'firm_select';
    case FIRM_SELECTED = 'firm_selected';
    case AWAITING_PHOTO = 'awaiting_photo';
}
