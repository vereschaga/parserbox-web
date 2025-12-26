<?php

namespace AwardWallet\Engine\korean\Email;

class Itinerary1 extends \TAccountChecker
{
    public const DATE_FORMAT = 'd M y';
    public const TIME_FORMAT = 'H i';
    public $mailFiles = "korean/it-1.eml";
    private $itineraries = [];

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers["from"]) && $this->СheckMail($headers["from"]);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]koreanair\.co\.kr/", $from);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // always returns
        if (stripos($parser->getHTMLBody(), '@market.koreanair.co.kr') !== false) {
            return true;
        } else {
            return false;
        }
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->itineraries['Kind'] = 'T';
        $this->itineraries['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(., 'Reference')]/ancestor::td[1]/following-sibling::td[2]");
        $passengers = $this->http->XPath->query("//text()[contains(., 'Passenger Information')]/ancestor::tr[1]/following-sibling::tr[position() >= 2]");

        if ($passengers->length > 0) {
            foreach ($passengers as $passenger) {
                if ($res = preg_replace("/[\d\.\s]/", "", $this->http->FindSingleNode("./td[2]", $passenger))) {
                    $this->itineraries['Passengers'][] = $res;
                }
            }
        }

        if (preg_match("/(\d{2})(\w{3})(\d{2})$/", $this->http->FindSingleNode('//img[@src="http://cyb.koreanair.com/KalApp/img/mailservice/reservation/40th_logo.gif"]/ancestor::td[1]/following-sibling::td[1]'), $matches)) {
            $this->itineraries['ReservationDate'] = $this->_buildDate(date_parse_from_format(self::DATE_FORMAT, $matches[1] . " " . ucfirst($matches[2]) . " " . $matches[3]));
        }
        $segments = $this->http->XPath->query("//text()[contains(., 'Itinerary')]/ancestor::tr[1]/following-sibling::tr[position() >= 2]");

        if ($segments->length > 0) {
            $this->itineraries['TripSegments'] = [];
            $i = 0;

            foreach ($segments as $segment) {
                $this->itineraries['TripSegments'][$i]['FlightNumber'] = $this->http->FindSingleNode(".//text()[contains(., 'Operated by ')]/preceding-sibling::strong", $segment, true, "#^\s*[A-Z\d]{2}\s*(\d+)#");

                if (preg_match("/\((\w{3})\)\s(.*)$/", $this->http->FindSingleNode(".//text()[contains(., 'Operated by ')]/ancestor::tr[1]/following-sibling::tr[1]/td[2]", $segment), $matches)) {
                    $this->itineraries['TripSegments'][$i]['DepCode'] = $matches[1];
                    $this->itineraries['TripSegments'][$i]['DepName'] = $matches[2];
                }
                $this->itineraries['TripSegments'][$i]['DepDate'] = $this->_buildDate(date_parse_from_format(self::DATE_FORMAT . self::TIME_FORMAT, $this->http->FindSingleNode(".//text()[contains(., 'Operated by ')]/ancestor::tr[1]/following-sibling::tr[1]/td[3]", $segment) . $this->http->FindSingleNode(".//text()[contains(., 'Operated by ')]/ancestor::tr[1]/following-sibling::tr[1]/td[4]/span", $segment)));

                if (preg_match("/\((\w{3})\)\s(.*)$/", $this->http->FindSingleNode(".//text()[contains(., 'Operated by ')]/ancestor::tr[1]/following-sibling::tr[2]/td[2]", $segment), $matches)) {
                    $this->itineraries['TripSegments'][$i]['ArrCode'] = $matches[1];
                    $this->itineraries['TripSegments'][$i]['ArrName'] = $matches[2];
                }
                $this->itineraries['TripSegments'][$i]['ArrDate'] = $this->_buildDate(date_parse_from_format(self::DATE_FORMAT . self::TIME_FORMAT, $this->http->FindSingleNode(".//text()[contains(., 'Operated by ')]/ancestor::tr[1]/following-sibling::tr[2]/td[3]", $segment) . $this->http->FindSingleNode(".//text()[contains(., 'Operated by ')]/ancestor::tr[1]/following-sibling::tr[2]/td[4]", $segment)));
                $this->itineraries['TripSegments'][$i]['AirlineName'] = $this->http->FindSingleNode(".//text()[contains(., 'Operated by ')]/preceding-sibling::strong", $segment, true, "#^\s*([A-Z\d]{2})\s*\d+#");
                $this->itineraries['TripSegments'][$i]['Operator'] = trim($this->http->FindSingleNode(".//text()[contains(., 'Operated by ')]/following-sibling::strong", $segment, true, "#^\s*([^\(]+)#"));
                $this->itineraries['TripSegments'][$i]['Aircraft'] = $this->http->FindSingleNode(".//text()[contains(., 'Operated by ')]/ancestor::tr[1]/following-sibling::tr[6]//tr[1]/td[2]", $segment);
                $this->itineraries['TripSegments'][$i]['Duration'] = $this->http->FindSingleNode(".//text()[contains(., 'Operated by ')]/ancestor::tr[1]/following-sibling::tr[6]//tr[1]/td[last()]", $segment);
                $i++;
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
        preg_match('/([\.@]koreanair\.co\.kr)/ims', $input, $matches);

        return (isset($matches[0])) ? true : false;
    }

    private function _buildDate($parsedDate)
    {
        return mktime((isset($parsedDate['hour'])) ? $parsedDate['hour'] : 0, (isset($parsedDate['minute'])) ? $parsedDate['minute'] : 0, 0, $parsedDate['month'], $parsedDate['day'], $parsedDate['year']);
    }
}
