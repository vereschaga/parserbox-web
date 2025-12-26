<?php

// bcdtravel

namespace AwardWallet\Engine\sncf\Email;

class BookingHtml2017En extends \TAccountChecker
{
    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its[] = $this->parseTrain();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'Train',
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'voyages-sncf.') !== false
                && isset($headers['subject']) && preg_match('/<Ref\d+> Booking \d+/', $headers['subject']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'sncf') !== false
                && strpos($parser->getHTMLBody(), 'Please check the following details thoroughly') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'voyages-sncf.') !== false;
    }

    /**
     * Parsing is written taking into account one reservation and one segment.
     * There were not enough examples.
     *
     * @return type
     */
    protected function parseTrain()
    {
        $result['Kind'] = 'T';
        $result['TripCategory'] = TRIP_CATEGORY_TRAIN;
        $result['RecordLocator'] = $this->http->FindSingleNode('(//text()[contains(., "PNR:")])[1]', null, false, '/:\s*([A-Z\d]+)\b/');
        $result['TripNumber'] = $this->http->FindSingleNode('//text()[contains(., "Booking #:")]/ancestor::td[1]/following-sibling::td[1]', null, false, '/\d+/');
        $result['ReservationDate'] = strtotime(str_replace('/', '-', $this->http->FindSingleNode('//text()[contains(., "Date:")]/ancestor::td[1]/following-sibling::td[1]')), false);
        $result['Passengers'][] = $this->http->FindSingleNode('(//text()[contains(., "Passengers:")])[1]', null, false, '/:\s*\d+ in party ([[:alpha:]\s]+)/');

        $result['Currency'] = $this->http->FindSingleNode('//text()[contains(., "Total Gross Amount")]/ancestor::td[1]/following-sibling::td[1]', null, false, '/\b[A-Z]{3}\b/');
        $result['TotalCharge'] = (float) $this->http->FindSingleNode('//text()[contains(., "Total Gross Amount")]/ancestor::td[1]/following-sibling::td[2]');

        // TripSegments
        $i = ['DepCode' => TRIP_CODE_UNKNOWN, 'ArrCode' => TRIP_CODE_UNKNOWN];
        $i['FlightNumber'] = $this->http->FindSingleNode('(//text()[contains(., "Train No:")])[1]', null, false, '/:\s*([A-Z]+ \d+)/');
        $i['Seats'] = $this->http->FindSingleNode('(//text()[contains(., "Seat:")])[1]', null, false, '/Seat:\s*(\w+)\b/');

        $depName = $this->http->FindSingleNode('//text()[contains(., "Departure:")]');
        $arrName = $this->http->FindSingleNode('//text()[contains(., "Arrival:")]');

        if (preg_match('/Departure:\s*([\w\s]+)\s+on [A-Z]+\s+(.+? at [\d:]+(:?\s*[ap\.]m\b)?)/', $depName, $matches)) {
            $i['DepName'] = $matches[1];
            $i['DepDate'] = strtotime(str_replace(['/', ' at '], ['-', ', '], $matches[2]), false);
        }

        if (preg_match('/Arrival:\s*([\w\s]+)\s+on [A-Z]+\s+(.+? at [\d:]+(:?\s*[ap\.]m\b)?)/', $arrName, $matches)) {
            $i['ArrName'] = $matches[1];
            $i['ArrDate'] = strtotime(str_replace(['/', ' at '], ['-', ', '], $matches[2]), false);
        }

        $result['TripSegments'][] = $i;

        return $result;
    }
}
