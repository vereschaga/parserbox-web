<?php

require_once __DIR__ . '/../pieology/functions.php';

class TAccountCheckerTacojohns extends TAccountCheckerPieologyPunchhDotCom
{
    public $code = "tacojohns";
    public $reCaptcha = true;
    public $seleniumAuth = true;
}
