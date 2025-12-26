<?php

namespace AwardWallet\Engine\rzd\Email\Statement;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Subscription extends \TAccountChecker
{
    public $mailFiles = "rzd/statements/it-130961642.eml, rzd/statements/it-85567120.eml, rzd/statements/it-85567123.eml, rzd/statements/it-86239546.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@rzd-bonus.ru') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,"//rzd-bonus.ru/") or contains(@href,".rzd-bonus.ru/") or contains(@href,"www.rzd-bonus.ru")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"С уважением,Команда программы «РЖД Бонус") or contains(.,"www.rzd-bonus.ru")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1 || $this->isMembership();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $st = $email->add()->statement();

        $name = $number = $status = $balance = null;

        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Уважаемый') or starts-with(normalize-space(),'Уважаемая')]", null, true, "/^(?:Уважаемый|Уважаемая)\s+({$patterns['travellerName']})(?:\s*[,:;!?]|$)/u");

        $number = $this->http->FindSingleNode("//text()[normalize-space()='Номер счета:']/following::text()[normalize-space()][1]", null, true, "/^[-A-Z\d]{5,}$/");

        $status = $this->http->FindSingleNode("//text()[normalize-space()='Уровень:']/following::text()[normalize-space()][1]", null, true, "/^[[:alpha:]]+$/u");

        $roots = $this->findRoot();

        if ($roots->length === 1) {
            $root = $roots->item(0);

            $rootText = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $root));

            /*
                Номер счета: 9002541815460
                Баланс баллов: 6015
            */

            if (preg_match("/^[ ]*Номер счета[ ]*[:]+[ ]*([-A-Z\d ]{5,}?)[ ]*$/m", $rootText, $m)) {
                $number = $m[1];
            }

            if (preg_match("/^[ ]*Баланс(?: баллов)?[ ]*[:]+[ ]*(\d[,.\'\d ]*?)[ ]*$/m", $rootText, $m)) {
                $balance = $m[1];
            }
        }

        if (preg_match("/^(?:пассажир|участник|пользователь)$/i", $name)) {
            $name = null;
        }

        if ($name) {
            $st->addProperty('Name', $name);
        }

        if ($number) {
            $st->setNumber($number)->setLogin($number);
        }

        if ($status) {
            $st->addProperty('Level', $status);
        }

        if ($balance !== null) {
            $st->setBalance($this->normalizeAmount($balance));
        } elseif ($name || $number || $status) {
            $st->setNoBalance(true);
        }

        if ($this->http->XPath->query("//text()[contains(., 'Подробная информация по начислению баллов за совершенную поездку')]")->length > 0) {
            $this->parseTrain($email);
        }

        return $email;
    }

    public function parseTrain(Email $email) {
        $t = $email->add()->train();

        // General
        $t->general()
            ->noConfirmation();

        // Segments
        $text = implode("\n", $this->http->FindNodes("//text()[".$this->starts("Дата отпр. пассажира:")."]/ancestor::*[".$this->contains("Данные поездки:")."][1]//text()[normalize-space()]"));
        $this->logger->debug('$text = '.print_r( $text,true));

        $s = $t->addSegment();

        // Departure
        $s->departure()
            ->name($this->re("/".$this->opt("Пункт отпр. пассажира:")."\s*(.+)/u", $text))
            ->date(strtotime($this->re("/".$this->opt("Дата отпр. пассажира:")."\s*(.+)/u", $text)));

        // Arrival
        $s->arrival()
            ->name($this->re("/".$this->opt("Пункт приб. пассажира:")."\s*(.+)/u", $text))
            ->date(strtotime($this->re("/".$this->opt("Дата приб. пассажира:")."\s*(.+)/u", $text)));

        // Extra
        $s->extra()
            ->number($this->re("/".$this->opt("Номер поезда:")."\s*(.+)/u", $text))
            ->miles($this->re("/".$this->opt("Расстояние:")."\s*(.+)/u", $text))
            ->cabin($this->re("/".$this->opt("Класс обслуживания:")."\s*(.+)/u", $text))
        ;

        // Ticket
        $t->setTicketNumbers([$this->re("/".$this->opt("Цифр. номер:")."\s*(\d{10,})/u", $text)], false);

        // Price
        $currency = $this->currency($this->re("/".$this->opt("Валюта:")."\s*(.+)/u", $text));
        $t->price()
            ->total(PriceHelper::parse($this->re("/".$this->opt("Цена:")."\s*(.+)/u", $text)), $currency)
            ->currency($currency)
        ;
        return true;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//tr/*[2][ not(.//tr) and descendant::text()[starts-with(normalize-space(),'Номер счета')] and descendant::text()[starts-with(normalize-space(),'Баланс')] ]");
    }

    private function isMembership(): bool
    {
        $phrases = [
            'Благодарим Вас за участие в программе лояльности холдинга «РЖД',
            'Благодарим Вас за участие в программе лояльности для пассажиров',
            'благодарим Вас за участие в нашей программе',
        ];

        return $this->http->XPath->query("//*[{$this->contains($phrases)}]")->length > 0;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }
    private function starts($field)
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) {
                return 'starts-with(normalize-space(.),"' . $s . '")';
            }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);
        if(isset($m[$c])) return $m[$c];
        return null;
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array)$field;
        if (empty($field)) {
            $field = ['false'];
        }
        return '(?:' . implode("|", array_map(function ($s) use($delimiter) {
                return str_replace(' ', '\s+', preg_quote($s, $delimiter));
            }, $field)) . ')';
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) return $code;
        $sym = [
            'РУБ' => 'RUB',
//            '€' => 'EUR',
//            '$' => 'USD',
//            '£' => 'GBP',
        ];
        foreach($sym as $f => $r)
            if ($s == $f) return $r;
        return null;
    }
}
