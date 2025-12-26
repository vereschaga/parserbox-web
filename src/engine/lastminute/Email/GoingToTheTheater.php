<?php
/**
 * Created by PhpStorm.
 * User: Roman.
 */

namespace AwardWallet\Engine\lastminute\Email;

class GoingToTheTheater extends \TAccountChecker
{
    public $mailFiles = "lastminute/it-4622748.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'lastminute.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'lastminute.com')]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, 'lastminute.com') !== false;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'E'];
        $it['ConfNo'] = $this->http->FindSingleNode("//strong[contains(text(), 'Your order number is')]", null, true, '#Your\s+order\s+number\s+is\s+(\w+)#');
        $it['Name'] = $this->http->FindSingleNode("//b[contains(text(), 'Entertainment')]/ancestor::tr[2]/following-sibling::tr[1]/td[2]/descendant::text()", null, true, '#([\w\s]+) - .*#');
        $startDate = $this->http->FindSingleNode("//td/b[contains(text(), 'Date')]/ancestor::td[1]/following-sibling::td[1]");

        if (preg_match('#(?<Time>\d+:\d+)\s+\w+\s+(?<Day>\d+)\s+(?<Month>\w+)\s+(?<Year>\d+)#', $startDate, $m)) {
            $it['StartDate'] = strtotime($m['Day'] . ' ' . $m['Month'] . ' ' . $m['Year'] . ' ' . $m['Time']);
        }
        $it['Address'] = $this->http->FindSingleNode("//td/b[contains(text(), 'Address')]/ancestor::td[1]/following-sibling::td[1]/descendant::text()");
        $it['DinerName'] = $this->http->FindSingleNode("//td/b[contains(text(), 'Booked in Name')]/ancestor::td[1]/following-sibling::td[1]");
        $totalCharge = $this->http->FindSingleNode("//b[contains(text(), 'Total')]/ancestor::tr[2]/following-sibling::tr[2]/td[8]/descendant::text()");

        if (preg_match('#(\D+)\s*([\d,.]+)#', $totalCharge, $math)) {
            $it['TotalCharge'] = str_replace('.', ',', $math[2]);
            $it['Currency'] = ($math[1] === '£') ? 'GBP' : null;
        }

        return [$it];
    }
}
