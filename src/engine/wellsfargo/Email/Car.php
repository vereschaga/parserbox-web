<?php

namespace AwardWallet\Engine\wellsfargo\Email;

class Car extends \TAccountCheckerExtended
{
    public $mailFiles = "wellsfargo/it-2713863.eml, wellsfargo/it-90634459.eml";
    public $reBody = 'Car rental cost';

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            // Parsing subject "wellsfargo/it-2713863.eml"
            $this->reBody => function (&$itineraries) {
                $it = [];
                $it['Kind'] = "L";

                // Number
                $it['Number'] = $this->http->FindSingleNode("//*[contains(text(),'Reservation number')]/../following-sibling::*[1]");
                // TripNumber
                // PickupDatetime
                $it['PickupDatetime'] = strtotime(str_replace('at ', '', $this->http->FindSingleNode("//*[contains(text(),'Pick-up information')]/ancestor::tr[1]/following-sibling::*[1]//*[contains(text(), 'Date & time')]/../following-sibling::*[1]", null, true, "#^.*?,\s+(.+)$#")));

                // PickupLocation
                $it['PickupLocation'] = $this->http->FindSingleNode("//*[contains(text(),'Pick-up information')]/ancestor::tr[1]/following-sibling::*[1]//*[contains(text(), 'Location')]/../following-sibling::*[1]");

                // DropoffDatetime
                $it['DropoffDatetime'] = strtotime(str_replace('at ', '', $this->http->FindSingleNode("//*[contains(text(),'Drop-off information')]/ancestor::tr[1]/following-sibling::*[1]//*[contains(text(), 'Date & time')]/../following-sibling::*[1]", null, true, "#^.*?,\s+(.+)$#")));

                // DropoffLocation
                $it['DropoffLocation'] = $this->http->FindSingleNode("//*[contains(text(),'Drop-off information')]/ancestor::tr[1]/following-sibling::*[1]//*[contains(text(), 'Location')]/../following-sibling::*[1]");

                // PickupPhone
                $it['PickupPhone'] = $this->http->FindSingleNode("//*[contains(text(),'Pick')]/ancestor::tr[1]/following-sibling::*[1]//*[contains(text(), 'Phone')]/../following-sibling::*[1]");

                // PickupFax
                $it['PickupFax'] = $this->http->FindSingleNode("//*[contains(text(),'Pick')]/ancestor::tr[1]/following-sibling::*[1]//*[contains(text(), 'Fax')]/../following-sibling::*[1]");

                // PickupHours
                // DropoffPhone
                // DropoffHours
                // DropoffFax
                // RentalCompany
                // CarType
                $it['CarType'] = trim($this->http->FindSingleNode("//*[contains(text(),'Car details')]/ancestor::tr[1]/following-sibling::*[1]//*[contains(text(), 'Car type')]/../following-sibling::*[1]"));

                // CarModel
                $it['CarModel'] = trim($this->http->FindSingleNode("//*[contains(text(),'Car details')]/ancestor::tr[1]/following-sibling::*[1]//*[contains(text(), 'Make/model')]/../following-sibling::*[1]"));

                // CarImageUrl
                $it['CarImageUrl'] = $this->http->FindSingleNode("//*[contains(text(),'Car details')]/ancestor::tr[1]/following-sibling::*[1]//img[1]/@src");

                // TotalCharge
                $it["TotalCharge"] = $this->http->FindSingleNode('//*[contains(text(),"Charge to payment card")]/../following-sibling::*[1]', null, true, "#([0-9\.]+)#");

                // Currency
                $it["Currency"] = $this->http->FindSingleNode('//*[contains(text(),"Charge to payment card")]/../following-sibling::*[1]', null, true, "#([^0-9\.]+)#");
                $it["Currency"] = $it["Currency"] == '$' ? 'USD' : $it["Currency"];

                // SpentAwards
                $it["SpentAwards"] = $this->http->FindSingleNode('//*[contains(text(),"Rewards applied")]/../following-sibling::*[1]');

                $xpathDriver = "//tr[normalize-space()=\"Driver's name\"]/following-sibling::tr";

                $driverName = null;
                $firstName = $this->http->FindSingleNode($xpathDriver . "/descendant::tr[ *[1][normalize-space()='First name'] ]/*[2]");

                if ($firstName) {
                    $driverName = $firstName;
                    $lastName = $this->http->FindSingleNode($xpathDriver . "/descendant::tr[ *[1][normalize-space()='Last name'] ]/*[2]");

                    if ($lastName) {
                        $driverName .= ' ' . $lastName;
                    }
                }

                if ($driverName) {
                    $it['RenterName'] = $driverName;
                }

                $itineraries[] = $it;
            },
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/(?:mywellsfargorewards|wellsfargo)\.com/i', $from) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"//mywellsfargorewards.com/") or contains(@href,".wellsfargo.com/") or contains(@href,"www.wellsfargo.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing Wells Fargo") or contains(normalize-space(),"Wells Fargo Bank, N.A. All Rights Reserved") or contains(.,"MyWellsFargoRewards.com")]')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query("//*[contains(normalize-space(),\"{$this->reBody}\")]")->length > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Wells Fargo Rewards Travel Confirmation') !== false
            || stripos($headers['subject'], 'Go Far® Rewards Travel Confirmation') !== false
            || stripos($headers['subject'], 'Go Far(R) Rewards Travel Confirmation') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
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
}
