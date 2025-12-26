<?php

namespace AwardWallet\Engine\maketrip\Email;

class FareQuote extends \TAccountChecker
{
    public $mailFiles = "maketrip/it-6014997.eml";

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'noreply@makemytrip.com') !== false
            || stripos($headers['subject'], 'MakeMyTrip Fare Quote') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@makemytrip.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//node()[contains(.,"Team MakeMyTrip")]')->length > 0
            && $this->http->XPath->query('//node()[contains(.,"Fare Quote")]')->length > 0;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->year = getdate(strtotime($parser->getHeader('date')))['year'];
        $it = $this->ParseEmail();

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'FareQuote',
        ];
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    protected function priceNormalize($string)
    {
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    protected function ParseEmail()
    {
        $it = [];
        $it['Kind'] = 'T';
        $it['RecordLocator'] = CONFNO_UNKNOWN;
        $it['TripSegments'] = [];
        $segments = $this->http->XPath->query('//tr[count(./td)>3 and contains(.,"Flight") and ./td[normalize-space(.)="to"] and not(.//tr)]');

        foreach ($segments as $segment) {
            $seg = [];
            $fligth = $this->http->FindSingleNode('./td[contains(.,"Flight")][1]', $segment);

            if (preg_match('/Flight[#\s]+([A-Z\d]{2})\s*(\d+)\s*Operated\s+by[:\s]+(\S.+\S)/i', $fligth, $matches)) {
                $seg['AirlineName'] = $matches[1];
                $seg['FlightNumber'] = $matches[2];
                $seg['Operator'] = $matches[3];
            }
            $xpathFragment = 'normalize-space(.)="to"';
            $regexpPattern = '/\s*(\S.+\S)\s+\(([A-Z]{3})\)(?:.+\s*Terminal\s+([A-Z\d]{1,2})|.+)\s*(\d{2}\s+[^,\d\s]+)[,\s]+(\d{2}:\d{2})\s*$/i';
            $from = $this->http->FindSingleNode('./td[' . $xpathFragment . ']/preceding-sibling::td[contains(.,":") and contains(.,"(") and string-length(.)>13][1]', $segment);

            if (preg_match($regexpPattern, $from, $matches)) {
                $seg['DepName'] = $matches[1];
                $seg['DepCode'] = $matches[2];

                if ($matches[3] !== '') {
                    $seg['DepartureTerminal'] = $matches[3];
                }
                $dateDep = $matches[4];
                $timeDep = $matches[5];
            }

            if ($dateDep && $timeDep) {
                $dateDep = strtotime($dateDep . ' ' . $this->year);
                $seg['DepDate'] = strtotime($timeDep, $dateDep);
            }
            $to = $this->http->FindSingleNode('./td[' . $xpathFragment . ']/following-sibling::td[contains(.,":") and contains(.,"(") and string-length(.)>13][1]', $segment);

            if (preg_match($regexpPattern, $to, $matches)) {
                $seg['ArrName'] = $matches[1];
                $seg['ArrCode'] = $matches[2];

                if ($matches[3] !== '') {
                    $seg['ArrivalTerminal'] = $matches[3];
                }
                $dateArr = $matches[4];
                $timeArr = $matches[5];
            }

            if ($dateArr && $timeArr) {
                $dateArr = strtotime($dateArr . ' ' . $this->year);
                $seg['ArrDate'] = strtotime($timeArr, $dateArr);
            }
            $classAndDuration = $this->http->FindSingleNode('./following-sibling::tr[normalize-space(.)!=""][1]', $segment);

            if (preg_match('/^[>\s]*Class[:\s]+(\b[^|]+\b)[|\s]+Duration[:\s]+(\b[hm\d\s]{2,}\b)\s*$/i', $classAndDuration, $matches)) {
                $seg['Cabin'] = $matches[1];
                $seg['Duration'] = $matches[2];
            }
            $it['TripSegments'][] = $seg;
        }
        $payment = $this->http->FindSingleNode('//td[starts-with(normalize-space(.),"Grand Total") and not(.//td)]/following::td[normalize-space(.)!=""][1]');

        if (preg_match('/^\s*(\S[^\d]+\S)\s+([,.\d]+)\s*$/', $payment, $matches)) {
            $it['Currency'] = $matches[1];
            $it['TotalCharge'] = $this->priceNormalize($matches[2]);
        }

        return $it;
    }
}
