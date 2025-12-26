<?php

namespace AwardWallet\Engine\jetstar\Email;

use AwardWallet\Schema\Parser\Email\Email;

class FlightUnlocksVoucher extends \TAccountChecker
{
    public $mailFiles = "jetstar/it-50130741.eml";

    private static $detectors = [
        'en' => ["To redeem this voucher, enter the code above in the 'Apply Voucher' section of the Review and Pay page when making your hotel booking."],
    ];

    private static $dictionary = [
        'en' => [
            "Booking reference"   => ["Booking reference"],
            "Your flight details" => ["Your flight details"],
        ],
    ];

    private $from = "@emails.hotels.jetstar.com";

    private $body = "jetstar.com";

    private $subject = ["Your flight unlocks"];

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
        return stripos($from, $this->from) !== false;
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
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }

        $email->setType('FlightUnlocksVoucher');
        $this->parseEmail($email);

        return $email;
    }

    private function parseEmail(Email $email)
    {
        if (!$this->detectBody()) {
            return false;
        }

        $r = $email->add()->flight();

        $confNo = $this->http->FindSingleNode("//*[" . $this->starts($this->t('Booking reference:')) . "]/following-sibling::span[1]");

        if (!empty($confNo)) {
            $r->general()->confirmation($confNo, $this->t('Booking reference:'));
        }

        $pax = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t("you're going to")) . "]/preceding-sibling::span[1])[1]", null, true, '/^([A-z]+),$/');

        if (!empty($pax)) {
            $r->general()->traveller($pax, false);
        }

        $segments = $this->http->XPath->query("//*[./img[contains(@src, '/icon-flight-to-')]]/ancestor::table[1]");

        foreach ($segments as $segment) {
            $s = $r->addSegment();

            $s->airline()
                ->noNumber()
                ->noName();

            $depName = $this->http->FindSingleNode("./descendant::td[1]/b[1]", $segment);

            if (!empty($depName)) {
                $s->departure()
                    ->noCode()
                    ->noDate()
                    ->name($depName);
            }
            //------------------------------------------/
            $arrName = $this->http->FindSingleNode("./descendant::td[1]/b[2]", $segment);

            if (!empty($arrName)) {
                $s->arrival()
                    ->noCode()
                    ->name($arrName);
            }
            $arrDate = $this->http->FindSingleNode("./descendant::td[1]/following::td[2]", $segment);

            if (!empty($arrDate)) {
                if (preg_match('/Arrives\s[A-z]+\s(\d{1,2}\s[A-z]{3}\s\d{4})\sat\s(\d{1,2}:\d{1,2}[A-z]{2})/', $arrDate, $m)) {
                    $s->arrival()->date(strtotime($m[1] . " " . $m[2]));
                }
            }
        }

        return $email;
    }

    private function detectBody()
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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $words) {
            if (isset($words["Booking reference"], $words["Your flight details"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Booking reference'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Your flight details'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
