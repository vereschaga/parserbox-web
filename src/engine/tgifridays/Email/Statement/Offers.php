<?php

namespace AwardWallet\Engine\tgifridays\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Offers extends \TAccountChecker
{
    public $mailFiles = "tgifridays/statements/it-79307817.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@mail.fridays.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'rewards@mail.fridays.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@title,"Fridays Rewards Footer") or contains(@title,"Find Your Fridays Footer")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"rewards@mail.fridays.com")]')->length === 0
        ) {
            return false;
        }

        return $this->isMembership();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $name = $login = null;

        $name = $this->http->FindSingleNode("//a[normalize-space()='JOIN REWARDS']/ancestor::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][last()][ descendant-or-self::*[contains(@style,'#e8002e')] ]", null, true, "/^{$patterns['travellerName']}$/u");

        if ($name) {
            $st->addProperty('Name', $name);
        }

        $login = $this->http->FindSingleNode("//text()[normalize-space()='This email was sent to']/following::text()[normalize-space()][1]", null, true, "/^\S+@\S+$/");

        if ($login) {
            $st->setLogin($login);
        }

        if ($name || $login) {
            $st->setNoBalance(true);
        } elseif (stripos($parser->getCleanFrom(), 'rewards@mail.fridays.com') === 0) {
            $st->setMembership(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function isMembership(): bool
    {
        return $this->http->XPath->query('//*[contains(normalize-space(),"by rewards@mail.fridays.com because you signed up for the Fridays Rewards® program through Fridays")]')->length > 0;
    }
}
