<?php

class TAccountCheckerTestphantom extends TAccountChecker
{
    public static function GetCheckStrategy($fields)
    {
        return CommonCheckAccountFactory::STRATEGY_CHECK_PHANTOM;
    }
}
