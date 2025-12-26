<?php

namespace AwardWallet\Engine\milleniumnclc\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmationPlain extends \TAccountChecker
{
    public $mailFiles = "milleniumnclc/it-15502522.eml";

    private $reFrom = "MBreservations@mill-usa.com";
    private $reBody = [
        'en' => 'BOOKING SUMMARY',
    ];
    private $reSubject = [
        ' - Reservation Confirmation - ',
    ];
    private static $dict = [
        'en' => [],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $email->setType('ReservationConfirmationPlain');
        $this->parseEmail($email, $parser->getPlainBody());
        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        if (stripos($body, 'MillenniumHotels') === false) {
            return false;
        }

        foreach ($this->reBody as $re) {
            if (stripos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email, string $text): void
    {
        $h = $email->add()->hotel();
        $h->general()
            ->confirmation($this->re("#Confirmation Number:\s+\*?([A-Z\d]{5,})\*?\s+#", $text), 'Confirmation Number')
            ->traveller($this->re("#Name:\s+([A-Za-z \-]{5,})\s+#", $text))
            ->cancellation(trim(preg_replace("#\s+#", ' ', $this->re("#GUARANTEE AND CANCELLATION POLICIES\s+([\s\S]+?)GUEST DETAILS#", $text))));

        $h->hotel()
            ->name(trim($this->re("#HOTEL DETAILS\s+(.+)\s+(?:(?:.*\n){1,10})?(Telephone:|We look forward)#", $text)))
            ->address(trim(preg_replace("#\s+#", ' ', $this->re("#HOTEL DETAILS\s+.+\s+((?:.*\n){1,10}?)(?:Telephone:|We look forward)#", $text))))
            ->phone(trim($this->re("#HOTEL DETAILS[\s\S]*?Telephone:\s*(\S[\d\-\+\(\) \.]+)\s+#", $text)));

        $h->booked()
            ->checkIn(strtotime($this->re("#Date of Arrival:\s+(.+)#", $text)))
            ->checkOut(strtotime($this->re("#Date of Departure:\s+(.+)#", $text)))
            ->guests($this->re("#Number of Adults:\s*(\d+)\s+#", $text))
            ->kids($this->re("#Number of Children:\s*(\d+)\s+#", $text))
            ->rooms($this->re("#Number of Rooms:\s*(\d+)\s+#", $text));

        $r = $h->addRoom();
        $r->setType($this->re("#Room Type:\s*(.+)\s+#", $text))
            ->setRate($this->re("#Average Daily Rate:\s+(.+)\s+#", $text));
        $h->price()
            ->total((float) $this->re("#Total Cost.*?:\s*[A-Z]{3}[ ]*(\d[\d\.]+)\s+#", $text))
            ->currency($this->re("#Total Cost.*?:\s*([A-Z]{3})[ ]*\d[\d\.]+\s+#", $text));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
