<?php

namespace AwardWallet\Engine\koa\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "koa/it-63522264.eml, koa/it-65222985.eml, koa/it-65370017.eml";

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            "Value Kard Rewards #:" => ["Value Kard Rewards #:", "KOA Rewards #:"],
            "Estimated Total For Your Stay*" => ["Estimated Total For Your Stay*", "Total Payments to Date", "Estimated Total for Your Stay:*"],
        ],
    ];

    private $detectFrom = 'koa@email.koa.com';
    private $detectSubject = [
        "en" => [
            "KOA Reservation Confirmation",
            "KOA Holiday Reservation Cancellation",
            "KOA Journey Reservation Confirmation",
            "KOA Reservation Cancellation",
        ],
    ];

    private $detectBody = [
        'en'=> ['Your Campsite:'],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
//        $this->detectBody();

        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['subject'])) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            foreach ($detectSubject as $dSubject) {
                if (stripos($headers["subject"], $dSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (empty($body)) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }

        if ($this->http->XPath->query('//a[contains(@href,"/koa.com") or contains(@href,".koa.com") or contains(@href, "%2F%2Fkoa.com")]')->length === 0) {
            return false;
        }

        return $this->detectBody();
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
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("RESERVATION #")) . "]/following::text()[normalize-space()][1]", null, true,
                "/^\s*([\d.]{5,})\s*$/"), 'RESERVATION #')
            ->traveller($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Contact Info:")) . "]/following::text()[normalize-space()][1]", null, true,
                "/^\s*(\D+)\s*$/u"), false)
        ;
        $cancellation = $this->http->FindSingleNode("//p[" . $this->eq($this->t("Campground Cancellation Guideline:")) . "]/following-sibling::*[1]");

        if (!empty($cancellation)) {
            $h->general()->cancellation($cancellation);
        }

        if (!empty($this->http->FindSingleNode("(//*[" . $this->contains($this->t(", your cancellation is complete.")) . "])[1]"))) {
            $h->general()
                ->cancelled()
                ->status('Cancelled');
        }

        // Program
        $program = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Value Kard Rewards #:")) . "]/following::text()[normalize-space()][1]", null, true,
            "/^\s*([A-Z\d]{5,})\s*$/");

        if (!empty($program)) {
            $h->program()
                ->account($program, false);

            $email->add()->statement()
                ->setNumber($program)
                ->setNoBalance(true)
            ;
        }

        // Hotel
        $hotel = implode("\n", $this->http->FindNodes("//img[normalize-space(@alt) = 'Map to your KOA Kampground']/ancestor::td[1]/following-sibling::td[" . $this->contains($this->t("Phone:")) . "][1]//text()[normalize-space()]"));

        if (empty($hotel)) {
            $hotel = implode("\n", $this->http->FindNodes("//img[contains(@src,  'checkin/checkin-email-campground-information.jpg')]/following::text()[normalize-space()][1]/ancestor::td[1][" . $this->contains($this->t("Phone:")) . "][1]//text()[normalize-space()]"));
        }

        if (!empty($hotel)) {
            if (preg_match("/^\s*(.+)\n([\s\S]+?)\n\s*(?:" . $this->opt($this->t("Phone:")) . "|" . $this->opt($this->t("Fax:")) . ")/u", $hotel, $m)) {
                $h->hotel()
                    ->name($m[1])
                    ->address(preg_replace('/\s+/', ' ', $m[2]))
                ;
            }

            if (preg_match("/" . $this->opt($this->t("Phone:")) . "\s*([\d\(\(\- ]{5,})\n/u", $hotel, $m)) {
                $h->hotel()
                    ->phone($m[1])
                ;
            }

            if (preg_match("/" . $this->opt($this->t("Fax:")) . "\s*([\d\(\(\- ]{5,})\n/u", $hotel, $m)) {
                $h->hotel()
                    ->fax($m[1])
                ;
            }
        }

        // Booked
        $dates = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("RESERVED")) . "]/following::text()[normalize-space()][1]");

        if (preg_match("/^(.*\d{4}.*) - (.*\d{4}.*?)\(.+\)$/", $dates, $m)) {
            $h->booked()
                ->checkIn($this->normalizeDate($m[1]))
                ->checkOut($this->normalizeDate($m[2]))
            ;
        }

        $details = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Details:")) . "]/following::text()[normalize-space()][1]");

        if (preg_match("/\b(\d{1,2}) Adult/", $details, $m)) {
            $h->booked()
                ->guests($m[1]);
        }

        if (preg_match("/\b(\d{1,2}) Kid/", $details, $m)) {
            $h->booked()
                ->kids($m[1]);
        }

        // Rooms
        $type = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Your Campsite:")) . "]/ancestor::td[1][count(./*) = 2]/*[1]", null, true,
            "/^" . $this->opt($this->t("Your Campsite:")) . "\s*(.+)/s");
        $description = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Your Campsite:")) . "]/ancestor::td[1][count(./*) = 2]/*[2]");

        if (!empty($type) || !empty($description)) {
            $r = $h->addRoom();

            if (!empty($type)) {
                $r->setType($type);
            }

            if (!empty($description)) {
                $r->setDescription($description);
            }
        }

        $ratesPath = $this->http->XPath->query("//td[" . $this->eq($this->t("Rates")) . "]/following::tr[normalize-space()][1]/ancestor::*[1]/tr");
        $rates = [];

        foreach ($ratesPath as $rp) {
            $value = $this->http->FindSingleNode("./*[2]", $rp, true, "/(.+) \/ Night/");

            if (!empty($value) && !empty($this->http->FindSingleNode("./*[1]", $rp, true, "/\b\d{4}.* - .*\d{4}\b/"))) {
                $rates[] = $value;
            }
        }

        if (count($rates) == 1) {
            if (isset($r)) {
                $r->setRate(array_shift($rates));
            } else {
                $r = $h->addRoom();
                $r->setRate(array_shift($rates));
            }
        }

        // Total
        $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Estimated Total For Your Stay*")) . "]/following::text()[normalize-space()][1]");

        if (!empty($total) && preg_match("/\(([A-Z]{3})\)\s*$/", $total, $m)) {
            $total = trim(str_replace($m[0], '', $total));
            $currency = $m[1];
        }

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $h->price()
                ->total($this->amount($m['amount']))
                ->currency($currency ?? $this->currency($m['curr']))
            ;
        }

        return $email;
    }

    private function detectBody()
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//node()[{$this->contains($detectBody)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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
//        $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            //Monday, September 7, 2020 ;
            "/^\s*[^\d\s]+,\s*([^\d\s]+)\s+(\d+)\s*,\s+(\d{4})\s*$/",
        ];
        $out = [
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
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
        $price = str_replace([',', ' '], '', $price);

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
            '€'   => 'EUR',
            '$'   => 'USD',
            'US$' => 'USD',
            '£'   => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
