<?php

namespace AwardWallet\Engine\porter\Email;

use AwardWallet\Engine\MonthTranslate;

/**
 * it-3709927.eml, it-3710389.eml.
 */
class AirTicketFr extends \TAccountChecker
{
    public $mailFiles = "porter/it-3709927.eml, porter/it-3710389.eml";
    public $lang = "fr";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'AirTicketFr',
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'PorterAirlines@flyporter.com') !== false
            || isset($headers['subject']) && preg_match("#Porter#", $headers['subject']);
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, '@flyporter.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//td/b[contains(normalize-space(.), 'Porter')]")->length > 0;
    }

    public static function getEmailLanguages()
    {
        return ['fr'];
    }

    protected function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
//        RecorLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Porter num')]/following-sibling::b");
        $it['Status'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'État de la réservation:')]/following-sibling::b[1]");
        $xpath = "//img[@alt='Modifier votre réservation']/ancestor::tr/preceding-sibling::tr[count(td)=4]";
        $rows = $this->http->XPath->query($xpath);

        foreach ($rows as $row) {
            $depTime = $arrTime = null;
            $seg = [];
//            FlightNumber, AirlinaName
            $flightNumAirName = $this->http->FindSingleNode("td[1]", $row);

            if (preg_match("#([\w]{2}) ([\d]{2,5})#", $flightNumAirName, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
//            DepName, DepCode
            $timeDepNameCode = $this->http->FindSingleNode("td[2]", $row);

            if (preg_match("#(?<depTime>[\d:]{5})(?<depName>[\w\s\S]+) \((?<depCode>[\w]{3})\)#", $timeDepNameCode, $math)) {
                $seg['DepName'] = $math['depName'];
                $seg['DepCode'] = $math['depCode'];
                $depTime = $math['depTime'];
            }
//            ArrName, ArrCode
            $timeArrNameCode = $this->http->FindSingleNode("td[3]", $row);

            if (preg_match("#(?<arrTime>[\d:]{5})(?<arrName>[\w\s\S]+) \((?<arrCode>[\w]{3})\)#", $timeArrNameCode, $mathec)) {
                $seg['ArrName'] = $mathec['arrName'];
                $seg['ArrCode'] = $mathec['arrCode'];
                $arrTime = $mathec['arrTime'];
            }
//            Duration
            $seg['Duration'] = $this->http->FindSingleNode("td[4]", $row, true, "#Durée: ([\w\s\d]+) [\S\s\w]+#");
//            DepDate, ArrDate
            $data = $this->http->FindSingleNode("preceding-sibling::tr[2]", $row, true, "#(([\d]+) ([\w]+). ([\d]{4}))#");

            if (isset($depTime) && isset($arrTime)) {
                $seg['DepDate'] = strtotime($this->normalizeDate($data) . ' ' . $depTime);
                $seg['ArrDate'] = strtotime($this->normalizeDate($data) . ' ' . $arrTime);
            }
            $it['TripSegments'][] = $seg;
        }
//        Passengers
        $it['Passengers'] = $this->http->FindNodes("//td[contains(normalize-space(.), 'Passager')]/following::tr[2]/td[1]/b");
//        AccountNumbers
        $it['AccountNumbers'] = $this->http->FindSingleNode("//td[contains(normalize-space(.), 'Passager')]/ancestor::tr/following-sibling::tr[3]/td[1]/text()", null, true, "#VIPorter Member ([\d]+)#");
//        Tax
        $it['Tax'] = str_replace(',', '.', $this->getReceivPaymentDetails('taxes '));
//        Total
        $it['TotalCharge'] = str_replace(',', '.', $this->getReceivPaymentDetails('Total'));
//        Currency
        $currency = $this->getReceivPaymentDetails('Total', '#[\d,]+ (\S+)#');

        if ($currency == '$') {
            $it['Currency'] = 'USD';
        }

        return [$it];
    }

    protected function getReceivPaymentDetails($str, $regExp = null)
    {
        if (!$regExp) {
            return $this->http->FindSingleNode("//td[contains(normalize-space(.), '{$str}')]/following-sibling::td", null, true, "#([\d,]+) \S+#");
        } else {
            return $this->http->FindSingleNode("//td[contains(normalize-space(.), '{$str}')]/following-sibling::td", null, true, "{$regExp}");
        }
    }

    private function normalizeDate($str)
    {
        // echo $str."\n"; die();
        $in = [
            "#^(\d+)\s+([^\d\s]+)\.\s+(\d{4})$#", //20 avr. 2016
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }
}
