<?php

namespace AwardWallet\Engine\mileageplus\Email;

// TODO: delete what not use
use AwardWallet\Schema\Parser\Email\Email;

class ComplimentaryHotel extends \TAccountChecker
{
    public $mailFiles = "";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Show this code upon check-in:' => ['Show this code upon check-in:', 'Check in code:'],
            'Number of rooms:'              => 'Number of rooms:',
        ],
    ];

    private $detectFrom = "notifications@united.com";
    private $detectSubject = [
        // en
        'Complimentary hotel and meal details',
        'You’re all set with your hotel confirmation',
    ];
    private $detectBody = [
        'en' => [
            'Show this code upon check-in:', 'Check in code:',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]united\.com$/", $from) > 0;
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
            $this->http->XPath->query("//img[{$this->contains(['.united.com'], '@src')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['United Airlines. All rights reserved'])}]")->length === 0
        ) {
            return false;
        }

        // detect Format
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
            if (isset($dict["Number of rooms:"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Number of rooms:'])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Show this code upon check-in:'))}]/following::text()[normalize-space()][1]"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Travelers'))}]/following::text()[normalize-space()][1]/ancestor::tr[1]"))
        ;

        // Hotel
        $info = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Show this code upon check-in:'))}]/following::text()[normalize-space()][3]/ancestor::tr[1]//text()[normalize-space()]"));
        $this->logger->debug('$info = ' . print_r($info, true));

        if (preg_match("/^\d[\d,. ]* *miles?\n(?<name>.+)\n(?<address>[\s\S]+?)(?<phone>\n[\d\- ()]{5,})\s*$/", $info, $m)) {
            $h->hotel()
                ->name($m['name'])
                ->address($m['address'])
                ->phone($m['phone'])
            ;
        }

        // Booked
        $h->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check in:'))}]/following::text()[normalize-space()][1]")))
            ->checkOut(strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check out:'))}]/following::text()[normalize-space()][1]")))
            ->rooms($this->http->FindSingleNode("//text()[{$this->eq($this->t('Number of rooms:'))}]/following::text()[normalize-space()][1]"))
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
        $this->logger->debug('date begin = ' . print_r($date, true));

        if (empty($date)) {
            return null;
        }

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

        $this->logger->debug('date end = ' . print_r($date, true));

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
