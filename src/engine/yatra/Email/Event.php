<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\yatra\Email;

class Event extends \TAccountChecker
{
    public $mailFiles = "yatra/it-7122050.eml";

    private $detects = [
        'Thanks for choosing to take walks with us',
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        return [
            'emailType'  => 'EventEn',
            'parsedData' => [
                'Itineraries' => $this->parseEmail(),
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'yatra.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'yatra.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->detects as $detect) {
            if (stripos($body, $detect) !== false && stripos($body, 'The Walks of Italy Team') !== false) {
                return true;
            }
        }

        return false;
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\Restaurant $it */
        $it = ['Kind' => 'E'];
        // ConfNo
        $it['ConfNo'] = $this->http->FindSingleNode("//strong[contains(., 'Order ID')]/following-sibling::text()[1]");
        // Name
        $it['Name'] = $this->http->FindSingleNode("//tr[contains(., 'City/Region')]/preceding-sibling::tr[1]");
        // StartDate
        $date = $this->http->FindSingleNode("//tr[contains(., 'City/Region')]/preceding-sibling::tr[2]");
        $time = $this->getNode('Meeting Time');
        $it['StartDate'] = $this->normalizeDate($date . ', ' . $time);
        // EndDate
        // Address
        $it['Address'] = $this->getNode('City/Region') . ', ' . $this->getNode('Meeting Point');
        // Phone
        $it['Phone'] = $this->http->FindSingleNode("//p[contains(normalize-space(.), 'Thanks for choosing to take walks with us')]", null, true, '/phone at\s+([\d\-]+)/');
        // DinerName
        $it['DinerName'] = $this->http->FindSingleNode("//p[contains(., 'Dear')]/strong[1]");
        // Guests
        $it['Guests'] = (int) trim($this->getNode('Total Pax'));
        // TotalCharge
        // Currency
        $total = $this->getNode('Price');
        $currency = $this->http->FindSingleNode("//th[contains(., 'Date')]/following-sibling::th[last()]", null, true, '/([A-Z]{3})/');

        if (preg_match('/(\w)\s*([\d\.]+)/', $total, $m)) {
            $it['Currency'] = !empty($currency) ? $currency : str_replace(['$'], ['USD'], $m[1]);
            $it['TotalCharge'] = $m[2];
        }
        $it['EventType'] = EVENT_MEETING;
        // Tax
        // AccountNumbers
        // Status
        // Cancelled
        // ReservationDate
        // NoItineraries
        return [$it];
    }

    private function normalizeDate($str)
    {
        $in = [
            '/(\D+)\s+(\d{1,2})\,\s+(\d{4})\s+\(\w+\)\s*\,\s+(\d{1,2}:\d{2}\s*[ap]m)/i',
        ];
        $out = [
            '$2 $1 $3, $4',
        ];

        return strtotime(preg_replace($in, $out, $str));
    }

    private function getNode($str)
    {
        return $this->http->FindSingleNode("//tr[contains(., 'City/Region')]/descendant::strong[contains(., '" . $str . "')]/following-sibling::text()[1]");
    }
}
