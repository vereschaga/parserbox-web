<?php

namespace AwardWallet\Engine\qantas\Email;

class Basic extends \TAccountChecker
{
    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $emailType = $this->getEmailType($parser);               // определяем тип
        $result = "Undefined email type";           // возвращаем строку, если не определили тип

        switch ($emailType) {
            case "DepartureInfo":
                $this->http->SetBody($parser->getHTMLBody());      // достаем тело письма, загружаем в парсер
                $result = $this->ParseEmailDepartureInfo($parser);     // вызываем нужный метод

                break;
        }

        return [
            'parsedData' => $result,                        // возвращаем результат
            'emailType'  => $emailType,
        ];
    }

    public function getEmailType(\PlancakeEmailParser $parser)
    {
        if (
        preg_match('/Qantas\s+Departure\s+Information/i', $parser->getSubject())
        ) { // определяем тип, по характерным для таких писем надписям, верстке и т.д.
            return "DepartureInfo";
        }

        return "Undefined";
    }

    public function ParseEmailDepartureInfo(\PlancakeEmailParser $parser)
    {
        $result = [];

        for ($i = 0; $i <= $parser->countAttachments(); $i++) {
            if (preg_match('/^\s*([\w-]+)\/([\w-]+)\s*(;.*)?$/i', $parser->getAttachmentHeader($i, 'Content-Type'), $ar)) {
                if (in_array(strtolower($ar[1]), ['file', 'application']) and $ar[2] == 'pdf') {
                    $pdf = new \PDFConvert($parser->getAttachmentBody($i));
                    //$result['pdf']=$pdf->textBlocks();//
                }
            }
        }

        $baseNode = $this->http->XPath->query('//table[contains(.//td,"Flight Details") and not(.//table)]')->item(0);
        $result['RecordLocator'] = $this->http->FindSingleNode('//td/*[contains(.,"Details for the first flight")]/following-sibling::*/descendant-or-self::*[name()="li" or name()="p"][.//strong and contains(.,"booking reference")]/.//strong', $baseNode);
        // Passengers
        $passengers = $this->http->FindNodes('/following-sibling::table/.//table[1]/.//table/.//td[1]/*[not(starts-with(.,"Passenger") or name()="table")]', $baseNode);

        if (isset($passengers[0])) {
            $result['Passengers'] = implode(', ', $passengers);
        }
        // AccountNumbers
        $accounts = $this->http->FindNodes('//table[contains(.//td,"Flight details") and not(.//table)]/following-sibling::table/descendant::table[1]/descendant::table/descendant::td[2]/*');

        for ($i = 0; $i < count($accounts); $i++) {
            if (!preg_match('/Add your|Flyer No/i', $accounts[$i])) {
                $arrAccounts[] = $accounts[$i];
            }
        }

        if (isset($arrAccounts[0])) {
            $result['AccountNumbers'] = implode(', ', $arrAccounts);
        }
        // Air trip segments

        //$segments = Array();
        //$detailsNode=$this->http->XPath->query('',$baseNode)->item(0);
        $trip = $this->http->FindNodes('//td/*[contains(.,"Details for the first flight")]/following-sibling::*/descendant-or-self::*[name()="li" or name()="p"][.//strong and not(contains(.,"booking reference") or contains(.,"Departing"))]/.//strong', $baseNode);
        $segments = ['FlightNumber'=>$trip[0], 'ArrName'=>$trip[1]];
        $trip = $this->http->FindNodes('//td/*[contains(.,"Details for the first flight")]/following-sibling::*/descendant-or-self::*[name()="li" or name()="p"][.//strong and contains(.,"Departing")]/.//strong', $baseNode);
        $segments = array_merge($segments, ['DepName'=>$trip[0], 'DepTime'=>$trip[1], 'DepDate'=>$trip[2]]);

        $result['TripSegments'][] = $segments;

        return $result;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[\.@]qantas\.com/ims', $from);
    }

    //todo: create detect methods
}
