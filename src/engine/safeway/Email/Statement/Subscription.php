<?php

namespace AwardWallet\Engine\safeway\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Subscription extends \TAccountChecker
{
    public $mailFiles = "safeway/statements/it-65633568.eml, safeway/statements/it-64658487.eml";

    private $detectors = [
        'en' => [
            "You've received this email because our records indicate you supplied an email address when you created an account for one of our programs",
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@safeway.com') !== false || stripos($from, '@email.safeway.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".safeway.com/") or contains(@href,"email.safeway.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(.,"www.safeway.com") or contains(.,"Safeway.com") or contains(.,"@email.safeway.com")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $welcomeTexts = ['Hi', "You're all signed up"];

        $name = $this->http->FindSingleNode("//text()[{$this->starts($welcomeTexts)}]", null, true,
            "/{$this->opt($welcomeTexts)}[,\s]+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|\.?$)/u");

        if ($name) {
            $st->addProperty('Name', $name);
        }

        $isMembership = $this->detectBody();

        if ($isMembership) {
            $st->setMembership(true);
        }

        if ($name) {
            $st->setNoBalance(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
