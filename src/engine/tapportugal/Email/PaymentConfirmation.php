<?php

namespace AwardWallet\Engine\tapportugal\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class PaymentConfirmation extends \TAccountCheckerExtended
{
    public $mailFiles = "tapportugal/it-125498418.eml, tapportugal/it-64752987.eml";
    public $reBody = [
        'pt' => ['Pagamento Efetuado', 'Detalhes da Viagem'],
        'en' => ['Payment not accomplished', 'Payment confirmed'],
    ];
    public $reSubject = [
        'pt' => [
            'TAP - Confirmação de pagamento c/ cartão de crédito - Reserva:',
            'TAP - Confirmação de pagamento Multibanco - Reserva:',
        ],
        'en' => [
            'TAP - Payment not accomplished - Booking:',
            'TAP - Credit Card payment confirmation - Booking:',
        ],
    ];

    public $lang;
    public $year;
    public $date;

    public static $dict = [
        'pt' => [
            'Confirmation number:' => ['Código de Reserva:', 'Referência da reserva::'],
            'Date Reservation:'    => 'Data/Hora da Reserva:',
            'Title'                => 'Título',
            'First name'           => 'Primeiro Nome',
            'Last name'            => 'Último Nome',
            'Total'                => 'Valor do bilhete:',
            'Payment authorized:'  => 'Pagamento efetuado:',
            'in'                   => 'em',
            'Flight'               => 'Voo',
            'Departure'            => 'Partida',
            'Arrive'               => 'Chegada',
        ],
        'en' => [
            'Confirmation number:' => 'Booking reference:',
            'Date Reservation:'    => 'Date / Time of the reservation:',
            'Title'                => 'Title',
            'First name'           => 'First name',
            'Last name'            => 'Last name',
            //            'Total' => '', // need to translate
            'Payment authorized:' => 'Payment authorized:',
            'in'                  => 'in', // Payment authorized: 97.94 USD in 2021/11/28 01:58:04
            'Flight'              => 'Flight',
            'Departure'           => 'Departure',
            'Arrive'              => 'Arrival',
        ],
    ];

    public function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->travellers($this->http->FindNodes("//tr[{$this->starts($this->t('Title'))} and {$this->contains($this->t('First name'))} and {$this->contains($this->t('Last name'))}]/following-sibling::tr", null, "/^\s*(?:(?:[[:alpha:]]{1,3}\.|Menina)\s+)?(.+)/"), true)
            ->confirmation($this->http->FindSingleNode("//span[{$this->starts($this->t('Confirmation number:'))}]", null, true, "/{$this->opt($this->t('Confirmation number:'))}\s*([A-Z\d]+)/"),
                trim($this->http->FindSingleNode("//span[{$this->starts($this->t('Confirmation number:'))}]", null, true, "/({$this->opt($this->t('Confirmation number:'))})\s*[A-Z\d]+/"), ":"));

        $relativeDate = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Date Reservation:'))}]", null, true, "/\:\s*(.+)/u")));

        if (!empty($relativeDate)) {
            $f->general()
                ->date($relativeDate);
        } else {
            $relativeDate = strtotime($this->http->FindSingleNode("//td[{$this->eq($this->t('Payment authorized:'))}]/following-sibling::td[normalize-space()][1]", null, true,
                "/ {$this->opt($this->t('in'))} (.+)/"));

            if (empty($relativeDate)) {
                $relativeDate = $this->date;
            }
        }

        // Price
        $total = $this->http->FindSingleNode("//td[{$this->eq($this->t('Total'))}]/following-sibling::td[normalize-space()][1]");

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//td[{$this->eq($this->t('Payment authorized:'))}]/following-sibling::td[normalize-space()][1]", null, true,
                "/(.+?) {$this->opt($this->t('in'))} /");
        }

        if (preg_match("#^\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $total, $m)) {
            $f->price()
                ->currency($m['currency'])
                ->total(PriceHelper::parse($m['amount'], $m['currency']));
        }

        $xpath = "//tr[{$this->starts($this->t('Flight'))} and {$this->contains($this->t('Departure'))} and {$this->contains($this->t('Arrive'))}]/following-sibling::tr";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode("./descendant::td[1]", $root, true, "/^([A-Z\d]{2})\d{1,5}$/"))
                ->number($this->http->FindSingleNode("./descendant::td[1]", $root, true, "/^[A-Z\d]{2}(\d{1,5})$/"));

            $s->departure()
                ->name($this->http->FindSingleNode("./descendant::td[2]", $root))
                ->noCode();

            $dateDepNormal = $this->normalizeDate($this->http->FindSingleNode("./descendant::td[4]", $root, true, "/^(\d+\s*\w+)\,/"));
            $timeDep = str_replace("h", ":", $this->http->FindSingleNode("./descendant::td[4]", $root, true, "/\,\s*(\d+h\d+)$/"));
            $dateDep = EmailDateHelper::parseDateRelative($dateDepNormal . ' ' . $this->year . ', ' . $timeDep, $relativeDate);
            $s->departure()->date(strtotime($timeDep, $dateDep));

            $s->arrival()
                ->name($this->http->FindSingleNode("./descendant::td[3]", $root))
                ->noCode();

            $dateArrNormal = $this->normalizeDate($this->http->FindSingleNode("./descendant::td[5]", $root, true, "/^(\d+\s*\w+)\,/"));
            $timeArr = str_replace("h", ":", $this->http->FindSingleNode("./descendant::td[5]", $root, true, "/\,\s*(\d+h\d+)$/"));
            $dateArr = EmailDateHelper::parseDateRelative($dateArrNormal . ' ' . $this->year . ', ' . $timeArr, $relativeDate);

            if (empty($this->http->FindSingleNode("./descendant::td[5]", $root))
                && count($this->http->FindNodes("./descendant::td[normalize-space()]", $root)) === 4
            ) {
                $s->arrival()->noDate();
            } else {
                $s->arrival()->date(strtotime($timeArr, $dateArr));
            }
        }
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->year = date('Y', strtotime($parser->getDate()));
        $this->date = $parser->getDate();
        $body = $parser->getHTMLBody();

        if ($this->assignLang($body) == false) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $this->parseEmail($email);

        $name = explode('\\', __CLASS__);
        $email->setType(end($name) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "flytap.com") !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return $this->AssignLang($body);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject) && isset($headers['subject'])) {
            foreach ($this->reSubject as $lang => $reSubject) {
                foreach ($reSubject as $rs) {
                    if (stripos($headers['subject'], $rs) !== false) {
                        return true;
                    }
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

    private function AssignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (is_array($reBody) && (stripos($body, $reBody[0]) !== false || stripos($body, $reBody[1]) !== false)) {
                    $this->lang = $lang;

                    return true;
                } elseif (is_string($reBody) && stripos($body, $reBody) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;
        $str = str_replace(")", "\)", str_replace("(", "\(", implode("|", $field)));

        return '(?:' . $str . ')';
    }

    private function normalizeDate($str)
    {
        //$this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            "#^(\d+\s*\w+\s*\d{4}\,\s+[\d\:]+)\s+GMT$#", //27 Ago 2020, 18:45 GMT
        ];
        $out = [
            "$1",
        ];

        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str2 = '.print_r( $str,true));

        if (preg_match('/^(\d{1,2})\s+([^\d\W]{3,})\.?$/u', $str, $matches)) { // 28 Feb
            $day = $matches[1];
            $month = $matches[2];
            $year = '';

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }

            return $str;
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }
}
