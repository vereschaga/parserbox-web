<?php

namespace AwardWallet\Engine\regalhotels\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourStay extends \TAccountChecker
{
    public $mailFiles = "regalhotels/it-703474110.eml";
    public $subjects = [
        'Your Stay at',
    ];

    public $lang = 'en';
    public $currency;

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@airport.regalhotel.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Regal ')]")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), '@airport.regalhotel.com')]")->length > 0
            && $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Check-In Date:')]/ancestor::tr[1][contains(normalize-space(), 'Check-In Time:')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('We look forward to your visit'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]airport\.regalhotel\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Confirmation Number:')]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Confirmation Number:'))}\s*([A-Z\-\d]+)/"))
            ->travellers(preg_replace("/^(?:Mrs|Ms|Mr)/", "", $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Dear')]", null, "/^{$this->opt($this->t('Dear'))}\s*(.+)\,$/")));

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Thank you for choosing')]", null, true, "/{$this->opt($this->t('Thank you for choosing'))}\s+(.+)\s+{$this->opt($this->t('for your upcoming stay'))}/"))
            ->address($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'T:')]/ancestor::tr[1]/preceding::tr[1]"))
            ->phone($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'T:')]",
                null, true, "/{$this->opt($this->t('T:'))}\s*([+\s\d\(\)]+)/"))
            ->fax($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'T:')]",
                null, true, "/{$this->opt($this->t('F:'))}\s*([+\s\d\(\)]+)/"));

        $inTime = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-In Time:')]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Check-In Time:'))}\s*([\d\:]+\s*A?P?M)/");
        $outTime = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-Out Time:')]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Check-Out Time:'))}\s*([\d\:]+\s*A?P?M)/");

        $inDate = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-In Date:')]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Check-In Date:'))}\s*(.+\d{4})/");
        $outDate = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-Out Date:')]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Check-Out Date:'))}\s*(.+\d{4})/");

        $h->booked()
            ->checkIn(strtotime($inDate . ', ' . $inTime))
            ->checkOut(strtotime($outDate . ', ' . $outTime));
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
