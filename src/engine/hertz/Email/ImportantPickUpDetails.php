<?php

namespace AwardWallet\Engine\hertz\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ImportantPickUpDetails extends \TAccountChecker
{
    public $mailFiles = "hertz/it-16567305.eml";

    public $reFrom = "emails.hertz.com";
    public $reBody = [
        'en' => ['Pick-up Location', 'recent booking and other information you need to get started'],
    ];
    public $reSubject = [
        'Important pick-up details for',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        //cause this format is sent to junk
        $flag = false;

        foreach ($this->reSubject as $reSubject) {
            if (stripos($parser->getSubject(), $reSubject) !== false) {
                $flag = true;

                break;
            }
        }

        if (!$flag) {
            $this->logger->debug('could be other format with info about drop-off');

            return $email;
        }

        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        if (!$this->parseEmail($email)) {
            return null;
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'hertz.com')] | //a[contains(@href,'hertz.com')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->rental();
        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Reference:'))}]/ancestor::td[1]/descendant::text()[normalize-space(.)!=''][2]"),
                trim($this->t('Booking Reference:'), ": "))
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi '))}]", null, false,
                "#{$this->opt($this->t('Hi '))}\s*(.+),#"));

        $r->pickup()
            ->phone(implode(' ',
                $this->http->FindNodes("//text()[{$this->eq($this->t('Phone:'))}][1]/ancestor::td[1]/descendant::text()[normalize-space()][2]", null, '/^([+(\d][-. \d)(]{5,}[\d)])[ *]*$/')))
            ->openingHours(implode(' ',
                $this->http->FindNodes("//text()[{$this->contains($this->t('Opening Hours'))}][1]/ancestor::td[1]/descendant::text()[normalize-space(.)!=''][position()>1]")))
            ->location(implode(' ',
                $this->http->FindNodes("//text()[{$this->contains($this->t('Pick-up Location'))}]/ancestor::td[1]/descendant::text()[normalize-space(.)!=''][position()>1]")))
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->contains($this->t('Pick-up Time'))}]/ancestor::td[1]/descendant::text()[normalize-space(.)!=''][2]")));

        $r->dropoff()
            ->noDate()
            ->noLocation();

        return true;
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug($date);
        $in = [
            //Mon, Jul 16, 2018 at 10:30 AM
            '#^\w+, (\w+) (\d+), (\d{4}) at (\d+:\d+\s*(?:[ap]m)?)$#i',
            //Tue, 07 May, 2019 at 12:00
            '#^[[:alpha:]]{2,}[, ]+(\d{1,2})\s+([[:alpha:]]{3,})[, ]+(\d{4})\s+at\s+(\d+:\d+\s*(?:[ap]m)?)$#iu',
            //08/18/2019 at 12:30 PM
            '#^(\d+/\d+/\d{4}) at (\d+:\d+(?:\s*[ap]m)?)$#i',
        ];
        $out = [
            '$2 $1 $3, $4',
            '$1 $2 $3, $4',
            '$1, $2',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return strtotime($str);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
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
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
