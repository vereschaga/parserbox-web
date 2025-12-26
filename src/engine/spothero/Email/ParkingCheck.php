<?php

namespace AwardWallet\Engine\spothero\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ParkingCheck extends \TAccountChecker
{
    public $mailFiles = "spothero/it-149221813.eml";

    public $lang = '';
    public static $dictionary = [
        'en' => [
            'confNumber'     => ['Rental ID #'],
            'checkIn'        => ['Enter After'],
            'statusPhrases'  => ['Your spot is'],
            'statusVariants' => ['reserved'],
            'feeNames'       => ['Service Fee'],
        ],
    ];

    private $detectors = [
        'en' => ['Open Parking Pass', 'Your spot is reserved. Open your parking pass'],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('ParkingCheck' . ucfirst($this->lang));

        $park = $email->add()->parking();

        $statuses = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[,\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/"));

        if (count(array_unique($statuses)) === 1) {
            $status = array_shift($statuses);
            $park->general()->status($status);
        }

        $mainText = $this->htmlToText($this->http->FindHTMLByXpath("//text()[{$this->starts($this->t('confNumber'))}]/ancestor::*[ not(self::span) and descendant::text()[{$this->starts($this->t('checkIn'))}] ][1]"));

        $spot = $this->re("/^[ ]*{$this->opt($this->t('Spot'))}[ ]*[:]+[ ]*(.{3,}?)[ ]*$/m", $mainText);
        $address = $this->re("/^[ ]*{$this->opt($this->t('Entrance Address'))}[ ]*[:]+[ ]*(.{3,}?)[ ]*$/m", $mainText);
        $park->place()
            ->location($spot)
            ->address($address)
        ;

        if (preg_match("/^[ ]*({$this->opt($this->t('confNumber'))})[ ]*[:]+[ ]*([-A-z\d]{5,})[ ]*$/m", $mainText, $m)) {
            $park->general()->confirmation($m[2], $m[1]);
        }

        $plate = $this->re("/^[ ]*{$this->opt($this->t('License Plate'))}[ ]*[:]+[ ]*([-A-z\d ]{4,30}?)(?:[ ]+{$this->opt($this->t('Edit'))})?[ ]*$/im", $mainText);
        $checkIn = $this->re("/^[ ]*{$this->opt($this->t('checkIn'))}[ ]*[:]+[ ]*(.*\d.*?)[ ]*$/m", $mainText);
        $checkOut = $this->re("/^[ ]*{$this->opt($this->t('Exit Before'))}[ ]*[:]+[ ]*(.*\d.*?)[ ]*$/m", $mainText);
        $park->booked()
            ->plate($plate, false, true)
            ->start2($checkIn)
            ->end2($checkOut)
        ;

        $totalPrice = $this->re("/^[ ]*{$this->opt($this->t('Amount Charged'))}[ ]*[:]+[ ]*(.*\d.*?)[ *]*$/m", $mainText);

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // $15.75
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $park->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $baseFare = $this->re("/^[ ]*{$this->opt($this->t('Subtotal'))}[ ]*[:]+[ ]*(.*\d.*?)[ *]*$/m", $mainText);

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $baseFare, $m)) {
                $park->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            preg_match_all("/^[ ]*(?<name>{$this->opt($this->t('feeNames'))})[ ]*[:]+[ ]*(?<charge>.*\d.*?)[ *]*$/m", $mainText, $feeMatches, PREG_SET_ORDER);

            foreach ($feeMatches as $feeM) {
                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $feeM['charge'], $m)) {
                    $park->price()->fee($feeM['name'], PriceHelper::parse($m['amount'], $currencyCode));
                }
            }
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".spothero.com/") or contains(@href,"track.spothero.com")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match('/SpotHero Parking Confirmation #\s*[-A-z\d]{5,} - Check Your Parking Pass/i', $headers['subject']) > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@spothero.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
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
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['checkIn'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['checkIn'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
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
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
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

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (!empty($m[$c])) {
            return $m[$c];
        }

        return null;
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
