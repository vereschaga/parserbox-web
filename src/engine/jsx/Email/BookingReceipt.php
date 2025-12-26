<?php

namespace AwardWallet\Engine\jsx\Email;

use AwardWallet\Schema\Parser\Email\Email;

class BookingReceipt extends \TAccountChecker
{
    public $mailFiles = "jsx/it-61299655.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber' => ['Booking Confirmation:', 'Booking Confirmation :'],
            'flightNo'   => ['Flight no.'],
        ],
    ];

    private $subjects = [
        'en' => ['Booking Receipt Email'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@jetsuitex.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
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
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".jetsuitex.com/") or contains(@href,"www.jetsuitex.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"JetSuiteX Contact Information") or contains(normalize-space(),"JetSuiteX. All Rights Reserved") or contains(normalize-space(),"JSX. All Rights Reserved") or contains(.,"@jetsuitex.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseFlight($email);
        $email->setType('BookingReceipt' . ucfirst($this->lang));

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

    private function parseFlight(Email $email): void
    {
        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]");

        if (preg_match("/^({$this->opt($this->t('confNumber'))})[:\s]*([A-Z\d]{5,})$/", $confirmation, $m)) {
            $f->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        $cancellation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('your reservation has been successfully cancelled'))}]");

        if (!empty($cancellation)) {
            $f->general()
                ->cancelled();
        }

        $segments = $this->http->XPath->query("//tr[ *[1][{$this->eq($this->t('flightNo'))}] and *[3][{$this->eq($this->t('Arriving'))}] ]/following-sibling::tr[ *[4] ]");

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $flightHtml = $this->http->FindHTMLByXpath('*[1]', null, $segment);
            $flight = $this->htmlToText($flightHtml);

            if (preg_match('/^\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])[-\s]*(?<number>\d+)\s*$/', $flight, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }

            /*
                Coachella Valley Thermal, CA (TRM)
                7:30PM    April 15, 2019
            */
            $pattern = "/^\s*"
                . "(?<name>.+?)[ ]*\([ ]*(?<code>[A-Z]{3})[ ]*\)[ ]*\n+"
                . "[ ]*(?<time>\d{1,2}[:]+\d{2}(?:[ ]*[AaPp][Mm])?)[ ]+(?<date>.{6,}?)"
                . "\s*$/";

            $departingHtml = $this->http->FindHTMLByXpath('*[2]', null, $segment);
            $departing = $this->htmlToText($departingHtml);

            if (preg_match($pattern, $departing, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date2($m['date'] . ' ' . $m['time']);
            }

            $arrivalHtml = $this->http->FindHTMLByXpath('*[3]', null, $segment);
            $arrival = $this->htmlToText($arrivalHtml);

            if (preg_match($pattern, $arrival, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date2($m['date'] . ' ' . $m['time']);
            }

            $durationHtml = $this->http->FindHTMLByXpath('*[4]', null, $segment);
            $duration = $this->htmlToText($durationHtml);

            if (preg_match("/^\s*(\d{1,3}.+?)[ ]*(?:\n|$)/", $duration, $m)) {
                $s->extra()->duration($m[1]);
            }
        }

        $travellers = [];
        $passengerCells = $this->http->XPath->query("//tr[ *[1][{$this->eq($this->t('Passengers'))}] and *[2] ]/following-sibling::tr[ *[2] ]/*[1]");

        foreach ($passengerCells as $pCell) {
            $passengerHtml = $this->http->FindHTMLByXpath('.', null, $pCell);
            $passengerValue = $this->htmlToText($passengerHtml);

            if (preg_match("/^\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[ ]*(?:\n|$)/", $passengerValue, $m)) {
                $travellers[] = $m[1];
            }
        }

        if (count($travellers)) {
            $f->general()->travellers(array_unique($travellers));
        }

        // TODO: need more examples for full parsing seats
        if (count($f->getSegments()) === 1 && count($f->getTravellers()) === 1) {
            // it-61299655.eml
            $seat = $this->http->FindSingleNode("//tr[ *[1][{$this->eq($this->t('Passengers'))}] and *[3][{$this->eq($this->t('Seat'))}] ]/following-sibling::tr/*[3]", null, true, "/^\d+[ ]*[A-Z]$/");
            $f->getSegments()[0]->extra()->seat($seat ? str_replace(' ', '', $seat) : null, false, true);
        }

        $xpathCost = "//tr[ *[3][{$this->eq($this->t('Cost breakdown'))}] ]/following::tr[count(*)=3 and normalize-space()][1]/*[3]";

        $totalPrice = $this->http->FindSingleNode($xpathCost . "/descendant::td[{$this->eq($this->t('Total:'))}]/following-sibling::td[normalize-space()][1]");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $m)) {
            // $ 319.80
            $f->price()
                ->currency($m['currency'])
                ->total($this->normalizeAmount($m['amount']));

            $baseFare = $this->http->FindSingleNode($xpathCost . "/descendant::td[{$this->eq($this->t('Airfare:'))}]/following-sibling::td[normalize-space()][1]");

            if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $baseFare, $matches)) {
                $f->price()->cost($this->normalizeAmount($matches['amount']));
            }

            $tax = $this->http->FindSingleNode($xpathCost . "/descendant::td[{$this->eq($this->t('Tax:'))}]/following-sibling::td[normalize-space()][1]");

            if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $tax, $matches)) {
                $f->price()->tax($this->normalizeAmount($matches['amount']));
            }
        }
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['flightNo'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['confNumber'])}]")->length > 0
                || $this->http->XPath->query("//node()[{$this->contains($phrases['flightNo'])}]")->length > 0
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
