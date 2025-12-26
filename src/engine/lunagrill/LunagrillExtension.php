<?php

namespace AwardWallet\Engine\lunagrill;

use AwardWallet\Engine\pieology\PieologyPunchhDotComExtension;
use AwardWallet\ExtensionWorker\Tab;

class LunagrillExtension extends PieologyPunchhDotComExtension
{
    /*
     * like as papaginos, burgerville, gordonb, genghisgrill, california, carinos, cosi, oberweis, restaurants
     *
     * like as huhot, canes, whichwich, boloco
     *
     * like as freebirds, maxermas  // refs #16823
     */

    public $code = "lunagrill";

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->gotoUrl('/customers/sign_out.iframe?slug=' . $this->code);
        $tab->evaluate('//a[contains(@href, "sign_up.iframe")]');
    }
}
