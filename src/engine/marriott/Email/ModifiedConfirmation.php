<?php

namespace AwardWallet\Engine\marriott\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ModifiedConfirmation extends \TAccountChecker
{
    public $mailFiles = "marriott/it-36428090.eml, marriott/it-47162547.eml";
    public $reFrom = "groupcampaigns@pkghlrss.com";
    public $reSubject = [
        //en
        "Grande Lakes Orlando Modified Confirmation",
        "Grande Lakes Orlando Reservation Confirmation",
    ];
    public $reBody = 'Marriott';
    public $reBody2 = [
        "en" => "Room Reservation Details",
    ];

    public static $dictionary = [
        "en" => [
            "Hotel Confirmation Number:" => ["Hotel Confirmation Number:", "Hotel Online Confirmation Number:"],
            "Changes and Cancelations"   => ["Changes and Cancelations", "Changes and Cancellations"],
        ],
    ];

    public $lang = "en";
    private $providerCode;

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers["from"]) || strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = true;

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($email);

        if ($this->assignProvider($parser->getHeaders())) {
            $email->setProviderCode($this->providerCode);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    public static function getEmailProviders()
    {
        return ['marriott', 'gcampaigns'];
    }

    private function parseHtml(Email $email)
    {
        $r = $email->add()->hotel();

        $confNo = $this->nextText("Passkey Acknowledgement Number:");

        if (!empty($confNo)) {
            $r->ota()->confirmation($confNo);
        }
        $r->general()
            ->confirmation($this->nextText($this->t("Hotel Confirmation Number:")))
            ->traveller($this->nextText("Name of the Guest:"))
            ->date(strtotime($this->normalizeDate($this->nextText("Date Booked:"))))
            ->cancellation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Changes and Cancelations'))}]/following::strong[normalize-space(.)!=''][1]"));

        $r->hotel()
            ->name($this->nextText("Hotel Name:"))
            ->address($this->http->FindSingleNode("(//a[contains(@href, 'manage.passkey.com/Tracking/track.do')])[1]/ancestor::strong[2]"))
            ->phone($this->nextText("or call"));

        $r->booked()
            ->checkIn(strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq("Arrival date:") . "]/ancestor::td[1]/following-sibling::td[1]"))))
            ->checkOut(strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq("Departure date:") . "]/ancestor::td[1]/following-sibling::td[1]"))));

        $room = $r->addRoom();
        $room
            ->setRate($this->nextText("Nightly Rate:"))
            ->setType($this->nextText("Room Type:"));

        $r->price()
            ->total($this->amount($this->nextText("Total Charges:")))
            ->currency($this->currency($this->nextText("Total Charges:")));

        $this->detectDeadLine($r);
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/^If cancelled within (\d+) days of your arrival, you will be charged/i",
            $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m[1] . ' days', '00:00');

            return;
        }
    }

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^([^\s\d]+)\s+(\d+),\s+(\d{4})\s+\((?:Check-In|Check-out) Time:\s+(\d+:\d+[AP]M)\)$#",
            //Aug 21, 2017 (Check-In Time: 4:00PM)
            "#^([^\s\d]+)\s+(\d+),\s+(\d{4})$#",
            //Aug 21, 2017
        ];
        $out = [
            "$2 $1 $3, $4",
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            '£' => 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

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

    private function assignProvider($headers): bool
    {
        if (self::detectEmailFromProvider($headers['from']) === true) {
            $this->providerCode = 'gcampaigns';

            return true;
        }

        if ($this->http->XPath->query('//node()[contains(normalize-space(),"Marriott International Inc")]')->length > 0) {
            $this->providerCode = 'marriott';

            return true;
        }

        return false;
    }
}
