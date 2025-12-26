<?php

require_once __DIR__ . '/../pieology/functions.php';

class TAccountCheckerTucanos extends TAccountCheckerPieologyPunchhDotCom
{
    /*
     * like as papaginos, burgerville, gordonb, genghisgrill, california, carinos, cosi, oberweis, restaurants
     *
     * like as huhot, canes, whichwich, boloco
     *
     * like as freebirds, maxermas  // refs #16823
     */

    public $code = "tucanos";
    public $reCaptcha = true;
}
