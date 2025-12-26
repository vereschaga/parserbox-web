<?php

namespace AwardWallet\Engine\tripair\Email;

use AwardWallet\Engine\MonthTranslate;

class FlightBookingInfo extends \TAccountChecker
{
    public $mailFiles = "tripair/it-11497374.eml, tripair/it-11636592.eml, tripair/it-11658778.eml, tripair/it-11765607.eml, tripair/it-11784203.eml, tripair/it-12197280.eml, tripair/it-5677069.eml, tripair/it-5695543.eml, tripair/it-5698370.eml, tripair/it-5706688.eml, tripair/it-5714539.eml, tripair/it-5717780.eml, tripair/it-5784252.eml, tripair/it-5784253.eml, tripair/it-5784442.eml, tripair/it-5818119.eml, tripair/it-5910248.eml, tripair/it-5910251.eml, tripair/it-5992166.eml, tripair/it-5992169.eml, tripair/it-6018067.eml, tripair/it-6018077.eml, tripair/it-6095263.eml, tripair/it-6557456.eml, tripair/it-6557836.eml, tripair/it-7584204.eml, tripair/it-7645577.eml, tripair/it-8782428.eml, tripair/it-8782432.eml, tripair/it-8824041.eml, tripair/it-8824055.eml, tripair/it-8865131.eml, tripair/it-8865134.eml, tripair/it-8976105.eml";

    public $reBody = [
        'fi'  => [['Lentoyhtiön tiedot lennostasi', 'Varaustunnus'], 'Täydellinen nimi'],
        'es'  => [['Localizador de registro de la aerolínea', 'El código de reserva es'], 'Nombre completo'],
        'en'  => [['Airline Record Locator', 'The reservation code is'], 'Complete Name'],
        'en2' => [['Airline Record Locator', 'The reservation code is', 'Tripair Reference Code'], 'Title Name Surname'],
        'de'  => [['Ihr Buchungscode ist', 'Buchungscode der Fluggesellschaft'], 'Nachname/Vorname'],
        'de2' => [['Ihr Buchungscode ist', 'Buchungscode der Fluggesellschaft'], 'Titel Vorname Nachname'],
        'nl'  => [['Record Locator Luchtvaartmaatschappij', 'De reserveringscode is'], 'Volledige Naam'],
        'nl2' => [['Record Locator Luchtvaartmaatschappij', 'De reserveringscode is'], 'Titel Naam Achternaam'],
        'fr'  => [['Le code de réservation est', 'Numéro de réservation de la compagnie aérienne'], 'Nom entier'],
        'pt'  => [['Localizador de Registo da Companhia Aérea', 'O código de reserva é'], 'Nome Completo'],
        'da'  => [['Flyselskabets record locator-kode', 'Billetnumrene vil snarest blive sendt'], 'Fuldstændigt navn'],
        'tr'  => [['Rezervasyon kodu:', 'Havaalanı Kimlik Numarası'], 'Tam Adı'],
        'tr2' => [['Rezervasyon kodu:', 'Havaalanı Kimlik Numarası'], 'Başlık Ad Soyad'],
        'it'  => [["Codici d'identificazione della compagnia aerea", 'Il codice di prenotazione è', 'Codici d&#39;identificazione della compagnia aerea', 'Il codice di prenotazione è'], 'Nome completo'],
        'it2' => [["Codici d'identificazione della compagnia aerea", 'Il codice di prenotazione è', 'Codici d&#39;identificazione della compagnia aerea', 'Il codice di prenotazione è'], 'Titolo Nome Cognome'],
        'hu'  => [["Foglalás száma", "Légitársaság nyilvántartási száma"], 'Teljes név'],
        'ko'  => [["약 코드", "항공편 예약 번호"], '성을 포함한 이름'],
        'ko2' => [["약 코드", "Tripair 참조 코드"], '승객 세부사항'],
        'cs'  => [["Identifikátor letecké společnosti", "Rezervační kód"], 'Celé jméno'],
        'el'  => [["Κωδικός Κράτησης Αεροπορικής"], 'Eπώνυμο/Όνομα'],
    ];

    public $lang = '';
    public $pdf;
    public static $dict = [
        'fi' => [
            'Lentoyhtiön tiedot lennostasi' => ['Lentoyhtiön tiedot lennostasi', 'Varaustunnus', 'Record Locator Luchtvaartmaatschappij'],
            'E-ticket Numbers'              => 'Sähköiset lippunumerot',
            //			'Partenza' => '',
            //			'Ritorno' => '',
            //			'Titolo Nome Cognome' => '',
            //			'Biglietto elettronico' => '',
            //            'Tripair Reference Code' => '',
            //            'Your e-tickets will be emailed to you within the next 24 hours' => '',
            //            'Flight operated by:' => '',
        ],
        'es' => [
            'Täydellinen nimi'              => 'Nombre completo',
            'Lentoyhtiön tiedot lennostasi' => ['Localizador de registro de la aerolínea', 'El código de reserva es'],
            'veloittama hinta'              => 'Cargo de',
            'E-ticket Numbers'              => 'Números del billete',
            //			'Partenza' => '',
            //			'Ritorno' => '',
            //			'Titolo Nome Cognome' => '',
            //			'Biglietto elettronico' => '',
            //            'Tripair Reference Code' => '',
            //            'Your e-tickets will be emailed to you within the next 24 hours' => '',
            //            'Flight operated by:' => '',
        ],
        'en' => [
            'Täydellinen nimi'                                               => 'Complete Name',
            'Lentoyhtiön tiedot lennostasi'                                  => ['Airline Record Locator', 'The reservation code is'],
            'veloittama hinta'                                               => ['Total charge', 'Charge from'],
            'Sähköiset lippunumerot'                                         => 'E-ticket Numbers',
            'E-ticket Numbers'                                               => 'E-ticket Numbers',
            'return'                                                         => 'return', //Airline Record Locator return:, E-ticket Numbers return:
            'Partenza'                                                       => 'Departure',
            'Ritorno'                                                        => 'Return',
            'Titolo Nome Cognome'                                            => 'Title Name Surname',
            'Biglietto elettronico'                                          => 'E-ticket',
            'Tripair Reference Code'                                         => 'Tripair Reference Code',
            'Your e-tickets will be emailed to you within the next 24 hours' => 'Your e-tickets will be emailed to you within the next 24 hours',
            //            'Flight operated by:' => '',
        ],
        'de' => [
            'Täydellinen nimi'              => 'Nachname/Vorname',
            'Lentoyhtiön tiedot lennostasi' => ['Ihr Buchungscode ist', 'Buchungscode der Fluggesellschaft'],
            'veloittama hinta'              => ['verlangte Gebühren'],
            'E-ticket Numbers'              => 'Ticketnummern der Fluggesellschaft',
            'Partenza'                      => 'Hinflug',
            'Ritorno'                       => 'Rückflug',
            'Titolo Nome Cognome'           => 'Titel Vorname Nachname',
            'Biglietto elettronico'         => 'E-Ticket',
            'Tripair Reference Code'        => 'Tripair Referenz-Code',
            //            'Your e-tickets will be emailed to you within the next 24 hours' => '',
            'Flight operated by:' => 'Flug betrieben von:',
            'Cost'                => 'Preisübersicht',
        ],
        'nl' => [
            'Täydellinen nimi'              => 'Volledige Naam',
            'Lentoyhtiön tiedot lennostasi' => ['Record Locator Luchtvaartmaatschappij', 'De reserveringscode is'],
            'veloittama hinta'              => 'Kosten Altair Travel S.A',
            'E-ticket Numbers'              => 'Ticketnummers',
            'Partenza'                      => 'Vertrek',
            //			'Ritorno' => '',
            'Titolo Nome Cognome' => 'Titel Naam Achternaam',
            //			'Biglietto elettronico' => '',
            //            'Tripair Reference Code' => '',
            //            'Your e-tickets will be emailed to you within the next 24 hours' => '',
            //            'Flight operated by:' => '',
        ],
        'fr' => [
            'Täydellinen nimi'              => 'Nom entier',
            'Lentoyhtiön tiedot lennostasi' => ['Le code de réservation est', 'Numéro de réservation de la compagnie aérienne'],
            'veloittama hinta'              => 'Frais perçus par Altair Travel S.A',
            'E-ticket Numbers'              => ['Numéro(s) du billet d’avion', 'Numéro(s) du billet d\'avion'],
            //			'Partenza' => '',
            //			'Ritorno' => '',
            //			'Titolo Nome Cognome' => '',
            //			'Biglietto elettronico' => '',
            //            'Tripair Reference Code' => '',
            //            'Your e-tickets will be emailed to you within the next 24 hours' => '',
            //            'Flight operated by:' => '',
        ],
        'pt' => [
            'Täydellinen nimi'              => 'Nome Completo',
            'Lentoyhtiön tiedot lennostasi' => ['Localizador de Registo da Companhia Aérea', 'O código de reserva é'],
            'veloittama hinta'              => 'Cobrado pela Altair Travel S.A. (Tripair.com)',
            'E-ticket Numbers'              => 'Números de bilhetes electrónicos',
            //			'Partenza' => '',
            //			'Ritorno' => '',
            //			'Titolo Nome Cognome' => '',
            //			'Biglietto elettronico' => '',
            //            'Tripair Reference Code' => '',
            //            'Your e-tickets will be emailed to you within the next 24 hours' => '',
            //            'Flight operated by:' => '',
        ],
        'da' => [
            'Täydellinen nimi'              => 'Fuldstændigt navn',
            'Lentoyhtiön tiedot lennostasi' => ['Flyselskabets record locator-kode', 'Reservationskoden er:'],
            'veloittama hinta'              => 'Afgift fra Altair Travel S.A. (Tripair)',
            'E-ticket Numbers'              => 'E-billetnumre',
            //			'Partenza' => '',
            //			'Ritorno' => '',
            //			'Titolo Nome Cognome' => '',
            //			'Biglietto elettronico' => '',
            //            'Tripair Reference Code' => '',
            //            'Your e-tickets will be emailed to you within the next 24 hours' => '',
            //            'Flight operated by:' => '',
        ],
        'tr' => [
            'Täydellinen nimi'              => 'Tam Adı',
            'Lentoyhtiön tiedot lennostasi' => ['Rezervasyon kodu', 'Havaalanı Kimlik Numarası'],
            'veloittama hinta'              => "Ücreti Altair Travel S.A.'dan (Tripair) al",
            'E-ticket Numbers'              => 'Bilet Numaraları',
            'return'                        => 'dönüş',
            'Partenza'                      => 'Kalkış',
            'Ritorno'                       => 'Dönüş',
            'Titolo Nome Cognome'           => 'Başlık Ad Soyad',
            'Biglietto elettronico'         => 'E-bilet',
            //            'Tripair Reference Code' => '',
            //            'Your e-tickets will be emailed to you within the next 24 hours' => '',
            //            'Flight operated by:' => '',
        ],
        'it' => [
            'Täydellinen nimi'              => 'Nome completo',
            'Lentoyhtiön tiedot lennostasi' => ["Codici d'identificazione della compagnia aerea", 'Il codice di prenotazione è'],
            'veloittama hinta'              => ['Addebito da parte di', 'Totale'],
            'E-ticket Numbers'              => 'Numeri dei biglietti',
            'Partenza'                      => 'Partenza',
            'Ritorno'                       => 'Ritorno',
            'Titolo Nome Cognome'           => 'Titolo Nome Cognome',
            'Biglietto elettronico'         => 'Biglietto elettronico',
            //            'Tripair Reference Code' => '',
            //            'Your e-tickets will be emailed to you within the next 24 hours' => '',
            //            'Flight operated by:' => '',
        ],
        'hu' => [
            'Täydellinen nimi'              => 'Teljes név',
            'Lentoyhtiön tiedot lennostasi' => ["A retúrjegyéhez tartozó foglalási szám a következő", 'Foglalás száma', 'Légitársaság nyilvántartási száma'],
            'veloittama hinta'              => ['Összes '],
            'E-ticket Numbers'              => ['E-jegyek számai', 'E-retúrjegy számai'],
            'return'                        => 'visszaúton',
            'returnTicket'                  => 'E-retúrjegy számai',
            //			'Partenza' => '',
            //			'Ritorno' => '',
            //			'Titolo Nome Cognome' => '',
            //			'Biglietto elettronico' => '',
            //            'Tripair Reference Code' => '',
            //            'Your e-tickets will be emailed to you within the next 24 hours' => '',
            //            'Flight operated by:' => '',
        ],
        'ko' => [
            'Täydellinen nimi'              => '성을 포함한 이름',
            'Lentoyhtiön tiedot lennostasi' => ["약 코드", "항공편 예약 번호", '예약 코드'],
            'veloittama hinta'              => ['총계 ', 'Altair Travel S.A.트리페어에서 부과한 금액'],
            'E-ticket Numbers'              => '전자티켓 번호',
            //			'Partenza' => '',
            //			'Ritorno' => '',
            'Titolo Nome Cognome'   => '성인',
            'Biglietto elettronico' => '전자 항공권',
            //            'Tripair Reference Code' => '',
            //            'Your e-tickets will be emailed to you within the next 24 hours' => '',
            //            'Flight operated by:' => '',
        ],
        'cs' => [
            'Täydellinen nimi'              => 'Celé jméno',
            'Lentoyhtiön tiedot lennostasi' => ["Identifikátor letecké společnosti", "Rezervační kód"],
            'veloittama hinta'              => ['Celkem (Altair Travel S.A. -Tripair)'],
            'E-ticket Numbers'              => 'Čísla e-letenek',
            //			'Partenza' => '',
            //			'Ritorno' => '',
            //			'Titolo Nome Cognome' => '',
            //			'Biglietto elettronico' => '',
            //            'Tripair Reference Code' => '',
            //            'Your e-tickets will be emailed to you within the next 24 hours' => '',
            //            'Flight operated by:' => '',
        ],
        'el' => [
            'Täydellinen nimi'              => 'Eπώνυμο/Όνομα',
            'Lentoyhtiön tiedot lennostasi' => ['Κωδικός Κράτησης Αεροπορικής'],
            'veloittama hinta'              => ['Χρέωση από'],
            'Sähköiset lippunumerot'        => 'Αριθμοί Ηλεκτρονικών Εισιτηρίων',
            //			'Partenza' => '',
            //			'Ritorno' => '',
            //			'Titolo Nome Cognome' => '',
            //			'Biglietto elettronico' => '',
            //            'Tripair Reference Code' => '',
            //            'Your e-tickets will be emailed to you within the next 24 hours' => '',
            //            'Flight operated by:' => '',
        ],
    ];

    private static $supportedProviders = ['tripair', 'petas'];

    private $providers = [
        "body" => [
            "tripair" => "//img[contains(@alt,'Tripair') or contains(@alt,'tripair') ] | //a[contains(@href,'tripair')]",
            "petas"   => "//img[contains(@alt,'Petas.gr') or contains(@alt,'petas') ] | //a[contains(@href,'petas.gr')]",
        ],
        "header" => ["tripair" => "tripair", "petas" => "petas"],
        "from"   => ["tripair" => "tripair.com", "petas" => "petas.gr"],
    ];


    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = true;
        $body = html_entity_decode($parser->getHTMLBody());
        $this->lang = "";
        $this->detectLang($body);

        if (empty($this->lang)) {
            $body = iconv('utf-8', 'windows-1251//IGNORE', $body);
            $this->detectLang($body);

            if (!empty($this->lang)) {
                $this->http->SetEmailBody($body);
            }
        }

        $its = $this->parseEmail();
        $w = $this->t('veloittama hinta');

        if (!is_array($w)) {
            $w = [$w];
        }
        $rule = implode(' or ', array_map(function ($s) {
            return "contains(normalize-space(),\"{$s}\")";
        }, $w));

        $node = $this->http->FindSingleNode("//text()[{$rule}]/ancestor::td[1]/following-sibling::td[1]");
        $tot = $this->getTotalCurrency($node);

        if (count($its) == 1) {
            $its[0]['TotalCharge'] = $tot['Total'];
            $its[0]['Currency'] = $tot['Currency'];
            $result = [
                'parsedData' => ['Itineraries' => $its],
                'emailType'  => "FlightBookingInfo" . ucfirst($this->lang),
            ];
        } else {
            $result = [
                'parsedData' => [
                    'Itineraries' => $its,
                    'TotalCharge' => [
                        'Amount'   => $tot['Total'],
                        'Currency' => $tot['Currency'],
                    ],
                ],
                'emailType' => "FlightBookingInfo" . ucfirst($this->lang),
            ];
        }

        foreach ($this->providers['body'] as $provider => $xpath) {
            if ($this->http->XPath->query($xpath)->length > 0) {
                $result['providerCode'] = $provider;

                break;
            }
        }

        return $result;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        foreach ($this->providers['body'] as $provider => $xpath) {
            if ($this->http->XPath->query($xpath)->length > 0) {
                $body = html_entity_decode($this->http->Response['body']);
                $this->detectLang($body);

                if (empty($this->lang)) {
                    $body = iconv('utf-8', 'windows-1251//IGNORE', $body);
                    $this->detectLang($body);
                }

                if (empty($this->lang)) {
                    continue;
                } else {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectLang($body)
    {
        foreach ($this->reBody as $lang => $reBody) {
            if (is_array($reBody[0])) {
                if (stripos($body, $reBody[1]) !== false) {
                    foreach ($reBody[0] as $re) {
                        if (stripos($body, $re) !== false) {
                            $this->lang = substr($lang, 0, 2);

                            break 2;
                        }
                    }
                }
            } else {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    break;
                }
            }
        }

        return true;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->providers['header'] as $provider => $text) {
            if (stripos($headers["subject"], $text) !== false && stripos($headers["subject"],
                    'Flight Booking Information') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->providers['from'] as $provider => $text) {
            if (stripos($from, $text) !== false) {
                return true;
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
        $provs = 2;
        $cnt = $provs * count(self::$dict);

        return $cnt;
    }

    public static function getEmailProviders()
    {
        return self::$supportedProviders;
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail()
    {
        $pax = $this->http->FindNodes('//text()[contains(.,"' . $this->t('Täydellinen nimi') . '")]/ancestor::*[1]/following-sibling::*[normalize-space(.)]');

        if (empty($pax)) {
            $pax = $this->http->FindNodes('//text()[contains(.,"' . $this->t('Täydellinen nimi') . '")]/ancestor::*[2]/following-sibling::*[contains(normalize-space(.),"/")]');
        }

        if (empty($pax)) {
            $pax = array_filter($this->http->FindNodes('//text()[contains(.,"' . $this->t('Titolo Nome Cognome') . '")]/ancestor::tr[1]', null, "#.*?\d+\s*:\s*(.+?)\s*\(.+#"));
        }

        if (empty($pax)) {
            $pax = $this->http->FindNodes('//tr[./td[1][normalize-space() = "' . $this->t('Titolo Nome Cognome') . '"]]/ancestor::table[1]/following-sibling::table/descendant::tr[1]/ancestor::*[1]/tr[normalize-space()]/td[1]', null, "#^\s*(?:mr|ms)\s*([a-z\- ]{4,})#i");
        }
        $recLocs = [];
        $recLocsStr = array_filter($this->http->FindNodes("//text()[" . $this->contains($this->t('Lentoyhtiön tiedot lennostasi')) . "]/following::text()[string-length(normalize-space(.))>4][1]", null, "#([A-Z\d]{5,7}([ ,]+[A-Z\d]{5,7})*)#"));

        foreach ($recLocsStr as $recLoc) {
            $recLocs = array_merge($recLocs, explode(",", $recLoc));
        }
        $tripNumber = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Tripair Reference Code')) . "]/following::text()[1]", null, "#^\s*(\d{5,})\s*$#i");

        $TicketNumbers = [];
        $TicketNumbersStr = $this->http->FindNodes("//text()[" . $this->contains($this->t('E-ticket Numbers')) . "]/following::text()[string-length(normalize-space(.))>4][1]", null, "#([\d]{5,}([ ,]+[\d]{5,})*)#");

        if (empty($TicketNumbersStr[0])) {
            $TicketNumbersStr = $this->http->FindNodes("//text()[" . $this->contains($this->t('E-ticket Numbers')) . "][1]", null, "#:\s*([\d]{5,}([ ,]+[\d]{5,})*)#");
        }

        if (!empty($TicketNumbersStr[0])) {
            $TicketNumbersStr = str_replace(' ', '', $TicketNumbersStr);

            foreach ($TicketNumbersStr as $value) {
                $TicketNumbers = array_merge($TicketNumbers, explode(",", $value));
            }
        }

        $airlines = array_unique($this->http->FindNodes("//img[contains(@alt,'time') or ./following::text()[string-length(normalize-space(.))>4][1][contains(.,':') and string-length(translate(.,'1234567890',''))<3]]/ancestor::table[1]/ancestor::tr[1]/following-sibling::tr[contains(.,':') and contains(.,',')]/descendant::table[1]/following::table[1]/descendant::tr[1]/td[1]/descendant::text()[normalize-space(.)][1]"));

        $xpath = "//img[contains(@alt,'time') or ./following::text()[string-length(normalize-space(.))>4][1][contains(.,':') and string-length(translate(normalize-space(.),'1234567890',''))<3]]/ancestor::table[1][contains(.,':') and contains(.,',')]";

        if ($this->http->XPath->query($xpath)->length == 0) {
            $xpath = "//img[contains(@alt,'time') or ./following::text()[string-length(normalize-space(.))>4][1][contains(.,':') and string-length(translate(normalize-space(.),'1234567890',''))<3]]/ancestor::table[1]/ancestor::tr[1]/following-sibling::tr[contains(.,':') and contains(.,',')]/descendant::table[1]";
        }

        if (0 === $this->http->XPath->query($xpath)->length) {
            $xpath = "//img[contains(@alt,'time') or contains(@altx,'time') or ./following::text()[string-length(normalize-space(.))>4][1][contains(.,':') and string-length(translate(normalize-space(.),'1234567890',''))<3]]/ancestor::table[1]/following-sibling::table[contains(.,':') and contains(.,',')][descendant::img[not(contains(@src, 'return'))]]";
        }
        $flights = $this->http->XPath->query($xpath);

        if (0 === $flights->length) {
            $this->logger->debug("Segments did not found by xpath: {$xpath}");
        }
        $airs = [];
        $this->logger->debug($xpath);

        if (!empty($recLocs) && count($recLocs) !== count($airlines)) {
            // it-7645577
            if (count($recLocs) == 2 && !empty($this->http->FindSingleNode("//text()[(" . $this->contains($this->t('Lentoyhtiön tiedot lennostasi')) . ") and contains(.,'" . $this->t('return') . "')]"))) {
                $tic = [];
                $tic = array_map('trim', explode(",", $this->http->FindSingleNode("//text()[(" . $this->contains($this->t('E-ticket Numbers')) . ") and ( contains(.,'" . $this->t('return') . "') or contains(.,'" . $this->t('returnTicket') . "') )]/following::text()[string-length(normalize-space(.))>4][1]")));
                $ticket[$recLocs[1]] = $tic;
                $ticket[$recLocs[0]] = array_diff($TicketNumbers, $tic);

                foreach ($flights as $root) {
                    if (!empty($this->http->FindSingleNode(".//ancestor::tr[2]/preceding-sibling::tr//img/@src[contains(.,'return')]", $root))) {
                        $airs[$recLocs[1]][] = $root;
                    } else {
                        $airs[$recLocs[0]][] = $root;
                    }
                }
            } else {
                $recLoc = array_shift($recLocs);

                foreach ($flights as $root) {
                    $airs[$recLoc][] = $root;
                }
            }
        } elseif (!empty($recLocs)) {
            $getRL = array_combine($airlines, $recLocs);

            foreach ($flights as $root) {
                $airline = $this->http->FindSingleNode("./following::table[1]/descendant::tr[1]/td[1]/descendant::text()[normalize-space(.)][1]", $root);
                $airs[$getRL[$airline]][] = $root;
            }
        } else {
            $departRl = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Lentoyhtiön tiedot lennostasi')) . "]/following::text()[position()<5][" . $this->contains($this->t('Partenza')) . "]/following::text()[string-length(normalize-space(.))>4][1]", null, "#([A-Z\d]{5,7}([ ,]+[A-Z\d]{5,7})*)#");
            $returnRl = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Lentoyhtiön tiedot lennostasi')) . "]/following::text()[position()<5][" . $this->contains($this->t('Ritorno')) . "]/following::text()[string-length(normalize-space(.))>4][1]", null, "#([A-Z\d]{5,7}([ ,]+[A-Z\d]{5,7})*)#");

            foreach ($flights as $root) {
                $direction = $this->http->FindSingleNode("./preceding-sibling::table[" . $this->contains($this->t('Partenza')) . " or " . $this->contains($this->t('Ritorno')) . "][1]", $root);

                if (stripos($direction, $this->t('Partenza')) !== false) {
                    $airs[$departRl][] = $root;
                } elseif (stripos($direction, $this->t('Ritorno')) !== false) {
                    $airs[$returnRl][] = $root;
                } else {
                    $airs[''][] = $root;
                }
            }
        }

        $its = [];

        foreach ($airs as $rl => $roots) {
            $it = ['Kind' => 'T', 'TripSegments' => []];

            if (empty($rl)) {
                $it['RecordLocator'] = CONFNO_UNKNOWN;
            } else {
                $it['RecordLocator'] = $rl;
            }
            $it['Passengers'] = $pax;

            if (!empty($tripNumber)) {
                $it['TripNumber'] = $tripNumber;
            }

            if (isset($ticket[$it['RecordLocator']])) {
                $it['TicketNumbers'] = $ticket[$it['RecordLocator']];
            } else {
                if (!empty($TicketNumbers)) {
                    $it['TicketNumbers'] = $TicketNumbers;
                }
            }
            $firstDepCode = null;

            foreach ($roots as $i => $root) {
                $seg = [];

                $regexp = "#(?<name1>.+?)\s+(?<code>[A-Z]{3})(?:,\s+(?<name2>.+))?\s+(?:Terminal:[ ]*(?<term>.*))?\s*(?<date>\d{2}:\d+[\s\S]+?\d{4})\s*(?:\n|$)#";
                $node = implode("\n", array_filter($this->http->FindNodes(".//td[1]//text()", $root)));

                if (preg_match($regexp, $node, $m)) {
                    $seg['DepName'] = trim($m[1]) . ' - ' . trim($m[3]);
                    $seg['DepCode'] = $m[2];

                    if (!empty($m[4])) {
                        $seg['DepartureTerminal'] = trim($m[4]);
                    }
                    $seg['DepDate'] = strtotime($this->normalizeDate($m[5]));
                }
                $node = implode("\n", $this->http->FindNodes(".//td[2]//text()", $root));

                if (preg_match($regexp, $node, $m)) {
                    $seg['ArrName'] = trim($m[1]) . ' - ' . trim($m[3]);
                    $seg['ArrCode'] = $m[2];

                    if (!empty($m[4])) {
                        $seg['ArrivalTerminal'] = trim($m[4]);
                    }

                    $seg['ArrDate'] = strtotime($this->normalizeDate($m[5]));
                }

                if (!empty($seg['DepCode']) && !empty($seg['ArrCode'])) {
                    $tickets = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t('Biglietto elettronico')) . "][not(ancestor::tr[1]/following-sibling::tr)]/ancestor::table[1]/following::table[1]//tr[not(.//tr) and td[1][contains(.,'" . $seg['DepCode'] . '-' . $seg['ArrCode'] . "')]]/td[2]", null, "#^\s*([\d]{7,})\s*$#"));

                    if (!empty($tickets)) {
                        $it['TicketNumbers'] = array_merge($it['TicketNumbers'] ?? [], $tickets);
                    }

                    if (empty($tickets) && !empty($firstDepCode) && !empty($seg['ArrCode'])) {
                        $tickets = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t('Biglietto elettronico')) . "][not(ancestor::tr[1]/following-sibling::tr)]/ancestor::table[1]/following::table[1]//tr[not(.//tr) and td[1][contains(.,'" . $firstDepCode . '-' . $seg['ArrCode'] . "')]]/td[2]", null, "#^\s*([\d]{7,})\s*$#"));

                        if (empty($tickets)) {
                            $tickets = array_filter(array_unique($this->http->FindNodes("//text()[" . $this->eq($this->t('Biglietto elettronico')) . "][not(ancestor::tr[1]/following-sibling::tr)]/ancestor::table[1]/following::table[1]//tr[not(.//tr)]/td[2]", null, "#^\s*([\d]{7,})\s*$#")));
                        }

                        if (!empty($tickets)) {
                            $it['TicketNumbers'] = array_merge($it['TicketNumbers'] ?? [], $tickets);
                            $it['TicketNumbers'] = array_filter(array_unique($it['TicketNumbers']));
                        }
                    }

                    if ($i === 0 && !empty($seg['DepCode'])) {
                        $firstDepCode = $seg['DepCode'];
                    }
                }

                $seg['Duration'] = $this->http->FindSingleNode(".//td[3]", $root);
                $node = $this->http->FindSingleNode("./following::table[1]/descendant::tr[1]/td[1]/descendant::text()[normalize-space(.)][2]", $root);

                if (empty($node)) {
                    $node = $this->http->FindSingleNode("./descendant::tr/td[1]/descendant::text()[normalize-space()][last()]", $root);
                }

                if (preg_match("#([A-Z\d]{2})\s*?(\d+)#", $node, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }
                $seg['Aircraft'] = $this->http->FindSingleNode("./following::table[1]/descendant::tr[1]/td[2]/descendant::text()[normalize-space(.)][2]", $root);
                $seg['Cabin'] = $this->http->FindSingleNode("./following::table[1]/descendant::tr[1]/td[2]/descendant::text()[normalize-space(.)][1][not(" . $this->contains($this->t('Price Analysis')) . ")][not(contains(normalize-space(), ':'))][not(" . $this->contains($this->t('Cost')) . ")]", $root);

                if (empty($seg['AirlineName']) && empty($seg['AirlineName']) && empty($seg['Duration'])) {
                    $seg['Duration'] = $this->http->FindSingleNode(".//td[2]//img[contains(@alt,'time')]/following::text()[normalize-space()][1]", $root);
                    $seg['Cabin'] = $this->http->FindSingleNode(".//td[2]//img[contains(@alt,'time')]/following::text()[normalize-space()][2]", $root);
                    $seg['Aircraft'] = $this->http->FindSingleNode(".//td[2]//img[contains(@alt,'time')]/following::text()[normalize-space()][3]", $root);

                    $node = $this->http->FindSingleNode(".//td[1]/descendant::text()[normalize-space()][last()]", $root);

                    if (preg_match("#^\s*([A-Z\d]{2})\s*(\d{1,5})\s*$#", $node, $m)) {
                        $seg['AirlineName'] = $m[1];
                        $seg['FlightNumber'] = $m[2];
                    } elseif (preg_match("#^\s*\(\s*" . $this->preg_implode($this->t("Flight operated by:")) . "\s*([A-Z\d]{2})\s*\d{1,5}\s*\)\s*$#", $node, $m)) {
                        $seg['Operator'] = $m[1];
                        $node = $this->http->FindSingleNode(".//td[1]/descendant::text()[normalize-space()][last()]/preceding::text()[normalize-space()][1]", $root);

                        if (preg_match("#^\s*([A-Z\d]{2})\s*(\d{1,5})\s*$#", $node, $m)) {
                            $seg['AirlineName'] = $m[1];
                            $seg['FlightNumber'] = $m[2];
                        }
                    }
                }

                if (empty($seg['Duration'])) {
                    $duration = $this->http->FindSingleNode("./descendant::tr/td[2]/descendant::text()[contains(normalize-space(), 'h ') and contains(normalize-space(), 'm')]", $root);

                    if (!empty($duration)) {
                        $seg['Duration'] = $duration;
                    }
                }

                if (empty($seg['Aircraft'])) {
                    $aircraft = $this->http->FindSingleNode("./descendant::img[contains(@alt, 'time')]/following::text()[normalize-space()][3]", $root, true, '/^\s*([\d]{3})$/');

                    if (!empty($aircraft)) {
                        $seg['Aircraft'] = $aircraft;
                    }
                }

                if (empty($seg['BookingClass'])) {
                    $bookingCode = $this->http->FindSingleNode("./descendant::img[contains(@alt, 'time')]/following::text()[normalize-space()][2]", $root, true, '/^\s*([A-Z]{1})$/');

                    if (!empty($bookingCode)) {
                        $seg['BookingClass'] = $bookingCode;
                    }
                }

                if (empty($seg['Cabin'])) {
                    $cabin = $this->http->FindSingleNode(".//td[2]//img[contains(@alt,'time')]/following::text()[normalize-space()][2]", $root, true, '/^\s*[A-Z][a-z].+/');

                    if (!empty($cabin)) {
                        $seg['Cabin'] = $cabin;
                    }
                }

                $it['TripSegments'][] = $seg;
            }
            $its[] = $it;
        }

        return $its;
    }

    private function normalizeDate($date)
    {
        $in = [  // 17:25 keskiviikko, 25 tammi  2017
            "#^(\d+:\d+)\s+\S+\s+(\d+)\s*([^\d\s]+)\s*(\d+)#",
            "#^(\d+:\d+)\s+\S+\s+(\d+)\s+(\d+)[^\d\s]*\s+(\d+)#",
        ];
        $out = [
            "$2 $3 $4 $1",
            "$2.$3.$4 $1",
        ];

        return $this->dateStringToEnglish(preg_replace($in, $out, $date));
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[^\d\s]*)\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[^\d\s]*)#", $node, $m)) {
            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $this->currency($m['c']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function contains($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

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

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            'TL' => 'TRY',
            'HK$'=> 'HKD',
            'Ft' => 'HUF',
            '₩'  => 'KRW',
            'Kč' => 'CZK',
            'SG$'=> 'SGD',
            '€'  => 'EUR',
            '$'  => 'USD',
            '£'  => 'GBP',
            'RM' => 'MYR',
            '₹'  => 'INR',
            'Fr.'=> 'CHF',
            'AU$'=> 'AUD',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
