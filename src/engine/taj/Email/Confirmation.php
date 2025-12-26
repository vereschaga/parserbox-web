<?php

namespace AwardWallet\Engine\taj\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "reservations@tajhotels.com";
    public $reBody = [
        'en'  => ['Your Booking Confirmation', 'Thank you for choosing Taj'],
        'en2' => ['Hotel Reservation', 'Thank you for your reservation'],
    ];
    public $reSubject = [
        '#Your.+?Confirmation\s+\d+#',
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
        $result = [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'Confirmation' . ucfirst($this->lang),
        ];

        if (!empty($prov = $this->getProvider($parser->getCleanFrom()))) {
            $result['providerCode'] = $prov;
        }

        return $result;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'tajhotels.com')] | //text()[contains(.,'Taj ')]")->length > 0
        || $this->http->XPath->query("//a[contains(@href,'@huttonhotel.com')] | //text()[contains(.,'Hutton ')]")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"])) {
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
        $cntProvs = 2;
        $cnt = $cntProvs * count(self::$dict);

        return $cnt;
    }

    private function nextText($field, $num = 1)
    {
        return $this->http->FindSingleNode("//text()[{$this->eq($field)}]/following::text()[normalize-space(.)!=''][$num]");
    }

    private function prevText($field, $num = 1)
    {
        return $this->http->FindSingleNode("//text()[{$this->eq($field)}]/preceding::text()[normalize-space(.)!=''][$num]");
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'R'];
        $it['ConfirmationNumber'] = $this->nextText($this->t("Reservation Number:"));
        $it['HotelName'] = $this->prevText("Address:");
        $it['Address'] = $this->nextText("Address:");
        $it['Phone'] = $this->nextText("Phone Number:");
        $it['GuestNames'][] = $this->nextText("First Name") . ' ' . $this->nextText("Last Name");
        $it['CheckInDate'] = strtotime($this->nextText("Arrival Date:"));
        $it['CheckOutDate'] = strtotime($this->nextText("Departure Date:"));
        $node = $this->nextText("Check-in Time:");

        if (preg_match("#^\d+:\d+(?:\s*[ap]m)?$#i", $node)) {
            $it['CheckInDate'] = strtotime($node, $it['CheckInDate']);
        }
        $node = $this->nextText("Check-out Time:");

        if (preg_match("#^\d+:\d+(?:\s*[ap]m)?$#i", $node)) {
            $it['CheckOutDate'] = strtotime($node, $it['CheckOutDate']);
        }
        $it['Guests'] = $this->nextText("Number of Adults:");
        $it['RateType'] = $this->nextText("Rate Description:");
        $it['RoomType'] = $this->nextText("Room Description:");
        $it['CancellationPolicy'] = $this->nextText("Cancellation policy:");
        $it['RoomTypeDescription'] = $this->nextText("Room Description:", 2);

        $tot = $this->getTotalCurrency($this->nextText("Room Price:"));

        if (!empty($tot['Total'])) {
            $it['Cost'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $tot = $this->getTotalCurrency($this->nextText("Taxes:"));

        if (!empty($tot['Total'])) {
            $it['Taxes'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $tot = $this->getTotalCurrency($this->nextText("Total"));

        if (!empty($tot['Total'])) {
            $it['Total'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }

        return [$it];
    }

    private function getProvider($from)
    {
        if ($this->http->XPath->query("//a[contains(@href,'tajhotels.com')] | //text()[contains(.,'Taj ')]")->length > 0) {
            return 'taj';
        }

        if ($this->http->XPath->query("//a[contains(@href,'@huttonhotel.com')] | //text()[contains(.,'Hutton ')]")->length > 0
            && stripos($from, $this->reFrom) === false
        ) {
            return 'leadinghotels';
        }

        return null;
    }

    public static function getEmailProviders()
    {
        return ['taj', 'leadinghotels'];
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
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $node = str_replace("₹", "INR", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }
}
