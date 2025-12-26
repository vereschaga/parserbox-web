<?php

namespace AwardWallet\Engine\marriott\Email;

use AwardWallet\Schema\Parser\Email\Email;

// parsers with similar formats: gcampaigns/HResConfirmation (object), marriott/It2506177, mirage/It1591085, triprewards/It3520762, woodfield/It2220680, goldpassport/WelcomeTo

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "marriott/it-17.eml, marriott/it-1802848.eml, marriott/it-2.eml, marriott/it-2099097.eml, marriott/it-9.eml";
    private static $headers = [
        'gcampaigns' => [
            'from' => ['pkghlrss.com'],
            'subj' => [
                "/Marriott Reservation Confirmation/i",
                "/Hotel Modification Acknowledgement/i",
                "/The Westfields Marriott Washington Dulles/i",
            ],
        ],
        'marriott' => [
            'from' => ['marriott.com'],
            'subj' => [
                "/Marriott Reservation Confirmation/i",
                "/Hotel Modification Acknowledgement/i",
                "/The Westfields Marriott Washington Dulles/i",
                "/Gaylord Opryland Resort & Convention Center Reservation Confirmation/",
            ],
        ],
    ];

    private $bodies = [
        'gcampaigns' => [
            'groupcampaigns@pkghlrss.com',
            '//a[contains(@href,"passkey.com")]',
        ],
        'marriott' => [
            '//a[contains(@href,"marriott.com")]',
        ],
    ];

    private $code;

    public function detectEmailFromProvider($from)
    {
        foreach (self::$headers as $code => $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (preg_match($subj, $headers['subject'])) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;
            }

            if ($bySubj) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
        }

        return stripos($body, 'Marriott International') !== false && stripos($body, 'Additional Guest') !== false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmail($email);

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class);

        if (null !== ($prov = $this->getProvider($parser))) {
            $email->setProviderCode($prov);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$headers);
    }

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            if ($this->code === 'marriott') {
                return null;
            } else {
                return $this->code;
            }
        }

        foreach ($this->bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($this->http->Response['body'],
                                $search) !== false)
                    ) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function parseEmail(Email $email)
    {
        $text = $this->http->Response['body'];

        $number = $this->re("/([A-Z\d]+)$/", $this->nextTD('Online Confirmation Number'));

        if (empty($number) && preg_match('/Hotel Confirmation Number:\s*([A-Z\d]+)/', $text, $matches)) {
            $number = $matches[1];
        }
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($number)
            ->traveller($this->nextTD('Reservation Name'))
            ->cancellation($this->nextTD(['Cancellation Policy', 'Cancel Policy']));

        if (!empty($resDate = strtotime($this->nextTD('Date Booked')))) {
            $h->general()
                ->date($resDate);
        }

        if (preg_match('#We\s+are\s+pleased\s+to\s+confirm\s+your\s+reservation.*?at\s+(.+?)\.#i', $text, $matches)) {
            $hotelName = $matches[1];
        } elseif (preg_match('#seeing you soon\!\s*-([^\n]+)#i', $text, $matches)) {
            $hotelName = $matches[1];
        } elseif (preg_match('#Your\s+reservation\s+at\s+the\s+(.+)\s+has\s+been\s+modified#i', $text, $matches)) {
            $hotelName = $matches[1];
        } elseif (preg_match('#Your\s+reservation\s+at\s+(.+)\s+has\s+been\s+modified#i', $text, $matches)) {
            $hotelName = $matches[1];
        }

        if (isset($hotelName)) {
            $h->hotel()
                ->name($hotelName)
                ->noAddress();
        }

        $checkInDate = strtotime(preg_replace(['/check-in\s*:/ims', '/noon/ims'], [' ', 'pm'],
            $this->nextTD('Arrival Date')));
        $checkOutDate = strtotime(preg_replace(['/check-out\s*:/ims', '/noon/ims'], [' ', 'pm'],
            $this->nextTD('Departure Date')));
        $guests = $this->nextTD('Number of Guests');
        $rooms = $this->nextTD('Number of Rooms');

        $h->booked()
            ->checkIn($checkInDate)
            ->checkOut($checkOutDate)
            ->guests($guests)
            ->rooms($rooms);

        $room = $h->addRoom();
        $room->setType($this->nextTD('Room Type'));

        $tot = $this->getTotalCurrency($this->nextTD('Total Charges'));

        if (!empty($tot['Total'])) {
            $h->price()
                ->total($tot['Total']);

            if (!empty($tot['Currency'])) {
                $h->price()
                    ->currency($tot['Currency']);
            }
        }

        if (stripos($text, 'HOTEL MODIFICATION CONFIRMATION')) {
            $h->general()->status('modified');
        }

        if (!empty($node = $h->getCancellation())) {
            $this->detectDeadLine($h, $node);
        }

        return true;
    }

    private function nextTD($field, $num = 1)
    {
        return $this->http->FindSingleNode("//text()[{$this->starts($field)}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][{$num}]");
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText)
    {
        //Here we describe various variations in the definition of dates deadLine
        if (preg_match("#Cancellations made after (\d+:\d+(?:\s*[ap]m)?|\d+\s*[ap]m) day of arrival will forfeit one night#i",
                $cancellationText, $m)
            || (preg_match("#Reservations may be cancelled without charge by (\d+:\d+(?:\s*[ap]m)?|\d+\s*[ap]m) on the day of arrival.#i",
                $cancellationText, $m))
        ) {
            $h->booked()
                ->deadlineRelative('0 days', $m[1]);
        } elseif (preg_match("#^(\d+ hours?) prior to arrival. #i", $cancellationText, $m)
            || preg_match("#Cancellations made within (\d+ hours?) of arrival will forfeit one night#i",
                $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m[1], '00:00');
        }
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
