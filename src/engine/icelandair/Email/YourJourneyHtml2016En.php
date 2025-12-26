<?php

namespace AwardWallet\Engine\icelandair\Email;

class YourJourneyHtml2016En extends \TAccountChecker
{
    public $mailFiles = "icelandair/it-4527353.eml, icelandair/it-9954629.eml";

    private $result = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->result['Kind'] = 'T';
        $this->parseSegments();

        return [
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'icelandair@noreply.icelandair.is') !== false
                && isset($headers['subject']) && (
                stripos($headers['subject'], 'Your journey with us has begun') !== false);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'Your trip starts here') !== false
                || $this->http->XPath->query('//*[contains(@src, "ice_2014_flight_nobg")]')->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@noreply.icelandair.is') !== false;
    }

    protected function parseSegments()
    {
        $recordLocator = [];

        foreach ($this->http->XPath->query('//*[contains(normalize-space(text()), "Your booking")]/ancestor::tr[1]') as $value) {
            $value->nodeValue = str_replace(' ', ' ', $value->nodeValue);

            if (preg_match('/Your\s*booking\s*reference\s*([A-Z\d]{5,6})/s', $value->nodeValue, $matches)) {
                $recordLoctor[] = $matches[1];
            }
            $this->result['TripSegments'][] = $this->parseSegment($value->nodeValue);
        }

        if (isset($recordLoctor) && count(array_unique($recordLoctor)) === 1) {
            $this->result['RecordLocator'] = current($recordLoctor);
        } else {
            $this->http->Log('RecordLocator > 1', LOG_LEVEL_ERROR);
        }
    }

    protected function parseSegment($text)
    {
        // FI  205  CPH KEF  23/09/2016 Terminal Dep. Arr. 3  14:00 15:10
        $preg = '([A-Z]{2})?\s*(\d+)\s+([A-Z]{3})\s+([A-Z]{3})\s+';
        $preg .= '(\d+/\d+/\d+).*?(\d{1,2})?\s+(\d+:\d+)\s+(\d+:\d+)';

        if (preg_match("#{$preg}#s", $text, $matches)) {
            return [
                'AirlineName'       => $matches[1],
                'FlightNumber'      => $matches[2],
                'DepCode'           => $matches[3],
                'ArrCode'           => $matches[4],
                'DepDate'           => strtotime(str_replace('/', '.', $matches[5]) . ' ' . $matches[7]),
                'ArrDate'           => strtotime(str_replace('/', '.', $matches[5]) . ' ' . $matches[8]),
                'DepartureTerminal' => $matches[6],
            ];
        }
    }
}
