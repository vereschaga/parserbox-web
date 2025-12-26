<?php

namespace AwardWallet\Engine\peninsula\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ReservationCancellation extends \TAccountChecker
{
    public $mailFiles = "peninsula/it-230609345.eml";

    private $detectFrom = '@peninsula.com';
    private $detectSubject = [
        // en
        ' - Reservation Cancellation'
    ];
    private $detectBody = [
        'en' => [
            'Cancellation of your reservation at the Peninsula',
        ],
    ];

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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
        // detect Provider
        if (
            $this->http->XPath->query("//a[{$this->contains(['.peninsula.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Cancellation of your reservation at the Peninsula'])}]")->length === 0
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
            ->confirmation($this->nextSibling($this->t("Original Confirmation #:")))
            ->cancellationNumber($this->nextSibling($this->t("Cancellation #:")))
            ->traveller(preg_replace("/^\s*(Mr|Ms|Mrs|Miss|Mstr|Dr)[\.\s]+/", '',
                $this->nextSibling($this->t("Guest Name:"))))
        ;
        if ($this->http->XPath->query("//text()[{$this->contains(['Cancellation #:', 'Cancellation of your reservation at'])}]")->length > 0) {
            $h->general()
                ->cancelled()
                ->status('Cancelled');
        }

        // Hotel
        $hotelInfo = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t("Yours sincerely,"))}]/following::text()[normalize-space()][1][contains(., 'Peninsula')]/ancestor::td[1][not({$this->contains($this->t("Hotel Information"))})]//text()[normalize-space()]"));
        if (preg_match("/Yours sincerely,\s*(?<name>.*\bPeninsula\b.+)\n(?<address>(?:.+\n)+?)\s*Tel:\s*(?<tel>[\d\.\ \-\+\(\)]{5,})\n\s*Fax:\s*(?<fax>[\d\.\ \-\+\(\)]{5,})(?:\n|$)/", $hotelInfo, $m)) {
            /*  The Peninsula New York
                700 Fifth Ave at 55th St
                New York, 10019, United States
                Tel: 1-212-9562888
                Fax: 1-212-9033949
            */
            $h->hotel()
                ->name($m['name'])
                ->address(str_replace("\n", ', ', $m['address']))
                ->phone($m['tel'])
                ->fax($m['fax'])
            ;
        }

        // Booked
        $h->booked()
            ->checkIn(strtotime($this->nextSibling($this->t("Arrival Date:"))))
            ->checkOut(strtotime($this->nextSibling($this->t("Departure Date:"))))
        ;

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }
        return self::$dictionary[$this->lang][$s];
    }

    private function nextSibling($field)
    {
        return $this->http->FindSingleNode("(//*[{$this->eq($field)}]/following-sibling::*[normalize-space(.)!=''][1])[1]");
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }


    private function eq($field)
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }


    private function starts($field)
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }


    private function normalizeDate(?string $date): ?int
    {
//        $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date)) {
            return null;
        }

        $in = [
//            // Sun, Apr 09
//            '/^\s*(\w+),\s*(\w+)\s+(\d+)\s*$/iu',
//            // Tue Jul 03, 2018 at 1 :43 PM
//            '/^\s*[\w\-]+\s+(\w+)\s+(\d+),\s*(\d{4})\s+(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
//            '$1, $3 $2 ' . $year,
//            '$2 $1 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('date replace = ' . print_r( $date, true));

//        $this->logger->debug('date end = ' . print_r( $date, true));

        return $date;
    }


    private function opt($field, $delimiter = '/')
    {
        $field = (array)$field;
        if (empty($field)) {
            $field = ['false'];
        }
        return '(?:' . implode("|", array_map(function ($s) use($delimiter) {
                return str_replace(' ', '\s+', preg_quote($s, $delimiter));
            }, $field)) . ')';
    }
}