<?php

namespace AwardWallet\Engine\thetrainline\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Bus;
use AwardWallet\Schema\Parser\Common\Train;
use AwardWallet\Schema\Parser\Email\Email;

class TripHtml2016En extends \TAccountChecker
{
    public $mailFiles = "thetrainline/it-105369465.eml, thetrainline/it-111794651.eml, thetrainline/it-11188052.eml, thetrainline/it-144513061.eml, thetrainline/it-166374823.eml, thetrainline/it-45833713.eml, thetrainline/it-51917284.eml, thetrainline/it-52799251.eml, thetrainline/it-53460958.eml, thetrainline/it-53745158.eml, thetrainline/it-652963572.eml, thetrainline/it-73776615.eml, thetrainline/it-79005389.eml, thetrainline/it-82325985.eml, thetrainline/it-88963823.eml"; // +2 bcdtravel(html)[en]
    public $lang = '';
    public static $dictionary = [
        'en' => [ // it-73776615.eml, it-52799251.eml, it-11188052.eml
            'reSubject'                        => ['Your Booking Confirmation', 'Ticket collection reference'],
            'route'                            => ['Outbound', 'Return', 'OUT'],
            '0 changes'                        => ['0 changes', 'direct'],
            'Ticket PNR reference'             => ['Ticket PNR reference', 'Ticket collection reference', 'Booking reference', 'Order ID:', 'Booking no:'],
            'Booking Fee:'                     => ['Booking Fee:', 'Booking Fee', 'Bookingfee', 'Travel Insurance'],
            'Total Fare:'                      => ['Total Fare:', 'TicketPrice'],
            'Total amount:'                    => ['Total amount:', 'Total amount'],
            'Please quote your Transaction ID' => ['Please quote your Transaction ID', 'Your Transaction ID:'],
        ],
        'fr' => [ // it-79005389.eml
            'reSubject'     => 'Identifiant de transaction',
            'route'         => ['Aller', 'Aller:', 'Aller :'],
            '0 changes'     => '0 correspondance',
            'Coach'         => 'Voiture',
            'Total amount:' => ['Montant total'],
            'Booking Fee:'  => ['Frais de National Express :', 'Frais de réservation'],
            // 'Departing' => '',
            'Please quote your Transaction ID' => 'Votre identifiant de transaction',
            'Transaction ID'                   => 'Identifiant de transaction',
            'Ticket PNR reference'             => 'Référence',
        ],
        'de' => [ // it-45833713.eml
            'reSubject'                        => 'Ticket-Abholnummer',
            'route'                            => ['Hinfahrt:', 'Hinfahrt'],
            '0 changes'                        => '0 Umstiege',
            'Coach'                            => 'Wagen',
            'Total amount:'                    => 'Gesamtbetrag:',
            'Booking Fee:'                     => 'Buchungsgebühr:',
            'Departing'                        => 'Hinfahrt',
            'Please quote your Transaction ID' => ['Bitte geben Sie Ihre Transaktionsnummer an', 'Ihre Transaktionsnummer:'],
            'Transaction ID'                   => 'Transaktionsnummer',
        ],
        'pt' => [ // it-51917284.eml
            'reSubject' => ['Confirmação de reserva. ID da transação'],
            'route'     => ['Ida'],
            '0 changes' => '0 trocas',
            // 'Coach' => '',
            'Total amount:'                    => 'Valor total:',
            'Booking Fee:'                     => 'Taxa de reserva:',
            'Please quote your Transaction ID' => 'Forneça o ID de sua transação',
            'Transaction ID'                   => 'ID de sua transação',
        ],
        'es' => [ // it-53460958.eml
            'reSubject'                        => ['Identificador de la transacción'],
            'route'                            => ['Ida'],
            '0 changes'                        => '0 cambios',
            'Coach'                            => 'Vagón',
            'Total amount:'                    => 'Importe total:',
            'Booking Fee:'                     => ['Gastos de gestión:', 'Gastos de tarjeta de crédito:'],
            'Please quote your Transaction ID' => ['Indica el identificador de tu transacción', 'Tu número de transacción:'],
            'Transaction ID'                   => 'identificador de tu transacción',
        ],
        'it' => [ // it-53745158.eml
            'reSubject'                        => ['ID transazione'],
            'route'                            => ['Andata'],
            '0 changes'                        => '0 cambi',
            'Coach'                            => 'Carrozza',
            'Total amount:'                    => 'Importo totale:',
            'Booking Fee:'                     => ['Commissione prenotazione:', 'Commissione di prenotazione'],
            'Please quote your Transaction ID' => 'Dovrai fornire il tuo ID transazione:',
            'Transaction ID'                   => 'ID transazione',
            'Ticket PNR reference'             => ['Codice PNR'],
        ],
        'nl' => [ // it-53745158.eml
            'reSubject'                        => ['Bevestiging, naam:'],
            'route'                            => ['Heenreis', 'Terugreis'],
            //            '0 changes' => '',
            'Coach'                            => 'Rijtuig',
            'Passengers and seats'             => 'Passagiers en zitplaatsen',
            'Seat'                             => ['zitplaatsen', 'Zitplaats'],
            'Duration'                         => 'Reistijd',
            'Total amount:'                    => 'Totaalprijs',
            //'Booking Fee:'                     => ['Commissione prenotazione:', 'Commissione di prenotazione'],
            //'Please quote your Transaction ID' => 'Dovrai fornire il tuo ID transazione:',
            //'Transaction ID'                   => 'ID transazione',
            'Ticket PNR reference'             => ['Boekingscode'],
            'cabin'                            => 'Zitplaatsreservering',
        ],
    ];
    public $departing;

    public static $langDetectors = [
        'nl' => [
            'Passagiers en zitplaatsen',
        ],
        'fr' => [
            'Votre trajet à destination', 'Vous pouvez afficher le billet sur votre smartphone',
        ],
        'de' => [
            'Lassen Sie sich Ihre Abholnummer und mehr in der App anzeigen',
            'Ihre Reise nach',
            'Dies ist Ihre Buchungsbestätigung',
            'Ihre Transaktionsnummer',
        ],
        'en' => [
            'Your trip to',
            'Ticket collection reference',
            "Here's everything you need for",
            "Here's the detail for your booking to",
            'back on your next Trainline booking',
            'Look out for our next email with your etickets',
            'You will receive your tickets directly from',
            "You'll need the awesome trainline app",
            'All your tickets ● Manage your bookings',
            'We make it easy to view and manage all your bookings on the trainline website',
            'View your ticket collection reference and more in app',
            'This is just a booking confirmation, you still need your tickets to travel',
            'Ticket PNR reference',
            'There’s no difference to your journey, you just have multiple tickets',
            'Useful coach information',
            'This is just a booking confirmation',
            'View live times and platforms',
            'Journey information',
            'You will soon be travelling by train',
            'Remember to bring',
            'Your trips to',
        ],
        'es' => [
            'Información de pago',
        ],
        'pt' => [
            'Como alternativa, você pode mostrar',
            'Referência PNR do bilhete',
            'Informações de pagamento',
        ],
        'it' => [
            'In alternativa, puoi mostrare gli e-ticket in allegato sul tuo telefono.',
            'Il tuo viaggio a',
        ],
    ];

    protected $result = [];

    private $subjects = [
        'nl' => ['Bevestiging, naam:'],
        'fr' => ['Votre confirmation de réservation. Identifiant de transaction'],
        'de' => ['Ihre Buchungsbestätigung. Ticket-Abholnummer ',
            'Ihre Buchungsbestätigung für die',
        ],
        'en' => [
            'Your tickets for', 'Your Booking Confirmation', 'Your booking confirmation. Transaction Id', 'Your booking confirmation:',
            'Your booking confirmation for',
        ],
        'pt' => ['Confirmação de reserva. ID da transação'],
        'es' => ['Tu confirmación de la reserva. Identificador de la transacción '],
        'it' => ['Conferma della tua prenotazione. ID transazione', '[EXTERNAL] Conferma della prenotazione per'],
    ];
    private $date;
    private $region = '';

    private $patterns = [
        'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?',
    ];

    private static $providerDetect = [
        // !!! for virgintrains parser virgintrains\BookingConfirmation
        'trainpal' => [
            'from' => ['tp-accounts-noreply@trip.com'],
            'url'  => ['mytrainpal.com'],
            'text' => ['Thank you for choosing TrainPal', 'You can download free TrainPal App'],
        ],
        'nsinter' => [
            'from' => ['nsinternational.'],
            'url'  => ['nsinternational.'],
            //            'text' => [],
        ],
        'thetrainline' => [
            'from' => ['thetrainline.com'],
            'url'  => ['thetrainline.com'],
            //            'text' => [],
        ],
        'cssc' => [
            'from' => ['crosscountry@trainsfares.co.uk'],
            'url'  => ['.crosscountrytrains.'],
            'text' => ['XC Trains Limited'],
        ],
        'northern' => [
            'from' => 'northern@trainsfares.co.uk',
            'url'  => ['northernrailway.co.uk'],
        ],
        'greang' => [
            'from' => 'greateranglia@trainsfares.co.uk',
            'url'  => ['greateranglia.'],
        ],
        [
            'from' => 'auto-confirm.lnr@trainsfares.co.uk',
            'url'  => ['londonnorthwesternrailway'],
        ],
        [
            'from' => 'auto-confirm.scotrail@trainsfares.co.uk',
            'url'  => ['scotrail.co.uk'],
        ],
        [
            'from' => 'auto-confirm.eastmidlands@trainsfares.co.uk',
            'url'  => ['eastmidlands.co.uk'],
        ],
        [
            'from' => 'auto-confirm.wmr@trainsfares.co.uk',
            'url'  => ['.westmidlandsrailway.co.uk'],
        ],
        [
            'from' => 'auto-confirm.eastmidlands@trainsfares.co.uk',
            'url'  => ['.trainsfares.co.uk'],
            'text' => 'National Rail Conditions of Travel',
        ],
    ];

    public static function getEmailProviders()
    {
        return array_filter(array_keys(self::$providerDetect), function ($v) {
            return (is_numeric($v)) ? false : true;
        });
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        // Provider
        $provider = $this->detectProviderCode();

        if (!empty($provider) && is_string($provider)) {
            $email->setProviderCode($provider);
        }

        $this->date = strtotime($parser->getHeader('date'));

        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language!");
        }

        $xpathNoEmpty = 'string-length(normalize-space())>1';
        $recordLocator = $this->http->FindNodes("(//text()[normalize-space()='Ticket PNR reference'])/following::text()[string-length()>3][1]");

        if (empty($recordLocator)) {
            // RecordLocator, was parsed first recLoc, bcs we have been seen only two examples of emails, which contains one recLoc or 2 and more, but this recLocs was equals
            $recordLocator = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Ticket PNR reference'))}])[1]/following::text()[normalize-space()][1]", null, true, '/^([A-Z\d]{5,})$/');
        }

        if (empty($recordLocator)) {
            $recordLocator = $this->re("/{$this->opt($this->t('reSubject'))}\s+([A-Z\d]{5,})/iu", $parser->getSubject());
        }

        if (empty($recordLocator)) {
            $recordLocator = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Ticket PNR reference'))}]", null, true, '/\s*([A-Z\d]{5,})$/');
        }

        $pax = [];
        $paxText = $this->http->FindNodes('//img[contains(@src,"Passenger") or contains(@src,"passenger")]/ancestor::table[1]//text()[normalize-space()!=""][not(contains(normalize-space(), "Adult")) and not(contains(normalize-space(), "Child"))]');

        if (empty($paxText)) {
            $paxText = $this->http->FindNodes("//text()[normalize-space()='Passagiers en zitplaatsen']/ancestor::tr[1]/following-sibling::tr/descendant::text()[normalize-space()][1]");
        }

        if (!empty($paxText)) {
            foreach ($paxText as $pt) {
                $pax = array_unique(array_merge($pax, array_filter(explode(",", $pt))));
            }
        }
        $pax = array_map("trim", $pax);

        $t = $email->add()->train();
        $b = $email->add()->bus(); // additional (example: it-45833713.eml)

        if (count($pax)) {
            $t->general()->travellers($pax);
            $b->general()->travellers($pax);
        } elseif ($traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]/ancestor::*[1]", null, true, "/Dear\s*(\D+)\,/")) {
            $t->general()->traveller($traveller);
            $b->general()->traveller($traveller);
        }

        if (is_array($recordLocator)) {
            foreach ($recordLocator as $locatior) {
                $t->general()->confirmation($locatior, 'Reference');
                $b->general()->confirmation($locatior, 'Reference');
            }
        } elseif (!empty($recordLocator)) {
            $t->general()->confirmation($recordLocator, 'Reference');
            $b->general()->confirmation($recordLocator, 'Reference');
        }

        if (empty($recordLocator)) {
            $transactionNo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Please quote your Transaction ID'))}]/following::text()[{$xpathNoEmpty}][1]", null, true, "/^\d{5,}$/");

            if (!empty($transactionNo) && $transactionNo !== $recordLocator) {
                $t->general()->confirmation($transactionNo, $this->t('Transaction ID'));
                $b->general()->confirmation($transactionNo, $this->t('Transaction ID'));
            }
        }

        $total = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total amount:'))}]/following::*[normalize-space()][1]/descendant::text()[normalize-space()][1]");
        $price = $this->parsePrice($total);

        if (null !== $price && is_array($price)) {
            $email->price()
                ->currency($price[0])
                ->total($price[1]);
        }

        $cost = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total Fare:'))}]/following::*[normalize-space(.)][1]");
        $price = $this->parsePrice($cost);

        if (null !== $price && is_array($price)) {
            $email->price()->cost($price[1]);
        }

        $feeRows = $this->http->XPath->query("//tr[ *[1][{$this->contains($this->t('Booking Fee:'))}] and *[2][normalize-space()] ]");

        foreach ($feeRows as $feeRow) {
            $feeCharge = $this->http->FindSingleNode('*[2]', $feeRow, true, '/^(.+?)\s*(?:\(|$)/');
            $price = $this->parsePrice($feeCharge);

            if ($price !== null && is_array($price) && $price[1] !== null) {
                $feeName = $this->http->FindSingleNode('*[1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                $email->price()->fee($feeName, $price[1]);
            }
        }

        $this->departing = strtotime($this->http->FindSingleNode("//text()[{$this->contains($this->t('Departing'))}][not(ancestor::*[self::b or self::strong or self::h3])]/following::*[1]"));
        $this->parseSegments($t, $b);

        if (count($t->getSegments()) > 0) {
            $t->general()->noConfirmation();

            if (count($t->getSegments()) >= 2 && count($t->getSegments()) === count($t->getConfirmationNumbers())) {
                $this->correctReservations($email, $t); //it-652963572.eml
            }
        } else {
            $email->removeItinerary($t);
        }

        if (count($b->getSegments()) > 0) {
            $b->general()->noConfirmation();
        } else {
            $email->removeItinerary($b);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function correctReservations(Email $email, Train $t)
    {
        $confs = $t->getConfirmationNumbers();

        for ($i = 1; $i <= count($confs) - 1; $i++) {
            $tNew = $email->add()->train();

            $tNew->general()
                ->confirmation($confs[$i][0], $confs[$i][1])
                ->travellers(array_filter($t->getTravellers()[0]));

            $sOld = $t->getSegments()[$i];

            $sNew = $tNew->addSegment();

            if (!empty($sOld->getNumber())) {
                $sNew->setNumber($sOld->getNumber());
            }

            if (!empty($sOld->getServiceName())) {
                $sNew->extra()->service($sOld->getServiceName());
            }

            if (!empty($sOld->getCarNumber())) {
                $sNew->setCarNumber($sOld->getCarNumber());
            }

            if (!empty($sOld->getDepName())) {
                $sNew->setDepName($sOld->getDepName());
            }

            if (!empty($sOld->getArrName())) {
                $sNew->setArrName($sOld->getArrName());
            }

            if (!empty($sOld->getDepDate())) {
                $sNew->setDepDate($sOld->getDepDate());
            }

            if (!empty($sOld->getArrDate())) {
                $sNew->setArrDate($sOld->getArrDate());
            }

            if (!empty($sOld->getDepGeoTip())) {
                $sNew->setDepGeoTip($sOld->getDepGeoTip());
            }

            if (!empty($sOld->getArrGeoTip())) {
                $sNew->setArrGeoTip($sOld->getArrGeoTip());
            }

            if (!empty($sOld->getSeats())) {
                $sNew->setSeats($sOld->getSeats());
            }

            if (!empty($sOld->getDuration())) {
                $sNew->setDuration($sOld->getDuration());
            }

            $t->removeSegment($sOld);
            $t->removeConfirmationNumber($confs[$i][0]);
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@info.thetrainline.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $pcode = null;

        foreach (self::$providerDetect as $code => $detects) {
            if (empty($detects['from'])) {
                continue;
            }

            if (is_array($detects['from'])) {
                foreach ($detects['from'] as $df) {
                    if (!empty($df) && stripos($headers['from'], $df) !== false) {
                        $pcode = $code;

                        break 2;
                    }
                }
            } elseif (stripos($headers['from'], $detects['from']) !== false) {
                $pcode = $code;

                break;
            }
        }

        if ($pcode === null) {
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
        $provider = $this->detectProviderCode();

        if ($provider === null) {
            return false;
        }

        return $this->assignLang();
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function detectProviderCode()
    {
        foreach (self::$providerDetect as $code => $detects) {
            if (!empty($detects['url']) && $this->http->XPath->query("//a[{$this->contains($detects['url'], '@href')} or {$this->contains($detects['url'], '@originalsrc')}]")->length > 0
                || !empty($detects['text']) && $this->http->XPath->query("//*[{$this->contains($detects['text'])}]")->length > 0
            ) {
                return $code;
            }
        }

        return null;
    }

    private function assignRegion(string $nameStation, ?string $trainService): void
    {
        // added region for google, to help find correct address of stations
        if (preg_match("/\bParis\b/i", $nameStation)) {
            $this->region = 'France';
        } elseif (preg_match("/(?:\bLondon\b|\bOxford\b|\bManchester\b|\bWolverhampton\b|\bHaymarket\b|\bLeicester\b)/i", $nameStation)) {
            $this->region = 'United Kingdom';
        } elseif (preg_match("/^SNCF\b/i", $trainService)) {
            // https://en.wikipedia.org/wiki/SNCF
            $this->region = 'France';
        } elseif (preg_match("/^(?:London\b|Avanti West Coast|Great Western Railway|East Midlands Railway)/i", $trainService)) {
            // https://en.wikipedia.org/wiki/Trainline
            // https://en.wikipedia.org/wiki/Avanti_West_Coast
            // https://en.wikipedia.org/wiki/Great_Western_Railway_(train_operating_company)
            $this->region = 'United Kingdom';
        } elseif (preg_match("/^(?:Deutsche Bahn)/i", $trainService)) {
            $this->region = 'Germany';
        } else {
            $this->region = 'Europe';
        }
    }

    private function parseSegments(Train $t, Bus $b)
    {
        $xpath = $this->http->XPath->query('//table[contains(@class, "row booking-confirmation-journeyrow booking-confirmation-leg")][not(contains(normalize-space(),"Arrive at least") and contains(normalize-space(),"before departure"))]');

        if ($xpath->length === 0) {
            $xpath = $this->http->XPath->query('//img[contains(@src, "/icon-terminal@2x.png")]/ancestor::table[1]');
        }

        if ($xpath->length === 0) {
            $xpath = $this->http->XPath->query('//img[contains(@src, "/images/icon-bus@1x.png")]/ancestor::table[1]');
            $typeBus = true;
        }

        if ($xpath->length === 0) {
            $xpath = $this->http->XPath->query('//table[not(.//table) and count(.//img[@width=16 and @height=16]) = 2]');
        }

        if ($xpath->length === 0) {
            $ruleTimeStart = "starts-with(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd') or starts-with(translate(normalize-space(.),'0123456789','dddddddddd--'),'dd:dd')";
            $xpath = $this->http->XPath->query("//text()[{$ruleTimeStart}]/ancestor::table[1][count(./descendant::text()[{$ruleTimeStart}])=2]");
        }

        if ($xpath->length === 0) {
            $ruleTimeStart = "starts-with(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd') or starts-with(translate(normalize-space(.),'0123456789','dddddddddd--'),'dd:dd')";
            $xpath = $this->http->XPath->query("//text()[{$ruleTimeStart}]/ancestor::*[count(./descendant::text()[{$ruleTimeStart}])=2][1]");
        }

        if ($xpath->length === 0) {
            $xpath = $this->http->XPath->query("(//span[starts-with(normalize-space(),'Spoor')]/preceding::table[1])[position() mod 2 = 1 ]/ancestor::tr[2]");
        }

        foreach ($xpath as $root) {
            $this->departing = $this->normalizeDate($this->http->FindSingleNode("preceding::text()[{$this->eq($this->t('route'))}][1]/following::text()[normalize-space(.)][1]/ancestor::*[self::p or self::div or self::td or self::tr][1]",
                $root, true, "/^\s*(?:{$this->opt($this->t('route'))})?[\s:]*(\b.+)/u"));

            if (empty($this->departing)) {
                $this->departing = $this->normalizeDate($this->http->FindSingleNode("preceding::text()[{$this->contains($this->t('with'))}][1]/preceding::text()[normalize-space(.)][1]", $root));
            }

            $text = str_replace(' ', ' ', implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $root)));

            if (!preg_match("/.*\d+\:\d+.*\d+\:\d+.*/", $text)) {
                $text = $text . "\n" . $this->http->FindSingleNode("./following::tr[1]", $root);
            }

            /*
            17:40
            Gatwick Airport
            Thameslink
            18:21
            London Blackfriars
             */

            //  18:12   Liverpool Lime Street   Transpennine Express    Coach C: seat 03 Window    18:46   Manchester Victoria

            /*11:34
            Milano Centrale
            Italo Italo 8981
            Carriage 2: Prima 17
            Carriage 2: Prima 18
            Carriage 2: Prima 19
            Carriage 2: Prima 20
            13:47
            Venezia Mestre
            */
            $text = preg_replace("/({$this->opt($this->t('Spoor'))}\s*\d+\s*Overstap.*)/", "\n$1", $text);

            if (preg_match("/(?<timeDep>{$this->patterns['time']}|—)\s+(?<airportDep>.+?)\n+Spoor.+\n+(?<number>[\s\S]*\n+)?(?<timeArr>{$this->patterns['time']}|—)\s*(?<airportArr>.+)/", $text, $matches)
                || preg_match("/(?<timeDep>{$this->patterns['time']}|—)\s+(?<airportDep>.+?)\n+(?<service>.+?)\n+(?<coach>[\s\S]*\n+)?(?<timeArr>{$this->patterns['time']}|—)\s+(?<airportArr>.+)/", $text, $matches)
                || preg_match("/(?<timeDep>{$this->patterns['time']})\s+(?<airportDep>.+)\n+(?<timeArr>{$this->patterns['time']})\s+(?<airportArr>.+)/", $text, $matches)
            ) {
                if (empty($matches['service'])) {
                    $matches['service'] = null;
                }

                if (empty($matches['coach'])) {
                    $matches['coach'] = null;
                }

                if ($this->http->XPath->query('./descendant::img[contains(@src, "/images/icon-bus@1x.png")]', $root)->length > 0
                    || preg_match("/^Bus\b/i", $matches['service'])
                    || preg_match("/\bNational Express\b/i", $matches['service'])
                    || preg_match("/\b(?:Coach|Bus) Station\b/i", $matches['airportDep'])
                    || preg_match("/\b(?:Coach|Bus) Station\b/i", $matches['airportArr'])
                ) {
                    $typeBus = true;
                } else {
                    $typeBus = false;
                }

                if ($typeBus) {
                    $s = $b->addSegment();
                } else {
                    $s = $t->addSegment();
                }

                $duration = $this->http->FindSingleNode("./preceding::text()[{$this->contains($this->t('0 changes'))}][1]", $root, true,
                    "/^\s*((\s*\d{1,2} *(?:m|h|Std|min|minutes)\.?)+)\s*,/i");

                if (!empty($duration)) {
                    $s->extra()
                        ->duration($duration);
                }

                if ($matches['timeDep'] === '—') {
                    $s->departure()->noDate();
                } elseif (!empty($this->departing)) {
                    $s->departure()->date(strtotime($matches['timeDep'], $this->departing));
                }
                $this->assignRegion($matches['airportDep'], $matches['service']);

                if (empty($this->region)) {
                    $this->region = 'Europe';
                }

                $s->departure()
                    ->name(implode(', ', [$matches['airportDep']]) . ', ' . $this->region)
                    ->geoTip($this->region);

                if (!empty($matches['service'])) {
                    $service = preg_replace([
                        '/(\w)[_]+(\w)/u',
                        '/\s{2,}/',
                        '/^\s*Train\s*$/i',
                    ], [
                        '$1 $2',
                        ' / ',
                        '',
                    ], $matches['service']);
                } else {
                    $service = null;
                }

                if (preg_match("/^(.+?\S\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])?)(\d+)$/", $service, $m)) {
                    $s->extra()->service($m[1])->number($m[2]);
                } elseif ($service && stripos($service, 'Seat reserved') === false && stripos($service, 'Seats reserved') === false
                    && empty($this->http->FindSingleNode("(//*[{$this->starts($service)}]//img[contains(@src,'ic-split-ticket.')]/following::text()[{$this->starts($service)}])[1]"))
                ) {
                    if (!preg_match("/,\s*\d/", $service)) {
                        if ($typeBus) {
                            $s->extra()->type($service);
                        } else {
                            $s->extra()->service($service);
                        }
                    }
                }

                if (!empty($matches['number'])) {
                    $s->setNumber($matches['number']);
                }

                if (preg_match("/(?:{$this->opt($this->t('Coach'))}|Carriage)\s+(?-i)([\dA-Z]{1,5})\b/i", $matches['coach'], $m)) {
                    $s->extra()->car($m[1]);
                }

                $depNameStrong = strtoupper($this->re("/^(\w+)/", $s->getDepName()));
                $depNameOrinigal = $this->re("/^(\w+)/", $s->getDepName());
                $seatText = $this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Passengers and seats'))}][1]/following::text()[{$this->starts($this->t($depNameStrong))}][1]/ancestor::tr[1]/following::tr[normalize-space()][1]", $root);

                if (empty($seatText)) {
                    $seatText = $this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Passengers and seats'))}][1]/following::text()[{$this->starts($this->t($depNameOrinigal))}][1]/ancestor::tr[1]/following::tr[normalize-space()][1]", $root);
                }
                $seats = [];

                if (preg_match_all('/(?:seat|Prima|Sitzplatz|place|zitplaatsen)\s+(?-i)([\dA-Z]{1,5})\b/iu', $matches['coach'], $m)) {
                    $seats = array_merge($seats, $m[1]);
                }

                if (preg_match_all('/executive\s+(?-i)([\dA-Z]{1,5})[,\s]+(?i)Asiento/iu', $matches['coach'], $m)) {
                    // es
                    $seats = array_merge($seats, $m[1]);
                }

                if (empty($seats) && preg_match_all("/(?:{$this->opt($this->t('Coach'))}|Carriage)\s+(?-i)[\dA-Z]+\b, *(?:[[:alpha:] ]+ )?([\dA-Z]{1,3})(?: *,|\n|$)/i", $matches['coach'], $m)) {
                    // Carriage 1, standard 9D, Place isolée
                    // Carriage 1, Standard 26E
                    $seats = array_merge($seats, $m[1]);
                }

                if (empty($seats) && !empty($seatText)) {
                    if (preg_match("/^{$this->opt($this->t('Coach'))}:?\s*([\dA-Z]{1,3})\s*{$this->opt($this->t('Seat'))}:?\s*(\d+)(?:\s*{$this->opt($this->t('cabin'))}\s*(.+)\-\D+|\s*\D+\-\s*(.+))?$/", $seatText, $m)) {
                        $s->setCarNumber($m[1]);

                        $seats = [$m[2]];
                    }
                }

                if (empty($seats)
                    && preg_match_all("/^{$this->opt($this->t('Coach'))}:?\s*([\dA-Z]{1,5})\s*{$this->opt($this->t('Seat'))}:?\s*([\dA-Z]{1,5})$/m", $matches['coach'], $m)) {
                    $s->setCarNumber($m[1][0]);

                    $seats = $m[2];
                }

                if (empty($seats) && preg_match("/^{$this->opt($this->t('Coach'))}:?\s*(?<carNumber>[\dA-Z]{1,5})\s*{$this->opt($this->t('Seat'))}:?\s*(?<seat>[\dA-Z\s\,]{1,}){$this->opt($this->t('Seat'))}/", $seatText, $m)) {
                    $s->setCarNumber($m['carNumber']);
                    $s->extra()
                        ->seats(explode(", ", $m['seat']));
                }

                if (!empty($seats)) {
                    $seats = array_filter(preg_replace("/^\s*Any\s*$/", '', $seats));

                    if (count($seats)) {
                        $s->extra()->seats(array_unique($seats));
                    }
                }

                if ($matches['timeArr'] === '—') {
                    $s->arrival()->noDate();
                } elseif (!empty($this->departing)) {
                    $adate = strtotime($matches['timeArr'], $this->departing);

                    if (!empty($s->getDepDate())) {
                        if ($adate < $s->getDepDate() && $s->getDepDate() < strtotime("+1 day", $adate)) {
                            $adate = strtotime("+1 day", $adate);
                        }
                    }
                    $s->arrival()->date($adate);
                }
                $this->assignRegion($matches['airportArr'], $matches['service']);

                if (stripos($matches['airportArr'], 'Spoor') !== false) {
                    $matches['airportArr'] = preg_replace("/\s*Spoor.+$/u", "", $matches['airportArr']);
                }

                $s->arrival()
                    ->name(implode(', ', [$matches['airportArr']]) . ', ' . $this->region)
                    ->geoTip($this->region);

                if (!$s->getNumber() && !empty($s->getDepName()) && !empty($s->getArrName())
                    && (!empty($s->getDepDate()) || !empty($s->getNoDepDate()))
                    && (!empty($s->getArrDate()) || !empty($s->getNoArrDate()))
                ) {
                    $s->extra()->noNumber();
                }

                // kostyl
                $type = 'type';

                if ($typeBus) {
                    $type = $s->getBusType();
                } else {
                    $type = $s->getServiceName();
                }

                if (($s->getDepDate() === $s->getArrDate() && strcasecmp($type, 'Tube') === 0)
                || ($s->getDepDate() === $s->getArrDate() && (stripos($s->getArrName(), 'Zone') !== false || stripos($s->getDepName(), 'Zone') !== false))) {
                    if ($typeBus) {
                        $b->removeSegment($s);
                    } else {
                        $t->removeSegment($s);
                    }
                }
            }
        }
    }

    private function parsePrice(?string $text): ?array
    {
        if (preg_match('/^(?<currencyCode>[A-Z]{3})[ ]*(?<amount>\d[,.\'\d ]*)/u', $text, $matches)) {
            return [$matches['currencyCode'], PriceHelper::parse($matches['amount'], $matches['currencyCode'])];
        } elseif (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d]*)/', $text, $matches) // £10.40
            || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[^\d)(]+)/', $text, $matches) // 17,92 £
        ) {
            $currency = preg_replace(['/£/', '/€/', '/^C\$$/'], ['GBP', 'EUR', 'CAD'], $matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;

            return [$currency, PriceHelper::parse($matches['amount'], $currencyCode)];
        }

        return null;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$langDetectors)) {
            return false;
        }

        foreach (self::$langDetectors as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases)) {
                continue;
            }

            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($str)
    {
        //$this->logger->debug('IN-' . $str);
        $year = date("Y", $this->date);
        $in = [
            // Wednesday April 4, 10:39
            '/^([-[:alpha:]]{2,})[,\s]+([[:alpha:]]{3,})\s+(\d{1,2})\s*,\s*\d+:\d+\s*$/u',
            // Wednesday April 4
            '/^[-[:alpha:]]{2,}[,\s]+([[:alpha:]]{3,})\s+(\d{1,2})$/u',
            // Dienstag 8 Oktober, 08:06    |    Thu 30th Mar, 17:40
            '/^([-[:alpha:]]{2,})\s+(\d{1,2})(?:[[:alpha:]]{2})?\s+([[:alpha:]]{3,})\s*,\s*\d+:\d+\s*$/u',
            // Dienstag 8 Oktober    |    Friday, 7 February    |    lunes, 24 de mayo
            // Montag, 13. März
            '/^[-[:alpha:]]{2,}[,\s]+(\d{1,2})(?:\s+de|.)?\s+([[:alpha:]]{3,})$/u',
            // ma 07 mrt 2022
            '/^\w+\s+(\d+)\s+(\w+)\s+(\d{4})$/u',
            // Dienstag, 29. November
            '/^(\w+\,\s*\d+)\.\s*(\w+)$/',
        ];
        $out = [
            '$1, $3 $2 ' . $year,
            '$2 $1 ' . $year,
            '$1, $2 $3 ' . $year,
            '$1 $2 ' . $year,
            '$1 $2 $3',
            '$1 $2 ' . $year,
        ];
        $str = preg_replace($in, $out, $str);
        //$this->logger->debug('OUT-' . $str);

        if (preg_match("#\d+\s+([^\d\s]+)(?:\s+\d{4}|$)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            } elseif ($en = MonthTranslate::translate($m[1], 'fr')) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (preg_match("#^(?<week>\w+), (?<date>\d+ \w+ .+)#u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
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
