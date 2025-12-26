<?php

namespace AwardWallet\Engine\stash\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "stash/it-1.eml, stash/it-2.eml";
    private $_emails = [
        'resinquiry@crm.data2gold.com',
        'reservations@boulderado.com',
    ];
    private $_subjects = [
        'LaPlaya Beach & Golf Resort: Your Reservation Confirmation',
        'Hotel Boulderado: Your Reservation Confirmation',
    ];
    private $_detected = null; // detected type of letter

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[\.@](crm\.data2gold|boulderado)\.com$/ims', $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        $from = $this->_checkInHeader($headers, 'from', $this->_emails);
        $subject = $this->_checkInHeader($headers, 'subject', $this->_subjects);
        $this->_detected = (int) $from . (int) $subject;

        return $from && $subject;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // If forwarded message
        $body = $parser->getPlainBody();
        $from = $this->_checkInBody($body, 'From:', $this->_emails);
        $subject = $this->_checkInBody($body, 'Subject:', $this->_subjects);
        $this->_detected = (int) $from . (int) $subject;

        return $from || $subject;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        // workaround for bug in EmailTester.php
        $this->detectEmailByHeaders($parser->getHeaders());
        $this->detectEmailByBody($parser);

        switch ($this->_detected) {
            case '11':
                $it = $this->_parseLetterType1($parser);

                break;

            case '22':
                $it = $this->_parseLetterType2($parser);

                break;

            default:
                return [
                    0 => [
                        'NoItineraries' => true,
                    ],
                ];
        }

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => [$it],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    private function _checkInHeader(&$headers, $field, $source)
    {
        if (isset($headers[$field])) {
            foreach ($source as $key => $temp) {
                if (stripos($headers[$field], $temp) !== false) {
                    return $key + 1;
                }
            }
        }

        return false;
    }

    private function _checkInBody(&$body, $field, $source)
    {
        $end = 0;

        while ($start = strpos($body, $field, $end)) {
            $end = strpos($body, "\n", $start);

            if ($end === false) {
                break;
            }
            $header = substr($body, $start, $end - $start);

            foreach ($source as $key => $temp) {
                if (stripos($header, $temp) !== false) {
                    return $key + 1;
                }
            }
        }

        return false;
    }

    /**
     * Parse letter type 1 (resinquiry@crm.data2gold.com / LaPlaya Beach & Golf Resort: Your Reservation Confirmation).
     */
    private function _parseLetterType1(\PlancakeEmailParser &$parser)
    {
        $body = $parser->getBody();
        $body = preg_replace("/^\>?\s+/im", '', $body);
        $body = preg_replace("/:$/im", '', $body);
        $offset = 0;

        $it = [];
        $it['Kind'] = 'R';

        if ($start = strpos($body, 'Confirmation Number', $offset)) {
            $start = strpos($body, "\n", $start) + 1;
            $end = strpos($body, 'Guest Name', $offset) - 1;
            $it['ConfirmationNumber'] = preg_replace("/\s+/", ' ', trim(substr($body, $start, $end - $start)));
            $offset = $end;
        }

        if ($start = strpos($body, 'Guest Name', $offset)) {
            $start = strpos($body, "\n", $start) + 1;
            $end = strpos($body, 'Arrival Date', $offset) - 1;
            $temp = explode(',', preg_replace("/\s+/", ' ', trim(substr($body, $start, $end - $start))));

            if (!empty($temp)) {
                foreach ($temp as $guest) {
                    $it['GuestNames'][] = trim($guest);
                }
                $offset = $end;
            }
        }

        if ($start = strpos($body, 'Arrival Date', $offset)) {
            $start = strpos($body, "\n", $start) + 1;
            $end = strpos($body, 'Departure Date', $offset) - 1;
            $it['CheckInDate'] = strtotime(preg_replace("/\s+/", ' ', trim(substr($body, $start, $end - $start))));
            $offset = $end;
        }

        if ($start = strpos($body, 'Departure Date', $offset)) {
            $start = strpos($body, "\n", $start) + 1;
            $end = strpos($body, 'Room Type', $offset) - 1;
            $it['CheckOutDate'] = strtotime(preg_replace("/\s+/", ' ', trim(substr($body, $start, $end - $start))));
            $offset = $end;
        }

        if ($start = strpos($body, 'Room Type', $offset)) {
            $start = strpos($body, "\n", $start) + 1;
            $end = strpos($body, 'Package Description', $offset) - 1;
            $it['RoomType'] = preg_replace("/\s+/", ' ', trim(substr($body, $start, $end - $start)));
            $offset = $end;
        }

        if ($start = strpos($body, 'Package Description', $offset)) {
            $start = strpos($body, "\n", $start) + 1;
            $end = strpos($body, 'Average Daily Rate', $offset) - 1;
            $it['RoomTypeDescription'] = preg_replace("/\s+/", ' ', trim(substr($body, $start, $end - $start)));
            $offset = $end;
        }

        if ($start = strpos($body, 'Average Daily Rate', $offset)) {
            $start = strpos($body, "\n", $start) + 1;
            $end = strpos($body, 'Total Stay Amount', $offset) - 1;
            $it['Rate'] = preg_replace("/\s+/", ' ', trim(substr($body, $start, $end - $start)));
            $offset = $end;
        }

        if ($start = strpos($body, 'Total Stay Amount', $offset)) {
            $start = strpos($body, "\n", $start) + 1;
            $end = strpos($body, 'Policy Information', $offset) - 1;
            $temp = preg_replace("/\s+/", ' ', trim(substr($body, $start, $end - $start)));
            preg_match("/^.?([\d\.]+)/i", $temp, $m);

            if (isset($m[1])) {
                $it['Cost'] = $m[1];
                $offset = $end;
            }
        }

        if ($start = strpos($body, 'Cancellation', $offset)) {
            $start = strpos($body, "\n", $start) + 1;
            $end = strpos($body, 'Contact Information', $offset) - 1;
            $it['CancellationPolicy'] = preg_replace("/\s+/", ' ', trim(substr($body, $start, $end - $start)));
            $offset = $end;
        }

        if ($start = strpos($body, 'Reservations', $offset)) {
            $start = strpos($body, "\n", $start) + 1;
            $end = strpos($body, 'Concierge', $offset) - 1;
            $it['Phone'] = preg_replace("/\s+/", ' ', trim(substr($body, $start, $end - $start)));
            $offset = $end;
        }

        if ($start = strpos($body, 'Points FOR your upcoming stay', $offset)) {
            $start = strpos($body, "\n", $start) + 1;
            $end = strpos($body, "\n", $start) - 1;
            $temp = explode('|', preg_replace("/\s+/", ' ', trim(substr($body, $start, $end - $start))));

            if (isset($temp[1])) {
                $it['HotelName'] = trim($temp[0]);
                $it['Address'] = trim($temp[1]);
            }
        }

        return $it;
    }

    /**
     * Parse letter type 2 (reservations@boulderado.com / Hotel Boulderado: Your Reservation Confirmation).
     */
    private function _parseLetterType2(\PlancakeEmailParser &$parser)
    {
        $body = $parser->getBody();
        $body = preg_replace("/^\>?\s+/im", '', $body);
        $body = preg_replace("/:$/im", '', $body);
        $offset = 0;

        $it = [];
        $it['Kind'] = 'R';

        if ($start = strpos($body, 'Confirmation Number', $offset)) {
            $start = strpos($body, "\n", $start) + 1;
            $end = strpos($body, 'Guest Name', $offset) - 1;
            $it['ConfirmationNumber'] = preg_replace("/\s+/", ' ', trim(substr($body, $start, $end - $start)));
            $offset = $end;
        }

        if ($start = strpos($body, 'Guest Name', $offset)) {
            $start = strpos($body, "\n", $start) + 1;
            $end = strpos($body, 'Arrival Date', $offset) - 1;
            $temp = explode(',', preg_replace("/\s+/", ' ', trim(substr($body, $start, $end - $start))));

            if (!empty($temp)) {
                foreach ($temp as $guest) {
                    $it['GuestNames'][] = trim($guest);
                }
                $offset = $end;
            }
        }

        if ($start = strpos($body, 'Arrival Date', $offset)) {
            $start = strpos($body, "\n", $start) + 1;
            $end = strpos($body, 'Departure Date', $offset) - 1;
            $it['CheckInDate'] = strtotime(preg_replace("/\s+/", ' ', trim(substr($body, $start, $end - $start))));
            $offset = $end;
        }

        if ($start = strpos($body, 'Departure Date', $offset)) {
            $start = strpos($body, "\n", $start) + 1;
            $end = strpos($body, 'Room Type', $offset) - 1;
            $it['CheckOutDate'] = strtotime(preg_replace("/\s+/", ' ', trim(substr($body, $start, $end - $start))));
            $offset = $end;
        }

        if ($start = strpos($body, 'Room Type', $offset)) {
            $start = strpos($body, "\n", $start) + 1;
            $end = strpos($body, 'Nightly Rate', $offset) - 1;
            $it['RoomType'] = preg_replace("/\s+/", ' ', trim(substr($body, $start, $end - $start)));
            $offset = $end;
        }

        if ($offset > 0 && $start = strpos($body, 'Nightly Rate', $offset)) {
            $start = strpos($body, "\n", $start) + 1;
            $end = strpos($body, "\n", $start) - 1;
            $it['Rate'] = preg_replace("/\s+/", ' ', trim(substr($body, $start, $end - $start)));
            $offset = $end;
        }

        if ($offset > 0 && $start = strpos($body, 'Cancellation Policy', $offset)) {
            $start = strpos($body, "\n", $start) + 1;
            $end = strpos($body, 'Minimum Stay', $offset) - 1;
            $it['CancellationPolicy'] = preg_replace("/\s+/", ' ', trim(substr($body, $start, $end - $start)));
            $offset = $end;
        }
        preg_match("/We look forward to welcoming you to the ([^\.]+)\./i", $body, $m, PREG_OFFSET_CAPTURE, $offset);

        if (isset($m[1])) {
            $it['HotelName'] = trim($m[1][0]);
            $offset = $m[1][1];
        }

        if ($offset > 0 && $start = strpos($body, 'Reservation Department', $offset)) {
            $start = strpos($body, "\n", $start) + 1;
            $temp = explode("\n", substr($body, $start));

            if (isset($temp[3])) {
                $temp = explode("•", $temp[3]);
                $it['Address'] = trim($temp[0]);
            }
        }

        return $it;
    }
}
