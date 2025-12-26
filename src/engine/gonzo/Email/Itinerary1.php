<?php

namespace AwardWallet\Engine\gonzo\Email;

class Itinerary1 extends \TAccountChecker
{
    public const DATE_FORMAT = 'm/d/Y';
    public $mailFiles = "gonzo/it-1.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers["from"]) && $headers["from"] == 'gonzoinn@gonzoinn.com';
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($this->http->Response['body'], 'Thank You for choosing The Gonzo Inn') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $textBody = $parser->getPlainBody();

        if (empty($textBody)) {
            $textBody = $parser->getHTMLBody();
        }

        $itineraries = [];
        $itineraries['Kind'] = 'R';
        $itineraries['ConfirmationNumber'] = $this->http->FindPreg('/Confirmation:\s*\#(\d+)/');
        $guestName = $this->http->FindPreg('/Dear\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[ ]*(?:[,!?]+|$)/u');

        if ($guestName) {
            $itineraries['GuestNames'] = [$guestName];
        }
        $checkInDate = $this->http->FindPreg('/Check In Date\:\s*([^\ ]+)/');
        $itineraries['CheckInDate'] = $this->buildDate(date_parse_from_format($this::DATE_FORMAT, $checkInDate));
        $checkOutDate = $this->http->FindPreg('/Check Out Date\:\s*([^\ ]+)/');
        $itineraries['CheckOutDate'] = $this->buildDate(date_parse_from_format($this::DATE_FORMAT, $checkOutDate));
        $cost = $this->http->FindPreg('/Total Room Amount:\s*(\d[,.\'\d]*)/');

        if ($cost !== null) {
            $itineraries['Cost'] = floatval($cost);
        }
        $tax = $this->http->FindPreg('/Total Tax Amount:\s*(\d[,.\'\d]*)/');

        if ($tax !== null) {
            $itineraries['Taxes'] = floatval($tax);
        }
        $total = $this->http->FindPreg('/Grand Total:\s*(\d[,.\'\d]*)/');

        if ($total !== null) {
            $itineraries['Total'] = floatval($total);
        }

        $emailLines = preg_split('/\n/', $textBody);
        //skip first lines
        while (!empty($emailLines)) {
            $firstRow = $emailLines[0];

            if (strlen($firstRow) == 0 || strpos($firstRow, 'On ') === 0 || strpos($firstRow, 'wrote:') === 0) {
                array_shift($emailLines);
            } else {
                break;
            }
        }
        $hotelName_temp = preg_replace('/\>/', '', $emailLines[0]);

        if ($this->http->XPath->query("//node()[contains(normalize-space(),'{$hotelName_temp}')]")->length > 1) {
            $itineraries['HotelName'] = $hotelName_temp;
            $itineraries['Address'] = implode(', ', [preg_replace('/\>/', '', $emailLines[1]), preg_replace('/\>/', '', $emailLines[2])]);
            $phoneAndFax = preg_split('/\//', preg_replace('/\>/', '', $emailLines[3]));
            $itineraries['Phone'] = $phoneAndFax[0];

            if (count($phoneAndFax) > 1) {
                $itineraries['Fax'] = preg_replace('/fax\s*/', '', $phoneAndFax[1]);
            }
        }
        $roomAndType = $this->http->FindPreg('/Rooms \/ Type:\s*(.*)Nights/');

        $matches = [];

        if (preg_match('/(\d+)(.*)/', $roomAndType, $matches)) {
            $itineraries['Rooms'] = intval($matches[1]);
            $itineraries['RoomType'] = $matches[2];
        }

        //skip to CANCELLATION/CHANGE POLICY
        while (!empty($emailLines)) {
            $firstRow = $emailLines[0];

            if (strpos($firstRow, 'CANCELLATION/CHANGE POLICY') !== false) {
                break;
            }
            array_shift($emailLines);
        }
        $cancellationPolicy = '';

        while (!empty($emailLines)) {
            $firstRow = $emailLines[0];

            if ($firstRow == '>' || strlen($firstRow) == 0) {
                break;
            } else {
                $cancellationPolicy .= preg_replace('/\>/', '', $firstRow);
            }
            array_shift($emailLines);
        }

        if ($cancellationPolicy) {
            $itineraries['CancellationPolicy'] = preg_replace('/CANCELLATION\/CHANGE POLICY\:\s*/', '', $cancellationPolicy);
        }

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => [$itineraries],
            ],
        ];
    }

    public function buildDate($parsedDate)
    {
        return mktime($parsedDate['hour'], $parsedDate['minute'], 0, $parsedDate['month'], $parsedDate['day'], $parsedDate['year']);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[\.@]gonzoinn\.com$/ims', $from) > 0;
    }
}
