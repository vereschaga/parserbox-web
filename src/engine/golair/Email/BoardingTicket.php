<?php

namespace AwardWallet\Engine\golair\Email;

class BoardingTicket extends \TAccountChecker
{
    public $mailFiles = "golair/it-4095988.eml, golair/it-6001520.eml, golair/it-7439324.eml";

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'voegol@voegol.com.br') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($this->http->Response['body'], "horário de embarque") !== false && stripos($this->http->Response['body'], "//www.voegol.com.br") !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@voegol.com.br') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->ParseEmail();

        return [
            'parsedData' => [
                'Itineraries' => $its,
            ],
            'emailType' => 'BoardingTicket',
        ];
    }

    public static function getEmailLanguages()
    {
        return ['pt'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    // Function returns a key from $array in which $recordLocator was found, otherwise FALSE
    protected function recordLocatorInArray($recordLocator, $array)
    {
        $result = false;

        foreach ($array as $key => $value) {
            if (in_array($recordLocator, $value)) {
                $result = $key;
            }
        }

        return $result;
    }

    protected function datePtToEn($dateString)
    {
        return str_replace(
            ['JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN', 'JUL', 'AGO', 'SET', 'OUT', 'NON', 'DEZ'],
            ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
        $dateString);
    }

    protected function ParseEmail()
    {
        $its = [];

        $blocks = $this->http->XPath->query('//table[starts-with(normalize-space(.),"Código de reserva") and .//tr[contains(.,"from") and contains(.,"to") and contains(.,"gate") and not(.//tr)]]');

        if ($blocks->length == 0) {
            $this->http->Response['body'] = iconv('UTF-8', 'ISO-8859-1//IGNORE', $this->http->Response['body']);
            $this->http->SetBody($this->http->Response['body']);
            $blocks = $this->http->XPath->query('//table[starts-with(normalize-space(.),"Código de reserva") and .//tr[contains(.,"from") and contains(.,"to") and contains(.,"gate") and not(.//tr)]]');
        }

        foreach ($blocks as $block) {
            $it = $this->ParseFlights($block);

            if (($key = $this->recordLocatorInArray($it['RecordLocator'], $its)) !== false) {
                $its[$key]['Passengers'] = array_merge($its[$key]['Passengers'], $it['Passengers']);
                $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);
                $its[$key]['TripSegments'] = array_merge($its[$key]['TripSegments'], $it['TripSegments']);
            } else {
                $its[] = $it;
            }
        }

        return $its;
    }

    protected function ParseFlights($root)
    {
        $it = [];
        $it['Kind'] = 'T';
        $it['RecordLocator'] = $this->http->FindSingleNode('.//text()[normalize-space(.)="Código de reserva"]/following::text()[normalize-space(.)!=""][1]', $root, true, '/([A-Z\d]{6})/');

        $passengers = $this->http->FindNodes('.//table[starts-with(normalize-space(.),"nome") and count(.//tr[2]/td)=3]//tr[2]/td[1]', $root);
        $it['Passengers'] = array_unique($passengers);
        $it['TripSegments'] = [];
        $rows = $this->http->XPath->query('.//tr[contains(.,"voo") and ./following-sibling::tr[starts-with(normalize-space(.),"de")]]', $root);

        foreach ($rows as $row) {
            $seg = [];
            $seg['FlightNumber'] = $this->http->FindSingleNode('.//tr[starts-with(normalize-space(.),"voo") and not(.//tr)]/following-sibling::tr[1]/td[1]', $row, true, '/(\d+)/');

            if (!empty($seg['FlightNumber'])) {
                $seg['AirlineName'] = 'G3';
            }
            $seg['Seats'] = $this->http->FindSingleNode('.//tr[contains(.,"assento") and not(.//tr)]/following-sibling::tr[1]/td[3]', $row);
            $nodes = $this->http->XPath->query('./following-sibling::tr[1]//tr[starts-with(normalize-space(.),"de") and contains(.,"para")]', $row);

            if ($nodes->length > 0) {
                $fromTo = $nodes->item(0);
                $departure = $this->http->FindSingleNode('./following-sibling::tr[1]/td[1]', $fromTo);

                if (preg_match('/^(.+)\s+\(([A-Z\d]{3})\)$/', $departure, $matches)) {
                    $seg['DepName'] = $matches[1];
                    $seg['DepCode'] = $matches[2];
                }
                $arrival = $this->http->FindSingleNode('./following-sibling::tr[1]/td[2]', $fromTo);

                if (preg_match('/^(.+)\s+\(([A-Z\d]{3})\)$/', $arrival, $matches)) {
                    $seg['ArrName'] = $matches[1];
                    $seg['ArrCode'] = $matches[2];
                }
                $dateDep = $this->http->FindSingleNode('./following-sibling::tr[2]/td[1]', $fromTo, true, '/(\d{2}\s*[\S\D]{3}\s*\d{2,4})/');
                $timeDep = $this->http->FindSingleNode('./following-sibling::tr[2]/td[3]', $fromTo, true, '/(\d{2}:\d{2})/');

                if ($dateDep && $timeDep) {
                    $dateDep = $this->datePtToEn($dateDep);
                    $seg['DepDate'] = strtotime($timeDep, strtotime($dateDep));
                }
                $dateArr = $this->http->FindSingleNode('./following-sibling::tr[2]/td[4]', $fromTo, true, '/(\d{2}\s*[\S\D]{3}\s*\d{2,4})/');
                $timeArr = $this->http->FindSingleNode('./following-sibling::tr[2]/td[6]', $fromTo, true, '/(\d{2}:\d{2})/');

                if ($dateArr && $timeArr) {
                    $dateArr = $this->datePtToEn($dateArr);
                    $seg['ArrDate'] = strtotime($timeArr, strtotime($dateArr));
                }
            }
            $seg['BookingClass'] = $this->http->FindSingleNode('./following-sibling::tr[1]//td[starts-with(normalize-space(.),"classe") and not(.//td)]/following-sibling::td[1]', $row);
            $it['TripSegments'][] = $seg;
        }

        return $it;
    }
}
