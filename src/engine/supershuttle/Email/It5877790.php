<?php

namespace AwardWallet\Engine\supershuttle\Email;

class It5877790 extends \TAccountChecker
{
    public $mailFiles = "supershuttle/it-8269502.eml"; // +1 bcdtravel(html)[en]

    public $reFrom = '@supershuttle.com';

    public $reSubject = [
        'en' => ['SuperShuttle Reservation Confirmation'],
    ];

    public $reBody = 'SuperShuttle';

    public $langDetectors = [
        'en' => ['Booking Confirmation'],
    ];

    public static $dictionary = [
        'en' => [],
    ];

    public $lang = '';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $textBody = $parser->getHTMLBody();

        if (strpos($textBody, $this->reBody) === false) {
            return false;
        }

        return $this->assignLang($textBody);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $this->http->SetEmailBody(str_replace(" ", " ", $this->http->Response['body'])); // bad fr char " :"

        $this->assignLang($this->http->Response['body']);

        return $this->parseEmail();
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    protected function parseEmail()
    {
        $its = [];

        $travelSegments = $this->http->XPath->query('//text()[contains(normalize-space(.),"Confirmation #:")]/ancestor::table[contains(normalize-space(.),"Dropoff Location:")][1]');

        foreach ($travelSegments as $travelSegment) {
            $itFlight = $this->parseFlight($travelSegment);

            if (($key = $this->recordLocatorInArray($itFlight['RecordLocator'], $its)) !== false) {
                $its[$key]['TripSegments'] = array_merge($its[$key]['TripSegments'], $itFlight['TripSegments']);
            } else {
                $its[] = $itFlight;
            }
        }

        $result = [
            'emailType'  => 'TripConfirmation_' . $this->lang,
            'parsedData' => [
                'Itineraries' => $its,
            ],
        ];

        $paymentTotal = $this->http->FindSingleNode("//text()[normalize-space(.)='Fare:']/ancestor::tr[1]/following-sibling::tr[last()]/td[2]");

        if (preg_match('/(\D+)([,.\d]+)/', $paymentTotal, $matches)) {
            $currency = $matches[1];
            $totalCharge = $this->normalizePrice($matches[2]);
            $paymentFare = $this->nextText('Fare:');

            if (preg_match('/' . preg_replace('/([.$*)(])/', '\\\\$1', $currency) . '([,.\d]+)/', $paymentFare, $m)) {
                $baseFare = $this->normalizePrice($m[1]);
            }
        }

        if (count($result['parsedData']['Itineraries']) === 1) {
            $result['parsedData']['Itineraries'][0]['Currency'] = $currency;
            $result['parsedData']['Itineraries'][0]['TotalCharge'] = $totalCharge;

            if ($baseFare) {
                $result['parsedData']['Itineraries'][0]['BaseFare'] = $baseFare;
            }
        } elseif (count($result['parsedData']['Itineraries']) > 1) {
            $result['parsedData']['TotalCharge']['Currency'] = $currency;
            $result['parsedData']['TotalCharge']['Amount'] = $totalCharge;
        }

        return $result;
    }

    protected function parseFlight($root)
    {
        $it = [];
        $it['Kind'] = 'T';

        // TripCategory
        $it['TripCategory'] = TRIP_CATEGORY_TRANSFER;

        // RecordLocator
        $it['RecordLocator'] = $this->nextText('Confirmation #:', $root);

        $it['TripSegments'] = [];

        $itsegment = [];

        // DepCode
        $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

        // DepName
        $itsegment['DepName'] = $this->nextCol('Pickup Location:', $root);

        // DepDate
        $itsegment['DepDate'] = strtotime($this->normalizeDate($this->nextCol('Pickup Date & Time:', $root)));

        // ArrCode
        $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

        // ArrName
        $itsegment['ArrName'] = $this->nextCol('Dropoff Location:', $root);

        // ArrDate
        $itsegment['ArrDate'] = MISSING_DATE;

        $it['TripSegments'][] = $itsegment;

        return $it;
    }

    protected function assignLang($text)
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    protected function recordLocatorInArray($recordLocator, $array)
    {
        foreach ($array as $key => $value) {
            if ($value['Kind'] === 'T') {
                if ($value['RecordLocator'] === $recordLocator) {
                    return $key;
                }
            }
        }

        return false;
    }

    protected function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    private function nextText($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//text()[normalize-space(.)='{$field}'])[{$n}]/following::text()[normalize-space(.)][1]", $root);
    }

    private function nextCol($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//td[not(.//td) and normalize-space(.)='{$field}'])[{$n}]/following-sibling::td[1]", $root);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        //		$year = date('Y', $this->date);
        // Thursday, April 06, 2017 3:15 PM - 3:30 PM
        $in = [
            '/^\w+, (\w+) (\d+), (\d{4}) ([\d:]+(?:\s*[ap.]m)?).*?$/i',
            "#^[^\d\s]+,\s+(\d+)\s+([^\d\s]+)\s+(\d{4})\s+(\d+:\d+)$#",
            "#^[^\d\s]+,\s+(\d+)\s+([^\d\s]+)\s+(\d{4})$#",
        ];
        $out = [
            '$2 $1 $3, $4',
            "$1 $2 $3, $4",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
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
