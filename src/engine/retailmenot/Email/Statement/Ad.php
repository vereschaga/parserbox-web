<?php

namespace AwardWallet\Engine\retailmenot\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Ad extends \TAccountChecker
{
    public $mailFiles = "retailmenot/statements/it-64504201.eml, retailmenot/statements/it-64606781.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '.retailmenot.com') !== false
            && isset($headers['subject']) && stripos($headers['subject'], '& More!') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->checkBody();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/\bretailmenot\.com$/', $from) > 0;
    }

    public function checkBody(): bool
    {
        return stripos($body = $this->http->Response['body'], 'How can RetailMeNot make savings even easier') !== false
            || stripos($body, 'RetailMeNot') !== false && stripos($body, '"Your Rewards" amount shown above may have changed since the sending of this email') !== false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->checkBody()) {
            $st = $email->add()->statement();
            $st->setLogin($this->http->FindSingleNode('//*[text()[contains(., "This email was sent to")]]', null, true, '/This email was sent to ([^@]+@[\w.]+)\b/'));

            if (($roots = $this->http->XPath->query('//td[normalize-space(.)="Your Rewards:"]'))->length > 0) {
                $st->setBalance($this->http->FindSingleNode('./following-sibling::td', $roots->item(0), true, '/^[$](\d+\.\d+)$/'));
            } else {
                $st->setNoBalance(true);
            }
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }
}
