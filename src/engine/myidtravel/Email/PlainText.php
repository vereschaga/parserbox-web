<?php

namespace AwardWallet\Engine\myidtravel\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class PlainText extends \TAccountChecker
{
    public $mailFiles = "myidtravel/it-136274514.eml, myidtravel/it-137678348.eml, myidtravel/it-137711254.eml, myidtravel/it-1655559.eml, myidtravel/it-1675413.eml, myidtravel/it-451476114.eml, myidtravel/it-463794556.eml, myidtravel/it-464025052.eml, myidtravel/it-464064397.eml, myidtravel/it-5197578.eml, myidtravel/it-5214987.eml, myidtravel/it-5662023.eml, myidtravel/it-5704736.eml, myidtravel/it-5850425.eml";

    public $subjects = [
        'myIDTravel Leisure Booking/Listing Confirmation',
        'Automatic myIDTravel Booking/Listing Cancellation',
        'myIDTravel LEISURE Booking/Listing Cancellation',
        'myIDTravel DUTY Booking/Listing Cancellation',
        'myIDTravel LEISURE-travel confirmation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        'fr' => [
            'bookings have been cancelled' => ['Vous avez annulé le listage'],
            'Traveller Name'               => 'Nom(s)',
            'Booking Reference'            => ['Référence de dossier', 'Référence de dossier (PNR)'],
            'Ticketnumber'                 => ['numéro(s) de billet', 'Numéro de billet'],
            'Flightno'                     => ['N° Vol', 'N  Vol'],
            'Local Time'                   => 'horaires en heure locale',
            'Total Fare'                   => 'Total HT',
            'Taxes'                        => ['Taxes/frais totaux', 'Frais de service myIDTravel'],
            'Total Ticket Price'           => 'Total TTC',
            'Your myIDTravel-Team'         => "L'équipe myIDTravel",
        ],
        'pt' => [
            'bookings have been cancelled' => ['bookings have been cancelled', 'Full refund requested', 'Você cancelou as seguintes reservas/listagens'],
            'Traveller Name'               => 'Nome(s)',
            'Booking Reference'            => 'Código de reserva',
            'Ticketnumber'                 => ['Número de bilhete', 'NÃºmero do bilhete'],
            'Flightno'                     => 'Número de Voo',
            'Local Time'                   => 'Horário local',
            'Total Fare'                   => 'Total Tarifa',
            'Taxes'                        => ['Taxas/tarifas totais', 'Taxa myIDTravel'],
            'Total Ticket Price'           => 'PreÃ§o total do Bilhete',
            'Your myIDTravel-Team'         => ['Sua equipe myIDTravel', 'Your My Private Travel Manager-Team'],
        ],
        'en' => [
            'bookings have been cancelled' => ['bookings have been cancelled', 'Full refund requested', 'You have cancelled the following leisure booking'],
            'Traveller Name'               => ['Traveller Name', 'Name(s)', 'Names', 'Traveller'],
            'Booking Reference'            => 'Booking Reference',
            // 'Ticketnumber' => '',
            'Flightno'   => 'Flightno',
            'Local Time' => 'All times are local',
            // 'Total Fare' => '',
            'Taxes' => ['Total Government taxes', 'myIDTravel Fee'],
            // 'Total Ticket Price' => '',
            'Your myIDTravel-Team' => ['Your myIDTravel-Team', 'Your My Private Travel Manager-Team', 'Yours myIDTravel-Team'],
        ],
        'es' => [
            // 'bookings have been cancelled' => ['bookings have been cancelled', 'Full refund requested', 'You have cancelled the following leisure booking'],
            'Traveller Name'       => ['Nombre(s)'],
            'Booking Reference'    => 'Referencia de reserva',
            'Ticketnumber'         => 'Número de billete',
            'Flightno'             => ['Vuelo N', 'Vuelo N°'],
            'Local Time'           => 'Todos los horarios son locales',
            'Total Fare'           => 'Total Fare',
            'Taxes'                => ['Total impuestos/tasas', 'myIDTravel Fee'],
            'Total Ticket Price'   => 'Total Ticket Price',
            'Your myIDTravel-Team' => ['El equipo de myIDTravel'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $textBody = $parser->getPlainBody();

        if (empty($textBody) || stripos($textBody, '</pre>') !== false
            || stripos($textBody, '</body>') !== false || stripos($textBody, '</html>') !== false
        ) {
            $textBody = text($parser->getHTMLBody());
        }

        $NBSP = chr(194) . chr(160);
        $textBody = str_replace($NBSP, ' ', $textBody);
        $textBody = str_replace('&nbsp;', ' ', $textBody);
        $textBody = preg_replace("/^(?:>+ |>+$)/m", '', $textBody);

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Flightno']) && !empty($dict['Local Time'])
                && $this->containsText($textBody, $dict['Flightno']) === true
                && $this->containsText($textBody, $dict['Local Time']) === true
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $this->ParseEmail($email, $textBody);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@myidtravel.com') !== false) {
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
        $NBSP = chr(194) . chr(160);
        $textBody = empty($parser->getPlainBody()) ? text($parser->getHTMLBody()) : $parser->getPlainBody();
        $textBody = str_replace($NBSP, ' ', $textBody);
        $textBody = str_replace('&nbsp;', ' ', $textBody);
        $textBody = strip_tags($textBody);

        foreach (self::$dictionary as $lang => $dict) {
            if (empty($dict['Your myIDTravel-Team'])
                || $this->containsText($textBody, $dict['Your myIDTravel-Team']) !== true
            ) {
                continue;
            }

            if (!empty($dict['Flightno']) && !empty($dict['Local Time'])
                && $this->containsText($textBody, $dict['Flightno']) === true
                && $this->containsText($textBody, $dict['Local Time']) === true
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]myidtravel\.com$/', $from) > 0;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    protected function ParseEmail(Email $email, $textBody): void
    {
        $f = $email->add()->flight();

        // General
        if (preg_match("/^[>\s]*({$this->opt($this->t('Booking Reference'))})[:\s]\s*([A-Z\d]{5,})\s*$/m", $textBody, $m)) {
            $f->general()->confirmation($m[2], rtrim($m[1], ': '));
        } elseif (preg_match("/\n\s*({$this->opt($this->t('Booking Reference'))}):\s*\n\s*([[:alpha:]] ?)+:/", $textBody, $m)) {
            $f->general()->noConfirmation();
        }

        $travellers = [];
        $travellerText = $this->re("/{$this->opt($this->t('Traveller Name'))}[:\s]\s*(\D+?)\s*(?:\;|{$this->opt($this->t('Booking Reference'))}|\n\s*([[:alpha:]] ?)+:)/u", $textBody);
        $travellerRows = preg_split("/\s*\n+\s*/", $travellerText);

        foreach ($travellerRows as $tRow) {
            if (preg_match("/^[[:alpha:]][-,.\'’[:alpha:]\s]*[[:alpha:]]$/u", $tRow)) {
                $travellers[] = $tRow;
            } else {
                $travellers = [];

                break;
            }
        }

        $travellers = preg_replace("/^\s*([\w\- ]+?)\s*,\s*([\w\- ]+?)\s+(MS|MR)\s*$/", '$2 $1', $travellers);
        $travellers = preg_replace("/^\s*(MS|MR)\s+/", '', $travellers);

        $f->general()->travellers($travellers, true);

        if (preg_match("/({$this->opt($this->t('bookings have been cancelled'))})/", $textBody, $m)) {
            $f->general()->cancelled();
        }

        // Issued
        if (preg_match_all("/{$this->opt($this->t('Ticketnumber'))}:\*?\s*(\d[-\d]+)\s*\n/u", $textBody, $ticketMatches)) {
            $f->issued()
                ->tickets(array_unique($ticketMatches[1]), false);
        }

        if ($f->getCancelled() !== true) {
            $currency = $currencyCode = null;

            if (preg_match_all("/\s+{$this->opt($this->t('Total Ticket Price'))}\s+(.*\d.*?(?:\s+[A-Z]{3})?)\s*\n/", $textBody, $m)) {
                $total = 0.0;

                foreach ($m[1] as $tvalue) {
                    if (preg_match('/^(?<currency>[^\-\d)(]+?)\s*(?<amount>\d[,.‘\'\d\s]*)$/u', $tvalue, $matches)
                        || preg_match('/^(?<amount>\d[,.‘\'\d\s]*?)\s*(?<currency>[^\-\d)(]+?)$/u', $tvalue, $matches)
                    ) {
                        $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                        $currency = $matches['currency'];
                        $total += PriceHelper::parse($matches['amount'], $currencyCode);
                    }
                }

                if (!empty($total)) {
                    $f->price()
                        ->currency($currencyCode)
                        ->total($total);
                }
            }

            if (preg_match_all("/\s+{$this->opt($this->t('Total Fare'))}\s+(.*\d.*?(?:\s+[A-Z]{3})?)\s*\n/", $textBody, $m)) {
                $fare = 0.0;

                foreach ($m[1] as $fvalue) {
                    if (preg_match('/^\s*(?:' . preg_quote($currency, '/') . ')?\s*(?<amount>\d[,.‘\'\d\s]*)$/u', $fvalue, $matches)
                        || preg_match('/^(?<amount>\d[,.‘\'\d\s]*?)\s*(?:' . preg_quote($currency, '/') . ')?\s*$/u', $fvalue, $matches)
                    ) {
                        $fare += PriceHelper::parse($matches['amount'], $currencyCode);
                    }
                }
                $f->price()
                    ->cost($fare);
            }

            if (preg_match_all("/^\s*(?<name>{$this->opt($this->t('Taxes'))})\s+(?<charge>.*\d.*?(?:\s+[A-Z]{3})?)\s*$/m", $textBody, $feeMatches, PREG_SET_ORDER)) {
                foreach ($feeMatches as $fee) {
                    if (preg_match('/^\s*(?:' . preg_quote($currency, '/') . ')?\s*(?<amount>\d[,.‘\'\d ]*?)\s*$/u', $fee['charge'], $m)
                        || preg_match('/^\s*(?<amount>\d[,.‘\'\d ]*?)\s*(?:' . preg_quote($currency, '/') . ')?\s*$/u', $fee['charge'], $m)
                    ) {
                        $f->price()->fee($fee['name'], PriceHelper::parse($m['amount'], $currencyCode));
                    }
                }
            }
        }

        $this->parseSegments($f, $this->re("/\b({$this->opt($this->t('Flightno'))}\s+.+)\s+[* ]*(?:{$this->opt($this->t('Local Time'))}|{$this->opt($this->t('Total Fare'))})/su", $textBody));
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if (empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function parseSegments(Flight $f, $text): void
    {
        $patterns = [
            'time'  => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'cabin' => '(?:Economy|Business)',
        ];

        $segments = $this->split("/(\n\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\d{1,5}\s+\d+\s*\S+\s*\d+\s+[A-Z]{3}\s+{$patterns['time']})/", $text);
        // $this->logger->debug('$segments = '.print_r( $segments,true));

        // AZ609    15 May 2017   JFK    16:20    FCO    06:45+1    listed              Economy
        // BA583    06/11/2023    MXP    08:05    LHR    09:15      ZED (R2 Standby)    LISTING REQUIRED    Economy
        foreach ($segments as $value) {
            $delmtr = '\s*\n\s*';
            $regexp1 = "/([A-Z][A-Z\d]|[A-Z\d][A-Z]) *(\d+){$delmtr}(\d+ ?\S+ ?\d+){$delmtr}([A-Z]{3}){$delmtr}({$patterns['time']}){$delmtr}([A-Z]{3}){$delmtr}({$patterns['time']})(\s*[-+]\d+|){$delmtr}\S.+{$delmtr}(\S.+)\s*(?:\n|$)/u";
            $delmtr = '[\t ]*\t[\t ]*';
            $regexp2 = "/([A-Z][A-Z\d]|[A-Z\d][A-Z]) *(\d+){$delmtr}(\d+ ?\S+ ?\d+){$delmtr}([A-Z]{3}){$delmtr}({$patterns['time']}){$delmtr}([A-Z]{3}){$delmtr}({$patterns['time']})(\s*[-+]\d+|){$delmtr}\S.+{$delmtr}(\S.+)\s*(?:\n|$)/u";
            $delmtr = ' +';
            $regexp3 = "/([A-Z][A-Z\d]|[A-Z\d][A-Z]) *(\d+){$delmtr}(\d+ ?\S+ ?\d+){$delmtr}([A-Z]{3}){$delmtr}({$patterns['time']}){$delmtr}([A-Z]{3}){$delmtr}({$patterns['time']})(\s*[-+]\d+|){$delmtr}\S.+ (\S.+)\s*(?:\n|$)/u";

            if (preg_match($regexp1, $value, $matches) || preg_match($regexp2, $value, $matches) || preg_match($regexp3, $value, $matches)) {
                $s = $f->addSegment();

                $s->airline()
                    ->name($matches[1])
                    ->number($matches[2]);

                $date = $matches[3];

                $s->departure()
                    ->code($matches[4])
                    ->date(strtotime($matches[5], $this->normalizeDate($date)));

                $s->arrival()
                    ->code($matches[6])
                    ->date(strtotime($matches[7], $this->normalizeDate($date)));

                if (!empty(trim($matches[8])) && !empty($s->getArrDate())) {
                    $s->arrival()
                        ->date(strtotime(trim($matches[8]) . " day", $s->getArrDate()));
                }

                $matches[9] = trim(preg_replace("/^.*\S\s{2,}(\S.*)$/", '$1', $matches[9]));

                if (preg_match("/^{$patterns['cabin']}$/i", $matches[9])) {
                    $s->extra()->cabin($matches[9]);
                }
            } elseif ($f->getCancelled() !== true) {
                $f->addSegment();
            }
        }
    }

    private function normalizeDate($date)
    {
        $in = [
            // 01/15/2022
            "/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/",
        ];
        $out = [
            "$2.$1.$3",
        ];
        $date = preg_replace($in, $out, $date);
        //$this->logger->debug('$date = '.print_r( $date,true));

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
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

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
