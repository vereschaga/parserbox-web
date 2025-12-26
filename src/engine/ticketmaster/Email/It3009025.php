<?php

namespace AwardWallet\Engine\ticketmaster\Email;

class It3009025 extends \TAccountCheckerExtended
{
    public $mailFiles = "ticketmaster/it-3009025.eml";
    public $reBody = "Ticketmaster";
    public $reBody2 = "Purchase reference:";
    public $reBody3 = "Event";
    public $reSubject = "Purchase confirmation of tickets for";
    public $reSubject2 = "in ticketmaster.es";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $it = [];

                $it['Kind'] = "E";

                // ConfNo
                $it["ConfNo"] = str_replace(" ", "-", $this->http->FindSingleNode("//*[contains(text(), 'Purchase reference:')]/following-sibling::*[1]"));

                // TripNumber
                // Name
                $it["Name"] = $this->getField("Event");

                // StartDate
                $it["StartDate"] = strtotime($this->getField("Day") . ", " . $this->getField("Entry time", false, "#\d+:\d+#"));

                // EndDate
                // Address
                $it["Address"] = $this->getField("Venue");

                // Phone
                // DinerName
                $it["DinerName"] = trim(re("#\n([^\n]+),\s+You have purchased tickets for#msi", text($this->text())));

                // Guests
                $it["Guests"] = $this->getField("Quantity", true, "#\d+#");

                // TotalCharge
                $it["TotalCharge"] = cost($this->getField("TOTAL", false));

                // Currency
                $it["Currency"] = currency(re("#Price\(.{1,3}\)#", $this->text()));

                // Tax
                // SpentAwards
                // EarnedAwards
                // AccountNumbers
                // Status
                // Cancelled
                // ReservationDate
                // NoItineraries
                $itineraries[] = $it;
            },
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false && strpos($body, $this->reBody3) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["subject"], $this->reSubject) !== false && strpos($headers["subject"], $this->reSubject2) !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
        }

        foreach ($this->processors as $re => $processor) {
            if (stripos($body, $re)) {
                $processor($itineraries);

                break;
            }
        }
        $result = [
            'emailType'  => 'Reservations',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public function getField($str, $strictly = true, $regexp = "#.+#")
    {
        if (!$strictly) {
            return $this->http->FindSingleNode("//*[contains(text(), '{$str}')]/ancestor-or-self::td[1]/following-sibling::td[1]", null, true, $regexp);
        } else {
            return $this->http->FindSingleNode("//*[text()='{$str}']/ancestor-or-self::td[1]/following-sibling::td[1]", null, true, $regexp);
        }
    }
}
