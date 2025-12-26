<?php

namespace AwardWallet\Engine\hiltongvc\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "hiltongvc/it-1605080.eml";

    public static $dictionary = [
        "en" => [],
    ];

    private $detectFrom = "@hgv.com";

    private $detectSubject = [
        "en"  => "Your VIP Reservation with Tour Confirmation Letter! - PLEASE DO NOT REPLY TO THIS EMAIL",
        "en2" => "Your VIP Second Reservation Confirmation Letter! - PLEASE DO NOT REPLY TO THIS EMAIL",
    ];
    //	private $detectCompany = "Hilton Grand Vacations";
    private $detectBody = [
        "en" => "Thank you for your recent Hilton Grand Vacation",
    ];

    private $lang = "en";

    public function parseEmail(Email $email)
    {
        // HOTEL
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq(["Reservation Number:", "Reservation number:"]) . "]/following::text()[normalize-space()][1]", null, true, "#^\s*([A-Z\d\-]{5,})\s*$#"))
        ;
        $traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear ')][1]", null, true, "#Dear (.+),\s*$#");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[normalize-space() = 'Dear']/following::text()[normalize-space()][1]", null, true, "#^\s*(.+),\s*$#");
        }
        $h->general()
            ->traveller($traveller);

        // Hotel
        $name = $this->http->FindSingleNode("//text()[normalize-space() = 'Accommodation Location:']/following::text()[normalize-space()][1]", null, true, "#(.* by HGV.*)#");

        if (empty($name)) {
            $name = 'Hilton Grand Vacation';
        }
        $h->hotel()
            ->name($name)
        ;
        $address = $this->http->FindSingleNode("(//text()[normalize-space() = 'Accommodation Location:']/following::text()[" . $this->starts("Address:") . "])[1]", null, true, "#Address:\s*(\S.+)#");

        if (empty($address)) {
            $address = $this->http->FindSingleNode("(//text()[normalize-space() = 'Accommodation Location:']/following::text()[normalize-space() = 'Address:'])[1]/following::text()[normalize-space()][1]");
        }
        $h->hotel()
            ->address($address);

        // Booked
        $h->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("//text()[" . $this->eq(["Your Scheduled Arrival Date:", "Your scheduled arrival date:"]) . "]/following::text()[normalize-space()][1]")))
            ->checkOut(strtotime($this->http->FindSingleNode("//text()[" . $this->eq(["Departure Date:", "Departure date:"]) . "]/following::text()[normalize-space()][1]")))
            ->guests($this->http->FindSingleNode("//text()[" . $this->starts(['Your Accommodations:', 'Your accommodations include:']) . "]/following::text()[position()<5][" . $this->contains(['People', 'people']) . "][1]", null, true, "#\b(\d+)\s*people#i"))
        ;

        if (!empty($h->getCheckInDate())) {
            $inTime = $this->http->FindSingleNode("//text()[" . $this->contains("Check-in time is") . "][1]", null, true, "#Check-in time is\s+(\d+:\d{2}\s*[ap][\.]?m)#");

            if (empty($inTime)) {
                $inTime = $this->http->FindSingleNode("//text()[" . $this->contains("Check-in time is") . "]/following::text()[normalize-space()][1]", null, true, "#^\s*(\d+:\d{2}\s*[ap][\.]?m)#");
            }

            if (!empty($inTime)) {
                $h->booked()
                ->checkIn(strtotime(str_replace('.', '', $inTime), $h->getCheckInDate()));
            }
        }

        if (!empty($h->getCheckOutDate())) {
            $outTime = $this->http->FindSingleNode("//text()[" . $this->contains("check-out time is") . "][1]", null, true, "#Check-in time is\s+(\d+:\d{2}\s*[ap]\.m)\.#");

            if (empty($outTime)) {
                $outTime = $this->http->FindSingleNode("//text()[" . $this->contains("check-out time is") . "]/following::text()[normalize-space()][1]", null, true, "#^\s*(\d+:\d{2}\s*[ap]\.m)\.#");
            }

            if (!empty($outTime)) {
                $h->booked()
                ->checkOut(strtotime(str_replace('.', '', $outTime), $h->getCheckOutDate()));
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        //		$body = html_entity_decode($this->http->Response["body"]);
        //		foreach($this->detectBody as $lang => $dBody){
        //			if (stripos($body, $dBody) !== false) {
        //				$this->lang = $lang;
        //				break;
        //			}
        //		}

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

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

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];
//        if ($this->http->XPath->query("//*[contains(.,'{$this->detectCompany}')]")->length === 0) {
        //			return false;
//        }

        foreach ($this->detectBody as $detectBody) {
            if (strpos($body, $detectBody) !== false) {
                return true;
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        //		 $this->http->log($str);
        $in = [
            //			"#^\s*(\d{1,2})/(\d{1,2})/(\d{4})\s*(?:at\s*)?(\d+:\d+\s*(?:[ap]m)?)\s*$#iu",//02/01/19  03:00 PM
        ];
        $out = [
            //			"$2.$1.$3 $4",
        ];
        $str = preg_replace($in, $out, $str);
        //		if(preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
        //			if($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
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

    private function nextTd($field, $root = null, $regexp = null)
    {
        return $this->http->FindSingleNode("(.//text()[{$this->eq($field)}])[1]/ancestor::td[1]/following-sibling::td[1]", $root, true, $regexp);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
