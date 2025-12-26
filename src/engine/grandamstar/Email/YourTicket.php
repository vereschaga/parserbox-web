<?php

namespace AwardWallet\Engine\grandamstar\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourTicket extends \TAccountChecker
{
    public $mailFiles = "grandamstar/it-352450427.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber' => ['Booking ID:', 'Booking ID :'],
            'dateTime'   => ['Date/Time:', 'Date/Time :'],
            'movie'      => ['Movie:', 'Movie :'],
        ],
    ];

    private $subjects = [
        'en' => ['here is your booking confirmation for'],
    ];

    private $detectors = [
        'en' => ['Ticket Purchase Details', 'Location Info'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@southerntickets.net') !== false;
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
            && $this->http->XPath->query('//a[contains(@href,".thegrandtheatre.com/") or contains(@href,"www.thegrandtheatre.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing The Grand")]')->length === 0
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
        $email->setType('YourTicket' . ucfirst($this->lang));

        $patterns = [
            'time' => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
        ];

        $ev = $email->add()->event();
        $ev->type()->event();

        $traveller = null;
        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Hello'))}]", null, "/^{$this->opt($this->t('Hello'))}[,\s]+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
        }
        $ev->general()->traveller($traveller);

        $confirmation = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('confNumber'))}]");

        if (preg_match("/^({$this->opt($this->t('confNumber'))})[: ]*([-_A-Z\d]{5,})$/", $confirmation, $m)) {
            $ev->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        $eventName = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('movie'))}]", null, true, "/^{$this->opt($this->t('movie'))}.{2,}$/");

        $dateTime = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('dateTime'))}]", null, true, "/^{$this->opt($this->t('dateTime'))}[: ]*(.*\d.*)$/");

        if (preg_match("/^(?<date>.{6,}?)\s+[-–]+\s+(?<time>{$patterns['time']})$/", $dateTime, $m)) {
            $ev->booked()->start(strtotime($m['time'], strtotime($m['date'])))->noEnd();
        }

        $seats = [];
        $auditorium = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('Auditorium:'))}]", null, true, "/^{$this->opt($this->t('Auditorium:'))}[: ]*(.+?)(?:\s*\(|$)/");

        $totalPrice = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('Order Total:'))}]", null, true, "/^{$this->opt($this->t('Order Total:'))}[: ]*(.*\d.*?)(?:\s*\(|$)/");

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // $36.03
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $ev->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $costCurrency = $costAmount = [];
            $seatsPrice = $this->htmlToText($this->http->FindHTMLByXpath("//tr[not(.//tr) and {$this->starts($this->t('Tickets:'))}]"));

            if (preg_match_all("/.+\(\s*(?<price>.*\d.*?)\s+-\s+(?<seats>[A-Z][-, A-Z\d]*\d)\s*\)$/m", $seatsPrice, $seatMatches, PREG_SET_ORDER)) {
                // 2 x Res Child ($20.00 - E4, E5)
                foreach ($seatMatches as $m) {
                    if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $m['price'], $m2)) {
                        $costCurrency[] = $m2['currency'];
                        $costAmount[] = PriceHelper::parse($m2['amount'], $currencyCode);
                    }
                    $seats = array_merge($seats, preg_split('/(?:\s*,\s*)+/', $m['seats']));
                }
            }

            if (count(array_unique($costCurrency)) === 1 && $costCurrency[0] === $matches['currency']) {
                $ev->price()->cost(array_sum($costAmount));
            }

            $feeRows = $this->http->XPath->query("//tr[not(.//tr) and {$this->starts($this->t('Booking Fee:'))}]");

            foreach ($feeRows as $feeRow) {
                if (preg_match("/^(?<name>{$this->opt($this->t('Booking Fee:'))})[: ]*(?<charge>.*\d.*?)(?:\s*\(|$)$/", $this->http->FindSingleNode(".", $feeRow), $m)) {
                    if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $m['charge'], $m2)) {
                        $ev->price()->fee(rtrim($m['name'], ': '), PriceHelper::parse($m2['amount'], $currencyCode));
                    }
                }
            }
        }

        if (count($seats) > 0) {
            if ($auditorium) {
                $seats = array_map(function (&$item) use ($auditorium) {
                    return $auditorium . ', ' . $item;
                }, $seats);
            }
            $ev->booked()->seats($seats);
        }

        $address = implode(', ', $this->http->FindNodes("//tr[ not(.//tr) and preceding::tr[not(.//tr) and normalize-space()][position()<4][{$this->contains($this->t('Location Info'))}] and following::tr[not(.//tr) and normalize-space()][position()<4][{$this->contains($this->t('Get Directions'))}] ]"));
        $ev->place()->name($eventName)->address($address);

        $cancellation = $this->http->FindSingleNode("//tr[ preceding-sibling::tr[{$this->eq($this->t('Refund Policy'))}] and following-sibling::tr[{$this->eq($this->t('Curfew Policy'))}] ]");
        $ev->general()->cancellation($cancellation);

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
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['dateTime'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['dateTime'])}]")->length > 0
            ) {
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
}
