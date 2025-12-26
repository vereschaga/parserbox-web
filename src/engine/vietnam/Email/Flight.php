<?php

namespace AwardWallet\Engine\vietnam\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
    public $mailFiles = "vietnam/it-136519992.eml, vietnam/it-136520127.eml";
    public $subjects = [
        'The len may bay',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'traveller'     => ['Tên (các) hành khách', 'Hành khách'],
            'confirmation'  => ['Số xác nhận', 'Mã đặt chỗ'],
            'departure'     => ['Khởi hành', 'KHỞI HÀNH'],
            'boarding pass' => 'Thẻ lên máy bay cho:',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@vietnamairlines.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'www.vietnamairlines.com')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('departure'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('confirmation'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]vietnamairlines\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('traveller'))}]/following::text()[normalize-space()][1]"))
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('confirmation'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('confirmation'))}\s*([A-Z\d]{6,})/"));

        $account = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Vietnam Airlines Titanium')]", null, true, "/[#]\s*(\d{6,})/");

        if (!empty($account)) {
            $f->program()
                ->account($account, false);
        }

        $xpath = "//text()[{$this->eq($this->t('departure'))}]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->airline()
               ->name($this->http->FindSingleNode("./descendant::tr[1]", $root, true, "/(?:Chuyến bay|\:)\s*([A-Z\d]{2})/"))
               ->number($this->http->FindSingleNode("./descendant::tr[1]", $root, true, "/(?:Chuyến bay|\:)\s*[A-Z\d]{2}\s*(\d{2,4})/"));

            $depCode = $this->http->FindSingleNode("./descendant::tr[1]", $root, true, "/\(([A-Z]{3})\)\s*\-/");

            if (empty($depCode)) {
                $depCode = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Chi tiết hành trình cho')]", null, true, "/\:\s*([A-Z]{3})\-/");
            }

            $depDate = $this->http->FindSingleNode("./descendant::tr[2]/td[1]/descendant::text()[contains(normalize-space(), ':')][1]", $root);

            if (preg_match("/^\s*[\d\:]+\s*$/", $depDate)) {
                $depDate = $this->http->FindSingleNode("./descendant::tr[2]/td[1]/descendant::text()[contains(normalize-space(), ':')][1]/ancestor::div[1]", $root);
            }
            $s->departure()
               ->code($depCode)
               ->date($this->normalizeDate($depDate));

            $arrCode = $this->http->FindSingleNode("./descendant::tr[1]", $root, true, "/\s*\-\s*.+\(([A-Z]{3})\)/");

            if (empty($arrCode)) {
                $arrCode = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Chi tiết hành trình cho')]", null, true, "/\:\s*[A-Z]{3}\-([A-Z]{3})/");
            }
            $arrDate = $this->http->FindSingleNode("./descendant::tr[2]/td[1]/descendant::text()[contains(normalize-space(), ':')][2]", $root);

            if (preg_match("/^\s*[\d\:]+\s*$/", $arrDate)) {
                $arrDate = $this->http->FindSingleNode("./descendant::tr[2]/td[1]/descendant::text()[contains(normalize-space(), ':')][2]/ancestor::div[1]", $root);
            }
            $s->arrival()
               ->code($arrCode)
               ->date($this->normalizeDate($arrDate));

            $s->extra()
               ->seat($this->http->FindSingleNode("./descendant::tr[2]/td[3]", $root, true, "/\s(\d{1,3}[A-Z])$/"));

            if ($this->http->XPath->query("//text()[{$this->starts($this->t('boarding pass'))}]")->length > 0) {
                $bp = $email->add()->bpass();

                $bp->setDepCode($s->getDepCode());
                $bp->setDepDate($s->getDepDate());
                $bp->setFlightNumber($s->getFlightNumber());
                $bp->setUrl($this->http->FindSingleNode("//text()[{$this->starts($this->t('boarding pass'))}]/following::img[1]/@src"));
                $bp->setTraveller($f->getTravellers()[0][0]);
            }
        }
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

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
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

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        //$this->logger->debug('$date = '.print_r( $date,true));

        $in = [
            // 25, thg 11, 2021-18:00
            "/^(\d+)\,\s*\w+\s*(\d+)\,\s*(\d{4})\-([\d\:]+)$/iu",
            // 23 Nov, 2021 11:00
            "/^(\d+)\s*(\w+)\,\s*(\d{4})\s*([\d\:]+)$/iu",
        ];
        $out = [
            "$1.$2.$3, $4",
            "$1 $2 $3, $4",
        ];
        $date = preg_replace($in, $out, $date);
        //$this->logger->debug('$date = '.print_r( $date,true));

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }
}
