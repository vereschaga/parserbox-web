<?php

namespace AwardWallet\Engine\lner\Email;

//use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class AllBooked extends \TAccountChecker
{
    public $mailFiles = "lner/it-114196733.eml, lner/it-34126990.eml, lner/it-34933090.eml, lner/it-35028923.eml, lner/it-658366977.eml";

    public $lang = "en";
    public static $dictionary = [
        "en" => [
            'Reservations:'                 => ['Reservations:', 'Reservations', 'Your new reservation'],
            'Collection ref:'               => ['Booking Reference:', 'Collection ref:', 'New reservation reference:'],
            'Total price:'                  => ['Total price:', 'Total price'],
            "your booking is now confirmed" => ["your booking is now confirmed", ", your booking(s) are now confirmed "],
        ],
    ];

    private $detectFrom = "lner.co.uk";
    private $detectSubject = [
        "en"  => "you're all booked",
        "en2" => "Your reservation from",
    ];

    private $detectCompany = ['London North Eastern Railway'];

    private $detectBody = [
        "en" => [
            "this email is not your ticket",
            "your booking(s) are now confirmed and you can find all",
            "amended journey(s) are now confirmed",
            'Your train journey has changed',
            'Your booking(s) are now confirmed and you can find all the details below.',
            'Please take this email with you as proof of your new reservation.',
        ],
    ];

    private $detectLang = [
        "en" => "Journey Details",
    ];
    private $segmentsCnt = 0;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];

        foreach ($this->detectLang as $lang => $dBody) {
            if (strpos($body, $dBody) !== false || $this->http->XPath->query("//*[contains(normalize-space(.),'{$dBody}')]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }
        $this->parseTrain($email);

        // Price
        $total = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Total price:")) . "]/following-sibling::td[normalize-space()][1]");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#u", $total, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#u", $total, $m)) {
            $email->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;

            if (count($email->getItineraries()) === 1 && $this->segmentsCnt === 1) {
                $email->getItineraries()[0]->price()
                    ->total($this->amount($m['amount']))
                    ->currency($this->currency($m['curr']))
                ;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        if (strpos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = strip_tags($this->http->Response['body']);

        if ($this->http->XPath->query("//*[" . $this->contains($this->detectCompany) . "]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($body, $dBody) !== false || $this->http->XPath->query("//*[contains(normalize-space(.),'{$dBody}')]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseTrain(Email $email)
    {
        $t = $email->add()->train();

        // General
        $confirmation = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Collection ref:")) . "]/following::text()[normalize-space()][1]");

        if (!empty($confirmation)) {
            $t->general()
                ->confirmation($confirmation, $this->t("Collection ref"));
        } elseif (empty($this->http->FindSingleNode("//text()[" . $this->contains($this->t("Collection ref:")) . "]"))
            && (!empty($this->http->FindSingleNode("//text()[" . $this->eq(["Mobile Tickets", "Self-Print"]) . " or " . $this->contains(["printing and posting your tickets"]) . "]"))
                || empty($this->http->FindSingleNode("//text()[{$this->eq('Your Tickets')}]"))
                || empty($this->http->FindSingleNode("//text()[{$this->eq('your booking(s) are now confirmed and you can find all the details below or in your')}]"))
                || !empty($this->http->FindSingleNode("//text()[{$this->eq('Your Tickets')}]/following::text()[{$this->eq(['eTickets'])}]")))
            ) {
            $t->general()->noConfirmation();
        }

        $traveller = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("your booking is now confirmed")) . "]", null, true, "#^\s*([A-Za-z ]+),#");

        if (!empty($traveller)) {
            $t->general()
                ->traveller($traveller)
                ->status('Confirmed')
            ;
        }

        // Segments
        $xpath = "//text()[{$this->eq($this->t('Amended Journey details'))}]/following::text()[{$this->eq($this->t('Reservations:'))}]/ancestor::tr[.//img][1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $xpath = "//text()[{$this->eq($this->t('Reservations:'))}]/ancestor::tr[.//img][1]";
            $nodes = $this->http->XPath->query($xpath);
        }

        if ($nodes->length == 0) {
            $this->logger->debug("Segments not found");

            return false;
        }

        foreach ($nodes as $root) {
            if (!empty($this->http->FindSingleNode(".//text()[" . $this->contains($this->t("(Underground)")) . "]", $root))) {
                continue;
            }

            $s = $t->addSegment();
            $this->segmentsCnt++;

            $date = $this->normalizeDate($this->http->FindSingleNode("preceding::text()[starts-with(normalize-space(),'Journey ')][1]/ancestor::*[self::td or self::th][2]/following-sibling::*[1]", $root));

            if (empty($date)) {
                $date = $this->normalizeDate($this->http->FindSingleNode("preceding::text()[starts-with(normalize-space(),'Journey ')][1]/following::text()[normalize-space()][1]", $root));
            }

            if (empty($date)) {
                $date = $this->normalizeDate($this->http->FindSingleNode("descendant::text()[normalize-space()][1]", $root, null, "/^\s*\d{1,2}\\/\d{2}\\/\d{4}\s*$/"));
            }

            if (empty($date)) {
                $date = $this->normalizeDate($this->http->FindSingleNode("./descendant::text()[normalize-space()='Your new reservation']/following::text()[normalize-space()][1]", $root));
            }

            $regexp = "#(?<dTime>\d{2}:\d{2})\n(?<aTime>\d{2}:\d{2})\n(?<dName>.+?)\s*\((?<service>[^)]+)\)\n(?<aName>.+)#s";
            $regexp2 = "#^(?<dName>.+)\n(?<dTime>[\d\:]+)\n(?<aName>.+)\n(?<aTime>[\d\:]+)$#";
            $regexp3 = "#(?<dName>.+?)\n(?<dTime>\d{2}:\d{2})\n\(?(?<service>[^)]+)\)?\n(?<aName>.+)(?<aTime>\d{2}:\d{2})#s";

            $node = implode("\n", $this->http->FindNodes("./td[1]//td[not(.//td)][normalize-space()]", $root));

            if (stripos($node, ':') === false) {
                $node = implode("\n", $this->http->FindNodes("./td[1]/following::td[1]/descendant::text()[normalize-space()]", $root));
            }

            if (stripos($node, 'seat') !== false || stripos($node, 'No reservations made') !== false) {
                $node = implode("\n", $this->http->FindNodes("./td[2]/descendant::text()[normalize-space()]", $root));
            }

            if (preg_match($regexp, $node, $m) || preg_match($regexp2, $node, $m) || preg_match($regexp3, $node, $m)) {
                // for google, to help find correct address of stations
                if ($this->http->XPath->query("//node()[{$this->contains('Thanks, The London North Eastern Railway Team')}]")->length > 0
                    || $this->http->XPath->query("//node()[{$this->contains('London North Eastern Railway Limited. Registered in Eng')}]")->length > 0
                ) {
                    // https://en.wikipedia.org/wiki/London_North_Eastern_Railway
                    $region = ', UK';
                } else {
                    $region = '';
                }

                // Departure
                $s->departure()->name(str_replace("\n", "", $m['dName']) . $region);

                if (!empty($date)) {
                    $s->departure()->date(strtotime($m['dTime'], $date));
                    $s->arrival()->date(strtotime($m['aTime'], $date));
                }

                // Arrival
                $s->arrival()->name(str_replace("\n", "", $m['aName']) . $region);

                // Extra
                $number = $this->http->FindSingleNode("./following::text()[normalize-space()='Reservations changed to'][1]/following::text()[normalize-space()][1]", $root, true, "/^([A-Z\d]+)$/");

                if (!empty($number)) {
                    $s->extra()
                        ->number($number);
                } else {
                    $s->extra()
                        ->noNumber();
                }

                if (isset($m['service']) && !empty($m['service'])) {
                    $s->extra()
                        ->service($m['service']);
                }
            }
            $node = implode("\n", $this->http->FindNodes("./td[2]//td[not(.//td)][normalize-space()]", $root));

            if (stripos($node, 'seat') == false && stripos($node, 'seats') == false) {
                $node = implode("\n", $this->http->FindNodes("./td[1]/descendant::text()[normalize-space()]", $root));
            }

            if (preg_match("#Reservations\:?\n(?<cabin>\D+)\s(?<bookingCode>[A-Z])\,\s*seats?\:\s*(?<seat>[\d\s]+)#", $node, $m)) {
                /*if (isset($m['car']) && !empty($m['car'])) {
                    $s->extra()
                        ->car($m['car']);
                }*/

                $s->extra()
                    ->car($m['bookingCode']);

                /*if (isset($m['cabin']) && !empty($m['cabin'])) {
                    $s->extra()
                        ->cabin($m['cabin']);
                }

                if (isset($m['bookingCode']) && !empty($m['bookingCode'])) {
                    $s->extra()
                        ->bookingCode($m['bookingCode']);
                }*/

                $s->extra()
                    ->seats(array_map('trim', array_filter(explode(" ", $m['seat']))));
            }

            if (empty($s->getDepName()) && empty($s->getArrName()) && empty($s->getDepDate()) && empty($s->getArrDate())) {
                $t->removeSegment($s);
            }
        }

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*(\d{1,2})/(\d{2})/(\d{4})\s*$#u', //21/03/2019
        ];
        $out = [
            '$1.$2.$3',
        ];
        $date = preg_replace($in, $out, $date);
//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)){
        //			if ($en = MonthTranslate::translate($m[1], $this->lang))
        //				$date = str_replace($m[1], $en, $date);
        //		}
        return strtotime($date);
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return $text . '="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($price)
    {
        $price = trim($price);

        if (preg_match("#^([\d,. ]+)[.,](\d{2})$#", $price, $m)) {
            $price = str_replace([' ', ',', '.'], '', $m[1]) . '.' . $m[2];
        }

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
