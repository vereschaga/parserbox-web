<?php

namespace AwardWallet\Engine\thaiair\Email;

class FlightHtml2017En extends \TAccountChecker
{
    public $mailFiles = "thaiair/it-5550639.eml";
    protected $result = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->result['Kind'] = 'T';

        foreach ($this->http->XPath->query('//*[contains(text(), "Booking Date")]/ancestor::tr[2]') as $root) {
            $this->result['RecordLocator'] = $this->http->FindSingleNode('td[1]/strong[1]', $root, false, '/[A-Z\d]{5,6}/');
            $this->result['Status'] = $this->http->FindSingleNode('td[1]/strong[2]', $root, false, '/[A-Z\s]+/');
            $this->result['ReservationDate'] = strtotime($this->http->FindSingleNode('.//*[contains(text(), "Booking Date")]/ancestor::td[1]/following-sibling::td[2]', $root, false, '/(.+?)\s*(?:$|GMT)/'));
        }

        $this->result['Passengers'] = $this->http->FindNodes('//*[contains(text(), "Passenger(s)")]/ancestor::tr[1]/following-sibling::tr[1]', null, '#\d+\.\s*(.+?)\s*(/|ROP:)#');

        $total = $this->http->FindSingleNode('//text()[contains(., "TOTAL PRICE")]/ancestor::td[1]/following-sibling::td[2]');

        if (preg_match('/([\d,.]+)\s*([A-Z]{3})/', $total, $matches)) {
            $this->result['TotalCharge'] = (float) str_replace(',', '', $matches[1]);
            $this->result['Currency'] = $matches[2];
        }

        $fare = $this->http->FindSingleNode('//text()[contains(., "Airfare")]/ancestor::td[1]/following-sibling::td[2]');

        if (preg_match('/([\d,.]+)\s*[A-Z]{3}/', $fare, $matches)) {
            $this->result['BaseFare'] = (float) str_replace(',', '', $matches[1]);
        }

        $this->parseSegments();

        return [
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'noreply@thaismileair.com') !== false
                && isset($headers['subject']) && (
                stripos($headers['subject'], 'THAI SMILE Itinerary') !== false);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'TRAVEL ITINERARY / RECEIPT') !== false
                || stripos($parser->getHTMLBody(), 'THAI SMILE AIRWAYS') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@thaismileair.com') !== false;
    }

    protected function parseSegments()
    {
        foreach ($this->http->XPath->query('//text()[normalize-space(.)="FLIGHT DETAIL"]/ancestor::tr[1]/'
                . 'following-sibling::tr[contains(., "Departure")]/following-sibling::tr') as $root) {
            $segment = [];
            $td1 = $this->http->FindSingleNode('.//td[1]', $root);
            $td2 = $this->http->FindSingleNode('.//td[2]', $root);
            $td3 = $this->http->FindSingleNode('.//td[3]', $root);
            $td4 = $this->http->FindSingleNode('.//td[4]', $root);

            if (preg_match('/([A-Z]{3})\s*([A-Z]{3})/', $td1, $matches)) {
                $segment['DepCode'] = $matches[1];
                $segment['ArrCode'] = $matches[2];
            }

            // WE 161 Smile Plus Class P
            if (preg_match('/([A-Z\d]{2})\s*(\d+)\s*([\w\s]+)\s*Class\s+([A-Z])/', $td2, $matches)) {
                $segment['AirlineName'] = $matches[1];
                $segment['FlightNumber'] = $matches[2];
                $segment['Operator'] = trim($matches[3]);
                $segment['BookingClass'] = $matches[4];
            }

            // 12:20  Chiang Mai (CNX) Chiang Mai International AirportWed 12 Jul 2017
            if (preg_match('/(\d+:\d+)\s*(.+?)\s*(\w{3} \d+ \w+ \d+)/', $td3, $matches)) {
                $segment['DepDate'] = strtotime($matches[3] . ', ' . $matches[1]);
                $segment['DepName'] = $matches[2];
            }

            if (preg_match('/(\d+:\d+)\s*(.+?)\s*(\w{3} \d+ \w+ \d+)/', $td4, $matches)) {
                $segment['ArrDate'] = strtotime($matches[3] . ', ' . $matches[1]);
                $segment['ArrName'] = $matches[2];
            }

            $this->result['TripSegments'][] = $segment;
        }
    }
}
