<?php

namespace AwardWallet\Engine\thrifty\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CarRental2021 extends \TAccountChecker
{
    public $mailFiles = "thrifty/it-103497403.eml, thrifty/it-675522447.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Pick up'    => ['Pick up'],
            'Return'     => ['Return'],
            'totalPrice' => ['Total cost', 'Total Cost'],
        ],
    ];

    private $subjects = [
        'en' => ['Booking Confirmation'],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (array_key_exists('subject', $headers)
            && stripos($headers['subject'], 'Your Thrifty Car Rental Booking Confirmation') !== false
        ) {
            return true;
        }

        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true
            && strpos($headers['subject'], 'Thrifty Car Rental') === false
        ) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && array_key_exists('subject', $headers) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".thriftycanada.ca/") or contains(@href,"www.thriftycanada.ca")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for booking your next adventure with Thrifty") or contains(normalize-space(),"The following are trademarks of Thrifty Car Rental")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.](?:thriftycars4rent|thrifty-reservations)\.com$/i', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $email->setType('CarRental2021' . ucfirst($this->lang));

        $patterns = [
            'date'          => '\b\d{1,2}-\d{1,2}-\d{4}\b', // 14-08-2021
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your booking confirmation number is:')]/following::text()[normalize-space()][1]"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi'))}]/ancestor::*[count(descendant::text()[normalize-space()])>1][1]", null, true, "/{$this->opt($this->t('Hi'))}\s*({$patterns['travellerName']})\s*,/u"), false);

        $pickUpInfo = $this->htmlToText($this->http->FindHTMLByXpath("//*[ count(tr[normalize-space()])=2 and tr[normalize-space()][1][{$this->eq($this->t('Pick up'))}] ]/tr[normalize-space()][2]"));

        if (preg_match($pattern = "/^(?<location>[\s\S]{3,}?)[ ]*\n+[ ]*(?<dateTime>\S.+\S)$/", $pickUpInfo, $m)) {
            $r->pickup()->location(preg_replace(['/[ ]*\n+[ ]*/', '/(\s*[,]+\s*)+/'], [', ', ', '], $m['location']));

            if (preg_match("/^(?<date>{$patterns['date']})\s+{$this->opt($this->t('at'))}\s+(?<time>{$patterns['time']})/", $m['dateTime'], $m2)) {
                $r->pickup()->date(strtotime($m2['time'], strtotime($m2['date'])));
            }
        }

        $dropOffInfo = $this->htmlToText($this->http->FindHTMLByXpath("//*[ count(tr[normalize-space()])=2 and tr[normalize-space()][1][{$this->eq($this->t('Return'))}] ]/tr[normalize-space()][2]"));

        if (preg_match($pattern, $dropOffInfo, $m)) {
            $r->dropoff()->location(preg_replace(['/[ ]*\n+[ ]*/', '/(\s*[,]+\s*)+/'], [', ', ', '], $m['location']));

            if (preg_match("/^(?<date>{$patterns['date']})\s+{$this->opt($this->t('at'))}\s+(?<time>{$patterns['time']})/", $m['dateTime'], $m2)) {
                $r->dropoff()->date(strtotime($m2['time'], strtotime($m2['date'])));
            }
        }

        $xpathCarV1 = "descendant::text()[ normalize-space() and preceding::text()[{$this->starts($this->t('Change booking'))}] and following::text()[{$this->starts($this->t('Change car'))}] ]"; // it-103497403.eml
        $xpathCarV2 = "//*[{$this->eq($this->t('Pick up'))}]/following::*[{$this->eq($this->t('Return'))}]/following::text()[{$this->contains($this->t('OR SIMILAR'))}]"; // it-675522447.eml

        $carModel = $this->http->FindSingleNode($xpathCarV1 . "[1]")
            ?? $this->http->FindSingleNode($xpathCarV2, null, true, "/^.{2,}\s+{$this->opt($this->t('OR SIMILAR'))}$/");
        $carType = $this->http->FindSingleNode($xpathCarV1 . "[2]");
        $carImg = $this->http->FindSingleNode("//img[ normalize-space(@src) and preceding::text()[{$this->starts($this->t('Change booking'))}] and following::text()[{$this->starts($this->t('Change car'))}] ]/@src")
            ?? $this->http->FindSingleNode($xpathCarV2 . "/following::node()[self::text()[normalize-space()] or self::img][1][ self::img[normalize-space(@src)] ]/@src");

        $r->car()->model($carModel)->type($carType, false, true)->image($carImg);

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('totalPrice'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // CAD 1128.15
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $r->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $baseFare = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Rental Charge'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $baseFare, $m)) {
                $r->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $feeRows = $this->http->XPath->query("//tr[ preceding-sibling::tr[*[normalize-space()][1][{$this->starts($this->t('Rental Charge'))}]] and following-sibling::tr[*[normalize-space()][1][{$this->eq($this->t('totalPrice'))}]] and *[2][normalize-space()] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[normalize-space()][2]', $feeRow, true, '/^(.*?\d.*?)\s*(?:\(|$)/');

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeCharge, $m)) {
                    $feeName = $this->http->FindSingleNode('*[normalize-space()][1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                    $r->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
                }
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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Pick up']) || empty($phrases['Return'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->eq($phrases['Pick up'])}]/following::*[{$this->eq($phrases['Return'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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
}
