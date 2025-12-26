<?php

namespace AwardWallet\Engine\spirit\Email;

use AwardWallet\Schema\Parser\Email\Email;

class CheckInReminder extends \TAccountChecker
{
    public $mailFiles = "spirit/it-48982171.eml, spirit/it-58698846.eml";

    private static $detectors = [
        'en' => [
            "Save yourself time and money at the airport by checking in online and printing your boarding pass yourself, or by downloading the Spirit Airlines App and getting your boarding pass right on your phone.",
            "Your flight is just around the corner and you haven't picked your seat. We will begin assigning seats at the time of check in. If choosing where you sit is important to you, select your seat now.",
            "we noticed that you haven't booked any carry-ons or checked bags for your flight. See how much you could save by paying for a bag beforehand.",
            "See how much you could save by paying for a bag beforehand",
            "Your fare comes with a seat and one personal item",
        ],
    ];

    private $detectLang = [
        "en" => ["Check In Online And Save", "Bringing a Carry-On or Checking a Bag", "Window Or Aisle", "flying with us", "You booked a Spirit flight"],
    ];
    private static $dictionary = [
        'en' => [
            //            "Confirmation" => "",
            //            "Hi" => "",
        ],
    ];

    private $body = "spirit.com";

    private $subject = [
        "Check In For Tomorrow's Flight!",
        "Purchase Bags For Your Flight to", //Purchase Bags For Your Flight to New Orleans
        "Pick A Seat For Your Flight To",
    ];

    private $lang;

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@fly.spirit-airlines.com') !== false
            || stripos($from, '@save.spirit-airlines.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $text = $this->http->Response['body'];

        if (stripos($text, $this->body) === false) {
            return false;
        }

        if ($this->detectBody()) {
            return $this->assignLang();
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('CheckInReminder');
        $this->parseEmail($email);

        return $email;
    }

    private function parseEmail(Email $email): void
    {
        $re = '/(.+)\s\(([A-Z]{3})\)\s*(\d{1,2}\/\d{1,2}\/\d{1,2})\s(\d{1,2}:\d{1,2}\s[A-Z]{2})/u';
        $r = $email->add()->flight();

        $confNo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation') . ' #')}]/following-sibling::strong");

        if (!empty($confNo)) {
            $r->general()
                ->confirmation($confNo, $this->t('Confirmation'));
        }
        $traveller = $this->http->FindSingleNode("//text()[normalize-space()='Hi']/following::text()[normalize-space()][1]", null, true, "#(.+),$#");

        if (!empty($traveller)) {
            $r->general()
                ->traveller($traveller, false);
        }

        $xPath = "//img[contains(@src,'images/greyarrow')]/ancestor::tr[1]";
        $segments = $this->http->XPath->query($xPath);

        foreach ($segments as $segment) {
            $s = $r->addSegment();
            $s->airline()
                ->noNumber()
                ->name('NK');

            $dep = $this->http->FindSingleNode("./td[1]", $segment);

            if (preg_match($re, $dep, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->code($m[2])
                    ->date(strtotime($m[3] . " " . $m[4]));
            }

            $arr = $this->http->FindSingleNode("./td[3]", $segment);

            if (preg_match($re, $arr, $m)) {
                $s->arrival()
                    ->name($m[1])
                    ->code($m[2])
                    ->date(strtotime($m[3] . " " . $m[4]));
            }
        }
    }

    private function detectBody(): bool
    {
        foreach (self::$detectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        foreach ($this->detectLang as $lang => $words) {
            if ($this->http->XPath->query("//*[{$this->contains($words)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }
}
