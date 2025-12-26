<?php

namespace AwardWallet\Engine\hotels\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ZJunk extends \TAccountChecker
{
    public $mailFiles = "hotels/it-58020043.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@mail.hotels.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'mail.hotels.com')]")->length === 0) {
            return false;
        }

        return $this->http->XPath->query("//td[not(.//td) and starts-with(normalize-space(),'Secret Prices:')]")->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if ($this->http->XPath->query("//a[contains(@href,'mail.hotels.com')]//img[contains(@src,'mail.hotels.com') and @width='600' and @height='450']")->length === 1
            && $this->http->XPath->query("//td[not(.//td) and starts-with(normalize-space(),'Secret Prices:')]/following-sibling::td[normalize-space()='Access deals']")->length === 1
        ) {
            $email->setIsJunk(true);
        }
        $email->setType('ZJunk');

        return $email;
    }
}
