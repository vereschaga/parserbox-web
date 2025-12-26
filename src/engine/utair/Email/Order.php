<?php

namespace AwardWallet\Engine\utair\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// TODO: can parse from pdf
class Order extends \TAccountChecker
{
    public $mailFiles = "utair/it-40877551.eml, utair/it-41122897.eml";

    public $reFrom = ["@utair.ru"];
    public $reBody = [
        'en' => ['Order has been paid, tickets are attached'],
        'ru' => ['Ваш заказ оплачен, билеты во вложении'],
    ];
    public $reSubject = [
        '#Your order [A-Z\d]+ on ticket.utair.ru$#',
        '#ticket.utair.ru: Заказ билетов [A-Z\d]+$#',
    ];
    public $lang = '';
    public $pdf;
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'ORDER #'        => 'ORDER #',
            'DEPARTURE DATE' => 'DEPARTURE DATE',
            //            'ДОПОЛНИТЕЛЬНЫЕ УСЛУГИ'=>''
        ],
        'ru' => [
            'ORDER #'        => '№ ЗАКАЗА',
            'DEPARTURE DATE' => 'ДАТА ВЫЛЕТА',
            'PLANE'          => 'ТИП ВС',
            'PASSENGER '     => 'ПАССАЖИР ',
            'FARE'           => 'ТАРИФ',
            'FEES'           => 'СБОРЫ',
            'SUM'            => 'СУММА',
        ],
    ];
    private $keywordProv = 'Utair';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
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
        if ($this->http->XPath->query("//img[@alt='Utair' or contains(@src,'.utair.ru')] | //a[contains(@href,'.utair.ru')]")->length > 0
            && $this->detectBody()
        ) {
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
                if (($fromProv || stripos($headers["subject"], $this->keywordProv) !== false)
                    && preg_match($reSubject, $headers["subject"]) > 0
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
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->flight();
        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('ORDER #'))}]/ancestor::tr[1]/following-sibling::tr[1]/td[normalize-space()!=''][1]"))
            ->travellers($this->http->FindNodes("//text()[{$this->starts($this->t('PASSENGER '))}]/ancestor::tr[1]/following-sibling::tr[1]/td[1]"));

        $fare = $this->http->FindSingleNode("//text()[{$this->eq($this->t('FARE'))}]/ancestor::tr[1]/following-sibling::tr[1]/td[1]");
        $fare = $this->getTotalCurrency(strtoupper($fare));
        $r->price()
            ->cost($fare['Total'])
            ->currency($fare['Currency']);
        $fees = $this->http->FindSingleNode("//text()[{$this->eq($this->t('FEES'))}]/ancestor::tr[1]/following-sibling::tr[1]/td[2]");
        $fees = $this->getTotalCurrency(strtoupper($fees));
        $r->price()
            ->fee($this->t('FEES'), $fees['Total']);

        if ($additional = $this->http->FindSingleNode("//text()[{$this->eq($this->t('ДОПОЛНИТЕЛЬНЫЕ УСЛУГИ'))}]/ancestor::td[1]/following-sibling::td[1]")) {
            $additional = $this->getTotalCurrency(strtoupper($additional));
            $r->price()
                ->fee($this->t('ДОПОЛНИТЕЛЬНЫЕ УСЛУГИ'), $additional['Total']);
            $meal = $this->http->FindSingleNode("//text()[{$this->eq($this->t('ДОПОЛНИТЕЛЬНЫЕ УСЛУГИ'))}]/ancestor::tr[./following-sibling::tr][1]/following-sibling::tr[1]/descendant::img[contains(@src,'i_meal')]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space()!=''][1]");
        }

        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('ORDER #'))}]/ancestor::tr[1]/following-sibling::tr[1]/td[normalize-space()!=''][2]");
        $total = $this->getTotalCurrency(strtoupper($total));
        $r->price()
            ->total($total['Total'])
            ->currency($total['Currency']);

        //		$sum = $this->http->FindSingleNode("//text()[{$this->eq($this->t('SUM'))}]/ancestor::tr[1]/following-sibling::tr[1]/td[2]");
//        $sum = $this->getTotalCurrency(strtoupper($sum));
//        $r->price()
//            ->total($sum['Total'])
//            ->currency($sum['Currency']);

        $xpath = "//text()[{$this->eq($this->t('DEPARTURE DATE'))}]/ancestor::tr[./following-sibling::tr[{$this->contains($this->t('PLANE'))}]][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $date = $this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('DEPARTURE DATE'))}]/ancestor::tr[1]/following-sibling::tr[1]/td[1]",
                $root));
            $s = $r->addSegment();

            $s->extra()->cabin($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('DEPARTURE DATE'))}]/ancestor::tr[1]/following-sibling::tr[1]/td[2]",
                $root));

            $s->departure()
                ->date(strtotime($this->http->FindSingleNode("./following-sibling::tr[1]/descendant::text()[contains(.,':')]/ancestor::tr[1]/td[1]/descendant::text()[normalize-space()!=''][1]",
                    $root), $date))
                ->code($this->http->FindSingleNode("./following-sibling::tr[1]/descendant::text()[contains(.,':')]/ancestor::tr[1]/td[1]/descendant::text()[normalize-space()!=''][2]",
                    $root))
                ->name($this->http->FindSingleNode("./following-sibling::tr[1]/descendant::text()[contains(.,':')]/ancestor::tr[1]/preceding-sibling::tr[1]/td[normalize-space()!=''][1]",
                    $root));

            $s->arrival()
                ->date(strtotime($this->http->FindSingleNode("./following-sibling::tr[1]/descendant::text()[contains(.,':')]/ancestor::tr[1]/td[3]/descendant::text()[normalize-space()!=''][1]",
                    $root), $date))
                ->code($this->http->FindSingleNode("./following-sibling::tr[1]/descendant::text()[contains(.,':')]/ancestor::tr[1]/td[3]/descendant::text()[normalize-space()!=''][2]",
                    $root))
                ->name($this->http->FindSingleNode("./following-sibling::tr[1]/descendant::text()[contains(.,':')]/ancestor::tr[1]/preceding-sibling::tr[1]/td[normalize-space()!=''][2]",
                    $root));

            $node = $this->http->FindSingleNode("./following-sibling::tr[1]/descendant::text()[contains(.,':')]/ancestor::tr[1]/following::tr[1][{$this->contains($this->t('PLANE'))}]/following-sibling::tr[1]/td[1]",
                $root);

            if (preg_match("#^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$#", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            $s->extra()->aircraft($this->http->FindSingleNode("./following-sibling::tr[1]/descendant::text()[contains(.,':')]/ancestor::tr[1]/following::tr[1][{$this->contains($this->t('PLANE'))}]/following-sibling::tr[1]/td[2]",
                $root));

            if (isset($meal)) {
                $s->extra()->meal($meal);
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            //5 july, 2019
            '#^(\d+)\s+(\w+),\s+(\d{4})$#u',
        ];
        $out = [
            '$1 $2 $3',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
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
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
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
            if (isset($words['ORDER #'], $words['DEPARTURE DATE'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['ORDER #'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['DEPARTURE DATE'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace(["€", "£", "$", "₹"], ["EUR", "GBP", "USD", "INR"], $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
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
