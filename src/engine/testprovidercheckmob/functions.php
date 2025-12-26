<?php

include_once __DIR__ . '/../testprovider/functions.php';

class TAccountCheckerTestprovidercheckmob extends TAccountCheckerTestprovider
{
    public static function GetAccountChecker($accountInfo)
    {
        return new self();
    }
}
