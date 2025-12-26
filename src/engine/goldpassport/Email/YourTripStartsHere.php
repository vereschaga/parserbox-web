<?php

namespace AwardWallet\Engine\goldpassport\Email;

use AwardWallet\Schema\Parser\Email\Email;

// parser for junk
class YourTripStartsHere extends \TAccountChecker
{
    public $mailFiles = "goldpassport/it-35580442.eml, goldpassport/it-36220517.eml";

    public $reFrom = ["e.hyatt.com"];
    public $reBody = [
        'en' => [
            ['Complete your hotel reservation', 'free when you book on hyatt.com'], 'BOOK NOW', ],
    ];
    public $reSubject = [
        '#Your Trip to .+? Starts Here#',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Depart' => 'Depart',
            'Arrive' => 'Arrive',
        ],
    ];
    private $keywordProv = 'Hyatt';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->detectBody()) {
            $this->logger->debug('other format');

            return $email;
        }

        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectBody()) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv && preg_match($reSubject, $headers["subject"]) > 0)
                    || strpos($headers["subject"], $this->keywordProv) !== false
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return 0; //count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $hotel = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Arrive'))}]/preceding::td[normalize-space()!=''][2]");
        $address = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Arrive'))}]/preceding::td[normalize-space()!=''][1]");
        $checkin = strtotime($this->http->FindSingleNode("//text()[{$this->starts($this->t('Arrive'))}]/following::text()[normalize-space()!=''][1]"));
        $checkout = strtotime($this->http->FindSingleNode("//text()[{$this->starts($this->t('Depart'))}]/following::text()[normalize-space()!=''][1]"));

        if (!empty($hotel) && !empty($address) && !empty($checkin) && !empty($checkout)) {
            $email->setIsJunk(true);

            return true;
        }

        $r = $email->add()->hotel();
        $r->general()
            ->noConfirmation()
            ->status('Latest Search');
        $r->hotel()
            ->name($hotel)
            ->address($address);

        $r->booked()
            ->checkIn($checkin)
            ->checkOut($checkout);

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (
                    $this->http->XPath->query("//*[{$this->contains($reBody[0])}]")->length > 0
                    && $this->http->XPath->query("//a[{$this->eq($reBody[1])}][contains(@href,'e.hyatt.com')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["Depart"], $words["Arrive"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Depart'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Arrive'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
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
}
