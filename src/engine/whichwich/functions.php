<?php

require_once __DIR__ . '/../pieology/functions.php';

class TAccountCheckerWhichwich extends TAccountCheckerPieologyPunchhDotCom
{
    public $code = 'whichwich';
    public $reCaptcha = true;
    public $seleniumAuth = true;
}
