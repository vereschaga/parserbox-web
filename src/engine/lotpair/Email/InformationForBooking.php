<?php

namespace AwardWallet\Engine\lotpair\Email;

class InformationForBooking extends \TAccountChecker
{
    public $mailFiles = "lotpair/it-11501378.eml";

    private $reBody = 'LOT Polish Airlines';

    private $reBody2 = [
        'inform you that your journey details have changed',
        'inform you that your travel details have changed',
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];
        $its[] = $this->parseEmail();

        return [
            'emailType'  => 'InformationForBooking',
            'parsedData' => [
                'Itineraries' => $its,
            ],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $detect) {
            if (stripos($body, $detect) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[normalize-space()='Your booking reference:']/following::text()[normalize-space()][1]", null, true, "#^\s*([A-Z\d]{5,7})\s*$#");
        $xpath = "//text()[normalize-space()='Your new flight:']/ancestor::tr[1]/following::tr[normalize-space()][2]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];

            $text = implode("\n", $this->http->FindNodes("./td", $root));

            if (preg_match("#(.+\s+\d+:\d+)\s+([A-Z]{3})\s+([A-Z]{3})\s+([A-Z\d]{2})(\d{1,5})\s+([A-Z]{1,2})#", $text, $m)) {
                $seg['DepCode'] = $m[2];
                $seg['ArrCode'] = $m[3];

                $seg['AirlineName'] = $m[4];
                $seg['FlightNumber'] = $m[5];

                $seg['DepDate'] = strtotime($this->normalizeDate($m[1]));
                $seg['ArrDate'] = MISSING_DATE;

                $seg['BookingClass'] = $m[6];
            }

            $it['TripSegments'][] = $seg;
        }

        return $it;
    }

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        $in = [
            "#^\s*(\d{1,2})([^\d\s]+)(\d{2})\s+(\d+:\d+)\s*$#", //03APR16   17:50
        ];
        $out = [
            "$1 $2 20$3 $4",
        ];
        $str = preg_replace($in, $out, $str);
        //		if(preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
        //			if($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
        return $str;
    }
}
