<?php

namespace AwardWallet\Engine\ryanair\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "";

    public function detectEmailFromProvider($from)
    {
        return preg_match("#ryanair\.com#i", $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match("#Ryanair\.com#i", $headers['from']) || isset($header['subject']) && stripos($headers['subject'], 'Ryanair Travel') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return preg_match('#GRACIAS POR REALIZAR SU RESERVA CON RYANAIR|THANK YOU FOR BOOKING WITH RYANAIR|BEDANKT VOOR HET BOEKEN MET RYANAIR#', $body);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        // Parser toggled off as it is covered by emailRyanairTravelItineraryChecker.php
        return null;
        $itineraries['Kind'] = 'T';
        $RecordLocator = $this->http->FindSingleNode("//table[.//*[contains(text(),'FLIGHT RESERVATION') or contains(text(),'DE RESERVA DEL VUELO') or contains(text(),'VLUCHTRESERVERINGSNUMMER')]and not(.//table)]");

        if (preg_match('#(?:NUMBER|DE RESERVA DEL VUELO|VLUCHTRESERVERINGSNUMMER)\s*(\S*)\s*.*(?:STATUS|ESTADO DE VUELO)\s*(.*)#i', $RecordLocator, $match)) {
            $itineraries['RecordLocator'] = $match[1];
        }
        $itineraries['Passengers'] = $this->http->FindNodes(".//*[contains(text(),'PASSENGER(S)') or contains(text(), 'PASAJERO/PASAJEROS') or contains(text(), 'PASSAGIER(S)')]/ancestor::tr[1]/following-sibling::tr//tr/td[not(contains(.,'Insurance')) and not(contains(.,'Geen reisverzekering')) and normalize-space()!='']");

        $itineraries['Tax'] = $this->http->FindSingleNode("//*[(contains(text(), 'Taxes,') and contains(text(), 'Fees')) or (contains(text(), 'Belastingen,') and contains(text(), 'toeslagen'))]/ancestor-or-self::td[1]/following-sibling::td[1]", null, true, "#(\d+\.*\d+)#");
        $itineraries['BaseFare'] = $this->http->FindSingleNode("//*[(contains(text(), 'Fare') and contains(text(), 'Total')) or contains(text(), 'Totaalbedrag')]/ancestor-or-self::td[1]/following-sibling::td[1]", null, true, "#(\d+\.*\d+)#");
        $itineraries['Currency'] = $this->http->FindSingleNode("//*[(contains(text(), 'Fare') and contains(text(), 'Total')) or contains(text(), 'Totaalbedrag')]/ancestor-or-self::td[1]/following-sibling::td[1]", null, false, '#\d+\.?\d+\s*(\w+)#i');
        $itineraries['TotalCharge'] = $this->http->FindSingleNode("//*[(contains(text(),'Total') and contains(text(),'paid')) or (contains(text(),'Tarifa') and contains(text(),'total')) or (contains(text(),'Totaal') and contains(text(),'betaald'))]/ancestor-or-self::td[1]/following-sibling::td[1]", null, false, '#(\d+\.?\d+)#i');

        $nodes = $this->http->XPath->query("//tr[.//*[contains(text(),'From') or contains(text(), 'Origen') or contains(text(), 'Van')] and not(.//table[.//table])]");
        $this->http->Log("Total nodes found " . $nodes->length);

        for ($i = 0; $i < $nodes->length; $i++) {
            $all = $this->http->FindSingleNode("ancestor::table[1]", $nodes->item($i));

            $itineraries['TripSegments'][$i]['FlightNumber'] = preg_match("#\(\w{2}\s*(\d+)\)#", $all, $m) ? $m[1] : '';
            $itineraries['TripSegments'][$i]['AirlineName'] = preg_match("#\((\w{2})\s*(\d+)\)#", $all, $m) ? $m[1] : '';

            if (preg_match("#(?:DEPART|SALIDA|VERTREK)\s*\((\w{3})\)\s*(.*?)\s*(?:ARRIVAL|LLEGADA|AANKOMST)\s*\((\w{3})\)\s*(.*?)\s(\w{3},*\s*\d+\s*\w+\s*\d+)\s*(\d{2}:\d{2})\w+\s*(\w{3},*\s*\d+\s*\w+\s*\d+)\s*(\d{2}:\d{2})#", $all, $m)) {
                $itineraries['TripSegments'][$i]['DepName'] = $m[2];
                $itineraries['TripSegments'][$i]['DepCode'] = $m[1];
                $itineraries['TripSegments'][$i]['ArrName'] = $m[4];
                $itineraries['TripSegments'][$i]['ArrCode'] = $m[3];

                $itineraries['TripSegments'][$i]['DepDate'] = strtotime($m[5] . ',' . $m[6]);
                $itineraries['TripSegments'][$i]['ArrDate'] = strtotime($m[7] . ',' . $m[8]);
            }
        }

        return [
            "emailType"  => "reservation",
            "parsedData" => [
                "Itineraries" => [$itineraries],
            ],
        ];
    }

    public static function getEmailLanguages()
    {
        return ["es", "en", "nl"];
    }

    public function es2en($date)
    {
        $arr = ['ene'=>'jan', 'feb'=>'feb', 'mar'=>'mar', 'abr'=>'apr', 'may'=>'may', 'jun'=>'jun', 'jul'=>'jul', 'ago'=>'aug', 'sep'=>'sep', 'set'=>'sep', 'oct'=>'oct', 'nov'=>'nov', 'dic'=>'dec'];
        $date = preg_replace_callback("#\s*([A-Za-z]{3})#iu", function ($m) use (&$arr) {
            foreach ($arr as $name => $en) {
                if (preg_match("#$name#i", $m[1])) {
                    return " $en ";
                }
            }

            return $m[0];
        }, $date);

        return $date;
    }
}
