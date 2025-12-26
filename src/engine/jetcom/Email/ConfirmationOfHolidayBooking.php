<?php

namespace AwardWallet\Engine\jetcom\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class ConfirmationOfHolidayBooking extends \TAccountChecker
{
    public $mailFiles = "jetcom/it-47821422.eml, jetcom/it-8877140.eml, jetcom/it-9858993.eml";

    public static $dictionary = [
        "en" => [
            "Your holiday to..." => ["Your holiday to...", "Your City Break to..."],
            "Terminal"           => ["Terminal", "TERMINAL"],
        ],
    ];

    public $lang = "en";
    private $reFrom = "@jet2holidays.com";
    private $reSubject = [
        "en" => "from Jet2Holidays",
    ];
    private $keywordProv = 'Jet2holidays';
    private $reBody = [
        "en" => ["Your holiday to...", "Your City Break to..."],
    ];
    private $pax;

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || stripos($headers["subject"], $this->keywordProv) !== false)
                    && stripos($headers["subject"], $reSubject) !== false
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->keywordProv) === false) {
            return false;
        }

        foreach ($this->reBody as $re) {
            if ($this->http->XPath->query("//*[{$this->contains($re)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = true;

        foreach ($this->reBody as $lang => $re) {
            if ($this->http->XPath->query("//*[{$this->contains($re)}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $total = $this->amount($this->nextText("Holiday Price"));

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//text()[normalize-space()='Total Holiday Price']/following::text()[normalize-space()][1]", null, true, "/^\D+([\d\.\,]+)/");
        }
        $currency = $this->currency($this->nextText("Holiday Price"));

        if (empty($currency)) {
            $currency = $this->http->FindSingleNode("//text()[normalize-space()='Total Holiday Price']/following::text()[normalize-space()][1]", null, true, "/^(\D+)[\d\.\,]+/");
        }

        if ($total !== null && !empty($currency)) {
            $email->price()
                ->total($total)
                ->currency($this->currency($currency));
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHtml(Email $email)
    {
        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Passenger Details'))}]/ancestor::tr[1][{$this->contains('Contact Details')}]")->length > 0) {
            $this->pax = $this->http->FindNodes("//text()[{$this->eq('Passenger Details')}]/ancestor::tr[1]/following-sibling::tr[1]/td[3]//tr",
                null, "/(.*?)\s+-/");
        } else {
            $this->pax = $this->http->FindNodes("//text()[{$this->eq('Passenger Details')}]/ancestor::tr[1]/following-sibling::tr/descendant::text()[normalize-space()!=''][not({$this->contains('Lead Passenger')})]",
                null, "/(.*?)\s+-/");
        }

        $this->parseFlight($email);
        $this->parseHotel($email);
    }

    private function parseFlight(Email $email)
    {
        $r = $email->add()->flight();

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->starts("Booking Reference") . "]", null,
                true, "/Booking Reference\s+(.+)/"), 'Booking Reference')
            ->travellers($this->pax);

        $xpath = "//text()[{$this->eq("Departs:")}]/ancestor::tr[1]/..";
        $roots = $this->http->XPath->query($xpath);

        if ($this->http->XPath->query($xpath . "/tr[1][count(./td)=1]")->length > 0) {
            $this->parseSegments_1($roots, $r);
        } else {
            $this->parseSegments_2($roots, $r);
        }
    }

    private function parseSegments_1(\DOMNodeList $roots, Flight $r)
    {
        foreach ($roots as $root) {
            $s = $r->addSegment();

            $s->airline()
                ->number($this->re("/\w{2}(\d+)/", $this->nextText("Flight Number:", $root)))
                ->name($this->re("/(\w{2})\d+/", $this->nextText("Flight Number:", $root)));

            $s->departure()
                ->noCode()
                ->name($this->http->FindSingleNode("./tr[1]", $root, true,
                    "/(.*?)(?:\s+{$this->opt($this->t('Terminal'))}[ ]+\w+)? to /"))
                ->terminal($this->http->FindSingleNode("./tr[1]", $root, true,
                    "/{$this->opt($this->t('Terminal'))}[ ]+(\w+) to /"), false, true)
                ->date($this->normalizeDate($this->nextText("Departs:", $root)));

            $s->arrival()
                ->noCode()
                ->name($this->http->FindSingleNode("./tr[1]", $root, true,
                    "/ to (.*?)(?:\s+{$this->opt($this->t('Terminal'))}[ ]+\w+|$)/"))
                ->terminal($this->http->FindSingleNode("./tr[1]", $root, true,
                    "/ to .*?\s+{$this->opt($this->t('Terminal'))}[ ]+(\w+)/"), false,
                    true)
                ->date($this->normalizeDate($this->nextText("Arrives:", $root)));

            $seats = array_filter(explode(",",
                $this->http->FindSingleNode("./ancestor::tr[1]//text()[" . $this->starts("Seats:") . "]", $root, true,
                    "/Seats:\s*((?:\d+[A-z][ ,]*)+)/")));

            if (!empty($seats)) {
                $s->extra()->seats($seats);
            }
        }
    }

    private function parseSegments_2(\DOMNodeList $roots, Flight $r)
    {
        foreach ($roots as $root) {
            $s = $r->addSegment();

            $s->airline()
                ->number($this->re("/\w{2}(\d+)/", $this->nextText("Flight Number:", $root)))
                ->name($this->re("/(\w{2})\d+/", $this->nextText("Flight Number:", $root)));

            $s->departure()
                ->noCode()
                ->name($this->http->FindSingleNode("./preceding::tr[normalize-space()!=''][position()<=2][./td[2][contains(.,'to')]][1]/td[1]",
                    $root))
                ->terminal($this->http->FindSingleNode("./preceding::tr[normalize-space()!=''][1][./td[{$this->contains($this->t('Terminal'))}]][1]/td[1]",
                    $root, true,
                    "/{$this->opt($this->t('Terminal'))}[ ]+(\w+)$/"), false, true)
                ->date($this->normalizeDate($this->nextText("Departs:", $root)));

            $s->arrival()
                ->noCode()
                ->name($this->http->FindSingleNode("./preceding::tr[normalize-space()!=''][position()<=2][./td[2][contains(.,'to')]][1]/td[3]",
                    $root))
                ->terminal($this->http->FindSingleNode("./preceding::tr[normalize-space()!=''][1][./td[{$this->contains($this->t('Terminal'))}]][1]/td[3]",
                    $root, true,
                    "/{$this->opt($this->t('Terminal'))}[ ]+(\w+)$/"), false, true)
                ->date($this->normalizeDate($this->nextText("Arrives:", $root)));

            $seats = array_filter(explode(",",
                $this->http->FindSingleNode("./ancestor::tr[1]//text()[" . $this->starts("Seats:") . "]", $root, true,
                    "/Seats:\s*((?:\d+[A-z][ ,]*)+)/")));

            if (!empty($seats)) {
                $s->extra()->seats($seats);
            }
        }
    }

    private function parseHotel(Email $email)
    {
        $r = $email->add()->hotel();

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->starts("Booking Reference") . "]", null,
                true, "/Booking Reference\s+(.+)/"))
            ->travellers($this->pax);

        $r->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t("Your holiday to..."))}]/following::text()[normalize-space()!=''][1]"))
            ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t("Your holiday to..."))}]/following::text()[normalize-space()!=''][1]/ancestor::tr[1]/following-sibling::tr[last()]/descendant::text()[normalize-space(.)!=''][1]"))
            ->phone($this->http->FindSingleNode("//text()[{$this->eq($this->t("Your holiday to..."))}]/following::text()[normalize-space()!=''][1]/ancestor::tr[1]/following-sibling::tr[last()]/descendant::text()[normalize-space(.)!=''][2]"),
                false, true);

        $r->booked()
            ->guests($this->http->FindSingleNode("(//img[contains(@src, '/occupancy.png')])[last()]/following::text()[normalize-space(.)!=''][1]",
                null, true, "/(\d+) Adult/"))
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//img[contains(@src, '/nights.png')]/following::text()[normalize-space(.)!=''][1]",
                null, true, "/from\s+(.+)/")));

        if ($date = $r->getCheckInDate()) {
            $r->booked()
                ->checkOut(strtotime("+" . $this->http->FindSingleNode("//img[contains(@src, '/nights.png')]/following::text()[normalize-space(.)!=''][1]",
                        null, true, "/(\d+) night/") . " day", $date));
        }

        $roomsCnt = 0;
        $roomsRoot = $this->http->XPath->query("//img[contains(@src, '/room.png')]");

        foreach ($roomsRoot as $root) {
            $cnt = (int) $this->http->FindSingleNode("./following::text()[normalize-space(.)!=''][1]", $root, true,
                "/(\d+) x /");
            $roomsCnt += $cnt;
            $room = $r->addRoom();
            $room->setType($this->http->FindSingleNode("./following::text()[normalize-space(.)!=''][1]", $root, true,
                "/x (.*?)(\s*-|$)/"));
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $in = [
            "/^[^\s\d]+ (\d+ [^\s\d]+ \d{4}) at (\d+:\d+)$/", //Fri 09 Mar 2018 at 17:05
        ];
        $out = [
            "$1, $2",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("/\d+\s+([^\d\s]+)\s+\d{4}/", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("/[.,](\d{3})/", "$1", $this->re("/([\d\,\.]+)/", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        if ($code = $this->re("/(?:^|\s)([A-Z]{3})(?:$|\s)/", $s)) {
            return $code;
        }
        $s = $this->re("/([^\d\,\.]+)/", $s);

        foreach ($sym as $f => $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)!=''][1]",
            $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
