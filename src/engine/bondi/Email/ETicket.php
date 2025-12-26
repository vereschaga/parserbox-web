<?php

namespace AwardWallet\Engine\bondi\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ETicket extends \TAccountChecker
{
    public $mailFiles = "bondi/it-811852970.eml";
    public $subjects = [
        '',
    ];

    public $lang = 'es';

    public static $dictionary = [
        "es" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@flybondi.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'FLYBONDI')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('TICKET ELECTRÓNICO'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Desglose de tu pago'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Forma de Pago'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Itinerario'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]flybondi\.com$/', $from) > 0;
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
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Código de Reserva']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Código de Reserva'))}\s*([A-Z\d]{6})/u"))
            ->travellers($this->http->FindNodes("//text()[normalize-space()='Nombre de Pasajero']/ancestor::tr[1]", null, "/{$this->opt($this->t('Nombre de Pasajero'))}\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])/"));

        $tickets = $this->http->FindNodes("//text()[normalize-space()='INFORMACION DE TU PASAJE NRO']/ancestor::tr[1]", null, "/{$this->opt($this->t('INFORMACION DE TU PASAJE NRO'))}\s*(\d{5,})/");

        foreach ($tickets as $ticket) {
            $pax = $this->http->FindSingleNode("//text()[{$this->eq($ticket)}]/ancestor::table[1]/descendant::text()[normalize-space()='Nombre de Pasajero']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Nombre de Pasajero'))}\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])/");

            if (!empty($pax)) {
                $f->addTicketNumber($ticket, false, $pax);
            } else {
                $f->addTicketNumber($ticket, false);
            }
        }

        $price = $this->http->FindSingleNode("//text()[normalize-space()='Total Pasaje']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Total Pasaje'))}\s*(.+)/");

        if (preg_match("/^(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,]+)$/", $price, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        $nodes = $this->http->XPath->query("//text()[normalize-space()='Origen']/ancestor::tr[1]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $airlineInfo = $this->http->FindSingleNode("./preceding-sibling::tr[1]", $root);

            if (preg_match("/^(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})$/", $airlineInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
            }

            $s->departure()
                ->noCode()
                ->name($this->http->FindSingleNode("./descendant::td[2]", $root))
                ->date($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[2][contains(normalize-space(), 'Salida Fecha - Hora')]/descendant::td[2]", $root)));

            $s->arrival()
                ->noCode()
                ->name($this->http->FindSingleNode("./following-sibling::tr[1][contains(normalize-space(), 'Destino')]/descendant::td[2]", $root))
                ->date($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[3][contains(normalize-space(), 'Llegada Fecha - hora')]/descendant::td[2]", $root)));
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
        //$this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            "#^\w+\s*(\d+)\-(\w+)\-(\d{4})\s+([\d\:]+)$#u", //SAT 14-DEC-2024 12:40
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
