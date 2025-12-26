<?php

namespace AwardWallet\Engine\porter\Email;

class FlightChange2016En extends \TAccountChecker
{
    public $mailFiles = "porter/it-4221221.eml, porter/it-4221243.eml, porter/it-4309757.eml";

    protected $result = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->result['Kind'] = 'T';
        $this->result['RecordLocator'] = $this->http->FindSingleNode('//*[contains(text(), "Confirmation Number:")]/..', null, false, '/[A-Z\d]{5,6}/');
        $this->parsePassengers('//*[contains(text(), "VIPorter")]/ancestor::tr[1]/following-sibling::tr');
        $this->parseSegments('//*[contains(text(), "Flight Information")]/ancestor::tr[1]/following-sibling::tr[2]'
                . '//tbody/tr[contains(translate(., "AM", "am"), "am") or contains(translate(., "PM", "pm"), "pm")]');

        return [
            'emailType'  => 'FlightChange2016En',
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'flyporter@flyporter.com') !== false
                && isset($headers['subject']) && (
                stripos($headers['subject'], 'IMPORTANT - Flight Change Notification') !== false
                || stripos($headers['subject'], 'IMPORTANT - Flight Delay Notice') !== false
                );
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return (strpos($parser->getHTMLBody(), 'Flight Change Notification') !== false
                || strpos($parser->getHTMLBody(), 'Flight Delay Notification') !== false)
                && strpos($parser->getHTMLBody(), 'New Flight Information:') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@flyporter.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    protected function parsePassengers($xpath)
    {
        foreach ($this->http->XPath->query($xpath) as $value) {
            $inner = $this->innerArray($value);

            if (empty($inner) !== true) {
                $this->result['Passengers'][] = $inner[0];
            }
        }
    }

    protected function parseSegments($xpath)
    {
        foreach ($this->http->XPath->query($xpath) as $value) {
            $inner = $this->innerArray($value);

            if (empty($inner) !== true) {
                $this->result['TripSegments'][] = $this->segments($inner);
            }
        }
    }

    protected function innerArray(\DOMElement $element)
    {
        $array = [];

        foreach ($element->childNodes as $value) {
            // Link https://en.wikipedia.org/wiki/Non-breaking_space
            $value->nodeValue = trim(trim($value->nodeValue, chr(0xC2) . chr(0xA0)));

            if (empty($value->nodeValue) !== true) {
                $array[] = $value->nodeValue;
            }
        }

        return $array;
    }

    protected function segments($array)
    {
        $segment = [];

        // Tuesday August 16, 2016
        if (preg_match('/\w+ \d+, \d{4}|\d{4}-\d+-\d+/', $array[0], $matches)) {
            $date = strtotime($matches[0]);
        }

        // 118
        if (preg_match('/\d+/', $array[1], $matches)) {
            $segment['FlightNumber'] = (int) $matches[0];
        }

        if ($this->http->XPath->query("//a[contains(@href,'.flyporter.com')]")->length > 0
            && $this->http->XPath->query("//text()[contains(.,'VIPorter')]")->length > 0) {
            //https://en.wikipedia.org/wiki/Porter_Airlines
            $segment['AirlineName'] = 'PD';
        }

        if (preg_match('/(\w+)\s*\(([A-Z]{3})\)\s*(\d+:\d+\s*(?:[AP]M)?)\s*(?:Terminal\s+(.*))?/is', $array[2], $matches)) {
            $segment['DepName'] = $matches[1];
            $segment['DepCode'] = $matches[2];
            $segment['DepDate'] = strtotime($matches[3], $date);

            if (!empty($matches[4])) {
                $segment['DepartureTerminal'] = $matches[4];
            }
        }

        if (preg_match('/(\w+)\s*\(([A-Z]{3})\)\s*(\d+:\d+\s*(?:[AP]M)?)\s*(?:Terminal\s+(.*))?/is', $array[3], $matches)) {
            $segment['ArrName'] = $matches[1];
            $segment['ArrCode'] = $matches[2];
            $segment['ArrDate'] = strtotime($matches[3], $date);

            if (!empty($matches[4])) {
                $segment['ArrivalTerminal'] = $matches[4];
            }
        }

        return $segment;
    }
}
