<?php

namespace AwardWallet\Engine\sandals\Email;

// TODO: delete what not use
use AwardWallet\Schema\Parser\Email\Email;

class EConfirmation extends \TAccountChecker
{
    public $mailFiles = "sandals/it-700350289.eml, sandals/it-705022148.eml";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'BOOKING NUMBER #' => 'BOOKING NUMBER #',
            'Adults:'          => 'Adults:',
        ],
    ];

    private $detectFrom = ["reservations@e.sandalsmailings.com", "info@uniquetravel.messages5.com"];
    private $detectSubject = [
        // en
        'E-Confirmation of your Luxury Included Vacation',
    ];
    private $detectBody = [
        //        'en' => [
        //            '',
        //        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.](?:uniquetravel|sandalsmailings)\.$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->containsText($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], 'Luxury Included Vacation') === false
        ) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        // case 3: subject may contain provider name(if not than check from)
        $detectedFromProvider = false;

        if ($this->detectEmailFromProvider($headers['from']) === true) {
            $detectedFromProvider = true;
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query("//a/@href[{$this->contains(['links.uniquetravel.'])}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Unique Travel Corp.'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["BOOKING NUMBER #"]) && !empty($dict["Adults:"])) {
                if ($this->http->XPath->query("//text()[{$this->starts($dict['BOOKING NUMBER #'])}]/ancestor::tr[{$this->starts($dict['BOOKING NUMBER #'])}][1]/following::text()[normalize-space()][1][{$this->starts($dict['Adults:'])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
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

    private function parseEmailHtml(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('BOOKING NUMBER #'))}]/ancestor::tr[{$this->starts($this->t('BOOKING NUMBER #'))}][1]",
                null, true, "/^\s*{$this->opt($this->t('BOOKING NUMBER #'))}\s*:\s*(\d{5,})\s*$/"));
        $travellers = $this->http->FindSingleNode("//tr[not(.//tr)][count(*[normalize-space()]) = 2][*[normalize-space()][1][{$this->eq($this->t('Guests:'))}]]/*[normalize-space()][2]");
        $travellers = preg_split('/\s*;\s*/', $travellers);
        $travellers = preg_replace("/^\s*[[:alpha:]]{1,5}\. /", '', $travellers);
        $h->general()
            ->travellers($travellers);

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//tr[{$this->starts($this->t('Phone Number:'))}]/preceding-sibling::tr[normalize-space()][2]"
                . "[count(.//text()[normalize-space()]) = 1]/descendant-or-self::*[@style[{$this->contains('bold')}] or self::b]"))
            ->address(preg_replace('/\s*,[\s,]*/', ', ',
                $this->http->FindSingleNode("//tr[{$this->starts($this->t('Phone Number:'))}]/preceding-sibling::tr[normalize-space()][1]")))
            ->phone($this->http->FindSingleNode("//tr[{$this->starts($this->t('Phone Number:'))}]",
                null, true, "/{$this->opt($this->t('Phone Number:'))}\s*([\d\- ]{5,})\s*(?:\||$)/"))
            ->fax($this->http->FindSingleNode("//tr[{$this->starts($this->t('Phone Number:'))}]",
                null, true, "/{$this->opt($this->t(' Fax Number:'))}\s*([\d\- ]{5,})\s*(?:\||$)/"), true, true)
        ;

        // Booked
        $h->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("//tr[not(.//tr)][count(*[normalize-space()]) = 2][*[normalize-space()][1][{$this->eq($this->t('Arrival Date:'))}]]/*[normalize-space()][2]")))
            ->checkOut(strtotime($this->http->FindSingleNode("//tr[not(.//tr)][count(*[normalize-space()]) = 2][*[normalize-space()][1][{$this->eq($this->t('Departure Date:'))}]]/*[normalize-space()][2]")))
            ->guests($this->http->FindSingleNode("//tr[not(.//tr)][count(*[normalize-space()]) = 2][*[normalize-space()][1][{$this->eq($this->t('Adults:'))}]]/*[normalize-space()][2]",
                null, true, "/^\s*(\d+)\s*$/"))
            ->kids($this->http->FindSingleNode("//tr[not(.//tr)][count(*[normalize-space()]) = 2][*[normalize-space()][1][{$this->eq($this->t('Children:'))}]]/*[normalize-space()][2]",
                null, true, "/^\s*(\d+)\s*$/"), true, true)
        ;
        // Rooms
        $h->addRoom()
            ->setType($this->http->FindSingleNode("//tr[not(.//tr)][count(*[normalize-space()]) = 2][*[normalize-space()][1][{$this->eq($this->t('Room Category:'))}]]/*[normalize-space()][2]"));

        return true;
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

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
