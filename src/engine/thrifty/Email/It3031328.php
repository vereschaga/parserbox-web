<?php

namespace AwardWallet\Engine\thrifty\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class It3031328 extends \TAccountChecker
{
    public $mailFiles = "thrifty/it-3031328.eml";

    private $subjects = [
        'en' => ['Thrifty reservation'],
    ];

    private $langDetectors = [
        'en' => ['Return to Location:'],
    ];

    private $lang = '';

    private static $dict = [
        'en' => [
            'Pick up from Location:' => ['Pick up from Location:', 'Location:'],
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Thrifty Reservations') !== false
            || stripos($from, '@thrifty.co.uk') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Thrifty') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Thrifty Car and Van Rental") or contains(.,"@thrifty.co.uk") or contains(.,"www.thrifty.co.uk")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.thrifty.co")]')->length === 0;

        if ($condition1 && $condition2) {
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
        $email->setType('It3031328' . ucfirst($this->lang));

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
        $patterns = [
            'phone'         => '[+)(\d][-.\s\d)(]{5,}[\d)(]', // +377 (93) 15 48 52    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $r = $email->add()->rental();

        // confirmation number
        $confirmationNumberTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Thrifty reservation'))}]");
        $confirmationNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Thrifty reservation'))}]/following::text()[normalize-space(.)][1]", null, true, '/^([A-Z\d]{5,})$/');
        $r->general()->confirmation($confirmationNumber, preg_replace('/\s*:\s*$/', '', $confirmationNumberTitle));

        // traveller
        $traveller = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Reservation Details'))}]/following::text()[normalize-space(.)][1]", null, true, "/^{$patterns['travellerName']}$/u");
        $r->general()->traveller($traveller);

        $xpathFragmentReturn = "//text()[{$this->starts($this->t('Return to Location:'))}]";

        $patterns['dateTime'] = '/^(.{6,}?)\s*(\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)$/'; // 4:19PM    |    2:00 p.m.

        // pickUpDateTime
        $pickUpDateTime = $this->http->FindSingleNode($xpathFragmentReturn . "/preceding::text()[{$this->starts($this->t('Pickup:'))}]", null, true, "/{$this->opt($this->t('Pickup:'))}\s*(.+)/");

        if (preg_match($patterns['dateTime'], $pickUpDateTime, $m)) {
            $pickUpDate = $this->normalizeDate($m[1]);

            if ($pickUpDate) {
                $r->pickup()->date(strtotime($pickUpDate . ' ' . $m[2]));
            }
        }

        // dropOffDateTime
        $dropOffDateTime = $this->http->FindSingleNode($xpathFragmentReturn . "/preceding::text()[{$this->starts($this->t('Return:'))}]", null, true, "/{$this->opt($this->t('Return:'))}\s*(.+)/");

        if (preg_match($patterns['dateTime'], $dropOffDateTime, $m)) {
            $dropOffDate = $this->normalizeDate($m[1]);

            if ($dropOffDate) {
                $r->dropoff()->date(strtotime($dropOffDate . ' ' . $m[2]));
            }
        }

        // pickUpPhone
        $pickUpPhone = $this->http->FindSingleNode($xpathFragmentReturn . "/preceding::text()[{$this->starts($this->t('Tel:'))}][ ./preceding::text()[{$this->starts($this->t('Pick up from Location:'))}] ]", null, true, "/{$this->opt($this->t('Tel:'))}\s*({$patterns['phone']})\b/");
        $r->pickup()->phone($pickUpPhone);

        // dropOffPhone
        $dropOffPhone = $this->http->FindSingleNode($xpathFragmentReturn . "/following::text()[{$this->starts($this->t('Tel:'))}][ ./following::text()[{$this->starts($this->t('Car Group:'))}] ]", null, true, "/{$this->opt($this->t('Tel:'))}\s*({$patterns['phone']})\b/");
        $r->dropoff()->phone($dropOffPhone);

        // pickUpFax
        $pickUpFax = $this->http->FindSingleNode($xpathFragmentReturn . "/preceding::text()[{$this->contains($this->t('Fax:'))}][ ./preceding::text()[{$this->starts($this->t('Pick up from Location:'))}] ]", null, true, "/{$this->opt($this->t('Fax:'))}\s*({$patterns['phone']})\b/");
        $r->pickup()->fax($pickUpFax, false, true);

        // dropOffFax
        $dropOffFax = $this->http->FindSingleNode($xpathFragmentReturn . "/following::text()[{$this->contains($this->t('Fax:'))}][ ./following::text()[{$this->starts($this->t('Car Group:'))}] ]", null, true, "/{$this->opt($this->t('Fax:'))}\s*({$patterns['phone']})\b/");
        $r->dropoff()->fax($dropOffFax, false, true);

        $xpathFragmentPickup = $xpathFragmentReturn . "/preceding::text()[{$this->starts($this->t('Pick up from Location:'))}]";

        // pickUpLocation
        $pickUpLocation = $this->http->FindSingleNode($xpathFragmentPickup, null, true, "/{$this->opt($this->t('Pick up from Location:'))}\s*(.+)/") ?? '';
        $pickUpAddress = $this->http->FindSingleNode($xpathFragmentPickup . '/following::text()[normalize-space(.)][1][not(contains(.,":"))]');

        if ($pickUpAddress) {
            $pickUpLocation .= ', ' . $pickUpAddress;
        }
        $r->pickup()->location($pickUpLocation);

        // dropOffLocation
        $dropOffLocation = $this->http->FindSingleNode($xpathFragmentReturn, null, true, "/{$this->opt($this->t('Return to Location:'))}\s*(.+)/") ?? '';
        $dropOffAddress = $this->http->FindSingleNode($xpathFragmentReturn . '/following::text()[normalize-space(.)][1][not(contains(.,":"))]');

        if ($dropOffAddress) {
            $dropOffLocation .= ', ' . $dropOffAddress;
        }
        $r->dropoff()->location($dropOffLocation);

        // carType
        $carType = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Car Group:'))}]", null, true, "/{$this->opt($this->t('Car Group:'))}\s*(.+)/");
        $r->car()->type($carType);

        // p.total
        // p.currencyCode
        $totalCost = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total Cost:'))}]", null, true, "/{$this->opt($this->t('Total Cost:'))}\s*(.+)/");

        if (
            preg_match('/^(?<amount>\d[,.\'\d]*)\s*(?<currency>[A-Z]{3})\b/', $totalCost, $matches)
            || preg_match('/^(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\'\d]*)/', $totalCost, $matches)
        ) {
            // 57.69 GBP    |    GBP337.93
            $r->price()
                ->total($this->normalizeAmount($matches['amount']))
                ->currency($matches['currency'])
            ;
        }
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @return string|bool
     */
    private function normalizeDate(string $string)
    {
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $string, $matches)) {
            // 21/12/2018
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
