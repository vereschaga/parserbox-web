<?php

namespace AwardWallet\Engine\regiondo\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourTicket extends \TAccountChecker
{
    public $mailFiles = "regiondo/it-883934154.eml";

    private $subjects = [
        'en' => ['Your ticket has arrived!']
    ];

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Add to Calendar' => ['Add to Calendar'],
            'Meeting Point' => ['Meeting Point'],
            'View on' => ['View on'],
            'Open Ticket' => ['Open Ticket'],
        ]
    ];

    private $http2; // for remote html-content

    private $patterns = [
        'date' => '.{4,}?\b\d{4}\b', // Wednesday, July 12th 2023
        'time' => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.  |  3pm
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]regiondo\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array)$phrases as $phrase) {
                if (is_string($phrase) && array_key_exists('subject', $headers) && stripos($headers['subject'], $phrase) !== false)
                    return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $href = ['.regiondo.com/', 'shop.regiondo.com'];

        if ($this->detectEmailFromProvider($parser->getCleanFrom()) !== true
            && $this->http->XPath->query("//a[{$this->contains($href, '@href')} or {$this->contains($href, '@originalsrc')}]")->length === 0
        ) {
            return false;
        }

        $this->assignLang();

        return $this->findRoots()->length > 0;
    }

    private function findRoots(): \DOMNodeList
    {
        return $this->http->XPath->query("//text()[{$this->eq($this->t('Meeting Point'), "translate(.,':','')")}]");
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        if ( empty($this->lang) ) {
            $this->logger->debug("Can't determine a language!");
        }
        $email->setType('YourTicket' . ucfirst($this->lang));

        $this->http2 = clone $this->http;

        /* Step 1: parsing content */

        $its = [];
        $ticketNodes = $this->findRoots();
        $this->logger->debug('Found event tickets: ' . $ticketNodes->length);

        foreach ($ticketNodes as $root) {
            $it = [
                'eventName' => null,
                'startDate' => null,
                'notes' => [],
                'address' => null,
                'travellers' => [],
            ];

            $xpathDateTime = "preceding::text()[normalize-space()][position()<9][{$this->eq($this->t('Add to Calendar'), "translate(.,':','')")}]/preceding::text()[normalize-space()][1]";

            $eventName = $this->http->FindSingleNode($xpathDateTime . "/ancestor-or-self::*[ preceding-sibling::*[normalize-space()] ][1]/preceding-sibling::*[normalize-space()][last()]", $root);
            $it['eventName'] = $eventName;

            $dateTimeVal = $this->http->FindSingleNode($xpathDateTime, $root);

            if (preg_match("/^(?<date>{$this->patterns['date']})[-,.\s]+(?<time>{$this->patterns['time']})/", $dateTimeVal, $m)) {
                $it['startDate'] = strtotime($m['time'], strtotime($m['date']));
            }

            $addressText = $this->htmlToText( $this->http->FindHTMLByXpath("ancestor-or-self::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()][1]", null, $root) );

            if (preg_match("/^[ ]*(\S.+\S)[ ]*\n.*\n[ ]*{$this->opt($this->t('View on'))}[ ]*:/", $addressText, $m)) {
                $addressNote = $m[1];
            } else {
                $addressNote = null;
            }

            if ($addressNote) {
                $it['notes'][] = $addressNote;
            }

            $queryAddress = $this->http->FindSingleNode("following::text()[normalize-space()][position()<7][{$this->eq($this->t('View on'), "translate(.,':','')")}]/following::text()[normalize-space()][1]/ancestor::a[{$this->contains($this->t('Google Maps'))} and normalize-space(@href)]/@href", $root, false, "/\?q=(.*?)(?:&|$)/i");
            $it['address'] = $queryAddress && strlen($queryAddress) > 2 ? urldecode($queryAddress) : null;

            $this->parseUrl($it, $root);

            foreach ($its as &$currentIt) {
                if (!empty($it['eventName']) && $it['eventName'] === $currentIt['eventName']
                    && !empty($it['startDate']) && $it['startDate'] === $currentIt['startDate']
                    && !empty($it['address']) && $it['address'] === $currentIt['address']
                ) {
                    foreach ($it['travellers'] as $travellerName) {
                        if ($travellerName && !in_array($travellerName, $currentIt['travellers'])) {
                            $currentIt['travellers'][] = $travellerName;
                        }
                    }

                    foreach ($it['notes'] as $note) {
                        $currentIt['notes'][] = $note;
                    }

                    continue 2;
                }
            }

            $its[] = $it;
        }

        /* Step 2: create reservations */

        $confNumber = $this->http->FindSingleNode("//text()[{$this->contains($this->t('your booking number'))}]/following::text()[normalize-space()][1]", null, true, "/^[-A-z\d]{4,}$/");

        foreach ($its as $it) {
            $ev = $email->add()->event();
            $ev->type()->event();
            $ev->general()->confirmation($confNumber);

            if (count($it['travellers']) > 0) {
                $ev->general()->travellers($it['travellers'], true);
            }

            $ev->booked()->start($it['startDate'])->noEnd();

            if (count(array_unique($it['notes'])) === 1) {
                $notes = array_shift($it['notes']);
                $ev->general()->notes($notes);
            }

            $ev->place()->name($it['eventName'])->address($it['address']);
        }

        return $email;
    }

    private function parseUrl(array &$it, \DOMNode $root): void
    {
        $url = $this->http->FindSingleNode("ancestor::*[ descendant::text()[normalize-space()][4] ][1]/following::text()[normalize-space()][1]/ancestor::a[{$this->eq($this->t('Open Ticket'))} and normalize-space(@href)]/@href", $root);

        if (empty($url)) {
            return;
        }

        $this->http2->GetURL($url);

        /* travellers */

        $traveller = $this->http2->FindSingleNode("//*[ *[normalize-space()][1][{$this->eq($this->t('Attendee'), "translate(.,':','')")}] ]/*[normalize-space()][2]", null, true, "/^{$this->patterns['travellerName']}$/u");

        if (!$traveller) {
            $tNameParts = [];

            $firstName = $this->http2->FindSingleNode("//*[ *[normalize-space()][1][{$this->eq($this->t('Attendee'), "translate(.,':','')")}] ]/*[{$this->starts($this->t('FIRST NAME'))}]", null, true, "/^{$this->opt($this->t('FIRST NAME'))}\s*[:]+\s*({$this->patterns['travellerName']})$/iu");

            if ($firstName) {
                $tNameParts[] = $firstName;
            }

            $lastName = $this->http2->FindSingleNode("//*[ *[normalize-space()][1][{$this->eq($this->t('Attendee'), "translate(.,':','')")}] ]/*[{$this->starts($this->t('LAST NAME'))}]", null, true, "/^{$this->opt($this->t('LAST NAME'))}\s*[:]+\s*({$this->patterns['travellerName']})$/iu");

            if ($lastName) {
                $tNameParts[] = $lastName;
            }

            if (count($tNameParts) > 0) {
                $traveller = implode(' ', $tNameParts);
            }
        }

        if (!$traveller) {
            $traveller = $this->http2->FindSingleNode("//*[ *[normalize-space()][1][{$this->eq($this->t('Buyer'), "translate(.,':','')")}] ]/*[normalize-space()][2]", null, true, "/^{$this->patterns['travellerName']}$/u");
        }

        if (!$traveller) {
            $tNameParts = [];

            $firstName = $this->http2->FindSingleNode("//*[ *[normalize-space()][1][{$this->eq($this->t('Buyer'), "translate(.,':','')")}] ]/*[{$this->starts($this->t('FIRST NAME'))}]", null, true, "/^{$this->opt($this->t('FIRST NAME'))}\s*[:]+\s*({$this->patterns['travellerName']})$/iu");

            if ($firstName) {
                $tNameParts[] = $firstName;
            }

            $lastName = $this->http2->FindSingleNode("//*[ *[normalize-space()][1][{$this->eq($this->t('Buyer'), "translate(.,':','')")}] ]/*[{$this->starts($this->t('LAST NAME'))}]", null, true, "/^{$this->opt($this->t('LAST NAME'))}\s*[:]+\s*({$this->patterns['travellerName']})$/iu");

            if ($lastName) {
                $tNameParts[] = $lastName;
            }

            if (count($tNameParts) > 0) {
                $traveller = implode(' ', $tNameParts);
            }
        }

        if ($traveller) {
            $it['travellers'][] = $traveller;
        }

        /* notes */

        $importantInfoRows = $this->http2->FindNodes("//*[ *[normalize-space()][1][{$this->eq($this->t('Important information'), "translate(.,':','')")}] ]/*[normalize-space()][2]/*[normalize-space()][1][self::ul or self::ol]/li[normalize-space()]");

        if (count($importantInfoRows) > 0) {
            $note = implode('. ', preg_replace('/^(.*?)\s*[.;!?]+$/', '$1', $importantInfoRows)) . '.';
            $it['notes'] = [$note]; // overwriting old value
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function assignLang(): bool
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return false;
        }
        foreach (self::$dictionary as $lang => $phrases) {
            if ( !is_string($lang)  ) {
                continue;
            }
            if (!empty($phrases['Add to Calendar']) && $this->http->XPath->query("//node()[{$this->eq($phrases['Add to Calendar'], "translate(.,':','')")}]")->length > 0
                || !empty($phrases['Meeting Point']) && $this->http->XPath->query("//*[{$this->eq($phrases['Meeting Point'], "translate(.,':','')")}]")->length > 0
                || !empty($phrases['View on']) && $this->http->XPath->query("//node()[{$this->eq($phrases['View on'], "translate(.,':','')")}]")->length > 0
                || !empty($phrases['Open Ticket']) && $this->http->XPath->query("//*[{$this->eq($phrases['Open Ticket'], "translate(.,':','')")}]")->length > 0
            ) {
                $this->lang = $lang;
                return true;
            }
        }
        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return $phrase;
        }
        if ($lang === '') {
            $lang = $this->lang;
        }
        if ( empty(self::$dictionary[$lang][$phrase]) ) {
            return $phrase;
        }
        return self::$dictionary[$lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return '';
        return '(?:' . implode('|', array_map(function($s) {
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
