<?php

namespace AwardWallet\Engine\oberweis;

use AwardWallet\Engine\california\CaliforniaExtension;

class OberweisExtension extends CaliforniaExtension
{
    /*
    * like as papaginos, burgerville, gordonb, genghisgrill, california, carinos, cosi, oberweis, restaurants, tortilla
    *
    * like as huhot, canes, whichwich, boloco
    *
    * like as freebirds, maxermas  // refs #16823
    */

    public $loginURL = "https://oberweis.myguestaccount.com/guest/accountlogin";
    public $balanceURL = "https://oberweis.myguestaccount.com/guest/account-balance";
    public $code = "oberweis";
}
