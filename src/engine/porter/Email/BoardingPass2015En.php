<?php

namespace AwardWallet\Engine\porter\Email;

class BoardingPass2015En extends \TAccountChecker
{
    public $mailFiles = "porter/it-4394710.eml";

    protected $result = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->result['Kind'] = 'T';

        $this->result['RecordLocator'] = $this->http->FindSingleNode('//*[contains(text(), "Confirmation number:")]/..', null, false, '/[A-Z\d]{5,6}/');
        $passengers = $this->http->FindSingleNode('//*[contains(text(), "Passenger name:")]/..', null, false, '/Passenger name:\s*(.*)/');
        $this->result['Passengers'] = preg_split('#\n#', $passengers, -1, PREG_SPLIT_NO_EMPTY);
        $this->parseSegments('//text()[contains(., "information only")]/ancestor::tr[1]//following-sibling::tr[count(.//table)>1]');

        return [
            'parsedData' => ['Itineraries' => [$this->result]],
            'emailType'  => 'BoardingPass2015En',
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && isset($headers['subject'])
                && stripos($headers['from'], 'flyporter@flyporter.com') !== false
                && stripos($headers['subject'], 'Your boarding pass is attached.') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, 'Your boarding pass is attached.') !== false
            || stripos($body, 'Your checked bag(s) must be tagged by a Porter representative at your departure airport') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@flyporter.com') !== false;
    }

    protected function parseSegments($xpath)
    {
        foreach ($this->http->XPath->query($xpath) as $value) {
            $this->result['TripSegments'][] = $this->segments($this->innerArray($value));
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

        $date = null;

        if (preg_match('/Date:\s*(\d+ \w+ \d{4})/', $text, $matches)) {
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

        if (preg_match('/Departs:\s*([^\(]*)\(([A-Z]{3})\)\s*(\d+:\d+)/', $text, $matches)) {
            $segment['DepName'] = $matches[1];
            $segment['DepCode'] = $matches[2];
            $segment['DepDate'] = strtotime($matches[3], $date);
        }

        if (preg_match('/Arrives:\s*([^\(]*)\(([A-Z]{3})\)\s*(\d+:\d+)/', $text, $matches)) {
            $segment['ArrName'] = $matches[1];
            $segment['ArrCode'] = $matches[2];
            $segment['ArrDate'] = strtotime($matches[3], $date);
        }

        return $segment;
    }
}
