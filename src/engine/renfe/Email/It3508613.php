<?php

namespace AwardWallet\Engine\renfe\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It3508613 extends \TAccountChecker
{
    public $mailFiles = "renfe/it-118450978.eml, renfe/it-3508613.eml";

    public $lang = 'es';
    public $reBody = 'Renfe';
    public $reBody2 = "Su compra se ha realizado correctamente";
    public $reSubject = "Confirmacion de venta Renfe";
    public $reFrom = "ventaOnline@renfe.es";

    public static $dictionary = [
        "es" => [
        ],
    ];

    public function ParseFlight(Email $email)
    {
        $t = $email->add()->train();

        $text = text($this->http->Response["body"]);

        $t->general()
            ->confirmation($this->re("#Localizador\s*:\s*(\w+)#", $text));

        $total = cost($this->re("#Importe\s+Total\s+de\s+la\s+Compra\s*:\s*([^\n]+)#", $text));
        $currency = currency($this->re("#Importe\s+Total\s+de\s+la\s+Compra\s*:\s*([^\n]+)#", $text));

        if (!empty($total) && !empty($currency)) {
            $t->price()
                ->total($total)
                ->currency($currency);
        }

        // Segments roots
        $xpath = "//*[normalize-space(text())='Ida :' or normalize-space(text())='Vuelta :']/ancestor::tr[1]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length > 0) {
            $this->ParseSegment1($t, $email, $segments);
        } elseif ($segments->length == 0) {
            $xpath = "//*[normalize-space(text())='Origen :' or normalize-space(text())='Destino :']/ancestor::tr[1]";
            $segments = $this->http->XPath->query($xpath);

            if ($segments->length > 0) {
                $this->ParseSegment2($t, $email, $segments);
            }
        } elseif ($segments->length == 0) {
            $this->http->Log("segments not found: $xpath", LOG_LEVEL_NORMAL);
        }
    }

    public function ParseSegment1(\AwardWallet\Schema\Parser\Common\Train $t, Email $email, \DOMNodeList $segments)
    {
        foreach ($segments as $root) {
            $date = strtotime($this->http->FindSingleNode("(./td[2]//text()[normalize-space(.)])[2]", $root, true, "#\d+-\d+-\d{4}#"));

            $s = $t->addSegment();

            $s->departure()
                ->name(trim($this->http->FindSingleNode("./td[3]", $root, true, "#:\s*(.+)#")))
                ->geoTip('europe')
                ->date(strtotime($this->http->FindSingleNode("./following::tr[contains(., 'Tren')][1]/following-sibling::tr[1]/td[2]", $root), $date));

            $s->arrival()
                ->name(trim($this->http->FindSingleNode("./td[4]", $root, true, "#:\s*(.+)#")))
                ->geoTip('europe')
                ->date(strtotime($this->http->FindSingleNode("./following::tr[contains(., 'Tren')][1]/following-sibling::tr[1]/td[3]", $root), $date));

            $s->extra()
                ->number($this->http->FindSingleNode("./following::tr[contains(., 'Tren')][1]/following-sibling::tr[1]/td[1]", $root))
                ->cabin($this->http->FindSingleNode("./following::tr[contains(., 'Tren')][1]/following-sibling::tr[1]/td[4]", $root))
                ->car($this->http->FindSingleNode("./following::tr[contains(., 'Coche')][1]/following-sibling::tr[1]/td[5]", $root))
                ->seat($this->http->FindSingleNode("./following::tr[contains(., 'Plaza')][1]/following-sibling::tr[1]/td[6]", $root));
        }
    }

    public function ParseSegment2(\AwardWallet\Schema\Parser\Common\Train $t, Email $email, \DOMNodeList $segments)
    {
        foreach ($segments as $root) {
            $date = $this->http->FindSingleNode("./following::tr[2]/descendant::td[2]", $root, true, "#\d+[\-\/]+\d+[\-\/]+\d{4}#u");

            $s = $t->addSegment();

            $s->departure()
                ->name(trim($this->http->FindSingleNode("./td[1]", $root, true, "#:\s*(.+)#")))
                ->geoTip('europe')
                ->date($this->normalizeDate($date . ', ' . str_replace('.', ':', $this->http->FindSingleNode("./following::tr[contains(., 'Tren')][1]/following-sibling::tr[1]/td[3]", $root))));

            $s->arrival()
                ->name(trim($this->http->FindSingleNode("./td[2]", $root, true, "#:\s*(.+)#")))
                ->geoTip('europe')
                ->date($this->normalizeDate($date . ', ' . str_replace('.', ':', $this->http->FindSingleNode("./following::tr[contains(., 'Tren')][1]/following-sibling::tr[1]/td[4]", $root))));

            $s->extra()
                ->number($this->http->FindSingleNode("./following::tr[contains(., 'Tren')][1]/following-sibling::tr[1]/td[1]", $root))
                ->cabin($this->http->FindSingleNode("./following::tr[contains(., 'Clase')][1]/following-sibling::tr[1]/td[5]", $root))
                ->car($this->http->FindSingleNode("./following::tr[contains(., 'Coche')][1]/following-sibling::tr[1]/td[6]", $root))
                ->seat($this->http->FindSingleNode("./following::tr[contains(., 'Plaza')][1]/following-sibling::tr[1]/td[7]", $root));

            $t->addTicketNumber($this->http->FindSingleNode("./following::tr[contains(., 'Cod. Billete')][1]/following-sibling::tr[1]/td[8]", $root), false);
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["from"], $this->reFrom) !== false || strpos($headers["subject"], $this->reSubject) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
        }

        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function getField($str, $root)
    {
        return $this->http->FindSingleNode(".//*[contains(text(), '{$str}')]/ancestor-or-self::td[1]/following-sibling::td[1]", $root);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
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
        //$this->logger->error($str);

        $in = [
            "#^(\d+)\/(\d+)\/(\d{4})\,\s*([\d\:]+)$#", //20/10/2021, 09:15
        ];
        $out = [
            "$1.$2.$3, $4",
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
