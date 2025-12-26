<?php

namespace AwardWallet\Engine\hotelslash\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Tracking extends \TAccountChecker
{
    public $mailFiles = "hotelslash/it-736971834.eml, hotelslash/it-737969116.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Property:'  => 'Property:',
            'Check out:' => 'Check out:',
        ],
    ];

    private $detectFrom = "hotelslash.com";
    private $detectSubject = [
        // en
        'HotelSlash Tracking ',
        'Lower Rate Found on Your Trip to ',
    ];
    private $detectBody = [
        'en' => [
            'Below are the details of your current reservation.',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]hotelslash\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query("//a[{$this->contains(['.hotelslash.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['©  HotelSlash', 'Your friends at HotelSlash'])}]")->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }
        $this->parseEmailHtml($email);

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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Property:"]) && !empty($dict["Check out:"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Property:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($dict['Check out:'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        // Travel Agency
        $email->obtainTravelAgency();

        // Hotels
        $h = $email->add()->hotel();

        // General
        $confs = explode('/', $this->getValue($this->t('Confirmation #:'), '/^\s*([\w\.\-\/ ]+)\s*$/'));

        foreach ($confs as $conf) {
            $h->general()
                ->confirmation(trim($conf));
        }
        $h->general()
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello,'))}]", null, true,
                "/{$this->opt($this->t('Hello,'))}\s*(.+?)\s*!\s*$/"), false)
            ->cancellation($this->getValue($this->t('Cancellation policy:')))
        ;

        // Hotel
        $h->hotel()
            ->name($this->getValue($this->t('Property:')))
            ->noAddress()
        ;

        // Booked
        $h->booked()
            ->checkIn(strtotime($this->getValue($this->t('Check in:'))))
            ->checkOut(strtotime($this->getValue($this->t('Check out:'), "/^\s*(.+?)\s*\(/")))
            ->guests($this->getValue($this->t('Guests:'), "/^\s*(\d+)\s*{$this->opt($this->t('adult'))}/"))
            ->kids($this->getValue($this->t('Guests:'), "/,\s*(\d+)\s*{$this->opt($this->t('child'))}/"), true, true)
        ;

        // Rooms
        $h->addRoom()
            ->setType($this->getValue($this->t('Room:')))
        ;

        // Price
        $total = $this->getValue($this->t('Booked at:'), "/^\s*(.+?) {$this->opt($this->t('for'))}/");

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//p[descendant::text()[normalize-space()][1][{$this->starts($this->t('Booked through'))}]]", null, true,
                "/^\s*{$this->opt($this->t('Booked through'))}[^:]*:\s*(.+?) {$this->opt($this->t('for'))}/");
        }

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
        ) {
            $h->price()
                ->total(PriceHelper::parse($m['amount']))
                ->currency($m['currency']);
        }

        return true;
    }

    private function getValue($title, $regexp = null)
    {
        $result = $this->http->FindSingleNode("//p[descendant::text()[normalize-space()][1][{$this->eq($title)}]]", null, true,
            "/^\s*{$this->opt($title)}\s*(.+)/");

        if (!empty($regexp)) {
            preg_match($regexp, $result, $m);
            $result = $m[1] ?? null;
        }

        return $result;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
