<?php

namespace AwardWallet\Engine\singaporeair\Email;

use AwardWallet\Engine\MonthTranslate;

class SeatMeal extends \TAccountChecker
{
    public $mailFiles = "singaporeair/it-6216867.eml, singaporeair/it-6345188.eml, singaporeair/it-6732543.eml";

    public $reFrom = "booking@singaporeair.com.sg";
    public $reBody = [
        'fr' => ['Référence de la réservation', 'Seat change confirmed'], //mix lang's
        'ru' => ['Код бронирования', 'Singapore Airlines. Все права защищены.'],
        'en' => ['Booking reference', 'Singapore Airlines. All Rights Reserved'],
    ];
    public $reSubject = [
        'Confirmation of your Singapore Airlines seat/meal',
    ];
    public $lang = '';
    public $date;
    public static $dict = [
        'fr' => [
            'RecLoc'   => 'Référence de la réservation',
            'Duration' => 'Durée totale du voyage',
        ],
        'ru' => [
            'RecLoc'   => 'Код бронирования',
            'Duration' => 'Общее время путешествия',
        ],
        'en' => [
            'RecLoc'   => 'Booking reference',
            'Duration' => 'Total travel time',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());
        $body = $this->http->Response['body'];
        $this->AssignLang($body);

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "SeatMeal" . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'singaporeair.com')]")->length > 0) {
            $body = $parser->getHTMLBody();

            return $this->AssignLang($body);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false) {
            if (isset($this->reSubject)) {
                foreach ($this->reSubject as $reSubject) {
                    if (stripos($headers["subject"], $reSubject) !== false) {
                        return true;
                    }
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

            if (
                ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang))
                || ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, 'en'))
            ) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'" . $this->t('RecLoc') . "')]", null, true, "#:\s+([A-Z\d]+)#");
        $it['Passengers'] = array_unique(array_filter($this->http->FindNodes("//img[contains(@src,'seat')]/ancestor::table[2]/descendant::tr[1]", null, "#\d+\.?\s+(.+)#")));
        //		$it['Passengers'] = array_unique(array_filter($this->http->FindNodes("//text()[normalize-space(.)='Passagers']/ancestor::table[1]/following-sibling::table[descendant::img[contains(@src,'seat')]]/descendant::tr[1]",null,"#\d+\.?\s+(.+)#")));

        $xpath = "//img[contains(@src,'plane')]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];

            $node = $this->http->FindSingleNode("./preceding::tr[1]/td[1]", $root);

            if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
                $seg['Seats'] = implode(",", $this->http->FindNodes("//text()[contains(.,'{$m[1]}') and contains(.,'{$m[2]}')]/following::table[1][descendant::img[contains(@src,'seat')]]/descendant::tr[normalize-space(.)][descendant::img[contains(@src,'seat')]]/td[normalize-space(.)][2]"));
            }
            $node = $this->http->FindSingleNode("./preceding::tr[1]/td[2]", $root);

            if (preg_match("#(.*?)(?:\s*\(([A-Z]{1,2})\)|$)#", $node, $m)) {
                $seg['Cabin'] = $m[1];

                if (isset($m[2]) && !empty($m[2])) {
                    $seg['BookingClass'] = $m[2];
                }
            }
            $seg['Duration'] = $this->http->FindSingleNode("./following::div[1][contains(.,'" . $this->t('Duration') . "')]", $root, true, "#:\s+(.+)#");

            $node = implode("\n", $this->http->FindNodes("./td[1]//text()[normalize-space(.)]", $root));
            $re = "#([A-Z]{3})\s+(\d+:\d+(?:\s[ap]m)?)\n.+?\n(\d+\s+\w+)\s*[\(\W]*\w{1,5}[\)\W]*\s+(.+?)(?:\s*,\s+(.*Terminal.*)|$)#ui";

            if (preg_match($re, $node, $m)) {
                $seg['DepCode'] = $m[1];

                if (preg_match('/(\d{1,2}):(\d{2})\s*([pa]m)/i', $m[2], $t) && (int) $t[1] > 12) {
                    $seg['DepDate'] = strtotime($this->normalizeDate($m[3]) . ' ' . $t[1] . ':' . $t[2]);
                } else {
                    $seg['DepDate'] = strtotime($this->normalizeDate($m[3]) . ' ' . $m[2]);
                }
                $seg['DepName'] = trim($m[4], ' ,');

                if (isset($m[5]) && !empty($m[5])) {
                    $seg['DepartureTerminal'] = trim(str_ireplace('Terminal', '', $m[5]));
                }
            }
            $node = implode("\n", $this->http->FindNodes("./td[3]//text()[normalize-space(.)]", $root));

            if (preg_match("#([A-Z]{3})\s+(\d+:\d+(?:\s[ap]m)?)\s*\n.+?\n\s*(.+?)(?:\s*,\s+(.*Terminal.*)|$)#ui", $node, $m)) {
                $seg['ArrCode'] = $m[1];
                $time = $m[2];

                if (preg_match("#^(\d+):(\d+)#", $m[2], $t)) {
                    if ((int) $t[1] > 12) {
                        $time = $t[1] . ':' . $t[2];
                    } elseif ((int) $t[1] === 12 && strpos($m[2], 'p') !== false) {
                        $time = "00:00";
                    }
                }

                if (isset($seg['DepDate'])) {
                    $seg['ArrDate'] = strtotime($time, $seg['DepDate']);
                }
                $seg['ArrName'] = trim($m[3], ' ,');

                if (isset($m[4]) && !empty($m[4])) {
                    $seg['ArrivalTerminal'] = trim(str_ireplace('Terminal', '', $m[4]));
                }
            }

            if ($seg['DepCode'] === $seg['ArrCode'] && !empty($seg['DepName']) && !empty($seg['ArrName'])) {
                $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            '#^(\d+\s+\w+)$#',
        ];
        $out = [
            '$1' . ' ' . $year,
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

    private function AssignLang($body)
    {
        if (isset($this->reBody)) {
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
