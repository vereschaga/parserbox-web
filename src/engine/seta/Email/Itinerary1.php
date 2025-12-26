<?php

namespace AwardWallet\Engine\seta\Email;

class Itinerary1 extends \TAccountCheckerExtended
{
    public $mailFiles = "seta/it-2638043.eml, seta/it-2638603.eml, seta/it-2650574.eml, seta/it-2703218.eml, seta/it-2703224.eml, seta/it-2845954.eml";
    public $reBody = "Logic Information Systems";
    public $reFrom = "/logicinfo\.com/";

    public function parsePdf(&$itineraries)
    {
        $it = [];

        $text = text($this->http->Response['body']);
        // echo $text;
        // die();

        $table1 = $this->parseTable("#Dados de Hospedagem:\n(.*?\n)\s+Tipo de Pagamento#ms", $text, 7);
        $table2 = $this->parseTable("#\n\s+(Tipo de Pagamento.*?\n)\s+Obs#ms", $text, 5);

        $it['Kind'] = "R";

        // ConfirmationNumber
        if (!isset($table2[1][2])) {
            return null;
        }
        $it['ConfirmationNumber'] = trim($table2[1][2], ' .');

        // TripNumber
        $it['TripNumber'] = trim(re("#VOUCHER Nº\s+(\d+)#ms", $text));

        // ConfirmationNumbers

        // Hotel Name
        $it['HotelName'] = trim(re("#Para \(To\):\n(.*?)\n#ms", $text));

        // 2ChainName

        // CheckInDate
        $it['CheckInDate'] = strtotime(str_replace("/", ".", $table1[1][3]));

        // CheckOutDate
        $it['CheckOutDate'] = strtotime(str_replace("/", ".", $table1[1][4]));

        // Address
        $it['Address'] = trim(re("#Endereço \(Address\):\n(.*?)\n#ms", $text));

        // DetailedAddress

        // Phone
        $it['Phone'] = trim(re("#Fone:\s+(.*?)\n#ms", $text));

        // Fax
        // GuestNames
        // Guests
        // Kids
        // Rooms
        // Rate
        // RateType

        // RoomType
        $it['RoomType'] = $table2[1][1];

        // RoomTypeDescription
        // Cost
        // Taxes
        // Total
        // Currency
        // SpentAwards
        // EarnedAwards
        // AccountNumbers
        // Status
        // Cancelled
        // ReservationDate
        $it['ReservationDate'] = strtotime(str_replace('/', '.', $table2[1][3]));
        // NoItineraries
        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($this->reFrom) && isset($headers['from']) && preg_match($this->reFrom, $headers["from"]);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                $this->http->SetBody($html);
                $this->parsePdf($itineraries);
            } else {
                $this->http->Log("Cant parse PDF $pdf");
            }
        }

        $result = [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    private function reAll($reg, $text, $index = 1)
    {
        preg_match_all($reg, $text, $result, PREG_PATTERN_ORDER);

        return $result[$index];
    }

    private function parseTable($reg, $text, $c)
    {
        $table = re($reg, $text);

        $table = $this->reAll("#(" . str_repeat(".*?\n", $c) . ")#ms", $table);

        foreach ($table as $key => $row) {
            $row = trim($row);
            $colls = explode("\n", $row);

            foreach ($colls as $key2=> $val) {
                $colls[$key2] = trim($val);
            }
            $table[$key] = $colls;
        }

        return $table;
    }
}
