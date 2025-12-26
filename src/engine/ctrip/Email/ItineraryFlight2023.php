<?php

namespace AwardWallet\Engine\ctrip\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ItineraryFlight2023 extends \TAccountChecker
{
    public $mailFiles = "ctrip/it-638650809.eml, ctrip/it-628947120-pt.eml, ctrip/it-702784250-it.eml, ctrip/it-704937866-zh.eml, ctrip/it-714299772-de.eml";

    public $lang = '';

    public static $dictionary = [
        'zh' => [
            'otaConfNumber'             => ['訂單編號：'],
            'Airline Booking Reference' => ['航空公司預訂參考編號', '預訂參考編號'],
            'statusPhrases'             => ['您的機票訂單已'],
            'statusVariants'            => ['確認'],
            // 'and' => '',
            'Given names'               => ['名字', '名'],
            'Surname'                   => ['姓氏', '姓'],
            'Request Update'            => '申請更新',
            'Ticket Number'             => '機票編號',
            'Total'                     => ['總計', '總額'],
        ],
        'de' => [
            'otaConfNumber'             => ['Buchungsnr.'],
            'Airline Booking Reference' => ['Referenzcode der Fluggesellschaft'],
            'statusPhrases'             => ['Die folgende Reiseroute wurde', 'Ihre Flugbuchung wurde'],
            'statusVariants'            => ['bestätigt'],
            'and'                       => 'und',
            'Given names'               => 'Vorname(n)',
            'Surname'                   => 'Nachname',
            'Request Update'            => 'Ändern',
            'Ticket Number'             => 'Ticketnummer',
            'Total'                     => 'Gesamt',
        ],
        'it' => [
            'otaConfNumber'             => ['Prenotazione n.'],
            'Airline Booking Reference' => ['Codice di prenotazione della compagnia aerea'],
            'statusPhrases'             => ['Il seguente itinerario è stato', 'La tua prenotazione è stata'],
            'statusVariants'            => ['confermato', 'confermata'],
            'and'                       => 'e',
            'Given names'               => 'Nome(i) di battesimo',
            'Surname'                   => 'Cognome',
            'Request Update'            => 'Richiedi modifica',
            'Ticket Number'             => 'Numero del biglietto',
            'Total'                     => 'Totale',

            // pdf
            'Name Pdf'               => 'Nome',
            'Class Pdf'              => 'Classe',
            'Airline Conf Pdf'       => 'Codice di prenotazione della',
            'Flight Information Pdf' => 'Informazioni sul volo',
        ],
        'pt' => [
            'otaConfNumber'             => ['N.º da reserva'],
            'Airline Booking Reference' => ['Referência de reserva da companhia aérea/Localizador'],
            'statusPhrases'             => ['Sua reserva de voo foi'],
            'statusVariants'            => ['confirmada'],
            // 'and' => '',
            'Given names'               => 'Nome e nome do meio',
            'Surname'                   => 'Sobrenome',
            // 'Request Update' => '',
            'Ticket Number'             => 'Número da passagem',
            // 'Total' => '',
        ],
        'en' => [
            'otaConfNumber'             => ['Booking No.'],
            'Airline Booking Reference' => ['Airline Booking Reference'],
            'statusPhrases'             => ['Your flight booking has been'],
            'statusVariants'            => ['confirmed'],
            // 'and' => '',
            'Given names' => ['Given names', 'First name'],
            'Surname'     => ['Surname', 'Last name'],
            // 'Request Update' => '',
            // 'Ticket Number' => '',
            // 'Total' => '',

            // pdf
            'Name Pdf'               => 'Name',
            'Class Pdf'              => 'Class',
            'Airline Conf Pdf'       => 'Numéro du dossier',
            'Flight Information Pdf' => 'Flight Information',
        ],
        'fr' => [
            'otaConfNumber'             => ['Nº de réservation：'],
            'Airline Booking Reference' => ['Numéro du dossier passager'],
            'statusPhrases'             => ["L'itinéraire suivant a été"],
            'statusVariants'            => ['confirmée', 'confirmé'],
            'and'                       => 'et',
            'Given names'               => 'Prénoms',
            'Surname'                   => 'Nom',
            'Request Update'            => 'Demander une mise à jour',
            'Ticket Number'             => 'Numéro(s) de billet',
            'Total'                     => 'Total',
        ],
        'es' => [
            'otaConfNumber'             => ['N.º de reserva：'],
            'Airline Booking Reference' => ['Localizador de la reserva'],
            'statusPhrases'             => ["Tu reserva de vuelo se ha"],
            'statusVariants'            => ['confirmado'],
            'and'                       => ', y',
            'Given names'               => 'Nombre',
            'Surname'                   => 'Apellidos',
            'Request Update'            => 'Cambiar',
            'Ticket Number'             => 'N.º de billete',
            'Total'                     => 'Total',
        ],
        'pl' => [
            'otaConfNumber'             => ['Nr rezerwacji'],
            'Airline Booking Reference' => ['Numer referencyjny rezerwacji w linii lotniczej'],
            'statusPhrases'             => ["Twoja rezerwacja lotu została"],
            'statusVariants'            => ['potwierdzona'],
            'and'                       => ', a',
            'Given names'               => 'Imiona',
            'Surname'                   => 'Nazwisko',
            'Request Update'            => 'Aktualizacja wniosku',
            'Ticket Number'             => 'Numer biletu',
            'Total'                     => 'Łącznie',
        ],
        'ja' => [
            'otaConfNumber'             => ['予約番号', '予約番号：'],
            // 'statusPhrases'             => ["Twoja rezerwacja lotu została"],
            // 'statusVariants'            => ['potwierdzona'],
            // 'and'                       => ', a',
            'Given names'               => '姓',
            'Surname'                   => '名（下の名前）',
            'Request Update'            => '情報更新を申し込む',
            'Airline Booking Reference' => ['航空会社予約番号（PNR）'],
            'Ticket Number'             => 'eチケット番号',
            'Total'                     => '合計',

            // pdf
            'Name Pdf'               => '搭乗者名',
            'Class Pdf'              => 'クラス',
            'Airline Conf Pdf'       => '航空会社予約番号（',
            'Flight Information Pdf' => 'フライト情報',
        ],
    ];

    private $subjects = [
        'zh' => ['機票訂單確認郵件'],
        'de' => ['Flugbuchungsbestätigung'],
        'it' => ['Prenotazione del volo confermata'],
        'pt' => ['Confirmação de reserva de voo'],
        'en' => ['Flight Booking Confirmed'],
        'fr' => ['Réservation de vol confirmée'],
        'es' => ['Reserva de vuelo confirmada'],
        'ja' => ['航空券予約確認書 (お問合せコード:'],
    ];

    private $patterns = [
        // 23 marca 2025 r.
        'date'          => '(?:\b.{4,20}?\b\d{4}\b(?: *r\.)?|\b\d{4}\s*年\s*\d{1,2}\s*月\s*\d{1,2}\s*日)', // Feb 7, 2024    |    7. Feb. 2024    |    2024 年 7 月 31 日
        'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]trip\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".trip.com/") or contains(@href,".trip.com%2F") or contains(@href,"www.trip.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thanks for choosing Trip.com")] | //text()[starts-with(normalize-space(),"Copyright ©") and contains(.,"Trip.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('ItineraryFlight2023' . ucfirst($this->lang));

        $xpathDigits = "contains(translate(.,'0123456789','∆∆∆∆∆∆∆∆∆∆'),'∆')";
        $xpathTime = 'contains(translate(translate(.," ",""),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆")';

        $otaConfirmations = array_values(array_unique(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('otaConfNumber'))}]/following::text()[normalize-space()][1]", null, '/^[A-Z\d]{5,}$/'))));

        if (count($otaConfirmations) === 1) {
            $otaConfirmationTitle = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('otaConfNumber'))}][last()]", null, true, '/^(.+?)[\s:：]*$/u');
            $email->ota()->confirmation($otaConfirmations[0], $otaConfirmationTitle);
        }

        $f = $email->add()->flight();

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]*({$this->opt($this->t('statusVariants'))})(?:\s*[，,.;:!?]|\s+{$this->opt($this->t('and'))}\s|$)/iu"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $f->general()->status($status);
        }

        $routes = [];

        $xpathSegPoint = "(count(*[normalize-space()])=2 and *[1][{$xpathTime}] and *[5][normalize-space()])";
        $xpathSegment = "{$xpathSegPoint} and following-sibling::tr[normalize-space()][1][count(*[normalize-space()])=1 and *[5][normalize-space()]] and following-sibling::tr[normalize-space()][2][{$xpathSegPoint} or starts-with(normalize-space(),'➤')]";
        $xpathRouteHeader = "{$xpathDigits} and contains(.,'|') and contains(.,'-') and string-length(normalize-space())>6 and following-sibling::tr[{$xpathSegment}]";

        foreach ($this->http->XPath->query("//tr[{$xpathRouteHeader}]") as $routeNode) {
            $routeDB = [];
            $routeDB['headerNode'] = $routeNode;

            $sgmts = [];
            $followingRows = $this->http->XPath->query("following-sibling::tr[normalize-space()]", $routeNode);

            foreach ($followingRows as $row) {
                if ($this->http->XPath->query("self::tr[{$xpathRouteHeader}]", $row)->length > 0) {
                    break;
                }

                if ($this->http->XPath->query("self::tr[{$xpathSegment}]", $row)->length > 0) {
                    $sgmts[] = $row;
                }
            }

            $routeDB['segmentsNodes'] = $sgmts;
            $routes[] = $routeDB;
        }

        $segConfNoAll = [];
        $segConfNoUsedInSegments = [];

        $confsBySegment = [];
        $allConfsUsedInSegment = [];
        $isExistsSegmentsWith2Confs = false;
        $isExistsSegmentsWithoutConf = false;

        $confsSegmentId = [];
        $confsAirline = [];

        foreach ($routes as $routeDB) {
            $routePoints = [];
            $headerText = implode(' ', $this->http->FindNodes("descendant::text()[normalize-space()]", $routeDB['headerNode']));

            if (preg_match("/^{$this->patterns['date']}\s*\|\s*([^\|]{5,})$/u", $headerText, $m)) {
                $routePoints = preg_split('/\s+-\s+/', $m[1]);
            }

            foreach ($routeDB['segmentsNodes'] as $i => $root) {
                $s = $f->addSegment();

                $dateDep = $dateDepShort = $dateDepShortMonthNum = $dateDepShortDayNum = $dateDepShortFormat = null;
                $airlineName = null;
                $preRoots = $this->http->XPath->query("preceding-sibling::tr[normalize-space()][1]", $root);
                $preRoot = $preRoots->length > 0 ? $preRoots->item(0) : null;

                while ($preRoot) {
                    $dateVal = $this->http->FindSingleNode("descendant::text()[normalize-space()][1]", $preRoot);

                    if (preg_match("/^({$this->patterns['date']})(?:\s*\||$)/u", $dateVal, $matches)) {
                        $matches = array_map('trim', $matches);

                        $matches[1] = preg_replace('/\b\s*r\.\s*$/u', '', $matches[1]);

                        $dateDep = strtotime($this->normalizeDate($matches[1]));

                        if (preg_match('/^\d{4}\s*年\s*(\d{1,2})\s*(月)\s*(\d{1,2})\s*(日)$/', $matches[1], $m)) {
                            // 2024 年 7 月 31 日  ->  7月31日
                            $dateDepShort = $m[1] . $m[2] . $m[3] . $m[4];
                        } elseif (preg_match('/^(?<month>[[:alpha:]]+)\s+(?<day>\d{1,2})(?:\s*,\s*\d{2,4})?$/u', $matches[1], $m)
                            || preg_match('/^(?<day>\d{1,2}\.?)\s+(?:de\s+)?(?<month>[[:alpha:]]+)[\.]?(?:(?:\s+de)?\s+\d{2,4})?$/iu', $matches[1], $m)
                        ) {
                            // January 29, 2024  ->  Jan 29
                            // 4 de janeiro de 2024  ->  4 jan
                            $dateDepShort = $m[1] . ' ' . $m[2];
                            $dateDepShortDayNum = $m['day'];
                            $dateDepShortMonthNum = MonthTranslate::$MonthNames[$this->lang][mb_strtolower($m['month'])] ?? null;
                            $dateDepShortFormat = ($m[1] === $m['day']) ? '%day% %month%' : '%month%' . ' %day%';
                        }

                        break;
                    }

                    $preRoots = $this->http->XPath->query("preceding-sibling::tr[normalize-space()][1]", $preRoot);
                    $preRoot = $preRoots->length > 0 ? $preRoots->item(0) : null;
                }

                $timeDep = $this->http->FindSingleNode("*[1]", $root, true, "/^{$this->patterns['time']}/");

                if ($dateDep && $timeDep) {
                    $s->departure()->date(strtotime($timeDep, $dateDep));
                }

                $airportDep = $this->http->FindSingleNode("*[5]", $root);

                if (preg_match($pattern = "/^(?<name>.{2,}?)\s+T[-\s]*(?<terminal>[A-Z\d]|\d+[A-Z]?)$/", $airportDep, $m)) {
                    // Parigi Charles de Gaulle T2D
                    $s->departure()->name($m['name'])->terminal($m['terminal']);
                } else {
                    $s->departure()->name($airportDep);
                }

                if ($airportDep) {
                    $s->departure()->noCode();
                }

                $flightText = implode("\n", $this->http->FindNodes("following-sibling::tr[normalize-space()][1]/descendant::text()[normalize-space()]", $root));

                if (preg_match("/^\s*(?<fullName>.+\s)?(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<number>\d+)(?:\s*•|$)/", $flightText, $m)) {
                    $s->airline()->name($m['name'])->number($m['number']);
                    $airlineName = $m['fullName'];
                }

                if (preg_match("/•[ ]*(\d[.std小時分鐘hora mins\d]+)$/imu", $flightText, $m)) {
                    $s->extra()->duration($m[1]);
                }

                $dateArr = $timeArr = null;
                $timeArrVal = implode(' ', $this->http->FindNodes("following-sibling::tr[normalize-space() and not(starts-with(normalize-space(),'➤'))][2]/*[1]/descendant::text()[normalize-space()]", $root));

                if (preg_match("/^(?<date>{$this->patterns['date']})\s+(?<time>{$this->patterns['time']})/u", $timeArrVal, $m)) {
                    $dateArr = strtotime($this->normalizeDate($m['date']));
                    $timeArr = $m['time'];
                } elseif (preg_match("/^{$this->patterns['time']}/u", $timeArrVal, $m)) {
                    $dateArr = $dateDep;
                    $timeArr = $m[0];
                }

                if ($dateArr && $timeArr) {
                    $s->arrival()->date(strtotime($timeArr, $dateArr));
                }

                $airportArr = $this->http->FindSingleNode("following-sibling::tr[normalize-space() and not(starts-with(normalize-space(),'➤'))][2]/*[5]", $root);

                if (preg_match($pattern, $airportArr, $m)) {
                    $s->arrival()->name($m['name'])->terminal($m['terminal']);
                } else {
                    $s->arrival()->name($airportArr);
                }

                if ($airportArr) {
                    $s->arrival()->noCode();
                }

                $segRoute = array_key_exists($i, $routePoints) && array_key_exists($i + 1, $routePoints)
                ? $routePoints[$i] . ' - ' . $routePoints[$i + 1] : null;

                if (!$segRoute || !$dateDepShort) {
                    $this->logger->debug('Fields from segment header not found!');
                    $f->addSegment(); // for 100% fail

                    continue;
                }

                $bookingReferenceHeader = [
                    $segRoute . ' • ' . $dateDepShort,
                    $segRoute . ' • ' . $dateDepShort . '.',
                ];

                if ($dateDepShortMonthNum !== null && $dateDepShortDayNum !== null) {
                    $keys = array_keys(MonthTranslate::$MonthNames[$this->lang], $dateDepShortMonthNum);

                    foreach ($keys as $k) {
                        $bookingReferenceHeader[] = $segRoute . ' • ' . str_replace(['%day%', '%month%'], [$dateDepShortDayNum, $k], $dateDepShortFormat);
                        $bookingReferenceHeader[] = $segRoute . ' • ' . str_replace(['%day%', '%month%'], [$dateDepShortDayNum, $this->mb_ucfirst($k)], $dateDepShortFormat);
                        $bookingReferenceHeader[] = $segRoute . ' • ' . str_replace(['%day%', '%month%'], [$dateDepShortDayNum, $k . '.'], $dateDepShortFormat);
                        $bookingReferenceHeader[] = $segRoute . ' • ' . str_replace(['%day%', '%month%'], [$dateDepShortDayNum, $this->mb_ucfirst($k) . '.'], $dateDepShortFormat);
                    }
                }
                $bookingReferenceHeader = array_unique($bookingReferenceHeader);

                if ($this->http->XPath->query("//*[{$this->eq($bookingReferenceHeader)}]")->length === 0
                    && strtotime('00:00', $s->getDepDate()) !== strtotime('00:00', $s->getArrDate())
                ) {
                    if (preg_match('/^(\d+)(\D*)$/', $dateDepShortDayNum, $m)) {
                        $dateDepShortDayNum = (string) ((int) $m[1] + 1) . $m[2];
                    }

                    $bookingReferenceHeader = [];

                    if ($dateDepShortMonthNum !== null && $dateDepShortDayNum !== null) {
                        $keys = array_keys(MonthTranslate::$MonthNames[$this->lang], $dateDepShortMonthNum);

                        foreach ($keys as $k) {
                            $bookingReferenceHeader[] = $segRoute . ' • ' . str_replace(['%day%', '%month%'], [$dateDepShortDayNum, $k], $dateDepShortFormat);
                            $bookingReferenceHeader[] = $segRoute . ' • ' . str_replace(['%day%', '%month%'], [$dateDepShortDayNum, $this->mb_ucfirst($k)], $dateDepShortFormat);
                            $bookingReferenceHeader[] = $segRoute . ' • ' . str_replace(['%day%', '%month%'], [$dateDepShortDayNum, $k . '.'], $dateDepShortFormat);
                            $bookingReferenceHeader[] = $segRoute . ' • ' . str_replace(['%day%', '%month%'], [$dateDepShortDayNum, $this->mb_ucfirst($k) . '.'], $dateDepShortFormat);
                        }
                    }
                }

                $xpathBookingReference = "count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Airline Booking Reference'))}]";

                $bookingReferences = array_values(array_unique(array_merge(
                    array_filter($this->http->FindNodes("//tr[ *[1][{$this->eq($bookingReferenceHeader)}] ]/following-sibling::tr[normalize-space()][1]/*[1]/descendant-or-self::*[{$xpathBookingReference}]/*[normalize-space()][2]", null, '/^[A-Z\d]{5,}$/')),
                    array_filter($this->http->FindNodes("//tr[ *[3][{$this->eq($bookingReferenceHeader)}] ]/following-sibling::tr[normalize-space()][1]/*[3]/descendant-or-self::*[{$xpathBookingReference}]/*[normalize-space()][2]", null, '/^[A-Z\d]{5,}$/'))
                )));

                if (count($bookingReferences) === 1) {
                    $confsSegmentId[$s->getId()] = ['conf' => $bookingReferences[0], 'title' => $airlineName];
                } elseif (count($bookingReferences) > 1) {
                    foreach ($bookingReferences as $br) {
                        $confsAirline[$br][] = $airlineName;
                    }
                } else {
                    $isExistsSegmentsWithoutConf = true;
                }
            }
        }

        $allConfs = array_unique(array_filter($this->http->FindNodes("//*[{$xpathBookingReference}]/*[normalize-space()][2]", null, '/^[A-Z\d]{5,}$/')));

        $pdfConfs = [];

        if ($isExistsSegmentsWithoutConf === true) {
            $pdfs = $parser->searchAttachmentByName('.*pdf');

            foreach ($pdfs as $pdf) {
                $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (empty($textPdf)) {
                    continue;
                }

                foreach (self::$dictionary as $lang => $dict) {
                    if (empty($dict['Name Pdf']) || empty($dict['Class Pdf']) || empty($dict['Airline Conf Pdf']) || empty($dict['Flight Information Pdf'])) {
                        continue;
                    }
                    $textPdf = $this->cutText($dict['Name Pdf'], $dict['Flight Information Pdf'], $textPdf);

                    if (preg_match("/^\s*{$this->opt($dict['Name Pdf'])} {2,}{$this->opt($dict['Class Pdf'])} {2,}(?:.* {2,})?{$this->opt($dict['Airline Conf Pdf'])}/u", $textPdf)
                        && preg_match_all("/^.+ {2,}([A-Z\d]{5,7})$/m", $textPdf, $m)
                    ) {
                        $allConfs = array_unique(array_merge($allConfs, $m[1]));

                        break 2;
                    }
                }
            }
        }

        // $this->logger->debug('$allConfs = '.print_r( $allConfs,true));
        // $this->logger->debug('array_column($confsSegmentId, \'conf\') = '.print_r( array_column($confsSegmentId, 'conf'),true));
        // $this->logger->debug('array_diff = '.print_r( array_diff($allConfs, array_column($confsSegmentId, 'conf')),true));
        // $this->logger->debug('$confsSegmentId = '.print_r( $confsSegmentId,true));
        // $this->logger->debug('$confsAirline = '.print_r( $confsAirline,true));
        if (!empty($confsSegmentId) && empty($confsAirline) && $isExistsSegmentsWithoutConf === false && empty(array_diff($allConfs, array_column($confsSegmentId, 'conf')))) {
            // для каждого сегмента есть один номер подтверждения и нет номеров неизвестно к чему относящихся
            foreach ($f->getSegments() as $seg) {
                $seg->airline()
                    ->confirmation($confsSegmentId[$seg->getId()]['conf']);
            }
            $f->general()->noConfirmation();
        } else {
            // case 1 - номера, относящиеся к сегменту добавляются к сегменту, остальные в общий спимок

            if (!empty($confsSegmentId)) {
                foreach ($f->getSegments() as $seg) {
                    if (!empty($confsSegmentId[$seg->getId()])) {
                        $seg->airline()
                            ->confirmation($confsSegmentId[$seg->getId()]['conf']);
                    }
                }
            }

            // добавление номеров для сегментов с несколькими номера
            foreach ($confsAirline as $conf => $title) {
                if (!in_array($conf, array_column($confsSegmentId, 'conf'))) {
                    $f->general()->confirmation($conf, implode(', ', array_filter(array_unique($title))));
                }
            }

            // добавление номеров, которые есть в файле, но непонятно к какому сегменту относится
            $addConfs = array_diff($allConfs, array_column($confsSegmentId, 'conf'), array_keys($confsAirline));

            foreach ($addConfs as $br) {
                $f->general()->confirmation($br, $this->http->FindSingleNode("(//*[{$this->eq($this->t('Airline Booking Reference'))}])[1]"));
            }

            if (!empty($confsSegmentId) && empty($confsAirline) && empty($addConfs)) {
                $f->general()->noConfirmation();
            }
            // } else {
        //     // case 2 - если хотя бы для одного сегмента отсутствует номер или их два, но все номера записывать в общий список номеров
        //
        //     foreach ($confsSegmentId as $cs) {
        //         $confsAirline[$cs['conf']][] = $cs['title'];
        //     }
        //
        //     $this->logger->debug('$confsAirline = '.print_r( $confsAirline,true));
        //
        //     // добавление номеров для сегментов с несколькими номера
        //     foreach ($confsAirline as $conf => $title) {
        //         $f->general()->confirmation($conf, implode(', ', array_unique(array_filter($title))));
        //     }
        //
        //     // добавление намеров, которые есть в файле на неопределено к какому сегменту относится
        //     $addConfs = array_diff($allConfs, array_keys($confsAirline));
        //     foreach ($addConfs as $br) {
        //         $f->general()->confirmation($br, $this->http->FindSingleNode("(//*[{$this->eq($this->t('Airline Booking Reference'))}])[1]"));
        //     }
        }

        if (empty($confsSegmentId) && empty($confsAirline) && empty($allConfs)
            && empty(array_filter($this->http->FindNodes("//tr[not(.//tr[normalize-space()])][{$this->contains($this->t('Given names'))} or {$this->contains($this->t('Surname'))}]/following::text()[contains(., ' • ')]/following::text()[normalize-space()][position() < 5]", null, '/^[A-Z\d]{5,}$/')))
        ) {
            // нет ни одного номера подтверждения
            $f->general()->noConfirmation();
        }

        $travellers = [];
        $passengerNameRecords = $this->http->FindNodes("//tr[not(.//tr[normalize-space()])][{$this->contains($this->t('Given names'))} or {$this->contains($this->t('Surname'))}]");

        foreach ($passengerNameRecords as $record) {
            $passengerName = $this->parsePassengerName($record);

            if ($passengerName) {
                $travellers[] = $passengerName;
            }
        }

        $tickets = [];
        $ticketRows = $this->http->XPath->query("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Ticket Number'))}] ]");

        foreach ($ticketRows as $tktRow) {
            $passengerText = $this->http->FindSingleNode("ancestor::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][{$this->contains($this->t('Given names'))} or {$this->contains($this->t('Surname'))}][1]", $tktRow);
            $passengerName = $this->parsePassengerName($passengerText);
            $ticket = $this->http->FindSingleNode("*[normalize-space()][2]", $tktRow, true, "/^{$this->patterns['eTicket']}$/");

            if ($ticket && !in_array($ticket, $tickets)) {
                $f->issued()->ticket($ticket, false, $passengerName);
                $tickets[] = $ticket;
            }
        }

        if (count($travellers) > 0) {
            $f->general()->travellers($travellers, true);
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)
            || preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+?)$/u', $totalPrice, $matches)
        ) {
            // $ 1,471.80    |    R$ 3.371,25    |    62,12 €
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $email->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function mb_ucfirst($string)
    {
        return mb_strtoupper(mb_substr($string, 0, 1)) . mb_substr($string, 1);
    }

    private function parsePassengerName(?string $s): ?string
    {
        $passengerName = null;

        $s = preg_replace([
            "/[\s(]*{$this->opt($this->t('Request Update'))}[)\s]*$/i",
        ], '', $s);

        if (preg_match("/^(?<name>{$this->patterns['travellerName']})[\s(]*{$this->opt($this->t('Given names'))}[)\s]*(?<surname>{$this->patterns['travellerName']})[\s(]*{$this->opt($this->t('Surname'))}[)\s]*$/iu", $s, $m)
            || preg_match("/^(?<surname>{$this->patterns['travellerName']})[\s(]*{$this->opt($this->t('Surname'))}[)\s]*(?<name>{$this->patterns['travellerName']})[\s(]*{$this->opt($this->t('Given names'))}[)\s]*$/iu", $s, $m)
        ) {
            $passengerName = $m['name'] . ' ' . $m['surname'];
        }

        return $passengerName;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (empty($phrases['otaConfNumber']) || $this->http->XPath->query("//*[{$this->contains($phrases['otaConfNumber'])}]")->length === 0) {
                continue;
            }

            if (!empty($phrases['Airline Booking Reference'])
                && $this->http->XPath->query("//*[{$this->contains($phrases['Airline Booking Reference'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }

            if (!empty($phrases['Given names']) && !empty($phrases['Surname'])
                && $this->http->XPath->query("//text()[{$this->contains($phrases['Given names'])}][{$this->contains($phrases['Surname'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^([[:alpha:]]+)\s+(\d{1,2})\s*,\s*(\d{4})$/u', $text, $m)) {
            // Feb 13, 2024
            $month = $m[1];
            $day = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^(\d{1,2})[.\s]+(?:de\s+)?([[:alpha:]]+)(?:\s+de)?[.\s]+(\d{4})$/u', $text, $m)) {
            // 4 jan 2024    |    4 de janeiro de 2024    |    8. August 2024
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^\b(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日$/', $text, $m)) {
            // 2024 年 7 月 31 日
            $year = $m[1];
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
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'HKD' => ['HK$'],
            'TWD' => ['NT$'],
        ];

        if ($string === '円' && $this->lang == 'ja') {
            return 'JPY';
        }

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function cutText($start, $end, $text)
    {
        if (empty($start) && empty($end) || empty($text)) {
            return false;
        }
        $result = false;

        if ($start === 0) {
            $result = $text;
        } elseif (is_string($start)) {
            $result = strstr($text, $start);
        } elseif (is_array($start)) {
            $positions = [];

            foreach ($start as $i => $st) {
                $pos = strpos($text, $st);

                if ($pos !== false) {
                    $positions[] = $pos;
                }
            }

            if (!empty($positions)) {
                $result = substr($text, min($positions));
            }
        }

        if ($result === false) {
            return false;
        }

        $text = $result;
        $result = false;

        if ($end === 0) {
            $result = $text;
        } elseif (is_string($end)) {
            $result = strstr($text, $end, true);
        } elseif (is_array($end)) {
            $positions = [];

            foreach ($end as $i => $st) {
                $pos = strpos($text, $st);

                if ($pos !== false) {
                    $positions[] = $pos;
                }
            }

            if (!empty($positions)) {
                $result = substr($text, 0, min($positions));
            }
        }

        return $result;
    }
}
