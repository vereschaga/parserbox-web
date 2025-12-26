<?php

namespace AwardWallet\Engine\ichotelsgroup\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Vacations extends \TAccountChecker
{
    public $mailFiles = "ichotelsgroup/it-32494079.eml, ichotelsgroup/it-43969551.eml";

    public static $dictionary = [
        'en' => [
            'Conf #:'    => ['Conf #:', 'Confirmation Number:', 'Getaway #:'],
            'Check-in:'  => ['Check-in:', 'Check-In:'],
            'Check-out:' => ['Check-out:', 'Check-Out:'],
            'Room View:' => ['Room Type:', 'Room View:', 'Unit Type:'],
            "Hotel:"     => ["Hotel:", "Your Stay:"],

            //EVENT
            "Resort:" => ["Resort:", "Your Tour:"],
        ],
    ];

    private $detectFrom = "@holidayinnclub.com";

    private $detectSubject = [
        "en"  => "Please reply YES to confirm your reservation or call us at",
        "en2" => "is Confirmed", //Your Orlando, Fl Getaway is Confirmed
        "en3" => "Your Reservation Confirmation at Holiday Inn Club Vacations",
    ];
    private $detectCompany = ["IHG®", "IHG ®", "InterContinental Hotels Group"];
    private $detectBody = [
        "en"  => "Tour Address:",
        "en2" => "Member Name:",
        "en3" => "Getaway Details",
    ];

    private $lang = "en";

    public function parseEmail(Email $email)
    {
        ///////////
        // HOTEL //
        ///////////

        $h = $email->add()->hotel();

        // General
        $guestName = $this->http->FindSingleNode("//text()[contains(.,'Rewards Club #:')]/ancestor::td[1]/descendant::text()[normalize-space()][1]", null, true,
            "#^\s*([A-z\-]*( [A-z\-]*){0,4})\s*$#");

        if (empty($guestName)) {
            $guestName = $this->nextTd($this->t('Member Name:'), null, "#^\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*$#u");
        }

        if (empty($guestName)) {
            $guestName = $this->nextTd($this->t('Guest Name:'), null, "#^\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*$#u");
        }

        if ($conf = $this->nextTd($this->t('Conf #:'), null, "#^\s*([A-Z\d]{5,})\s*$#")) {
            $h->general()->confirmation($conf);
        } else {
            $h->general()->noConfirmation();
        }
        $h->general()
            ->traveller($guestName);

        // Program
        $accountNumber = $this->http->FindSingleNode("//text()[contains(.,'Rewards Club #:')][1]", null, true, "#:\s*(\d{5,})\s*$#");

        if ($accountNumber !== null) {
            $h->program()->account($accountNumber, false);
        }

        // Hotel
        $xpathLocation = "//text()[{$this->eq($this->t('Check-out:'))}]/ancestor::tr[1]/following::text()[normalize-space()][1]/ancestor::td[1][ preceding-sibling::td[1][ descendant::img[contains(@src,'/directions.') or contains(@alt,'Compass')] ] ]"; // it-43969551.eml
        $hotelName = $this->http->FindSingleNode($xpathLocation . "/descendant::text()[normalize-space()][1]");

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("(.//text()[{$this->eq($this->t("Hotel:"))}])[1]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space()][1]");
        }
        $address = implode(', ', $this->http->FindNodes($xpathLocation . "/descendant::text()[normalize-space()][position()>1]"));

        if (empty($address)) {
            $address = implode(', ', $this->http->FindNodes("(.//text()[{$this->eq($this->t("Hotel:"))}])[1]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space()][position()>1]"));
        }
        $h->hotel()
            ->name($hotelName)
            ->address($address);

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->nextTd($this->t('Check-in:'))))
            ->checkOut($this->normalizeDate($this->nextTd($this->t('Check-out:'))))
            ->rooms($this->nextTd("# of Rooms:", null, "#^\s*(\d+)\b#"), false, true)
        ;

        $h->addRoom()->setType($this->nextTd($this->t('Room View:')));

        ///////////
        // EVENT //
        ///////////

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Tour ID:'))}]")->length === 0) {
            return;
        }

        $ev = $email->add()->event();

        // General
        $traveller = $this->http->FindSingleNode("//text()[contains(.,'Rewards Club #:')]/ancestor::td[1]/descendant::text()[normalize-space()][1]", null, true,
            "#^\s*([A-z\-]*( [A-z\-]*){0,4})\s*$#");

        if (empty($traveller) && !empty($guestName)) {
            $traveller = $guestName;
        }

        $ev->general()
            ->confirmation($this->nextTd("Tour ID:", null, "#^\s*([A-Z\d]{5,})\s*$#"))
            ->traveller($traveller);

        // Program
        $account = $this->http->FindSingleNode("//text()[contains(.,'Rewards Club #:')][1]", null, true,
            "#:\s*(\d{5,})\s*$#");

        if (!empty($account)) {
            $ev->program()->account($account, false);
        }

        // Place
        $ev->place()
            ->name($this->nextTd($this->t("Resort:")))
            ->address($this->nextTd("Tour Address:"))
            ->type(Event::TYPE_EVENT);

        $phone = $this->nextTd("Tour Desk:");

        if (!empty($phone)) {
            $ev->place()
                ->phone($phone);
        }

        // Booked
        $ev->booked()
            ->start($this->normalizeDate($this->nextTd("Tour Date:") . ' ' . $this->nextTd("Tour Time:")))
        ;

        if (!empty($ev->getStartDate())) {
            $ev->booked()->end(strtotime('+' . $this->nextTd("Duration:"), $ev->getStartDate()));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        //		$body = html_entity_decode($this->http->Response["body"]);
        //		foreach($this->detectBody as $lang => $dBody){
        //			if (stripos($body, $dBody) !== false) {
        //				$this->lang = substr($lang, 0, 2);
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

        if ($this->http->XPath->query("//*[{$this->contains($this->detectCompany)}]")->length === 0) {
            return false;
        }

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
        $in = [
            "#^\s*(\d{1,2})/(\d{1,2})/(\d{4})\s*(?:at\s*)?(\d+:\d+\s*(?:[ap]m)?)\s*$#iu", //02/01/19  03:00 PM
            "#^\s*(\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?)[,\s]+(\d{1,2})\/(\d{1,2})\/(\d{4})\s*$#i", //4 p.m., 8/26/2019
        ];
        $out = [
            "$2.$1.$3 $4",
            "$3.$2.$4 $1",
        ];
        $str = preg_replace($in, $out, $str);
        //		if(preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
        //			if($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
        return strtotime($str);
    }

    private function nextTd($field, $root = null, $regexp = null)
    {
        return $this->http->FindSingleNode("(.//text()[{$this->eq($field)}])[1]/ancestor::td[1]/following-sibling::td[1]", $root, true, $regexp);
    }

    private function eq($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }
}
