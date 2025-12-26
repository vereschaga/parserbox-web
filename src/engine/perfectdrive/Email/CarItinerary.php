<?php

namespace AwardWallet\Engine\perfectdrive\Email;

class CarItinerary extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "reservations@rezserver.com";
    public $reBody = [
        'en' => ['Your Rental Car Reservation', 'Thank you for booking with Car Rental'],
    ];
    public $reSubject = [
        'Your Car Rental Car Itinerary',
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
            'emailType'  => end(explode('\\', __CLASS__)) . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'rezserver.com')]")->length > 0) {
            return $this->AssignLang();
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
        $it = ['Kind' => 'L'];
        $it['Number'] = $this->re("#^\s*([\w\-]+)\s*$#", $this->nextText('Budget Rent a Car Confirmation #'));

        if (empty($it['Number'])) {
            $it['Number'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'CONFIRMATION NUMBER')]/ancestor::td[1]", null, true, "#CONFIRMATION NUMBER\s+([\w\-]+)#");
        }

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Amount Due at Counter')]/ancestor::td[1]/following::td[normalize-space(.)][1]"));

        if (!empty($tot['Total'])) {
            $it['TotalCharge'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Subtotal')]/ancestor::td[1]/following::td[normalize-space(.)][1]"));

        if (!empty($tot['Total'])) {
            $it['BaseFare'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Taxes and Fees')]/ancestor::td[1]/following::td[normalize-space(.)][1]"));

        if (!empty($tot['Total'])) {
            $it['TotalTaxAmount'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $it['RenterName'] = $this->nextText('Driver Name');
        $it['TripNumber'] = $this->nextText('Car Rental Trip');
        $it['Status'] = $this->nextText('Booking Status');
        $it['RentalCompany'] = $this->nextText('Rental Partner');

        if (empty($it['RentalCompany'])) {
            $it['RentalCompany'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'RENTAL CAR COMPANY')]/ancestor::td[1]", null, true, "#RENTAL CAR COMPANY\s+(.+)#");
        }
        $it['PickupPhone'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'PHONE NUMBER')]/ancestor::td[1]", null, true, "#PHONE NUMBER\s+(.+)#");
        $it['DropoffPhone'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'PHONE NUMBER')]/ancestor::td[1]", null, true, "#PHONE NUMBER\s+(.+)#");
        $it['CarType'] = $this->nextText('Car Type');
        $it['CarModel'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Rental Partner')]/preceding::text()[normalize-space(.)][1]");
        $it['CarImageUrl'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Rental Partner')]/preceding::img[1]/@src");
        $it['PickupLocation'] = $this->http->FindSingleNode("(//text()[starts-with(normalize-space(.),'Pick-up Details')]/ancestor::td[1]/following::td[1]//text()[normalize-space(.)])[2]");
        $it['PickupDatetime'] = strtotime($this->normalizeDate($this->http->FindSingleNode("(//text()[starts-with(normalize-space(.),'Pick-up Details')]/ancestor::td[1]/following::td[1]//text()[normalize-space(.)])[1]")));
        $it['DropoffLocation'] = $this->http->FindSingleNode("(//text()[starts-with(normalize-space(.),'Drop-Off Details')]/ancestor::td[1]/following::td[1]//text()[normalize-space(.)])[2]");
        $it['DropoffDatetime'] = strtotime($this->normalizeDate($this->http->FindSingleNode("(//text()[starts-with(normalize-space(.),'Drop-Off Details')]/ancestor::td[1]/following::td[1]//text()[normalize-space(.)])[1]")));

        return [$it];
    }

    private function nextText($field)
    {
        return $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'{$field}')]/following::text()[normalize-space(.)][1]");
    }

    private function normalizeDate($date)
    {
        $in = [
            //Thu, Aug 24, 2017 at 7:00 am
            '#^\S+\s+(\w+)\s+(\d+),\s+(\d+)\s+at\s+(\d+:\d+(?:\s*[ap]m)?)$#i',
        ];
        $out = [
            '$2 $1 $3 $4',
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
