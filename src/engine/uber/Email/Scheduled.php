<?php

namespace AwardWallet\Engine\uber\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class Scheduled extends \TAccountChecker
{
    public $mailFiles = "uber/it-137581575.eml, uber/it-140894288.eml, uber/it-33138364.eml, uber/it-33231084.eml, uber/it-33723646.eml, uber/it-401801940.eml, uber/it-407407520.eml, uber/it-409373539.eml, uber/it-43811172.eml";

    public $reBody = [
        'en' => ['Uber ride details', 'Thanks for scheduling your', 'Your reservation details', 'Uber Reserve ride details'],
        'fr' => ["Merci d'avoir planifié votre", 'Détails de votre réservation'],
        'de' => ['Vielen Dank, dass du deine', 'Deine Reservierungsdetails'],
        'es' => ['Gracias por programar tu viaje', 'Detalles de la reserva'],
        'it' => ['Dettagli prenotazione'],
        'ru' => ['Информация о бронировании'],
    ];
    public $reSubject = [
        'Your Uber ride has been scheduled', //en
        'Scheduled ride confirmation',
        'Your reservation confirmation',
        'Votre commande Uber est planifiée', //fr
        'Confirmation de votre réservation', //fr
        // de
        'Deine Fahrt wurde eingeplant',
        'Deine Reservierungsbestätigung',
        // es
        'Se programó tu viaje Uber', //es
        'Tu confirmación de reserva',
        // it
        'Conferma della prenotazione',
        // ru
        'Подтверждение бронирования',
    ];
    public $year = 0;
    public $lang = '';
    public static $dict = [
        'en' => [
            'Pickup date' => ['Pickup date', 'Pick-up date'],
            'Pickup time' => ['Pickup time', 'Pick-up time'],
            //            'Fare' => '',
            'Uber ride details' => ['Uber ride details', 'Your reservation details', 'Uber Reserve ride details'],
            'statusPhrases'     => ['Your ride is'],
            'statusVariants'    => ['booked'],
            'wordsNotAddress'   => ['Departures', 'Unnamed Road', 'Airport', 'Back Pickup Zone'],
        ],
        'fr' => [
            'Pickup date' => 'Date de la prise en charge',
            'Pickup time' => 'Heure de la prise en charge',
            //            'Fare' => '',
            'Uber ride details' => 'Détails de votre réservation',
            // 'statusPhrases' => [''],
            // 'statusVariants' => [''],
        ],
        'de' => [
            'Pickup date' => 'Abholdatum',
            'Pickup time' => 'Abholzeit',
            //            'Fare' => '',
            'Uber ride details' => 'Deine Reservierungsdetails',
            // 'statusPhrases' => [''],
            // 'statusVariants' => [''],
        ],
        'es' => [
            'Pickup date' => 'Fecha del viaje',
            'Pickup time' => 'Horario del viaje',
            //            'Fare' => '',
            'Uber ride details' => 'Detalles de la reserva',
            // 'statusPhrases' => [''],
            // 'statusVariants' => [''],
        ],
        'it' => [
            // 'Pickup date' => 'Fecha del viaje',
            // 'Pickup time' => 'Horario del viaje',
            //            'Fare' => '',
            'Uber ride details' => 'Dettagli prenotazione',
            // 'statusPhrases' => [''],
            // 'statusVariants' => [''],
        ],
        'ru' => [
            // 'Pickup date' => 'Fecha del viaje',
            // 'Pickup time' => 'Horario del viaje',
            //            'Fare' => '',
            'Uber ride details' => 'Информация о бронировании',
            // 'statusPhrases' => [''],
            // 'statusVariants' => [''],
        ],
    ];

    private $patterns = [
        'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?',
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->assignLang();
        $this->year = getdate(strtotime($parser->getHeader('date')))['year'];
        $xpath2 = "//tr[td[1][not(normalize-space()) and .//img] and td[2][count(.//td[not(.//td)][normalize-space()]) = 3 and .//td[not(.//td)][normalize-space()][contains(translate(., '0123456789', 'dddddddddd'), 'd:dd')]] ]";

        if ($this->http->XPath->query($xpath2)->length > 0) {
            $its = $this->parseEmail2($xpath2);
        } else {
            $its = $this->parseEmail();
        }
        $class = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => end($class) . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'uber-statis')] | //a[{$this->contains(['.uber.com/', 'email.uber.com'], '@href')}] | //*[{$this->contains(['Uber.com', 'Uber B.V.', 'Uber Technologies', 'Uber do Brasil Tecnologia'])}]")->length === 0) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && !preg_match('/\bUber\b/i', $headers['subject'])
        ) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers['subject'], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@uber.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict) * 2;
    }

    private function parseEmail(): array
    {
        // examples: it-33138364.eml
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = CONFNO_UNKNOWN;
        $it['TripCategory'] = TRIP_CATEGORY_TRANSFER;

        if ($tot = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Fare'))}]/following::text()[string-length(normalize-space(.))>2][1]")) {
            if (preg_match("/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/", $tot, $matches)) {
                // $34.21

                switch ($matches['currency']) {
                    case "$":
                        $currency = 'USD';

                        break;

                    case "€":
                        $currency = 'EUR';

                        break;

                    case "£":
                        $currency = 'GBP';

                        break;

                    case "R":
                        $currency = 'ZAR';

                        break;

                    case "zł":
                        $currency = 'PLN';

                        break;

                    default:
                        $currency = $matches['currency'];
                }

                $it['Currency'] = $currency;
                $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                $it['TotalCharge'] = PriceHelper::parse($matches['amount'], $currencyCode);
            }
        }

        $segment = [];
        $dateValue = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Pickup date'))}]/following::text()[string-length(normalize-space())>2][1]");
        $date = strtotime($this->normalizeDate($dateValue));
        $timePickup = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Pickup time'))}]/following::text()[string-length(normalize-space())>2][1]", null, true, "/{$this->patterns['time']}$/i");

        if ($date && $timePickup) {
            $segment['DepDate'] = strtotime($timePickup, $date);
            $segment['ArrDate'] = MISSING_DATE;
        }

        if (($root = $this->http->XPath->query("//text()[{$this->starts($this->t('Pickup time'))}]/ancestor::tr[1]/following::tr[string-length(normalize-space(.))>3][position()<4][descendant::table[count(descendant::text()[normalize-space()!=''])=2]][1]"))->length === 1) {
            $root = $root->item(0);

            $depName = $this->http->FindSingleNode("descendant::text()[normalize-space()][1]", $root);
            $arrName = $this->http->FindSingleNode("descendant::text()[normalize-space()][2]", $root);

            // for google, to help find correct address
            if ($this->http->XPath->query("//node()[{$this->contains('San Francisco, CA')}]")->length > 0) {
                $region = ', US';
            } else {
                $region = '';
            }

            $segment['DepName'] = $depName . $region;
            $segment['ArrName'] = $arrName . $region;
        }

        if (count(array_filter(array_map('trim', $segment))) === 4) {
            $segment['DepCode'] = $segment['ArrCode'] = TRIP_CODE_UNKNOWN;
            $it['TripSegments'] = [$segment];
        } else {
            $this->logger->debug('Invalid array parsed');
        }

        return [$it];
    }

    private function parseEmail2($xpath): array
    {
        // examples: it-140894288.eml
        $this->logger->debug('Type 2');
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = CONFNO_UNKNOWN;
        $it['TripCategory'] = TRIP_CATEGORY_TRANSFER;

        $status = $this->http->FindSingleNode("//h2[{$this->starts($this->t('statusPhrases'))}]", null, true, "/{$this->opt($this->t('statusPhrases'))}\s+({$this->opt($this->t('statusVariants'))})(?:\s*[,.:;!?]|$)/");

        if ($status) {
            $it['Status'] = $status;
        }

        if ($tot = $this->http->FindSingleNode($xpath . "/following::text()[normalize-space(.)][1]")) {
            if (preg_match("/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/", $tot, $matches)) {
                // $325.85

                switch ($matches['currency']) {
                    case "$":
                        $currency = 'USD';

                        break;

                    case "€":
                        $currency = 'EUR';

                        break;

                    case "£":
                        $currency = 'GBP';

                        break;

                    case "R":
                        $currency = 'ZAR';

                        break;

                    case "zł":
                        $currency = 'PLN';

                        break;

                    case "₴":
                        $currency = 'UAH';

                        break;

                    default:
                        $currency = $matches['currency'];
                }

                $it['Currency'] = $currency;
                $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                $it['TotalCharge'] = PriceHelper::parse($matches['amount'], $currencyCode);
            }
        }

        $segment = [];
        $dateDep = 0;
        $dateTimeValue = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Uber ride details'))}]/following::text()[string-length(normalize-space())>2][1]");

        if (preg_match("/^(?<wday>[-[:alpha:]]+)\s*,\s*(?<date>[[:alpha:]]+\s+\d{1,2}|\d{1,2}\s+[[:alpha:]]+)\s*,\s*{$this->patterns['time']}\s+-\s+(?<timeDep>{$this->patterns['time']})/u", $dateTimeValue, $m)) {
            // Friday, Feb 18, 11:45 AM - 11:55 AM
            $weekDateNumber = WeekTranslate::number1($m['wday']);
            $date = EmailDateHelper::parseDateUsingWeekDay($this->normalizeDate($m['date']), $weekDateNumber);
            $dateDep = strtotime($m['timeDep'], $date);
        } elseif (preg_match("/^(?<wday>[-[:alpha:]]+)\s*,\s*(?<date>[[:alpha:]]+\s+\d{1,2}|\d{1,2}\s+[[:alpha:]]+)\s*,\s*(?<timeDep>{$this->patterns['time']})\s*$/u", $dateTimeValue, $m)) {
            // Friday, Feb 18, 11:45 AM
            $weekDateNumber = WeekTranslate::number1($m['wday']);
            $date = EmailDateHelper::parseDateUsingWeekDay($this->normalizeDate($m['date']), $weekDateNumber);
            $dateDep = strtotime($m['timeDep'], $date);
        } elseif (preg_match("/^(?<wday>[-[:alpha:]]+)\s*,\s*(?<date>[[:alpha:]]+\s+\d{1,2}|\d{1,2}\s+[[:alpha:]]+)\s*$/u", $dateTimeValue, $m)) {
            // Uber Reserve ride details
            // Thursday, Jun 15
            //
            // Brisbane Airport (BNE)
            //   VA 347 lands at 8:20pm
            $weekDateNumber = WeekTranslate::number1($m['wday']);
            $date = EmailDateHelper::parseDateUsingWeekDay($this->normalizeDate($m['date']), $weekDateNumber);

            $time = $this->http->FindSingleNode('(' . $xpath . "//td[not(.//td)][normalize-space()])[2][{$this->contains($this->t('lands at'))}]",
                null, true, "/{$this->opt($this->t('lands at'))}\s*({$this->patterns['time']})\s*$/");

            if (!empty($time) && !empty($date)) {
                $dateDep = strtotime($time, $date);
            }
        }

        if (!empty($dateDep)) {
            $segment['DepDate'] = $dateDep;
            $segment['ArrDate'] = MISSING_DATE;
        }

        $segment['DepName'] = $this->http->FindSingleNode('(' . $xpath . "//td[not(.//td)][normalize-space()])[1]");

        $segment['ArrName'] = $this->http->FindSingleNode('(' . $xpath . "//td[not(.//td)][normalize-space()])[last()]");

        if (count(array_filter(array_map('trim', $segment))) === 4) {
            if (
                preg_match("/ Airport\s*\(\s*(?<code>[A-Z]{3})\s*\)\s*$/", $segment['DepName'], $m)
                || preg_match("/^\s*Aeropuerto\s+.+\(\s*(?<code>[A-Z]{3})\s*\)\s*$/", $segment['DepName'], $m)
            ) {
                $segment['DepCode'] = $m['code'];
            } else {
                $segment['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            if (
                preg_match("/ Airport\s*\(\s*(?<code>[A-Z]{3})\s*\)\s*$/", $segment['ArrName'], $m)
                || preg_match("/^\s*Aeropuerto\s+.+\(\s*(?<code>[A-Z]{3})\s*\)\s*$/", $segment['ArrName'], $m)
            ) {
                $segment['ArrCode'] = $m['code'];
            } else {
                $segment['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            if (
                preg_match("/^\s*(?:T\d )?[A-Z][a-z]+ Terminal\s*$/", $segment['DepName'], $m)
                || preg_match("/^\s*(?:T\d )?[A-Z][a-z]+ Terminal\s*$/", $segment['ArrName'], $m)
                || preg_match("/^\s*Terminal (?:[A-Z][a-z]*|\d[A-Z]?)\s*$/", $segment['DepName'], $m)
                || preg_match("/^\s*Terminal (?:[A-Z][a-z]*|\d[A-Z]?)\s*$/", $segment['ArrName'], $m)
            ) {
                // T2 Domestic Terminal
                // Terminal C
                // for junk
                $segment['DepDate'] = MISSING_DATE;
            }

            if (
                preg_match("/^\s*{$this->opt($this->t('wordsNotAddress'))}\s*$/", $segment['DepName'], $m)
                || preg_match("/^\s*{$this->opt($this->t('wordsNotAddress'))}\s*$/", $segment['ArrName'], $m)
            ) {
                // for junk
                $segment['DepDate'] = MISSING_DATE;
            }
        }
        $it['TripSegments'] = [$segment];

        return [$it];
    }

    private function normalizeDate(?string $str): string
    {
        $in = [
            // Monday, February 4, 2019
            '/^[-[:alpha:]]+\s*,\s*([[:alpha:]]+)\s+(\d{1,2})\s*,\s*(\d{4})$/u',
            // Jan 27
            '/^([[:alpha:]]+)\s+(\d{1,2})$/u',
        ];
        $out = [
            '$2 $1 $3',
            "$2 $1 $this->year",
        ];

        return $this->dateStringToEnglish(preg_replace($in, $out, $str));
    }

    private function dateStringToEnglish(string $date): string
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody(): bool
    {
        if (!isset($this->reBody)) {
            return false;
        }

        foreach ($this->reBody as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["Pickup date"], $words["Pickup time"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Pickup date'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Pickup time'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }

            if (isset($words["Uber ride details"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Uber ride details'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
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
