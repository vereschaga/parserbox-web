<?php

namespace AwardWallet\Engine\turkish\Email;

class Itinerary1 extends \TAccountChecker
{
    public const RESERVATION_DATE_FORMAT = 'd M Y H:i';
    public const TRIP_DATE_FORMAT = 'd.m.Y / H:i';

    public function detectEmailByHeaders(array $headers)
    {
        return (isset($headers["from"]) && $headers["from"] === 'please_do_not_reply@thy.com')
                || stripos($headers['subject'], 'Turkish Airlines Online Ticket') !== false
                || stripos($headers['subject'], 'Turk Hava Yollari Online Bilet') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($this->http->Response['body'], 'Turkish Airlines Online') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $type = $this->getEmailType($parser);
        $itineraries = [];
        $itineraries['Kind'] = 'T';

        switch ($type) {
            case 'html': $this->parseHtmlEmail($itineraries);

break;

            case 'text': $this->parseTextEmail($itineraries);

break;
        }

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => [$itineraries],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public function buildDate($parsedDate)
    {
        return mktime($parsedDate['hour'], $parsedDate['minute'], 0, $parsedDate['month'], $parsedDate['day'], $parsedDate['year']);
    }

    public function parseFloat($value)
    {
        return floatval(preg_replace('/,/', '', $value));
    }

    public function getEmailType(\PlancakeEmailParser $parser)
    {
        $attach = arrayVal($parser->getAttachments(), 0);

        if (!$attach) {
            return;
        }
        $type = '';

        if (isset($attach['headers']['content-disposition'])) {
            $type = $attach['headers']['content-disposition'];
        }

        if (stripos($type, 'html')) {
            return 'html';
        }

        if (stripos($type, 'zip')) {
            return 'text';
        }

        return "unknown";
    }

    public function parseHtmlEmail(&$itineraries)
    {
        $itineraries['RecordLocator'] = $this->http->FindSingleNode('//td[contains(text(), "Reservation Code")]/following-sibling::td[1]');
        $passengerInfo = $this->http->XPath->query("//div/table[4]/tbody/tr[2]/td/table")->item(0);
        $itineraries['Passengers'] = join(', ', array_unique(array_slice($this->http->FindNodes(".//td[2]", $passengerInfo), 1)));
        $total = preg_split('/ /', $this->http->FindSingleNode('//td[contains(text(), "Total Amount")]/following-sibling::td[1]'));
        $itineraries['TotalCharge'] = $this->parseFloat($total[0]);
        $itineraries['Currency'] = $total[1];
        $reservationDate = $this->http->FindSingleNode('//td[contains(text(), "Process date")]/following-sibling::td[1]');
        $itineraries['ReservationDate'] = $this->buildDate(date_parse_from_format($this::RESERVATION_DATE_FORMAT, $reservationDate));

        $segments = [];

        $tripRows = $this->http->XPath->query('//td[contains(text(), "Itinerary")]/../following-sibling::tr[1]//table/tbody/tr');

        for ($i = 1; $i < $tripRows->length - 1; $i++) {
            $row = $tripRows->item($i);
            $tripSegment['FlightNumber'] = $this->http->FindSingleNode("./td[1]", $row);
            $departNode = preg_split("/\n/", $this->http->XPath->query("./td[2]", $row)->item(0)->textContent);
            $tripSegment['DepName'] = trim(preg_split('/\//', $departNode[2])[0]);
            $depTime = trim($departNode[1]);
            $tripSegment['DepDate'] = $this->buildDate(date_parse_from_format($this::TRIP_DATE_FORMAT, $depTime));
            $arrivalNode = preg_split("/\n/", $this->http->XPath->query("./td[3]", $row)->item(0)->textContent);
            $tripSegment['ArrName'] = trim(preg_split('/\//', $arrivalNode[2])[0]);
            $arrivalTime = trim($arrivalNode[1]);
            $tripSegment['ArrDate'] = $this->buildDate(date_parse_from_format($this::TRIP_DATE_FORMAT, $arrivalTime));
            $tripSegment['Cabin'] = $this->http->FindSingleNode("./td[4]", $row);
            $tripSegment['BookingClass'] = $this->http->FindSingleNode("./td[5]", $row);
            $tripSegment['DepCode'] = $tripSegment['ArrCode'] = TRIP_CODE_UNKNOWN;
            $segments[] = $tripSegment;
        }

        $itineraries['TripSegments'] = $segments;
    }

    public function parseTextEmail(&$itineraries)
    {
        $itineraries['RecordLocator'] = $this->http->FindPreg('/Reservation Code\s*([^<]*)/');
        $total = preg_split('/ /', $this->http->FindPreg("/Total Amount\\s*(((\\d|\\,|\\.)*)\\s*([^\\s]*))/"));
        $itineraries['TotalCharge'] = $this->parseFloat($total[0]);

        if (!isset($total[1])) {
            return;
        }
        $itineraries['Currency'] = $total[1];
        $reservationDate = $this->http->FindPreg("/Process date\\s*([^\\s]*\\s*[^\\s]*\\s*[^\\s]*\\s*[^\\s]*)/");
        $itineraries['ReservationDate'] = $this->buildDate(date_parse_from_format($this::RESERVATION_DATE_FORMAT, $reservationDate));

        $segments = [];

        $rows = preg_split('/\n/', $this->http->Response['body']);

        //skip until data
        while (count($rows) > 0) {
            if ($rows[0] == 'Itinerary') {
                array_shift($rows);
                array_shift($rows);
                array_shift($rows);

                break;
            }
            array_shift($rows);
        }

        $matches = [];
        //parse itineraries
        while (count($rows) > 0) {
            $tripSegment = [];

            if (preg_match('/(\S+)\s+(\S+)\s+(.*)/', $rows[0], $matches)) {
                $tripSegment['FlightNumber'] = $matches[2];
                $depDate = $matches[3];
                $tripSegment['DepDate'] = $this->buildDate(date_parse_from_format($this::TRIP_DATE_FORMAT, $depDate));
            }

            if (preg_match('/([^\/]+)\/.*(\d{1,2}\.\S+.*)/', $rows[1], $matches)) {
                $tripSegment['DepName'] = $matches[1];
                $arrDate = $matches[2];
                $tripSegment['ArrDate'] = $this->buildDate(date_parse_from_format($this::TRIP_DATE_FORMAT, $arrDate));
            }

            if (preg_match('/([^\/]+)\/.*\s+(\S+)$/', $rows[2], $matches)) {
                $tripSegment['ArrName'] = $matches[1];
                $tripSegment['Cabin'] = $matches[2];
            }

            $tripSegment['DepCode'] = $tripSegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            $segments[] = $tripSegment;

            if (stripos($rows[4], "to view the fare rules of your ticket") !== false) {
                break;
            }

            array_shift($rows);
            array_shift($rows);
            array_shift($rows);
            array_shift($rows);
        }

        $itineraries['TripSegments'] = $segments;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[\.@]thy\.com$/ims', $from);
    }
}
