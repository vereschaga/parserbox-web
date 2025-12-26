<?php

namespace AwardWallet\Engine\rentacar\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "rentacar/it-1937337.eml, rentacar/it-1945606.eml, rentacar/it-1954292.eml, rentacar/it-1965693.eml";
    private $_emails = [
        'onlinereservations@enterprise.com',
        'No-Reply@enterprise.com',
        'Enterprise Rent-A-Car Reservations',
        'Enterprise Rent-A-Car',
        'DLCusthelpOnlineReservationsReservations@ehi.com',
    ];
    private $_subjects = [
        'Enterprise Rent-A-Car Rental Information for claim number',
        'Confirmed: Enterprise Rent-A-Car Reservation',
        'Your Request: Find A Reservation',
        'Reminder: Enterprise Rent-A-Car Reservation',
    ];

    public function detectEmailByHeaders(array $headers)
    {
        $from = $this->_checkInHeader($headers, 'from', $this->_emails);
        $subject = $this->_checkInHeader($headers, 'subject', $this->_subjects);

        return $from || $subject;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // If forwarded message
        $body = $parser->getPlainBody();
        $from = $this->_checkInBody($body, 'From:', $this->_emails);
        $mailto = $this->_checkInBody($body, 'mailto:', $this->_emails);
        $subject = $this->_checkInBody($body, 'Subject:', $this->_subjects);

        return $from || $mailto || $subject || $this->http->XPath->query("//text()[contains(.,'Enterprise Rent-A-Car for your rental needs')]")->length;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $parser->getBody();
        $body = preg_replace('/^\>\s*/m', '', $body);
        $body = preg_replace('/\<https:\/\/maps\.google\.com\/.+?\>/m', '', $body);

        $offset = 0;

        $it = [];
        $it['Kind'] = 'L';
        $it['Cancelled'] = $this->http->FindPreg("#cancel+ation\s+number\s+is#i") ? true : false;

        preg_match("/(?:Confirmation|Reservation) Number:\s+([\dA-Z]+)/im", $body, $m, PREG_OFFSET_CAPTURE, $offset);

        if (isset($m[1][0])) {
            [$it['Number'], $offset] = $m[1];
        }

        if (!isset($it['Number']) || !$it['Number']) {
            $it['Number'] = $this->http->FindPreg("#cancel+ation\s+number\s+is\s+([A-Z\d\-]+)#i");
        }

        $it['Cancelled'] = $this->http->FindPreg("#cancel+ation\s+number\s+is#i") ? true : false;

        preg_match("/^Name:\s+(.+)$/im", $body, $m, PREG_OFFSET_CAPTURE, $offset);

        if (isset($m[1][0])) {
            $m[1][0] = trim($m[1][0]);
            [$it['RenterName'], $offset] = $m[1];
        }

        if (!isset($it['RenterName']) || $it['RenterName'] == null) {
            $it['RenterName'] = $this->http->FindPreg("/Name:\s*([^\n]+)/ims");
        }

        if (!isset($it['RenterName']) || $it['RenterName'] == null) {
            $it['RenterName'] = $this->http->FindPreg("/(?:^|\n\s*)Dear\s([^\n,]+)/ims");
        }

        $it['RentalCompany'] = $this->http->FindPreg("/made a reservation with\s*([^.\n]+)/ims");

        if (!$it['RentalCompany']) {
            $it['RentalCompany'] = $this->http->FindPreg("#Thank you (?:again\s+)?for choosing\s+([^\n.]+)#");
        }

        preg_match("/Member Number:\s+(.+)$/im", $body, $m, PREG_OFFSET_CAPTURE, $offset);

        if (isset($m[1][0])) {
            $m[1][0] = trim($m[1][0]);
            [$it['AccountNumbers'], $offset] = $m[1];
        }

        preg_match("/^Pick Up Date:\s+(.+)$/im", $body, $m, PREG_OFFSET_CAPTURE, $offset);

        if (isset($m[1][0])) {
            $m[1][0] = strtotime(str_replace(' at ', ' ', $m[1][0]));
            [$it['PickupDatetime'], $offset] = $m[1];
        }

        if (isset($it['PickupDatetime']) && $it['PickupDatetime'] == null) {
            $it['PickupDatetime'] = strtotime(str_replace(' at ', ' ', $this->http->FindPreg("/Pick Up Date:\s+([^\n]+)/ims")));
        }

        preg_match("/^Drop Off Date:\s+(.+)$/im", $body, $m, PREG_OFFSET_CAPTURE, $offset);

        if (isset($m[1][0])) {
            $m[1][0] = strtotime(str_replace(' at ', ' ', $m[1][0]));
            [$it['DropoffDatetime'], $offset] = $m[1];
        }

        if (!isset($it['DropoffDatetime']) || $it['DropoffDatetime'] == null) {
            $it['DropoffDatetime'] = strtotime(str_replace(' at ', ' ', $this->http->FindPreg("/Drop Off Date:\s+([^\n]+)/ims")));
        }

        if (empty($it['DropoffDatetime']) && !empty($it['PickupDatetime'])) {
            $it['DropoffDatetime'] = MISSING_DATE;
        }

        //if ($start = strpos($body, 'Pick Up Location Address', $offset)) {
        if (($start = strpos($body, 'Pick Up Location Address', $offset))
            || ($start = strpos($body, 'Branch Location:', $offset))
            || ($start = strpos($body, 'Rental Office Address and Phone Number:', $offset))
            || ($start = strpos($body, 'Pickup Location:', $offset))
            ) {
            $start = strpos($body, "\n", $start) + 1;

            if (strpos($body, 'Tel.:', $offset) !== false) {
                $end = strpos($body, 'Tel.:', $offset) - 1;
            } elseif (strpos($body, 'Tel:', $offset) !== false) {
                $end = strpos($body, 'Tel:', $offset) - 1;
            } elseif (strpos($body, 'For more details', $offset) !== false) {
                $end = strpos($body, 'For more details', $offset) - 1;
            } elseif (strpos($body, 'Please call', $offset) !== false) {
                $end = strpos($body, 'Please call', $offset) - 1;
            } elseif (strpos($body, 'PH: ', $offset) !== false) {
                $end = strpos($body, 'PH: ', $offset) - 1;
            }

            if (isset($end)) {
                $address = trim(substr($body, $start, $end - $start));
                $it['PickupLocation'] = trim(str_replace("\r", '', str_replace("\n", ', ', $address)),
                    "*, \n");
                $offset = $end;

                if (preg_match("/^(?:Tel|PH)\.?:\s+([()+,\d -]+)/im", $body, $m, PREG_OFFSET_CAPTURE, $offset)) {
                    if (isset($m[1][0])) {
                        $m[1][0] = trim($m[1][0]);
                        [$it['PickupPhone'], $offset] = $m[1];
                    }
                }
            }
        }

        // TODO: DropoffLocation is mandatory field but no such section in current letter
        if (!empty($it['PickupLocation'])) {
            $it['DropoffLocation'] = $it['PickupLocation'];
        }

        if (($start = strpos($body, 'Pick Up Location Hours', $offset))
            || ($start = strpos($body, 'Office Hours', $offset))) {
            $start = strpos($body, "\n", $start) + 1;

            if (preg_match("/\n\s+\n/im", $body, $m, PREG_OFFSET_CAPTURE, $offset)) {
                $end = $m[0][1] - 1;
                $it['PickupHours'] = trim(substr($body, $start, $end - $start));
                $offset = $end;
            }
        }

        if (!isset($it['PickupHours']) || $it['PickupHours'] == null) {
            $it['PickupHours'] = $this->http->FindPreg("/Pick Up Location Hours[^\n]+(.*?)\s*Car and Rate Information:/ims");
        }

        if ($start = strpos($body, 'Car and Rate Information:', $offset)) {
            $start = strpos($body, "\n", $start) + 1;
            $end = strpos($body, "\n", $start);
            $it['CarType'] = trim(substr($body, $start, $end - $start));

            $start = $end + 1;
            $end = strpos($body, "\n", $start);
            $it['CarModel'] = trim(substr($body, $start, $end - $start));

            $start = $end + 1;
            $end = strpos($body, "Total Charges", $start) - 1;
            $temp = preg_split("/\n/im", substr($body, $start, $end - $start));

            foreach ($temp as $row) {
                $row = trim($row);

                if (empty($row)) {
                    continue;
                }

                if (preg_match("/([\d.]+)\s+([A-Z]+)\s+\((.+)\)/i", $row, $m)) {
                    $it['Fees'][] = ['Name' => $m[3], 'Charge' => $m[1]];
                }
            }

            preg_match("/Total Charges\s+([\d.]+)\s+([A-Z]+)/im", $body, $m, PREG_OFFSET_CAPTURE, $offset);

            if (isset($m[1][0])) {
                [$it['TotalCharge'], $offset] = $m[1];
            }

            if (isset($m[2][0])) {
                [$it['Currency'], $offset] = $m[2];
            }
        } elseif (preg_match('/Vehicle Information: (.+)/', $body, $m)) {
            $it['CarModel'] = $m[1];
        }

        // A very ghetto way to not break the previous mail compatibility
        if (isset($it['CarModel']) && strlen($it['CarModel']) > 64) {
            $it['CarModel'] = $this->http->FindPreg("#Car and Rate Information:.*?\n.+?\n([^\n]+)#ims");
        }

        if (!isset($it['Status']) || !$it['Status']) {
            $it['Status'] = $this->http->FindPreg("#(cancel+ation)\s+number\s+is#i") ? 'Cancelled' : null;
        }

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => [$it],
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[\.@](?:eth|enterprise)\.com$/ims', $from);
    }

    private function _checkInHeader(&$headers, $field, $source)
    {
        if (isset($headers[$field])) {
            foreach ($source as $temp) {
                if (stripos($headers[$field], $temp) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function _checkInBody(&$body, $field, $source)
    {
        if ($start = strpos($body, $field)) {
            $end = strpos($body, "\n", $start);

            if ($end === false) {
                $end = strlen($body);
            }
            $header = substr($body, $start, $end - $start);

            foreach ($source as $temp) {
                if (stripos($header, $temp) !== false) {
                    return true;
                }
            }
        }

        return false;
    }
}
