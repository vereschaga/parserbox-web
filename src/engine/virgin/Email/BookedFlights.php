<?php

namespace AwardWallet\Engine\virgin\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BookedFlights extends \TAccountChecker
{
    public $mailFiles = "virgin/it-12410531.eml, virgin/it-399510342.eml, virgin/it-400079217.eml, virgin/it-402878333.eml, virgin/it-727604287.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'totalPrice'                => ['Total Amount', 'Total amount', 'Total'],
            'Your booking confirmation' => ['Your booking confirmation', 'Your booking information'],
        ],
    ];

    private $subjects = [
        'en' => ['Your travel checklist is ready',
            'Schedule change affecting your flight',
            'Check in now open',
            'Only 5 days to go until your flight to',
        ],
    ];

    private $langDetectors = [
        'en' => ['Your Itinerary', 'Your travel checklist', 'Your booking confirmation', 'Your original booking confirmation', 'What\'s changed?',
            'flight is just around the corner', 'It\'s almost time to fly.', 'View online', ],
    ];

    private $relativeDate;

    private $xpath = [
        'cell'        => '(self::td or self::th)',
        'airportCode' => 'translate(normalize-space(),"ABCDEFGHIJKLMNOPQRSTUVWXYZ ","∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆")="∆∆∆"',
        'noDisplay'   => 'ancestor-or-self::*[contains(translate(@style," ",""),"display:none")]',
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]virginatlantic\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (preg_match('/\bVirgin Atlantic Airways e-Ticket(?:\s+[A-Z\d]{5,}\b|$)/', $headers['subject'])) {
            return true;
        }

        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Virgin Atlantic Airways') === false) {
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
            && $this->http->XPath->query('//a[contains(@href,".virginatlantic.com/") or contains(@href,"emails.virginatlantic.com") or contains(@href,"virginatlantic-rt-prod2-t.campaign.adobe.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thanks for booking with Virgin Atlantic") or contains(normalize-space(),"when contacting Virgin Atlantic") or contains(normalize-space(),"Registered office: Virgin Atlantic Airways Ltd")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang() && $this->findSegments()->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $dateStr = $this->http->FindSingleNode("(//td[{$this->eq($this->t('Purchased on'))}]/following-sibling::*[normalize-space()][1])[1]");

        if (preg_match("/^\s*(\d{1,2}\s+[[:alpha:]]+)\s+(\d{2})\s*$/", $dateStr, $m)) {
            $this->relativeDate = strtotime($m[1] . ' 20' . $m[2]);
        }

        if (empty($this->relativeDate)) {
            $this->relativeDate = EmailDateHelper::getEmailDate($this, $parser);
        }

        $partXpath = "//text()[{$this->eq($this->t('Your booking confirmation'))}][following::text()[normalize-space()][1][{$this->eq($this->t('Can\'t see this email?'))}]]";
        // $this->logger->debug('$partXpath = '.print_r( $partXpath,true));

        if ($this->http->XPath->query($partXpath)->length === 0 && $this->http->XPath->query("//text()[{$this->eq($this->t('Your booking confirmation'))}][following::text()[{$this->eq($this->t('Can\'t see this email?'))}]]")->length === 0) {
            $partXpath = "//text()[{$this->eq($this->t('Can\'t see this email?'))}][following::text()[normalize-space()][1][{$this->eq($this->t('View online'))}]]";
        }

        if (stripos($parser->getCleanFrom(), 'virginatlantic.com') === false
            && $this->http->XPath->query($partXpath)->length === 1
        ) {
            $text = implode("\n", $this->http->FindNodes($partXpath . "/preceding::text()[normalize-space()][not(ancestor::style)][1]/ancestor::div[count(.//text()[normalize-space()]) > 2][not(.//text()[{$this->eq($this->t('Your booking confirmation'))}])][1]//text()[normalize-space()]"));

            if (empty($text)) {
                $text = implode("\n", $this->http->FindNodes($partXpath . "/preceding::text()[normalize-space()][not(ancestor::style)][1]/ancestor::div[1]/following-sibling::*[normalize-space()][1][descendant::text()[normalize-space()][1][{$this->eq($this->t('Your booking confirmation'))}]]/preceding-sibling::div[normalize-space()][position() < 7]//text()[normalize-space()]"));
            }

            if (empty($text)) {
                $text = implode("\n", $this->http->FindNodes($partXpath . "/preceding::text()[normalize-space()][not(ancestor::style)][1]/ancestor::span[count(.//text()[normalize-space()]) > 2][not(.//text()[{$this->eq($this->t('Your booking confirmation'))}])][1]//text()[normalize-space()]"));
            }

            if (empty($text)) {
                $text = implode("\n", $this->http->FindNodes($partXpath . "/ancestor::blockquote[1]/preceding::text()[normalize-space()][not(ancestor::style)][1]/ancestor::div[count(.//text()[normalize-space()]) > 2][not(.//text()[{$this->eq($this->t('Your booking confirmation'))}])][1]//text()[normalize-space()]"));
            }

            if (empty($text)) {
                $text = implode("\n", $this->http->FindNodes($partXpath . "/preceding::text()[normalize-space()][not(ancestor::style)][1]/ancestor::div[count(.//text()[normalize-space()]) < 3]/following-sibling::*[{$this->starts($this->t('Your booking confirmation'))} or {$this->starts($this->t('Can\'t see this email?'))}]/preceding-sibling::*[normalize-space()][position() < 7][count(.//text()[normalize-space()]) < 3]"));
            }

            // $this->logger->debug('$text = '.print_r( $text,true));

            if (substr_count($text, "\n") < 10
                && preg_match("/\n[[:alpha:]]+ ?[:：](?:\s*\W{0,3}\s*(?:Virgin Atlantic|Virgin|virginatlantic\.com)\W{0,3})+\n/u", "\n" . $text . "\n")
                && preg_match("/\n(Date|Data|Sent|Dátum|Fecha) ?:\s*(?<date>.*\b20\d{2}\D.+\D\d{1,2}:\d{2}\b.*)\n/u", "\n" . $text . "\n", $m)
                && preg_match_all("/\n[[:alpha:]]+ ?:\s*(?:Virgin Atlantic Airways e-Ticket [A-Z\d]{5,7})\s*\n/u", "\n" . $text . "\n", $sm)
                && count($sm[0]) === 1
            ) {
                $this->relativeDate = $this->normalizeDateFrom($m['date']);

                if (!empty($this->relativeDate)) {
                    $this->relativeDate = strtotime('-30 day', $this->relativeDate);
                }
            } elseif (preg_match("/^\s*On (.+) Virgin Atlantic(?: virginatlantic@service\.virginatlantic\.com\>)? wrote:/", $text, $m)) {
                // On Sun, Sep 22, 2024 at 9:03 AM Virgin Atlantic virginatlantic@service.virginatlantic.com> wrote:
                $this->relativeDate = $this->normalizeDateFrom($m[1]);

                if (!empty($this->relativeDate)) {
                    $this->relativeDate = strtotime('-30 day', $this->relativeDate);
                }
            }
        }

        if (empty($this->relativeDate)) {
            $email->setIsJunk(true, 'This email was forwarded. Dates cannot be determined reliably.');

            return $email;
        }
        $this->logger->debug('$this->relativeDate = ' . print_r($this->relativeDate, true));

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('BookedFlights' . ucfirst($this->lang));

        $patterns = [
            'dayMonth'      => '\b\d{1,2}\s+[[:alpha:]]+\b', // 05 JUL
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
        ];

        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking ref:'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking ref:'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $seatsByAirports = [];

        $passengerCells = $this->http->XPath->query("//tr[ *[{$this->eq($this->t('Flight'))}]/following-sibling::*[{$this->eq($this->t('Seat'))}] ]/ancestor-or-self::tr[ following-sibling::tr[normalize-space()] ][1]/following-sibling::tr[normalize-space() and not(descendant::*[{$this->eq($this->t('Flight'))} or {$this->eq($this->t('Seat'))}])]/descendant-or-self::*[ *[normalize-space()][2] ][1]");

        foreach ($passengerCells as $pCell) {
            $guestText = $this->htmlToText($this->http->FindHTMLByXpath('*[normalize-space()][1]', null, $pCell));

            $traveller = null;

            if (preg_match("/^\s*({$patterns['travellerName']})[ ]*(?:\(|\n|$)/u", $guestText, $m)) {
                $traveller = $m[1];

                if (preg_match("/\(\s*Infant\s*\)/i", $guestText)) {
                    $f->general()
                        ->infant($traveller, true);
                } else {
                    $f->general()
                        ->traveller($traveller, true);
                }
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('eTicket'))}[: ]+({$patterns['eTicket']})[ ]*(?:\n|$)/m", $guestText, $m)) {
                $f->issued()
                    ->ticket($m[1], false, $traveller);
            }

            if (preg_match("/^[ ]*({$this->opt($this->t('Flying Club No.'))})[: ]+([- A-Z\d]{5,})[ ]*(?:\n|$)/m", $guestText, $m)) {
                $f->program()
                    ->account($m[2], false, $traveller, $m[1]);
            }

            $airportsText = $this->htmlToText($this->http->FindHTMLByXpath('*[normalize-space()][2]/descendant::*[ *[normalize-space()][2] ][1]/*[normalize-space()][1]', null, $pCell));
            $seatsText = $this->htmlToText($this->http->FindHTMLByXpath('*[normalize-space()][2]/descendant::*[ *[normalize-space()][2] ][1]/*[normalize-space()][2]', null, $pCell));

            $airportsList = preg_split('/[ ]*\n[ ]*/', $airportsText);
            $seatsList = preg_split('/[ ]*\n[ ]*/', $seatsText);

            foreach ($airportsList as $key => $value) {
                if (!preg_match('/^\d+[A-Z]$/', $seatsList[$key])) {
                    continue;
                }

                $value = str_replace(' ', '', $value);

                if (array_key_exists($value, $seatsByAirports)) {
                    $seatsByAirports[$value][] = $seatsList[$key];
                } else {
                    $seatsByAirports[$value] = [$seatsList[$key]];
                }
            }
        }

        $segStatuses = [];
        $segments = $this->findSegments();

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $flight = $this->http->FindSingleNode("ancestor::table[ preceding-sibling::table[normalize-space()] ][1]/preceding-sibling::table[normalize-space()][1]/descendant::text()[{$this->eq($this->t('Flight no.'))}]/following::text()[normalize-space()][1]", $root)
                ?? $this->http->FindSingleNode("ancestor::div[ preceding-sibling::div[normalize-space()] ][1]/preceding-sibling::div[normalize-space()][1]/descendant::text()[{$this->eq($this->t('Flight no.'))}]/following::text()[normalize-space()][1]", $root)
            ;

            if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/", $flight, $m)) {
                // it-400079217.eml
                $s->airline()->name($m['name'])->number($m['number']);
            } elseif (preg_match("/^(?<number>\d{1,4})$/", $flight, $m)) {
                $s->airline()->noName()->number($m['number']);
            } elseif ($this->http->XPath->query("//tr[{$this->starts($this->t('Flight ∆ of ∆'), 'translate(normalize-space(),"0123456789","∆∆∆∆∆∆∆∆∆∆")')}]")->length === 0) {
                // it-12410531.eml
                $s->airline()->noName()->noNumber();
            }

            $xpathPreRow = "ancestor-or-self::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][1]/descendant::*[count(table)=2]";

            $cabin = $this->http->FindSingleNode($xpathPreRow . "/table[1]", $root);

            if (preg_match("/^(?<cabin>.{2,}?)\s*\(\s*(?<bookingCode>[A-Z]{1,2})\s*\)$/", $cabin, $m)) {
                // Economy Classic (N)
                $s->extra()->cabin($m['cabin'])->bookingCode($m['bookingCode']);
            } elseif (preg_match("/^[(\s]*(?<bookingCode>[A-Z]{1,2})[\s)]*$/", $cabin, $m)) {
                // (N)    |    N
                $s->extra()->bookingCode($m['bookingCode']);
            } else {
                // Economy Classic
                $s->extra()->cabin($cabin, false, true);
            }

            $bookingRef = $this->http->FindSingleNode($xpathPreRow . "/table[2]", $root);

            if (preg_match("/^({$this->opt($this->t('Booking Ref'))})[:\s]+([A-Z\d]{5,})$/", $bookingRef, $m)) {
                if ($confirmation && $confirmation !== $m[2]) {
                    $s->airline()->confirmation($m[2]);
                }
            }

            $dateDep = $dateArr = null;

            $dateDepVal = $this->http->FindSingleNode("*[{$this->xpath['cell']}][1]/descendant::tr[{$this->xpath['airportCode']}]/preceding-sibling::tr[normalize-space()][1]", $root);
            $dateArrVal = $this->http->FindSingleNode("*[{$this->xpath['cell']}][3]/descendant::tr[{$this->xpath['airportCode']}]/preceding-sibling::tr[normalize-space()][1]", $root) ?? $dateDepVal;
            $this->logger->error($dateArrVal);

            if (preg_match('/^.+\b\d{4}$/', $dateDepVal)) {
                $dateDep = strtotime($dateDepVal);
            } elseif (!empty($this->relativeDate) && preg_match("/^{$patterns['dayMonth']}$/u", $dateDepVal)) {
                $dateDep = EmailDateHelper::parseDateRelative($dateDepVal, $this->relativeDate, $parser, '%D% %Y%');
            }

            if (preg_match('/^.+\b\d{4}$/', $dateArrVal)) {
                $dateArr = strtotime($dateArrVal);
            } elseif (!empty($this->relativeDate) && preg_match("/^{$patterns['dayMonth']}$/u", $dateArrVal)) {
                $dateArr = EmailDateHelper::parseDateRelative($dateArrVal, $this->relativeDate, $parser, '%D% %Y%');
            }

            $timeDep = $this->http->FindSingleNode("*[{$this->xpath['cell']}][1]/descendant::tr[{$this->xpath['airportCode']}]/following::tr[not(.//tr) and normalize-space()][1]", $root, true, "/^{$patterns['time']}$/");
            $timeArr = $this->http->FindSingleNode("*[{$this->xpath['cell']}][3]/descendant::tr[{$this->xpath['airportCode']}]/following::tr[not(.//tr) and normalize-space()][1]", $root, true, "/^{$patterns['time']}$/");

            if ($dateDep && $timeDep) {
                $s->departure()
                    ->date(strtotime($timeDep, $dateDep))
                    ->strict();
            }

            if ($dateArr && $timeArr) {
                $dateArrival = strtotime($timeArr, $dateArr);

                if ($s->getDepDate() > $dateArrival && (abs($s->getDepDate() - $dateArrival) > 172800)) { // 172800 - two day in seconds
                    $dateArrival = strtotime('+1 year', $dateArrival);
                }

                $s->arrival()
                    ->date($dateArrival)
                    ->strict();
            } elseif ($dateArr && !$timeArr) {
                $s->arrival()
                    ->day($dateArr)
                    ->noDate();
            }

            $codeDep = $this->http->FindSingleNode("*[{$this->xpath['cell']}][1]/descendant::tr[{$this->xpath['airportCode']}]", $root);
            $codeArr = $this->http->FindSingleNode("*[{$this->xpath['cell']}][3]/descendant::tr[{$this->xpath['airportCode']}]", $root);

            $s->departure()->code($codeDep);
            $s->arrival()->code($codeArr);

            if ($codeDep && $codeArr && array_key_exists($codeDep . '-' . $codeArr, $seatsByAirports)) {
                $s->extra()->seats($seatsByAirports[$codeDep . '-' . $codeArr]);
            }

            $operator = $this->http->FindSingleNode("*[{$this->xpath['cell']}][2]/descendant::text()[{$this->eq($this->t('Operated by'))}]/following::text()[normalize-space()][1]", $root);
            $s->airline()->operator($operator, false, true);
        }

        $xpathFareBreakdown = "tr[not({$this->xpath['noDisplay']}) and not(.//tr) and {$this->eq($this->t('Fare breakdown'))}]";
        $xpathPaymentBreakdown = "tr[not({$this->xpath['noDisplay']}) and not(.//tr) and ({$this->eq($this->t('Payment breakdown'))} or {$this->eq($this->t('Extras and Upgrades'))}) and preceding::{$xpathFareBreakdown}]";

        $totalPriceCurrencies = $totalPriceAmounts = [];
        $totalPriceRows = $this->http->XPath->query("//tr[ not({$this->xpath['noDisplay']}) and count(preceding::{$xpathFareBreakdown})=1 and count(preceding::{$xpathPaymentBreakdown})=0 and count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('totalPrice'))}] ]");

        foreach ($totalPriceRows as $tpRow) {
            $totalPrice = $this->http->FindSingleNode("*[normalize-space()][2]", $tpRow, true, '/^.*\d.*$/');

            if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
                // GBP 1501.22
                $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                $totalPriceCurrencies[] = $matches['currency'];
                $totalPriceAmounts[] = PriceHelper::parse($matches['amount'], $currencyCode);
            }
        }

        if (count(array_unique($totalPriceCurrencies)) === 1) {
            $f->price()->currency($totalPriceCurrencies[0])->total(array_sum($totalPriceAmounts));
        }

        $spentAwards = $this->http->FindSingleNode("//{$xpathPaymentBreakdown}/following::tr[ not({$this->xpath['noDisplay']}) and count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Total Virgin Points paid'))}] ]/*[normalize-space()][2]", null, true, "/^\d[,.‘\'\d ]*points?$/iu");

        if ($spentAwards !== null) {
            $f->price()->spentAwards($spentAwards);
        }

        // Statement
        $xpathStatement = "//tr[ *[{$this->starts($this->t('Flying Club no.'))}]/following-sibling::*[{$this->starts($this->t('Virgin Points'))}]/following-sibling::*[{$this->starts($this->t('Tier'))}] ]";
        $ffNumber = $this->http->FindSingleNode($xpathStatement . "/*[{$this->starts($this->t('Flying Club no.'))}]", null, true, "/^{$this->opt($this->t('Flying Club no.'))}[:\s]*([- A-Z\d]{5,})$/");

        if (!empty($ffNumber) && !in_array($ffNumber, array_column($f->getAccountNumbers(), 0))) {
            $f->program()->account($ffNumber, false);
        }

        $virginPoints = $this->http->FindSingleNode($xpathStatement . "/*[{$this->starts($this->t('Virgin Points'))}]", null, true, "/^{$this->opt($this->t('Virgin Points'))}[:\s]*(\d[,.‘\'\d ]*)$/");

        $tierStatus = $this->http->FindSingleNode($xpathStatement . "/*[{$this->starts($this->t('Tier'))}]", null, true, "/^{$this->opt($this->t('Tier'))}[:\s]*(Red|Silver|Gold|Lifetime Gold|Gold for life)$/i");

        if (!empty($ffNumber) && ($virginPoints !== null || $tierStatus)) {
            $st = $email->add()->statement();
            $st->setNumber($ffNumber)
                ->setBalance($virginPoints);

            if (!empty($tierStatus)) {
                $st->addProperty('EliteStatus', $tierStatus);
            }
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

    private function findSegments(): \DOMNodeList
    {
        $xpath = "//tr[ *[{$this->xpath['cell']}][1]/descendant::tr[{$this->xpath['airportCode']}] and *[{$this->xpath['cell']}][3]/descendant::tr[{$this->xpath['airportCode']}] ]";
        $this->logger->debug('$xpath = ' . print_r($xpath, true));

        return $this->http->XPath->query($xpath);
    }

    private function assignLang(): bool
    {
        if (!isset($this->langDetectors, $this->lang)) {
            return false;
        }

        foreach ($this->langDetectors as $lang => $phrases) {
            if (!is_string($lang) || count($phrases) === 0) {
                continue;
            }

            if ($this->http->XPath->query("//text()[{$this->contains($phrases)} and not({$this->xpath['noDisplay']})]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
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

    private function normalizeDateFrom($date)
    {
        // $this->logger->debug('$date in  = '.print_r( $date,true));
        $in = [
            // seg., 4 de dez. de 2023 09:05
            "/^\s*[[:alpha:]\-]+[.]?\s*[,\s]\s*(\d{1,2})(?: de | )?([[:alpha:]]{3,})[.]?(?: de | )?(20\d{2})(\s*,\s*|\s+|[,\s]+[[:alpha:]\.]{1,4}\s+)\d{1,2}:\d{2}\b.*$/ui",
            // 22 de junho de 2024 às 00:47:04 BRT
            "/^\s*(\d{1,2})(?: de | )?([[:alpha:]]{3,})[.]?(?: de | )?(20\d{2})(\s*,\s*|\s+|\s+[[:alpha:]\.]{1,4}\s+)\d{1,2}:\d{2}\b.*/ui",
            // Mon, Jul 15, 2024 at 1:41 PM
            "/^\s*[[:alpha:]\-]+[.]?\s*[,\s]\s*([[:alpha:]]{3,})\s+(\d{1,2})\s*[\s,]\s*(20\d{2})(\s*,\s*|\s+|[,\s]+[[:alpha:]\.]{1,4}\s+)\d{1,2}:\d{2}\b.*$/ui",
            // May 17, 2024 at 11:41:14 AM EDT
            "/^\s*([[:alpha:]]{3,})\s+(\d{1,2})\s*[\s,]\s*(20\d{2})(\s*,\s*|\s+|[,\s]+[[:alpha:]\.]{1,4}\s+)\d{1,2}:\d{2}\b.*$/ui",
            // 16/07/2024 09:05 (GMT-03:00)
            "/^\s*(\d{1,2})\/(\d{1,2})\/(20\d{2})(\s*,\s*|\s+|\s+[[:alpha:]\.]{1,4}\s+)\d{1,2}:\d{2}\b.*$/ui",
            // 2024年11月7日 週四 下午12:20
            "/^\s*(20\d{2})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日.*\D\d{1,2}:\d{2}.*$/ui",
        ];
        $out = [
            "$1 $2 $3",
            "$1 $2 $3",
            "$2 $1 $3",
            "$2 $1 $3",
            "$1.$2.$3",
            "$1-$2-$3",
        ];

        foreach ($in as $i => $re) {
            if (preg_match($re, $date)) {
                $date = preg_replace($in, $out, $date);

                break;
            }
        }
        // $this->logger->debug('$date repl  = '.print_r( $date,true));

        if (preg_match("#^(\D*\d{1,2})\.(\d{1,2})\.(\d{4})\s*$#u", $date, $m)) {
            $date = $m[1] . ' ' . date("F", mktime(0, 0, 0, $m[2], 1, 2011)) . ' ' . $m[3];
        }

        $result = null;

        if (preg_match("/^\s*(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4})\s*$/u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[2], 'pt')) {
                $date = $m[1] . ' ' . $en . ' ' . $m[3];
            } elseif ($en = MonthTranslate::translate($m[2], 'es')) {
                $date = $m[1] . ' ' . $en . ' ' . $m[3];
            } elseif ($en = MonthTranslate::translate($m[2], 'en')) {
                $date = $m[1] . ' ' . $en . ' ' . $m[3];
            }
            $result = strtotime($date);
        } elseif (preg_match("/^\s*(\d{4})-(\d{1,2})-(\d{1,2})\s*$/u", $date, $m)) {
            $result = strtotime($date);
        }

        return $result;
    }
}
