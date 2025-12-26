<?php

namespace AwardWallet\Engine\wizz\Email;

class AirTicketRo extends \TAccountChecker
{
    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'AirTicketRo',
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query(".//text()[contains(normalize-space(.), 'Wizz Air oferă acum opțiunea de alocare de locuri!')]")->length > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'noreply@wizzair.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'noreply@wizzair.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['ro'];
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(., 'Codul de confirmare ')]/ancestor::td[1]/following-sibling::td[1]");
        $xpath = "//span[contains(., 'Informațiile pasagerului')]/ancestor::tr[2]/following-sibling::tr[not(contains(.,'Prenume'))]/td[position() < 4]";
        $nods = implode(' ', $this->http->FindNodes($xpath));

        if (preg_match_all("#[MS|MR|CHD]+ ([\w\-]+ [\w\-]+)#", $nods, $m)) {
            $it['Passengers'] = $m[1];
        }
        $xpath = "//span[contains(., 'Total general')]/ancestor::td[1]/following-sibling::td[1]";
        $total = $this->http->FindSingleNode($xpath);

        if (preg_match("#(.+)\s+(\D{3})#", $total, $math)) {
            $it['TotalCharge'] = $math[1];
            $it['Currency'] = $math[2];
        }
        $xpath = "//span[contains(., 'Detaliile zborului')]/ancestor::tr[2]/following-sibling::tr[position() mod 2 = 0]";
        $nods = $this->http->XPath->query($xpath);

        if ($nods->length > 0) {
            foreach ($nods as $i => $root) {
                $seg = [];
                $flightNumber = $this->http->FindSingleNode("preceding-sibling::tr[1]/td[2]", $root);

                if (preg_match("#: ([A-Z\d]{2})\s+(\d{2,5})#", $flightNumber, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }
                $depNameCode = $this->getNode(1, $root);
                $seg['DepName'] = trim($depNameCode['Name']);
                $seg['DepCode'] = $depNameCode['Code'];
                $arr = $this->getNode(2, $root);
                $seg['ArrName'] = trim($arr['Name']);
                $seg['ArrCode'] = $arr['Code'];
                $seg['DepDate'] = strtotime($this->http->FindSingleNode("descendant::tbody[count(tr)=3]/tr[3]/td[1]", $root));
                $seg['ArrDate'] = strtotime($this->http->FindSingleNode("descendant::tbody[count(tr)=3]/tr[3]/td[2]", $root));
                $num = $i + 1;
                $seg['Seats'] = $this->http->FindNodes(".//td[contains(., 'Loc') or contains(., 'Seat')]/ancestor::tr[1]/following-sibling::tr[./td[7]/div]/td[7]/div[{$num}]",
                    null, "#^\s*(\d+[a-zA-Z])\s*$#");
                $it['TripSegments'][] = $seg;
            }
        }

        return [$it];
    }

    private function getNode($td, $root)
    {
        $nod = $this->http->FindSingleNode("descendant::tbody[count(tr)=3]/tr[2]/td[$td]", $root);

        if (preg_match("#(.+)\s*\((\D{3})\)#", $nod, $m)) {
            return ['Name' => $m[1], 'Code' => $m[2]];
        }
    }
}
