<?php

namespace AwardWallet\Engine\goldcrown\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It2212666 extends \TAccountChecker
{
    public $mailFiles = "goldcrown/it-1898250.eml, goldcrown/it-1956025.eml, goldcrown/it-2050228.eml, goldcrown/it-2212666.eml, goldcrown/it-2229949.eml, goldcrown/it-2229966.eml, goldcrown/it-2247823.eml, goldcrown/it-2267547.eml, goldcrown/it-31566544.eml, goldcrown/it-31569825.eml";

    public static $dictionary = [
        'en' => [
            //            "Your Reservation Confirmation Number" => "",
            //            "Guest Information" => "",
            //            "Your reservation is confirmed" => "",
            "Cancellation Policy:" => ["Cancellation Policy:", "Cancel Policy:"],
            "Hotel Information"    => ["Hotel Information", "Reservation Information"],
            //            "Phone" => "",
            //            "Fax" => "",
            "Check-in"  => ["Check-in", "Check-In"],
            "Check-out" => ["Check-out", "Check-Out"],
            //            "Rooms:" => "",
            //            "Room " => "",
            //            "Room \d+ Summary" => "",
            //            "Guests:" => "",
            //            "Room Details:" => "",
            //            "Rate:" => "",
            //            "Total Stay:" => "",
            //            "Best Western Rewards® Number" => "",
        ],
        'it' => [
            "Your Reservation Confirmation Number" => "Numero di conferma prenotazione",
            "Guest Information"                    => "Informazioni sul cliente",
            "Your reservation is confirmed"        => "La tua prenotazione è confermata",
            "Cancellation Policy:"                 => ["Condizioni di cancellazione:"],
            "Hotel Information"                    => ["Hotel Information", "Reservation Information"],
            "Phone"                                => "Telefono",
            "Fax"                                  => "Fax",
            "Check-in"                             => "Arrivo",
            "Check-out"                            => "Partenza",
            //            "Rooms:" => "",
            "Room "                        => "Camera ",
            "Room \d+ Summary"             => "Camera \d+ Riepilogo",
            "Guests:"                      => "Clienti:",
            "Room Details:"                => "Dettagli Camera:",
            "Rate:"                        => "Tariffario:",
            "Total Stay:"                  => "Soggiorno Totale:",
            "Best Western Rewards® Number" => "Numero della tessera Best Western Rewards",
        ],
    ];

    private $detectFrom = 'bestwestern.com';
    private $detectSubject = [
        'en' => 'Best Western - Reservation Confirmation',
        'it' => 'Best Western - Conferma prenotazione',
    ];
    private $detectCompany = 'Best Western';
    private $detectBody = [
        'en' => 'Your Reservation Confirmation Number',
        'it' => 'Numero di conferma prenotazione',
    ];

    private $lang = "en";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = html_entity_decode($this->http->Response['body']);

        foreach ($this->detectBody as $lang => $dBody) {
            if (stripos($body, $dBody) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = html_entity_decode($this->http->Response['body']);

        if (stripos($body, $this->detectCompany) === false) {
            return false;
        }

        foreach ($this->detectBody as $lang => $dBody) {
            if (stripos($body, $dBody) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    private function parseEmail(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $conf = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Your Reservation Confirmation Number")) . "][1]", null, true, "#:\s*([A-Z\d]{5,})\s*$#");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Your Reservation Confirmation Number")) . "]/following::text()[normalize-space()][1]", null, true, "#^\s*([A-Z\d]{5,})\s*$#");
        }

        $h->general()
            ->confirmation($conf)
            ->traveller($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Guest Information")) . "]/following::tr[1]/descendant::text()[normalize-space()][1]"))
        ;

        if (!empty($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Your reservation is confirmed")) . "]/following::tr[1]"))) {
            $h->general()
                ->status('confirmed');
        }
        $cancellations = array_unique($this->http->FindNodes("//text()[" . $this->starts($this->t("Cancellation Policy:")) . "]/following::text()[normalize-space()][1][not(ancestor::strong)]"));
        $h->general()
            ->cancellation(implode("\n", $cancellations));

        $this->parseCancellationPolicy($h, $cancellations);

        // Program
        $account = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Best Western Rewards® Number")) . "]/following::text()[string-length(normalize-space())>1][1]", null, true, "#^\s*(\d{5,})\s*$#");

        if (!empty($account)) {
            $h->program()->account($account, false);
        }

        // Hotel
        $name = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Hotel Information")) . "]/following::*[self::strong or self::b][1]");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Phone")) . "]/ancestor::td[1][" . $this->contains($this->t("Fax")) . "]/preceding::text()[normalize-space()][1]");
        }
        $h->hotel()
            ->name($name)
        ;

        if (!empty($h->getHotelName())) {
            $info = implode("\n", $this->http->FindNodes("//text()[" . $this->starts($this->t("Hotel Information")) . "]/following::td[2]//text()"));

            if (preg_match("#" . preg_quote($h->getHotelName()) . "\s+([\s\S]+)\s+" . $this->preg_implode($this->t("Phone")) . "#", $info, $m)) {
                $h->hotel()->address(preg_replace("#\s*\n\s*#", ', ', trim($m[1])));
            }

            if (empty($info)) {
                $info = implode("\n", $this->http->FindNodes("//text()[" . $this->contains($this->t("Phone")) . "]/ancestor::td[1][" . $this->contains($this->t("Fax")) . "]//text()"));

                if (preg_match("#^\s*([\s\S]+)\s+" . $this->preg_implode($this->t("Phone")) . "#", $info, $m)) {
                    $h->hotel()->address(preg_replace("#\s*\n\s*#", ', ', trim($m[1])));
                }
            }

            if (preg_match("#\s+" . $this->preg_implode($this->t("Phone")) . "[:\s]+([\d\+\-\(\) \/]{5,})(?:\n|" . $this->preg_implode($this->t("Fax")) . ")#", $info, $m)) {
                $h->hotel()->phone(trim($m[1]));
            }

            if (preg_match("#\s+" . $this->preg_implode($this->t("Fax")) . "[:\s]+([\d\+\-\(\) \/]{5,})(?:\n|$)#", $info, $m)) {
                $h->hotel()->fax(trim($m[1]));
            }
        }

        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Check-in")) . "]/following::text()[normalize-space()][1])[1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Check-out")) . "]/following::text()[normalize-space()][1])[1]")))
        ;

        $rooms = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Rooms:")) . "]/following::text()[normalize-space()][1]");

        if (empty($rooms)) {
            $rooms = count(array_filter($this->http->FindNodes("//text()[" . $this->starts($this->t("Room ")) . "]", null, "#" . $this->t("Room \d+ Summary") . "#")));
        }
        $h->booked()
            ->rooms($rooms);

        $guests = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Rooms:")) . "]/following::text()[normalize-space()][position()<5][" . $this->starts($this->t("Guests:")) . "]/following::text()[normalize-space()][1]");

        if (empty($guests)) {
            $guests = array_sum(array_filter($this->http->FindNodes("//text()[" . $this->starts($this->t("Guests:")) . "]/following::text()[normalize-space()][1]", null, "#^\s*(\d+)#")));
        }
        $h->booked()
            ->guests($guests);

        $types = $this->http->FindNodes("//text()[" . $this->starts($this->t("Room Details:")) . "]/following::text()[normalize-space()][1]");
        $rateTypes = $this->http->FindNodes("//text()[" . $this->starts($this->t("Rate:")) . "]/following::text()[normalize-space()][1]");
        $setType = false;

        if (count($types) == count($rateTypes)) {
            $setType = true;
        }

        foreach ($types as $key => $type) {
            if ($setType == true) {
                $h->addRoom()
                    ->setType($type)
                    ->setRateType($rateTypes[$key])
                ;
            } else {
                $h->addRoom()->setType($type);
            }
        }

        $total = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Total Stay:")) . "]", null, true, "#:\s*(\S+.*)#");

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Total Stay:")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");
        }

        if (preg_match("#^\s*\D*(?<amount>\d[\d\., ]*)\s*(?<curr>[A-Z]{3})\s*$#", $total, $m)) {
            $h->price()
                ->total($this->amount(trim($m['amount'])))
                ->currency($this->currency($m['curr']))
            ;
        }

        return $email;
    }

    private function parseCancellationPolicy(\AwardWallet\Schema\Parser\Common\Hotel $h, array $cancellationTexts)
    {
        $countNonRefundable = 0;
        $deadlines = [];

        foreach ($cancellationTexts as $text) {
            if (
                    stripos($text, 'This reservation is too late to cancel') !== false
                    || stripos($text, 'This reservation cannot be cancelled') !== false
                    || stripos($text, 'Questa prenotazione non può essere cancellata') !== false //it
                    ) {
                $countNonRefundable++;

                continue;
            }

            if (
                    preg_match("#To avoid charge cancel by (?<hours>\d+)(?<timeAPM>[AP]M)? hotel time on (?<date>[\w\-]+)\.#i", $text, $m)
                    || preg_match("#Cancel before (?<hours>\d+)(?<timeAPM>[AP]M)? hotel time on (?<date>[\w\-, ]+) to avoid a charge#i", $text, $m)
                    ) {
                $date = $this->normalizeDate($m['date'] . ' ' . $m['hours'] . ':00' . ' ' . ($m['timeAPM'] ?? ''));

                if (!empty($date)) {
                    $deadlines[] = $date;
                }
            }
        }

        if (!empty($countNonRefundable) && $countNonRefundable == count($cancellationTexts)) {
            $h->booked()->nonRefundable();
        }

        if (!empty($deadlines) && count($deadlines) == count($cancellationTexts)) {
            $h->booked()->deadline(min($deadlines));
        }

        return true;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $text = '.')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(normalize-space(' . $text . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
//        $this->http->log($str);
        $in = [
            "#^\s*([^\d\s]+)\s+(\d+),\s+(\d{4})\s*-\s*[^\(]*?\(\s*(\d+:\d+)\s*\)\s*$#i", //November 26, 2011 - 12:00 P.M. (12:00)
            "#^\s*([^\d\s]+)\s+(\d+),\s+(\d{4})\s*;\s*(\d+:\d+\s*([ap][. ]*m[. ]*)?)\s*.*\s*$#i", //November 22, 2006; 1:00 P.M.  |  November 23, 2006; 12:00 Noon
            "#^\s*(\d+)-([^\d\s]+)-(\d{4})\s+(\d+:\d+\s*([ap]m)?)\s*$#i", //25-May-2013   4:00 PM
        ];
        $out = [
            "$2 $1 $3, $4",
            "$2 $1 $3, $4",
            "$1 $2 $3, $4",
        ];
        $str = str_replace('.', '', $str);
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function amount($price)
    {
        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if (preg_match("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $s;
        }
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
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
