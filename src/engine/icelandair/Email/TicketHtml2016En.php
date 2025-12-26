<?php

namespace AwardWallet\Engine\icelandair\Email;

//might better to look at YourFlightTicket.php (seem's one format)
class TicketHtml2016En extends \TAccountChecker
{
    protected $result = [];

    public function increaseYear($dateLetter, $dateSegment, $depTime, $arrTime)
    {
        $date = strtotime($dateSegment, $dateLetter);

        $depDate = strtotime($depTime, $date);

        if ($dateLetter > $depDate) {
            $depDate = strtotime('+1 year', $depDate);
        }

        return [
            'DepDate' => $depDate,
            'ArrDate' => strtotime($arrTime, $depDate),
        ];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());
        $this->result['Kind'] = 'T';

        $this->parseReservation();
        $this->result += total($this->http->FindSingleNode('//text()[contains(., "Total airfare:")]'));
        $this->parseSegments();

        return [
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'vildarbokanir@icelandair.is') !== false
                && isset($headers['subject']) && (
                stripos($headers['subject'], 'Flugmiðinn þinn: ') !== false);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'ELECTRONIC TICKET') !== false
                || strpos($parser->getHTMLBody(), 'PASSENGER ITINERARY RECEIPT') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@icelandair.is') !== false;
    }

    protected function parseReservation()
    {
        $recordLoctor = [];

        foreach ($this->http->XPath->query('//text()[contains(., "Ticket Number:")]/ancestor::tr[1]') as $value) {
            if (preg_match('#Name:.*?/(.*)#', $value->nodeValue, $matches)) {
                $this->result['Passengers'][] = $matches[1];
            }

            if (preg_match('/Ticket Number:\s*(\d{5,})/', $value->nodeValue, $matches)) {
                $this->result['TicketNumbers'][] = $matches[1];
            }

            if (preg_match('/Booking reference:\s*(.*)\s*/', $value->nodeValue, $matches)) {
                $recordLoctor[] = $matches[1];
            }

            if (preg_match('/Date of issue:\s*(\d+ \w+ \d+)/', $value->nodeValue, $matches)) {
                $this->result['ReservationDate'] = strtotime($matches[1]);
            }
        }

        if (count(array_unique($recordLoctor)) === 1) {
            $this->result['RecordLocator'] = current($recordLoctor);
        } else {
            $this->http->Log('RecordLocator > 1', LOG_LEVEL_ERROR);
        }
    }

    protected function parseSegments()
    {
        foreach ($this->http->XPath->query('//*[contains(text(), "Dep Time")]/ancestor::tr[1]/following-sibling::tr[not(td[contains(@colspan, "13")])]') as $value) {
            $this->result['TripSegments'][] = $this->parseSegment($value);
        }
    }

    protected function parseSegment(\DOMElement $element)
    {
        $flightNumber = $this->http->FindSingleNode('td[1]', $element);

        if (preg_match('/([A-Z]{2})(\d+)/', $flightNumber, $matches)) {
            $segment = $this->increaseYear($this->date, $this->http->FindSingleNode('td[4]', $element), $this->http->FindSingleNode('td[7]', $element), $this->http->FindSingleNode('td[8]', $element));

            return [
                'AirlineName'       => $matches[1],
                'FlightNumber'      => $matches[2],
                'DepartureTerminal' => $this->http->FindSingleNode('td[6]', $element),
                'DepCode'           => $this->http->FindSingleNode('td[2]', $element),
                'ArrCode'           => $this->http->FindSingleNode('td[3]', $element),
                'BookingClass'      => $this->http->FindSingleNode('td[5]', $element),
                'Seats'             => $this->http->FindSingleNode('td[9]', $element), ] + $segment;
        }
    }
}
