<?php

namespace AwardWallet\Engine\klm\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class AirTravel extends \TAccountChecker
{
    public $mailFiles = "klm/it-1732703.eml, klm/it-1788573.eml, klm/it-1810951.eml, klm/it-1990272.eml, klm/it-2316418.eml, klm/it-2316445.eml, klm/it-27617558.eml, klm/it-27649591.eml, klm/it-27717171.eml, klm/it-27883727.eml, klm/it-28086126.eml, klm/it-4730384.eml, klm/it-4758420.eml, klm/it-4793947.eml, klm/it-4813991.eml, klm/it-4820703.eml, klm/it-4847902.eml, klm/it-4860868.eml, klm/it-4895356.eml, klm/it-5168816.eml, klm/it-5199049.eml, klm/it-6.eml, klm/it-6696243.eml, klm/it-73907652.eml, klm/it-8.eml"; // +1 bcdtravel(html)[nl]

    private $lang = '';
    private static $detectBody = [
        'pt' => ['Sua reserva foi concluída com sucesso!', 'A sua reserva foi completada com sucesso'],
        'de' => 'Ihre Buchung war erfolgreich!',
        'es' => '¡Su reserva se ha realizado correctamente',
        'nl' => ['Betaalbewijs voor deze optie', 'Hartelijk dank voor uw reservering bij KLM', 'Uw reservering is gelukt'],
        'zh' => ['您已预订成功！', '您已預訂成功！'],
        'ja' => 'ご予約を承りました。',
        'en' => ['Your booking was successful!', 'Your booking is confirmed'],
        "uk" => "Рейс – Код бронювання",
        "fi" => "Lento - varauskoodi",
        "da" => "Flyrejse – Bookingkode",
        "ru" => "Рейс – Код бронирования",
        "no" => "Flyvning – referansenummer",
        "it" => "Volo – codice di prenotazione",
        "fr" => "Votre réservation a réussi",
    ];

    private $provider = 'klm';

    private $dict = [
        'pt' => [], // it-5168816.eml
        'fr' => [
            'Código de reserva'                          => 'Vol - Code de réservation',
            'Passageiros'                                => 'Passagers',
            'Número do bilhete'                          => 'Numéro de billet',
            'número de cadastro no programa de milhagem' => 'Numéro de voyageur fréquent',
            'Preço total:'                               => 'Prix total:',
            'Operado por'                                => 'Opéré par :',
            'Classe:'                                    => 'Classe :',
            'do voo'                                     => 'Numéro de vol :',
            'Tipo de aeronave'                           => 'Type d’avion :',
            "Departure:"                                 => "Départ",
            "Return:"                                    => "Retour",
            //"Seat number" => "",
        ],
        'it' => [
            'Código de reserva' => 'Volo – codice di prenotazione',
            'Passageiros'       => 'Passeggeri',
            'Número do bilhete' => 'Numero biglietto',
            //'número de cadastro no programa de milhagem' => '',
            'Preço total:'     => 'Prezzo totale:',
            'Operado por'      => 'Operato da:',
            'Classe:'          => 'Classe:',
            'do voo'           => 'Numero volo:',
            'Tipo de aeronave' => 'Tipo di aereo:',
            "Departure:"       => "Partenza:",
            "Return:"          => "Ritorno:",
            "Seat number"      => "Numero di posto",
        ],
        'no' => [
            'Código de reserva' => 'Flyvning – referansenummer',
            'Passageiros'       => 'Passasjerer',
            'Número do bilhete' => 'Billettnummer',
            //'número de cadastro no programa de milhagem' => '',
            'Preço total:'     => 'Total pris:',
            'Operado por'      => 'Betjenes av:',
            'Classe:'          => 'Klasse:',
            'do voo'           => 'Rutenummer:',
            'Tipo de aeronave' => 'Flytype:',
            "Departure:"       => "Avreise:",
            "Return:"          => "Retur:",
            //"Seat number" => "",
        ],
        'ru' => [
            'Código de reserva'                          => 'Рейс – Код бронирования',
            'Passageiros'                                => 'Пассажиры',
            'Número do bilhete'                          => 'Номер билета',
            'número de cadastro no programa de milhagem' => 'Номер участника программы для постоянных пассажиров',
            'Preço total:'                               => 'Общая цена:',
            'Operado por'                                => 'Выполняется:',
            'Classe:'                                    => 'Класс:',
            'do voo'                                     => 'Номер рейса:',
            'Tipo de aeronave'                           => 'Тип самолета:',
            "Departure:"                                 => "Вылет:",
            "Return:"                                    => "Возврат:",
            //"Seat number" => "",
        ],
        'da' => [
            'Código de reserva' => 'Flyrejse – Bookingkode',
            'Passageiros'       => 'Passagerer',
            'Número do bilhete' => 'Billetnummer',
            //'número de cadastro no programa de milhagem' => '',
            'Preço total:'     => 'Pris i alt:',
            'Operado por'      => 'Fløjet af:',
            'Classe:'          => 'Klasse:',
            'do voo'           => 'Flynummer:',
            'Tipo de aeronave' => 'Flytype:',
            "Departure:"       => "Udrejse:",
            "Return:"          => "Hjemrejse:",
            "Seat number"      => "NOTTRANSLATED",
        ],
        'fi' => [
            'Código de reserva'                          => 'Lento - varauskoodi',
            'Passageiros'                                => 'Matkustajat',
            'Número do bilhete'                          => 'Lipun numero',
            'número de cadastro no programa de milhagem' => 'kanta-asiakasnumero',
            'Preço total:'                               => 'Kokonaishinta:',
            'Operado por'                                => 'Lentoyhtiö:',
            'Classe:'                                    => 'NOTTRANSLATED',
            'do voo'                                     => 'Lennon numero:',
            'Tipo de aeronave'                           => 'Lentokonetyyppi:',
            "Departure:"                                 => "Meno:",
            "Return:"                                    => "Paluu:",
            "Seat number"                                => "NOTTRANSLATED",
        ],
        'uk' => [
            'Código de reserva'                          => 'Рейс – Код бронювання',
            'Passageiros'                                => 'Пасажири',
            'Número do bilhete'                          => 'Номер квитка',
            'número de cadastro no programa de milhagem' => 'Vielfliegernummer',
            'Preço total:'                               => 'Загальна ціна:',
            'Operado por'                                => 'Виконується:',
            'Classe:'                                    => 'Клас:',
            'do voo'                                     => 'Номер рейсу:',
            'Tipo de aeronave'                           => 'Тип літака:',
            "Departure:"                                 => "Виліт:",
            "Return:"                                    => "Повернення:",
            "Seat number"                                => "Номер місця",
        ],
        'de' => [ // it-73907652.eml
            'Código de reserva'                          => 'Buchungscode',
            'Passageiros'                                => 'Passagiere',
            'Número do bilhete'                          => 'Ticketnummer',
            'número de cadastro no programa de milhagem' => 'Vielfliegernummer',
            'Preço total:'                               => 'Gesamtpreis:',
            'Operado por'                                => 'Durchgeführt von',
            'Classe:'                                    => 'Class:',
            'do voo'                                     => 'Flugnummer',
            'Tipo de aeronave'                           => 'Flugzeugtyp',
        ],
        'nl' => [ // it-5199049.eml, it-8.eml
            'Código de reserva'                          => ['Boekingscode van uw optie', 'Boekingscode', 'Reserveringscode'],
            'Passageiros'                                => ['Passagiers'],
            'Número do bilhete'                          => 'Ticketnummer',
            'número de cadastro no programa de milhagem' => 'frequent flyer nummer',
            'Preço total:'                               => ['Preço total:', 'Totaalprijs:'],
            'Operado por'                                => 'Uitgevoerd door',
            'Classe:'                                    => 'Klasse:',
            'do voo'                                     => 'Vluchtnummer',
            'Tipo de aeronave'                           => 'Vliegtuigtype',
            "Departure:"                                 => "Heenreis:",
            "Return:"                                    => "Terugreis:",
        ],
        'zh' => [ // it-27883727.eml, it-27649591.eml
            'Código de reserva' => ['预订代码', '預訂代號'],
            'Passageiros'       => '旅客',
            'Número do bilhete' => ['机票号码', '機票編號'],
            //			'número de cadastro no programa de milhagem' => '',
            'Preço total:'     => ['总价款:', '總價額:'],
            'Operado por'      => ['运营航空公司：', '營運公司：'],
            'Classe:'          => ['客舱等级：', '艙等：'],
            'do voo'           => ['航班号：', '航班編號：'],
            'Tipo de aeronave' => ['机型：', '機型：'],
        ],
        'ja' => [ // it-27717171.eml
            'Código de reserva' => '予約コード',
            'Passageiros'       => '旅客',
            'Número do bilhete' => '航空券番号',
            //			'número de cadastro no programa de milhagem' => '',
            'Preço total:'     => '合計金額:',
            'Operado por'      => '運航会社：',
            'Classe:'          => 'クラス：',
            'do voo'           => '便名：',
            'Tipo de aeronave' => '機種:',
        ],
        'en' => [ // it-6696243.eml, it-27617558.eml, it-1732703.eml
            'Código de reserva'                          => ['Booking code', 'Flight - Booking code'],
            'Passageiros'                                => 'Passengers',
            'Número do bilhete'                          => 'Ticket number',
            'número de cadastro no programa de milhagem' => 'frequent flyer number',
            'Preço total:'                               => ['Total price:'],
            'Operado por'                                => 'Operated by:',
            'Classe:'                                    => 'Class:',
            'do voo'                                     => 'Flight number',
            'Tipo de aeronave'                           => 'Aircraft type',
        ],
        'es' => [
            'Código de reserva' => ['Código de reserva'],
            'Passageiros'       => 'Pasajeros',
            'Número do bilhete' => 'Número de billete',
            //			'número de cadastro no programa de milhagem' => '',
            'Preço total:'     => ['Precio total:'],
            'Operado por'      => 'Compañía:',
            'Classe:'          => 'Clase:',
            'do voo'           => 'Número de vuelo:',
            'Tipo de aeronave' => 'Tipo de avión:',
        ],
    ];

    public function parseEmail(Email $email)
    {
        $xpathNoEmpty2 = '(normalize-space() and normalize-space()!=" ")';
        $patterns = [
            'time' => '\d{1,2}[:：]\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?',
        ];

        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//td[{$this->contains($this->t('Código de reserva'))}]/following-sibling::td[1]"));

        $total = $this->http->FindSingleNode("(//td[" . $this->contains(str_replace(":", "", $this->t('Preço total:'))) . "]/following-sibling::td[1])[last()]");

        if ($total === null || $total === '') {
            $total = $this->http->FindSingleNode("//tr/*[{$this->contains($this->t('Preço total:'))}]/following-sibling::*[{$xpathNoEmpty2}][1]");
        }

        if (preg_match('/^(?<currency>[A-Z]{3})?[ ]*(?<amount>\d[,.\'\d ]*)$/', $total, $m)
            || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[^\d)(]+)$/', $total, $m)
        ) {
            // EUR 3.174,82    |    BRL 3,622.79    |    HKD 8,476    |    245,19 €    |    1.258,25
            if (!empty($m['currency'])) {
                $f->price()
                    ->currency($m['currency']);
            }
            $f->price()
                ->total($this->normalizeAmount($m['amount']));
        }

        $f->general()
            ->travellers(str_replace(['Пан ', 'Пані ', 'Г-н ', 'Г-жа ', 'Herr ', 'Sr. ', 'Srª. ', 'Mme ', 'M. ', 'Mlle '], '', $this->http->FindNodes("//text()[{$this->eq($this->t('Passageiros'))}]/ancestor::tr[./following-sibling::tr][1]/following-sibling::tr/descendant::tr[not(.//tr) and count(./td)=1]/descendant::text()[normalize-space(.)][1]", null, '/(.+?)\s*\(.*\)/')));

        $ticketNumbers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Passageiros'))}]/ancestor::tr[./following-sibling::tr][1]/following-sibling::tr/descendant::tr//text()[{$this->contains($this->t('Número do bilhete'), 'normalize-space(.)')}]/following::text()[normalize-space()][1]", null, '/^\s*([\d\- ]+)\s*$/'));

        if (count($ticketNumbers)) {
            $f->setTicketNumbers(array_unique($ticketNumbers), false);
        }

        $accountNumbers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Passageiros'))}]/ancestor::tr[following-sibling::tr][1]/following-sibling::tr/descendant::tr//text()[{$this->contains($this->t('número de cadastro no programa de milhagem'), 'normalize-space()')}]/following::text()[normalize-space()][1]", null, '/^[-A-Z\d]{5,}$/'));

        if (count($accountNumbers) == 0) {
            $accountNumbers = array_filter($this->http->FindNodes("//text()[normalize-space()='Frequent Flyer number']/ancestor::tr[1]/descendant::td[2]", null, '/^[-A-Z\d]{5,}$/'));
        }

        if (count($accountNumbers)) {
            $f->setAccountNumbers(array_unique($accountNumbers), false);
        }

        $xpathTime2 = '(starts-with(translate(normalize-space(),"0123456789：","dddddddddd:"),"d:dd") or starts-with(translate(normalize-space(),"0123456789：","dddddddddd:"),"dd:dd"))';
        $xpath = "//*[count(tr/*[normalize-space()][2][{$xpathTime2}])=2]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->logger->alert('Segments not found!');

            return null;
        }

        if ($segments->length === 1 && $this->http->XPath->query("//text()[{$this->starts($this->t('Departure:'))} or {$this->starts($this->t('Return:'))}]/ancestor::tr[1]")->length > 1) {
            $xpath = "//text()[{$this->starts($this->t('Departure:'))} or {$this->starts($this->t('Return:'))}]/ancestor::tr[1]";

            $segments = $this->http->XPath->query($xpath);

            if ($segments->length === 0) {
                $this->logger->alert('Segments not found!');

                return null;
            }
            $this->logger->debug('Root xpath: ' . $xpath);

            foreach ($segments as $root) {
                $s = $f->addSegment();

                $dateDep = $this->normalizeDate($this->http->FindSingleNode("./following::tr[normalize-space()][1]/*[normalize-space()][1]", $root));
                $timeNameDep = $this->http->FindSingleNode("./following::tr[normalize-space()][1]/*[normalize-space()][2]/descendant::text()[normalize-space()][1]", $root);

                if (preg_match("/^(?<time>{$patterns['time']})\s+(?<name>.{3,})$/", $timeNameDep, $m)) {
                    $s->departure()
                        ->noCode()
                        ->name($m['name']);

                    if ($dateDep) {
                        $s->departure()
                            ->date(strtotime($m['time'] . $dateDep));
                    }
                }

                $dateArr = $this->normalizeDate($this->http->FindSingleNode("./following::tr[normalize-space()][1]/*[normalize-space()][1]", $root));
                $timeNameArr = $this->http->FindSingleNode("./following::tr[normalize-space()][1]/*[normalize-space()][2]/descendant::text()[normalize-space()][2]", $root);

                if (preg_match("/^(?<time>{$patterns['time']})\s+(?<name>.{3,})$/", $timeNameArr, $m)) {
                    $s->arrival()
                        ->noCode()
                        ->name($m['name']);

                    if ($dateArr) {
                        $s->arrival()
                            ->date(strtotime($m['time'] . $dateArr));
                    }
                }

                $extraHtml = $this->http->FindHTMLByXpath("./following::tr[normalize-space()][2]/*[normalize-space()][1]", null, $root);
                $extraText = $this->htmlToText($extraHtml);

                if (preg_match("/{$this->opt($this->t('Classe:'))}[ ]+(\w[\w\s]*?\w)(?:[ ]+Class)?[ ]*$/im", $extraText, $m)) {
                    $s->extra()
                        ->cabin($m[1]);
                }

                if (preg_match("/{$this->opt($this->t('do voo'))}[: ]+([A-Z\d]{2})\s*(\d+)[ ]*/m", $extraText, $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2]);
                } elseif ($this->http->XPath->query("ancestor::tr[1]/following-sibling::tr[{$xpathNoEmpty2}][1][not(.//tr)]", $root)->length === 0
                    || preg_match("/{$this->opt($this->t('Equipment type is not known'))}/i", $extraText)
                    || !preg_match("/{$this->opt($this->t('do voo'))}/i", $extraText)
                ) {
                    $s->airline()
                        ->noName()
                        ->noNumber();
                }

                if (preg_match("/{$this->opt($this->t('Tipo de aeronave'))}[: ]+([^|\s][^|]*?[^|\s])[ ]*(?:\||$)/m", $extraText, $m)) {
                    $s->extra()
                        ->aircraft($m[1]);
                }

                if (preg_match("/{$this->opt($this->t('Operado por'))}[: ]+(.+?)[ ]*$/m", $extraText, $m)) {
                    $s->airline()
                        ->operator($m[1]);
                }

                $seats = array_filter($this->http->FindNodes("//tr[contains(., '" . $s->getFlightNumber() . "') and not(.//tr)]/following-sibling::tr[contains(., '" . $this->t("Seat number") . "')][1]//tr[contains(., '" . $this->t("Seat number") . "')]/following-sibling::tr/td[3]"));

                if (count($seats) > 0) {
                    $s->setSeats($seats);
                }
            }
        } else {
            foreach ($segments as $root) {
                /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
                $s = $f->addSegment();

                $dateDep = $this->normalizeDate($this->http->FindSingleNode("tr[normalize-space()][1]/*[normalize-space()][1]", $root));
                $timeNameDep = $this->http->FindSingleNode("tr[normalize-space()][1]/*[normalize-space()][2]", $root);

                if (preg_match("/^(?<time>{$patterns['time']})\s+(?<name>.{3,})$/", $timeNameDep, $m)) {
                    $s->departure()
                        ->noCode()
                        ->name($m['name']);

                    if ($dateDep) {
                        $s->departure()
                            ->date(strtotime($m['time'] . $dateDep));
                    }
                }

                $dateArr = $this->normalizeDate($this->http->FindSingleNode("tr[normalize-space()][2]/*[normalize-space()][1]", $root));
                $timeNameArr = $this->http->FindSingleNode("tr[normalize-space()][2]/*[normalize-space()][2]", $root);

                if (preg_match("/^(?<time>{$patterns['time']})\s+(?<name>.{3,})$/", $timeNameArr, $m)) {
                    $s->arrival()
                        ->noCode()
                        ->name($m['name']);

                    if ($dateArr) {
                        $s->arrival()
                            ->date(strtotime($m['time'] . $dateArr));
                    }
                }

                $extraHtml = $this->http->FindHTMLByXpath("ancestor::tr[1]/following-sibling::tr[{$xpathNoEmpty2}][1]", null, $root);
                $extraText = $this->htmlToText($extraHtml);

                if (preg_match("/{$this->opt($this->t('Classe:'))}[ ]+(\w[\w\s]*?\w)(?:[ ]+Class)?[ ]*$/im", $extraText, $m)) {
                    $s->extra()
                        ->cabin($m[1]);
                }

                if (preg_match("/{$this->opt($this->t('do voo'))}[: ]+([A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]+(\d+)[ ]*$/m", $extraText, $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2]);
                } elseif ($this->http->XPath->query("ancestor::tr[1]/following-sibling::tr[{$xpathNoEmpty2}][1][not(.//tr)]", $root)->length === 0
                    || preg_match("/{$this->opt($this->t('Equipment type is not known'))}/i", $extraText)
                    || !preg_match("/{$this->opt($this->t('do voo'))}/i", $extraText)
                ) {
                    // it-27617558.eml, it-5199049.eml
                    $s->airline()
                        ->noName()
                        ->noNumber();
                }

                if (preg_match("/{$this->opt($this->t('Tipo de aeronave'))}[: ]+([^|\s][^|]*?[^|\s])[ ]*(?:\||$)/m", $extraText, $m)) {
                    // Embraer 190    |    Boeing 737-700 | view seat map
                    $s->extra()
                        ->aircraft($m[1]);
                }

                if (preg_match("/{$this->opt($this->t('Operado por'))}[: ]+(.+?)[ ]*$/m", $extraText, $m)) {
                    $s->airline()
                        ->operator($m[1]);
                }

                $seats = array_filter($this->http->FindNodes("//tr[contains(., '" . $s->getFlightNumber() . "') and not(.//tr)]/following-sibling::tr[contains(., '" . $this->t("Seat number") . "')][1]//tr[contains(., '" . $this->t("Seat number") . "')]/following-sibling::tr/td[3]"));

                if (count($seats) > 0) {
                    $s->setSeats($seats);
                }
            }
        }
        $this->logger->debug('Root xpath: ' . $xpath);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->detectBodyAndAcceptLang();
        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '.klm.com')]")->length === 0) {
            return false;
        }

        return $this->detectBodyAndAcceptLang();
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, 'klm.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'klm.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$detectBody);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$detectBody);
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

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function t($s)
    {
        if (!isset($this->dict[$this->lang]) || !isset($this->dict[$this->lang][$s])) {
            return $s;
        }

        return $this->dict[$this->lang][$s];
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
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $text, $m)) {
            // 30.01.2021
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^[-[:alpha:]]+[,.\s]+(\d{1,2})\s*(?:de\s+)?([[:alpha:]]+)(?:\s+de)?\s*(\d{2})$/u', $text, $m)) {
            // Sun 4 Jul 21    |    星期三 4 十二月 21
            $day = $m[1];
            $month = $m[2];
            $year = '20' . $m[3];
        } elseif (preg_match('/^(\d{4})\/(\d{2})\/(\d{2})$/', $text, $m)) {
            // 2017/08/19
            $year = $m[1];
            $month = $m[2];
            $day = $m[3];
        } elseif (preg_match('/^(\d{1,2})[-\s]+([[:alpha:]]{3,})[-\s]+(\d{4})$/u', $text, $m)) {
            // 13 Aug 2017    |    23-Dec-2020
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^(\d+)\s*\w+\s*(\w+)\s*\w+\s*(\d{4})$/u', $text, $m)) {
            //25 de nov de 2021
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^\w+\.?\s*(\d+)\s*([\w\.]+)\s*(\d{2})$/u', $text, $m)) {
            // mié 30 jul 14
            $day = $m[1];
            $month = trim($m[2], '.');
            $year = '20' . $m[3];
        } elseif (preg_match('/^(\w+)\s*(\d+)\,\s*(\d{4})$/u', $text, $m)) {
            // Jan 15, 2021
            $day = $m[2];
            $month = $m[1];
            $year = $m[3];
        } elseif (preg_match('/^(\d{2})[-\s]+(\d{1,2})[-\s]+(\d{1,2})[\s[:alpha:]]*$/u', $text, $m)) {
            // 19 1 7 月
            $year = '20' . $m[1];
            $month = $m[2];
            $day = $m[3];
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

    private function detectBodyAndAcceptLang()
    {
        $body = $this->http->Response['body'];

        foreach (self::$detectBody as $lang => $detect) {
            if (is_array($detect)) {
                foreach ($detect as $dt) {
                    if (stripos($body, $dt) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            } elseif (is_string($detect)) {
                if (stripos($body, $detect) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }
}
