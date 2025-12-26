<?php

namespace AwardWallet\Engine\golair\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightReservation extends \TAccountChecker
{
    public $mailFiles = "golair/it-807996856.eml";
    public $subjects = [
        ': Faça o seu check-in online!',
    ];

    public $lang = 'pt';

    public static $dictionary = [
        "pt" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@news.voegol.com.br') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'GOL Linhas Aéreas Inteligentes')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Fazer check-in agora'))}]")->length > 0
            && $this->http->XPath->query("//a[{$this->contains($this->t('consulte o nosso site'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]news\.voegol\.com\.br$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->Flight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Flight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'LOCALIZADOR GOL:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('LOCALIZADOR GOL:'))}\s*([A-Z\d]{6})$/"))
            ->travellers(explode(", ", $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Operado por:')]/preceding::text()[normalize-space()][1]")));

        $nodes = $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Operado por:')]/ancestor::table[1]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $dateFlight = $this->http->FindSingleNode("./preceding::text()[normalize-space()][2]", $root, true, "/^(.+\d{4})$/");

            $airlineInfo = implode("\n", $this->http->FindNodes("./descendant::text()[normalize-space()]", $root));

            if (preg_match("/Operado por:\n(?<airlineName>.+)\nVoo:\n(?<flightNumber>\d{1,4})/", $airlineInfo, $m)) {
                $s->airline()
                    ->name($m['airlineName'])
                    ->number($m['flightNumber']);
            }

            $depInfo = implode("\n", $this->http->FindNodes("./following::table[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<depCode>[A-Z]{3})\n(?<depName>.+)\n(?<depTime>[\d\:]+)$/", $depInfo, $m)) {
                $s->departure()
                    ->code($m['depCode'])
                    ->name($m['depName'])
                    ->date($this->normalizeDate($dateFlight . ', ' . $m['depTime']));
            }

            $arrInfo = implode("\n", $this->http->FindNodes("./following::table[1]/following::table[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<arrCode>[A-Z]{3})\n(?<arrName>.+)\n(?<arrTime>[\d\:]+)$/", $arrInfo, $m)) {
                $s->arrival()
                    ->code($m['arrCode'])
                    ->name($m['arrName'])
                    ->date($this->normalizeDate($dateFlight . ', ' . $m['arrTime']));
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        //$this->logger->debug('$date in = ' . print_r($str, true));
        $in = [
            // Quarta-Feira, 04 de Dezembro de 2024, 12:30
            "/^[\w\-]+\,\s*(\d+)\s*de\s+(\w+)\s+de\s+(\d{4})\,\s+([\d\:]+)$/iu",
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        //$this->logger->debug('$date out = ' . print_r($str, true));

        return strtotime($str);
    }
}
