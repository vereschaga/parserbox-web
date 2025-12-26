<?php

namespace AwardWallet\Engine\junky\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class OrderConfirmation extends \TAccountChecker
{
    public $mailFiles = "junky/statements/it-110900782.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@sd.activejunky.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'ActiveJunky.com Order Confirmation') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".activejunky.com/") or contains(@href,"sd.activejunky.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Sent with ♥ from Active Junky") or contains(normalize-space(),"Active Junky, All rights reserved") or contains(.,"@activejunky.com")]')->length === 0
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

        $name = $this->http->FindSingleNode("descendant::text()[normalize-space()='Name:']/following::text()[normalize-space()][1]", $root, true, "/^{$patterns['travellerName']}$/u");

        if ($name) {
            $st->addProperty('Name', $name);
        }

        $login = $this->http->FindSingleNode("descendant::text()[normalize-space()='Email:']/following::text()[normalize-space()][1]", $root, true, "/^\S+@\S+$/");
        $st->setLogin($login);

        if ($name || $login) {
            $st->setNoBalance(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//text()[normalize-space()='Cash Back Earned:']/ancestor::*[ *[normalize-space()][2] ][1]");
    }
}
