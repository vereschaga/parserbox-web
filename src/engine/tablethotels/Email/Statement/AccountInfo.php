<?php

namespace AwardWallet\Engine\tablethotels\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class AccountInfo extends \TAccountChecker
{
    public $mailFiles = "tablethotels/statements/it-167860040.eml";

    private $subjects = [
        'en' => ['Your Account Has Been Updated'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@tablethotels.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".tablethotels.com/") or contains(@href,"cb.tablethotels.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Tablet Hotels LLC. New York City")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            return $email;
        }
        $root = $roots->item(0);

        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $st = $email->add()->statement();

        $name = $login = null;

        $firstName = $this->http->FindSingleNode(".", $root, true, "/^First Name\s*[:]+\s*({$patterns['travellerName']})$/u");
        $lastName = $this->http->FindSingleNode("following::p[normalize-space()][1][starts-with(normalize-space(),'Last Name')]", $root, true, "/^Last Name\s*[:]+\s*({$patterns['travellerName']})$/u");

        if ($firstName && $lastName) {
            $name = $firstName . ' ' . $lastName;
        }

        $login = $this->http->FindSingleNode("following::p[normalize-space()][1][starts-with(normalize-space(),'Last Name')]/following::p[normalize-space()][1][starts-with(normalize-space(),'Email')]", $root, true, "/^Email\s*[:]+\s*(\S+@\S+)$/");

        $st->addProperty('Name', $name)->setLogin($login);

        if ($name || $login) {
            $st->setNoBalance(true);
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//p[contains(normalize-space(),'your account information')]/following::p[normalize-space()][1][starts-with(normalize-space(),'First Name')]");
    }
}
