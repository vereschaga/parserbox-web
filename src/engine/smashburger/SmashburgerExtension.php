<?php

namespace AwardWallet\Engine\smashburger;

use AwardWallet\Engine\california\CaliforniaExtension;

class SmashburgerExtension extends CaliforniaExtension
{
    /*
    * like as papaginos, burgerville, gordonb, genghisgrill, california, carinos, cosi, oberweis, restaurants, tortilla
    *
    * like as huhot, canes, whichwich, boloco
    *
    * like as freebirds, maxermas  // refs #16823
    */

    public $loginURL = "https://smashburger.myguestaccount.com/guest/accountlogin";
    public $balanceURL = "https://smashburger.myguestaccount.com/guest/account-balance";
    public $code = "smashburger";
}
