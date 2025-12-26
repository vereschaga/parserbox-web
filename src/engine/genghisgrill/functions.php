<?php

require_once __DIR__ . '/../california/functions.php';

class TAccountCheckerGenghisgrill extends TAccountCheckerCalifornia
{
    /*
     * like as papaginos, burgerville, gordonb, genghisgrill, california, carinos, cosi, oberweis, restaurants, tortilla
     *
     * like as huhot, canes, whichwich, boloco
     *
     * like as freebirds, maxermas  // refs #16823
     */

    public $loginURL = "https://genghisgrill.myguestaccount.com/guest/accountlogin";
    public $balanceURL = "https://genghisgrill.myguestaccount.com/guest/account-balance";
    public $code = "genghisgrill";
}
