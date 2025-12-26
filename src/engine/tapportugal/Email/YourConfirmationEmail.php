<?php

namespace AwardWallet\Engine\tapportugal\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourConfirmationEmail extends \TAccountChecker
{
    public $mailFiles = "tapportugal/it-100494053.eml, tapportugal/it-109437167.eml, tapportugal/it-111940236-de.eml, tapportugal/it-112092190-es.eml, tapportugal/it-112274369-pt.eml, tapportugal/it-125617668-pt.eml, tapportugal/it-139047632-junk.eml, tapportugal/it-69798995.eml, tapportugal/it-71127761.eml, tapportugal/it-73534050.eml, tapportugal/it-91879227.eml, tapportugal/it-92026189-es-cancelled.eml, tapportugal/it-93094854-fr.eml, tapportugal/it-93536940.eml, tapportugal/it-861959382-pt-junk.eml";

    public static $dictionary = [
        'en' => [
            'hello'              => ['Mr./Mrs.', 'Mr./Mrs', 'Hello'],
            'Booking reference'  => ['Booking reference', 'Booking Reference', 'Booking'],
            'Your booking is'    => ['Your booking is', 'Your Flight reservation'],
            'statusVariants'     => ['confirmed', 'changed', 'cancelled', 'canceled'],
            'New Flight'         => ['New Flight', 'New flight details', 'Itinerary updated', 'Your flights', 'New flight(s)'],
            'Ticket Number:'     => ['Ticket Number:', 'Ticket number:', 'Ticket number'],
            // 'Miles earned' => '',
            // 'stop' => '',
            // 'to' => '',
            'Flight operated by' => ['Flight operated by', 'Voo operado por'],
            // 'Seat' => '',
            // 'Reservation date' => '',
            // 'cancelledPhrase' => '',
            'accNumber' => 'Client number',
            // 'flights' => '',
            'Your old flight'   => 'Your old flight',
            'completeYourBooking' => [
                'Complete your booking otherwise it will be cancelled',
                'Complete your booking otherwise it will be canceled',
            ],
            'completeBookingBtn' => 'Complete Booking',

            //Price
            'Total:'  => ['Total:', 'Total for all passengers'],
            'cost-v1' => 'Tickets',
            'cost-v2' => ['Flights', 'flights'],
            // 'fee' => '',
            // 'Extras' => '',
            // 'Total Summary' => '',
        ],
        'fr' => [
            'hello'              => 'Bonjour',
            'Booking reference'  => 'Référence de la réservation',
            'Your booking is'    => 'Votre réservation est',
            'statusVariants'     => 'confirmée',
            // 'New Flight' => '',
            'Ticket Number:'     => 'Numéro du billet',
            'Miles earned'       => 'Miles gagnés',
            // 'stop' => '',
            'to'                 => 'vers',
            'Flight operated by' => 'Vol opéré par',
            'Seat'               => 'Siège',
            'Reservation date'   => 'Date de la réservation',
            // 'cancelledPhrase' => '',
            // 'accNumber' => '',
            'flights' => ['voos', 'flights'],
            // 'Your old flight' => '',
            // 'completeYourBooking' => '',
            // 'completeBookingBtn' => '',

            //Price
            'Total:' => 'Total pour tous les passagers',
            // 'cost-v1' => '',
            // 'cost-v2' => '',
            'fee'    => 'frai',
            // 'Extras' => '',
            // 'Total Summary' => '',
        ],
        'pt' => [
            'hello' => 'Olá',
            'Booking reference'  => ['Referência da reserva', 'Referência de reserva', 'Código de reserva'],
            'Your booking is'    => 'A sua reserva está',
            'statusVariants'     => 'confirmada',
            'New Flight'         => 'O seu novo voo',
            'Ticket Number:'     => ['Número do bilhete', 'Bilhete:', "Número do bilhete:"],
            'Miles earned'       => 'Milhas ganhas',
            'stop' => 'paragem',
            'to'                 => 'para',
            'Flight operated by' => 'Voo operado por',
            'Seat'               => 'Assento',
            'Reservation date'   => 'Data da reserva:',
            // 'cancelledPhrase' => '',
            'accNumber'          => 'Número do cliente',
            'flights'            => ['voos', 'flights'],
            'Your old flight'    => 'o seu voo original',
            'completeYourBooking' => 'Conclua a sua reserva; caso contrário, será cancelada',
            'completeBookingBtn' => 'Concluir a reserva',

            //Price
            'Total:'        => ['Taxas, sobretaxas e encargos da transportadora incluídos', 'Total relativo a todos os passageiros', 'Total para todos os passageiros', 'Total:'],
            'cost-v1'       => 'Voos',
            'cost-v2'       => 'Voos',
            'fee'           => 'encargos',
            'Extras'        => 'Sobretaxa de cartão de crédito',
            'Total Summary' => ['Price breakdown', 'Resumo total'],
        ],
        'de' => [
            'hello'             => 'Hallo',
            'Booking reference' => 'Buchungsreferenz',
            'Your booking is'   => 'Ihre Buchung ist',
            'statusVariants'    => 'bestätigt',
            // 'New Flight' => '',
            'Ticket Number:' => 'Ticket-Nummer',
            //'Miles earned'       => '',
            // 'stop' => '',
            'to'                 => 'nach',
            'Flight operated by' => 'Flug durchgeführt von',
            //'Seat'               => '',
            //'Reservation date'   => '',
            // 'cancelledPhrase' => '',
            // 'accNumber' => '',
            // 'flights' => '',
            // 'Your old flight' => '',
            // 'completeYourBooking' => '',
            // 'completeBookingBtn' => '',

            //Price
            'Total:'  => 'Steuern, Abgaben und Fluggebühren inbegriffen',
            'cost-v1' => 'Flüge',
            // 'cost-v2' => '',
            //'fee'    => '',
            'Extras'        => 'Sobretaxa de cartão de crédito',
            'Total Summary' => 'Price breakdown',
        ],
        'es' => [
            'hello'             => 'Hola',
            'Booking reference' => ['Código de la reserva', 'Cdigo de la reserva'],
            'Your booking is'   => 'Reserva',
            'statusVariants'    => 'confirmada',
            // 'New Flight' => '',
            'Ticket Number:' => 'Número de billete',
            // 'Miles earned'     => '',
            // 'stop' => '',
            'to'                 => 'a',
            'Flight operated by' => 'Vuelo operado por',
            'Seat'               => 'Asiento',
            'Reservation date'   => 'Fecha de reserva',
            'cancelledPhrase'    => ['Cancelou a seguinte reserva'],
            // 'accNumber' => '',
            // 'flights' => '',
            // 'Your old flight' => '',
            // 'completeYourBooking' => '',
            // 'completeBookingBtn' => '',

            //Price
            'Total:'        => 'Impuestos, tasas y cargos de transporte incluidos',
            'cost-v1'       => 'Vuelos',
            // 'cost-v2' => '',
            // 'fee' => '',
            // 'Extras' => '',
            'Total Summary' => 'Price breakdown',
        ],
        'it' => [
            'hello'             => 'Salve',
            'Booking reference' => 'Codice prenotazione',
            'Your booking is'   => 'La prenotazione è',
            'statusVariants'    => 'confermata',
            // 'New Flight' => '',
            'Ticket Number:' => 'Numero biglietto',
            //'Miles earned'       => '',
            // 'stop' => '',
            'to'                 => 'a',
            'Flight operated by' => 'Volo operato da',
            //'Seat'               => '',
            'Reservation date'   => 'Data della prenotazione',
            // 'cancelledPhrase' => '',
            // 'accNumber' => '',
            // 'flights' => '',
            // 'Your old flight' => '',
            // 'completeYourBooking' => '',
            // 'completeBookingBtn' => '',

            //Price
            'Total:'  => ['Tasse, imposte e spese di trasporto incluse'],
            //            'cost-v1' => 'Flüge',
            'cost-v2' => 'Voli',
            //'fee'    => '',
            'Extras'        => 'Sobretaxa de cartão de crédito',
            'Total Summary' => 'Price breakdown',
        ],
    ];

    private $detectFrom = 'flytap.com';
    private $detectSubject = [
        'Your Confirmation Email',
        'Online check-in here',
        'Reservation Change',
        'Booking Confirmation E-mail',
        'Confirmation Email - ', //  Confirmation Email - Award; Confirmation Email - Stopover;
        'Cancellation Confirmation Email All Pax',
        'Flight Changed Confirmation Email',
    ];

    private $lang = 'en';
    private $date;

    private $detectBody = [
        'pt' => [
            'Os seus voos',
            'Passagens aéreas',
            'Seus voos',
            'Alterou com sucesso um voo',
        ],
        'de' => 'Ihre Flüge',
        'en' => [
            'See booking details', 'See Booking Details',
            'Online check-in for your flight from',
            'New Flight',
            'See everything that is included in your trip',
            'Please check your updated details below',
            'TAP informs you that some changes have occurred on your flights',
            'You have cancelled this booking:',
            'TAP informs you that your flight departure time has changed',
            'Attention: Fill the mandatory online PLC form',
            'When flying with TAP',
            'You have successfully changed',
        ],
        'es' => [
            'Esta reserva foi cancelada para todos os passageiros',
            'Fecha de reserva',
        ],
        'fr' => 'Voir tout ce qui est inclus dans votre voyage',
        'it' => [
            'I tuoi voli',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->date = strtotime($parser->getDate());

        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (strpos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $href = ['.flytap.com/', 'www.flytap.com', 'booking.flytap.com', 'myb.flytap.com', 'receipts.flytap.com'];

        if ($this->http->XPath->query("//a[{$this->contains($href, '@href')} or {$this->contains($href, '@originalsrc')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['go to flytap.com', 'Flight operated by TAP Air Portugal'])}]")->length === 0
        ) {
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

    private function parseHtml(Email $email): void
    {
        $xpathNoEmpty = 'string-length(normalize-space())>1';

        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?',
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]',
        ];

        $f = $email->add()->flight();

        // General
        $confirmation = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Booking reference'))}])[1]/following::text()[normalize-space()][1]", null, true, "/^[A-Z\d]{5,}$/");
        $confirmationTitle = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Booking reference'))}])[1]", null, true, '/^(.+?)[\s:：]*$/u');

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your booking is'))}]/following::text()[{$this->starts($this->t('Booking reference'))}][1]/following::text()[normalize-space()][1]", null, true, "/^\s*\:?\s*([A-Z\d]{5,7})\s*$/");
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your booking is'))}]/following::text()[{$this->starts($this->t('Booking reference'))}][1]", null, true, '/^(.+?)[\s:：]*$/u');
        }

        if (!empty($confirmation)) {
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $travellers = $this->http->FindNodes("//text()[" . $this->eq($this->t("Ticket Number:")) . "]/preceding::text()[normalize-space()][1]");

        if (count($travellers) == 0) {
            $travellers = $this->http->FindNodes("//text()[" . $this->starts($this->t("Ticket Number:")) . "]/preceding::text()[normalize-space()][1]");
        }

        if (count($travellers) > 0) {
            $f->general()
                ->travellers($travellers, true);
        } else {
            $traveller = null;

            $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('hello'), "translate(.,',','')")}]/ancestor::*[ descendant::text()[normalize-space()][2] ][1]", null, "/^{$this->opt($this->t('hello'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

            if (count(array_unique($travellerNames)) === 1) {
                $traveller = array_shift($travellerNames);
            }

            if (empty($traveller)) {
                $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('hello'))}]", null, "/^{$this->opt($this->t('hello'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));
    
                if (count(array_unique($travellerNames)) === 1) {
                    $traveller = array_shift($travellerNames);
                }
            }

            if (!empty($traveller)) {
                $f->general()->traveller($traveller);
            }
        }

        $status = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your booking is'))}]", null, true, "/{$this->opt($this->t('Your booking is'))}\s*({$this->opt($this->t('statusVariants'))})(?:\s*[,.:;!?]|$)/i");

        if (!empty($status)) {
            $f->general()->status($status);
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('cancelledPhrase'))}]")->length > 0) {
            $f->general()
                ->cancelled()
                ->status('cancelled');

            if (empty($confirmation)) {
                $f->general()
                    ->noConfirmation();
            }
        }

        $dateReserv = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Reservation date'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Reservation date'))}[:\s]*([^:\s].+)/");

        if (!empty($dateReserv)) {
            $f->general()
                ->date(strtotime($dateReserv));
        }

        // Issued
        $ticketNumbers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Ticket Number:'))}]/following::text()[normalize-space()][1]", null, "/^[:\s]*(?:{$this->opt($this->t('Ticket Number:'))}\s*|Ticket Number:\s*)?(\d{3}(?: | ?- ?)?[\d ]{4,}(?: | ?- ?)?\d{1,3})$/"));
        $f->issued()->tickets($ticketNumbers, false);

        $earnedAwards = array_sum($this->http->FindNodes("//text()[{$this->eq($this->t('Miles earned'))}]/following::text()[normalize-space()][1]", null, "/^[:\s]*(\d+)$/"));

        if (!empty($earnedAwards)) {
            $f->program()->earnedAwards($earnedAwards);
        }

        // Account
        $account = $this->http->FindSingleNode("//tr[{$this->eq($this->t('accNumber'))}]/following-sibling::tr[normalize-space()][1]", null, true, "/^\s*(\d{5,})\s*$/");

        if (!empty($account)) {
            $f->program()
                ->account($account, false);
        }

        // Price
        $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total:")) . "]/following::text()[contains(normalize-space(), ',')][not({$this->contains($this->t('fee'))})][1]");

        if (preg_match("/^(?<curr>[A-Z]{3})\s*(?<amount>\d[\d., ]*)$/", $total, $m)
            || preg_match("/^(?<amount>\d[\d., ]*)\s*(?<curr>[A-Z]{3})$/", $total, $m)
            || preg_match("/^(?<spentAwards>\d[\d., ]*\S+)[\s+]+(?<amount>\d[\d., ]*)\s*(?<curr>[A-Z]{3})$/", $total, $m)
            || preg_match("/^(?<spentAwards>\d[\d., ]*\s*M)\s*$/", $total, $m)
        ) {
            // 1.200 M + 1.714,74 USD

            $currencyCode = preg_match('/^[A-Z]{3}$/', $m['curr']) ? $m['curr'] : null;
            $f->price()
                ->total(PriceHelper::parse($m['amount'], $currencyCode))
                ->currency($this->currency($m['curr']));

            if (!empty($m['spentAwards'])) {
                $f->price()->spentAwards($m['spentAwards']);
            }

            $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Total Summary"))}]/following::text()[{$this->eq($this->t('cost-v1'))}]/following::text()[normalize-space()][1]", null, true, "/^.*\d.*$/");

            if (empty($cost)) {
                $cost = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('cost-v2'))}] ]/*[normalize-space()][2]", null, true, "/^.*\d.*$/");
            }

            if (preg_match("/(?:^|[+]\s+)" . preg_quote($m['curr']) . "\s*(?<amount>\d[\d., ]*)(?:\s+[+]|$)/", $cost, $mat)
                || preg_match("/(?:^|[+]\s+)(?<amount>\d[\d., ]*)\s*" . preg_quote($m['curr']) . "(?:\s+[+]|$)/", $cost, $mat)
            ) {
                // 1.200 M + 1.146,00 USD
                $f->price()->cost(PriceHelper::parse($mat['amount'], $currencyCode));
            }

            $taxes = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Total Summary"))}]/following::text()[{$this->eq($this->t("Taxes"))}]/following::text()[normalize-space()][1]", null, true, "/^.*\d.*$/");

            if (empty($taxes)) {
                $taxes = $this->http->FindSingleNode("//text()[normalize-space()='Taxes']/ancestor::tr[normalize-space()][1]/descendant::td[last()]", null, true, "/^.*\d.*$/");
            }

            if (preg_match("/(?:^|[+]\s+)" . preg_quote($m['curr']) . "\s*(?<amount>\d[\d., ]*)(?:\s+[+]|$)/", $taxes, $mat)
                || preg_match("/(?:^|[+]\s+)(?<amount>\d[\d., ]*)\s*" . preg_quote($m['curr']) . "(?:\s+[+]|$)/", $taxes, $mat)
            ) {
                $f->price()->tax(PriceHelper::parse($mat['amount'], $currencyCode));
            }

            $extras = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total Summary")) . "]/following::text()[" . $this->eq($this->t("Extras")) . "]/following::text()[normalize-space()][1]");

            if (preg_match("/(?:^|[+]\s+)" . preg_quote($m['curr']) . "\s*(?<amount>\d[\d\., ]*)(?:\s+[+]|$)/", $extras, $mat)
                || preg_match("/(?:^|[+]\s+)(?<amount>\d[\d\., ]*)\s*" . preg_quote($m['curr']) . "(?:\s+[+]|$)/", $extras, $mat)
            ) {
                $f->price()->fee($this->t("Extras"), PriceHelper::parse($mat['amount'], $currencyCode));
            }

            $discount = $this->http->FindSingleNode("//td[normalize-space() = 'Discount']/following::text()[normalize-space()][1]", null, true, "/\s([\d\,\.]+.+)/u");

            if (preg_match("/(?:^|[+]\s+)" . preg_quote($m['curr']) . "\s*(?<amount>\d[\d., ]*)(?:\s+[+]|$)/", $discount, $mat)
                || preg_match("/(?:^|[+]\s+)(?<amount>\d[\d., ]*)\s*" . preg_quote($m['curr']) . "(?:\s+[+]|$)/", $discount, $mat)
            ) {
                $f->price()->discount(PriceHelper::parse($mat['amount'], $currencyCode));
            }
        }

        // Segments
        if ($this->http->XPath->query("//text()[{$this->eq($this->t('New Flight'))}]")->length > 0) {
            $xpath = "//text()[{$this->eq($this->t('New Flight'))}]/following::img[contains(@alt,'TAP logo') or contains(@alt,'TAP Air Portugal short logo') or contains(@alt,'Multiple airline image') or contains(@alt,'Airline image')]/ancestor::tr[ *[normalize-space()][2] ][1]";
            $segments = $this->http->XPath->query($xpath);

            $f->general()
                ->status('change');
        } else {
            $xpath = "//img[contains(@src,'arrow-flight') or contains(@src,'/airline-multiple.') or contains(@src,'/airline-single.')]/ancestor::tr[ *[normalize-space()][2] ][1]";
            $segments = $this->http->XPath->query($xpath);

            if ($segments->length == 0) {
                $xpath = "//img[contains(@alt,'TAP logo') or contains(@alt,'Multiple airline image') or contains(@alt,'Airline image')]/ancestor::tr[ *[normalize-space()][2] ][1]";
                $segments = $this->http->XPath->query($xpath);
            }

            if ($segments->length == 0) {
                $xpath = "//img[contains(@altx,'TAP logo') or contains(@altx,'Multiple airline image') or contains(@altx,'Airline image')]/ancestor::tr[ *[normalize-space()][2] ][1]";
                $segments = $this->http->XPath->query($xpath);
            }
        }

        $this->logger->info('[XPath] segments:');
        $this->logger->debug($xpath);

        foreach ($segments as $root) {
            $duration = $this->http->FindSingleNode("td[normalize-space()][last()]", $root, null, "/(?:^|[\s\D])(\d{1,3}\s*h\s*\d{1,3}m)/i");
            $durationNormal = $this->normalizeDuration($duration);
            $stops = $this->http->FindSingleNode("td[normalize-space()][3]/descendant::text()[normalize-space()][2]", $root, null, "/^\s*(\d{1,3})[ ]*{$this->opt($this->t('stop'))}/");

            $date = $this->normalizeDate($this->http->FindSingleNode("ancestor::*[self::tr or self::div][ preceding-sibling::*[self::tr or self::div][normalize-space()] ][1]/preceding-sibling::*[self::tr or self::div][normalize-space()][1]/descendant::td[not(.//tr) and normalize-space()][1]", $root, true, "/^.*\d.*$/"));

            if ($this->http->XPath->query("preceding::text()[normalize-space()][2][{$this->eq($this->t('Your old flight'))}]", $root)->length > 0) {
                continue;
            }

            /*
                18:20 +1
                BOS
            */
            $pattern1 = "/^(?<time>{$patterns['time']})(?:\s*(?<overtime>[-+]\s*\d+))?\s+(?<code>[A-Z]{3})$/";
            /*
                BOS
                18:20 +1
            */
            $pattern2 = "/^(?<code>[A-Z]{3})\s+(?<time>{$patterns['time']})(?:\s*(?<overtime>[-+]\s*\d+))?$/";

            $date1 = $date2 = $airport1 = $airport2 = null;

            $departure = implode("\n", $this->http->FindNodes("*[normalize-space()][1]/descendant::text()[normalize-space()]", $root));

            if (preg_match($pattern1, $departure, $m) || preg_match($pattern2, $departure, $m)) {
                $date1 = empty($date) ? null : strtotime($m['time'], $date);
                $airport1 = $m['code'];
            }

            $arrival = implode("\n", $this->http->FindNodes("*[normalize-space()][2]/descendant::text()[normalize-space()]", $root));

            if (preg_match($pattern1, $arrival, $m) || preg_match($pattern2, $arrival, $m)) {
                $date2 = empty($date) ? null : strtotime($m['time'], $date);
                $airport2 = $m['code'];

                if (!empty($m['overtime']) && !empty($date2)) {
                    $date2 = strtotime($m['overtime'] . ' days', $date2);
                } elseif ($durationNormal && $date1 && date('d', $date1) !== date('d', strtotime($durationNormal, $date1))) {
                    $date2 = strtotime('+1 days', $date2); // dirty hack!
                }
            }

            $flight = $this->http->FindSingleNode("descendant::td[not(.//tr) and normalize-space()][last()]", $root);

            if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/', $flight, $m)) {
                // it-93536940.eml
                $s = $f->addSegment();

                $s->departure()
                    ->date($date1)
                    ->code($airport1)
                ;
                $s->arrival()
                    ->date($date2)
                    ->code($airport2)
                ;

                $s->extra()->duration($duration, false, true)->stops($stops, false, true);

                $s->airline()
                    ->name($m['name'])
                    ->number($m['number'])
                ;

                // TP834 - LISBON (Terminal 1) to ROME
                $flightVariants = [$m['name'] . $m['number'] . ' - ', $m['name'] . ' ' . $m['number'] . ' - '];
                $segmentDetails = $this->htmlToText($this->http->FindHTMLByXpath("ancestor::table[ following-sibling::table[{$this->contains($this->t('Flight operated by'))} or {$this->starts($flightVariants)}] ][1]/following-sibling::table[normalize-space()][1]", null, $root));

                $s->departure()->terminal($this->re("/{$this->opt($this->t('Terminal'))}\s+([A-z\d ]+?)[)\s]+{$this->opt($this->t('to'))}\s*/", $segmentDetails), false, true);
                $s->arrival()->terminal($this->re("/\s{$this->opt($this->t('to'))}\s.+{$this->opt($this->t('Terminal'))}\s*([A-z\d ]+?)[) ]*(?:\n|$)/", $segmentDetails), false, true);

                if (preg_match("/{$this->opt($this->t('Flight operated by'))}\s*(.*)\s*,\s*(.+)/", $segmentDetails, $m)) {
                    if (!empty(trim($m[1]))) {
                        $s->airline()->operator($m[1]);
                    }

                    if (!empty($m[2]) && $m[2] !== 'null') {
                        $s->extra()->aircraft($m[2]);
                    }
                }

                if ($s->getDepCode() && $s->getArrCode()) {
                    $seats = $this->http->FindNodes("//tr[ not(.//tr) and starts-with(normalize-space(),'({$s->getDepCode()})') and contains(normalize-space(),'({$s->getArrCode()})') ]/following::text()[normalize-space()][1][{$this->starts($this->t('Seat'))}][1]/following::text()[normalize-space()][1]", null, "/^[:\s]*(\d+[A-Z])$/");

                    if (count($seats) > 0) {
                        $s->extra()->seats(array_filter(array_unique($seats)));
                    }
                }
            } elseif (preg_match("/^(\d{1,3})\s+(?:{$this->opt($this->t('flight'))}|{$this->opt($this->t('flights'))})$/", $flight, $matches)) {
                // it-100494053.eml
                $xpathSS = "ancestor::table[ following-sibling::table[{$this->contains($this->t('Flight operated by'))}] ][1]/following-sibling::table[{$xpathNoEmpty}]";
                $xpath = $xpathSS . "[position()<={$matches[1]}]/descendant::text()[{$this->contains($this->t('Flight operated by'))}]/ancestor::tr[ descendant::text()[normalize-space()][2] ][1]";
                $subSegments = $this->http->XPath->query($xpath, $root);

                $this->logger->info('[XPath] sub-segments:');
                $this->logger->debug($xpath);

                /*
                    Remove duplicate sub-segments (start)
                    Examples: it-861959382-pt.eml
                */
                $uniqSubSegments = $uniqSubSegmentsTexts = [];

                foreach ($subSegments as $root2) {
                    $SSText = trim($root2->nodeValue);

                    if (!in_array($SSText, $uniqSubSegmentsTexts)) {
                        $uniqSubSegments[] = $root2;
                        $uniqSubSegmentsTexts[] = $SSText;
                    }
                }
                /* Remove duplicate sub-segments (end) */

                $stopoverNights = $stopoverCity = null;
                $stopoverText = $this->http->FindSingleNode($xpathSS . "[" . ($matches[1] + 1) . "]", $root);

                if (preg_match("/^(?<nights>\d{1,3})\s+{$this->opt($this->t('nights stopover in'))}\s+(?<city>.{3,})$/", $stopoverText, $m)) {
                    $stopoverNights = $m['nights'];
                    $stopoverCity = $m['city'];
                }

                $lastCity = null;

                foreach ($uniqSubSegments as $key => $root2) {
                    $s = $f->addSegment();

                    $stopoverExist = false;

                    /*
                        TP204 - (EWR) New York , Terminal B to (LIS) Lisbon , Terminal 1
                        Flight operated by TAP Air Portugal, A321NEO
                    */
                    $subSegmentText = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $root2));

                    if (preg_match("/^\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)\s+-\s+(?<airports>.+?)[ ]*(?:\n|$)/", $subSegmentText, $m)) {
                        // TP204 - (EWR) New York , Terminal B to (LIS) Lisbon , Terminal 1
                        $s->airline()->name($m['name'])->number($m['number']);
                        $airports = preg_split("/\s+{$this->opt($this->t('to'))}\s+/", $m['airports']);

                        if (count($airports) === 2) {
                            // (EWR) New York , Terminal B
                            $patterns['codeCityTerm'] = "/^\(\s*(?<code>[A-Z]{3})\s*\)\s*(?<city>[^,]{3,}?)?(?:\s*,\s*Terminal\s+(?<terminal>[A-z\d ]+))?$/";

                            if (preg_match($patterns['codeCityTerm'], $airports[0], $m2)) {
                                if ($m2['city'] === $lastCity && $m2['city'] === $stopoverCity) {
                                    $stopoverExist = true;
                                }
                                $s->departure()->code($m2['code']);

                                if (!empty($m2['terminal'])) {
                                    $s->departure()->terminal($m2['terminal']);
                                }
                            }

                            if (preg_match($patterns['codeCityTerm'], $airports[1], $m2)) {
                                $lastCity = $m2['city'];
                                $s->arrival()->code($m2['code']);

                                if (!empty($m2['terminal'])) {
                                    $s->arrival()->terminal($m2['terminal']);
                                }
                            }
                        }
                    }

                    if (preg_match("/{$this->opt($this->t('Flight operated by'))}\s*(.*)\s*,\s*(.+)/", $subSegmentText, $m)) {
                        if (!empty(trim($m[1]))) {
                            $s->airline()->operator($m[1]);
                        }

                        if (!empty($m[2]) && $m[2] !== 'null') {
                            $s->extra()->aircraft($m[2]);
                        }
                    }

                    if ($key === 0) {
                        $s->departure()->date($date1);
                    } else {
                        $s->departure()->noDate();
                    }

                    if ($key === count($uniqSubSegments) - 1) {
                        if (isset($stopoverNights) && !empty($stopoverNights)) {
                            $s->arrival()->date($stopoverExist ? strtotime('+' . $stopoverNights . ' days', $date2) : $date2);
                        } else {
                            $s->arrival()->date($date2);
                        }
                    } else {
                        $s->arrival()->noDate();
                    }

                    if ($s->getDepCode() && $s->getArrCode()) {
                        $seats = $this->http->FindNodes("//tr[ not(.//tr) and starts-with(normalize-space(),'({$s->getDepCode()})') and contains(normalize-space(),'({$s->getArrCode()})') ]/following::text()[normalize-space()][1][{$this->starts($this->t('Seat'))}][1]/following::text()[normalize-space()][1]", null, "/^[:\s]*(\d+[A-Z])$/");

                        if (count($seats) > 0) {
                            $s->extra()->seats(array_filter(array_unique($seats)));
                        }
                    }
                }
            }
        }

        /* Junk-formats */

        if (!$confirmation && $segments->length === 0) {
            return;
        }

        $isJunk = $junkMessage = false;

        if ($this->http->XPath->query("//text()[contains(normalize-space(),'There was a problem with your payment')]")->length > 0) {
            // it-139047632-junk.eml
            $this->logger->warning('WARNING: May be junk!');
            $isJunk = true;
            $junkMessage = 'There was a problem with your payment';
        } elseif ($this->http->XPath->query("//text()[{$this->contains($this->t('completeYourBooking'))}]")->length > 0
            && $this->http->XPath->query("//a[{$this->eq($this->t('completeBookingBtn'))}]")->length > 0
        ) {
            // it-861959382-pt-junk.eml
            $isJunk = true;
            $junkMessage = 'Booking was not completed';
        }

        if ($isJunk) {
            $email->removeItinerary($f);
            $email->setIsJunk(true, $junkMessage);
        }
    }

    private function assignLang(): bool
    {
        if ( !isset($this->detectBody, $this->lang) ) {
            return false;
        }
        foreach ($this->detectBody as $lang => $phrases) {
            if ( !is_string($lang) || empty($phrases) ) {
                continue;
            }
            if ($this->http->XPath->query("//*[{$this->contains($phrases)}]")->length > 0) {
                $this->lang = $lang;
                return true;
            }
        }
        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDuration(?string $str): ?string
    {
        if ($str === null) {
            return null;
        }
        $str = preg_replace('/(^|\D)(\d+)\s*h([^[:alpha:]]|$)/i', '$1$2 hours$3', $str);
        $str = preg_replace('/(^|\D)(\d+)\s*m([^[:alpha:]]|$)/i', '$1$2 minutes$3', $str);

        return $str;
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $year = date('Y', $this->date);
        $in = [
            // Mon, 01 Feb    |    Mon 01 Feb
            '/^\s*([-[:alpha:]]+)[,\s]+0?(\d+)\s+([[:alpha:]]+)\s*$/u',
        ];
        $out = [
            '$1, $2 $3 ' . $year,
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }
//        $this->logger->debug('$date = '.print_r( $date,true));

        if (preg_match("#^(?<week>\w+), (?<date>\d+ \w+ .+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], 'en'));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
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
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
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
}
