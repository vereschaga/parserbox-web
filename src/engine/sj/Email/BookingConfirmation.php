<?php

namespace AwardWallet\Engine\sj\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "sj/it-723182154.eml";
    public $subjects = [
        'Booking Confirmation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'traveller' => ['traveller', 'Traveller'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@info.sj.se') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'SJ AB')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Show booking and receipt'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Booked by:'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]info\.sj\.se$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseTrain($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseTrain(Email $email)
    {
        $t = $email->add()->train();

        $t->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booked by:')]/following::text()[starts-with(normalize-space(), 'Booking number:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Booking number:'))}\s*([A-Z\d]{6})/"))
            ->travellers(array_unique($this->http->FindNodes("//text()[{$this->contains($this->t('traveller'))}][1]/ancestor::tr[1]/following::table[1]/descendant::text()[normalize-space()]/ancestor::tr[1]/descendant::text()[normalize-space()][1]")));

        $nodes = $this->http->XPath->query("//text()[contains(normalize-space(), 'train') and contains(normalize-space(), 'class')]");

        foreach ($nodes as $root) {
            $s = $t->addSegment();

            $date = $this->http->FindSingleNode("./preceding::text()[contains(translate(normalize-space(), '0123456789', 'dddddddddd'), 'dddd')][1]", $root, true, "/^\w+\s*(\d+\s*\w+\s*\d{4})$/");

            $depInfo = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root);

            if (preg_match("/^(?<depTime>\d+\:\d+\s*a?p?m?)\s+(?<depName>.+)$/i", $depInfo, $m)) {
                $s->departure()
                    ->name($m['depName'] . ', Europe')
                    ->date(strtotime($date . ', ' . $m['depTime']));
            }

            $arrInfo = $this->http->FindSingleNode("./following::text()[normalize-space()][1]", $root);

            if (preg_match("/^(?<arrTime>\d+\:\d+\s*a?p?m?)\s+(?<arrName>.+)$/i", $arrInfo, $m)) {
                $s->arrival()
                    ->name($m['arrName'] . ', Europe')
                    ->date(strtotime($date . ', ' . $m['arrTime']));
            }

            $trainInfo = $this->http->FindSingleNode(".", $root);

            if (preg_match("/(?<serviceName>.+)\,\s+train\s*(?<trainNumber>\d{1,5})\,\s+(?<cabin>\d+\s+class)\,/", $trainInfo, $m)) {
                $s->setServiceName($m['serviceName']);
                $s->setCabin($m['cabin']);
                $s->setNumber($m['trainNumber']);
            }
        }
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
