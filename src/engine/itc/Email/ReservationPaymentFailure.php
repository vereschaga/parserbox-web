<?php

namespace AwardWallet\Engine\itc\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ReservationPaymentFailure extends \TAccountChecker
{
    public $mailFiles = "itc/it-671532682.eml";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
        ],
    ];

    private $detectFrom = "reservations@itc-hotels.com";
    private $detectSubject = [
        'Reservation payment failure',
    ];
    private $detectBody = [
        'en' => [
            'Your reservation has been cancelled.',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]itc-hotels\.com$/", $from) > 0;
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
        // detect Provider
        if (
            $this->http->XPath->query("//a[{$this->contains(['.itchotels.com/'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['@itchotels.com', 'by ITC Hotels'])}]")->length === 0
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

        $text = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Payment for your reservation'))}]");

        if (preg_match("/^\s*{$this->opt($this->t('Payment for your reservation'))}\s+(?<conf>[A-Z\d]{5,})\s+{$this->opt($this->t('at'))}\s+(?<name>.+?){$this->opt($this->t('was not completed '))}/", $text, $m)) {
            $h->general()
                ->confirmation($m['conf'])
                ->cancelled()
                ->status('Cancelled')
            ;
            $h->hotel()
                ->name($m['name'])
                ->noAddress()
            ;
        }

        // // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->contains($this->t('Arrival Date:'))}]",
                null, true, "/{$this->opt($this->t('Arrival Date:'))}\s*(.+?\d{4})\s*/")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->contains($this->t('Departure Date:'))}]/ancestor::*[1]",
                null, true, "/{$this->opt($this->t('Departure Date:'))}\s*(.+?\d{4})\s*/")))
            ->guests($this->http->FindSingleNode("//text()[{$this->contains($this->t('Number of guests:'))}]",
                null, true, "/{$this->opt($this->t('Number of guests:'))}\s*(\d+)\b/"));

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

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));

        $in = [
            //            // Apr 09
            //            '/^\s*(\w+)\s+(\d+)\s*$/iu',
            //            // Sun, Apr 09
            //            '/^\s*(\w+),\s*(\w+)\s+(\d+)\s*$/iu',
            //            // Tue Jul 03, 2018 at 1:43 PM
            //            '/^\s*[\w\-]+\s+(\w+)\s+(\d+),\s*(\d{4})\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            //            '$2 $1 %year%',
            //            '$1, $3 $2 ' . $year,
            //            '$2 $1 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);

//        $this->logger->debug('date replace = ' . print_r( $date, true));

        // $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
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
