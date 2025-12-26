<?php

namespace AwardWallet\Engine\htonight\Email;

class BReceipt extends \TAccountChecker
{
    public $mailFiles = "htonight/it-1.eml, htonight/it-1693157.eml, htonight/it-1699647.eml, htonight/it-1975554.eml, htonight/it-2701584.eml, htonight/it-6718678.eml, htonight/it-8181665.eml";

    public $reFrom = "help@hoteltonight.com";
    public $reFromH = "HotelTonight";
    public $reBody = [
        'en' => ['Thanks for booking with HotelTonight', 'Booking Confirmation'],
    ];
    public $reSubject = [
        'HotelTonight Booking Receipt',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->AssignLang();

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'BReceipt' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'hoteltonight')] | //img[contains(@src,'hoteltonight')]")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFromH) !== false && isset($this->reSubject)) {
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
        $it = ['Kind' => 'R'];
        $it['ReservationDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Booked')]", null, true, "#Booked:?\s*(.+)#")));
        $it['ConfirmationNumber'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'HotelTonight booking ID')]", null, true, "#:\s*([A-Z\d]+)#");

        $it['HotelName'] = $this->http->FindSingleNode("(//text()[starts-with(normalize-space(.),'To check')]/ancestor::td[1]//text()[normalize-space(.)])[1]");
        $it['Address'] = implode(" ", $this->http->FindNodes("//text()[normalize-space(.)=\"{$it['HotelName']}\"]/ancestor::p[1]/descendant::text()[normalize-space(.)][position()>1]"));
        $it['Phone'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Or call the hotel directly')]", null, true, "#:\s*(.+)#");

        if (empty($it['Phone'])) {
            $it['Phone'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),\"" . trim($it['HotelName']) . ":\")]", null, true, "#:\s*(.+)#");
        }
        $it['CheckInDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Check-in')]/following::text()[normalize-space(.)][1]")));
        $it['CheckOutDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Check-out')]/following::text()[normalize-space(.)][1]")));
        $it['Rooms'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Check-out')]/following::text()[normalize-space(.)][2]", null, true, "#(\d+)\s*room#");
        $it['RoomType'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Room type')]/following::text()[normalize-space(.)][1]");
        $it['GuestNames'][] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Guest name')]/following::text()[normalize-space(.)][1]");
        $it['Guests'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Check-out')]/following::text()[contains(.,'adult')][1]", null, true, "#(\d+)\s*adults#");

        if (!$it['Guests']) {
            $it['Guests'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'This reservation is for a room that fits')]", null, true, "#(\d+)\s*guests#");
        }

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("(//text()[normalize-space(.)='Room']/ancestor::div[1]/following-sibling::div[1]//text()[normalize-space(.)])[1]"));

        if (!empty($tot['Total'])) {
            $it['Cost'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("(//text()[normalize-space(.)='Room']/ancestor::div[1]/following-sibling::div[1]//text()[normalize-space(.)])[2]"));

        if (!empty($tot['Total'])) {
            $it['Taxes'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("(//text()[normalize-space(.)='Room']/ancestor::div[1]/following-sibling::div[1]//text()[normalize-space(.)])[3]"));

        if (!empty($tot['Total'])) {
            $it['Total'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $it['CancellationPolicy'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'This is a non-refundable')]", null, true, "#(.+?)\.#");

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*(\w+)\s+(\d+),\s+(\d+)\s*$#',
            //8:36pm May 25, 2017 EDT
            //12:00 PM - May 26, 2017
            '#^\s*(\d+:\d+(?:\s*[ap]m)?)[\s\-]+(\w+)\s+(\d+),\s+(\d{4}).*$#i',
        ];
        $out = [
            '$2 $1 $3',
            '$3 $2 $4, $1',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("$", "USD", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = str_replace(" ", "", $m['t']);
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
}
