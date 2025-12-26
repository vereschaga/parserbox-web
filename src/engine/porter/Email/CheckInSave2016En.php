<?php

namespace AwardWallet\Engine\porter\Email;

class CheckInSave2016En extends \TAccountChecker
{
    public $mailFiles = "porter/it-4383825.eml";

    protected $result = [];

    private $detects = [
        'Checking in is as easy as 1, 2, 3.',
        'Book your seat, check your bag and add your VIPorter number',
        'If you would like to change your flight, please visit',
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->result['Kind'] = 'T';
        $this->parseTrip("//td[contains(., 'Passenger information only') and ancestor::td[1]]");

        return [
            'emailType'  => 'CheckInSave2016En',
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'flyporter@flyporter.com') !== false
                && isset($headers['subject']) && stripos($headers['subject'], 'Check In & Save') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->detects as $detect) {
            if (stripos($body, $detect) !== false && stripos($body, 'porter') !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@flyporter.com') !== false;
    }

    protected function parseTrip($xpath)
    {
        $node = $this->http->XPath->query($xpath);

        if ($node->length > 0) {
            $text = $this->innerArray($node->item(0));
        }

        if (!empty($text)) {
            if (preg_match('/Confirmation number:\s*([A-Z\d]{5,6})/', $text, $matches)) {
                $this->result['RecordLocator'] = $matches[1];
            }

            if (preg_match('/Passenger name:(.*)/s', $text, $matches)) {
                $this->result['Passengers'] = preg_split('#\n#', $matches[1], -1, PREG_SPLIT_NO_EMPTY);
            }

            $this->result['TripSegments'][] = $this->segments($text);
        }
    }

    protected function innerArray(\DOMElement $element)
    {
        $array = [];

        foreach ($element->childNodes as $value) {
            // Link https://en.wikipedia.org/wiki/Non-breaking_space
            $value->nodeValue = trim(trim($value->nodeValue, chr(0xC2) . chr(0xA0)));
            $value->nodeValue = str_replace(' ', ' ', $value->nodeValue);

            if (empty($value->nodeValue) !== true) {
                $array[] = $value->nodeValue;
            }
        }

        return join(PHP_EOL, $array);
    }

    protected function segments($text)
    {
        $segment = [];

        // Departure date: 13 Feb 2016
        $date = '';

        if (preg_match('/Departure date:\s*(\d+ \w+ \d{4})/', $text, $matches)) {
            $date = strtotime($matches[1]);
        }

        if (preg_match('/Flight number:\s*(\d{3,4})/', $text, $matches)) {
            $segment['FlightNumber'] = $matches[1];
        }

        if ($this->http->XPath->query("//a[contains(@href,'.flyporter.com')]")->length > 0
            && $this->http->XPath->query("//text()[contains(.,' Porter representative')]")->length > 0) {
            //https://en.wikipedia.org/wiki/Porter_Airlines
            $segment['AirlineName'] = 'PD';
        }

        if (preg_match('/Departure city:\s*(.*)\s*\(([A-Z]{3})\)\s*(\d+:\d+)\s*[\d\D]*\s*Arrival city:\s*(.*)\s*\(([A-Z]{3})\)\s*(\d+:\d+)/i', $text, $matches)) {
            $segment['DepName'] = $matches[1];
            $segment['DepCode'] = $matches[2];
            $segment['DepDate'] = strtotime($matches[3], $date);
            $segment['ArrName'] = $matches[4];
            $segment['ArrCode'] = $matches[5];
            $segment['ArrDate'] = strtotime($matches[6], $date);
        }

        return $segment;
    }
}
