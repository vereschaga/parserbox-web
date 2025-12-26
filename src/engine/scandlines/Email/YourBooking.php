<?php

namespace AwardWallet\Engine\scandlines\Email;

use AwardWallet\Schema\Parser\Email\Email;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Common\Parser\Util\PriceHelper;

class YourBooking extends \TAccountChecker
{
    public $mailFiles = "scandlines/it-701732832.eml, scandlines/it-698898567-de.eml";

    private $subjects = [
        'de' => ['Hiermit erhalten Sie Ihre Buchungsbestätigung | Buchungsnummer'],
        'en' => ["Here's your booking confirmation | Booking number", 'Here’s your booking confirmation | Booking number']
    ];

    public $lang = '';

    public static $dictionary = [
        'de' => [
            // Html
            'confNumber' => ['Buchungsnummer:', 'Buchungsnummer :'],
            'hello' => 'Vielen Dank für Ihre Buchung',
            'duration' => 'Reisezeit',
            'Vehicle' => 'Fahrzeug',
            'up to' => 'Pkw bis',
            'person' => 'Personen',
            'totalPrice' => 'Gesamt',
            'feeNames' => ['Treibstoffzuschlag'],

            // Pdf
        ],
        'en' => [
            // Html
            'confNumber' => ['Booking number:', 'Booking number :'],
            'hello' => 'Thank you for your booking',
            'duration' => 'travel time',
            // 'Vehicle' => '',
            // 'up to' => '',
            // 'person' => '',
            'totalPrice' => 'Total',
            'feeNames' => ['Fuel surcharge'],

            // Pdf
        ]
    ];

    private $xpath = [
        'time' => 'contains(translate(.,"0123456789：Hh ","∆∆∆∆∆∆∆∆∆∆:::"),"∆:∆∆")',
    ];

    private $patterns = [
        'date' => '\b\d{1,2}[-\s]+[[:alpha:]]{1,30}[-\s]+\d{4}\b', // 28 October 2024
        'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    private function parseFerryHtml(Email $email): void
    {
        $f = $email->add()->ferry();

        $traveller = null;
        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('hello'))}]", null, "/^{$this->opt($this->t('hello'))}[,\s]+({$this->patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));
        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
        }
        $f->general()->traveller($traveller);

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]");
        if (preg_match("/^({$this->opt($this->t('confNumber'))})[\s:：]*([-A-Z\d]{4,40})$/", $confirmation, $m)) {
            $f->general()->confirmation($m[2], trim($m[1], ':： '));
        }

        $vehicleLength = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Vehicle'))}]/following-sibling::tr/descendant::text()[{$this->contains($this->t('up to'))}]");
        $adultsCount = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Vehicle'))}]/following-sibling::tr/descendant::text()[{$this->contains($this->t('person'))}]", null, true, "/^(\d{1,3})[-\s]*{$this->opt($this->t('person'))}/i");

        $noteTexts = [];

        $points = $this->findPoints();
        $pointStart = $pointEnd = null;
        $pointIsDeparture = true;

        foreach ($points as $key => $root) {
            $dateVal = null;

            $preRows = $this->http->XPath->query("preceding-sibling::tr[normalize-space()][1]", $root);
            $preRow = $preRows->length > 0 ? $preRows->item(0) : null;

            while ($preRow) {
                $preRowText = implode(' ', $this->http->FindNodes("descendant::text()[normalize-space()]", $preRow));

                if ($this->http->XPath->query("self::node()[ count(descendant::text()[normalize-space()])=count(descendant::text()[normalize-space() and ancestor::*[{$this->contains(['color:#f8ac20', 'color:#F8AC20'], "translate(@style,' ','')")}]]) ]", $preRow)->length > 0) {
                    $noteTexts[] = $preRowText;
                }
                
                if (preg_match("/^{$this->patterns['date']}$/u", $preRowText)) {
                    $dateVal = $preRowText;

                    break;
                }

                $preRows = $this->http->XPath->query("preceding-sibling::tr[normalize-space()][1]", $preRow);
                $preRow = $preRows->length > 0 ? $preRows->item(0) : null;
            }

            $date = strtotime($this->normalizeDate($dateVal));

            $time = $name = null;
            $text = implode(' ', $this->http->FindNodes("descendant::text()[normalize-space()]", $root));
            
            if (preg_match("/^[•\s]*(?<time>{$this->patterns['time']})\s+(?<name>.{2,})$/", $text, $m)) {
                $time = $m['time'];
                $name = $m['name'];
            }

            $dateTime = $date && $time ? strtotime($time, $date) : null;

            if ($key === 0) {
                $pointStart = $name;
            }

            if ($pointIsDeparture) {
                $s = $f->addSegment();
                $s->departure()->date($dateTime)->name($name);

                $subText = $this->htmlToText( $this->http->FindHTMLByXpath('following-sibling::tr[normalize-space()][1]', null, $root) );
            
                if (preg_match("/^(?<duration>.+?)[ ]*\n+[ ]*(?<vessel>.{2,})$/", $subText, $m)) {
                    $duration = preg_replace(["/^(.*\S)(?:\s+{$this->opt($this->t('duration'))})+$/i", "/^(?:{$this->opt($this->t('duration'))}\s+)+(\S.*)$/i"], '$1', $m['duration']);
                    $s->extra()->duration($duration)->vessel($m['vessel']);
                }

                if ($vehicleLength) {
                    $ve = $s->addVehicle();
                    $ve->setLength($vehicleLength);
                }

                if ($adultsCount !== null) {
                    $s->booked()->adults($adultsCount);
                }

                $pointIsDeparture = false;
            } else {
                /** @var \AwardWallet\Schema\Parser\Common\FlightSegment $s */
                $s->arrival()->date($dateTime)->name($name);
                $pointEnd = $name;
                $pointIsDeparture = true;
            }
        }

        if (count($noteTexts) > 0) {
            $f->general()->notes(implode('; ', array_unique($noteTexts)));
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('totalPrice'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/u', $totalPrice, $matches)) {
            // 105.90 EUR
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $f->price()->total(PriceHelper::parse($matches['amount'], $currencyCode))->currency($matches['currency']);

            if ($pointStart && $pointEnd) {
                $routeVariants = [$pointStart . '-' . $pointEnd, $pointStart . ' - ' . $pointEnd];
                $baseFare = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($routeVariants)}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

                if ( preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/u', $baseFare, $m) ) {
                    $f->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
                }
            }

            $feeRows = $this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('feeNames'))}] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[normalize-space()][2]', $feeRow, true, '/^(.*?\d.*?)\s*(?:\(|$)/');
                if ( preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/u', $feeCharge, $m) ) {
                    $feeName = $this->http->FindSingleNode('*[normalize-space()][1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                    $f->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
                }
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]scandlines\.com$/i', $from) > 0;
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
        if ($this->http->XPath->query("//a[{$this->contains(['.scandlines.com/', '.scandlines.de/', 'www.scandlines.com', 'www.scandlines.de', 'booking.scandlines.com', 'booking.scandlines.de'], '@href')}]")->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space()," on scandlines.com") or contains(normalize-space()," auf scandlines.de")]')->length === 0
        ) {
            return false;
        }
        return $this->findPoints()->length > 0;
    }

    private function findPoints(): \DOMNodeList
    {
        $xpathPoint = "starts-with(normalize-space(),'•') and count(descendant::text()[{$this->xpath['time']}])=1";
        return $this->http->XPath->query("//tr[ {$xpathPoint} and (preceding-sibling::tr[{$xpathPoint}] or following-sibling::tr[{$xpathPoint}]) ]");
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLangHtml();
        $this->parseFerryHtml($email);

        if ( empty($this->lang) ) {
            $this->logger->debug("Can't determine a language!");
        }
        $email->setType('YourBooking' . ucfirst($this->lang));
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

    private function assignLangHtml(): bool
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return false;
        }
        foreach (self::$dictionary as $lang => $phrases) {
            if ( !is_string($lang) || empty($phrases['confNumber']) ) {
                continue;
            }
            if ($this->http->XPath->query("//*[{$this->starts($phrases['confNumber'])}]")->length > 0) {
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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`
     * @param string|null $text Unformatted string with date
     * @return string|null
     */
    private function normalizeDate(?string $text): ?string
    {
        if ( preg_match('/^(\d{1,2})[-\s]+([[:alpha:]]+)[-\s]+(\d{4})$/u', $text, $m) ) {
            // 28 October 2024
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        }
        if ( isset($day, $month, $year) ) {
            if ( preg_match('/^\s*(\d{1,2})\s*$/', $month, $m) )
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            if ( ($monthNew = MonthTranslate::translate($month, $this->lang)) !== false )
                $month = $monthNew;
            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }
        return null;
    }
}
