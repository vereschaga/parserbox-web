<?php

namespace AwardWallet\Engine\kimpton\Email;

class HConfirmation extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "grandhotelminneapolis.com";
    public $reBody = [
        'en' => ['Thank you for making your reservation at the', 'Confirmation Number'],
    ];
    public $reSubject = [
        'Here is Your Confirmation with',
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
        if ($this->http->XPath->query("//a[contains(@href,'grandhotelminneapolis.com')]")->length > 0 || stripos($this->http->Response['body'], ' Grand Hotel Minneapolis') !== false) {
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
        $it = ['Kind' => 'R'];
        $it['ConfirmationNumber'] = $this->nextText("Confirmation Number");
        $it['HotelName'] = ucwords($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Thank you for making your reservation at the')]", null, true, "#Thank you for making your reservation at the\s+(.+?)(?:\.|$)#"));
        $it['Address'] = implode(" ", $this->http->FindNodes("//text()[starts-with(normalize-space(.),'Confirmation Number')]/preceding::a[1]/preceding::p[position()<=5][not(normalize-space(.))][last()]/following::p[position()<=5][not(descendant::a)][normalize-space(.)][position()!=last()]"));

        if (empty($it['Address'])) {
            $it['Address'] = $it['HotelName'];
        }

        $it['Phone'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'please feel free to contact us directly at')]", null, true, "#please feel free to contact us directly at\s+(.+?)(?:\.|$)#");

        if (empty($it['Phone'])) {
            $it['Phone'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Confirmation Number')]/preceding::a[1]/preceding::p[position()<=5][not(normalize-space(.))][last()]/following::p[position()<=5][not(descendant::a)][normalize-space(.)][position()=last()]");
        }
        $it['ConfirmationNumber'] = $this->nextText("Confirmation Number");
        $it['GuestNames'] = $this->http->FindNodes("//text()[starts-with(normalize-space(.),'MS') or starts-with(normalize-space(.),'MR')]");

        $date = $this->nextText("Arrival");
        $date2 = $this->nextText("Departure");
        $df = $this->DateFormatForHotels($date, $date2);
        $it['CheckInDate'] = strtotime($this->normalizeDate($df[0]));
        $it['CheckOutDate'] = strtotime($this->normalizeDate($df[1]));
        $time = $this->correctTimeString($this->nextText("Check-in time"));
        $it['CheckInDate'] = strtotime($time, $it['CheckInDate']);
        $time = $this->correctTimeString($this->nextText("Check-out time"));
        $it['CheckOutDate'] = strtotime($time, $it['CheckOutDate']);

        $node = $this->nextText("Number of Guests");
        $it['Guests'] = $this->re("#^(\d+)#", $node);
        $it['Kids'] = $this->re("#\d+\s*\/\s*(\d+)#", $node);
        $it['RoomType'] = $this->nextText("Accommodations");
        $it['Rate'] = $this->nextText("Rate");

        $it['CancellationPolicy'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Check-out time')]/following::text()[normalize-space(.)][2][contains(.,'Cancellations')]");

        return [$it];
    }

    private function nextText($field)
    {
        return $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'$field')]/following::text()[normalize-space(.)][1]");
    }

    private function normalizeDate($date)
    {
        $in = [
            '#(\d+)\.(\d+)\.(\d+)#',
        ];
        $out = [
            '$3-$2-$1',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function correctTimeString($time)
    {
        if (preg_match("#(\d+):(\d+)\s*([ap]m)#i", $time, $m)) {
            if (($m[1] == 0 && stripos($m[3], 'am') !== false) || $m[1] > 12) {
                return $m[1] . ":" . $m[2];
            }
        }

        return $time;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function DateFormatForHotels($dateIN, $dateOut)
    {
        if (preg_match("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", $dateIN, $m)) {
            $dateIN = str_replace(" ", "", preg_replace("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", "$1 " . "20$2", $dateIN));
        }

        if (preg_match("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", $dateOut, $m)) {
            $dateOut = str_replace(" ", "", preg_replace("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", "$1 " . "20$2", $dateOut));
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
        if (preg_match("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $date1, $m) && preg_match("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $date2, $m2)) {
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
