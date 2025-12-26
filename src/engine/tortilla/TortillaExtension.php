<?php

namespace AwardWallet\Engine\tortilla;

use AwardWallet\Engine\california\CaliforniaExtension;

class TortillaExtension extends CaliforniaExtension
{
    /*
    * like as papaginos, burgerville, gordonb, genghisgrill, california, carinos, cosi, oberweis, restaurants, tortilla
    *
    * like as huhot, canes, whichwich, boloco
    *
    * like as freebirds, maxermas  // refs #16823
    */

    public $loginURL = "https://californiatortilla.myguestaccount.com/guest/accountlogin";
    public $balanceURL = "https://californiatortilla.myguestaccount.com/guest/account-balance";
    public $code = "tortilla";
}
