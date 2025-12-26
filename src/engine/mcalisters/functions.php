<?php

require_once __DIR__ . '/../pieology/functions.php';
class TAccountCheckerMcalisters extends TAccountCheckerPieologyPunchhDotCom
{
    /*
     * like as papaginos, burgerville, gordonb, genghisgrill, california, carinos, cosi, oberweis, restaurants
     *
     * like as huhot, canes, whichwich, boloco
     *
     * like as freebirds, maxermas  // refs #16823
     */

    public $code = "mcalisters";
    public $reCaptcha = true;
    public $seleniumAuth = true;
}
