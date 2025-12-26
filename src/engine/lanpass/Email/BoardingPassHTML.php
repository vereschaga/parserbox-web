<?php

namespace AwardWallet\Engine\lanpass\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BoardingPassHTML extends \TAccountChecker
{
    public $mailFiles = "lanpass/it-83856921.eml, lanpass/it-84066515.eml, lanpass/it-84113818.eml";
    public $subjects = [
        '/Cartão de embarque de /',
    ];

    public $lang = '';
    public $date;

    public $detectLang = [
        'pt' => ['Cartão de embarque'],
    ];

    public static $dictionary = [
        "pt" => [
            'LATAM Airlines' => ['LATAM Airlines', '@latam.com'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@mail.latam.com') !== false) {
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
        if ($this->assignLang() == true) {
            return
                ($this->http->XPath->query("//text()[{$this->contains($this->t('LATAM Airlines'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Cartão de embarque'))}]")->length > 0 //Boarding pass
                    && $this->http->XPath->query("//text()[{$this->contains($this->t('A sua viagem'))}]")->length > 0)
                || ($this->http->XPath->query("//text()[{$this->contains($this->t('LATAM Airlines'))}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($this->t('Revise o cartão de embarque atualizado'))}]")->length > 0 //Boarding pass
                    && $this->http->XPath->query("//text()[{$this->contains($this->t('Ver cartão'))}]")->length > 0)
            ;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]latam\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());

        $this->assignLang();

        $b = $email->add()->bpass();

        $subject = $parser->getSubject();

        if (preg_match("/Cartão de embarque de\s*(?<traveller>\D+)(?:\.|\-)\s*(?<date>.+)\s*(?<depCode>[A-Z]{3})\s*\-\s*(?<arrCode>[A-Z]{3})\s*(?<name>[A-Z\d]{2})\s*(?<number>\d{2,4})/", $subject, $m)) {
            $b->setTraveller($m['traveller'])
                ->setDepCode($m['depCode'])
                ->setDepDate($this->normalizeDate($m['date']))
                ->setFlightNumber($m['number'])
                ->setUrl($this->http->FindSingleNode("//text()[{$this->starts($this->t('A sua viagem'))}]/following::a[contains(normalize-space(), 'Cartão de embarque')][1]/@href"));
        } else {
            $b->setTraveller($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Ver cartão')]/preceding::text()[normalize-space()][1]"))
                ->setDepDate($this->normalizeDate($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Ver cartão')]/preceding::text()[contains(normalize-space(), ':')][1]")))
                ->setFlightNumber($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Está tudo pronto para seu voo')]", null, true, "/{$this->opt($this->t('Está tudo pronto para seu voo'))}\s*[A-Z-d]{2}(\d{2,4})/"))
                ->setUrl($this->http->FindSingleNode("//a[{$this->starts($this->t('Ver cartão'))}]/@href"));
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $reBody) {
            foreach ($reBody as $word) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$word}')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $this->logger->debug($str);
        $year = date('Y', $this->date);
        $in = [
            "#^(\d+)\s*de\s*(\w+)\s*de\s*(\d{4})\s*$#", // 6 de Maio de 2019
            "#(\d+)/(\d+)/(\d+)#", //12/18/17
            "#^\w+\.\s*(\d+\s*\w+)\.\s*([\d\:]+)\s*h\s*$#u", //sáb. 20 mar. 14:50 h
        ];
        $out = [
            "$1 $2 $3",
            "$2.$1.20$3",
            "$1 $year $2",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
