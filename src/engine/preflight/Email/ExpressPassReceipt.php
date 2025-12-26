<?php

namespace AwardWallet\Engine\preflight\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ExpressPassReceipt extends \TAccountChecker
{
    public $mailFiles = "preflight/it-70659928.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Entry:'    => ['Entry:'],
            'Location:' => ['Location:'],
        ],
    ];

    private $subjects = [
        'en' => ['Express Pass Receipt'],
    ];

    private $detectors = [
        'en' => ['EXPRESS PASS RECEIPT', 'This is an Express Pass receipt from'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@preflightparking.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], 'PreFlight Airport Parking') === false
        ) {
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
            && $this->http->XPath->query('//a[contains(@href,".preflightairportparking.com/") or contains(@href,"www.preflightairportparking.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for parking with PreFlight") or contains(normalize-space(),"PreFlight LLC. All Right Reserved")]')->length === 0
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
        $email->setType('ExpressPassReceipt' . ucfirst($this->lang));

        $this->parseParking($email);

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

    private function parseParking(Email $email): void
    {
        $xpathBold = '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])';

        $p = $email->add()->parking();

        $mainHtml = $this->http->FindHTMLByXpath("//text()[ {$this->starts($this->t('Entry:'))} and ancestor::*[{$xpathBold}] ]/ancestor::tr[ descendant::text()[ {$this->starts($this->t('Location:'))} and ancestor::*[{$xpathBold}] ] ][1]");
        $mainText = $this->htmlToText($mainHtml);

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/^{$this->opt($this->t('Dear'))}[ ]+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[ ]*(?:[,;:!?]|$)/u");

        if (empty($traveller)
            && preg_match("/^[ ]*{$this->opt($this->t('Dear'))}[ ]+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[ ]*(?:[,;:!?]|$)/m", $mainText, $m)
        ) {
            $traveller = $m[1];
        }
        $p->general()->traveller($traveller);

        if (preg_match("/^[ ]*{$this->opt($this->t('Membership Number'))}[ ]*:[ ]*([-A-Z\d]{5,})[ ]*$/m", $mainText, $m)) {
            $p->program()->account($m[1], false);
        }

        if (preg_match("/^[ ]*({$this->opt($this->t('Transaction Number'))})[ ]*:[ ]*([-A-Z\d]{5,})[ ]*$/m", $mainText, $m)) {
            $p->general()->confirmation($m[2], $m[1]);
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('Entry'))}[ ]*:[ ]*(.{6,}?)[ ]*$/m", $mainText, $m)) {
            $p->booked()->start2($m[1]);
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('Exit'))}[ ]*:[ ]*(.{6,}?)[ ]*$/m", $mainText, $m)) {
            $p->booked()->end2($m[1]);
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('Amount'))}[ ]*:[ ]*(.+?)[ ]*$/m", $mainText, $matches)
            && preg_match("/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/m", $matches[1], $m)
        ) {
            // $ 66.3
            $p->price()
                ->currency($m['currency'])
                ->total($this->normalizeAmount($m['amount']));
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('Location'))}[ ]*:[ ]*(.{3,}?)[ ]*$/m", $mainText, $m)) {
            $p->place()->location($m[1]);
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('Facility Phone'))}[ ]*:[ ]*([+(\d][-. \d)(]{5,}[\d)])[ ]*$/m", $mainText, $m)) {
            $p->place()->phone($m[1]);
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
            if (!is_string($lang) || empty($phrases['Entry:']) || empty($phrases['Location:'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Entry:'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Location:'])}]")->length > 0
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

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
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
