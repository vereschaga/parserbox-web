<?php

namespace AwardWallet\Engine\otelcom\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourBooking extends \TAccountChecker
{
    public $mailFiles = "otelcom/it-29094703.eml";
    private $langDetectors = [
        'de' => ['IHRE BUCHUNG'],
    ];
    private $lang = '';
    private static $dict = [
        'de' => [],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@otel.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $patterns = [
            'de' => ['/Ihre Buchung bei Otel\b/i'],
        ];

        foreach ($patterns as $variants) {
            foreach ($variants as $variant) {
                if (preg_match($variant, $headers['subject']) > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.otel.com") or contains(@href,"//www2.otel.com")]')->length === 0;
        $condition4 = self::detectEmailFromProvider($parser->getHeader('from')) !== true;

        if ($condition2 && $condition4) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->notice("Can't determine a language!");

            return $email;
        }
        $this->parseEmail($email);
        $email->setType('YourBooking' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $email->ota();

        $h = $email->add()->hotel();

        // travellers
        $travellerName = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Vielen Dank'))}]", null, true, "/{$this->opt($this->t('Vielen Dank'))}[,\s]+([[:alpha:]][-.\'[:alpha:]\s]*[[:alpha:]])[.!]/u");
        $h->general()->traveller($travellerName);

        $xpathFragment0 = "//tr[{$this->eq($this->t('IHR HOTEL'))}]/following::table[normalize-space(.)][1]/descendant::img[contains(@src,'/star.') or normalize-space(@alt)='*']";

        // hotelName
        // address
        $hotelName = $this->http->FindSingleNode($xpathFragment0 . "/preceding::text()[normalize-space(.)][1]/ancestor::*[1]");
        $address = $this->http->FindSingleNode($xpathFragment0 . "/following::text()[normalize-space(.)][1]/ancestor::*[1]");
        $h->hotel()
            ->name($hotelName)
            ->address($address)
        ;

        $patterns['phone'] = '[+)(\d][-.\s\d)(]{5,}[\d)(]'; // +377 (93) 15 48 52    |    713.680.2992

        $xpathFragmentNextCell = '/ancestor::td[ ./following-sibling::*[normalize-space(.)] ][1]/following-sibling::td[normalize-space(.)][last()]';

        // phone
        // fax
        $phone = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Telefon'))}]" . $xpathFragmentNextCell, null, true, "/^({$patterns['phone']})$/");
        $fax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Fax'))}]" . $xpathFragmentNextCell, null, true, "/^({$patterns['phone']})$/");
        $h->hotel()
            ->phone($phone)
            ->fax($fax)
        ;

        // confirmation number
        $confirmationNumberTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservierungsnummer'))}]");
        $confirmationNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservierungsnummer'))}]" . $xpathFragmentNextCell, null, true, '/^([A-Z\d]{5,})$/');
        $h->general()->confirmation($confirmationNumber, preg_replace('/\s*:\s*$/', '', $confirmationNumberTitle));

        // depDate
        $dateDep = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Anreise:'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Anreise:'))}\s*(.{6,})/");

        if ($dateDep) {
            $dateDep = $this->normalizeDate($dateDep);
            $h->booked()->checkIn2($dateDep);
        }

        // arrDate
        $dateArr = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Abreise:'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Abreise:'))}\s*(.{6,})/");

        if ($dateArr) {
            $dateArr = $this->normalizeDate($dateArr);
            $h->booked()->checkOut2($dateArr);
        }

        // guestCount
        $guests = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reisende'))}]" . $xpathFragmentNextCell);

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Erwachsener'))}/", $guests, $m)) {
            $h->booked()->guests($m[1]);
        }

        $r = $h->addRoom();

        // r.type
        $roomType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Zimmer'))}]" . $xpathFragmentNextCell);
        $r->setType($roomType);

        // p.total
        // p.currencyCode
        $payment = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Gesamtpreis Ihrer Buchung'))}]" . $xpathFragmentNextCell);

        if (preg_match('/^(?<amount>\d[,.\'\d]*)\s*(?<currency>[A-Z]{3})\b/', $payment, $matches)) {
            // 565.28 CHF
            $h->price()
                ->total($this->normalizeAmount($matches['amount']))
                ->currency($matches['currency']);
        }

        // cancellation
        $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Stornierungsbedingungen'))}]/ancestor::tr[ ./following-sibling::tr[normalize-space(.)] ][1]/following-sibling::tr[normalize-space(.)][1]");
        $h->general()->cancellation($cancellation);

        // deadline
        if (
            preg_match('/You can cancel your booking free of charge up to\s*(.{6,}?\d{1,2}:\d{2}.*?)[,.!;]/i', $cancellation, $m) // de
        ) {
            $h->booked()->deadline2($m[1]);
        }
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @return string|bool
     */
    private function normalizeDate(string $string)
    {
        if (preg_match('/^[^\d\W]{2,}[,\s]+(\d{1,2})\.(\d{1,2})\.(\d{4})$/u', $string, $matches)) {
            // Mi, 28.11.2018
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        }

        if (isset($day) && isset($month) && isset($year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return false;
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{2})$/', '.$1', $s);    // 18800,00   ->  18800.00

        return $s;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ")='" . $s . "'"; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
