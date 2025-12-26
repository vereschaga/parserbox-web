<?php

namespace AwardWallet\Engine\panorama\Email;

class ReminderForFlights extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "panorama/it-4839741.eml";

    public $reBody = [
        'en' => ['This is a reminder for the upcoming flight(s) in your itinerary', 'Thank you for flying'],
    ];
    public $reSubject = [
        'Your FLIGHT details',
    ];
    public $lang = 'en';
    public $pdf;
    public static $dict = [
        'en' => [
        ],
    ];

    public static $providerDetect = [
        'panorama'  => 'Ukraine International Airlines',
        'oman'      => 'Oman Air',
        'airindia'  => 'Air India',
        'jordanian' => 'Royal Jordanian',
        'vistara'   => 'Team Vistara',
    ];
    private $regExp = [
        '1' => '#[A-Z\d]{2}\s*\d+#',                       // Flight
        '2' => '#.+\([A-Z]{3}\)#',                         // Depart
        '3' => '#.+\([A-Z]{3}\)#',                         // Arrive
        '4' => '#\w+\,\s+\w+\d{2}\s+\d{4}\s+\d{2}\:\d{2}#', // Depart Date  Tue, Jul 08 2014 09:55
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];
        $this->AssignLang($body);

        $its = $this->parseEmail();
        $result = [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "ReminderForFlights",
        ];

        if (null !== ($code = $this->getProvider())) {
            $result['providerCode'] = $code;
        }

        return $result;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $this->AssignLang($body);

        return stripos($body, $this->reBody[$this->lang][0]) !== false && stripos($body, $this->reBody[$this->lang][1]) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers['subject'], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "flyuia.com") !== false || stripos($from, "omanair.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$providerDetect);
    }

    private function getProvider()
    {
        foreach (self::$providerDetect as $code => $detect) {
            if ($this->http->XPath->query("//text()[starts-with(normalize-space(),'{$this->t('Thank you for flying')}') and contains(normalize-space(),'{$detect}')]")->length > 0) {
                return $code;
            }
        }

        foreach (self::$providerDetect as $code => $detect) {
            if ($this->http->XPath->query("//text()[contains(normalize-space(),'{$detect}')]")->length > 0) {
                return $code;
            }
        }

        return null;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(),'" . $this->t('Booking Reference') . "') and contains(.,':')]", null, true, "#" . $this->t('Booking Reference') . "\s*\:\s+(.+)#");

        $it['Passengers'] = $this->http->FindNodes("//*[contains(text(),'" . $this->t('Passenger(s)') . "')]/ancestor::tr[1]/td[2]/text()");

        if (empty($it['Passengers'])) {
            $it['Passengers'] = $this->http->FindNodes("//*[contains(text(),'" . $this->t('Passenger(s)') . "')]/ancestor::tr[1]//text()[normalize-space()][not(contains(.,'" . $this->t('Passenger(s)') . "'))]");
        }

        $xpath = "//text()[normalize-space() = 'Flight']/ancestor::tr[1][contains(normalize-space(),'Depart')]/following-sibling::tr";
        $rows = $this->http->XPath->query($xpath);

        foreach ($rows as $row) {
            $seg = [];

            foreach ($this->regExp as $i => $value) {
                switch ($i) {
                    case '1':
                        $node = $this->http->FindSingleNode("./td[" . $i . "]", $row, $value[0]);

                        if (($node != null) && (preg_match("#(?<AirlineName>[A-Z\d]{2})\s*(?<FlightNumber>\d+)#", $node, $m))) {
                            $seg['AirlineName'] = $m['AirlineName'];
                            $seg['FlightNumber'] = $m['FlightNumber'];
                        }

                        break;

                    case '2':
                        $node = $this->http->FindSingleNode("./td[" . $i . "]", $row, $value[0]);

                        if (($node != null) && (preg_match("#(?<DepName>.+)\((?<DepCode>[A-Z]{3})\)#", $node, $m))) {
                            $seg['DepName'] = trim($m['DepName']);
                            $seg['DepCode'] = $m['DepCode'];
                        }

                        break;

                    case '3':
                        $node = $this->http->FindSingleNode("./td[" . $i . "]", $row, $value[0]);

                        if (($node != null) && (preg_match("#(?<ArrName>.+)\((?<ArrCode>[A-Z]{3})\)#", $node, $m))) {
                            $seg['ArrName'] = trim($m['ArrName']);
                            $seg['ArrCode'] = $m['ArrCode'];
                        }

                        break;

                    case '4':
                        $node = $this->http->FindSingleNode("./td[" . $i . "]", $row, $value[0]);
                        $seg['DepDate'] = strtotime($node);
                        $seg['ArrDate'] = MISSING_DATE;

                        break;
                }
            }
            $it['TripSegments'][] = $seg;
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

    private function AssignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false || stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        return true;
    }
}
