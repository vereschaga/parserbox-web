<?php

namespace AwardWallet\Engine\onetravel\Email;

class AirTicket extends \TAccountChecker
{
    public $mailFiles = "onetravel/it-3801722.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'AirTicket',
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query(".//text()[contains(normalize-space(.), 'Thank you for choosing')]/ancestor::*/descendant::font[position() = 16 or position() = 18]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'tlc@onetravel.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'tlc@onetravel.com') !== false;
    }

    protected function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode(".//td[contains(normalize-space(.), 'E-Ticket Number')]/ancestor::tr/following::tr[1]/td[2]/font[1]");
        $totalCharge = $this->http->FindSingleNode(".//font[contains(normalize-space(.), 'Total Charge')]/ancestor::td/following-sibling::td[1]");

        if (preg_match("#(\S{1})(.+)#", $totalCharge, $v)) {
            $it['Currency'] = ($v[1] == '$') ? 'USD' : null;
            $it['TotalCharge'] = $v[2];
        }
        $it['Tax'] = str_replace(' ', '', $this->http->FindSingleNode(".//font[contains(normalize-space(.), 'Taxes and Fees')]/ancestor::td/following-sibling::td[1]", null, true, "#\S{1}(.+)#"));
        $Xpath = "//td[contains(normalize-space(.), 'Flight')]/ancestor::tr/following-sibling::tr//b[contains(text(), 'Airline')]";
        $roots = $this->http->XPath->query($Xpath);
        $data = array_filter($this->http->FindNodes("{$Xpath}/following::td[3]//tr[1]"));
        $aData = $data = array_merge($data, [end($data)]);

        if ($roots->length > 0) {
            foreach ($roots as $root) {
                $seg = [];
                $seg['FlightNumber'] = $this->http->FindSingleNode("following::font[1]", $root, true, "#Flight (.+)#");
                $seg['Aircraft'] = $this->http->FindSingleNode("following::font[2]", $root, true, "#Aircraft: (.+)#");
                $seg['DepName'] = $this->http->FindSingleNode("following::td[3]//tr[2]", $root, true, "#(.+), #");
                $depCodeTime = $this->http->FindSingleNode("following::td[3]//tr[3]", $root);

                if (preg_match("#(\w{3}) - (\d{2}:\d{2})#", $depCodeTime, $m)) {
                    $seg['DepCode'] = $m[1];
                    $seg['DepDate'] = strtotime(array_shift($data) . ' ' . $m[2]);
                }
                $seg['ArrName'] = $this->http->FindSingleNode("following::td[3]//tr[4]", $root, true, "#(.+), #");
                $arrCodeTime = $this->http->FindSingleNode("following::td[3]//tr[5]", $root);

                if (preg_match("#(\w{3}) - (\d{2}:\d{2})#", $arrCodeTime, $math)) {
                    $seg['ArrCode'] = $math[1];
                    $seg['ArrDate'] = strtotime(array_shift($aData) . ' ' . $math[2]);
                }
                $seatStatus = $this->http->FindSingleNode("following::td[9]//tr[4]", $root);

                if (preg_match("#Seats[\s\w]*:\s*([\d\w]+)\s+\S+\s+(\w+)#", $seatStatus, $mathec)) {
                    $seg['Seats'] = $mathec[1];
                    $it['Status'] = $mathec[2];
                }
                $it['TripSegments'][] = $seg;
            }
        }

        return [$it];
    }
}
