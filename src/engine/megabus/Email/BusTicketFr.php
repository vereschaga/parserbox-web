<?php

namespace AwardWallet\Engine\megabus\Email;

use AwardWallet\Engine\MonthTranslate;

class BusTicketFr extends \TAccountChecker
{
    public $mailFiles = "megabus/it-3710009.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'BusTicketFr',
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, '@megabus.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'support@megabus.com') !== false
            || isset($headers['subject']) && preg_match("#megabus\.com#", $headers['subject']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//p[contains(normalize-space(.), "Merci d\'avoir réservé avec megabus.com")]')->length > 0;
    }

    public static function getEmailLanguages()
    {
        return ['fr'];
    }

    protected function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripCategory' => TRIP_CATEGORY_BUS, 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//tr[3]/td[contains(normalize-space(.), \"Le sommaire de votre réservation pour la commande\")]/text()[1]", null, true, "#.* ([\w\d]+)#");
        $rows = $this->http->XPath->query("//p[contains(normalize-space(.), 'Voyage')]/ancestor::td[1]");

        foreach ($rows as $row) {
            $seg = [];
//            $seg['AccountNumbers'] = implode($this->http->FindNodes("p[2]/span[position() = 2 or position() = 3]", $row));
            $depArrDate = $this->http->FindSingleNode("p[3]/span[1]/following-sibling::text()[1]", $row);

            if (preg_match("#Départ (?<depName>[\w]+, [\w]+, [\d\w\s-]*) .+ [\w]{1,2} (?<depDate>(?<depMonth>[\w]+) [\d\S\s]+) Arrivée (?<arrName>[\w]+, [\w]+, [\d\w\s-]*) .+ [\w\S]{1,2} (?<arrDate>(?<arrMonth>[\w]+) [\d\S\s]+)#", $depArrDate, $math)) {
                $seg['DepName'] = $math['depName'];
                $seg['ArrName'] = $math['arrName'];
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                $depDate = $math['depDate'];
                $arrDate = $math['arrDate'];
                $seg['DepDate'] = strtotime($this->getDateFr($depDate));
                $seg['ArrDate'] = strtotime($this->getDateFr($arrDate));
            }
            $it['TripSegments'][] = $seg;
        }
        $it['Tax'] = $this->http->FindSingleNode("//p[contains(normalize-space(.), 'Tax Total')]/span", null, true, "#\w+ ([\d.]+)#");
        $totalCurency = $this->http->FindSingleNode("//p[contains(normalize-space(.), 'Prix total')]/span");

        if (preg_match("#(\w+) ([\d.]+)#", $totalCurency, $m)) {
            $it['Currency'] = $m[1];
            $it['TotalCharge'] = $m[2];
        }

        return [$it];
    }

    private function getDateFr($str)
    {
        // $this->http->log($str);
        $in = [
            "#^([^\s\d]+) (\d+), (\d{4}) (\d+:\d+ [AP]M)$#", //October 2, 2011 10:00 AM
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], "fr")) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }
}
