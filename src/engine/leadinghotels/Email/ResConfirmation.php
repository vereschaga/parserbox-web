<?php

namespace AwardWallet\Engine\leadinghotels\Email;

class ResConfirmation extends \TAccountChecker
{
    public $mailFiles = "leadinghotels/it-6595115.eml";

    public $reFrom = "confirmation@lhw.com";
    public $reBody = [
        'en' => ['Confirmation Number', 'RESERVATION CONFIRMATION'],
    ];
    public $reSubject = [
        'en' => 'Reservation Confirmation',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->assignLang();

        $its = $this->parseEmail();
        $a = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => end($a) . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(.),'Leading Hotels')] | //img[contains(@src,'lhw.com')] | //a[contains(@href,'lhw.com')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail()
    {
        $patterns = [
            'confNumber' => '[A-Z\d]{5,}', // 11986371476    |    M5GPQK
            'phone'      => '[+)(\d][-+.\s\d)(]{5,}[\d)(]', // (+39-55) 2645181    |    713.680.2992
        ];

        $it = ['Kind' => 'R'];

        $xpathFragment1 = 'following-sibling::tr[normalize-space(.)]';

        $it['ConfirmationNumber'] = $this->http->FindSingleNode("//text()[{$this->eq('Confirmation Number:')}]/ancestor::tr[ ./{$xpathFragment1} ][1]/{$xpathFragment1}[1]/td[normalize-space(.)][2]", null, true, '/^(' . $patterns['confNumber'] . ')$/');
        $it['GuestNames'] = [$this->http->FindSingleNode("//text()[{$this->eq('Guest:')}]/ancestor::tr[ ./{$xpathFragment1} ][1]/{$xpathFragment1}[1]/td[normalize-space(.)][1]")];
        $leadersClubID = $this->http->FindSingleNode("//text()[{$this->eq('Leaders Club ID:')}]/ancestor::tr[ ./{$xpathFragment1} ][1]/{$xpathFragment1}[1]/td[normalize-space(.)][3]", null, true, '/^([A-Z\d]*\d{5,}[A-Z\d]*)$/');

        if ($leadersClubID) {
            $it['AccountNumbers'] = [$leadersClubID];
        }
        $it['HotelName'] = $this->http->FindSingleNode("//text()[{$this->eq('Hotel:')}]/ancestor::tr[ ./{$xpathFragment1} ][1]/{$xpathFragment1}[1]/td[normalize-space(.)][1]");

        $xpathFragment2 = "//text()[{$this->starts('RESERVATION CONFIRMATION')}]/preceding::td[ not(.//td) and {$this->starts('P:')} and {$this->contains(' F:')} ][1]";

        $it['Address'] = $this->http->FindSingleNode($xpathFragment2 . "/preceding-sibling::td[normalize-space(.)][last()]");
        $phoneFaxText = $this->http->FindSingleNode($xpathFragment2);

        if (preg_match("/P\s*:\s*({$patterns['phone']})\s*F\s*:\s*({$patterns['phone']})/", $phoneFaxText, $m)) {
            $it['Phone'] = $m[1];
            $it['Fax'] = $m[2];
        }

        $it['CheckInDate'] = strtotime($this->http->FindSingleNode("//text()[{$this->eq('Arrival Date:')}]/ancestor::tr[ ./{$xpathFragment1} ][1]/{$xpathFragment1}[1]/td[normalize-space(.)][1]"));
        $it['CheckOutDate'] = strtotime($this->http->FindSingleNode("//text()[{$this->eq('Departure Date:')}]/ancestor::tr[ ./{$xpathFragment1} ][1]/{$xpathFragment1}[1]/td[normalize-space(.)][1]"));
        $it['ReservationDate'] = strtotime($this->http->FindSingleNode("//text()[{$this->eq('Booking Date:')}]/ancestor::tr[ ./{$xpathFragment1} ][1]/{$xpathFragment1}[1]/td[normalize-space(.)][1]"));
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts('Total Cost')}]/ancestor::tr[ ./{$xpathFragment1} ][1]/{$xpathFragment1}[1]/td[normalize-space(.)][1]"));

        if (!empty($tot['Total'])) {
            $it['Total'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
            $cost = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq('Total Room Rate:')}]/ancestor::tr[ ./{$xpathFragment1} ][1]/{$xpathFragment1}[1]/td[normalize-space(.)][2]"));

            if (!empty($cost['Total']) && $tot['Currency'] === $cost['Currency']) {
                $it['Cost'] = $cost['Total'];
            }
            $taxes = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq('Total Taxes:')}]/ancestor::tr[ ./{$xpathFragment1} ][1]/{$xpathFragment1}[1]/td[normalize-space(.)][3]"));

            if (!empty($taxes['Total']) && $tot['Currency'] === $taxes['Currency']) {
                $it['Taxes'] = $taxes['Total'];
            }
        }
        $it['Rate'] = $this->http->FindSingleNode("//text()[{$this->starts('Average Daily Room(s) Cost')}]/ancestor::tr[ ./{$xpathFragment1} ][1]/{$xpathFragment1}[1]/td[normalize-space(.)][1]");
        $it['Guests'] = $this->http->FindSingleNode("//text()[{$this->eq('Number of Adults:')}]/ancestor::tr[ ./{$xpathFragment1} ][1]/{$xpathFragment1}[1]/td[normalize-space(.)][1]", null, true, '/^(\d{1,3})$/');
        $it['Kids'] = $this->http->FindSingleNode("//text()[{$this->eq('Number of Children:')}]/ancestor::tr[ ./{$xpathFragment1} ][1]/{$xpathFragment1}[1]/td[normalize-space(.)][2]", null, true, '/^(\d{1,3})$/');
        $it['Rooms'] = $this->http->FindSingleNode("//text()[{$this->eq('Number of Rooms(s):')}]/ancestor::tr[ ./{$xpathFragment1} ][1]/{$xpathFragment1}[1]/td[normalize-space(.)][3]", null, true, '/^(\d{1,3})$/');
        $it['RateType'] = $this->http->FindSingleNode("//text()[{$this->eq('Rate Plan:')}]/ancestor::tr[ ./{$xpathFragment1} ][1]/{$xpathFragment1}[1]/td[normalize-space(.)][1]");
        $it['CancellationPolicy'] = $this->http->FindSingleNode("//text()[{$this->eq('Cancellation Policy')}]/following::tr[not(.//tr) and normalize-space(.)][1]/descendant::span[normalize-space(.)][1]");

        return [$it];
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        if (isset($this->reBody)) {
            $body = $this->http->Response['body'];

            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = preg_replace("#,(\d{3})$#", '$1', $m['t']);

            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }
}
