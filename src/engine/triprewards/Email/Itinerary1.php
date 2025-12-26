<?php

namespace AwardWallet\Engine\triprewards\Email;

class Itinerary1 extends \TAccountChecker
{
    public const DATE_FORMAT = 'm-d-y';
    public const DATE_PDFFORMAT = 'm/d/Y';

    public function detectEmailByHeaders(array $headers)
    {
        return (isset($headers["from"]) && in_array($headers["from"], ["donotreply@wyn.com", "reservationconfirmation@wyndhamvacationresorts.com", "Reservations@wyndham.com"]))
                || stripos($headers['subject'], 'Wyndham') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return stripos($body, 'wyndhamrewards.com') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];
        $itineraries['Kind'] = 'R';

        $result = [];

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) < 1) {/*
            if($this->http->FindPreg('/Your room reservation has been confirmed/')) {
                $this->parseFullLetter($itineraries);
            }
            else {*/
            $this->parseShortLetter($itineraries);
            //}
            $result = [$itineraries];
        } else {
            foreach ($pdfs as $pdf) {
                if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                    $http = $this->http;
                    $http->SetBody($html);
                    $it = [];
                    $it['Kind'] = 'R';
                    $this->parsePdf($it);
                    $result[] = $it;
                }
            }
        }

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => $result,
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 3;
    }

    public function getDateFormat($date)
    {
        $matches = [];

        if (preg_match('/(A|P)M$/i', $date, $matches)) {
            return "l, m/d/Y g:i " . ($matches[0] == 'AM' ? 'a' : 'A');
        }

        return '';
    }

    public function buildDate($parsedDate)
    {
        return mktime($parsedDate['hour'], $parsedDate['minute'], 0, $parsedDate['month'], $parsedDate['day'], $parsedDate['year']);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[\.@]wyn\.com$/ims', $from);
    }

    /*
        function parseFullLetter(&$itineraries) {
            $itineraries['ConfirmationNumber'] = $this->http->FindPreg('/Confirmation Number:\s*<[^>]*>([^<]*)/');
            $itineraries['HotelName'] = $this->http->FindSingleNode("(//body/div/div/table/tbody/tr/td/table/tbody/tr/td/h4
                                                                    | //h4)[1]");
            $checkInDate = preg_replace('/ (before|after)/i', '', $this->http->FindSingleNode("//strong[contains(text(), 'Check in')]/../../td[2]"));
            $checkInParsedDate = date_parse_from_format($this->getDateFormat($checkInDate), $checkInDate);
            $itineraries['CheckInDate'] = $this->buildDate($checkInParsedDate);
            $itineraries['GuestNames'] = $this->http->FindSingleNode("//*[contains(text(), 'Name:')]/following::text()[1]");
            $checkOutDate = preg_replace('/ (before|after)/i', '', $this->http->FindSingleNode("//strong[contains(text(), 'Check Out')]/../../td[2]"));
            $checkOutParsedDate = date_parse_from_format($this->getDateFormat($checkOutDate), $checkOutDate);
            $itineraries['CheckOutDate'] = $this->buildDate($checkOutParsedDate);
            if($headNode = $this->http->XPath->query('//h4[1]')->item(0)){
                $itineraries['Address'] = join(', ', array_filter($this->http->FindNodes('./following::tr[3][preceding-sibling::node()[self::tr][1][.//img]]//text()', $headNode), 'strlen'));
            }
            $itineraries['Phone'] = $this->http->FindPreg('/Phone:\s*([^\<]*)/i');
            $itineraries['Fax'] = $this->http->FindPreg('/Fax:\s*([^\<]*)/i');
            $itineraries['Guests'] = intval($this->http->FindPreg('/\d+\s*Adult\(s\)/i'));
            $itineraries['Kids'] = intval($this->http->FindPreg('/\d+\s*Ch[^\d]*13-17/i')) + intval($this->http->FindPreg('/\d+\s*Ch[^\d]*0-12/i'));
            $itineraries['Rooms'] = intval($this->http->FindPreg('/\d+\s*Room\(s\)/i'));
            $itineraries['RoomType'] = $this->http->FindSingleNode("//strong[contains(text(), 'Reservation')]/../../td[2]");
            if(count($cost = preg_split('/ /', $this->http->FindSingleNode("//div/div/table//tr/td[2]/table/tbody/tr/td/table//tr[4]/td/div/table//tr/td/div/p[2]/span[2]"))) == 2){
                $itineraries['Cost'] = floatval($cost[0]);
                $itineraries['Currency'] = $cost[1];
            }
            $itineraries['Taxes'] = floatval(($split = preg_split('/ /', $this->http->FindSingleNode("//th[contains(text(), 'Tax')]/../../..//tr/td[2]"))) ? $split[0] : null);
            $itineraries['Taxes'] = floatval(($split = preg_split('/ /', $this->http->FindSingleNode("//th[contains(text(), 'Total')]/../../..//tr/td[3]"))) ? $split[0] : null);
            $itineraries['AccountNumbers'] = $this->http->FindSingleNode('//span[@id="ecxLogin_confirmation1"]/text()');
        }
    */
    public function parseShortLetter(&$itineraries)
    {
        $link = $this->http->FindSingleNode("//td[contains(text(), 'Resort Confirmation Letter')]/../../tr[2]/td[2]//a/@href");

        if (empty($link)) {
            return;
        }
        $this->http->GetURL($link);
        $pdfLink = $this->http->FindPreg("/window.location.href = '([^']*)'/");

        if (empty($pdfLink)) {
            return;
        }
        $this->http->GetURL($pdfLink);

        if (($html = \PDF::convertToHtml($this->http->Response['body'], \PDF::MODE_COMPLEX)) !== null) {
            $http = $this->http;
            $http->SetBody($html);
            $itineraries['ConfirmationNumber'] = $this->http->FindPreg('/Confirmation Number: <\/b>([^<]*)/');
            $itineraries['HotelName'] = $this->http->FindPreg('/Directions to ([^<]*)/');
            $itineraries['AccountNumbers'] = $this->http->FindPreg('/Member Number: <\/b>([^<]*)/');
            $itineraries['CheckInDate'] = $this->stringDateToUnixtime($this->http->FindPreg('/Arrival Date: <\/b>([^<]*)/'), $this::DATE_PDFFORMAT);
            $itineraries['CheckOutDate'] = $this->stringDateToUnixtime($this->http->FindPreg('/Departure Date: <\/b>([^<]*)/'), $this::DATE_PDFFORMAT);
            $itineraries['Address'] = $this->http->FindPreg('/Check-in Address:<br\/><\/b>([^<]*)/');
            $itineraries['Phone'] = $this->http->FindPreg('/Resort Phone Number:<br\/><\/b>([^<]*)/');
            $itineraries['RoomType'] = preg_replace('/<[^>]+>/', ' ', $this->http->FindPreg('/Unit Type\/Description: <\/b>(.*)<\/P>/'));
        }
    }

    public function parsePdf(&$itineraries)
    {
        $itineraries['GuestNames'] = $this->http->FindPreg('/guest reservation for: <b> ([^<]*)<\/b>/');
        $itineraries['ConfirmationNumber'] = $this->http->FindPreg("/<b>Confirmation No. <\/b><br\/>\n<b>([^<]*)<\/b>/");
        $arrDate = $this->http->FindPreg('/<b>Arrival Date:       <\/b><br\/>\n<b>([^<]*)<\/b><br\/>/');
        $itineraries['CheckInDate'] = $this->stringDateToUnixtime($arrDate);
        $depDate = $this->http->FindPreg('/<b>Departure Date:  <\/b><br\/>\n<b>([^<]*)<\/b><br\/>/');
        $itineraries['CheckOutDate'] = $this->stringDateToUnixtime($depDate);

        if (count($people = preg_split('/\//', $this->http->FindPreg('/<b>Adults\/Children:  <\/b><br\/>\n<b>([^<]*)<\/b><br\/>/'))) == 2) {
            $itineraries['Guests'] = intval(trim($people[0]));
            $itineraries['Kids'] = intval(trim($people[1]));
        }
        $rooms = $this->http->FindPreg('/<b>No. of Rooms:     <\/b><br\/>\n<b>([^<]*)<\/b><br\/>/');
        $itineraries['Rooms'] = intval($rooms);
        $itineraries['RoomTypeDescription'] = $this->http->FindPreg('/<b>Room Description:   <\/b><br\/>\n([^<]*)\./');
        $itineraries['Cost'] = floatval($this->http->FindPreg('/<b>Total cost for accommodations, including taxes \(and resort fees if applicable\):<\/b><br\/>\n\S{6}([^<]*)<br\/>/u'));

        if (count($hotelInfo = preg_split('/ - /', $this->http->FindPreg('/Thank you for choosing the <b>([^<]*)<\/b>/'))) == 2) {
            $itineraries['HotelName'] = $hotelInfo[1];
            $itineraries['Address'] = $hotelInfo[0];
        } else {
            $itineraries['HotelName'] = $this->http->FindSingleNode('//text()[contains(., "Thank you for choosing")]/following::b[1]');

            if (!empty($itineraries['HotelName'])) {
                $itineraries['Address'] = $this->http->FindSingleNode("//a[contains(., '{$itineraries['HotelName']}')]/following::a[1]");

                if (preg_match('/Tel\s*:\s*([\d-\s]+)\s*(\|\s*Fax\s*:\s*([\d-\s]+))?/ims', $this->http->FindSingleNode("//a[contains(., '{$itineraries['HotelName']}')]/following::a[2]"), $matches)) {
                    $itineraries['Phone'] = trim($matches[1]);

                    if (!empty($matches[3])) {
                        $itineraries['Fax'] = trim($matches[3]);
                    }
                }
            }
        }
    }

    public function stringDateToUnixtime($date, $format = self::DATE_FORMAT)
    {
        $date = date_parse_from_format($this::DATE_FORMAT, $date);

        return mktime($date['hour'], $date['minute'], 0, $date['month'], $date['day'], $date['year']);
    }
}
