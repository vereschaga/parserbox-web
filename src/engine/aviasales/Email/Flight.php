<?php

namespace AwardWallet\Engine\aviasales\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
    public $mailFiles = "aviasales/it-825840552.eml, aviasales/it-847498175.eml, aviasales/it-854774419.eml, aviasales/it-856857064.eml";
    public $subjects = [
        'Ваш билет готов (заказ №',
    ];

    public $lang = '';
    public $subject;

    public $pdfNamePattern = 'Ticket.*pdf';

    public $detectLang = [
        'ru' => ['Маршрут'],
    ];

    public static $dictionary = [
        'ru' => [
            'Билеты по вашему заказу' => 'Билеты по вашему заказу',
            'Маршрут'                 => 'Маршрут',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@o.aviasales.ru') !== false) {
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
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            if (($textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            if (strpos($textPdf, 'Продавец этого билета — Авиасейлс') === false) {
                continue;
            }

            if (strpos($textPdf, 'Маршрутная квитанция / Электронный билет') === false) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        $this->assignLangHtml();

        if (
            $this->http->XPath->query("//a/@href[{$this->contains(['aviasales.ru'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Билеты по вашему заказу']) && $this->http->XPath->query("//*[{$this->contains($dict['Билеты по вашему заказу'])}]")->length > 0
                && !empty($dict['Маршрут']) && $this->http->XPath->query("//*[{$this->contains($dict['Маршрут'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]o\.aviasales\.ru$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->FlightPdf($email, $text);
            $type = 'Pdf';
        }

        if (count($pdfs) === 0) {
            $this->assignLangHtml();
            $this->FlightHtml($email);
            $type = 'Html';
        }

        $email->setType('Flight' . $type . ucfirst($this->lang));

        return $email;
    }

    public function FlightHtml(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()->noConfirmation();

        $f->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Заказ:'))}]/following::text()[normalize-space()][1]", null, true, "/^№ ([A-Z\d]{8})$/u"));

        $reservationDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Дата:'))}]/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<time>\d{1,2}\:\d{2})\s+.*(?<date>\d{1,2}\s+\w+)\,\s+\w+\,\s+(?<year>\d{4})$/u", $reservationDate, $d)) {
            $f->general()
                ->date($this->normalizeDate($d['date'] . ' ' . $d['year'] . ' ' . $d['time']));
        }

        $priceInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Сумма:'))}]/following::text()[normalize-space()][1]", null, true, "/^([\d\.\,\`\s]+\s+\D{1,3})$/");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<price>[\d\.\,\']+)$/", $priceInfo, $m)
            || preg_match("/^(?<price>[\d\.\,\'\s]+)\s*(?<currency>\D{1,3})$/", $priceInfo, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['price'], $m['currency']))
                ->currency($this->normalizeCurrency($m['currency']));
        }

        $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t('ФИО:'))}]/following::text()[normalize-space()][1]", null, "/^([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/u");
        $f->setTravellers($travellers, true);

        $segmentNodes = $this->http->XPath->query("//text()[normalize-space()][{$this->eq($this->t('вылет'))}]/ancestor::tr[not({$this->starts($this->t('вылет'))})][1][.//text()[{$this->eq($this->t('прибытие'))}]][count(.//img) = 2]");

        foreach ($segmentNodes as $root) {
            $s = $f->addSegment();
            $text = implode("\n", $this->http->FindNodes(".//td[not(.//td)][normalize-space()]", $root));

            $s->airline()
                ->noName()
                ->noNumber();

            if (preg_match("/^(?<depName>.+)[ ]+\((?<depCode>[A-Z]{3})\)[ ]\—[\n\s]*(?<arrName>.+)[ ]+\((?<arrCode>[A-Z]{3})\)[\n\s]*вылет[\n\s]*(?<depTime>\d{1,2}\:\d{2}[ ]*A?P?M?)[\n\s]*(?<depDate>\d{1,2}[ ]+[[:alpha:]]+[ ]*\d{4})[\n\s]*прибытие[\n\s]*(?<arrTime>\d{1,2}\:\d{2}[ ]*A?P?M?)[\n\s]*(?<arrDate>\d{1,2}[ ]+[[:alpha:]]+[ ]*\d{4})[\n\s]*$/u", $text, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->code($m['depCode'])
                    ->date($this->normalizeDate($m['depDate'] . ' ' . $m['depTime']));
                $s->arrival()
                    ->name($m['arrName'])
                    ->code($m['arrCode'])
                    ->date($this->normalizeDate($m['arrDate'] . ' ' . $m['arrTime']));
            }
        }
    }

    public function FlightPdf(Email $email, $text)
    {
        $f = $email->add()->flight();

        $this->logger->debug($text);
        preg_match_all("/{$this->opt($this->t('НОМЕР ЗАКАЗА'))}.+{$this->opt($this->t('НОМЕР БРОНИ'))}\n.+[ ]{2,}([\d\-]+)\n+{$this->opt($this->t('ПАССАЖИР'))}/u", $text, $tickets);

        if (!empty($tickets[1])) {
            foreach (array_unique($tickets[1]) as $ticketInfo) {
                $f->general()
                    ->confirmation($ticketInfo, "{$this->t('НОМЕР БРОНИ')}", false, ['regexp' => '/^[A-Z\d\-]{4,20}$/u']);
            }
        } else {
            $f->general()
                ->noConfirmation();
        }

        preg_match_all("/{$this->opt($this->t('НОМЕР ЗАКАЗА'))}.+(?:ЭЛЕКТРОННЫЙ БИЛЕТ|НОМЕР БРОНИ)\n[ ]*([A-Z\d]{8})/u", $text, $otaNumbers);

        foreach (array_unique($otaNumbers[1]) as $otaNum) {
            $f->ota()
                ->confirmation($otaNum);
        }

        $reservationDate = $this->re("/{$this->opt($this->t('Авиабилет'))}.+{$this->opt($this->t('ДАТА ЗАКАЗА'))}\n[ ]*[A-Z\d]{8}[ ]+(\d{1,2}[ ]+\w+[ ]+\d{4}[ ]+\d{1,2}\:\d{2})/u", $text);

        if ($reservationDate !== null) {
            $f->general()
                ->date($this->normalizeDate($reservationDate));
        }

        preg_match_all("/{$this->opt($this->t('ПАССАЖИР'))}[ ]\/[ ]{$this->opt($this->t('ДОКУМЕНТ'))}\n{1,}([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])[ ]\/.+\n/u", $text, $travellers);

        $f->setTravellers(array_unique($travellers[1]), true);

        if (preg_match_all("/{$this->opt($this->t('НОМЕР ЗАКАЗА'))}.+{$this->opt($this->t('ЭЛЕКТРОННЫЙ БИЛЕТ'))}\n.+[ ]{2,}(?<ticket>[\d\-]+)\n+{$this->opt($this->t('ПАССАЖИР'))}[ ]\/[ ]{$this->opt($this->t('ДОКУМЕНТ'))}\n+(?<travellerName>[[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])[ ]*\//u", $text, $tickets)) {
            foreach (array_unique($tickets['ticket']) as $i => $ticket) {
                $f->addTicketNumber($ticket, true, $tickets['travellerName'][$i]);
            }
        }

        preg_match_all("/{$this->opt($this->t('Лучше приехать в аэропорт'))}.+?\n{2}.*?\n{2,}(.*?[ ]{2,}Всего в пути)/us", $text, $segments);

        foreach ($segments[1] as $seg) {
            $segNum = $this->re("/{$this->opt($this->t('Номер брони для регистрации'))}\:[ ]*([A-Z\d]{5,7})\b/u", $seg);
            $seg = preg_replace("/(\n{2,}[ ]*.+?Всего в пути)/u", "", $seg);

            foreach (preg_split("(\n{2,})", $seg) as $root) {
                $s = $f->addSegment();

                if ($segNum !== null) {
                    $s->airline()->confirmation($segNum);
                }

                $flightNodes = $this->createTable($root, $this->rowColumnPositions($this->inOneRow($root)));

                $s->airline()
                    ->name($this->re("/^[\n\s]*([A-Z0-9]{2})\-[0-9]{1,4}\n*/u", $flightNodes[2]))
                    ->number($this->re("/^[\n\s]*[A-Z0-9]{2}\-([0-9]{1,4})\n*/u", $flightNodes[2]));

                if (preg_match("/[\n\s]*.+\n*(?<name>.+)[ \n]+\((?<code>[A-Z]{3})\)[\n\s]*(?:$|{$this->opt($this->t('Терминал'))}[ ]+(?<terminal>.+)[\n\s]*$)/u", $flightNodes[0], $dep)) {
                    $s->departure()
                        ->name($dep['name'])
                        ->code($dep['code']);

                    if (isset($dep['terminal'])) {
                        $s->departure()
                            ->terminal($dep['terminal']);
                    }
                }

                if (preg_match("/[\n\s]*.+\n*(?<name>.+)[ \n]+\((?<code>[A-Z]{3})\)[\n\s]*(?:$|{$this->opt($this->t('Терминал'))}[ ]+(?<terminal>.+)[\n\s]*$)/u", $flightNodes[5], $arr)) {
                    $s->arrival()
                        ->name($arr['name'])
                        ->code($arr['code']);

                    if (isset($arr['terminal'])) {
                        $s->arrival()
                            ->terminal($arr['terminal']);
                    }
                }

                preg_match_all("/[\n ]*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]][ ]+{$dep['code']}[ ]*\-[ ]*{$arr['code']}[ ]+[0-9]{1,2}[A-Z])\n/u", $text, $seatNodes);

                if (!empty($seatNodes[1])) {
                    foreach ($seatNodes[1] as $seatNode) {
                        if (preg_match("/^(?<name>[[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])[ ]+{$dep['code']}[ ]*\-[ ]*{$arr['code']}[ ]+(?<seat>[0-9]{1,2}[A-Z])$/", $seatNode, $seat)) {
                            $s->extra()
                                ->seat($seat['seat'], false, false, $seat['name']);
                        }
                    }
                }

                if (preg_match("/^[\n\s]*(?<time>\d{1,2}\:\d{2})[\n\s]*(?<date>\d{1,2}[ ]*\D+[ ]*\d{4})[\n\s]*$/u", $flightNodes[1], $m)) {
                    $s->departure()
                        ->date($this->normalizeDate($m['date'] . ' ' . $m['time']));
                }

                if (preg_match("/^[\n\s]*(?<time>\d{1,2}\:\d{2})[\n\s]*(?<date>\d{1,2}[ ]*\D+[ ]*\d{4})[\n\s]*$/u", $flightNodes[4], $m)) {
                    $s->arrival()
                        ->date($this->normalizeDate($m['date'] . ' ' . $m['time']));
                }

                $duration = $this->re("/[\n\s]*{$this->opt($this->t('Полёт'))}[ ]*(.+{$this->opt($this->t('мин'))})[\n\s]*/u", $flightNodes[2]);

                if ($duration !== null) {
                    $s->extra()
                        ->duration($duration);
                }

                $operatedBy = $this->re("/\n+(.+)[\n\s]*$/u", $flightNodes[3]);

                if ($operatedBy !== null) {
                    $s->airline()
                        ->operator($operatedBy);
                }

                $aircraft = $this->re("/^[\s\n]*(.+)\n+\s*.+/u", $flightNodes[3]);

                if ($aircraft !== null) {
                    $s->extra()
                        ->aircraft($aircraft);
                }

                $segments = $f->getSegments();

                foreach ($segments as $segment) {
                    if ($segment->getId() !== $s->getId()) {
                        if (serialize(array_diff_key($segment->toArray(),
                                ['seats' => []])) === serialize(array_diff_key($s->toArray(), ['seats' => []]))) {
                            if (!empty($s->getSeats())) {
                                $segment->extra()->seats(array_unique(array_merge($segment->getSeats(),
                                    $s->getSeats())));
                            }
                            $f->removeSegment($s);

                            break;
                        }
                    }
                }
            }
        }

        $priceInfo = $this->re("/\n *{$this->opt($this->t('Детали заказа'))}\n.+?\n *{$this->opt($this->t('Итого'))}[ ]{2,}(\d[\d.,' ]*[ ]\D{1,3})(?:\n|[ ]\()/su", $text);

        if (preg_match("/^(?<currency>\D{1,3})[ ]*(?<price>\d[\d\.\,\']*)$/", $priceInfo, $m)
            || preg_match("/^(?<price>\d[\d\.\,\'\s]*)[ ]*(?<currency>\D{1,3})$/", $priceInfo, $m)) {
            $currency = $m['currency'];

            $f->price()
                ->total(PriceHelper::parse($m['price'], $currency))
                ->currency($this->normalizeCurrency($currency));

            $costArray = [];
            preg_match_all("/\n *{$this->opt($this->t('Стоимость'))}\n+ *{$this->opt($this->t('Тариф'))}[ ]{2,}(\d[\d.,' ]*)[ ]\D{1,3}(?:\n|[ ]\()/su", $text, $cost);

            if (!empty($cost[1])) {
                foreach ($cost[1] as $cs) {
                    $costArray[] = PriceHelper::parse($cs, $currency);
                }
            }

            if (count($costArray) > 0) {
                $f->price()
                    ->cost(array_sum($costArray));
            }

            $taxesArray = [];
            preg_match_all("/{$this->opt($this->t('Такса'))}[ ]+[A-Z0-9]{2}[ ]+(\d[\d.,' ]*)[ ]\D{1,3}(?:\n|[ ]\()/su", $text, $tax);

            if (!empty($tax[1])) {
                foreach ($tax[1] as $ts) {
                    $taxesArray[] = PriceHelper::parse($ts, $currency);
                }
            }

            if (count($taxesArray) > 0) {
                $f->price()
                    ->tax(array_sum($taxesArray));
            }

            $fees = $this->re("/{$this->opt($this->t('Детали заказа'))}(.+){$this->opt($this->t('Итого'))}/su", $text);

            if ($fees !== null) {
                foreach (preg_split("/\n{1,}/", $fees) as $fee) {
                    if (preg_match("/(?<name>.+\b)[ ]{2,}(?<value>\d[\d\.\,\'\s]*)[ ]+\D{1,3}/u", $fee, $fees)) {
                        if (!preg_match("/{$this->opt($this->t('Авиабилет'))}/u", $fees['name'])) {
                            $f->price()
                                ->fee($fees['name'], PriceHelper::parse($fees['value'], $currency));
                        }
                    }
                }
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

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        if (preg_match("#^\d{1,2}[ ]+([[:alpha:]]+)[ ]*\d{4}[ ]+\d{1,2}\:\d{2}[ ]*A?P?M?$#u", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function assignLang($text)
    {
        foreach ($this->detectLang as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (mb_strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLangHtml()
    {
        foreach ($this->detectLang as $lang => $dBody) {
            foreach ($dBody as $word) {
                if ($this->http->XPath->query("//*[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function createTable(?string $text, $pos = []): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColumnPositions($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function rowColumnPositions(?string $row): array
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));

        if (empty($textRows)) {
            return '';
        }
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'RUB' => ['Руб.', '₽'],
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$', 'US$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
