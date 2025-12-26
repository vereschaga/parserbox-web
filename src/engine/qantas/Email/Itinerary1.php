<?php

namespace AwardWallet\Engine\qantas\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "qantas/it-1.eml, qantas/it-1665666.eml, qantas/it-4.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $emailType = $this->getEmailType($parser);
        $result = [];

        switch ($emailType) {
            case "CarRentalInfo":
            $this->http->SetBody($parser->getHTMLBody());
            $result = $this->ParseEmailCarRental($parser);

            break;

            case "DepartureInfo":
            $this->http->SetBody($parser->getHTMLBody());
            $result = $this->ParseEmailDepartureInfo($parser);

            break;
        }

        return [
            'parsedData' => [
                'Itineraries' => [$result],
            ],
            'emailType' => $emailType,
        ];
    }

    public function getEmailType(\PlancakeEmailParser $parser)
    {
        if (preg_match('/Qantas\s+Departure\s+Information/i', $parser->getSubject())) {
            return "DepartureInfo";
        }

        if (preg_match('/Your car rental confirmation/i', $parser->getSubject())) {
            return "CarRentalInfo";
        }

        return "Undefined";
    }

    public function toText($html)
    {
        $nbsp = '&' . 'nbsp;';
        $html = preg_replace("#[^\w\d\t\r\n\* :;,./\(\)\[\]\{\}\-\\\$+=_<>&\#%^&!]#", ' ', $html);

        $html = preg_replace("#<t(d|h)[^>]*>#uims", "\t", $html);
        $html = preg_replace("#&\#160;#ums", " ", $html);
        $html = preg_replace("#$nbsp#ums", " ", $html);
        $html = preg_replace("#<br/*>#uims", "\n", $html);
        $html = preg_replace("#<[^>]*>#ums", " ", $html);
        $html = preg_replace("#\n\s+#ums", "\n", $html);
        $html = preg_replace("#\s+\n#ums", "\n", $html);
        $html = preg_replace("#\n+#ums", "\n", $html);

        return $html;
    }

    public function extractPDF($parser, $wildcard = null)
    {
        $pdfs = $parser->searchAttachmentByName($wildcard ? $wildcard : '.*pdf');
        $pdf = "";

        foreach ($pdfs as $pdfo) {
            if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdfo), \PDF::MODE_SIMPLE)) !== null) {
                $pdf .= $html;
            }
        }

        return $pdf;
    }

    public function ParseEmailCarRental(\PlancakeEmailParser $parser)
    {
        $it = ['Kind' => 'L'];

        $it['RenterName'] = $this->http->FindSingleNode("//*[contains(text(), 'Driver:')]/ancestor::td[1]/following-sibling::td[1]");
        $it['Number'] = $this->http->FindSingleNode("//*[contains(text(), 'Confirmation Number:')]/ancestor::td[1]/following-sibling::td[1]");
        $it['RentalCompany'] = $this->http->FindSingleNode("//*[contains(text(), 'Confirmation Number:')]/ancestor::td[1]", null, true, "#^(.*?)\s+Confirmation Number#");
        $it['AccountNumbers'] = implode(', ', $this->http->FindNodes("//*[contains(text(), 'Confirmation Number:')]/ancestor::td[1]/following-sibling::td[1]"));

        $it['CarModel'] = $this->http->FindSingleNode("//*[contains(text(), 'Car Type')]/ancestor::tr[2]/following-sibling::tr[2]//tr[2]/td[2]");

        $up = $this->http->FindSingleNode("//*[contains(text(), 'Car Type')]/ancestor::tr[2]/following-sibling::tr[2]//tr[2]/td[8]");
        $off = $this->http->FindSingleNode("//*[contains(text(), 'Car Type')]/ancestor::tr[2]/following-sibling::tr[2]//tr[2]/td[10]");

        if (preg_match("#^(\d+\s+\w{3}\s+\d+\s+\d+)\s+(.*)\s+(Tel:\s*([+\-\d\s]+))*$#ms", $up, $m)) {
            $it['PickupDatetime'] = strtotime($m[1]);
            $it['PickupLocation'] = $m[2];
            $it['PickupPhone'] = $m[4] ?? null;
        }

        if (preg_match("#^(\d+\s+\w{3}\s+\d+\s+\d+)(\s+.*)*(\s+Tel:\s*([+\-\d\s]+))*$#ms", $off, $m)) {
            $it['DropoffDatetime'] = strtotime($m[1]);
            $it['DropoffLocation'] = $m[2] ?? $it['PickupLocation'];
            $it['DropoffPhone'] = $m[4] ?? $it['PickupPhone'];
        }

        $total = $this->http->FindSingleNode("//*[contains(text(), 'Total Estimated Price:')]/ancestor::td[1]/following-sibling::td[2]");
        $it['TotalCharge'] = preg_replace("#[^\d.]#", '', $total);
        $it['Currency'] = preg_replace("#[\d.\s]#", '', $total);

        $r = [];
        $fees = $this->http->XPath->query("//*[contains(text(), 'Total Estimated Price:')]/ancestor::tr[1]/following-sibling::tr//*[contains(text(), 'Fee')]/ancestor::td[1]");

        for ($i = 0; $i < $fees->length; $i++) {
            $td = $fees->item($i);

            $name = $this->http->FindSingleNode(".", $td);
            $value = $this->http->FindSingleNode("following-sibling::td[2]", $td);

            $r[] = ['Name' => $name, 'Charge' => $value];
        }

        $it['Fees'] = $r;
        $it['TotalTaxAmount'] = $this->http->FindSingleNode("//*[contains(text(), 'Sales Tax')]/ancestor::td[1]/following-sibling::td[2]");

        return $it;
    }

    public function ParseEmailDepartureInfo(\PlancakeEmailParser $parser)
    {
        $it = ['Kind' => 'T'];

        $pdf = $this->extractPDF($parser, '.');
        $text = $this->toText($pdf);

        $it['RecordLocator'] = preg_match("#\nYour Booking Reference\s+([^\s]+)#i", $text, $m) ? $m[1] : null;

        $names = [];

        if (preg_match("#Ticket Total\*(.*?)\s+Ticket Total for all passengers#ms", $text, $m)) {
            preg_replace_callback("#(?![a-zA-Z]+)\n([A-Za-z]+[^\n]+)\n(\d+|\w+\d+)#ms", function ($m) use (&$names) {
                if (!preg_match("#\d#", $m[1])) {
                    $name = trim(preg_replace("#\([^\)]+\)#", '', $m[1]));
                    $names[$name] = 1;
                }
            }, '100.500' . $m[1]);
        }

        if ($names) {
            $it['Passengers'] = $names;
        }

        $it['TripSegments'] = [];

        $trip = preg_match("#Status\s+Flight Information(.*?)Your Receipt Details#ms", $text, $m) ? $m[1] : '';

        preg_replace_callback("#\n(\d+\s+\w{3}\s+\d+)\s+([\w\d]+)\s+([^\n]+)\s+([^\n]+)\s+([^\n]+)\s+Est journey Time:\s*(\d+:\d+)\s+\d+,\s*(\d+:\d+\w{2})\s+\d+,\s*(\d+:\d+\w{2})\s+([^\n]+)\s+([^\n]+)(?:\s+Terminal\s+\d+)*\s+(\d+\s+\w{3}\s+\d+)\s+Aircraft Type:\s*([^\n]+)#ms",
                              function ($m) use (&$it) {
                                  $seg = [];

                                  $seg['FlightNumber'] = $m[2];

                                  $seg['DepName'] = $m[3];
                                  $seg['DepDate'] = strtotime($m[1] . ', ' . $m[7]);
                                  $seg['DepCode'] = TRIP_CODE_UNKNOWN;

                                  $seg['ArrName'] = $m[4];
                                  $seg['ArrDate'] = strtotime($m[11] . ', ' . $m[8]);
                                  $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

                                  $seg['Duration'] = $m[6];
                                  $seg['Cabin'] = $m[5];
                                  $seg['Stops'] = $m[10] == 'Non-Stop' ? 0 : $m[10];

                                  $seg['Aircraft'] = $m[12];

                                  $it['TripSegments'][] = $seg;
                              }, $trip);

        return $it;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return (isset($headers["from"]) && preg_match("/@yourbooking\.qantas\.com\.au/i", $headers['from']))
            || (isset($headers['subject']) && preg_match("/Qantas Departure Information|Qantas Airways/i", $headers['subject']));
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), '<b>From:</b> "Qantas') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[\.@]qantas\.com/ims', $from);
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }
}
