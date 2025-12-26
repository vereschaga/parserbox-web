<?php

namespace AwardWallet\Engine\indigo\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightCancelled extends \TAccountChecker
{
    public $mailFiles = "indigo/it-171361943.eml";
    public $subjects = [
        'Your IndiGo Itinerary - ',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@customer.goindigo.in') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'IndiGo')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Your booking has been cancelled'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('This PNR is no longer valid'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]customer\.goindigo\.in$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $travellers = $this->http->FindSingleNode("//text()[normalize-space()='Adult']/following::text()[normalize-space()][1]");

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Your booking has been cancelled.']/following::text()[normalize-space()][1]"))
            ->traveller(str_replace(['Mrs.', 'Mr.', 'Ms.'], '', $travellers))
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space()='Booked on:']/following::text()[normalize-space()][1]")));

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Your booking has been cancelled'))}]")->length > 0) {
            $f->general()
                ->cancelled()
                ->status('cancelled');
        }

        $total = $this->http->FindSingleNode("//text()[normalize-space()='Refund Amount']/following::text()[normalize-space()][1]", null, true, "/^([\d\,\.]+)$/");
        $f->price()
            ->total(str_replace([',', ''], '', $total));
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseFlight($email);

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

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+)(\D+)(\d{2})$#u", //27Jun22
        ];
        $out = [
            "$1 $2 20$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
