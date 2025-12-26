<?php

namespace AwardWallet\Engine\swissair\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class AdvanceSeatReservation extends \TAccountChecker
{
    public $mailFiles = "swissair/it-10025232.eml, swissair/it-107220875.eml, swissair/it-110023310.eml, swissair/it-111233239.eml, swissair/it-115923986.eml, swissair/it-122874501-ru.eml, swissair/it-123443061-fr.eml, swissair/it-124834972.eml, swissair/it-220620688-es.eml, swissair/it-61304686.eml, swissair/it-621635420.eml, swissair/it-71705826.eml";

    protected $lang = '';

    protected $langDetectors = [
        'es' => ['Referencia de la reserva:'],
        'fr' => ['Référence de réservation:'],
        'ru' => ['Номер бронирования:'],
        'de' => ['Buchungsreferenz:'],
        'pt' => ['Referência da reserva:'],
        'en' => ['Booking reference:'],
        'it' => ['Codice di prenotazione:'],
    ];

    protected static $dict = [
        'es' => [ // it-220620688-es.eml
            'Grand total'                => 'Suma total',
            'Booking reference:'         => 'Referencia de la reserva:',
            'Hello '                     => ['Estimado '],
            // 'Passenger' => '', // Dear Passenger,
            'All names in this booking:' => 'Todos los nombres en esta reserva:',
            'Operated by'                => 'Operado por',
            // 'on' => '',
            // 'meal' => '',
            // 'Seat' => '',
        ],
        'fr' => [ // it-123443061-fr.eml
            'Grand total'                => 'Montant total',
            'Booking reference:'         => 'Référence de réservation:',
            'Hello '                     => ['Chère ', 'Bonjour '],
            'Passenger'                  => 'cliente', // Dear Passenger,
            'All names in this booking:' => 'Tous les noms dans cette réservation:',
            // 'Your PartnerPlusBenefit number:' => '',
            'Operated by'                => 'Opéré par',
            'on'                         => 'au nom de',
            // 'meal' => '',
            'Seat' => 'Siège avec place supplémentaire pour les jambes',
        ],
        'ru' => [ // it-122874501-ru.eml
            // 'Grand total' => '',
            'Booking reference:'         => 'Номер бронирования:',
            'Hello '                     => ['Уважаемый '],
            'Passenger'                  => 'клиент авиакомпании', // Dear Passenger,
            'All names in this booking:' => 'Все имена в бронировании:',
            // 'Your PartnerPlusBenefit number:' => '',
            'Operated by'                => 'управляется',
            // 'on' => '',
            // 'meal' => '',
            'Seat' => ['Стандартное место', 'Предпочтительная зона', 'Pre-reserved Seat'],
        ],
        'de' => [ // it-61304686.eml, it-71705826.eml
            'Grand total'                => 'Gesamtbetrag',
            'Booking reference:'         => 'Buchungsreferenz:',
            'Hello '                     => ['Herr ', 'Grüezi Herr ', 'Grüezi Frau '],
            // 'Passenger' => '', // Dear Passenger,
            'All names in this booking:' => 'Alle Namen in dieser Buchung:',
            // 'Your PartnerPlusBenefit number:' => '',
            'Operated by'                => 'Durchgeführt von',
            'on'                         => 'im Auftrag',
            // 'meal' => '',
            'Seat' => 'Sitzplatz mit extra Beinfreiheit',
        ],
        'pt' => [ // it-110023310.eml
            'Grand total'                => 'Total final',
            'Booking reference:'         => 'Referência da reserva:',
            'Hello '                     => ['Prezado/a ', 'Bom dia '],
            // 'Passenger' => '', // Dear Passenger,
            'All names in this booking:' => 'Todos os nomes neste reserva:',
            // 'Your PartnerPlusBenefit number:' => '',
            'Operated by'                => 'Operado por',
            'on'                         => 'em nome de',
            'meal'                       => 'refeição',
            'Seat'                       => 'Zona preferencial',
        ],
        'it' => [
            'Grand total'                => 'Totale',
            'Booking reference:'         => 'Codice di prenotazione:',
            'Hello '                     => ['Buongiorno '],
            // 'Passenger' => '', // Dear Passenger,
            'All names in this booking:' => 'Tutti i nomi in questa prenotazione:',
            // 'Your PartnerPlusBenefit number:' => '',
            'Operated by'                => 'Operato da',
            'on'                         => 'per incarico di',
            //            'meal'               => 'refeição',
            // 'Seat' => '',
        ],
        'en' => [
            'Operated by' => ['Operated by', 'Flight operated by'],
            // 'Hello ' => ['Hello ', 'Dear '],
            // 'Passenger' => '', // Dear Passenger,
            // 'Your PartnerPlusBenefit number:' => '',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Swiss International Air') !== false
            || stripos($from, '@noti.swiss.com') !== false
            || stripos($from, '@notifications.swiss.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return stripos($headers['subject'], 'Advance seat reservation') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".swiss.com")]')->length === 0) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if ($this->assignLang() === false) {
            return $email;
        }

        $this->parseEmail($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    protected function parseEmail(Email $email): void
    {
        $xpathTime = 'contains(translate(normalize-space(),"0123456789：","dddddddddd:"),"d:dd")';
        $patterns = [
            'code'          => '/^([A-Z]{3})$/',
            'date'          => '/(\d{1,2}.+?\d{4})/',
            'time'          => '\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?',
            'travellerName' => '[[:alpha:]][-.\'\/[:alpha:] ]*[[:alpha:]]', // SALZMAN/FLORIAN MR
        ];

        $f = $email->add()->flight();

        $totalPrice = $this->http->FindSingleNode("//text()[contains(normalize-space(),\"from only\")]", null, true, "/from only\s*(.*\d.*)/i");

        if ($totalPrice === null) {
            // it-71705826.eml
            $totalPrice = $this->http->FindSingleNode("//tr[ not(.//tr) and *[normalize-space()][1][{$this->eq($this->t('Gesamtbetrag'))}] ]/*[normalize-space()][2]", null, true, "/.*\d.*/");
        }

        if ($totalPrice === null) {
            // it-71705826.eml
            $totalPrice = $this->http->FindSingleNode("//tr[ not(.//tr) and *[normalize-space()][1][{$this->eq($this->t('Grand total'))}] ]/*[normalize-space()][2]", null, true, "/.*\d.*/");
        }

        if (preg_match('/^(?<currency>[A-Z]{3})[ ]*(?<amount>\d[,.\'\d]*)$/', $totalPrice, $m)) {
            // CHF 1084.30
            $f->price()
                ->currency($m['currency'])
                ->total(PriceHelper::parse($m['amount'], $m['currency']));
        }

        $travellers = [];

        // it-71705826.eml
        $travellerNamesHtml = $this->http->FindHTMLByXpath("//tr[not(.//tr) and {$this->eq($this->t('All names in this booking:'))}]/following-sibling::tr[normalize-space()]");
        $travellerNamesText = $this->htmlToText($travellerNamesHtml);
        $accountsText = preg_replace("/^[\s\S]+{$this->opt($this->t('Your PartnerPlusBenefit number:'))}\s*([\s\S]+?)\s*$/", '$1', $travellerNamesText);
        $travellerNamesText = preg_replace("/^\s*([\s\S]+?)\s+{$this->opt($this->t('Your PartnerPlusBenefit number:'))}\s*[\s\S]+/", '$1', $travellerNamesText);

        $travellerNames = preg_split('/\s*\n\s*/', $travellerNamesText);

        foreach ($travellerNames as $tName) {
            if (preg_match("/^{$patterns['travellerName']}$/u", $tName)) {
                $travellers[] = $tName;
            } else {
                $travellers = [];

                break;
            }
        }

        $accounts = [];
        $accountsRows = preg_split('/\s*\n\s*/', $accountsText);

        foreach ($accountsRows as $acc) {
            if (preg_match("/^\s*([A-Z\d]{5,})\s*$/u", $acc)) {
                $accounts[] = $acc;
            } else {
                $accounts = [];

                break;
            }
        }

        if (!empty($accounts)) {
            $f->program()
                ->accounts($accounts, false);
        }

        // it-10025232.eml
        if (count($travellers) === 0
            && ($passenger = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello '))}][not(contains(normalize-space(), 'cliente'))]", null, true, "/^{$this->opt($this->t('Hello '))}\s*({$patterns['travellerName']})(?:\s*[,.:;!?]|$)/u"))
        ) {
            if (!preg_match("/^\s*{$this->opt($this->t('Passenger'))}\s*$/iu", $passenger)) {
                $travellers[] = $passenger;
            }
        }

        if (count($travellers) === 0) {
            $travellers = array_filter(array_unique($this->http->FindNodes("//text()[{$this->starts($this->t('Operated by'))}]/following::text()[contains(normalize-space(), '-')]/preceding::text()[normalize-space()][1][contains(normalize-space(), ':')]", null, "/^(\D+)\s*\:$/")));
        }

        if (count($travellers)) {
            // example: PELECHANO GARCIA/VICENTE JOSE MRS DR

            $travellers = preg_replace(["/ (ADT|DR|CHD|PROF|INF)\s*$/", "/ (?:MRS|MR|MS|MRSDR|MISS|MRS|MRDR|MSTR)\s*$/"], '', $travellers);
            $travellers = preg_replace("/^\s*(.+?)\s*\\/\s*(.+?)\s*$/", '$2 $1', $travellers);
            $f->general()->travellers($travellers);
        }

        $confirmationRow = $this->http->FindSingleNode("//tr[{$this->contains($this->t('Booking reference:'))} and not(.//tr)]");

        if (preg_match("/^({$this->opt($this->t('Booking reference:'))})\s*([A-Z\d]{5,})$/", $confirmationRow, $m)) {
            $f->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        $segments = $this->http->XPath->query('//td[string-length(normalize-space(.))=3]/following-sibling::td[string-length(normalize-space(.))=3]/ancestor::tr[1]');

        foreach ($segments as $segment) {
            $xpathFragment2 = './ancestor::tr[1]/following-sibling::tr[string-length(normalize-space(.))>1]';
            $xpathFragment3 = $xpathFragment2 . '[1]/descendant::tr[ ./td[3] ][1]/td[string-length(normalize-space(.))>1]';
            $dateDep = $this->normalizeDate($this->http->FindSingleNode($xpathFragment3 . '[1]', $segment, true, $patterns['date']));
            $dateArr = $this->normalizeDate($this->http->FindSingleNode($xpathFragment3 . '[last()]', $segment, true, $patterns['date']));

            // it-61304686.eml
            $xpathInsideSegmentImg = "descendant::img[contains(@src,'/arrow_white.')]";
            $segmentsInside = $this->http->XPath->query("ancestor::tr[ following-sibling::tr[normalize-space()] ][1]/following-sibling::tr[ *[1][{$xpathInsideSegmentImg} or descendant::text()[{$this->starts($this->t('Operated by'))}]] ][1]/descendant::tr[*[3] and *[2][{$xpathInsideSegmentImg} or {$xpathTime}] and descendant::tr]", $segment);

            if ($segmentsInside->length) {
                foreach ($segmentsInside as $key => $sInside) {
                    $s = $f->addSegment();
                    $segmentInsideText = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $sInside));

                    $this->logger->debug("/^(?:\s*(?<dateDep>[^\n]{6,})\s+)?(?<depTime>{$patterns['time']})\s+(?<depCode>[A-Z]{3})\s+(?<arrTime>{$patterns['time']})\s+(?<arrCode>[A-Z]{3})\s*(?:[+][ ]*(?<overnight>\d{1,3}))?\s+{$this->opt($this->t('Operated by'))}[ ]*(?<operator>.*?)\s{4,}(?<aircraft>[-\w\s)(]+?)(\n\D+\:)?[ ]*$/su");

                    if (preg_match("/^(?:\s*(?<dateDep>[^\n]{6,})\s+)?(?<depTime>{$patterns['time']})\s+(?<depCode>[A-Z]{3})\s+(?<arrTime>{$patterns['time']})\s+(?<arrCode>[A-Z]{3})\s*(?:[+][ ]*(?<overnight>\d{1,3}))?\s+{$this->opt($this->t('Operated by'))}[ ]*(?<operator>.*?)\s{4,}(?<aircraft>[-\w\s)(]+?)(\n\D+\:)?[ ]*$/su", $segmentInsideText, $m)
                        || preg_match("/^(?:\s*(?<dateDep>[^\n]{6,})\s+)?(?<depTime>{$patterns['time']})\s+(?<depCode>[A-Z]{3})\s+(?<arrTime>{$patterns['time']})\s+(?<arrCode>[A-Z]{3})\s*(?:[+][ ]*(?<overnight>\d{1,3}))?\s+{$this->opt($this->t('Operated by'))}[ ]*(?<operator>.*?)\n(?<aircraft>[-\w\s)(]+?)[ ]*$/su", $segmentInsideText, $m)
                        || preg_match("/^(?:\s*(?<dateDep>[^\n]{6,})\s+)?(?<depTime>{$patterns['time']})\s+(?<depCode>[A-Z]{3})\s+(?<arrTime>{$patterns['time']})\s+(?<arrCode>[A-Z]{3})\s*(?:[+][ ]*(?<overnight>\d{1,3}))?\s+{$this->opt($this->t('Operated by'))}[ ]*(?<operator>.*?)\s{3,}(?<aircraft>[-\w\s)(]+?)(\n\D+\:)?[ ]*$/su", $segmentInsideText, $m)
                    ) {
                        // 10:20 ZRH  11:40 TXL  Durchgeführt von Swiss International Air Lines     Airbus A220-300
                        $s->departure()->code($m['depCode']);
                        $s->arrival()->code($m['arrCode']);
                        // $this->logger->debug('OPERATOR: ' . $m['operator']);
                        $s->airline()->operator(preg_replace("/\s(?:{$this->opt(['For mileage', 'This is a', 'Für Meilengutschriften', 'Dies ist ein', 'Para obtener millas', 'Este é um voo'])}|{$this->opt($this->t('on'))}).+/s", "", $m['operator']));

                        if (!empty($m['dateDep'])) {
                            $dateDep = $this->normalizeDate($m['dateDep']);
                            $s->departure()->strict();
                        }

                        if ($dateDep) {
                            $s->departure()->date2($dateDep . ' ' . $m['depTime']);
                        }

                        if ($dateDep) {
                            $s->arrival()->date2($dateDep . ' ' . $m['arrTime']);
                        }

                        if (!empty($m['overnight'])) {
                            $s->arrival()->date(strtotime("+{$m['overnight']} days", $s->getArrDate()));
                        }

                        $s->extra()->aircraft($m['aircraft']);
                        $flightInfoHtml = $this->http->FindHTMLByXpath("preceding::tr[not(.//tr) and normalize-space()][1]", null, $sInside);
                        $flightInfo = $this->htmlToText($flightInfoHtml);

                        if (preg_match('/(?:^|>)[ ]*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<number>\d+)[ ]*$(?:\s+^[ ]*(?i)(?<status>Confirmed|Confirmé|Cancelled|Time Change|New Time)[ ]*$)?/m', $flightInfo, $m)) {
                            /*
                                </image006.jpg>LX 964
                                Confirmed
                            */
                            $s->airline()
                                ->name($m['name'])
                                ->number($m['number']);

                            if ($m['status']) {
                                $s->extra()->status($m['status']);
                            }
                        }
                    }

                    // cabin
                    // bookingCode
                    $cabins = $bookingCodes = [];
                    $followingRows = $this->http->XPath->query("following-sibling::tr[normalize-space()]", $sInside);

                    foreach ($followingRows as $fRow) {
                        if (!empty($segmentsInside[$key + 1]) && $segmentsInside[$key + 1] === $fRow) {
                            break;
                        }
                        $currentRow = implode(' ', $this->http->FindNodes('descendant::text()[normalize-space()]', $fRow));
                        $currentRow = preg_replace("/^[:\s]*(.+)$/", '$1', $currentRow);

                        if ($this->http->FindSingleNode("./preceding-sibling::tr[normalize-space()][1]", $fRow, true, "/^{$patterns['travellerName']}[ ]*[:]+$/u") !== null
                            && (preg_match("/^(?<cabin>\w[-\w\s]+?)[ ]+-[ ]+(?<bookingCode>[A-Z]{1,2})\D+(?i){$this->opt($this->t('Seat'))}(?-i)\s*(?<seat>\d+[A-Z])[*\s]*(?<meal>\D*{$this->opt($this->t('meal'))}\D*)$/u", $currentRow, $m)
                                || preg_match("/^(?<cabin>\w[-\w\s]+?)[ ]+-[ ]+(?<bookingCode>[A-Z]{1,2})\D+(?i){$this->opt($this->t('Seat'))}[*\s]*(?<meal>\D*{$this->opt($this->t('meal'))}\D*)$/u", $currentRow, $m)
                                || preg_match("/^(?<cabin>\w[-\w\s]+?)[ ]+-[ ]+(?<bookingCode>[A-Z]{1,2})\D+(?i){$this->opt($this->t('Seat'))}(?-i)\s*(?<seat>\d+[A-Z])?[*\s]*$/", $currentRow, $m)
                                || preg_match("/^(?<cabin>\D{2,}?)[ ]+-[ ]+(?<bookingCode>[A-Z]{1,2})\b\s*(?i)(?<meal>\D*{$this->opt($this->t('meal'))}\D*)?$/u", $currentRow, $m)
                                || preg_match("/^(?<cabin>[[:alpha:]][-[:alpha:]\s]+)$/u", $currentRow, $m)
                            )
                        ) {
                            // Economy Light - U    |    Economy Lx-Light - V    |    Economy
                            $cabins[] = ucwords(strtolower($m['cabin']));

                            if (!empty($m['bookingCode'])) {
                                $bookingCodes[] = $m['bookingCode'];
                            }

                            if (!empty($m['meal'])) {
                                $s->extra()
                                    ->meal($m['meal']);
                            }

                            if (!empty($m['seat'])) {
                                $s->extra()
                                    ->seat($m['seat']);
                            }
                        }
                    }

                    if (count(array_unique($cabins)) === 1) {
                        $s->extra()->cabin(array_shift($cabins));
                    }

                    if (count(array_unique($bookingCodes)) === 1) {
                        $s->extra()->bookingCode(array_shift($bookingCodes));
                    }
                }
            } else {
                $xpathFragment1 = './td[string-length(normalize-space(.))=3]';
                $s = $f->addSegment();

                $s->departure()->code($this->http->FindSingleNode($xpathFragment1 . '[1]', $segment, true, $patterns['code']));
                $s->arrival()->code($this->http->FindSingleNode($xpathFragment1 . '[2]', $segment, true, $patterns['code']));

                $timeDep = $this->http->FindSingleNode($xpathFragment3 . '[1]', $segment, true, "/{$patterns['time']}/");

                if ($dateDep && $timeDep) {
                    $s->departure()->date2($dateDep . ' ' . $timeDep);
                }

                $timeArr = $this->http->FindSingleNode($xpathFragment3 . '[last()]', $segment, true, "/{$patterns['time']}/");

                if ($dateArr && $timeArr) {
                    $s->arrival()->date2($dateArr . ' ' . $timeArr);
                }

                $thirdLine = $this->http->FindSingleNode($xpathFragment2 . '[2]', $segment);

                // Flight number: LX8
                if (preg_match('/[Ff]light\s*[Nn]umber\s*:\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])(\d+)/', $thirdLine, $matches)) {
                    $s->airline()->name($matches[1]);
                    $s->airline()->number($matches[2]);
                }

                // Seat: 7A
                if (preg_match('/Seat\s*:\s*(\d{1,2}[A-Z])/', $thirdLine, $matches)) {
                    $s->extra()->seat($matches[1]);
                }
            }
        }
    }

    protected function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//*[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $text, $m)) {
            // 21.12.2017
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/(?:^|\D)(\d{1,2})(?:\s+de)?\s+([[:alpha:]]{3,})[. ]*(?:\s+de)?\s+(\d{4})$/u', $text, $m)) {
            // 05 Sep 2020    |    27 nov. 2021    |    суббота 26 сентября 2020
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
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

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ',
                array_map(function ($s) use ($node) {
                    return 'contains(normalize-space(' . $node . '),"' . $s . '")';
                }, $field))
            . ')';
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

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . "),'" . $s . "')";
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
