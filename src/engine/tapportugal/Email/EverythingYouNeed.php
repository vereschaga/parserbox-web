<?php

namespace AwardWallet\Engine\tapportugal\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class EverythingYouNeed extends \TAccountChecker
{
    public $mailFiles = "tapportugal/it-29793002.eml, tapportugal/it-29988155.eml";

    public $reFrom = ["flytap.com"];
    public $reBody = [
        'en' => ['Do you have everything you need', 'Booking reference'],
        'pt' => ['Já tem tudo o que precisa', 'Código de Reserva'],
    ];
    public static $dict = [
        'en' => [
        ],
        'pt' => [
            'Booking reference' => 'Código de Reserva',
            'Date'              => 'Data',
            'Duration'          => 'Duração',
            'Flight'            => 'Voo',
        ],
    ];
    private $lang = '';
    private $date;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());

        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'.flytap.com/')] | //a[contains(@href,'.flytap.com/')]")->length > 0) {
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
        $f = $email->add()->flight();
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking reference'))}]/following::text()[normalize-space()!=''][1]"));

        $xpath = "//text()[{$this->eq($this->t('Date'))}]/ancestor::tr[count(./descendant::text()[{$this->eq($this->t('Date'))}])=2][1]";
        $this->logger->debug("[XPATH]: " . $xpath);
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $i => $root) {
            $s = $f->addSegment();
            $texts = $this->http->FindNodes("./td[1]/*[{$this->contains($this->t('Date'))}][1]/descendant::td[count(.//td)=0]//text()[normalize-space()!='']",
                $root);

            if (count($texts) !== 5) {
                $this->logger->debug('other segment format - dep ' . $i);

                return false;
            }

            $date = $this->normalizeDate($texts[4]);
            $s->departure()
                ->date(strtotime($texts[0], $date))
                ->code($texts[1]);

            if (preg_match("/\(\s*(\S.+)\s*\)/", $texts[2], $m)) {
                $s->departure()
                    ->name($m[1]);
            }

            $texts = $this->http->FindNodes("./td[1]/*[{$this->contains($this->t('Date'))}][2]/descendant::td[count(.//td)=0][1]//text()[normalize-space()!='']",
                $root);

            if (count($texts) !== 5) {
                $this->logger->debug('other segment format - arr ' . $i);

                return false;
            }

            $date = $this->normalizeDate($texts[4]);
            $s->arrival()
                ->date(strtotime($texts[0], $date))
                ->code($texts[1]);

            if (preg_match("/\(\s*(\S.+)\s*\)/", $texts[2], $m)) {
                $s->arrival()
                    ->name($m[1]);
            }

            $node = $this->http->FindSingleNode("./td[1]/*[{$this->contains($this->t('Date'))}][2]/descendant::text()[{$this->contains($this->t('Duration'))}]/following::text()[normalize-space()!=''][1][not({$this->contains($this->t('Flight'))})]",
                $root);

            if (!empty($node)) {
                $s->extra()->duration($node);
            }
            $node = $this->http->FindSingleNode("./td[1]/*[{$this->contains($this->t('Date'))}][2]/descendant::text()[{$this->contains($this->t('Flight'))}]/following::text()[normalize-space()!=''][1]",
                $root);

            if (preg_match("/([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)/", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            //20 Oct | 17 jun.
            '#^(\d+)\s+(\w+)\.?$#u',
        ];
        $out = [
            '$1 $2 ' . $year,
        ];
        $outWeek = [
            '',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
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
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            if (in_array($this->lang, ['it', 'pt', 'es'])) {
                $tot = PriceHelper::cost($m['t'], '.', ',');
            } else {
                $tot = PriceHelper::cost($m['t']);
            }
            $cur = $m['c'];
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
