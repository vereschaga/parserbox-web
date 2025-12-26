<?php

namespace AwardWallet\Engine\lufthansa\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ZJunk extends \TAccountChecker
{
    public $mailFiles = "lufthansa/it-446430840-junk.eml";

    private $subjects = [
        'en' => ['Check in your carry-on baggage free of charge'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]lufthansa\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
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
            && $this->http->XPath->query('//a[contains(@href,".lufthansa.com/") or contains(@href,"www.lufthansa.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Yours sincerely, Your Lufthansa Team")]')->length === 0
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
        $email->setType('ZJunk');

        $xpathAirportCode = 'translate(normalize-space(),"ABCDEFGHIJKLMNOPQRSTUVWXYZ","∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆")="∆∆∆"';
        $xpathTime = 'contains(translate(translate(.," ",""),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆")';

        $root = $roots->item(0);

        $mainText = $this->http->FindSingleNode("following::text()[{$this->starts(['Your flight', 'Your Lufthansa flight'])} and contains(normalize-space(),'is almost fully booked')]", $root, true, "/^{$this->opt(['Your flight', 'Your Lufthansa flight'])}\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+\s+is almost fully booked.*$/");

        if (stripos($mainText, 'storage space in the cabin is limited, at the gate we may have to load carry-on baggage') !== false
            && $this->http->XPath->query("//text()[{$xpathAirportCode}]")->length < 2
            && $this->http->XPath->query("//*[{$xpathTime}]")->length === 0
        ) {
            $email->setIsJunk(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query('//h1[normalize-space()="Check in your carry-on baggage free of charge"]');
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
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
