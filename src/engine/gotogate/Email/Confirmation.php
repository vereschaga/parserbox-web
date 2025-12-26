<?php

namespace AwardWallet\Engine\gotogate\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "gotogate/it-13556179.eml, gotogate/it-13573776.eml, gotogate/it-39068522.eml, gotogate/it-6732796.eml, gotogate/it-39013095.eml";

    public $reFrom = "reservev3@reserve.com.br";
    public $reBody = [
        'pt' => ['Confirmação de Passagem Aérea', 'Confirmação de Reserva de Hotel'],
    ];
    public $reSubject = [
        '#Pedido [A-Z\d]+ Confirmado#',
    ];
    public $lang = '';
    public static $dict = [
        'pt' => [],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        if ($this->http->XPath->query("//*[contains(normalize-space(),'Check-Out:')]")->length > 0) {
            $type = 'Hotel';
            $this->parseHotel($email);
        } else {
            $type = 'Flight';
            $this->parseFlight($email);
        }

        $email->setType('Confirmation' . $type . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'reserve.com')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseHotel(Email $email)
    {
        $xpathBold = '(self::b or self::strong)';

        $h = $email->add()->hotel();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Localizador:'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Localizador:'))}]/following::text()[string-length(normalize-space(.))>2][1]", null, true, '/^[\d\.]{5,}$/');
        }

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Localizador:'))}]", null, true, '/^(.+?)[\s:]*$/');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        $guest = $this->http->FindSingleNode("//tr[ not(.//tr) and *[normalize-space()][1][normalize-space()='Nome:'] ]/following-sibling::tr[normalize-space()]/td[normalize-space()][1]", null, true, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');
        $h->general()->traveller($guest);

        $xpathHotel = "//tr[{$this->eq($this->t('Hotel'))}]";

        $hotelName = $this->http->FindSingleNode($xpathHotel . "/following::text()[{$this->eq($this->t('Nome:'))}]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathBold}])]");
        $address = $this->http->FindSingleNode($xpathHotel . "/following::text()[{$this->eq($this->t('Endereço:'))}]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathBold}])]");
        $city = $this->http->FindSingleNode($xpathHotel . "/following::text()[{$this->eq($this->t('Cidade:'))}]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathBold}])]");
        $phone = $this->http->FindSingleNode($xpathHotel . "/following::text()[{$this->eq($this->t('Telefone:'))}]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathBold}])]", null, true, '/^[+(\d][-. \d)(]{5,}[\d)]$/');
        $h->hotel()
            ->name($hotelName)
            ->address($address . ', ' . $city)
            ->phone($phone);

        $checkIn = $this->http->FindSingleNode($xpathHotel . "/following::text()[{$this->eq($this->t('Check-In:'))}]/following::text()[normalize-space()][1]");
        $h->booked()->checkIn2($this->normalizeDate($checkIn));

        $checkOut = $this->http->FindSingleNode($xpathHotel . "/following::text()[{$this->eq($this->t('Check-Out:'))}]/following::text()[normalize-space()][1]");
        $h->booked()->checkOut2($this->normalizeDate($checkOut));

        $deadline = $this->http->FindSingleNode($xpathHotel . "/following::text()[{$this->eq($this->t('Prazo de Cancelamento:'))}]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathBold}])]");

        if ($deadline) {
            $h->booked()->deadline2($this->normalizeDate($deadline));
        }

        $accommodation = $this->http->FindSingleNode($xpathHotel . "/following::text()[{$this->eq($this->t('Acomodação:'))}]/following::text()[normalize-space()][1][not(contains(.,':'))]");

        if ($accommodation) {
            $room = $h->addRoom();
            $room->setDescription($accommodation);
        }

        $payment = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total:'))}]/ancestor::tr[1]");

        if ($payment !== null) {
            $tot = $this->getTotalCurrency($payment);

            if ($tot['Total'] !== '') {
                $h->price()
                    ->currency($tot['Currency'])
                    ->total($tot['Total']);
            }
        }

        $cancellation = $this->http->FindSingleNode("//*[{$this->eq($this->t('Política Hotelaria'))}]/following-sibling::*[normalize-space()][1]", null, true, "/^.*{$this->opt($this->t('cancel'))}.*$/i");

        if (!empty($cancellation)) {
            $h->general()->cancellation($cancellation);
        }
    }

    private function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[normalize-space()='Localizador:']/following::text()[string-length(normalize-space())>2][1]", null, true, "#^\s*([A-Z\d]+)\s*$#");
        $confirmationTitle = $this->http->FindSingleNode("//text()[normalize-space()='Localizador:']", null, true, '/^(.+?)[\s:]*$/');
        $f->general()->confirmation($confirmation, $confirmationTitle, true);

        $confirmation2 = $this->http->FindSingleNode("//text()[normalize-space()='Localizador Cia:']/following::text()[string-length(normalize-space())>2][1]", null, true, "#^\s*([A-Z\d]+)\s*$#");

        if ($confirmation2) {
            $confirmation2Title = $this->http->FindSingleNode("//text()[normalize-space()='Localizador Cia:']", null, true, '/^(.+?)[\s:]*$/');
            $f->general()->confirmation($confirmation2, $confirmation2Title);
        }

        $passenger = $this->http->FindSingleNode("//text()[normalize-space()='Nome:']/ancestor::tr[1][count(descendant::td)>3]/following-sibling::tr[normalize-space()]/td[normalize-space()][1]");
        $f->general()->traveller($passenger);

        $etkt = $this->http->FindSingleNode("//text()[normalize-space()='Nome:']/ancestor::tr[1][count(descendant::td)>3]/following-sibling::tr[normalize-space()]/td[normalize-space()][2]");
        $f->addTicketNumber($etkt, false);

        $mileageCard = $this->http->FindSingleNode("//tr[ not(.//tr) and *[normalize-space()][5][normalize-space()='Cartão de Milhagem'] ]/following-sibling::tr[normalize-space()]/td[normalize-space()][5]", null, true, '/^(?:[^:]+?:\s*)?(.+)$/');

        if ($mileageCard !== null && strcasecmp($mileageCard, 'N/D') !== 0) {
            $f->addAccountNumber($mileageCard, false);
        }

        $payment = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total:'))}]/ancestor::tr[1]");

        if ($payment !== null) {
            $tot = $this->getTotalCurrency($payment);

            if ($tot['Total'] !== '') {
                $f->price()
                    ->currency($tot['Currency'])
                    ->total($tot['Total']);
            }
        }

        $xpath = "//text()[normalize-space()='De:']/ancestor::tr[1][contains(.,'Para:')]";
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $dateDep = preg_match("#Saída:\s+(.+)\s+Hora:\s+(\d+:\d+)#", $this->http->FindSingleNode("following-sibling::tr[1]/td[normalize-space()][1]", $root), $m)
                ? strtotime($this->normalizeDate($m[1] . ' ' . $m[2])) : null;

            $terminalDep = preg_match("#Terminal de Embarque:\s+(.+)#", $this->http->FindSingleNode("following-sibling::tr[2]/td[normalize-space()][1]", $root), $m) && strcasecmp($m[1], 'N/D') !== 0
                ? $m[1] : null;

            $s->departure()
                ->noCode()
                ->name($this->http->FindSingleNode("td[normalize-space()][1]", $root, true, "#:\s+(.+)#"))
                ->date($dateDep)
                ->terminal($terminalDep, false, true);

            $dateArr = preg_match("#Chegada:\s+(.+)\s+Hora:\s+(\d+:\d+)#", $this->http->FindSingleNode("following-sibling::tr[1]/td[normalize-space()][2]", $root), $m)
                ? strtotime($this->normalizeDate($m[1] . ' ' . $m[2])) : null;

            $terminalArr = preg_match("#Terminal de Desembarque:\s+(.+)#", $this->http->FindSingleNode("following-sibling::tr[2]/td[normalize-space()][2]", $root), $m) && strcasecmp($m[1], 'N/D') !== 0
                ? $m[1] : null;

            $s->arrival()
                ->noCode()
                ->name($this->http->FindSingleNode("td[normalize-space()][2]", $root, true, "#:\s+(.+)#"))
                ->date($dateArr)
                ->terminal($terminalArr, false, true);

            $airline = $this->http->FindSingleNode("following-sibling::tr[3]/td[normalize-space()][1]", $root);

            if (preg_match("#Cia Aérea:\s+(.+)#", $airline, $m)) {
                $s->airline()->name($m[1] === 'Gol' ? 'G3' : $m[1]);
            }

            $flightNumber = $this->http->FindSingleNode("following-sibling::tr[3]/td[normalize-space()][2]//text()[contains(.,'Vôo')]/following::text()[1]", $root, true, "#^\s*(\d+)\s*$#");
            $s->airline()->number($flightNumber);

            $bookingCode = $this->http->FindSingleNode("following-sibling::tr[3]/td[normalize-space()][2]//text()[contains(.,'Classe')]/following::text()[1]", $root, true, "#^\s*([A-Z]{1,2})\s*$#");
            $s->extra()->bookingCode($bookingCode, false, true);

            $seat = $this->http->FindSingleNode("following-sibling::tr[3]/td[normalize-space()][2]//text()[contains(.,'Assento')]/following::text()[1]", $root, true, "#^\s*(\d+[\-\s]*[A-Z])\s*$#");

            if ($seat) {
                $s->extra()->seat($seat);
            }
        }
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^(\d+)\/(\d+)\/(\d{4})\s+(\d+:\d+)$#',
            '#^(\d+)\/(\d+)\/(\d{4})$#',
        ];
        $out = [
            '$3-$2-$1 $4',
            '$3-$2-$1',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function assignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                foreach ($reBody as $re) {
                    if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$re}')]")->length > 0) {
                        //if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("R$", "BRL", $node);
        $node = str_replace("$", "USD", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = str_replace(" ", "", $m['t']);
            $m['t'] = preg_replace("#,(\d{3})$#", '$1', $m['t']);

            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
