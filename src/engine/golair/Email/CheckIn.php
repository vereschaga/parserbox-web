<?php

namespace AwardWallet\Engine\golair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CheckIn extends \TAccountChecker
{
    public $mailFiles = "golair/it-116222673.eml, golair/it-133819612.eml";

    private $detectFrom = "@voegol.com.br";
    private $detectSubject = [
        // pt
        "Lembrete de Check-in",
        "Antecipação de Check-in",
    ];

    private $detectBody = [
        "pt" => ["Antecipe o seu check-in.", "Check-in agora"],
    ];

    private $lang = "pt";
    private static $dictionary = [
        "pt" => [
            "CÓDIGO DE RESERVA GOL:" => ["CÓDIGO DE RESERVA GOL:", "CODIGO DE RESERVA GOL:", "LOCALIZADOR GOL:"],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        if (strpos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'www.voegol.com.br')] | //*[contains(.,'www.voegol.com.br')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $travellers = $this->http->FindNodes("//tr[not(.//tr)][" . $this->eq($this->t("Passageiros:")) . "]/following-sibling::tr[normalize-space()]", null, "/^[ ,[:alpha:]\-\']+$/");

        $travellers = explode(',', implode(',', $travellers));

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->starts($this->t('CÓDIGO DE RESERVA GOL:')) . "]/ancestor::tr[1]",
                null, true, "/:\s*([A-Z\d]{5,7})\s*$/u"))
            ->travellers(preg_replace("/^\s*(MR|MRS|MISS|MISTER|DR|MS)\s+/", '', array_map('trim',
                array_filter($travellers))), true);

        // Segment
        $xpath = "//*[" . $this->starts($this->t('Data:')) . "]/following-sibling::*[normalize-space()][1][" . $this->starts($this->t('Voo:')) . "]/ancestor::*[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $date = strtotime(str_replace([$this->t('Data:'), '/'], ['', '.'], $this->http->FindSingleNode("*[normalize-space()][1]", $root, true, "/:\s*(.+)/")));

            $s->airline()
                ->name('G3');

            $flightNumber = $this->http->FindSingleNode("*[normalize-space()][2]", $root, true, "/:\s*(\d{1,5})\s*$/");

            if (!empty($flightNumber)) {
                $s->airline()
                    ->number($flightNumber);
            } else {
                $s->airline()
                    ->noNumber();
            }

            // Departure
            $depPoint = $this->http->FindSingleNode("*[normalize-space()][3]", $root, true, "/:\s*(.+)\s*$/");

            if (preg_match("/^(?<code>[A-Z]{3})$/", $depPoint, $m)) {
                $s->departure()
                    ->code($m['code']);
            } else {
                $s->departure()
                    ->name($depPoint)
                    ->noCode();
            }

            $time = $this->http->FindSingleNode("*[normalize-space()][5]", $root, true, "/:\s*(\d{1,2}:\d{2}.*)\s*$/");

            if (empty($time)) {
                $time = $this->http->FindSingleNode("*[normalize-space()][3]/following::text()[{$this->eq($this->t('Partida:'))}][1]/ancestor::table[1]", $root, true, "/{$this->opt($this->t('Partida:'))}\s*(\d{1,2}:\d{2}.*)\s*$/");
            }

            if (!empty($time) && !empty($date)) {
                $s->departure()->date(strtotime($time, $date));
            }

            // Arrival
            $arrPoint = $this->http->FindSingleNode("*[normalize-space()][4]", $root, true, "/:\s*(.+)\s*$/");

            if (empty($arrPoint)) {
                $arrPoint = $this->http->FindSingleNode("*[normalize-space()][3]/following::text()[{$this->eq($this->t('Destino:'))}][1]/ancestor::table[1]", $root, true, "/{$this->opt($this->t('Destino:'))}\s*(.+)/");
            }

            if (preg_match("/^(?<code>[A-Z]{3})$/", $arrPoint, $m)) {
                $s->arrival()
                    ->code($m['code']);
            } else {
                $s->arrival()
                    ->name($arrPoint)
                    ->noCode();
            }

            $time = $this->http->FindSingleNode("*[normalize-space()][6]", $root, true, "/:\s*(\d{1,2}:\d{2}.*)\s*$/");

            if (empty($time)) {
                $time = $this->http->FindSingleNode("*[normalize-space()][3]/following::text()[{$this->eq($this->t('Chegada:'))}][1]/ancestor::table[1]", $root, true, "/{$this->opt($this->t('Chegada:'))}\s*(\d{1,2}:\d{2}.*)\s*$/");
            }

            if (!empty($time) && !empty($date)) {
                $s->arrival()->date(strtotime($time, $date));
            }
        }

        return $email;
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
//        $this->logger->debug('$date = '.print_r( $str,true));
        $in = [
            //            "#^\s*(\d{2})-(\d{2})-(\d{4})\s*$#iu",// 07-10-2021
        ];
        $out = [
            //            "$1.$2.$3",
        ];
        $str = preg_replace($in, $out, $str);
//        if (preg_match("#\s*\d+\s+([^\d\s]+)\s*$#", $str, $m)) {
//            $str = EmailDateHelper::calculateDateRelative($str, $this, $this->parser);
//
//            return $str;
//        }

        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
