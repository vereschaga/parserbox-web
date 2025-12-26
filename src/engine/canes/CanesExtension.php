<?php

namespace AwardWallet\Engine\canes;

use AwardWallet\Engine\california\CaliforniaExtension;

class CanesExtension extends CaliforniaExtension
{
    /*
    * like as papaginos, burgerville, gordonb, genghisgrill, california, carinos, cosi, oberweis, restaurants, tortilla
    *
    * like as huhot, canes, whichwich, boloco
    *
    * like as freebirds, maxermas  // refs #16823
    */

    public $loginURL = "https://raisingcanes.myguestaccount.com/guest/accountlogin";
    public $balanceURL = "https://raisingcanes.myguestaccount.com/guest/account-balance";
    public $code = "canes";
}
