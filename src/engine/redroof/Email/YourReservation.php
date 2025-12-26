<?php

namespace AwardWallet\Engine\redroof\Email;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "redroof/it-87569826.eml";

    public $reFrom = "redroof.com";
    public $reBody = [
        'en' => ['We\'re looking forward to seeing you', 'Check In'],
    ];
    public $reSubject = [
        'Your reservation has been confirmed',
    ];
    public $lang = '';
    public $subject = '';
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->subject = $parser->getSubject();
        $this->AssignLang();

        $its = $this->parseEmail();
        $a = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[@alt='RedRoof'] | //a[contains(@href,'redroof.com')]")->length > 0) {
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

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\Hotel $it */
        $it = ['Kind' => 'R'];
        $it['ConfirmationNumber'] = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking#'))}]", null,
            true, "#\s*([A-Z\d\-]{5,})#");

        if (!empty($str = $this->http->FindPreg("#Your reservation has been\s+(\w+)#i", false, $this->subject))) {
            $it['Status'] = $str;
        }
        $it['AccountNumbers'][] = $this->http->FindSingleNode("//text()[{$this->starts($this->t('RediCard#'))}]", null,
            true, "#\s*([A-Z\d\-]{5,})#");
        $it['HotelName'] = $this->http->FindSingleNode("//img[@alt='Call']/preceding::text()[normalize-space(.)!=''][1]");

        if (empty($it['HotelName'])) {
            $it['HotelName'] = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking#'))}]/preceding::text()[normalize-space(.)!=''][2]");
            $it['Phone'] = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking#'))}]/preceding::text()[normalize-space(.)!=''][1]");
        } else {
            $it['Phone'] = $this->http->FindSingleNode("//img[@alt='Call']/following::text()[normalize-space(.)!=''][1]");
        }
        $it['Address'] = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Address'))}]/following::text()[normalize-space(.)!=''][1]");
        $it['RoomType'] = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room'))}]/following::text()[normalize-space(.)!=''][1]");
        $it['CancellationPolicy'] = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Cancellation Policy'))}]",
            null, true, "#{$this->opt($this->t('Cancellation Policy'))}[\s:]+(.+)#");
        $it['GuestNames'][] = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Name'))}]/following::text()[normalize-space(.)!=''][1]");

        $dateIN = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check In'))}]/following::text()[normalize-space(.)!=''][1]");
        $dateOUT = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check Out'))}]/following::text()[normalize-space(.)!=''][1]");
        [$dateIN, $dateOUT] = $this->DateFormatForHotels($dateIN, $dateOUT);
        $it['CheckInDate'] = strtotime($dateIN);
        $it['CheckOutDate'] = strtotime($dateOUT);

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->contains($this->t('Subtotal'))}]/following::text()[normalize-space(.)!=''][1]"));

        if (!empty($tot['Total'])) {
            $it['Cost'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->contains($this->t('Tax & Fees'))}]/following::text()[normalize-space(.)!=''][1]"));

        if (!empty($tot['Total'])) {
            $it['Taxes'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->contains($this->t('Total'))}]/following::text()[normalize-space(.)!=''][1]"));

        if (!empty($tot['Total'])) {
            $it['Total'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }

        if (!empty($tot = $this->http->FindSingleNode("//text()[{$this->contains($this->t('RediCard Points'))}]/following::text()[normalize-space(.)!=''][1]",
            null, true, "#([\d.]+\s*points)#"))
        ) {
            $it['SpentAwards'] = $tot;
        }

        if (!empty($tot = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Stay Will Potentially Earn'))}]/following::text()[normalize-space(.)!=''][1]",
            null, true, "#([\d.]+\s*points)#"))
        ) {
            $it['EarnedAwards'] = $tot;
        }

        return [$it];
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang(): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody[0])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($reBody[1])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node): array
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{1,2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function DateFormatForHotels($dateIN, $dateOut)
    {
        if (preg_match("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", $dateIN, $m)) {
            $dateIN = str_replace(" ", "",
                preg_replace("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", "$1 " . "20$2", $dateIN));
        }

        if (preg_match("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", $dateOut, $m)) {
            $dateOut = str_replace(" ", "",
                preg_replace("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", "$1 " . "20$2", $dateOut));
        }

        if ($this->identifyDateFormat($dateIN, $dateOut) === 1) {
            $dateIN = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)$#", "$2.$1.$3", $dateIN);
            $dateOut = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)$#", "$2.$1.$3", $dateOut);
        } else {
            $dateIN = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)$#", "$1.$2.$3", $dateIN);
            $dateOut = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)$#", "$1.$2.$3", $dateOut);
        }

        return [$dateIN, $dateOut];
    }

    private function identifyDateFormat($date1, $date2)
    {
//    define("DATE_MONTH_FIRST", "1");
//    define("DATE_DAY_FIRST", "0");
//    define("DATE_UNKNOWN_FORMAT", "-1");
        if (preg_match("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $date1,
                $m) && preg_match("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $date2, $m2)
        ) {
            if (intval($m[1]) > 12 || intval($m2[1]) > 12) {
                return 0;
            } elseif (intval($m[2]) > 12 || intval($m2[2]) > 12) {
                return 1;
            } else {
                //try to guess format
                $diff = [];

                foreach (['$3-$2-$1', '$3-$1-$2'] as $i => $format) {
                    $tempdate1 = preg_replace("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $format, $date1);
                    $tempdate2 = preg_replace("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $format, $date2);

                    if (($tstd1 = strtotime($tempdate1)) !== false && ($tstd2 = strtotime($tempdate2)) !== false && ($tstd2 - $tstd1 > 0)) {
                        $diff[$i] = $tstd2 - $tstd1;
                    }
                }
                $min = min($diff);

                return array_flip($diff)[$min];
            }
        }

        return -1;
    }
}
