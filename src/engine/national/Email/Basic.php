<?php

namespace AwardWallet\Engine\national\Email;

class Basic extends \TAccountChecker
{
    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strlen($body) == 0) {
            $start = stripos($parser->emailRawContent, "<html>");
            $end = stripos($parser->emailRawContent, "</html>");
            $body = substr($parser->emailRawContent, $start, $end - $start + 7);
        }
        $this->http->FilterHTML = false;
        $this->http->SetBody($body);
        $emailType = $this->getEmailType($parser->getHeader("subject"));

        switch ($emailType) {
            case "ReservationConfirmation":
                $result = $this->ParseEmailReservationConfirmation();

                break;

            default:
                $result = 'Undefined email type';

                break;
        }

        return [
            'parsedData' => $result,
            'emailType'  => $emailType,
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "nationalcar.com") !== false;
    }

    public function getEmailType($subject = null)
    {
        if ($this->http->FindPreg('/Reservation Information/') || stripos($subject, "National Car Rental Reservation Confirmation") !== false) {
            return 'ReservationConfirmation';
        }

        return 'Undefined';
    }

    public function ParseEmailReservationConfirmation()
    {
        $props = [];
        $it = [];

        $it['Kind'] = 'L';
        $conf = $this->http->FindPreg('/Your confirmation number is: (\d+)/');

        if (empty($conf)) {
            $conf = $this->http->FindPreg('/Confirmation # (\d+)/');
        }
        $it['ConfirmationNumber'] = $it['Number'] = $conf;

        $it['Status'] = $this->http->FindPreg('/Status: ([^\s]+)/');

        $nodes = $this->http->XPath->query('//table//tr[td]');
        $state = '';

        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);

            if ($value = $this->http->FindSingleNode('.', $node, false, '/^Name: (.+)/')) {
                $it['RenterName'] = $value;
            }

            if ($value = $this->http->FindSingleNode('.', $node, false, '/^Vehicle Type: (.+)/')) {
                $car = explode(' - ', $value);
                $it['CarType'] = array_shift($car);
                $it['CarModel'] = array_pop($car);
            }

            if ($state == 'Pickup' || $state == 'Dropoff') {
                if ($value = $this->http->FindSingleNode('.', $node, false, '/^Date & Time: (.+)/')) {
                    $it[$state . 'Datetime'] = strtotime(str_replace('@', ' ', $value));
                }

                if ($value = $this->http->FindSingleNode('.', $node, false, '/^Location: (.+)/')) {
                    $it[$state . 'Location'] = $value;
                }

                if ($value = $this->http->FindSingleNode('.', $node, false, '/^Phone: (.+)/')) {
                    $it[$state . 'Phone'] = $value;
                }

                if ($value = $this->http->FindSingleNode('.', $node, false, '/^Fax: (.+)/')) {
                    $it[$state . 'Fax'] = $value;
                }

                if ($value = $this->http->FindSingleNode('.', $node, false, '/^Hours: (.+)/')) {
                    $it[$state . 'Hours'] = $value;
                }
            }

            if ($this->http->FindSingleNode('.', $node, false, '/^Pickup Information/')) {
                $state = 'Pickup';
            }

            if ($this->http->FindSingleNode('.', $node, false, '/^Dropoff Information/')) {
                $state = 'Dropoff';
            }
        }

        $nodes = $this->http->XPath->query('//*[contains(text(), "Rate Information")]');

        if ($nodes->length == 1) {
            $node = $nodes->item($i);

            $it['TotalCharge'] = trim($this->http->FindSingleNode('.', $node, false, '/Total Estimate\.+\$([\d\., ]+)/'));
            $it['Currency'] = $this->http->FindSingleNode('.', $node, false, '/Item:[\s\r\n]+Prices[\s\r\n]+\(([A-Z]{3})\)/');
        }

        return ['Itineraries' => $it, 'Properties' => $props];
    }
}
