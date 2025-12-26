<?php

namespace AwardWallet\Engine\burgerville;

use AwardWallet\Engine\california\CaliforniaExtension;

class BurgervilleExtension extends CaliforniaExtension
{
    /*
    * like as papaginos, burgerville, gordonb, genghisgrill, california, carinos, cosi, oberweis, restaurants, tortilla
    *
    * like as huhot, canes, whichwich, boloco
    *
    * like as freebirds, maxermas  // refs #16823
    */

    public $loginURL = "https://burgerville.myguestaccount.com/guest/accountlogin";
    public $balanceURL = "https://burgerville.myguestaccount.com/guest/account-balance";
    public $code = "burgerville";
}
