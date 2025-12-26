<?php

namespace AwardWallet\Engine\sonesta\Email;

use AwardWallet\Schema\Parser\Email\Email;

class It3141894 extends \TAccountChecker
{
    public $mailFiles = "sonesta/it-59696384.eml, sonesta/it-59854406.eml, sonesta/it-660877617.eml, sonesta/it-89524170.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Confirmation number'             => ['Confirmation number', 'Confirmation #:'],
            'Number of nights'                => ['Number of nights'],
            'Check-in'                        => ['Check-in', 'Check in'],
            'Check-out'                       => ['Check-out', 'Check out'],
            'GET DIRECTIONS'                  => ['GET DIRECTIONS', 'Get Directions', 'Get directions', 'get directions', 'get directions [sonesta.com]'],
            'GUARANTEE & CANCELLATION POLICY' => ['GUARANTEE & CANCELLATION POLICY', 'Guarantee & Cancellation Policy', 'Guarantee & cancellation policy'],
            'totalPrice'                      => ['Total price including room, daily fee, and applicable taxes', 'Total price including tax'],
        ],
    ];

    private $subjects = [
        'en' => ['Thank you for choosing', 'Sonesta Itinerary Confirmation - '],
    ];

    private $detectors = [
        'en' => ['YOUR UPCOMING RESERVATION AT', 'Your upcoming reservation at', 'Your Upcoming Reservation at', 'Your Upcoming Stay at'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@sonesta.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Sonesta') === false) {
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
            && $this->http->XPath->query('//a[contains(@href,".sonesta.com/") or contains(@href,"www.sonesta.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"SONESTA TRAVEL PASS") or contains(.,"@sonesta.com") or contains(.,"www.sonesta.com") or contains(.,"Sonesta International Hotels Corporation")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseHotel($email);
        $email->setType('It3141894' . ucfirst($this->lang));

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

    private function parseHotel(Email $email): void
    {
        $xpath = "//text()[{$this->starts($this->t('Confirmation number'))}]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $i => $root) {
            $rule = '';

            if ($nodes->length > 1) {
                $rule = "[count(preceding::text()[{$this->starts($this->t('Confirmation number'))}]) = " . ($i + 1) . "]"
                    . "[count(following::text()[{$this->starts($this->t('Confirmation number'))}]) = " . ($nodes->length - $i - 1) . "]";
            }
            $h = $email->add()->hotel();

            $xpathBold = '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])';

            $xpathFields = "//*[ count(table[normalize-space()])=2 and table[normalize-space()][1]/descendant::text()[{$this->starts($this->t('Arrival date'))}] and table[normalize-space()][2]/descendant::text()[{$this->starts($this->t('Room type'))}] ]"
                . $rule;

            $leftFieldsHtml = $this->http->FindHTMLByXpath($xpathFields . "/table[normalize-space()][1]");
            $leftFields = $this->htmlToText($leftFieldsHtml);

            $rightFieldsHtml = $this->http->FindHTMLByXpath($xpathFields . "/table[normalize-space()][2]/descendant::*[not(descendant::a[contains(.,'/')])][1]");
            $rightFields = $this->htmlToText($rightFieldsHtml);

            $bottomFieldsHtml = $this->http->FindHTMLByXpath($xpathFields . "/following::tr[normalize-space()][1][ descendant::text()[{$this->starts($this->t('totalPrice'))}] ]");
            $bottomFields = $this->htmlToText($bottomFieldsHtml);

            if (empty(trim($leftFields)) && empty(trim($rightFields)) && empty(trim($bottomFields))) {
                $xpathFields = "//*[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::text()[{$this->starts($this->t('Arrival date'))}] and *[normalize-space()][2]/descendant::text()[{$this->starts($this->t('Room type'))}] ]"
                    . $rule;
                $leftFieldsHtml = $this->http->FindHTMLByXpath($xpathFields . "/*[normalize-space()][1]");
                $leftFields = $this->htmlToText($leftFieldsHtml);

                $rightFieldsHtml = $this->http->FindHTMLByXpath($xpathFields . "/*[normalize-space()][2]/descendant::*[not(descendant::a[contains(.,'/')])][1]");
                $rightFields = $this->htmlToText($rightFieldsHtml);

                $bottomFieldsHtml = $this->http->FindHTMLByXpath($xpathFields . "/following::tr[normalize-space()][1][ descendant::text()[{$this->starts($this->t('totalPrice'))}] ]");
                $bottomFields = $this->htmlToText($bottomFieldsHtml);
            }

            $fullTextFields = $leftFields . "\n\n" . $rightFields . "\n\n" . $bottomFields;

            $guestName = preg_match("/^[ ]*{$this->opt($this->t('Guest'))}[: ]+([[:alpha:]][-.\'\(\)[:alpha:] ]*[[:alpha:]])[ ]*$/m",
                $fullTextFields, $m) ? $m[1] : null;

            if (empty($guestName)) {
                $guestName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Guest:'))}]",
                    $root, true, "/^[ ]*{$this->opt($this->t('Guest'))}[: ]+([[:alpha:]][-.\'\(\)[:alpha:] ]*[[:alpha:]])[ ]*$/");
            }
            $h->general()->traveller($guestName);

            if (preg_match("/^[ ]*({$this->opt($this->t('Confirmation number'))})[: ]+([-A-Z\d]{5,})[ ]*$/m", $fullTextFields, $m)
                || preg_match("/^[ ]*({$this->opt($this->t('Confirmation number'))})[: ]+([-A-Z\d]{5,})[ ]*$/m",
                $this->http->FindSingleNode("./ancestor::td[1]", $root), $m)
            ) {
                $h->general()->confirmation($m[2], $m[1]);
            }

            $patterns['time'] = '\d{1,2}(?:[:：]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon)?';

            $dateCheckIn = preg_match("/^[ ]*{$this->opt($this->t('Arrival date'))}[: ]+(.{6,})[ ]*$/m",
                $fullTextFields, $m) ? $m[1] : null;
            $timeCheckIn = preg_match("/^[ ]*{$this->opt($this->t('Check-in'))}[: ]+({$patterns['time']})[ ]*(?:\(|$)/m",
                $fullTextFields, $m) ? preg_replace('/^12 noon$/i', '12:00', $m[1]) : null;
            $dateCheckOut = preg_match("/^[ ]*{$this->opt($this->t('Departure date'))}[: ]+(.{6,})[ ]*$/m",
                $fullTextFields, $m) ? $m[1] : null;
            $timeCheckOut = preg_match("/^[ ]*{$this->opt($this->t('Check-out'))}[: ]+({$patterns['time']})[ ]*(?:\(|$)/m",
                $fullTextFields, $m) ? preg_replace('/^12 noon$/i', '12:00', $m[1]) : null;
            $h->booked()
                ->checkIn2($dateCheckIn . ' ' . $timeCheckIn)
                ->checkOut2($dateCheckOut . ' ' . $timeCheckOut);

            $adults = preg_match("/^[ ]*{$this->opt($this->t('Number of adults'))}[: ]+(\d{1,3})[ ]*$/m",
                $fullTextFields, $m) ? $m[1] : null;
            $children = preg_match("/^[ ]*{$this->opt($this->t('Number of children'))}[: ]+(\d{1,3})[ ]*/m",
                $fullTextFields, $m) ? $m[1] : null;
            $h->booked()
                ->guests($adults);

            if ($children !== null) {
                $h->booked()
                    ->kids($children);
            }

            $room = $h->addRoom();

            $roomType = preg_match("/^[ ]*{$this->opt($this->t('Room type'))}[: ]+(.+?)[ ]*$/m", $fullTextFields,
                $m) ? $m[1] : null;
            $room->setType($roomType);

            $rate = preg_match("/^[ ]*{$this->opt($this->t('Average daily rate'))}[: ]+(.*\d.*?)[ ]*$/m",
                $fullTextFields, $m) ? $m[1] : null;
            $room->setRate($rate, false, true);

            $totalPrice = preg_match("/^[ ]*{$this->opt($this->t('totalPrice'))}[: ]+(.+?)[ ]*$/m", $fullTextFields,
                $m) ? $m[1] : null;

            if (preg_match('/^(?<currency>[^\d)(]+)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $m)) {
                // USD 258.62
                $h->price()
                    ->currency($m['currency'])
                    ->total($this->normalizeAmount($m['amount']));
            }

            $hotelHtml = $this->http->FindHTMLByXpath("//*[ count(table)=2 and table[2]/descendant::a[{$this->eq($this->t('GET DIRECTIONS'))}] ]/table[normalize-space()][1]")
                ?? $this->http->FindHTMLByXpath("//table[ not(.//table) and not(preceding-sibling::table[normalize-space()]) and not(following-sibling::table[normalize-space()]) and descendant::text()[normalize-space()][last()][{$this->starts(['www.sonesta.com/', 'WWW.SONESTA.COM/'])}] ]")
                ?? $this->http->FindHTMLByXpath("//tr[{$this->eq($this->t('GET DIRECTIONS'))}]/ancestor::tr[ descendant::text()[normalize-space()][1][ancestor::*[{$xpathBold}]] ][1]") // it-89524170.eml
            ;
            $hotelText = $this->htmlToText($hotelHtml);

            if (preg_match("/^\s*(?<name>.{3,}?)[ ]*\n[ ]*(?<address>[\s\S]{3,}?)\s*$/", $hotelText, $matches)) {
                $h->hotel()->name($matches['name']);
                $matches['address'] = preg_replace('/^[ ]*www\.\w.+/im', '', $matches['address']);

                if (preg_match("/^(?<address>[\s\S]{3,}?)[ ]*\n[ ]*(?<phone>[+(\d][-. \d)(]{5,}[\d)])[ ]*(?:\n|$)/",
                    $matches['address'], $m)) {
                    $h->hotel()
                        ->address(preg_replace('/\s+/', ' ', $m['address']))
                        ->phone($m['phone']);
                } else {
                    $h->hotel()->address(preg_replace('/\s+/', ' ', $matches['address']));
                }
            }

            $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('GUARANTEE & CANCELLATION POLICY'))}]{$rule}/following::text()[normalize-space()][1][not(ancestor::*[self::b or self::strong or contains(@style,'uppercase')])]");
            $h->general()->cancellation($cancellation);

            if (preg_match("/Cancel by (?<hour>{$patterns['time']}) day of arrival to avoid fee of 1 night with taxes\.?$/",
                $cancellation, $m)) {
                $h->booked()->deadlineRelative('0 days', $m['hour']);
            } elseif (preg_match("/Cancell? by (?<hour>{$patterns['time']}) hotel time zone AZ (?<prior>\d{1,3} hours?) prior to arrival to avoid one nights room and tax\.?$/",
                    $cancellation, $m)
                || preg_match("/Cancell? (?i)by (?<hour>{$patterns['time']}) on the (?<prior>hour|day|month) before arrival to avoid fee equal to 1 night's room rate plus tax\.?$/",
                    $cancellation, $m)
            ) {
                $m['prior'] = preg_replace('/^(hour|day|month)$/i', '1 $1', $m['prior']);
                $h->booked()->deadlineRelative($m['prior'], $m['hour']);
            } elseif (preg_match("/Cxl (?i)(?<prior>\d{1,3} days?) prior to arrival date to avoid fee of 1 night and tax/",
                    $cancellation, $m)
                || preg_match("/Cancell? (?i)(?<prior>\d{1,3}\s*(?:hrs?|hours?)) prior to arrive to avoid 1 night room plus tax/",
                    $cancellation, $m)
            ) {
                $m['prior'] = preg_replace('/^(\d+)\s*hrs?$/i', '$1 hours', $m['prior']);
                $h->booked()->deadlineRelative($m['prior'], '00:00');
            }
        }
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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Confirmation number']) || empty($phrases['Number of nights'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Confirmation number'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Number of nights'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
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

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
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
