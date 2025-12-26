<?php

namespace AwardWallet\Engine\navy\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class YourAccount extends \TAccountChecker
{
    public $mailFiles = "navy/statements/it-66513830.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@messages.navyfederal.org') !== false
            || stripos($from, 'Navy Federal Credit Union') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Your Navy Federal Account') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,"//nfcu.link/")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"This email is being sent from: Navy Federal Credit Union")]')->length === 0
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

        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("descendant::text()[starts-with(normalize-space(),'Email for')]", $root, true, "/^Email for\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])$/iu");
        $st->addProperty('Name', $name);

        $number = $this->http->FindSingleNode("descendant::text()[starts-with(normalize-space(),'Access')]", $root, true, "/^Access\s+([-X\d ]{5,})$/i");
        $number = str_replace(' ', '', $number);

        if (preg_match("/^[X]{2,}([-\d]{2,})$/i", $number, $m)) {
            // XXXXXXXXXXXX13
            $st->setNumber($m[1])->masked()
                ->setLogin($m[1])->masked();
        } elseif (preg_match("/^[-\d]{5,}$/", $number)) {
            // 13729330186413
            $st->setNumber($number)
                ->setLogin($number);
        }

        if ($name || $number) {
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
        return $this->http->XPath->query("//tr[ not(.//tr) and descendant::text()[starts-with(normalize-space(),'Email for')] and descendant::text()[starts-with(normalize-space(),'Access')] ]");
    }
}
