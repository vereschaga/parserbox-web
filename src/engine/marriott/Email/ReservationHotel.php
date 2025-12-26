<?php

namespace AwardWallet\Engine\marriott\Email;

//ToDo: need merge with It1682043.php, HotelReservation

class ReservationHotel extends \TAccountChecker
{
    public $mailFiles = "marriott/it-1729270.eml, marriott/it-1734696.eml, marriott/it-1783666.eml, marriott/it-2109119.eml, marriott/it-2109235.eml, marriott/it-2578011.eml";

    public $reFrom = "marriott";
    public $reFromH = "marriott";
    public $reBody = [
        'en' => ['RESERVATION CONFIRMATION', 'CHECK-IN TIME'],
        'fr' => ['CONFIRMATION DE RÉSERVATION', 'HEURE D\'ARRIVÉE'],
    ];
    public $reSubject = [
        'Your stay + things to do',
        'Votre séjour au',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
        'fr' => [
            'RESERVATION CONFIRMATION' => 'CONFIRMATION DE RÉSERVATION',
            'Hotel Website'            => 'Site Internet de l\'hôtel',
            'CHECK-IN DATE'            => 'DATE D\'ARRIVÉE',
            'CHECK-IN TIME'            => 'HEURE D\'ARRIVÉE',
            'CHECK-OUT DATE'           => 'DATE DE DÉPART',
            'CHECK-OUT TIME'           => 'HEURE DE DÉPART',
            'Dear'                     => 'Chère/cher',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->AssignLang();

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'PlanYourStay' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'marriott-res.com')] | //img[contains(@src,'marriott-res.com')] | //text()[contains(.,'marriott-res.com')]")->length > 0) {
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
        $text = text($this->http->Response['body']);
        $it = ['Kind' => 'R'];
        $it['ConfirmationNumber'] = $this->http->FindSingleNode("//text()[normalize-space(.)='{$this->t('RESERVATION CONFIRMATION')}']/following::text()[normalize-space(.)!=''][1]");

        if (empty($it['ConfirmationNumber'])) {
            $it['ConfirmationNumber'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'{$this->t('RESERVATION CONFIRMATION')}')]", null, true, "#{$this->t('RESERVATION CONFIRMATION')}[\s:]+([A-Z\d]+)#");
        }
        $it["AccountNumbers"] = $this->http->FindNodes("//text()[normalize-space(.)='{$this->t('REWARDS NUMBER')}']/ancestor::tr[1]/following-sibling::tr[1]/td[2]", null, "#\d+#");
        $it["HotelName"] = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"' . $this->t('Hotel Website') . '")]/ancestor::table[2]/preceding-sibling::table[2]');
        $it["Address"] = implode(" ", $this->http->FindNodes('(//text()[starts-with(normalize-space(.),"' . $this->t('Hotel Website') . '")]/ancestor::table[2]/preceding-sibling::table[1]//text()[normalize-space(.)!="" and not(contains(.,"["))])'));
        $it["Phone"] = $this->http->FindSingleNode('(//text()[starts-with(normalize-space(.),"' . $this->t('Hotel Website') . '")]/ancestor::table[2]//text()[normalize-space(.)!=""])[position()=1]');

        if (empty($it["HotelName"]) && preg_match("#\n\s*{$this->opt($this->t('Plan Your Stay'))}\s+([^\n]+)\s+([^\n]+)\s+([+\d\(\)-]{5,})#i", $text, $m)) {
            $it["HotelName"] = $m[1];
            $it['Address'] = preg_replace("#\s+\[.+$#", '', $m[2]);
            $it['Phone'] = $m[3];
        }
        $date = $this->http->FindSingleNode('//text()[normalize-space(.)="' . $this->t('CHECK-IN DATE') . '"]/following::text()[normalize-space(.)!=""][1]');
        $time = $this->http->FindSingleNode('//text()[normalize-space(.)="' . $this->t('CHECK-IN TIME') . '"]/following::text()[normalize-space(.)!=""][1]');
        $it["CheckInDate"] = strtotime($this->normalizeDate($date . ' ' . $time));
        $date = $this->http->FindSingleNode("//text()[normalize-space(.)='{$this->t('CHECK-OUT DATE')}']/following::text()[normalize-space(.)!=''][1]");
        $time = $this->http->FindSingleNode("//text()[normalize-space(.)='{$this->t('CHECK-OUT TIME')}']/following::text()[normalize-space(.)!=''][1]");
        $it["CheckOutDate"] = strtotime($this->normalizeDate($date . ' ' . $time));
        $it["GuestNames"] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'{$this->t('Dear')}')]", null, true, "#{$this->t('Dear')}\s*(.+?),#");

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\w+\s+(\d+)\s+(\w+)\s+(\d+)\s+(\d+)\s*h\s*(\d+)$#u', //jeudi 12 octobre 2017 15 h 00
            '#^\w+,\s+(\w+)\s+(\d+),\s+(d+)\s+(\d+:\d+\s*(?:[ap]m)?)$#', //Friday, March 20, 2015 03:00 PM
            '#^\w+\s+(\d+)\s+de\s+(\w+)\s+de\s+(\d+)\s+(\d+:\d+)\s+hs$#u', //domingo 11 de enero de 2015 12:00 hs
        ];
        $out = [
            '$1 $2 $3 $4:$5',
            '$2 $1 $3 $4',
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
                    && $this->http->XPath->query('//*[contains(normalize-space(.),"' . $reBody[1] . '")]')->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }
}
