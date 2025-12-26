<?php

namespace AwardWallet\Engine\milleniumnclc\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourStay extends \TAccountChecker
{
    public $mailFiles = "milleniumnclc/it-232248275.eml, milleniumnclc/it-226327835.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [],
    ];

    private $subjects = [
        'en' => ['Complete pre-arrival check-in', 'Mobile Pre-Registration Check-In'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@millenniumhotels.com') !== false;
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
            && $this->http->XPath->query('//a[contains(@href,".millenniumhotels.com/") or contains(@href,"www.millenniumhotels.com") or contains(@href,"checkin.millenniumhotels.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"www.millenniumhotels.com") or contains(.,"checkin.millenniumhotels.com")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->lang = 'en';
        $email->setType('YourStay' . ucfirst($this->lang));

        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            $this->logger->debug('Root node not found!');

            return $email;
        }
        $root = $roots->item(0);

        $mainContent = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $root));
        $this->logger->info($mainContent);

        $h = $email->add()->hotel();

        $hotelName = $this->http->FindSingleNode("descendant::node()[not(.//tr) and starts-with(normalize-space(),'Your stay at')][1]", null, true, "/^Your stay at\s+(.{3,99}?)\s+is coming up soon/");

        if (preg_match("/^[ ]*Name:[: ]*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])[ ]*$/imu", $mainContent, $m)) {
            $h->general()->traveller($m[1], true);
        }

        if (preg_match("/^[ ]*Your Stay:[: ]*(?<date1>.{6,})[ ]+-[ ]+(?<date2>.{6,}?)[ ]*$/im", $mainContent, $m)) {
            $h->booked()->checkIn2($m['date1'])->checkOut2($m['date2']);
        }

        if (preg_match("/^[ ]*(Confirmation):[: ]*([-A-Z\d]{5,})[ ]*$/im", $mainContent, $m)) {
            $h->general()->confirmation($m[2], $m[1]);
        }

        if (!$hotelName) {
            $this->logger->debug('Hotel name not found!');

            return $email;
        }

        $hotelNameVariants = [];
        $hotelNameVariants[] = $hotelName;
        $hotelNameVariants[] = strtoupper($hotelName);
        $hotelNameVariants[] = ucwords($hotelName);

        $contactsText = $this->htmlToText($this->http->FindHTMLByXpath("following::text()[{$this->starts(array_unique($hotelNameVariants))} and contains(.,'|')]/ancestor::tr[1]", null, $root));

        if (preg_match("/^\s*(?<name>{$this->opt($hotelName)})\s*\|\s*(?<address>.{3,99}?)[ ]*(?:\n|$)/i", $contactsText, $m)) {
            $h->hotel()->name($m['name'])->address($m['address']);
        }

        if (preg_match("/^[ ]*([+(\d][-+. \d)(]{5,}[\d)])[ ]*$/m", $contactsText, $m)) {
            $h->hotel()->phone($m[1]);
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//node()[ node()[starts-with(normalize-space(),'Name:')] and node()[starts-with(normalize-space(),'Your Stay:')] and node()[starts-with(normalize-space(),'Confirmation:')] ]");
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

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
