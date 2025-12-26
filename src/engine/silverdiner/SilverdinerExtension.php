<?php

namespace AwardWallet\Engine\silverdiner;

use AwardWallet\Engine\california\CaliforniaExtension;

class SilverdinerExtension extends CaliforniaExtension
{
    /*
    * like as papaginos, burgerville, gordonb, genghisgrill, california, carinos, cosi, oberweis, restaurants, tortilla
    *
    * like as huhot, canes, whichwich, boloco
    *
    * like as freebirds, maxermas  // refs #16823
    */

    public $loginURL = "https://silverdiner.myguestaccount.com/guest/accountlogin";
    public $balanceURL = "https://silverdiner.myguestaccount.com/guest/account-balance";
    public $code = "silverdiner";
}
