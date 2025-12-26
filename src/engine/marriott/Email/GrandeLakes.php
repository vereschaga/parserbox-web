<?php

namespace AwardWallet\Engine\marriott\Email;

class GrandeLakes extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "groupcampaigns@pkghlrss.com";
    public $reBody = [
        'en' => ['Hotel Confirmation Number:', 'Arrival Date:'],
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
        if ($this->http->XPath->query("//a[contains(translate(.,'MARRIOTT','marriott'),'marriott')]")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(.),'Grande Lakes')]")->length > 0
        ) {
            return true;
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
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
        $it['ConfirmationNumber'] = $this->getField('Hotel Confirmation Number:');
        $it['HotelName'] = trim($this->getField('Hotel Name:'));

        $it['Address'] = $this->http->FindSingleNode("//text()[normalize-space(.)='{$it['HotelName']}']/ancestor::tr[1]/following::tr[1][.//img and contains(.,',')]");

        if (empty($it['Address'])) {
            $it['Address'] = $it['HotelName'];
        }
        $it['Phone'] = $this->http->FindSingleNode("//text()[normalize-space(.)='{$it['HotelName']}']/ancestor::tr[1]/following::tr[2][.//img or contains(.,'Call')]", null, true, "#:\s*(.+)#");

        $it['ReservationDate'] = strtotime($this->getField('Date Booked:'));
        $it['GuestNames'][] = $this->getField('Name of the Guest:');

        $it['CheckInDate'] = strtotime($this->getField('Arrival Date:'));
        $time = $this->getField('CHECK-IN TIME:');

        if (preg_match("#^\s*(\d+:\d+\s*(?:[ap]m)?)\s*$#i", $time, $m)) {
            $it['CheckInDate'] = strtotime($m[1], $it['CheckInDate']);
        }

        $it['CheckOutDate'] = strtotime($this->getField('Departure Date:'));
        $time = $this->getField('CHECK-OUT TIME:');

        if (preg_match("#^\s*(\d+:\d+\s*(?:[ap]m)?)\s*$#i", $time, $m)) {
            $it['CheckOutDate'] = strtotime($m[1], $it['CheckOutDate']);
        }

        $it['RoomType'] = $this->getField('Room Type:');
        $it['Rate'] = $this->getField('Nightly Group Rate:');
        $it['CancellationPolicy'] = $this->getField('Cancelation Policy:');

        return [$it];
    }

    private function getField($field)
    {
        return $this->http->FindSingleNode("(//text()[normalize-space(.)='{$field}']/ancestor::td[1]/following-sibling::td[1])[1]");
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
}
