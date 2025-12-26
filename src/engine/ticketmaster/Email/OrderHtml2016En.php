<?php

// bcdtravel

namespace AwardWallet\Engine\ticketmaster\Email;

class OrderHtml2016En extends \TAccountChecker
{
    public $mailFiles = "ticketmaster/it-8907854.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $result = [];

        foreach ($this->http->XPath->query('//a[contains(text(),"View") and contains(text(),"Tickets") and contains(@style, "color:#ffffff;")]/ancestor::table[2]') as $root) {
            $result['Kind'] = 'E';
            $result['ConfNo'] = str_replace(".", "-", $this->http->FindSingleNode('//td[contains(text(), "Confirmation Number")]/../following-sibling::tr[1]', $root));
            $result['DinerName'] = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),\'Thanks\')]', null, true, "#Thanks\s+(\w+)#");

            $total = $this->http->FindSingleNode('//td[contains(text(), "Order Summary")]/following-sibling::td[1]', $root);

            if (preg_match('/(\$)([\d\.,]+)/', $total, $matches)) {
                $result['Currency'] = preg_replace(['/^\$$/'], 'USD', $matches[1]);
                $result['TotalCharge'] = (float) $this->convertCost($matches[2]);
            }

            $result['Name'] = $this->http->FindSingleNode('tbody/tr[1]', $root);

            $address = $this->http->FindSingleNode('tbody/tr[2]', $root);

            if (preg_match('/(.+?), (\w+ \d+ \d+ - \d+:\d+\s*(?:[AP]M)?)/', $address, $matches)) {
                $result['Address'] = $matches[1];
                $result['StartDate'] = strtotime(str_replace(' - ', ', ', $matches[2]));
            }
        }

        return [
            'parsedData' => ['Itineraries' => [$result]],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'customer_support@email.ticketmaster.com') !== false
                && isset($headers['subject']) && (
                preg_match('/Your.+?Ticket Order/', $headers['subject'])
                );
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//*[contains(normalize-space(text()),"here\'s your order info.")]')->length > 0
            && $this->http->XPath->query('//text()[contains(normalize-space(.),"Ticketmaster")]')->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@email.ticketmaster.com') !== false;
    }

    private function convertCost($str)
    {
        $str = preg_replace('/\s+/', '', $str);			// 11 507.00	->	11507.00
        $str = preg_replace('/[,.](\d{3})/', '$1', $str);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $str = preg_replace('/^(.+),$/', '$1', $str);	// 18800,		->	18800
        $str = preg_replace('/,(\d{2})$/', '.$1', $str);	// 18800,00		->	18800.00

        return $str;
    }
}
