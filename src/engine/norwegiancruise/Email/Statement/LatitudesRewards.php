<?php

namespace AwardWallet\Engine\norwegiancruise\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class LatitudesRewards extends \TAccountChecker
{
    public $mailFiles = "norwegiancruise/statements/it-65590204.eml, norwegiancruise/statements/it-65959765.eml";

    private $detectors = [
        'en' => ['We can only credit your Latitudes account'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@email.ncl.com') !== false;
    }

//    public function detectEmailByHeaders(array $headers)
//    {
//        return stripos($headers['subject'], 'Happy Birthday from Norwegian Cruise Line') !== false;
//    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".ncl.com/") or contains(@href,"email.ncl.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(.,"@ncl.com") or contains(.,"@NCL.COM")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        if (preg_match("/(?:^|:\s*)([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]), NEW! Your/iu", $parser->getSubject(), $m)) {
            $st->addProperty('Name', $m[1]);
        }

        $number = $this->http->FindSingleNode("//text()[{$this->starts('LATITUDES REWARDS NUMBER')}]", null, true, "/{$this->opt('LATITUDES REWARDS NUMBER')}[:\s]+([A-Z\d]{5,})$/i");

        if ($number) {
            $st->setNumber($number);
        }

        $tier = $this->http->FindSingleNode("//text()[{$this->starts('CURRENT LATITUDES REWARDS TIER')}]", null, true, "/{$this->opt('CURRENT LATITUDES REWARDS TIER')}[:\s]+([\w]+)$/i");

        if ($tier) {
            $st->addProperty('Tier', $tier);
        }

        if (!$number && !$tier && $this->detectBody()) {
            $st->setMembership(true);
        }

        if ($number || $tier) {
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

                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0
//                    || $this->http->XPath->query("//img[contains(@alt,'Happy Birthday') or contains(@src,'/HappyBirthday_main.')]")->length > 0
                ) {
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

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
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
