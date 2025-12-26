<?php

namespace AwardWallet\Engine\goldcrown\Email;

class Itinerary1 extends \TAccountChecker
{
    public const DATE_FORMAT = 'm/d/Y';
    public const TIME_FORMAT = 'H:i';
    public $mailFiles = "goldcrown/it-1.eml";
    public $itineraries = [];

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers["from"]) && $this->СheckMail($headers["from"]);
    }

    public function detectEmailFromProvider($from)
    {
        return in_array($from, ["iTravel@bestwestern.com", "reserv@cs.bestwestern.com"]);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (stripos($parser->getHTMLBody(), 'bestwestern.com') !== false) {
            return true;
        }
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $text = orval($parser->getPlainBody(), text($parser->getHTMLBody()));
        $this->itineraries['Kind'] = 'R';

        if ($this->http->FindSingleNode('//a[@href = "https://book.bestwestern.com/bestwestern/reservationRetrieve.do?refresh=true"]')) {
            $this->itineraries['ConfirmationNumber'] = $this->http->FindPreg('/Reservation\s+Confirmation\sNumber\s*:\s+(\d+)/');

            if (preg_match('/(\d{1,2}\/\d{1,2}\/\d{1,2})(.*)\((\d{1,2}\:\d{1,2})\)/', $this->http->FindSingleNode("//*[contains(text(), 'Total Rooms')]/ancestor::tr[2]//*[contains(text(), 'Check-in')]/following::text()[1]"), $checkInMatches)) {
                $this->itineraries['CheckInDate'] = $this->buildDate(date_parse_from_format(self::TIME_FORMAT . ' ' . self::DATE_FORMAT, $checkInMatches[3] . ' ' . $checkInMatches[1]));
            }

            if (preg_match('/(\d{1,2}\/\d{1,2}\/\d{1,2})(.*)\((\d{1,2}\:\d{1,2})\)/', $this->http->FindSingleNode("//*[contains(text(), 'Total Rooms')]/ancestor::tr[2]//*[contains(text(), 'Check-out')]/following::text()[1]"), $checkOutMatches)) {
                $this->itineraries['CheckOutDate'] = $this->buildDate(date_parse_from_format(self::TIME_FORMAT . ' ' . self::DATE_FORMAT, $checkOutMatches[3] . ' ' . $checkOutMatches[1]));
            }

            $subj = implode("\n", $this->http->FindNodes('//td[contains(., "Phone:") and not(.//td)]//text()'));

            if (preg_match('#\s*(.*)\s+((?s).*)\s+Phone:\s+(.*)#i', $subj, $m)) {
                $this->itineraries['HotelName'] = $m[1];
                $this->itineraries['Address'] = nice($m[2], ',');
                $this->itineraries['Phone'] = $m[3];
            }

            $this->itineraries['GuestNames'] = [preg_replace('/Reservation\sSummary\s\-\s/', '', $this->http->FindSingleNode("//*[contains(text(), 'Reservation Summary')]"))];
            $this->itineraries['Guests'] = re('#Total\s+Occupants:\s+(\d+)#i', $text);
            $this->itineraries['Rooms'] = re('#Total\s+Rooms:\s+(\d+)#i', $text);
            $this->itineraries['Rate'] = nice(re('#Room\s+Rate:\s*(.*)#i', $text));
            $this->itineraries['CancellationPolicy'] = nice(re('#Cancellation\s+Policy:\s+(.*)#i', $text));
            $this->itineraries['RoomType'] = $this->http->FindSingleNode('//td[contains(., "Room Subtotal") and not(.//td)]/preceding-sibling::td[1]');
            $this->itineraries['RoomTypeDescription'] = nice(re('#Room\s+Details:\s+(.*)#i', $text));
            $this->itineraries['Cost'] = cost(re('#Reservation\s+Amount:\s+(.*)#i', $text));
            $this->itineraries['Taxes'] = cost(re('#Estimated\s+Taxes\s+&\s+Fees:\s+(.*)#i', $text));
            $total = re('#Total\s+Stay:\s+(.*)#i', $text);
            $this->itineraries['Total'] = cost($total);
            $this->itineraries['Currency'] = currency($total);
        } else {
            if (preg_match('/^(Confirmation\:\s)(\d*)$/', $this->http->FindSingleNode("//text()[contains(., 'Confirmation')]"), $matches)) {
                $this->itineraries['ConfirmationNumber'] = $matches[2];
            }
            $this->itineraries['HotelName'] = $this->http->FindSingleNode("//span[contains(., 'Arriving')]/text()[2]");

            if (preg_match('/(Arriving\:\s)(\d{1,2}\/\d{1,2}\/\d{4})/', $this->http->FindSingleNode("//span[contains(., 'Arriving')]/text()[7]"), $matches)) {
                $this->itineraries['CheckInDate'] = $this->buildDate(date_parse_from_format(self::DATE_FORMAT, $matches[2]));
                $this->itineraries['CheckOutDate'] = $this->itineraries['CheckInDate'];
            }
            $this->itineraries['Address'] = $this->http->FindSingleNode("//span[contains(., 'Arriving')]/text()[3]") . ', ' . $this->http->FindSingleNode("//span[contains(., 'Arriving')]/text()[4]") . ', ' . $this->http->FindSingleNode("//span[contains(., 'Arriving')]/text()[5]");
            $this->itineraries['Phone'] = $this->http->FindSingleNode("//span[contains(., 'Arriving:')]/text()[6]");

            if (preg_match('/^[^\,]*/', $this->http->FindSingleNode("//span[contains(., 'Arriving')]/text()[1]"), $GuestName)) {
                $this->itineraries['GuestNames'] = [$GuestName[0]];
            }
        }

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => [$this->itineraries],
            ],
        ];
    }

    private function СheckMail($input = '')
    {
        preg_match('/([\.@]bestwestern\.com)|([\.@]cs\.bestwestern\.com)/ims', $input, $matches);

        return (isset($matches[0])) ? true : false;
    }

    private function buildDate($parsedDate)
    {
        return mktime((isset($parsedDate['hour'])) ? $parsedDate['hour'] : 0, (isset($parsedDate['minute'])) ? $parsedDate['minute'] : 0, 0, $parsedDate['month'], $parsedDate['day'], $parsedDate['year']);
    }
}
