<?php

namespace AwardWallet\Engine\klm\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationConfirmed extends \TAccountChecker
{
    public $mailFiles = "klm/it-158711361.eml, klm/it-159207450.eml, klm/it-38015825.eml, klm/it-38053519.eml, klm/it-38144962.eml, klm/it-41665949.eml, klm/it-54587893.eml, klm/it-59016791.eml, klm/it-757396591.eml, klm/it-96478080.eml, klm/it-99784038.eml"; // +2 bcdtravel(html)[fr,es]

    public $reFrom = ["klm@klm-info.com"];
    public $reBody = [
        'fr' => 'Vérifiez vos données de vol',
        'pt' => ['Confira os detalhes do seu voo', 'Verifique a sua reserva com atenção', 'Esta é a confirmação de reserva para a sua viagem'],
        'nl' => 'oleer uw vluchtgegevens',
        'es' => 'Revise los detalles de su vuelo',
        'en' => ['Check your flight details', "We're saving your seat"],
        'it' => 'Ricontrolli la sua prenotazione',
        'pl' => 'Prosimy sprawdzić informacje na temat lotu',
        'de' => 'Ihre Flugdaten überprüfen',
        'ko' => '귀하의 예약을 재확인하십시오',
    ];
    public $reSubject = [
        'fr' => 'Confirmation de réservation - ',
        'pt' => 'Reserva confirmada - ',
        'nl' => 'Reservering bevestigd - ',
        'es' => 'Reserva confirmada - ',
        'en' => 'Reservation confirmed - ',
        'it' => 'Prenotazione confermata - ',
        'pl' => 'Rezerwacja potwierdzona -',
        'de' => 'Reservierung bestätigt -',
        'ko' => '예약 확정 -',
    ];
    public $lang = '';
    public static $dict = [
        'pl' => [ // it-96478080.eml
            'Booking code:'               => 'Kod rezerwacji:',
            'Check passenger information' => 'Prosimy sprawdzić dane pasażera',
            'Flying Blue members earn'    => 'Członkowie Flying Blue zdobywają',
            //don't describe everyone fees. only not included in 'Total tax amount all passengers'. check sum
            //            'Fees'                            => '',
            //            'Total tax amount all passengers' => '',
            //            'Total price:' => '',
            //            'Price per adult'                 => '',
            //            'Number of adults'                => '',
            'Travel class:' => 'Klasa podróży:',
            //            'Operated by'                    => '',
            'Aircraft type:'                  => 'Rodzaj samolotu:',
            'priceShort'                      => 'Całkowita cena',
            'Flights'                         => 'Loty',
        ],

        'fr' => [
            'Booking code:'               => 'Code de réservation:',
            'Check passenger information' => ['Vérifier API - Advanced Passenger Information', 'Advanced Passenger Information'],
            'Flying Blue members earn'    => 'Les membres Flying Blue gagnent',
            //don't describe everyone fees. only not included in 'Total tax amount all passengers'. check sum
            //            'Fees' => '',
            'Total tax amount all passengers' => 'Montant total des taxes pour tous les passagers',
            'Total price:'                    => ['Prix total:', 'Prix total :'],
            'Price per adult'                 => 'Prix par adulte',
            'Number of adults'                => "Nombre d'adultes",
            'Travel class:'                   => ['Classe de voyage:', 'Classe de voyage :'],
            'Operated by'                     => ['Opéré par', 'Opéré par '],
            'Aircraft type:'                  => ['Type d’avion:', 'Type d’avion :'],
            'priceShort'                      => 'Prix total',
            'Flights'                         => 'Vols',
        ],
        'pt' => [ // it-41665949.eml
            'Booking code:'               => 'Código de reserva:',
            'Check passenger information' => ['Confira as informações do passageiro', 'Verifique as informações do passageiro'],
            'Flying Blue members earn'    => 'Membros Flying Blue acumulam',
            //don't describe everyone fees. only not included in 'Total tax amount all passengers'. check sum
            'Fees'                            => 'Adicional de pagamento',
            'Total tax amount all passengers' => 'Total de imposto para todos os passageiros',
            'Total price:'                    => 'Preço total:',
            'Price per adult'                 => 'Preço por adulto',
            'Number of adults'                => 'Quantidade de adultos',
            'Travel class:'                   => ['Classe da viagem:', 'Classe de viagem:'],
            'Operated by'                     => 'Operado por',
            'Aircraft type:'                  => ['Tipo de aeronave:', 'Tipo de avião:'],
            'priceShort'                      => 'Preço total',
            'Flights'                         => 'Voos',
        ],
        'nl' => [ // it-38144962.eml
            'Booking code:'               => 'Boekingscode:',
            'Check passenger information' => 'Controleer de passagiersgegevens',
            'Flying Blue members earn'    => 'Flying Blue-deelnemers sparen',
            //don't describe everyone fees. only not included in 'Total tax amount all passengers'. check sum
            'Fees'                            => 'Boekingskosten',
            'Total tax amount all passengers' => 'Totale belastingen alle passagiers',
            'Total price:'                    => 'Totaalprijs:',
            'Price per adult'                 => 'Prijs per volwassene',
            'Number of adults'                => 'Aantal volwassenen',
            'Travel class:'                   => 'Reisklasse:',
            'Operated by'                     => 'Uitgevoerd door',
            'Aircraft type:'                  => 'Vliegtuigtype:',
            'priceShort'                      => 'Totaalprijs',
            'Flights'                         => 'Vluchten',
        ],
        'es' => [
            'Booking code:'               => 'Código de reserva:',
            'Check passenger information' => 'Revise la información de los pasajeros',
            'Flying Blue members earn'    => 'Los socios Flying Blue ganan',
            //don't describe everyone fees. only not included in 'Total tax amount all passengers'. check sum
            //            'Fees' => '',
            'Total tax amount all passengers' => 'Precio total de impuestos para todos los pasajeros',
            'Total price:'                    => 'Precio total:',
            'Price per adult'                 => 'Precio por adulto',
            'Number of adults'                => 'Cantidad de adultos',
            'Travel class:'                   => 'Clase de viaje:',
            'Operated by'                     => 'Operado por',
            'Aircraft type:'                  => 'Tipo de avión:',
            'priceShort'                      => 'Precio total',
            'Flights'                         => 'Vuelos',
        ],
        'en' => [
            'Booking code:'               => 'Booking code:',
            'Check passenger information' => ['Check passenger information', 'Passenger information'],
            //don't describe everyone fees. only not included in 'Total tax amount all passengers'. check sum
            'Fees'         => ['Booking fees', 'Payment surcharge'],
            'Total price:' => ['Total price:', 'Total price'],
            'priceShort'   => 'Total price',
        ],
        'it' => [ // it-59016791.eml
            'Booking code:'               => 'Codice di prenotazione:',
            'Check passenger information' => 'Controlli i dati dei passeggeri',
            'Flying Blue members earn'    => 'I soci Flying Blue accumulano',
            //don't describe everyone fees. only not included in 'Total tax amount all passengers'. check sum
            //            'Fees' => '',
            'Total tax amount all passengers' => 'Totale tasse per tutti i passeggeri',
            'Total price:'                    => 'Prezzo totale:',
            'Price per adult'                 => 'Prezzo totale passeggeri (adulti)',
            'Number of adults'                => 'Numero di adulti',
            'Travel class:'                   => 'Classe di viaggio:',
            //'Operated by' => '',
            'Aircraft type:' => 'Tipo di aereo:',
            'priceShort'     => 'Prezzo totale',
            'Flights'        => 'Voli',
        ],
        'de' => [ // it-59016791.eml
            'Booking code:'               => 'Buchungscode:',
            'Check passenger information' => 'Passagierinformationen überprüfen',
            'Flying Blue members earn'    => 'Flying Blue-Mitglieder sammeln',
            //don't describe everyone fees. only not included in 'Total tax amount all passengers'. check sum
            //            'Fees' => '',
            //            'Total tax amount all passengers' => '',
            //            'Total price:'                    => '',
            //            'Price per adult'                 => '',
            //            'Number of adults'                => 'Numero di adulti',
            'Travel class:'                   => 'Reiseklasse:',
            //'Operated by' => '',
            'Aircraft type:' => 'Flugzeugtyp:',
            'priceShort'     => 'Gesamtpreis',
            'Flights'        => 'Flüge',
        ],
        'ko' => [ // it-59016791.eml
            'Booking code:'               => '예약 코드:',
            'Check passenger information' => '승객 정보 확인',
            // 'Flying Blue members earn'    => 'Flying Blue-Mitglieder sammeln',
            //don't describe everyone fees. only not included in 'Total tax amount all passengers'. check sum
            //            'Fees' => '',
            //            'Total tax amount all passengers' => '',
            //            'Total price:'                    => '',
            //            'Price per adult'                 => '',
            //            'Number of adults'                => 'Numero di adulti',
            'Travel class:'                   => '좌석 등급:',
            //'Operated by' => '',
            'Aircraft type:' => '항공기 유형:',
            'priceShort'     => '총 가격',
            'Flights'        => ['항공편 + 옵션', '항공편'],
        ],
    ];
    private $keywordProv = ['Flying Blue', 'KLM'];
    private $year = '';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $emailDate = strtotime($parser->getDate());

        if ($emailDate) {
            $this->year = date('Y', $emailDate);
        }

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'.klm.com') or @alt='Acknowledge Changes']"
            . " | //a[contains(@href,'.klm.com/') or contains(@href,'www.klm.com') or contains(@href,'.infos-klm.com/')]"
            . " | //*[contains(normalize-space(),'Thank you for choosing KLM') or contains(normalize-space(),'Download the KLM app')]")->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || $this->detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || preg_match("/{$this->opt($this->keywordProv)}/", $headers['subject']) > 0)
                    && stripos($headers['subject'], $reSubject) !== false
                ) {
                    return true;
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

    private function parseEmail(Email $email): void
    {
        $xpathTime = 'contains(translate(normalize-space(),"0123456789：","dddddddddd:"),"d:dd")';
        $xpathCell = '(self::td or self::th)';
        $xpathBold = '(self::b or self::strong)';

        $patterns = [
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?',
        ];

        $r = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking code:'))}]");

        if (preg_match("/^({$this->opt($this->t('Booking code:'))})\s*([-A-Z\d]{5,})$/", $confirmation, $m)) {
            $r->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        $r->general()->travellers(preg_replace("/^ *(Mrs|Mr|Mstr|Miss|Ms) /", '',
            $this->http->FindNodes("//text()[{$this->starts($this->t('Check passenger information'))}]/following::table[1]/descendant::tr[normalize-space()][position()<last()]", null, "/^[• ]*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\s*(?:\(|$)/u")), true);

        $earnedAwards = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Flying Blue members earn'))}]", null, false, "/{$this->opt($this->t('Flying Blue members earn'))}\s*(.+?)[. ]*$/")
            ?? $this->http->FindSingleNode("//text()[{$this->contains($this->t('Flying Blue Miles'))} and {$this->contains($this->t('by completing this booking'))}]", null, false, "/^[+\s]+(\d[,.\'\d ]*{$this->opt($this->t('Flying Blue Miles'))})\s+{$this->opt($this->t('by completing this booking'))}/")
        ;
        $r->program()->earnedAwards($earnedAwards, false, true);

        $fees = $this->t('Fees');

        foreach ((array) $fees as $fee) {
            $sum = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($fee)}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]"));

            if ($sum['Total'] !== '') {
                $r->price()->fee(rtrim($fee, ':'), $sum['Total']);
            }
        }
        $sum = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Total tax amount all passengers'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]"));

        if ($sum['Total'] !== '') {
            $r->price()
                ->tax($sum['Total']);
        }
        $totalPrice = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total price:'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]");

        if ($totalPrice === null) {
            $totalPrice = $this->http->FindSingleNode("//text()[{$this->eq($this->t('priceShort'))} and ancestor::*[{$xpathBold}]]/following::table[normalize-space()][1]/descendant::*[{$xpathCell} and {$this->eq($this->t('Flights'))}]/following-sibling::*[normalize-space()]");
        }

        if ($totalPrice === null) {
            $totalPrice = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total price:'))}]/ancestor::table[1]/descendant::tr[{$this->starts($this->t('Flights'))}]");
        }

        if ($totalPrice !== null) {
            $sum = $this->getTotalCurrency($totalPrice);
            $r->price()
                ->total($sum['Total'])
                ->currency($sum['Currency']);
        }

        $sum = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Price per adult'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]"));
        $cnt = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Number of adults'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]",
            null, false, "#^\d+$#");

        if ($sum['Total'] !== '' && $cnt !== null) {
            $r->price()->cost($sum['Total'] * $cnt);
        }

        /* Segments: 1st try */

        $xpath = "//text()[{$this->starts($this->t('Travel class:'))}]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);
        $this->logger->debug("[XPATH] segments type-1: " . $xpath);

        foreach ($nodes as $root) {
            $s = $r->addSegment();

            $airports = $this->http->FindNodes("./ancestor::table[1]/descendant::text()[" . $this->contains(['Airport', 'Aeropuerto', 'Aéroport']) . "]/ancestor::tr[1]", $root);

            if (count($airports) !== 2) {
                $airports = $this->http->FindNodes("./ancestor::table[1]/descendant::tr[not(.//tr)][" . $this->contains(', (') . "]", $root);
            }

            if (count($airports) == 2) {
                $s->departure()
                    ->noCode()
                    ->name($airports[0]);

                $s->arrival()
                    ->noCode()
                    ->name($airports[1]);
            }

            $segmentText = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $root));

            /*
                KL1725 Operated by: KLM - Aircraft type: Embraer 190
                Travel class: Economy Class
                Friday 7 June 2019, 13:45 - 14:35 (D+1)

                [OR]

                KL1725 - Aircraft type: Embraer 190
                Operated by KLM
                Travel class: Economy Class
                Friday 7 June 2019, 13:45 - 14:35 (D+1)
            */

            if (preg_match("/^(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])?[ ]*(?<fn>\d+)(?:\s|$)/", $segmentText, $m)) {
                $s->airline()->name($m['al'])->number($m['fn']);
            }

            $operator = $this->re("/^.*\b{$this->opt($this->t('Operated by'))}[: ]+(.*?[^:\s])(?:[ ]+-[ ]+{$this->opt($this->t('Aircraft type:'))}|[ ]*\n|$)/", $segmentText)
                ?? $this->re("/^(?:.+\n+){0,1}[ ]*{$this->opt($this->t('Operated by'))}[: ]+(.*[^:\s])(?:[ ]*\n|$)/", $segmentText);
            $s->airline()->operator($operator, false, true);

            $aircraft = $this->re("/^.*\b{$this->opt($this->t('Aircraft type:'))}[: ]*(.*[^:\s])(?:[ ]*\n|$)/", $segmentText);
            $s->extra()->aircraft($aircraft, false, true);

            $cabin = $this->re("/^(?:.+\n+){0,2}[ ]*{$this->opt($this->t('Travel class:'))}[: ]*(.*[^:\s])(?:[ ]*\n|$)/", $segmentText);
            $s->extra()->cabin($cabin, false, true);

            if (preg_match("/^(?:.+\n+){0,3}[ ]*(?<date>.{4,}? \d{4})[ ]*,[ ]*(?<timeDep>{$patterns['time']})[ ]+-[ ]+(?<timeArr>{$patterns['time']})(?:[ ]*\(D[ ]*[+][ ]*(?<overnight>\d{1,3})[ ]*\))?/", $segmentText, $m)
                || preg_match("/^(?:.+\n+){0,3}[ ]*(?<date>.*?\d{4}.*?)[ ]*,[ ]*(?<timeDep>{$patterns['time']})[ ]+-[ ]+(?<timeArr>{$patterns['time']})(?:[ ]*\(D[ ]*[+][ ]*(?<overnight>\d{1,3})[ ]*\))?/", $segmentText, $m)
            ) {
                // Friday 7 June 2019, 13:45 - 14:35 (D+1)
                $date = strtotime($this->normalizeDate($m['date']));
                $s->departure()->date(strtotime($m['timeDep'], $date));
                $dateArr = strtotime($m['timeArr'], $date);

                if (empty($m['overnight'])) {
                    $s->arrival()->date($dateArr);
                } else {
                    $s->arrival()->date(strtotime('+' . $m['overnight'] . ' days', $dateArr));
                }
            }
        }

        $segmentCount = count($r->getSegments());

        if ($segmentCount) {
            $this->logger->debug('Found ' . $segmentCount . ' segments type-1.');

            return;
        }
        $this->logger->debug('Segments type-1 not found!');

        /* Segments: 2nd try */

        // it-99784038.eml
        $xpath = "//tr[ descendant::img[contains(@src,'/icon-arrow.')] and following-sibling::tr[{$xpathTime}] ]";
        $segments = $this->http->XPath->query($xpath);
        $this->logger->debug("[XPATH 2] segments type-2: " . $xpath);

        foreach ($segments as $root) {
            $s = $r->addSegment();

            $s->departure()
                ->name($this->http->FindSingleNode('preceding-sibling::tr[normalize-space()]', $root))
                ->noCode()
            ;

            $s->arrival()
                ->name($this->http->FindSingleNode('.', $root))
                ->noCode()
            ;

            $bottomText = $this->http->FindSingleNode('following-sibling::tr[normalize-space()]', $root);

            if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)\s*\|\s*(?<date>.{3,}?)\s*,\s*(?<time1>{$patterns['time']})\s+-\s+(?<time2>{$patterns['time']})(?:\s*\(\s*D\s*(?<overnight>[+]\s*\d{1,3})\s*\))?$/", $bottomText, $m)) {
                // KL0612 | Wednesday, June 30, 16:15 - 06:55 (D+1)
                $s->airline()->name($m['name'])->number($m['number']);

                if (preg_match("/^(?<wday>[-[:alpha:]]+)\s*,\s*(?<date>[[:alpha:]]+\s+\d{1,2}|[[:alpha:]]+\s+\d{1,2})$/u", $m['date'], $matches)) {
                    // Wednesday, June 30
                    $weekDateNumber = WeekTranslate::number1($matches['wday']);
                    $dateNormal = $this->normalizeDate($matches['date']);

                    if ($dateNormal && $this->year) {
                        $date = EmailDateHelper::parseDateUsingWeekDay($dateNormal . ' ' . $this->year, $weekDateNumber);
                        $s->departure()->date(strtotime($m['time1'], $date));
                        $s->arrival()->date(strtotime($m['time2'], $date));
                    }
                }

                if (!empty($m['overnight']) && !empty($s->getArrDate())) {
                    $s->arrival()->date(strtotime($m['overnight'] . ' days', $s->getArrDate()));
                }
            }
        }

        $segmentCount = count($r->getSegments());

        if ($segmentCount) {
            $this->logger->debug('Found ' . $segmentCount . ' segments type-2.');

            return;
        }
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
        if (isset($this->reBody)) {
            foreach ($this->reBody as $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Booking code:'], $words['Check passenger information'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Booking code:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Check passenger information'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node): array
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $currencyCode = preg_match('/^[A-Z]{3}$/', $cur) ? $cur : null;
            $tot = PriceHelper::parse($m['t'], $currencyCode);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
//        $this->logger->debug('DATE: ' . $text);
        if (preg_match('/^[[:alpha:]-]{2,}[, ]+(\d{1,2})(?:\s+de|[.])?\s+([[:alpha:]]{3,}?)(?:\s+de)?\s+(\d{4})$/u', $text, $m)) {
            // zondag 16 juni 2019    |    Segunda-feira 19 de agosto de 2019   |   Montag 7. März 2022
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^\s*(\d{4})\s*년\s*(\d{1,2})\s*월\s*(\d{1,2})\s*일\D*$/u', $text, $m)) {
            // 2024년 7월 5일 금요일
            $day = $m[3];
            $month = $m[2];
            $year = $m[1];
        } elseif (preg_match('/^(\d{1,2})\s+([[:alpha:]]{3,})$/u', $text, $m)) {
            // 30 June
            $day = $m[1];
            $month = $m[2];
            $year = '';
        } elseif (preg_match('/^([[:alpha:]]{3,})\s+(\d{1,2})$/u', $text, $m)) {
            // June 30
            $month = $m[1];
            $day = $m[2];
            $year = '';
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            } else {
                $langs = array_merge(array_keys(self::$dict), ['cs']);

                foreach ($langs as $lang) {
                    if (($monthNew = MonthTranslate::translate($month, $lang)) !== false) {
                        $month = $monthNew;

                        break;
                    }
                }
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }
}
