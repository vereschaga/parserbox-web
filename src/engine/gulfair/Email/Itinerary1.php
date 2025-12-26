<?php

namespace AwardWallet\Engine\gulfair\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "gulfair/it-1.eml, gulfair/it-1571333.eml, gulfair/it-2.eml, gulfair/it-3.eml, gulfair/it-9117257.eml";

    private $date = 0;

    public function detectEmailFromProvider($from)
    {
        return preg_match("#@gulfair\.com#i", $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers["from"], 'online.pnr@gulfair.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return stripos($body, 'online.pnr@gulfair.com') !== false || stripos($body, 'www.gulfair.com') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('Date'));
        $itineraries[0] = $this->AirTrip();

        return [
            "emailType"  => "reservation",
            "parsedData" => [
                "Itineraries" => $itineraries,
            ],
        ];
    }

    public function AirTrip()
    {
        $itineraries['Kind'] = 'T';

        $itineraries['RecordLocator'] =
            $this->http->FindSingleNode("//text()[contains(.,'Booking Reference')]/
													ancestor::td[1]", null, true, '#Reference\s*is\s*(\S+)#');

        $itineraries['TicketNumbers'] = array_values(array_unique(array_filter($this->http->FindNodes("//tr[.//font[contains(text(),'Ticket number')]and not(.//tr)]/td[contains(.,'Ticket number')]", null, "#Ticket number[\s:]+(.+)#"))));
        $itineraries['AccountNumbers'] = array_values(array_unique(array_filter($this->http->FindNodes("//tr[.//font[contains(text(),'Ticket number')]and not(.//tr)]/td[contains(.,'Ticket number')]/following-sibling::td[1]", null, "#[\s:]+(.+)#"))));
        $passengers = $this->http->FindNodes("//tr[.//font[contains(text(),'Ticket number')]and not(.//tr)]");

        for ($i = 0; $i <= count($passengers) - 1; $i++) {
            if (preg_match('#\d\.\s*(.*)\s*\(#', $passengers[$i], $match)) {
                $itineraries['Passengers'][$i] = trim($match[1]);
            }
        }

        $paymentDetailsNode =
            $this->http->XPath->query("//text()[contains(., 'payment details')]/
													ancestor::tr[3]/following-sibling::tr[1]")->item(0);

        $totalStr = $this->http->FindSingleNode(".//text()[contains(., 'Total')]/ancestor::td[1]", $paymentDetailsNode);

        if (preg_match('/([A-Z]{3}) ([0-9]+\.[0-9]+)/', $totalStr, $matches)) {
            $itineraries['Currency'] = $matches[1];
            $itineraries['TotalCharge'] = (float) $matches[2];
        }

        $itineraries['Tax'] =
            (float) $this->http->FindSingleNode(".//text()[contains(., 'Taxes and Surcharges')]/ancestor::tr[1]/td[3]",
                $paymentDetailsNode,
                false,
                '/[0-9]+\.[0-9]+/');

        $fare = .0;
        $fareValueStrs = $this->http->FindNodes(".//text()[contains(., 'fare')]/ancestor::tr[1]/td[3]",
            $paymentDetailsNode);

        foreach ($fareValueStrs as $s) {
            if (preg_match('/(([0-9]+) X )?([0-9]+\.[0-9]+)/', $s, $matches)) {
                $x = (float) $matches[3];

                if ($matches[2]) {
                    $x *= (float) $matches[2];
                }
                $fare += $x;
            }
        }
        $itineraries['BaseFare'] = $fare;

        $itineraries['TripSegments'] = $this->segments();

        return $itineraries;
    }

    public function segments()
    {
        $itineraries = [];
        $segments =
            $this->http->XPath->query("//text()[contains(.,'Flight(s) Information')]/
												ancestor::td[1]/
														ancestor::tr[3]/following-sibling::tr[1]//tr[position() > 1][count(descendant::td)>3]");
        $year = $this->http->FindSingleNode("//tr[td[font[contains(text(),'Ticket Issued')]]]",
                                            null,
                                            true,
                                            '#Ticket\s*Issued\s*on\s*\:\s*\w+\,\s*\d+\s*\w+\s*(\d+)#');
        $seats = $this->http->FindNodes("(//tr/td[font[contains(text(),'Please note:')]]/div)[last()]/font");

        for ($i = 0; $i < $segments->length; $i++) {
            $itineraries[$i]['FlightNumber'] = $this->http->FindSingleNode('./td[3]', $segments->item($i), false, '#\d+#');
            $itineraries[$i]['AirlineName'] = $this->http->FindSingleNode('./td[3]', $segments->item($i), false, '#^([A-Z][A-Z\d]|[A-Z\d][A-Z])\d+#');
            $operator = $this->http->FindSingleNode("./following-sibling::tr[1][contains(.,'Operated by')]", $segments->item($i), false, '#Operated by\s+(.+)#');

            if (!empty($operator)) {
                $itineraries[$i]['Operator'] = $operator;
            }

            // Departure info
            $r = '#([\w\s]+)\s+\((\w+)\)\s+\w+,\s+(\d+\s+\w+)\s+(\d+)\.(\d+)\s+(?:.*?TERMINAL\s(\w)|).*?$#';
            $info = join(' ', $this->http->FindNodes('./td[4]/node()', $segments->item($i), false));

            if (preg_match($r, $info, $m)) {
                $itineraries[$i]['DepName'] = trim($m[1]);

                if (isset($m[6])) {
                    $itineraries[$i]['DepartureTerminal'] = $m[6];
                }
                $itineraries[$i]['DepCode'] = $m[2];
                $itineraries[$i]['DepDate'] = strtotime("$m[3] $year, $m[4]:$m[5]", $this->date);
            }

            // Arrival info
            $info = join(' ', $this->http->FindNodes('./td[5]/node()', $segments->item($i), false));

            if (preg_match($r, $info, $m)) {
                $itineraries[$i]['ArrName'] = trim($m[1]);

                if (isset($m[6])) {
                    $itineraries[$i]['ArrivalTerminal'] = $m[6];
                }
                $itineraries[$i]['ArrCode'] = $m[2];
                $itineraries[$i]['ArrDate'] = strtotime("$m[3] $year, $m[4]:$m[5]", $this->date);
            }

            $info = $this->http->FindSingleNode('./td[6]', $segments->item($i), false);

            if (preg_match('#(\w+)\s\((\w)\)#', $info, $m)) {
                $itineraries[$i]['Cabin'] = $m[1];
                $itineraries[$i]['BookingClass'] = $m[2];
            }

            if (isset($seats[$i])) {
                $itineraries[$i]['Seats'] = $seats[$i];
            }
        }

        return $itineraries;
    }
}
