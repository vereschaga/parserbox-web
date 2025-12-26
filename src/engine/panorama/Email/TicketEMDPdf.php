<?php

namespace AwardWallet\Engine\panorama\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Common\Train;
use AwardWallet\Schema\Parser\Email\Email;

// parse HTML in amadeus/ETicketReceipt(multi-prov)

class TicketEMDPdf extends \TAccountChecker
{
    public $mailFiles = "panorama/it-12048867.eml, panorama/it-12294061.eml, panorama/it-12402037.eml, panorama/it-12505522.eml, panorama/it-197009855.eml, panorama/it-246882585.eml, panorama/it-262015161.eml, panorama/it-29343252.eml, panorama/it-40302454.eml, panorama/it-411900514.eml, panorama/it-418917424.eml, panorama/it-419694295.eml, panorama/it-49583506.eml, panorama/it-5116403.eml, panorama/it-536340950.eml, panorama/it-5543301.eml, panorama/it-61189287.eml, panorama/it-61851206.eml, panorama/it-63307661.eml, panorama/it-6787282.eml, panorama/it-6787283.eml, panorama/it-681693099.eml, panorama/it-695854906.eml, panorama/it-77677062.eml, panorama/it-777640795-it.eml, panorama/it-7907496.eml, panorama/it-7907636.eml, panorama/it-7995762.eml, panorama/it-817563407.eml, panorama/it-828400310.eml, panorama/it-8323840.eml, panorama/it-8328815.eml, panorama/it-8344597.eml, panorama/it-840405614.eml, panorama/it-8425953.eml, panorama/it-843445771.eml, panorama/it-860047277.eml";

    // Hard-code airports list from PDF-text. Need first line and full airport name!
    protected $airports = [
        // panorama
        'IVANO FRANKIVSK INTERNATIONAL',
        'IVANO FRANKIVSK',
        'TEL AVIV YAFO BEN GURION INTL',
        'TEL AVIV YAFO BEN GURION',
        'DNIPROPETROVSK INTERNATIONAL',
        'VINNYTSIA GAVRYSHIVKA INTL',
        'ZAPORIZHZHIA MOKRAYA INTL',
        'FRANKFURT FRANKFURT INTL',
        'NEW YORK JOHN F KENNEDY INTL',
        'NEW YORK JOHN F KENNEDY',
        'NEW YORK JOHN F',
        'STUTTGART STUTTGART',
        'KHARKIV OSNOVA INTL',
        'KIEV BORYSPIL INTL',
        'LVIV INTERNATIONAL',
        'VENICE MARCO POLO',
        'ISTANBUL ATATURK',
        'PRAGUE RUZYNE',
        'MUNICH MUNICH',
        'KIEV BORYSPIL INTL',
        'BERLIN TEGEL',
        'REYKJAVIK KEFLAVIK INTL',
        'FAROE ISLANDS VAGAR',

        // airmaroc
        'MALAGA MALAGA AIRPORT',
        'CASABLANCA MOHAMMED V',
        // bambooair
        'PHU QUOC ISLAND INTERNATIONAL',
        'PHU QUOC ISLAND',
        'DA NANG INTERNATIONAL',
        // flysaa
        'JOHANNESBURG O.R. TAMBO INTL',
        'JOHANNESBURG O.R. TAMBO',
        'JOHANNESBURG O.R.',
        'CAPE TOWN CAPE TOWN INTL',
        'DURBAN KING SHAKA INTL',
        'KIMBERLEY KIMBERLEY/ZA',
        'NELSPRUIT KRUGER',
        'HOEDSPRUIT AFB',
        'MAUN MAUN/BW',
        'RICHARDS BAY RICHARDS BAY/',
        'RICHARDS BAY RICHARDS BAY/ZA',
        'ANTANANARIVO IVATO INTL',
        'SKUKUZA SKUKUZA/ZA',
        'LUBUMBASHI LUANO INTL',
        'LIVINGSTONE LIVINGSTONE/ZM',
        'PIETERMARITZBURG',
        'MAPUTO MAPUTO INTL',
        'NDOLA NDOLA/ZM',
        // malaysia
        'MELBOURNE MELBOURNE AIRPORT',
        'KUALA LUMPUR KUALA LUMPUR',
        'KUCHING INTERNATIONAL',
        // lotpair
        'LOS ANGELES LOS ANGELES',
        'BUDAPEST LISZT FERENC',
        'WARSAW FREDERIC CHOPIN',
        'WARSAW FREDERIC',
        'TBILISI INTERNATIONAL',
        'VILNIUS VILNIUS INTL',
        'LONDON CITY AIRPORT',
        'MILAN MALPENSA',
        'POZNAN LAWICA',
        'KRAKOW JOHN PAUL II',
        'VIENNA VIENNA',
        // kuwait
        'JEDDAH KING ABDULAZIZ INTL',
        'JEDDAH KING ABDULAZIZ',
        'ISLAMABAD BENAZIR BHUTTO',
        'ISLAMABAD BENAZIR',
        'PARIS CHARLES DE GAULLE',
        'BANGKOK SUVARNABHUMI',
        'COLOMBO BANDARANAIKE',
        'KUWAIT KUWAIT INTL',
        'BRUSSELS BRUSSELS',
        'ROME FIUMICINO',
        'GENEVA GENEVA',
        'MUSCAT MUSCAT',
        'MILAN LINATE',
        // czech
        'BRUSSELS BRUSSELS AIRPORT',
        // aircaraibes
        'POINTE A PITRE POLE CARAIBES',
        'PARIS ORLY',
        'LUANDA 4 DE FEVEREIRO',
        // flybe
        'BELFAST GEORGE BEST CITY',
        'BELFAST GEORGE BEST',
        'BIRMINGHAM BIRMINGHAM',
        'MANCHESTER MANCHESTER',
        'MANCHESTER',
        'EXETER EXETER/GB',
        'GLASGOW GLASGOW INTL',
        'AMSTERDAM SCHIPHOL',
        'LYON SAINT EXUPERY',
        'EDINBURGH AIRPORT',
        'CARDIFF CARDIFF',
        'NOTTINGHAM EAST',
        'DUESSELDORF',
        'SOUTHAMPTON',
        'LUXEMBOURG',
        'NEWCASTLE INTERNATIONAL',
        // aviancataca
        'LOS ANGELES LOS ANGELES INTL',
        'CARTAGENA RAFAEL NUNEZ INTL',
        'CARTAGENA RAFAEL NUNEZ',
        'SAN FRANCISCO SAN FRANCISCO',
        'GUATEMALA CITY LA AURORA',
        'SAO PAULO GUARULHOS INTL',
        'SAN SALVADOR EL SALVADOR',
        'BOGOTA EL DORADO INTL',
        'SAN FRANCISCO SAN',
        'MIAMI MIAMI INTL',
        // algerie
        'CONSTANTINE MOHAMED',
        'ALGIERS HOUARI',
        // aegean
        'ATHENS ATHENS INT E VENIZELOS',
        'KERKYRA IOANNIS',
        // qmiles
        'SHIRAZ SHAHID DASTGHAIB',
        'TEHRAN IMAM KHOMEINI',
        'DOHA HAMAD',
        // aeroplan
        'SAULT STE MARIE, Sault Ste Marie/On/Ca',
        'SAULT STE MARIE, Sault Ste Marie/On',
        'SAULT STE MARIE, Sault Ste Marie/',
        'LIBERIA, D.Oduber Quiros Intl (LIR)',
        'NEW YORK, Newark Liberty Intl (EWR)',
        'NEW YORK, Newark Liberty Intl',
        'DUBLIN, Dublin International (DUB)',
        'TAIPEI, Taiwan Taoyuan Intl (TPE)',
        'ISTANBUL, Istanbul Airport (IST)',
        'SANTIAGO, A Merino Benitez (SCL)',
        'PANAMA CITY, Tocumen Intl (PTY)',
        'TORONTO, Lester B. Pearson Intl',
        'JOHANNESBURG , O.R. Tambo Intl',
        'OTTAWA, Macdonald Cartier Intl',
        'BUENOS AIRES, Pistarini (EZE)',
        'ADDIS ABABA, Bole Intl (ADD)',
        'RIO DE JANEIRO, Galeao A.C',
        'SINGAPORE, Changi (SIN)',
        'ORLANDO, Orlando Intl (MCO)',
        'HALIFAX, Stanfield Intl (YHZ)',

        //srilankan
        'MUMBAI CHHATRAPATI S',
        'COLOMBO BANDARANAIKE',

        // tahitinui
        'DELHI INDIRA GANDHI INTL',
        'TAHITI FAAA',
        'BORA BORA MOTU MUTE',
        'LONDON HEATHROW',
        'PHUKET PHUKET INTL',
        'SYDNEY KINGSFORD',
        'SYDNEY KINGSFORD SMITH',
        'HAT YAI INTERNATIONAL',
        'COPENHAGEN KASTRUP',
        'TAIPEI TAIWAN TAOYUAN INTL',

        // vistara
        'BENGALURU KEMPEGOWDA INTL',
        'MUMBAI CHHATRAPATI S MAHARAJ',

        // unknown provider
        'MARSEILLE PROVENCE',
        'NICE COTE D AZUR',
        'BASTIA PORETTA',

        //israel
        'BOSTON EDWARD L LOGAN INTL',
        'TAMPA TAMPA INTL',
        'LISBON AIRPORT',
        'TEL AVIV YAFO',
        'SAO PAULO',
        'NEW YORK',
        'WINNIPEG',
        'TORONTO',
        'VIENNA',
        'BOSTON',
        'TAMPA',
        'MIAMI',
        // egyptair
        'ISTANBUL ISTANBUL AIRPORT',
        'CAIRO CAIRO INTL',
        // thaiair
        'MANILA NINOY AQUINO INTL',
        'NAGOYA CHUBU CENTRAIR',
        'TOKYO NARITA INTL',
        'KARACHI JINNAH INTL',
        'BUSAN GIMHAE INTL',
        'VIENTIANE WATTAY INTL',
        'JAKARTA SOEKARNO HATTA INTL',
        'SEOUL INCHEON INTERNATIONAL',
        'DENPASAR-BALI NGURAH RAI',
        'OSAKA KANSAI INTERNATIONAL',
        // thaismile
        'KAOHSIUNG KAOHSIUNG INTL',
        // itaairways
        'ROME FIUMICINO',
        'MALTA LUQA INTERNATIONAL',
        // airlink
        'VICTORIA FALLS INTERNATIONAL',
        // flyerbonus
        'PHUKET PHUKET INTL',
        'CHIANG MAI CHIANG MAI INTL',
        'KO SAMUI KO SAMUI',
        'BEIRUT RAFIC HARIRI INTL',
        'LARNACA LARNACA',
        // malmo
        'STOCKHOLM BROMMA',
        'MALMO MALMO/SE',
        // kenyaair
        'DAR ES SALAAM JULIUS NYERERE INTL',
        'DAR ES SALAAM JULIUS',
        'RALEIGH DURHAM INTERNATIONAL',
        'RALEIGH DURHAM',
        'KILIMANJARO KILIMANJARO INTL',
        'KILIMANJARO KILIMANJARO',
        'MAHE ISLAND SEYCHELLES INTL',
        'MAHE ISLAND SEYCHELLES',
        'NAIROBI JOMO KENYATTA INTL',
        'NAIROBI JOMO KENYATTA',
        // mabuhay
        'CEBU MACTAN INTERNATIONAL',
        'DEL CARMEN SIARGAO SAYAK',
        'MANILA NINOY AQUINO INTL',
        'BUTUAN BANCASI',
        'PHNOM PENH INTERNATIONAL',
        'ILOILO INTERNATIONAL',
        'URGENCH INTERNATIONAL',
        'TASHKENT INTERNATIONAL',
    ];
    private $lang = '';
    private $lang2 = '';
    private $reFrom = [
        '@amadeus.com', '@kuwaitairways.com', 'hop.fr', 'csa.cz', 'aircaraibes.com', 'norwegian.com'
    ];
    private $providerCode;
    private $detectLangHTML = [
        'en' => [
            'Please find attached your Electronic Ticket Receipt',
            'Please find attached your Electronic Ticket-EMD Receipt',
            'Please find attached your Electronic Ticket EMD Receipt',
            'Attached in this email is the Electronic Ticket Receipt – EMD for your booking',
            'Vous trouverez ci-joint une copie de votre confirmation de réservation officielle et de',
            'Please find information about your flight in the attached file. This is a receipt for your electronic ticket',
            'Attached is a copy of your official Booking Confirmation and Receipt.',
            'Please find enclosed your electronic ticket. We recommend that you print this document',
            'At check in you must present your travel documents',
            'Attached in this email is the Electronic Ticket Receipt for your booking.',
            'We are pleased to attach your electronic receipt for your travel.',
            'Carry-on and additional bag fees will be higher at airport counter and gate',
            'ELECTRONIC TICKET RECEIPT',
        ],
        'pt' => ['DETALHES DE PAGAMENTO'],
    ];
    private $detectPdf = [
        'TICKET RECEIPT',
        '/ ELECTRONIC TICKET',
        'REÇU DE BILLET ÉLECTRONIQUE', 'Reçu du ticket électronique',
        'ELECTRONIC TICKET ITINERARY',
        'TICKET CREDIT RECEIPT',
        '電子機票收據',
        'Electronic ticket receipt',
        'OBSERVAÇÕES DE BILHETE ELETRÔNICO',
        'Ricevuta del biglietto elettronico', // it
        'Recibo de Bilhete Eletrônico', // pt
        'TRAVEL DOCUMENT',
        'Elektronischer Ticketbeleg', // de
        'Reisedokumente', // de
        '電子機票收據', // zh
    ];
    private $reSubject = [
        'Flight schedule change',
        'Your Electronic Ticket-EMD Receipt',
    ];

    private $terminalTitle = ['Terminal', 'Terminale', '航站', '终端'];

    private static $dictionary = [
        // en first
        'en' => [
            "Arrival"               => "Arrival",
            "Class"                 => ["Class", "Cabin (Booking Class)", "Cabin"],
            "Duration"              => ["Duration", "Flight duration"],
            "Frequent flyer number" => ["Frequent flyer number", "Loyalty programme number"],
            "paymentStart"          => ["PAYMENT DETAILS", "Payment details", "FARE DETAILS", "Form of payment", "Form of Payment"],
            "cancelledTicket"       => ["Your cancellation is successfully processed"],
            "Total Amount"          => ["Total Amount", "Total amount (Inclusive of VAT)"],
        ],
        "fr" => [ // it-681693099.eml
            "Arrival"     => "Arrivée",
            "From"        => "De",
            "Class"       => "Classe",
            "Operated by" => "Opéré par",
            // 'Aircraft type' => '',
            "Duration"    => "Durée",
            "Seat"        => "Siège",
            //            "Number of stops" => "",
            "Frequent flyer number" => ["Numéro de membre du programme de fidélité", "Nº de carte fidélité"],
            "paymentStart"          => ["DÉTAILS DU PAIEMENT", "détails du paiement", "DÉTAILS TARIFAIRES", "Mode de paiement"],
            "Total Amount"          => "Montant total",
            'Base Fare'             => 'Tarif',
            //            "cancelledTicket" => "",
        ],
        "is" => [
            "Arrival"     => "Koma",
            "From"        => "Frá",
            "Class"       => "Klassi",
            "Operated by" => "Flogið av",
            // 'Aircraft type' => '',
            "Duration"    => "Tíð",
            "Seat"        => "Setur",
            //            "Number of stops" => "",
            "Frequent flyer number" => "Nº de carte fidélité",
            "paymentStart"          => ["GJALDSUPPLÝSINGAR", "FERÐASEÐLAPRÍSUR", "Gjaldsháttur"],
            "Total Amount"          => "Samlað upphædd",
            'Base Fare'             => 'Prísur',
            //            "cancelledTicket" => "",
        ],
        "zh" => [
            "Arrival"         => "目的地",
            "From"            => "從",
            "Class"           => ["Cabin", '艙等'],
            "Operated by"     => "營運由",
            // 'Aircraft type' => '',
            "Duration"              => "飛行時間",
            "Seat"                  => "座位",
            "Number of stops"       => "经停次数",
            "Frequent flyer number" => "Loyalty programme number",
            "paymentStart"          => ["票價明細", "票价详细信息"],
            "Total Amount"          => "總額",
            'Base Fare'             => ['Prísur', '票面價', '票面價'],
            //            "cancelledTicket" => "",
        ],
        "pt" => [
            "Arrival"     => "Para",
            "From"        => "De",
            "Class"       => "Classe",
            "Operated by" => "Operado por",
            // 'Aircraft type' => '',
            "Duration"              => "Duração",
            "Seat"                  => "Lugar",
            "Number of stops"       => "Numero de paragens",
            "Frequent flyer number" => "Numero de passageiro frequente",
            "paymentStart"          => ["DETALHES DE PAGAMENTO", "detalhes do pagamento"],
            "Total Amount"          => "Valor Total",
            'Base Fare'             => 'Tarifa',
            //            "cancelledTicket" => "",
        ],
        "de" => [
            "Arrival"     => "Ankunft",
            "From"        => "Von",
            "Class"       => "Klasse",
            "Operated by" => "Betreiber",
            // 'Aircraft type' => '',
            "Duration"              => ["Flugzeit", "Flugdauer"],
            "Seat"                  => "Sitzplatz",
            // "Number of stops"       => ",
            "Frequent flyer number" => ["Treueprogrammnummer", "Reward-Nummer"],
            "paymentStart"          => ["Tarifdetails", "Zahlungsinformationen"],
            "Total Amount"          => "Gesamtsumme",
            'Base Fare'             => 'Ticketpreis',
            //            "cancelledTicket" => "",
        ],
        "it" => [
            "Arrival"     => "Arrivo",
            "From"        => "Da",
            "Class"       => "Classe",
            "Operated by" => "Operato da",
            // 'Aircraft type' => '',
            "Duration"    => "Durata volo",
            "Seat"        => "Posto",
            // "Number of stops" => "",
            "Frequent flyer number" => "Numero programma fedeltà",
            "paymentStart"          => ["DETTAGLI DI PAGAMENTO", "dettagli di pagamento", "Dettagli tariffa", "Forma di pagamento"],
            "Total Amount"          => "Importo totale",
            'Base Fare'             => 'Tariffa',
            // "cancelledTicket" => "",
        ],
        "sv" => [
            "Arrival"     => "Till",
            "From"        => "Från",
            "Class"       => "Bokningsklass",
            "Operated by" => "Trafikeras av",
            // 'Aircraft type' => '',
            "Duration"    => "Restid",
            "Seat"        => "Plats",
            // "Number of stops" => "",
            //"Frequent flyer number" => "",
            //"paymentStart" => ["DETTAGLI DI PAGAMENTO", "dettagli di pagamento", "Dettagli tariffa", "Forma di pagamento"],
            //"Total Amount" => "",
            //'Base Fare' => '',
            // "cancelledTicket" => "",
        ],
    ];

    private $deleteWorlds = [
        'DOCS - PASSENGER/CREW PRIMARY TRAVEL DOCUMENT INFO - CONFIRMED',
        'FQTR - FREQUENT TRAVELLER REDEMPTION - CONFIRMED',
        'FQTV - FREQUENT TRAVELLER INFORMATION - CONFIRMED',
        '/tmp/pdftohtml-.+?\.html',
        'LANG - PASSENGER LANGUAGE INFORMATION - REQUESTED',
        '\s+Terminál /',
        'VGML - VEGETARIAN VEGAN MEAL REQUEST - CONFIRMED',
        'VJML[- ]+VEGETARIAN[ ]+JAIN[ ]+MEAL[ ]+REQUEST[- ]+CONFIRMED',
        'PBAG - WEBSITE EXCESS BAGGAGE IN PIECE - CONFIRMED',
        'PDBG - PREPAID WEIGHT BAGGAGE - CONFIRMED',
        'CHECKED BAG FIRST - CONFIRMED'
    ];

    private $accounts = [];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.+\.pdf');

        if (empty($pdfs)) {
            // it-63307661.eml
            $pdfs = $parser->searchAttachmentByName('.+f');
        }

        if (empty($pdfs)) {
            $pdfs = $parser->searchAttachmentByName('\.pd');
        }

        if (count($pdfs)) {
            // sorting hard-code airports by string length (from long to short)
            usort($this->airports, function ($a, $b) {
                $aLen = mb_strlen($a);
                $bLen = mb_strlen($b);

                if ($aLen === $bLen) {
                    return 0;
                }

                return ($aLen > $bLen) ? -1 : 1;
            });
        }

        $flightsTexts = [];

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToHtml($parser->getAttachmentBody($pdf));

            if (empty($text)) {
                continue;
            }
            $text = $this->normalizeText($text);

            //it-it-536340950.eml - junk
            if (stripos($text, 'ELECTRONIC MISCELLANEOUS DOCUMENT RECEIPT') !== false
            && stripos($text, 'ELECTRONIC TICKET RECEIPT') === false
            && stripos($text, 'Flight duration') === false) {
                $email->setIsJunk(true);

                return $email;
            }

            if ($this->assignProvider($text, $parser->getHeaders()) !== true) {
                $this->logger->debug("Didn't detect provider!");

                continue;
            }

            if (!preg_match("/{$this->opt($this->detectPdf, true)}/", $text)) {
                $this->logger->debug("Didn't detect PDF!");

                continue;
            }

            if ($this->arrikey(substr($text, 0, 1000), ['MISCELLANEOUS DOCUMENT (EMD)']) !== false) {
                continue;
            }
            $f = $email->add()->flight();

            foreach (self::$dictionary as $lang => $dict) {
                if (!empty($dict['Arrival']) && preg_match('/\s+' . $this->opt($dict['Arrival']) . '\s+/s', $text)) {
                    $this->lang = $lang;

                    break;
                }
            }

            foreach (self::$dictionary as $lang => $dict) {
                if ($this->lang !== $lang && !empty($dict['Arrival']) && preg_match('/\s+' . $this->opt($dict['Arrival']) . '\s+/s', $text)) {
                    $this->lang2 = $lang;

                    break;
                }
            }

            if (preg_match("/^(.+?)\n[ ]*AEROPLAN(?: FLIGHT REWARD)? RULES\n/s", $text, $m)) {
                $text = $m[1];
            }

            $header = $this->findCutSection($text, null,
                ['ELECTRONIC TICKET RECEIPT', 'TICKET RECEIPT', 'ELECTRONIC TICKET', 'REÇU DE BILLET ÉLECTRONIQUE', 'Reçu du ticket électronique', 'TICKET CREDIT RECEIPT', '電子機票收據', 'Electronic ticket receipt', 'RECIBO DE BILHETE ELETRÓNICO', 'Ricevuta del biglietto elettronico', 'Recibo de Bilhete Eletrônico', 'TRAVEL DOCUMENT', 'Elektronischer Ticketbeleg', 'REISEDOKUMENT']);

            if (preg_match("/(?:Booking [Rr]ef\:?(?:erence)?|{$this->opt(['BOOKING REFERENCE', 'Código de reserva', 'Booking code', 'Numéro de réservation', 'Référence de la réservation', 'Référence de la', 'Reference du dossier', 'Bíleggingarnr.', 'ﺰﺠﺤﻟا ﻢﻗر', 'Numero di prenotazione', 'Numero di', 'Referência da reserva', 'Bokningsnummer', 'Riferimento prenotazione', 'Buchungsnummer', '預訂參考編號', 'Buchungscode'], true)})(?:\s*\/\s*\S[^:\n]*?\s*[:]+)?\s*:?\s*(?:[A-Z\d]*\/)?([A-Z\d]{5,})\b/u", $header, $matches)) {
                if (!isset($f->getConfirmationNumbers()[0])) {
                    $f->general()->confirmation($matches[1]);
                } elseif (!in_array($matches[1], $f->getConfirmationNumbers()[0])) {
                    $f->general()->confirmation($matches[1]);
                }
            } elseif (preg_match('/(?:Booking [Rr]ef(?:erence)?|Booking code)(?:\s*\/\s*\S[^:\n]*?)?\s*[:]+[ ]*\n[ ]*Ticket [Nn]umber\s*:/', $header, $matches)) {
                $f->general()->noConfirmation();
            }

            if (preg_match('/(?:Passenger|Passager|Passageiro|Guest|Ferðandi|ﺐﻛاﺮﻟا ﻢﺳا|旅客|Passeggero|Passagerare|Gast|Passagier)(?:\s*\/\s*\S[^:\n]*?)?\s*[:]+\s*(.+?)(?: (?:Mrs|Mr|Miss|Ms))?(?:\s*\(.*?\))?(?:\s{3,}|Booking ref|Issuing|\n)/si', $header, $matches)
                || preg_match('/Passenger[\s\S]+\n(.+)\n+Issuing office/iu', $header, $matches)
                || preg_match('/Passenger Name\n+(?:Mrs|Mr|Miss|Ms)?\s*\.\s*(.+)\s+\-\s*ADT/ius', $header, $matches)
            ) {
                $traveller = $this->normalizeTraveller(trim($matches[1], ': '));
                $f->general()->traveller($traveller);
            }

            $patterns['eTicket'] = '\d{3}(?:[ ]{1,2}| ?- ?)?\d{5,}(?:[ ]{1,2}| ?- ?)?\d{1,3}'; // 075-2345005149-02    |    0167544038003-004

            if (preg_match("/{$this->opt(['Ticket number', 'Numéro de billet', 'Ferðaseðlanummar', 'Número do Bilhete', 'Numero Biglietto', 'Biljettnummer', 'E-ticketnummer'], true)}(?:\s*\/\s*\S[^:\n]*?)?\s*[:]+\s*({$patterns['eTicket']})(?:[ ]{2}| ?\n)/i", $header, $matches)
                || preg_match("/({$patterns['eTicket']})\s*\n*{$this->opt(['Ticket number', 'Numéro de billet'], true)}\s*[:\/]*/i", $header, $matches)
                || preg_match("/{$this->opt(['Ticket number', 'Numéro de billet'], true)}\n*({$patterns['eTicket']})\s*\n*\s*[:\/]*/i", $header, $matches)
            ) {
                $travellers = $f->getTravellers();
                $passengerName = count($travellers) === 1 ? array_shift($travellers)[0] : null;
                $f->issued()->ticket(preg_replace('/\s+/', ' ', $matches[1]), false, $passengerName);
            }

            // Issuing date: Dec-02, 2019
            if (preg_match("/{$this->opt(['Issue date', 'Issuing date', 'Date of issue', 'Data di emissione', 'Date', 'Data', 'Datum', 'Ausstellungsdatum'], true)}(?:\s*\/\s*\S[^:\n]*?)?\s*[:]+\s*([-A-z\d ,]{4,}\d+)(?: ?\n|$)/", $header, $matches)
                || preg_match('/\n[ ]*([A-z\d ,]{4,}\d+)\s+(?:Date)\s*:/', $header, $matches)
            ) {
                $f->general()->date2(trim($matches[1]));
            }

            if (preg_match("/({$this->opt($this->t('cancelledTicket'), true)}|Your cancellation is successfully processed)/", $text)
            ) {
                $f->general()
                    ->status('Cancelled')
                    ->cancelled()
                ;
            }

            $this->accounts = [];

            // Segments

            foreach (['Last check-in', 'Arrival', 'Arrivée', '目的地', 'Para', 'Arrivo', 'Till', 'Ankunft'] as $phrase) {
                $textSegment = $this->findCutSection($text, $phrase);

                if ($textSegment) {
                    break;
                }
            }

            // 10:4    ->    ∆∆:∆∆
            // :10    ->    ∆∆:∆∆
            $textSegment = preg_replace(['/^ ?\d{1,2} ?: ?\d ?$/m', '/^ ?: ?\d{2} ?$/m'], '∆∆:∆∆', $textSegment);

            if (strpos($textSegment, 'BAGGAGE POLICY') !== false) {
                $textSegment = $this->re("/^(.+)BAGGAGE POLICY/s", $textSegment);
            }

            $segments = [];

            if (preg_match_all("/[A-Z]{2,}.+?(?:\d+:\d+|∆∆:∆∆).+?(?:number:|Seat|Duration|Flight duration|Durée|{$this->opt($this->t('Seat'))}|{$this->opt($this->t('Duration'))}).+?(?:_|\n ?{$this->opt($this->t('Special Service Request'), true)})/s", $textSegment, $items, PREG_PATTERN_ORDER)) {
                $allSegments = $items[0];

                foreach ($allSegments as $s) {
                    $regexp = "/(?<=^|\n) *[A-Z\W]{3,}(.*\n+){2,7}\n\d{1,2}:\d{2}(.*\n+){1,3}\s*\d{1,2}:\d{2}(.*\n+){1,3}(.+\s*:\s*.+\n+)+/";

                    if (preg_match_all($regexp, $s, $segmentMatches)
                        && count($segmentMatches[0]) > 1
                    ) {
                        $segments = array_merge($segments, $segmentMatches[0]);
                    } else {
                        $segments[] = $s;
                    }
                }
            }

            foreach ($segments as $item) {
                $regexp = "/(?<=\n) *[A-Z\W]{3,}(.*\n+){2,7}\n\d{1,2}:\d{2}(.*\n+){1,3}\s*\d{1,2}:\d{2}(.*\n+){1,3}(.+\s*:\s*.+\n+)+/";

                if (preg_match_all($regexp, $item, $partMatches)
                    && count($partMatches[0]) > 1
                ) {
                    $this->logger->debug('incorrect partitioning into segments');
                    $f->addSegment();
                }

                if (preg_match("/(\bRAIL\b|\bRAILWAY)/", $item)) {
                    /** @var Train $trainIt */
                    if (!isset($trainIt)) {
                        $trainIt = $email->add()->train();
                        $trainIt = $trainIt->fromArray(array_diff_key($f->toArray(), ['segments' => '', '']));
                    }
                    $this->logger->debug('TRAIN');
                    $this->parseSegmentTrain($trainIt, $item);
                    $text .= "\n-=PLUS_TRAIN=-";
                } else {
                    $this->logger->debug('FLIGHT');
                    $this->parseSegment($f, $item);
                }
            }

            unset($trainIt);

            $flightsTexts[] = $text;
        }

        if (count($flightsTexts) === 1) {
            $flightText = array_shift($flightsTexts);
            $this->parsePrice($email, $flightText);
        } else {
            // it-681693099.eml
            $its = $email->getItineraries();

            foreach ($its as $flightIt) {
                /** @var Flight $flightIt */
                if ($flightIt->getType() === 'flight') {
                    $flightText = array_shift($flightsTexts);

                    if (strpos($flightText, '-=PLUS_TRAIN=-') === false) {
                        $this->parsePrice($flightIt, $flightText);
                    }
                }
            }
        }

        $class = explode('\\', __CLASS__);

        if (isset($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }
        $email->setType(end($class) . ucfirst($this->lang) . ucfirst($this->lang2));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public static function getEmailProviders()
    {
        return [
            'cape', 'airlink',
            'panorama', 'airmaroc', 'flysaa', 'kuwait', 'lotpair', 'israel', 'czech', 'aircaraibes', 'flybe', 'aviancataca',
            'algerie', 'eva', 'srilankan', 'malaysia', 'thaismile', 'thaiair', 'vistara', 'aegean', 'malmo',
            'mabuhay', 'qmiles', 'aeroplan', 'flyerbonus', 'luxair', 'tahitinui', 's7', 'jordanian', 'sata',
            'china', 'airindia', 'egyptair', 'amadeus', 'atlanticairways', 'saudisrabianairlin',
            'etihad', 'itaairways', 'hawaiian', 'ethiopian', 'cyprusair', 'bambooair', 'tapportugal',
            'cairo', 'vietnam', 'kenyaair', 'norwegian', 'aircorsica', 'saudisrabianairlin', 'uzair',
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->assignLangHTML()) {
            return true;
        }

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($text)) {
                continue;
            }

            if (stripos($text, 'ELECTRONIC MISCELLANEOUS DOCUMENT RECEIPT') !== false) {
                return true;
            }

            $text = $this->normalizeText($text);

            if (isset($headers['subject']) && $this->assignProvider($text, $parser->getHeaders()) !== true) {
                continue;
            }

            if ($this->arrikey($text, $this->detectPdf) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public function findCutSection($input, $searchStart, $searchFinish = null)
    {
        $inputResult = null;

        if (!empty($searchStart)) {
            $input = mb_strstr($input, $searchStart);
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($input, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($input, $searchFinish, true);
        } else {
            $inputResult = $input;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    protected function assignProvider($textPdf, array $headers): bool
    {
        if (strpos($textPdf, 'Norwegian Air Shuttle') !== false
        ) {
            $this->providerCode = 'norwegian';

            return true;
        }

        if (
            strpos($textPdf, 'saudiairlines.com') !== false
            || strpos($textPdf, 'saudia.com') !== false
        ) {
            $this->providerCode = 'saudisrabianairlin';

            return true;
        }

        if (strpos($textPdf, 'FLYAIRLINK ') !== false
            || stripos($textPdf, 'www.flyairlink.com') !== false
            || stripos($textPdf, 'Thank you for choosing Airlink') !== false
        ) {
            $this->providerCode = 'airlink';

            return true;
        }

        if (stripos($headers['from'], '@flygbra.se') !== false
            || strpos($textPdf, 'BRA SVERIGE AB') !== false
        ) {
            $this->providerCode = 'malmo';

            return true;
        }

        if (false !== stripos($textPdf, 'SAUDI ARABIAN AIRLINES')) {
            $this->providerCode = 'saudisrabianairlin';

            return true;
        }

        if (stripos($textPdf, 'AIR VANUATU') !== false
        ) {
            // no provider for AIR VANUATU
            $this->providerCode = 'amadeus';

            return true;
        }

        if (stripos($textPdf, ' MIAT ') !== false
        ) {
            // no provider for MIAT MONGOLIAN AIRLINES
            $this->providerCode = 'amadeus';

            return true;
        }

        if (stripos($textPdf, 'www.lam.co.mz/') !== false
        ) {
            // no provider for lam.co.mz
            $this->providerCode = 'amadeus';

            return true;
        }

        if (strpos($textPdf, 'UKRAINE INT') !== false
            || stripos($textPdf, 'www.flyuia.com') !== false
        ) {
            $this->providerCode = 'panorama';

            return true;
        }

        if (stripos($textPdf, 'WWW.ROYALAIRMAROC.COM') !== false) {
            $this->providerCode = 'airmaroc';

            return true;
        }

        if (stripos($textPdf, '@bambooairways.com') !== false
            || strpos($textPdf, 'BAMBOO AIRWAYS EC SALES') !== false
            || strpos($textPdf, 'Bamboo Airways may randomly request to verify') !== false
        ) {
            $this->providerCode = 'bambooair';

            return true;
        }

        if (stripos($headers['from'], '@kenya-airways.com') !== false
            || stripos($headers['subject'], 'Kenya Airways') !== false
            || $this->http->XPath->query('//*[contains(normalize-space(),"Kenya Airways wishes you a pleasant trip")]')->length > 0
            || stripos($textPdf, 'Kenya Airways wishes you a very pleasant trip') !== false
        ) {
            $this->providerCode = 'kenyaair';

            return true;
        }

        if ($this->http->XPath->query('//node()[contains(normalize-space(.),"South African Airways Electronic Ticket") or contains(normalize-space(.)," SAA office") or contains(normalize-space(.),"fly with South African Airways")]')->length > 0
            || $this->http->XPath->query('//a[contains(@href,"//www.flysaa.com")]')->length > 0
            || stripos($textPdf, 'booking with South African Airways') !== false
            || stripos($textPdf, 'the South African Airways') !== false
            || stripos($textPdf, 'www.flysaa.com') !== false
            || stripos($textPdf, '@flysaa.com') !== false
        ) {
            $this->providerCode = 'flysaa';

            return true;
        }

        if (isset($headers['subject']) && stripos($headers['subject'], 'Your Kuwait Airways electronic ticket') !== false
            || stripos($textPdf, 'WWW.KUWAITAIRWAYS.COM') !== false
        ) {
            $this->providerCode = 'kuwait';

            return true;
        }

        if (stripos($textPdf, 'www.lot.com') !== false
            || stripos($textPdf, 'Order offer on LOT Shop') !== false
            || strpos($textPdf, 'Pre-Order offer on

LOT Shop') !== false
            || strpos($textPdf, 'LOT.COM') !== false
        ) {
            $this->providerCode = 'lotpair';

            return true;
        }

        if (stripos($headers['from'], '@bangkokair.com') !== false
            || stripos($headers['subject'], 'Bangkok Airways: Ticketed') !== false
            || stripos($textPdf, '@bangkokair.com') !== false
            || stripos($textPdf, 'BANGKOK AIRWAYS CALL CENTER') !== false
            || stripos($textPdf, '+66 (2) 270 6699') !== false
        ) {
            $this->providerCode = 'flyerbonus';

            return true;
        }

        if (stripos($textPdf, 'www.elal.com') !== false
            || preg_match('/[:,]\s*EL\s*AL\s+ISRAEL\s+AIRLINES(?:\s*,|\n)/', $textPdf) > 0
        ) {
            $this->providerCode = 'israel';

            return true;
        }

        if (stripos($headers['from'], 'CZECH AIRLINES ONLINE BOOKING OFFICE') !== false
            || strpos($textPdf, 'CZECH AIRLINES ONLINE BOOKING OFFICE') !== false
            || strpos($textPdf, 'CZECH AIRLINES, INTERNET OFFICE, PRAGUE') !== false
        ) {
            $this->providerCode = 'czech';

            return true;
        }

        if (stripos($headers['from'], 'AIRCARAIBES.COM') !== false
            || strpos($textPdf, 'AIRCARAIBES.COM') !== false
            || stripos($textPdf, 'Air Caraibes wishes you a very pleasant trip') !== false
        ) {
            $this->providerCode = 'aircaraibes';

            return true;
        }

        if (stripos($headers['from'], 'FLYBE LTD') !== false
            || stripos($headers['from'], '@flybe.com') !== false
            || strpos($textPdf, 'FLYBE LTD') !== false
        ) {
            $this->providerCode = 'flybe';

            return true;
        }

        if (stripos($headers['from'], '@avianca.com') !== false
            || $this->http->XPath->query('//node()[contains(.,"@avianca.com")]')->length > 0
            || stripos($textPdf, '@avianca.com') !== false
            || stripos($textPdf, 'https://www.avianca.com') !== false
            || strpos($textPdf, 'AVIANCA ONLINE') !== false || strpos($textPdf, 'AVIANCAONLINE') !== false
            || strpos($textPdf, 'AEROVÍAS DEL CONTINENTE AMERICANO S.A. AVIANCA') !== false
        ) {
            $this->providerCode = 'aviancataca';

            return true;
        }

        if (stripos($textPdf, 'www.airalgerie.dz') !== false) {
            $this->providerCode = 'algerie';

            return true;
        }

        if (strpos($textPdf, ' THAI SMILE ') !== false) {
            $this->providerCode = 'thaismile';

            return true;
        }

        if (strpos($textPdf, ' THAI ') !== false) {
            $this->providerCode = 'thaiair';

            return true;
        }

        if (strpos($textPdf, 'WWW.AIRVISTARA.COM') !== false || strpos($textPdf, 'VISTARA ') !== false) {
            $this->providerCode = 'vistara';

            return true;
        }

        if (strpos($textPdf, 'PHILIPPINEAIRLINES') !== false || strpos($textPdf,
                'www.philippineairlines.com') !== false) {
            $this->providerCode = 'mabuhay';

            return true;
        }

        if (false !== stripos($textPdf, 'Air Canada Flight')
            || false !== stripos($textPdf, 'Aeroplan Contact Centre')
            || false !== stripos($textPdf, 'Air Canada Reservations')
            || false !== stripos($textPdf, 'Réservations d\'Air Canada')
            || false !== stripos($textPdf, 'Centre Aéroplan:')
            || false !== stripos($textPdf, 'aeroplan.com')) {
            $this->providerCode = 'aeroplan';

            return true;
        }

        if (
            stripos($textPdf, 'www.cyprusairways.com') !== false
        ) {
            $this->providerCode = 'cyprusair';

            return true;
        }

        if ($headers['subject'] && stripos($headers['subject'], 'Your Luxair e-ticket receipt') !== false
            || stripos($textPdf, 'LUXAIR') !== false
        ) {
            $this->providerCode = 'luxair';

            return true;
        }

        if (stripos($textPdf, 'AIR TAHITI ') !== false
        ) {
            $this->providerCode = 'tahitinui';

            return true;
        }

        if (stripos($textPdf, 'S7 TRAVEL RETAIL') !== false
        ) {
            $this->providerCode = 's7';
        }

        if (
            stripos($textPdf, 'RJ IBE OFFICE AMM, ROYAL JORDANIAN') !== false
            || stripos($textPdf, 'WWW.RJ.COM') !== false
        ) {
            $this->providerCode = 'jordanian';

            return true;
        }

        if (
            stripos($textPdf, 'WWW.AZORESAIRLINES') !== false
        ) {
            $this->providerCode = 'sata';

            return true;
        }

        if (
            stripos($textPdf, '.china-airlines.com') !== false
        ) {
            $this->providerCode = 'china';

            return true;
        }

        if (
            mb_stripos($textPdf, 'WWW.AIRINDIA.IN') !== false
            || mb_stripos($textPdf, 'www.airindia.com') !== false
        ) {
            $this->providerCode = 'airindia';

            return true;
        }

        if (
            stripos($textPdf, 'Internet booking egyptair') !== false
            || stripos($textPdf, 'Egyptair administration') !== false
            || stripos($textPdf, 'Reservation office egyptair') !== false
            || stripos($textPdf, 'Egyptair toronto office') !== false
        ) {
            $this->providerCode = 'egyptair';

            return true;
        }

        if (
            stripos($textPdf, 'ATLANTIC AIRWAYS INTERNET') !== false
        ) {
            $this->providerCode = 'atlanticairways';

            return true;
        }

        if (
            stripos($textPdf, 'VISTARA RESERVATIONS') !== false
            || stripos($textPdf, 'WWW.AIRVISTARA.COM') !== false
        ) {
            $this->providerCode = 'vistara';

            return true;
        }

        if (
            stripos($textPdf, 'www.ita-airways.com') !== false
        ) {
            $this->providerCode = 'itaairways';

            return true;
        }

        if (
            stripos($textPdf, 'HAWAIIAN AIRLINES CONTACT') !== false
            || stripos($textPdf, 'HAWAIIAN AIRLINES, PO BOX') !== false
            || stripos($textPdf, 'HAWAIIAN AIRLINES GROUPS CONTACT') !== false
            || stripos($textPdf, ': HAWAIIAN AIRLINES') !== false
        ) {
            $this->providerCode = 'hawaiian';

            return true;
        }

        if (
            stripos($textPdf, 'ETIHAD AIRWAYS,') !== false
        ) {
            $this->providerCode = 'etihad';

            return true;
        }

        if (
            stripos($textPdf, 'TAP +351211234400') !== false
            || stripos($textPdf, 'www.flytap.com') !== false
        ) {
            $this->providerCode = 'tapportugal';

            return true;
        }

        if (
            stripos($headers['from'], 'booking@flyaircairo.com') !== false
            || stripos($textPdf, 'WIDE INTERNET, CAIRO') !== false
            || stripos($textPdf, '0226955555') !== false
        ) {
            $this->providerCode = 'cairo';

            return true;
        }

        if (strpos($textPdf, 'EVA AIRWAYS') !== false) {
            $this->providerCode = 'eva';

            return true;
        }

        if (strpos($textPdf, 'SRILANKAN AIRLINES') !== false) {
            $this->providerCode = 'srilankan';

            return true;
        }

        if (strpos($textPdf, 'AIR EUROPA') !== false) {
            $this->providerCode = 'aireuropa';

            return true;
        }

        if (strpos($textPdf, 'MALAYSIA AIRLINE') !== false) {
            $this->providerCode = 'malaysia';

            return true;
        }

        if (stripos($textPdf, 'CAPE AIR') !== false
        ) {
            $this->providerCode = 'cape';

            return true;
        }

        if (stripos($textPdf, 'SOLOMON AIRLINES') !== false
        ) {
            $this->providerCode = 'solomonair';

            return true;
        }

        if (false !== stripos($textPdf, 'AEGEAN AIRLINES')) {
            $this->providerCode = 'aegean';

            return true;
        }

        if (false !== stripos($textPdf, 'ETHIOPIAN AIRLINES')) {
            $this->providerCode = 'ethiopian';

            return true;
        }

        if (false !== stripos($textPdf, 'QATAR AIRWAYS')) {
            $this->providerCode = 'qmiles';

            return true;
        }

        if (false !== stripos($textPdf, 'VIETNAM AIRLINES WEBSITE')) {
            $this->providerCode = 'vietnam';

            return true;
        }

        if (false !== stripos($textPdf, 'AIR CORSICA')) {
            $this->providerCode = 'aircorsica';

            return true;
        }

        // after all, weak detect
        if (strpos($textPdf, 'AVIANCA') !== false) {
            $this->providerCode = 'aviancataca';

            return true;
        }

        if (strpos($textPdf, 'HY INTERNET SITE') !== false) {
            $this->providerCode = 'uzair';

            return true;
        }

        // the last, if provider not exists
        if (stripos($headers['from'], 'eticket@amadeus.com') !== false
         || strpos($textPdf, 'UGANDA AIRLINES') !== false) {
            $this->providerCode = 'amadeus';

            return true;
        }

        return false;
    }

    private function parsePrice($obj, string $text): void
    {
        $paymentDetails = $this->re("/\n(.*\b{$this->opt($this->t('paymentStart'), true)}\s[\s\S]+)/u", $text);

        if (preg_match("/{$this->opt(['Grand Total', 'Montant total', '(ﺔﺒﻳﺮﻀﻟا ﻞﻣﺎﺷ) ﻲﻟﺎﻤﺟﻹا ﻎﻠﺒﻤﻟا'], true)}\n*\s*[:]+\s*(?-i)(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\'\d]*)/i", $paymentDetails, $matches)
            || preg_match("/(?:{$this->opt('Total Amount', true)}|{$this->opt($this->t('Total Amount'), true)})(?:\s*\/\s*\S[^:\n]*?|\n*)?\s*[:]*\s*(?-i)(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\'\d]*)/iu", $paymentDetails, $matches)
        ) {
            $obj->price()->currency($matches['currency'])->total($this->amount($matches['amount']));
        } elseif (preg_match("/{$this->opt(['Grand Total', 'Montant total'], true)}\s*[:]+\s*(?-i)(?<currency>[A-Z]{3})[ ]+/i", $paymentDetails, $matches)) {
            $obj->price()->currency($matches['currency']);
        }

        $reCurrency = !empty($matches['currency']) ? preg_quote($matches['currency'], '/') : '[A-Z]{3}';

        if (preg_match("/(?:{$this->opt('Fare Equivalent', true)}|{$this->opt($this->t('Fare Equivalent'), true)})\s*[:]+\s*({$reCurrency})\s*(\d[,.\'\d]*)/i", $paymentDetails, $m)
            || preg_match("/\s(?:{$this->opt('Base Fare', true)}|Fare(?:\s*\/\s*[^:\d\n]+)?\s*[:]+|Fare[^:\n]*|Tarif\s*[:]+|{$this->opt($this->t('Base Fare'), true)})[:\s]*({$reCurrency})\s*(\d[,.\'\d]*)/", $paymentDetails, $m)
        ) {
            $obj->price()->currency($m[1])->cost($this->amount($m[2]));
        }

        if (preg_match("/(?:Taxes|Taxas|Tasse|Steuern|稅額).*?:\s+(.+?[A-Z].+?)\s*(?i){$this->opt(['Montant total', 'Total Amount', 'Valor Total', 'Importo totale', 'Gesamtsumme', '總額'], true)}(?:\s*:|\s*\/|[ ]{2}|\s*\()/s", $paymentDetails, $m)) {
            // it-49583506.eml
            if (preg_match_all('/\([ ]*(?<name>[A-Z][A-Z\d])[ ]*\)\s*[A-Z]{3}(?:[ ]{1,2}PD)?[ ]*(?<val>\d[,.\'\d]*)$/m', $m[1], $fees, PREG_SET_ORDER)) {
                // Embarkation Fee - Brazil(BR)    CAD 38.23
                foreach ($fees as $fee) {
                    $obj->price()->fee($fee['name'], $this->amount($fee['val']));
                }
            }

            foreach (explode("\n", $m[1]) as $row) {
                if (preg_match('/[A-Z]{3}(?:[ ]{1,2}PD)?[ ]*(?<val>\d[,.\'\d]*)[ ]*(?<name>[A-Z][A-Z\d])[ ]*$/m', $row, $fee)) {
                    // CHF PD 0.50G8
                    $obj->price()->fee($fee['name'], $this->amount($fee['val']));
                }
            }
        }

        if (preg_match('/Total OB Fees\s*\:*\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\'\d]*)/', $paymentDetails, $matches)
            || preg_match('/FLUGGESELLSCHAFTSGEBüHREN\s*\:*\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\'\d]*)/', $paymentDetails, $matches)
            || preg_match('/航空公司附加費用\s*\:*\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\'\d]*)/', $paymentDetails, $matches)
            || preg_match('/Total Tax Amount.*(?:\s*\/\s*[^:\d\n]+)?\s*[:]\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\'\d]*)/', $paymentDetails, $matches)
            || preg_match('/Taxes\n+(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\'\d]*)\n+\:\n+Carrier Imposed Fees/', $paymentDetails, $matches)
        ) {
            $obj->price()->tax($this->amount($matches['amount']));
        }
    }

    private function parseSegment(Flight $f, $text): void
    {
        //Remove junk from segment
        //$text = preg_replace("/^(.+)\n\nSpecial Service Request/mus", "", $text);
        $s = $f->addSegment();

        // it-61851206.eml
        $froms = preg_replace("/(.+)/", '$1' . "\n", $this->t("From"));

        if (is_array($froms)) {
            foreach ($froms as $from) {
                $textSegment = $this->findCutSection($text, $from);

                if (!empty($textSegment)) {
                    $text = $textSegment;
                    unset($textSegment);
                }
            }
        } else {
            $textSegment = $this->findCutSection($text, $froms);

            if (!empty($textSegment)) {
                $text = $textSegment;
                unset($textSegment);
            }
        }

        // it-5116403.eml
        $text = preg_replace('/((?:\b|\D)\d{1,2} ?[[:alpha:]]{3} ?20[12])\s+(\d(?:\b|\D))/u', '$1$2', $text);
        $text = preg_replace('/^\s*FQTV - FREQUENT.*\n\n/', '', $text);
        $text = preg_replace('/^\s*AVML\s+\-\s+.*\n/mu', '', $text);
        $text = preg_replace('/^\s*WCHR\s+\-\s+.*\n/mu', '', $text);
        $text = preg_replace('/^(\s*\n*.+\n.*(?:INTERNATIONAL|INTL)\s*\n*)/u', "$1\n", $text);
        $text = preg_replace('/(To\n+Flight\n+Departure\n+Arrival)/su', "", $text);
        // UPGP - PLUSGRADE CABIN UPGRADE CHARGE - CONFIRMED
        $text = preg_replace("/^ *[A-Z]{4} - (?:[A-Z]+[ \/])+- CONFIRMED\s*$/mu", "\n\n", $text);

        $str = preg_replace('/\n+/', ' ', $text);
        $airportContains = [];

        foreach ($this->airports as $ap) {
            if (preg_match("/" . $this->opt($ap, true) . "/", $str)) {
                $airportContains[] = $ap;
            }
        }

        if (!empty($airportContains)) {
            $patterns['airports'] = $this->opt($airportContains, true);
        } else {
            $patterns['airports'] = 'falseAirportNameNotFound';
        }
        $preg = "/(?<dep>{$patterns['airports']}.*?(?:\n+.+){0,3}?)\s+(?<arr>{$patterns['airports']}.*?(?:\n+.+){0,3}?)\s*\n\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d/";
        $preg2 = "^\s*(?<dep>[A-Z](?:.+?\n+{$this->opt($this->terminalTitle)}.+?|.+?|.+?\n+.+?))[ ]*\n{2}(?<arr>[A-Z](?:.+?|.+?\n+.+?))\s*\n\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d{1,5}\b";
        $preg3 = "^\s*(?<dep>[A-Z](?:.+?{$this->opt($this->terminalTitle)}.+?|.+?))[ ]*\n{2}(?<arr>[A-Z].+?)\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d{1,5}\b";

        if (preg_match('/' . $this->opt($this->t('Class')) . '\s*.*:\s*([[:alpha:]][[:alpha:]\s]+?[[:alpha:]])(?:\s*,[ ]*|[ ]+)([A-Z]{1,2})\b/u', $str, $m)) {
            // Economy, U    |    Economy U
            $s->extra()
                ->cabin($m[1])
                ->bookingCode($m[2]);
        } elseif (preg_match("/{$this->opt($this->t('Class'))}[^:]*[:]+\s*[,( ]*([A-Z]{1,2})\b/", $str, $m)) {
            // , U    |    (U)    |    U
            $s->extra()->bookingCode($m[1]);
        }

        if (preg_match("/Operated by\s*.*:\s*(\D+)\s*\:\s*([\d\:]+)\s*Duration/", $str, $m)
        || preg_match("/Operated By\s*(.+)\:\s*Duration\s*((?:\d+h)?\s*(?:\d+m)?)/", $str, $m)) {
            $s->airline()
                ->operator($m[1]);

            $s->extra()
                ->duration($m[2]);
        }
        unset($str);

        if ($this->providerCode === 'etihad') {
            /*  MNL,MANILA, NINOY AQUINO INTL
                Terminal: 3

                EY421

                23:40
                15 Apr 2023

                04:30
                16 Apr 2023

                AUH,ABU DHABI, ABU DHABI INTERNATIONAL
                Terminal: 3

                Class :
            */
            $prege = "/^\s*(?<dCode>[A-Z]{3}) ?, ?(?<dName>[A-Z].+(?:\n.+?){0,3})\n{2}\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d{1,5}\n[\s\S]+?\n[ ]*(?<aCode>[A-Z]{3}) ?, ?(?<aName>[A-Z].+(?:\n.+?){0,3})\s*\n[ ]*[[:alpha:]]+(?: [[:alpha:]]+)?\s*:/u";

            /*  MNL,MANILA, NINOY AQUINO INTL
                Terminal: 3

                AUH,ABU DHABI, ABU DHABI INTERNATIONAL
                Terminal: 3

                EY421

                23:40
                15 Apr 2023

                04:30
                16 Apr 2023

                Class :
            */
            $preg2e = "/^\s*(?<dCode>[A-Z]{3}), ?(?<dName>[A-Z].+(?:\n.+?){0,3})\n{2}\s*(?<aCode>[A-Z]{3}), ?(?<aName>[A-Z].+(?:\n.+?){0,3})\n{2}\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d{1,5}\n/";

            if (preg_match($prege, $text, $m) || preg_match($preg2e, $text, $m)) {
                $s->departure()
                    ->code($m['dCode']);
                $s->arrival()
                    ->code($m['aCode']);

                if (preg_match("/^\s*(.+?)\n([^\n]*{$this->opt($this->terminalTitle)}.*)$/is", $m['dName'], $mt)) {
                    $mt = preg_replace('/\s+/', ' ', $mt);
                    $m['dName'] = $mt[1];
                    $mt[2] = preg_replace(["/^\s*{$this->opt($this->terminalTitle)}\s*:\s*/si", "/\s*\b{$this->opt($this->terminalTitle)}\b\s*/i"], '', $mt[2]);
                    $s->departure()
                        ->terminal($mt[2]);
                }

                if (preg_match("/^\s*(.+?)\n([^\n]*{$this->opt($this->terminalTitle)}.*)$/is", $m['aName'], $mt)) {
                    $mt = preg_replace('/\s+/', ' ', $mt);
                    $m['aName'] = $mt[1];
                    $mt[2] = preg_replace(["/^\s*{$this->opt($this->terminalTitle)}\s*:\s*/si", "/\s*\b{$this->opt($this->terminalTitle)}\b\s*/i"], '', $mt[2]);
                    $s->arrival()
                        ->terminal($mt[2]);
                }
                $s->departure()
                    ->name($m['dName']);
                $s->arrival()
                    ->name($m['aName']);
            }
        } elseif (preg_match($preg, $text, $m)
            || preg_match("/{$preg2}/su", $text, $m)
            || preg_match("/{$preg3}/su", $text, $m)
        ) {
            $this->parseDepArr($s, $text, $m['dep'], $m['arr']);
        }

        $preg = '(?<arName>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<arNum>\d{1,4})\s*'
            . '(?<depTime>[\d:]+A?P?M|∆∆:∆∆)\s*(?<depDate>\d{2}\D+\d{4})\s*'
            . '(?<arrTime>[\d:]+A?P?M|∆∆:∆∆)\s*(?<arrDate>\d{2}\D+\d{4})\s*';

        $preg2 = '\n ?(?<arName>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<arNum>\d{1,4})\s+'
            . '(?<depTime>\d{2}:\d{2}(?: ?[AP]M)?|∆∆:∆∆)\s+(?<arrTime>\d{2}:\d{2}(?: ?[AP]M)?|∆∆:∆∆) ?\n'
            . '.*?\n ?(?<depDate>\d{2}\D+\d{4})\s+(?<arrDate>\d{2}\D+\d{4})';

        $preg3 = '(?<arName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<arNum>\d{1,4}\s*\d?)'
            . '\s*(?<depTime>\d{2}:\d+|∆∆:∆∆)\s+(?<depDate>.+?)\s+'
            . '\s+(?<arrTime>\d+:\d+|∆∆:∆∆)\s+(?<arrDate>.+?)\n';

        $preg4 = '(?<arName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<arNum>\d{1,4}\s*\d?)'
            . '\s*(?<depTime>\d{2}:\d+|∆∆:∆∆)\s+(?<depDate>.+?)\s*'
            . '\n+[[:alpha:]]+:'; // no arrival date

        if (preg_match("/{$preg}/s", $text, $m) || preg_match("/{$preg2}/s", $text, $m) || preg_match("/{$preg3}/s", $text, $m) || preg_match("/{$preg4}/su", $text, $m)) {
            // Airline
            $s->airline()->name($m['arName'])->number(str_replace("\n", '', $m['arNum']));

            if ($m['depTime'] === '∆∆:∆∆') {
                $s->departure()->day2($m['depDate'])->noDate();
            } elseif (!preg_match("/(^|\D)\d{4}(\D|$)/", $m['depDate']) && !empty($f->getReservationDate())) {
                $s->departure()->date(strtotime($m['depTime'], $this->normalizeDateWithWeek($m['depDate'] . ' ' . date('Y', $f->getReservationDate()))));
            } elseif (preg_match("/\d{4}/", $m['depDate'])) {
                $s->departure()->date(strtotime($m['depTime'], $this->normalizeDate($m['depDate'])));
            }

            $m['arrDate'] = preg_replace("/\n/", '', $m['arrDate'] ?? '');
            $m['arrTime'] = $m['arrTime'] ?? '';

            if ($m['arrTime'] === '∆∆:∆∆') {
                $s->arrival()->day2($m['arrDate'])->noDate();
            } elseif (!empty($m['arrDate']) && !preg_match("/(^|\D)\d{4}(\D|$)/", $m['arrDate']) && !empty($f->getReservationDate())) {
                $s->arrival()->date(strtotime($m['arrTime'], $this->normalizeDateWithWeek($m['arrDate'] . ' ' . date('Y', $f->getReservationDate()))));
            } elseif (preg_match("/\d{4}/", $m['arrDate'])) {
                $s->arrival()->date(strtotime($m['arrTime'], $this->normalizeDate($m['arrDate'])));
            } elseif (empty($m['arrDate'])) {
                $s->arrival()->noDate();
            }

            if (preg_match("/\(\s*([A-Z]{3})\s*\)/su", $s->getDepName(), $m)) {
                $s->departure()->code($m[1]);
            } elseif ($s->getDepName()) {
                $s->departure()->noCode();
            }

            if (preg_match("/\(\s*([A-Z]{3})\s*\)/su", $s->getArrName(), $m)) {
                $s->arrival()->code($m[1]);
            } elseif ($s->getArrName()) {
                $s->arrival()->noCode();
            }
        }

        /*
        Class: D
        Operated by: QATAR AIRWAYS
        Booking status (1): OK
        Baggage (4): 2PC
        Duration: 15:55
        Frequent flyer number : 2CJL040
         */

        if (preg_match("/{$this->opt($this->t('Class'))}[^:]*?[:]+\s*(\w[\w\s]*?)\s*\([ ]*([A-Z]{1,2})[ ]*\)/u", $text, $m)
        || preg_match("/{$this->opt($this->t('Class'))}[^:]*?[:]+\s*(\w[\w\s]*?)\,\s*[ ]*([A-Z]{1,2})[ ]*/u", $text, $m)) {
            // Economy (U)
            $s->extra()->cabin($m[1])->bookingCode($m[2]);
        } elseif (empty($s->getBookingCode()) && preg_match("/{$this->opt($this->t('Class'))}[^:]*?[:]+[,(\s]*([A-Z]{1,2})\b/u", $text, $m)) {
            // (U)    |    U
            $s->extra()->bookingCode($m[1]);
        } elseif (empty($s->getBookingCode()) && preg_match("/Class\n+((?:Guest Flex))\n+\:/", $text, $m)) {
            $s->extra()->cabin($m[1]);
        }

        if (preg_match("/{$this->opt($this->t('Operated by'), true)}[^:]*?[:]+\s*(.+)/u", $text, $m)) {
            $s->airline()->operator(preg_replace('/\s+/', ' ', $m[1]));
        }

        if (preg_match("/{$this->opt($this->t('Duration'))}[^:]*?[:]+\s*([\d:]+)/u", $text, $m)
            || preg_match("/([\d:]+)\s*{$this->opt($this->t('Duration'))}\s*:/u", $text, $m)
        ) {
            $s->extra()->duration($m[1]);
        }

        if (preg_match("/{$this->opt($this->t('Seat'))}[^:]*?\n*[:]*\s*(\d{1,3}[A-Z])\b/u", $text, $m)
            || preg_match("/\b(\d{1,3}[A-Z])[*\s]+(?:.+[\/\s]+)?{$this->opt($this->t('Seat'))}\s*:/u", $text, $m)
        ) {
            if (!empty($m[1])) {
                $s->extra()->seat($m[1]);
            }
        }

        if (preg_match('/' . $this->opt($this->t('Number of stops')) . '[^:\d]*?[:]+\s*(\d{1,3})\b/u', $text, $m)
            || preg_match('/\b(\d{1,3})\s+' . $this->opt($this->t('Number of stops')) . '[^:\d]*?:/u', $text, $m)
        ) {
            $s->extra()->stops($m[1]);
        }

        if (preg_match('/(?<desc>' . $this->opt($this->t('Frequent flyer number'), true) . ')[^:]*?[:]+\s*(?<number>[A-Z]{2}\s\d+)\b/u', $text, $m)
            || preg_match('/(?<desc>' . $this->opt($this->t('Frequent flyer number'), true) . ')[^:]*?[:]+\s*(?<number>[A-z\d\-]{5,})\b/u', $text, $m)
            || preg_match('/(?<number>[A-z\d\-]{5,})\s*(?<desc>' . $this->opt($this->t('Frequent flyer number'), true) . ')[^:]*?:/u', $text, $m)
        ) {
            if (!in_array($m['number'], $this->accounts)) {
                $travellers = $f->getTravellers();
                $passengerName = count($travellers) === 1 ? array_shift($travellers)[0] : null;
                $f->program()->account($m['number'], false, $passengerName, preg_replace('/\s+/', ' ', $m['desc']));
                $this->accounts[] = $m['number'];
            }
        }
    }

    private function parseSegmentTrain(Train $f, $text): void
    {
        // examples: it-12294061.eml

        $s = $f->addSegment();

        $froms = preg_replace("/(.+)/", '$1' . "\n", $this->t("From"));

        if (is_array($froms)) {
            foreach ($froms as $from) {
                $textSegment = $this->findCutSection($text, $from);

                if (!empty($textSegment)) {
                    $text = $textSegment;
                    unset($textSegment);
                }
            }
        } else {
            $textSegment = $this->findCutSection($text, $froms);

            if (!empty($textSegment)) {
                $text = $textSegment;
                unset($textSegment);
            }
        }

        // it-5116403.eml
        $text = preg_replace('/((?:\b|\D)\d{1,2} ?[[:alpha:]]{3} ?20[12])\s+(\d(?:\b|\D))/u', '$1$2', $text);

        $str = preg_replace('/\n+/', ' ', $text);
        $airportContains = [];

        foreach ($this->airports as $ap) {
            if (preg_match("/" . $this->opt($ap, true) . "/", $str)) {
                $airportContains[] = $ap;
            }
        }

        if (!empty($airportContains)) {
            $patterns['airports'] = $this->opt($airportContains, true);
        } else {
            $patterns['airports'] = 'falseAirportNameNotFound';
        }

        $patterns['airports'] = $this->opt($this->airports, true);
        $preg = "/(?<dep>{$patterns['airports']}.+?)[ ]*(?<arr>{$patterns['airports']}.+?)\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d/";
        $preg2 = "^\s*(?<dep>[A-Z](?:.+?\n+{$this->opt($this->terminalTitle)}.+?|.+?\n+.+?|.+?))[ ]*\n{2}(?<arr>[A-Z](?:.+?\n+.+?|.+?))\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d{1,4}\s*\d?";
        $preg3 = "^\s*(?<dep>[A-Z](?:.+?{$this->opt($this->terminalTitle)}.+?|.+?))[ ]*\n{2}(?<arr>[A-Z].+?)\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d{1,4}\s*\d?";

        if ($this->providerCode === 'etihad') {
            /*  MNL,MANILA, NINOY AQUINO INTL

                EY421

                23:40
                15 Apr 2023

                04:30
                16 Apr 2023

                AUH,ABU DHABI, ABU DHABI INTERNATIONAL

                Class:
            */
            $preg = "/^\s*(?<dCode>[A-Z]{3}) ?, ?(?<dName>[A-Z].+(?:\n.+?){0,3})\n{2}\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d{1,5}\n[\s\S]+?\n[ ]*(?<aCode>[A-Z]{3}) ?, ?(?<aName>[A-Z].+(?:\n.+?){0,3})\s*\n[ ]*[[:alpha:]]+(?: [[:alpha:]]+)?\s*:/u";

            /*  MNL,MANILA, NINOY AQUINO INTL

                AUH,ABU DHABI, ABU DHABI INTERNATIONAL

                EY421

                23:40
                15 Apr 2023

                04:30
                16 Apr 2023

                Class:
            */
            $preg2 = "/^\s*(?<dCode>[A-Z]{3}), ?(?<dName>[A-Z].+(?:\n.+?){0,3})\n{2}\s*(?<aCode>[A-Z]{3}), ?(?<aName>[A-Z].+(?:\n.+?){0,3})\n{2}\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d{1,5}\n/";

            if (preg_match($preg, $text, $m) || preg_match($preg2, $text, $m)) {
                $s->departure()
                    ->code($m['dCode']);
                $s->arrival()
                    ->code($m['aCode']);
                $m = preg_replace('/\s+/', ' ', $m);
                $s->departure()
                    ->name($m['dName']);
                $s->arrival()
                    ->name($m['aName']);
            }
        } elseif (preg_match($preg, $text, $m) || preg_match("/{$preg2}/s", $text, $m) || preg_match("/{$preg3}/s", $text, $m)) {
            $m = preg_replace("/\s+/", ' ', $m);
            $s->departure()->name($m['dep']);
            $s->arrival()->name($m['arr']);
        }
        unset($str);

        $preg = '(?<arName>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<arNum>\d{1,4})\s*'
            . '(?<depTime>[\d:]+A?P?M|∆∆:∆∆)\s*(?<depDate>\d+\D+\d{4})\s*'
            . '(?<arrTime>[\d:]+A?P?M|∆∆:∆∆)\s*(?<arrDate>\d+\D+\d{4})\s*';

        $preg2 = '\n ?(?<arName>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<arNum>\d{1,4})\s+'
            . '(?<depTime>\d{2}:\d{2}(?: ?[AP]M)?|∆∆:∆∆)\s+(?<arrTime>\d{2}:\d{2}(?: ?[AP]M)?|∆∆:∆∆) ?\n'
            . '.*?\n ?(?<depDate>\d{2}\D+\d{4})\s+(?<arrDate>\d{2}\D+\d{4})';

        $preg3 = '(?<arName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<arNum>\d{1,4}\s*\d?)'
            . '\s*(?<depTime>\d{2}:\d+|∆∆:∆∆)\s+(?<depDate>.+?)\s+'
            . '\s+(?<arrTime>\d+:\d+|∆∆:∆∆)\s+(?<arrDate>.+?)\n';

        if (preg_match("/{$preg}/s", $text, $m) || preg_match("/{$preg2}/s", $text, $m) || preg_match("/{$preg3}/s", $text, $m)) {
            // Extra
            $s->extra()->service($m['arName'])->number(str_replace("\n", '', $m['arNum']));

            if ($m['depTime'] === '∆∆:∆∆') {
                $s->departure()->noDate();
            } elseif (preg_match("/\d{4}/", $m['depDate'])) {
                $s->departure()->date(strtotime($m['depTime'], $this->normalizeDate($m['depDate'])));
            }

            $m['arrDate'] = preg_replace("/\n/", '', $m['arrDate']);

            if ($m['arrTime'] === '∆∆:∆∆') {
                $s->arrival()->noDate();
            } elseif (preg_match("/\d{4}/", $m['depDate'])) {
                $s->arrival()->date(strtotime($m['arrTime'], $this->normalizeDate($m['arrDate'])));
            }

            if (preg_match("/\(\s*([A-Z]{3})\s*\)/", $s->getDepName(), $m)) {
                $s->departure()->code($m[1]);
            }

            if (preg_match("/\(\s*([A-Z]{3})\s*\)/", $s->getArrName(), $m)) {
                $s->arrival()->code($m[1]);
            }
        }

        if (preg_match('/' . $this->opt($this->t('Duration')) . '\s*:\s*([\d:]+)/u', $text, $m)) {
            $s->extra()->duration($m[1]);
        } elseif (preg_match('/([\d:]+)\s*' . $this->opt($this->t('Duration')) . '\s*:/u', $text, $m)) {
            $s->extra()->duration($m[1]);
        }

        if (preg_match('/' . $this->opt($this->t('Seat')) . '\s*:\s*([A-Z\d]{1,4})\b/u', $text, $m)) {
            if (!empty($m[1])) {
                $s->extra()->seat($m[1]);
            }
        } elseif (preg_match('#\b([A-Z\d]{1,4})\*?\s*(.+?)?' . $this->opt($this->t('Seat')) . '\s*:#u', $text, $m)) {
            if (!empty($m[1])) {
                $s->extra()->seat($m[1]);
            }
        }
    }

    private function parseDepArr(FlightSegment $s, $text, $dep, $arr): void
    {
        $arr = preg_replace("/INTL\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d.+/", "INTL", $arr);

        $aircraft = $this->re("/{$this->opt($this->t('Aircraft type'))}\s*[:]+\s*([^:\s].*[^:\s])$/m", $text);

        if (!empty($aircraft)) {
            $s->extra()
                ->aircraft($aircraft);
        }

        // Depart
        $dep = preg_replace('/\s+/', ' ', $dep);
        $array = preg_split($pattern = "/(?:{$this->opt($this->terminalTitle)}[ ]*\/[ ]*)?{$this->opt($this->terminalTitle)} ?:/i", $dep);

        if (preg_match_all("/\([A-Z]{3}\).+\([A-Z]{3}\)/", $array[0], $m)) {
            $s->departure()->name($this->re("/^(\D+\([A-Z]{3}\)\s*)[A-Z]/us", $array[0]));
        } elseif (preg_match("/^(.+\sINTL)\s+(.+INTL)/u", $dep, $m)) {
            $s->departure()->name($m[1]);
        } else {
            $s->departure()->name($array[0]);
        }

        if (!empty($array[1])) {
            $s->departure()->terminal(preg_replace("/{$this->opt($this->terminalTitle)}/", "", $array[1]));
        }

        // Arrival
        $arr = preg_replace('/\s+/', ' ', $arr);
        $array = preg_split($pattern, $arr);
        $array = array_map('trim', $array);

        if (count($array) == 1) {
            $array = preg_split("/(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d+/", $arr);
        }

        if (preg_match("/^(.+\sINTL)\s+(.+INTL)/u", $dep, $m)) {
            $s->arrival()->name($m[2]);
        } elseif (preg_match("/^(.+\sINTL)\s+\d+\:\d+/", $array[0], $m)) {
            $s->arrival()->name($m[1]);
        } elseif (!empty($array[0])) {
            $s->arrival()->name($array[0]);
        } elseif (preg_match("/^(.+\sINTL)\s+(.+)/", $dep, $m)) {
            $s->departure()
                ->name($m[1]);
            $s->arrival()
                ->name($m[2]);
        } else {
            $nameArr = $this->re("/\([A-Z]{3}\)\n\s*(.+\s\([A-Z]{3}\))/", $text);
            $s->arrival()->name($nameArr ? preg_replace('/\s+/', ' ', $nameArr) : null);
        }

        if (!empty($array[1]) && strlen($array[1]) < 50) {
            $s->arrival()->terminal(preg_replace("/(?:{$this->opt($this->terminalTitle)}|\-)/", "", $array[1]), true, true);
        }

        if (empty($s->getDepTerminal()) && empty($s->getArrTerminal())) {
            if (preg_match("/{$this->opt($this->terminalTitle)}[ ]*:[ ]*(?<dep>.+?)[ ]*\n+[ ]*{$this->opt($this->terminalTitle)}[ ]*:[ ]*(?<arr>.+?)(?:[ ]*\n|\s*$)/", $text, $m)
                || preg_match("/{$this->opt($this->terminalTitle)}[ ]*:[ ]*(?<arr>.+?)[ ]*(?:\n+[ ]*\d{2}[- ]*[[:alpha:]]+[- ]*\d{4}[ ]*){2}\n+[ ]*{$this->opt($this->terminalTitle)}[ ]*:[ ]*(?<dep>.+?)(?:[ ]*\n|\s*$)/u", $text, $m)
                || preg_match("/\n[ ]*(?<dep>[A-Z\d]+)[ ]*\n+[ ]*(?:{$this->opt($this->terminalTitle)}[ ]*\/[ ]*)?{$this->opt($this->terminalTitle)}[ ]*:[ ]*\n+[ ]*(?<arr>[A-Z\d]+)[ ]*\n+[ ]*(?:{$this->opt($this->terminalTitle)}[ ]*\/[ ]*)?{$this->opt($this->terminalTitle)}[ ]*:(?:[ ]*\n|\s*$)/", $text, $m)
            ) {
                $s->departure()->terminal($m['dep']);
                $s->arrival()->terminal($m['arr']);
            }
        }
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(' ', ' ', preg_replace('/<[^>]+>/', "\n", html_entity_decode($text)));
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = preg_replace("#" . join('|', $this->deleteWorlds) . "#", "\n\n", $text);

        return $text;
    }

    private function assignLangHTML(): bool
    {
        foreach ($this->detectLangHTML as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//*[contains(normalize-space(),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function opt($field, bool $replaceSpaces = false): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) use ($replaceSpaces) {
            return $replaceSpaces ? preg_replace('/[ ]+/', '\s+', preg_quote($s, '/')) : preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function t($word, $twoLang = true)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            $result = $word;
        } else {
            $result = self::$dictionary[$this->lang][$word];
        }

        if ($twoLang && !empty($this->lang2) && isset(self::$dictionary[$this->lang2]) && isset(self::$dictionary[$this->lang2][$word])) {
            $result = array_merge((array) $result, (array) self::$dictionary[$this->lang2][$word]);
        }

        return $result;
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } elseif (stripos($haystack, $needles) !== false) {
                return $key;
            }
        }

        return false;
    }

    private function amount($price)
    {
        $price = str_replace(' ', '', $price);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDateWithWeek($str)
    {
        //$this->logger->debug('$str = ' . print_r($str, true));
        $in = [
            // 30Dec(Fri)
            "#^\s*(\d{1,2})([[:alpha:]]+)\(([[:alpha:]]+)\)\s*(\d{4})\s*$#su",
        ];
        $out = [
            "$3, $1 $2 $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        //$this->logger->debug('$str = ' . print_r($str, true));

        if (preg_match("/^(?<week>[[:alpha:]]+), (?<date>\d+ [[:alpha:]]+ \d{4})/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } else {
            $str = null;
        }

        return $str;
    }

    private function normalizeDate($str)
    {
        //$this->logger->debug('$str = ' . print_r($str, true));
        $in = [
            // Dimanche 27 août 2023
            "#^\s*[[:alpha:]]+\s+(\d{1,2})\s+([[:alpha:]]+)\.?\s*(\d{4})\s*$#su",
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        //$this->logger->debug('$str = ' . print_r($str, true));

        return strtotime($str);
    }

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        $namePrefixes = '(?:MSTR|MISS|MRS|MR|MS|DR)';
        $delimiter = $this->providerCode === 'tapportugal' ? '\s*' : '\s+';

        $in = [
            "/^(.+?)\s*-\s*ADT$/i",
            "/^(.{2,}?){$delimiter}(?:{$namePrefixes}[.\s]*)+$/i",
            "/^(?:{$namePrefixes}[.\s]+)+(.{2,})$/i",
        ];

        $out = '$1';

        return preg_replace($in, $out, $s);
    }
}
