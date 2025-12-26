<?php

namespace AwardWallet\Engine\rcg\Email;

use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "rcg/it-46398691.eml";

    public $lang = '';

    public static $dictionary = [
        'sv' => [
            'Bokningsnummer:' => ['Bokningsnummer:', 'Bokningsnummer :'],
            'Avresa:'         => ['Avresa:', 'Avresa :'],
        ],
    ];

    private $subjects = [
        'sv' => ['Bokningsbekräftelse från'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@rcg.se') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'RCG') === false) {
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
            && $this->http->XPath->query('//a[contains(@href,".rcg.se/") or contains(@href,"www.rcg.se")]')->length === 0
            && $this->http->XPath->query('//node()[contains(.,"@rcg.se")]')->length === 0
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
        $email->setType('BookingConfirmation' . ucfirst($this->lang));

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

    private function parseFlight(Email $email)
    {
        $taConfirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Bokningsnummer:'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($taConfirmation) {
            $taConfirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Bokningsnummer:'))}]", null, true, '/^(.+?)[\s:]*$/');
            $email->ota()->confirmation($taConfirmation, $taConfirmationTitle);
        }

        $f = $email->add()->flight();
        $f->general()->noConfirmation();

        $segments = $this->http->XPath->query("//tr[ descendant::text()[normalize-space()][1][{$this->eq($this->t('Avresa:'))}] and following-sibling::tr[normalize-space()][1][{$this->contains($this->t('Destination:'))}] ]");

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            // Seoul (ICN-Incheon Intl.)    |    Köpenhamn, Danmark (CPH)
            $patterns['airport'] = "(?<city>.{3,}?)[ ]*\([ ]*(?<code>[A-Z]{3})[ ]*(?:-[ ]*(?<name>.{2,}))?[ ]*\)";

            $airportDep = implode(' ', $this->http->FindNodes('descendant::text()[normalize-space()]', $segment));

            if (preg_match("/^\s*(?:{$this->opt($this->t('Avresa:'))})?\s*{$patterns['airport']}$/", $airportDep, $m)) {
                $s->departure()
                    ->name(empty($m['name']) ? $m['city'] : $m['name'] . ', ' . $m['city'])
                    ->code($m['code']);
            }

            $airportArr = implode(' ', $this->http->FindNodes("following-sibling::tr[{$this->contains($this->t('Destination:'))}][1]/descendant::text()[normalize-space()]", $segment));

            if (preg_match("/^\s*(?:{$this->opt($this->t('Destination:'))})?\s*{$patterns['airport']}$/", $airportArr, $m)) {
                $s->arrival()
                    ->name(empty($m['name']) ? $m['city'] : $m['name'] . ', ' . $m['city'])
                    ->code($m['code']);
            }

            // 4:19PM    |    2:00 p.m.    |    3pm
            $patterns['time'] = '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?';

            $dateDep = implode(' ', $this->http->FindNodes("following-sibling::tr[{$this->contains($this->t('Avresedatum:'))}][1]/descendant::text()[normalize-space()]", $segment));

            if (preg_match("/^\s*(?:{$this->opt($this->t('Avresedatum:'))})?\s*(?<date>.{6,}?)\s*{$this->opt($this->t('Tid:'))}\s*(?<time>{$patterns['time']})$/", $dateDep, $m)) {
                $s->departure()->date2($m['date'] . ' ' . $m['time']);
            }

            $dateArr = implode(' ', $this->http->FindNodes("following-sibling::tr[{$this->contains($this->t('Ankomst:'))}][1]/descendant::text()[normalize-space()]", $segment));

            if (preg_match("/^\s*(?:{$this->opt($this->t('Ankomst:'))})?\s*(?<date>.{6,}?)\s*{$this->opt($this->t('Tid:'))}\s*(?<time>{$patterns['time']})$/", $dateArr, $m)) {
                $s->arrival()->date2($m['date'] . ' ' . $m['time']);
            }

            $flight = implode(' ', $this->http->FindNodes("following-sibling::tr[{$this->contains($this->t('Flyg:'))}][1]/descendant::text()[normalize-space()]", $segment));

            if (preg_match("/^\s*(?:{$this->opt($this->t('Flyg:'))})?.*\b(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<number>\d+)$/", $flight, $m)) {
                // Turkish Airlines TK1782
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }
        }

        $passengerNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Passagerare:'))}]/following::text()[{$this->starts($this->t('Passagerare #'), 'translate(normalize-space(),"0123456789","##########")')}]");

        foreach ($passengerNodes as $key => $pNode) {
            $passengerHtml = $this->http->FindHTMLByXpath('ancestor::*[count(descendant::text()[normalize-space()])>1][1]', null, $pNode);
            $passengerInfo = $this->htmlToText($passengerHtml);

            $firstName = preg_match("/{$this->opt($this->t('Passagerare'))}\s*" . ($key + 1) . "\s*{$this->opt($this->t('Namn:'))}\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[ ]*$/mu", $passengerInfo, $m) ? $m[1] : null;
            $lastName = preg_match("/{$this->opt($this->t('Passagerare'))}\s*" . ($key + 1) . "\s*.{2,}\s+{$this->opt($this->t('Efternamn:'))}\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[ ]*$/mu", $passengerInfo, $m) ? $m[1] : null;

            if ($firstName && $lastName) {
                $f->addTraveller($firstName . ' ' . $lastName, true);
            } elseif ($firstName) {
                $f->addTraveller($firstName, false);
            }
        }

        $xpathPrice = "//text()[{$this->eq($this->t('Totalpris:'))}]/ancestor::*[self::div or self::tr][1]";

        $totalPrice = $this->http->FindSingleNode($xpathPrice, null, true, "/{$this->opt($this->t('Totalpris:'))}\s*(.+)$/");

        if (preg_match('/^(?<amount>\d[,.\'\d]*) ?(?<currency>[A-Z]{3})\b/', $totalPrice, $m)) {
            // 4740 SEK
            $f->price()
                ->total($this->normalizeAmount($m['amount']))
                ->currency($m['currency']);

            $taxes = $this->http->FindSingleNode($xpathPrice . "/following-sibling::*[{$this->contains($this->t('varav flygskatter'))}][1]", null, true, "/{$this->opt($this->t('varav flygskatter'))}\s*([^)(]+)\)?$/");

            if (preg_match('/^(?<amount>\d[,.\'\d]*) ?' . preg_quote($m['currency'], '/') . '\b/', $taxes, $matches)) {
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
            if (!is_string($lang) || empty($phrases['Bokningsnummer:']) || empty($phrases['Avresa:'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Bokningsnummer:'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Avresa:'])}]")->length > 0
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
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

        return $s;
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = str_replace("\n", '', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
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
}
