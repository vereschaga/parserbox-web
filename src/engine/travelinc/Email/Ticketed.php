<?php

namespace AwardWallet\Engine\travelinc\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;

// instead of `parseEmailPDF` have similar method `ctraveller/TravelItineraryPdf::parsePdf`

class Ticketed extends \TAccountChecker
{
    public $mailFiles = "travelinc/it-12477352.eml, travelinc/it-12483746.eml, travelinc/it-161079033.eml, travelinc/it-216423327.eml, travelinc/it-631572103.eml, travelinc/it-63272571.eml, travelinc/it-637835382.eml, travelinc/it-668012614.eml, travelinc/it-67508813.eml, travelinc/it-67600222.eml, travelinc/it-701767853-amextravel-es.eml";

    public $pdfNamePattern = ".*pdf";
    private $providerCode = '';
    private $lang = '';
    private $travellers = [];
    private $ticketNumber;
    private $confNumber;
    private $bookingDate;
    private $reFrom = ['@worldtravelinc.com'];
    private $reProvider = ['World Travel Inc', 'Safe Harbors Business Travel, LLC'];
    private $reSubject = [
        'Su itinerario de viajes ', // es
        'Ticketed Invoice for ',
        'Trip Reminder - Confirmation for ',
    ];
    private $reBody = [
        'es' => [
            ['Localizador de registros de World Travel', 'Pasajero'],
            ['Localizador de registros de American Express', 'Pasajero'],
        ],
        'en' => [
            ['World Travel Record Locator', 'Please review your itinerary upon receipt.'],
            ['American Express Record Locator', 'Please review your itinerary upon receipt.'],
        ],
    ];
    private static $dictionary = [
        'es' => [
            // 'its' => '',
            // 'CHECK IN DATE' => '',
            // 'CHECK OUT DATE' => '',
            // 'PICK UP' => '',
            // 'DROP OFF' => '',
            // 'Check In Date' => '',
            // 'Check Out Date' => '',
            // 'Limousine' => '',
            'its2'                        => ['Salida'],
            'DEPARTURE'                   => 'Salida',
            'ARRIVAL'                     => 'Llegada',
            'Equipment:'                  => 'Equipo:',
            'Class:'                      => 'Clase:',
            'Seat:'                       => 'Asiento:',
            'Estimated Time:'             => 'Tiempo estimado:',
            'Non-stop'                    => 'Directo',
            'World Travel Record Locator' => ['Localizador de registros de World Travel', 'Localizador de registros de American Express'],
            // 'Cancellation Policy:' => '',
            // 'Rooms / Type:' => '',
            // 'Rate:' => '',
            // 'No. Of Rooms:' => '',

            'Passenger' => 'Pasajero',
            // 'Passenger:' => '',
            // 'Ticket Number:' => '',
            // 'Estimated Total Cost' => '',
            // 'Total Invoice Amount' => '',
            // 'Service Fee Amount' => '',
            // 'Ticket Amount' => '',
            'Confirmation:' => 'Referencia de reserva de la aerolínea:',
            // 'Frequent Traveler ID:' => '',
            'Terminal' => 'TERMINAL',
            'Status:'  => 'Estado:',

            // 'Type:' => '',
            // 'Total:' => '',
            // 'Persons:' => '',
            // 'ADDRESS:' => '',
            // 'Phone:' => '',
            // 'Fax:' => '',

            // 'Booking Ref:' => '',
            // 'Issue Date:' => '',
            // 'DEPARTURE:' => '',
            // 'ARRIVAL:' => '',
            // 'Booking Class:' => '',
            // 'Duration:' => '',
            // 'PICK UP:' => '',
            // 'DROP OFF:' => '',
            // 'Car' => '',
            // 'Stops:' => '',
            'Meal:' => 'Meal Info:',
            // 'Aircraft:' => '',
            // 'Check In' => '',
            // 'Frequent Flyer' => '',
            // 'ETicket No.' => '',
            'Status' => 'Estado',

            // parseEmail1
            // 'Flight' => '',
            // 'Car Rental' => '',
            // 'Hotel Information' => '',
        ],
        'en' => [
            'its'                         => ['Flight', 'Car Rental', 'Hotel Information'],
            'CHECK IN DATE'               => ['CHECK IN DATE', 'CHECK IN:'],
            'CHECK OUT DATE'              => ['CHECK OUT DATE', 'CHECK OUT:'],
            // 'PICK UP' => '',
            // 'DROP OFF' => '',
            // 'Check In Date' => '',
            // 'Check Out Date' => '',
            // 'Limousine' => '',
            'its2'                        => ['CHECK IN DATE', 'Check In Date', 'DEPARTURE', 'PICK UP', 'DEPARTURE:', 'CHECK IN:'],
            'DEPARTURE'                   => ['DEPARTURE', 'DEPARTURE:'],
            // 'ARRIVAL' => '',
            'Equipment:'                  => ['Equipment:', 'Aircraft:'],
            // 'Class:' => '',
            // 'Seat:' => '',
            'Estimated Time:'             => ['Estimated Time:', 'Duration:'],
            // 'Non-stop' => '',
            'World Travel Record Locator' => ['World Travel Record Locator', 'American Express Record Locator', 'Booking Ref:'],
            'Cancellation Policy:'        => ['Cancellation Policy:', 'Cancel Policy:'],
            'Rooms / Type:'               => ['Rooms / Type:', 'Room Description:'],
            // 'Rate:' => '',
            // 'No. Of Rooms:' => '',

            // 'Passenger' => '',
            // 'Passenger:' => '',
            // 'Ticket Number:' => '',
            // 'Estimated Total Cost' => '',
            // 'Total Invoice Amount' => '',
            // 'Service Fee Amount' => '',
            // 'Ticket Amount' => '',
            // 'Confirmation:' => '',
            // 'Frequent Traveler ID:' => '',
            // 'Terminal' => '',
            // 'Status:' => '',

            // 'Type:' => '',
            // 'Total:' => '',
            // 'Persons:' => '',
            // 'ADDRESS:' => '',
            // 'Phone:' => '',
            // 'Fax:' => '',

            // 'Booking Ref:' => '',
            // 'Issue Date:' => '',
            // 'DEPARTURE:' => '',
            // 'ARRIVAL:' => '',
            // 'Booking Class:' => '',
            // 'Duration:' => '',
            // 'PICK UP:' => '',
            // 'DROP OFF:' => '',
            // 'Car' => '',
            // 'Stops:' => '',
            // 'Meal:' => '',
            // 'Aircraft:' => '',
            // 'Check In' => '',
            // 'Frequent Flyer' => '',
            // 'ETicket No.' => '',
            // 'Status' => '',

            // parseEmail1
            // 'Flight' => '',
            // 'Car Rental' => '',
            // 'Hotel Information' => '',
        ],
    ];

    private $xpath = [
        'airportCode' => 'translate(normalize-space(),"ABCDEFGHIJKLMNOPQRSTUVWXYZ","∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆")="∆∆∆"',
    ];

    private $patterns = [
        'time' => '\b\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignProvider($parser->getHeaders());
        $this->assignLang();

        $this->travellers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Passenger'))}]/ancestor::tr[1]/following-sibling::tr/td[1]"));

        if (count($this->travellers) === 0) {
            $this->travellers = array_filter($this->http->FindNodes("//td[not(.//td)][{$this->eq($this->t('Passenger:'))}][count(following-sibling::td[normalize-space()])=1]/following-sibling::td[normalize-space()][1]"));
        }

        if (count($this->travellers) > 0) {
            $this->travellers = array_map(function ($item) {
                return $this->normalizeTraveller($item);
            }, $this->travellers);
        }

        $this->ticketNumber = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Ticket Number:'))}]", null, true, "/{$this->opt($this->t('Ticket Number:'))}\s*(\d+)$/");

        $this->http->XPath->registerNamespace('php', 'http://php.net/xpath');
        $this->http->XPath->registerPhpFunctions('preg_match');

        $its = $this->http->XPath->query($xpath = "//td[{$this->eq($this->t('its'))}]");

        if ($its->length > 0) {
            $type = '1';
            $this->parseEmail1($email, $its);
        } elseif ($this->http->XPath->query($xpath = "//text()[{$this->eq($this->t('its2'))}]/ancestor::table[2]/preceding::table[normalize-space()][1]")->length > 0) {
            //it-67508813
            $type = '2';
            $its = $this->http->XPath->query($xpath);
            $this->parseEmail2($email, $its);
        } elseif (count($pdfs = $parser->searchAttachmentByName($this->pdfNamePattern)) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
                $this->parseEmailPDF($email, $text);
                $type = '3';
            }
        }

        $this->logger->debug('its 1 = ' . print_r("//td[{$this->eq($this->t('its'))}]", true));
        $this->logger->debug('its 2 = ' . print_r("//text()[{$this->eq($this->t('its2'))}]/ancestor::table[2]/preceding::table[normalize-space()][1]", true));

        $this->logger->debug("Found {$its->length} itineraries");

        $total = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Estimated Total Cost'))}]/following::text()[normalize-space()][1]");

        if (empty($total)) {
            $total = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Total Invoice Amount'))}]/following::text()[normalize-space()][1]");
        }

        if (preg_match('/^(?<amount>[\d\.]+)$/', $total, $matches)
            || preg_match('/^(?<currency>\D+)\s*(?<amount>\d[,.\'\d]*)/', preg_replace('/(\d)\s+(\d)/', '$1,$2', $total), $matches)
            || preg_match('/^(?<amount>\d[,.\'\d]*)\s*(?<currency>\D+)/', preg_replace('/(\d)\s+(\d)/', '$1,$2', $total), $matches)) {
            if (!empty($matches['amount'])) {
                $email->price()->total($this->normalizeAmount($matches['amount']));
            }

            if (!empty($matches['currency']) && isset($matches['currency']) && ($matches['currency'] !== '.')) {
                $email->price()->currency($matches['currency']);
            } else {
                $currency = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Service Fee Amount'))}]/ancestor::tr[1]/descendant::td[2]", null, true, '/([A-Z]{3})$/u');

                if (empty($currency)) {
                    $currency = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Ticket Amount'))}]/ancestor::tr[1]/descendant::td[2]", null, true, '/([A-Z]{3})$/u');
                }

                if (!empty($currency)) {
                    $email->price()
                        ->currency($currency);
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type . ucfirst($this->lang));

        if ($this->providerCode) {
            $email->setProviderCode($this->providerCode);
        }

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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src, '.coretraveltech.com/images/Icons') or contains(@src, 'images.concurcompleat.com')] | //a[contains(@href, '.coretraveltech.com')]")->length > 0) {
            return true;
        }

        foreach ($this->reBody as $values) {
            foreach ($values as $value) {
                if ($this->http->XPath->query("//text()[{$this->contains($value[0])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($value[1])}]")->length > 0
                ) {
                    return true;
                }
            }
        }

        $reProvider = '';
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($text, 'Safe Harbors Business Travel') !== false
                && stripos($text, 'BOOKING INFORMATION') !== false
                && stripos($text, 'Passenger:') !== false
                && stripos($text, 'Issue Date:') !== false
            ) {
                foreach ($this->reProvider as $prov) {
                    if (stripos($text, $prov) !== false) {
                        $reProvider = $prov;
                    }
                }

                return true;
            }
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0
            && empty($reProvider)) {
            return false;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public static function getEmailProviders()
    {
        return ['travelinc', 'amextravel'];
    }

    private function parseEmailPDF(Email $email, string $text): void
    {
        $this->logger->debug(__FUNCTION__);

        $segmentText = $this->re("/^([ ]\w+\s*\d+\s*\w+\s*\d{4}.+)\n{1,}(?:INVOICE DETAILS|BAGGAGE)/msu", $text);
        $segments = $this->splitText($segmentText, "/^([ ]+\S.*\n[ ]+\w+\,.*\d{4})/mu", true);

        $passenger = $this->re("/{$this->opt($this->t('Passenger:'))}\s*([[:alpha:]][-.\/'’[:alpha:] ]*[[:alpha:]])/u", $text);

        if ($passenger) {
            $this->travellers = [$passenger];
        }

        $this->confNumber = $this->re("/{$this->opt($this->t('Booking Ref:'))}\s*([A-Z\d]{6})\b/", $text);
        $this->bookingDate = $this->re("/{$this->opt($this->t('Issue Date:'))}\s*(.+)/", $text);

        if (preg_match("/Totals\s*(?<currency>[A-Z]{3})\:\s*(?<cost>[\d\.\,]+)\s*(?<tax>[\d\.\,]+)\s*[A-Z]{3}\s*(?<total>[\d\.\,]+)/", $text, $m)) {
            $email->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency'])
                ->cost(PriceHelper::parse($m['cost'], $m['currency']))
                ->tax(PriceHelper::parse($m['tax'], $m['currency']));
        }

        foreach ($segments as $segment) {
            if (preg_match("/{$this->opt($this->t('DEPARTURE:'))}/i", $segment)
                && preg_match("/{$this->opt($this->t('ARRIVAL:'))}/i", $segment)
                && preg_match("/{$this->opt($this->t('Booking Class:'))}/i", $segment)
                && preg_match("/{$this->opt($this->t('Duration:'))}/i", $segment)
            ) {
                $this->parseFlightPDF($email, $segment);
            } elseif (preg_match("/{$this->opt($this->t('PICK UP:'))}/i", $segment)
                && preg_match("/{$this->opt($this->t('DROP OFF:'))}/i", $segment)
                && preg_match("/{$this->opt($this->t('Car'))}/i", $segment)
            ) {
                $this->parseCarPDF($email, $segment);
            } else {
                $this->logger->debug('Segment type is not defined!');

                return;
            }
        }
    }

    private function parseFlightPDF(Email $email, string $segment): void
    {
        $this->logger->debug(__FUNCTION__);
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->confNumber)
            ->travellers($this->travellers);

        if (!empty($this->bookingDate)) {
            $f->general()
                ->date(strtotime($this->bookingDate));
        }

        $s = $f->addSegment();

        if (preg_match("/[ ]+.+\s(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d+\b)/", $segment, $m)) {
            $s->airline()
                ->name($m['aName'])
                ->number($m['fNumber']);

            $duration = $this->re("/{$this->opt($this->t('Duration:'))}\s*(\d*.*)\s+{$this->opt($this->t('Stops:'))}/", $segment);

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }

            if (preg_match("/{$this->opt($this->t('Booking Class:'))}\s*(?<bookingCode>[A-Z])\s*\((?<cabin>\w+)\)/", $segment, $m)) {
                $s->extra()
                    ->bookingCode($m['bookingCode'])
                    ->cabin($m['cabin']);
            }

            $meal = $this->re("/[ ]{$this->opt($this->t('Meal:'))}\s*(.+)\s+{$this->opt($this->t('Aircraft:'))}/", $segment);

            if (!empty($meal)) {
                $s->extra()
                    ->meal($meal);
            }

            $aircraft = $this->re("/{$this->opt($this->t('Aircraft:'))}\s*(.+)\s*\n*{$this->opt($this->t('Seat:'))}/", $segment);

            if (!empty($aircraft)) {
                $s->extra()
                    ->aircraft($aircraft);
            }

            $seat = $this->re("/{$this->opt($this->t('Seat:'))}\s*(\d+[A-Z])\s+\w+/", $segment);

            if (!empty($seat)) {
                $s->extra()
                    ->seat($seat);
            }

            $conf = $this->re("/{$this->opt($this->t('Check In'))}\s*([A-Z\d]{6})\s*\n/", $segment);

            if (!empty($conf)) {
                $s->setConfirmation($conf);
            }

            $status = $this->re("/{$this->opt($this->t('Status:'))}\s*(\w+)/", $segment);

            if (!empty($status)) {
                $s->setStatus($status);
            }

            $account = $this->re("/{$this->opt($this->t('Frequent Flyer'))}[ ]*([A-Z\d]*)/", $segment);

            if (!empty($account)) {
                $f->addAccountNumber($account, true);
            }

            $ticket = $this->re("/{$this->opt($this->t('ETicket No.'))}:\s*(\d{5,})/", $segment);

            if (!empty($ticket)) {
                $f->addTicketNumber($ticket, false);
            }

            $date = $this->re("/^[ ]+\S.*\n[ ]+(\w+\,.*\d{4})/", $segment);

            $flightText = $this->re("/([ ]*[A-Z]{3}\s*[A-Z]{3}\s*{$this->opt($this->t('Status'))}.*)\n\n[ ]*{$this->opt($this->t('Booking Class:'))}/msu", $segment);
            $flightTable = $this->splitCols($flightText);

            if (preg_match("/^\s*(?<depCode>[A-Z]{3})\s*(?<depName>.+)\n*(?:\s{$this->opt($this->t('Terminal'))}:\s*{$this->opt($this->t('Terminal'))}(?<depTerminal>.+)\n)\s*{$this->opt($this->t('DEPARTURE:'))}\n\s*(?<depTime>[\d\:]*\s*A?P?M)$/su", $flightTable[0], $m)
                || preg_match("/^\s*(?<depCode>[A-Z]{3})\s*(?<depName>.+)\n*\s*{$this->opt($this->t('DEPARTURE:'))}\n\s*(?<depTime>[\d\:]*\s*A?P?M)$/su", $flightTable[0], $m)
            ) {
                $s->departure()
                    ->name(preg_replace("/\n\s*/", " ", $m['depName']))
                    ->code($m['depCode'])
                    ->date(strtotime($date . ', ' . $m['depTime']));

                if (isset($m['depTerminal']) && !empty($m['depTerminal'])) {
                    $s->departure()
                        ->terminal($m['depTerminal']);
                }
            }

            if (preg_match("/^\s*(?<arrCode>[A-Z]{3})\s*(?<arrName>.+)\n*(?:\s{$this->opt($this->t('Terminal'))}:\s*{$this->opt($this->t('Terminal'))}(?<arrTerminal>.+)\n*)\s*{$this->opt($this->t('ARRIVAL:'))}\n*\s*(?<arrTime>[\d\:]*\s*A?P?M)/su", $flightTable[1], $m)
                || preg_match("/^\s*(?<arrCode>[A-Z]{3})\s*(?<arrName>.+)\n*\s*{$this->opt($this->t('ARRIVAL:'))}\n*\s*(?<arrTime>[\d\:]*\s*A?P?M)/su", $flightTable[1], $m)
            ) {
                $s->arrival()
                    ->name(preg_replace("/\n\s*/", " ", $m['arrName']))
                    ->code($m['arrCode'])
                    ->date(strtotime($date . ', ' . $m['arrTime']));

                if (isset($m['arrTerminal']) && !empty($m['arrTerminal'])) {
                    $s->arrival()
                        ->terminal($m['arrTerminal']);
                }
            }
        }
    }

    private function parseCarPDF(Email $email, string $segment): void
    {
        $this->logger->debug(__FUNCTION__);
        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->confNumber)
            ->travellers($this->travellers);

        if (!empty($this->bookingDate)) {
            $r->general()
                ->date(strtotime($this->bookingDate));
        }

        $company = $this->re("/[ ]+(?<company>.+)\n[ ]*\w+\,\s+.*\d{4}/", $segment);

        if (!empty($company)) {
            $r->setCompany($company);
        }

        $year = $this->re("/[ ]+.+\n[ ]*\w+,\s+.*(\d{4}\b)/", $segment);

        if (preg_match("/{$this->opt($this->t('PICK UP:'))}\s*(?<depDate>.+)\b[ ]+{$this->opt($this->t('Status:'))}\s*(?<status>\w+)\n(?<location>.+\n*.*)\b[ ]+{$this->opt($this->t('Confirmation:'))}\s*(?<conf>[A-Z\d]+)\n{$this->opt($this->t('Phone:'))}\s*(?<phone>[\d\‐]+)/", $segment, $m)) {
            $r->pickup()
                ->date(strtotime($this->normalizeDate($m['depDate'] . ', ' . $year)))
                ->location(str_replace("\n", "", $m['location']))
                ->phone(str_replace('‐', '', $m['phone']));

            $r->setStatus($m['status']);

            $r->general()
                ->confirmation($m['conf']);
        }

        if (preg_match("/{$this->opt($this->t('DROP OFF:'))}\s*(?<arrDate>.+)\b[ ]*\n(?<location>.+\n*.*)\b[ ]*\n{$this->opt($this->t('Phone:'))}\s*(?<phone>[\d\‐]+)/", $segment, $m)) {
            $r->dropoff()
                ->date(strtotime($this->normalizeDate($m['arrDate'] . ', ' . $year)))
                ->location(str_replace("\n", "", $m['location']))
                ->phone(str_replace('‐', '', $m['phone']));
        }
    }

    private function parseEmail1(Email $email, \DOMNodeList $its): void
    {
        $this->logger->debug(__FUNCTION__);

        // Sunday 09 August 2020
        $regex = '/^[[:upper:]][[:alpha:]]{3,} \d+ [[:upper:]][[:alpha:]]{3,} \d{4}$/';
        $date = '';

        foreach ($its as $it) {
            $nodeValue = trim($it->nodeValue);
            $nodes = $this->http->XPath->query("(./ancestor::table[3]/preceding-sibling::table[1]//text()[php:functionString('preg_match', '$regex', .)>0])[1]", $it);

            if ($nodes->length > 0) {
                $date = $nodes->item(0)->nodeValue;
            }

            if (empty($date)) {
                return;
            }

            $roots = $this->http->XPath->query("ancestor::tr[1]/following-sibling::tr[1]", $it);
            $root = $roots->length > 0 ? $roots->item(0) : null;

            if (preg_match("/^{$this->opt($this->t('Flight'))}$/i", $nodeValue)) {
                $this->parseFlight($email, $root, $date);
            } elseif (preg_match("/^{$this->opt($this->t('Car Rental'))}$/i", $nodeValue)) {
                $this->parseRental($email, $root, $date);
            } elseif (preg_match("/^{$this->opt($this->t('Hotel Information'))}$/i", $nodeValue)) {
                $this->parseHotel($email, $root, $date);
            }
        }
    }

    private function parseEmail2(Email $email, \DOMNodeList $its): void
    {
        $this->logger->debug(__FUNCTION__);

        // Sunday 15 May 2022    |    Sunday, 15 May 2022
        $regex = '/^[[:upper:]][[:alpha:]]{3,}[,\s]+\d{1,2}\s+[[:upper:]][[:alpha:]]{2,}\s+\d{4}$/u';
        $date = '';

        foreach ($its as $it) {
            $nodes = $this->http->XPath->query("(./preceding::tr[normalize-space()][1]//text()[php:functionString('preg_match', '$regex', .)>0])[1]", $it);

            if ($nodes->length > 0) {
                $date = $nodes->item(0)->nodeValue;
            }

            if (empty($date)) {
                return;
            }

            $roots = $this->http->XPath->query("ancestor::tr[1]/following::tr[normalize-space()][1]", $it);
            $root = $roots->length > 0 ? $roots->item(0) : null;

            if (!empty($this->http->FindSingleNode("following::table[normalize-space()][1]/descendant::text()[{$this->eq($this->t('DEPARTURE'))}][not(preceding::tr[1][{$this->contains($this->t('Courtesy Shuttle'))}])]", $it))) {
                $this->parseFlight2($email, $root, $date);
            } elseif (!empty($this->http->FindSingleNode("following::table[normalize-space()][1]/descendant::text()[{$this->eq($this->t('CHECK IN DATE'))}]", $it))) {
                $this->parseHotel2($email, $root, $date);
            } elseif (preg_match("/{$this->opt($this->t('Limousine'))}/i", $this->http->FindSingleNode(".", $it))) {
                $this->parseTransfer2($email, $root, $date);
            } elseif (!empty($this->http->FindSingleNode("following::table[normalize-space()][1]/descendant::text()[{$this->eq($this->t('PICK UP'))}]", $it))) {
                $this->parseRental2($email, $root, $date);
            }
        }
    }

    private function parseFlight(Email $email, ?\DOMNode $root, string $date): void
    {
        $this->logger->debug(__FUNCTION__);
        $f = $email->add()->flight();
        $conf = $this->http->FindSingleNode("//text()[{$this->contains($this->t('World Travel Record Locator'))}]",
            null, false, '/\:?\s*([A-Z\d]{5,})$/');

        if (!empty($conf)) {
            $f->ota()->confirmation($conf);
        }

        $conf = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Confirmation:'))}]/following::text()[normalize-space()][1]",
            $root, false, '/^\s*([A-Z\d]{5,})$/');

        if (!empty($conf)) {
            $f->general()->confirmation($conf);
        }

        if (count($this->travellers) > 0) {
            $f->general()
                ->travellers($this->travellers, true);
        }

        $account = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Frequent Traveler ID:'))}]/following::text()[normalize-space()][1]",
            $root, false, '/^\s*([A-Z\d]{5,})$/');

        if (!empty($account)) {
            $f->program()->account($account, false);
        }

        $regex = '/^\s*[A-Z]{3}\s+[A-Z]{3}\s*$/';
        $xpath = ".//table[php:functionString('preg_match', '$regex', .)>0][1]/ancestor::tr[1]";
        $segments = $this->http->XPath->query($xpath, $root);

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $air = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Confirmation:'))}]/ancestor::tr[2]/preceding-sibling::tr[string-length(.) > 3][1]",
                $root);
            // Southwest Airlines 443
            if (preg_match('/^\s*([A-z\d\s]{2,})\s+(\d{1,5})\s*$/', $air, $m)) {
                $s->airline()->name($m[1]);
                $s->airline()->number($m[2]);
            }

            if (preg_match('/^\s*([A-Z]{3})\s+([A-Z]{3})\s*$/', $segment->nodeValue, $m)) {
                $s->departure()->code($m[1]);
                $s->arrival()->code($m[2]);
            }

            $depName = $this->http->FindSingleNode("(./following-sibling::tr[1]//tr[1]/td[1])[1]", $segment, null,
                '/^[[:alpha:]\s.,\-\']{10,}$/');
            $arrName = $this->http->FindSingleNode("(./following-sibling::tr[1]//tr[1]/td[2])[1]", $segment, null,
                '/^[[:alpha:]\s.,\-\']{10,}$/');
            $s->departure()->name($depName);
            $s->arrival()->name($arrName);
            $depTerm = $this->http->FindSingleNode("(./following-sibling::tr[1]//tr[2]/td[1])[1]", $segment, null,
                "/{$this->opt($this->t('Terminal'))}\s+(\w+)/");
            $arrTerm = $this->http->FindSingleNode("(./following-sibling::tr[1]//tr[2]/td[2])[1]", $segment, null,
                "/{$this->opt($this->t('Terminal'))}\s+(\w+)/");

            if ($depTerm) {
                $s->departure()->terminal($depTerm);
            }

            if ($arrTerm) {
                $s->arrival()->terminal($arrTerm);
            }

            $depTime = $this->http->FindSingleNode("(./following-sibling::tr[2][{$this->contains($this->t('DEPARTURE'))}]//tr[1]/td[1])[1]",
                $segment, null,
                '/\s+(\d+:\d+(?:\s*[AP]M)?)\s*/i');
            $arrTime = $this->http->FindSingleNode("(./following-sibling::tr[2][{$this->contains($this->t('ARRIVAL'))}]//tr[1]/td[2])[1]",
                $segment, null,
                '/\s+(\d+:\d+(?:\s*[AP]M)?)\s*/i');
            $s->departure()->date(strtotime("{$date}, {$depTime}", false));
            $s->arrival()->date(strtotime("{$date}, {$arrTime}", false));

            $extra = $this->http->XPath->query("./following-sibling::tr[2][{$this->contains($this->t('DEPARTURE'))}]/following-sibling::tr[string-length(.) > 40]",
                $segment);
            $s->extra()->cabin($this->http->FindSingleNode(".//text()[{$this->contains($this->t('Class:'))}]/following::text()[normalize-space()][1]",
                $extra->item(0)));
            $s->extra()->aircraft($this->http->FindSingleNode(".//text()[{$this->contains($this->t('Equipment:'))}]/following::text()[normalize-space()][1]",
                $extra->item(0)));
            $seats = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Seat:'))}]/following::text()[normalize-space()][1]",
                $extra->item(0));

            if (preg_match_all("/\b\d{2}[A-Z]\b/", $seats, $seatMatches)) {
                if (count($seatMatches[0]) > 0) {
                    $s->extra()->seats($seatMatches[0]);
                }
            }

            $duration = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Estimated Time:'))}]/following::text()[normalize-space()][1]", $extra->item(0));

            if (preg_match("/^(.+?)\s+{$this->opt($this->t('Non-stop'))}$/i", $duration, $m)) {
                // 1 hour(s) and 35 minute(s) Non-stop
                $s->extra()->duration($m[1])->stops(0);
            } else {
                $s->extra()->duration($duration);
            }

            if (empty($s->getAirlineName()) && empty($s->getFlightNumber()) && empty($s->getDepCode()) && empty($s->getArrCode())) {
                $f->removeSegment($s);
                $email->removeItinerary($f);
            }
        }
    }

    private function parseFlight2(Email $email, ?\DOMNode $root, string $date): void
    {
        $this->logger->debug(__FUNCTION__);

        foreach ($email->getItineraries() as $i => $it) {
            if ($it->getType() === 'flight') {
                $f = $it;

                break;
            }
        }

        if (!isset($f)) {
            $f = $email->add()->flight();

            $f->general()
                ->noConfirmation();

            $conf = $this->http->FindSingleNode("//text()[{$this->contains($this->t('World Travel Record Locator'))}]/ancestor::tr[1]",
                null, false, '/\:?\s*([A-Z\d]{5,})$/');

            if (!empty($conf)) {
                $f->ota()->confirmation($conf);
            }

            if (count($this->travellers) > 0) {
                $f->general()
                    ->travellers($this->travellers, true);
            }

            $account = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Frequent Traveler ID:'))}]/following::text()[normalize-space()][1]",
                $root, false, '/^\s*([A-Z\d]{5,})$/');

            if (!empty($account) && !in_array($account, array_column($f->getAccountNumbers(), 0))) {
                $f->program()
                    ->account($account, false);
            }
        }

        $s = $f->addSegment();

        $air = $this->http->FindSingleNode("preceding::tr[normalize-space()][1]/descendant::text()[normalize-space()][1]", $root);

        if (preg_match('/^\s*([A-z\d\s]{2,})\s+(\d+)/', $air, $m)) {
            // Southwest Airlines 443
            $s->airline()->name($m[1])->number($m[2]);
        } elseif (preg_match('/^\s*[A-z\d\s]{2,}\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])(\d+)/', $air, $m)) {
            $s->airline()->name($m[1])->number($m[2]);
        }

        if (preg_match('/^\s*([A-Z]{3})\s+([A-Z]{3})\s*$/', $this->http->FindSingleNode("descendant::img[ preceding::text()[normalize-space()][1][{$this->xpath['airportCode']}] ][1]/ancestor::table[1]/ancestor::tr[1]", $root), $m)) {
            $s->departure()->code($m[1]);
            $s->arrival()->code($m[2]);
        }

        $conf = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Confirmation:'))}]/following::text()[normalize-space()][1]",
            $root, false, '/^\s*([A-Z\d]{5,})$/');

        if (!empty($conf)) {
            $s->airline()->confirmation($conf);
        }

        $dateDep = strtotime($date);
        $timeDep = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('DEPARTURE'))}]/ancestor::tr[1]/following::tr[1]/*[1]/descendant::text()[normalize-space()][last()]", $root, true, "/{$this->patterns['time']}/");
        $timeArr = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('DEPARTURE'))}]/ancestor::tr[1]/following::tr[1]/*[2]/descendant::text()[normalize-space()][last()]", $root, true, "/{$this->patterns['time']}/");
        $s->departure()->date(strtotime($timeDep, $dateDep));
        $s->arrival()->date(strtotime($timeArr, $dateDep));

        $depTerm = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Terminal')]/ancestor::tr[1]/descendant::td[1]/descendant::text()[normalize-space()][last()]", $root, null,
            "/{$this->opt($this->t('Terminal'))}\s+([\w ]+?)(?:\s*Arr ?- ?[\w ]+)?\s*$/");
        $arrTerm = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Terminal')]/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()][last()]", $root, null,
            "/{$this->opt($this->t('Terminal'))}\s+([\w ]+)\s*$/");

        if ($depTerm) {
            $s->departure()->terminal($depTerm);
        }

        if ($arrTerm) {
            $s->arrival()->terminal($arrTerm);
        }

        $cabin = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Class:'))}]/following::text()[normalize-space()][1]", $root);

        if (preg_match("/^\s*([A-Z]{1,2})\s*\(([\w ]{2,})\)\s*$/", $cabin, $m)) {
            $s->extra()
                ->cabin(trim($m[2]))
                ->bookingCode($m[1])
            ;
        } elseif (!empty($cabin)) {
            $s->extra()
                ->cabin($cabin);
        }

        $aircraft = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Equipment:'))}]/following::text()[normalize-space()][1]", $root);

        if (!empty($aircraft)) {
            $s->extra()
                ->aircraft($aircraft);
        }

        $seats = $this->http->FindNodes(".//text()[{$this->starts($this->t('Seat:'))}]/ancestor::*[self::p or self::div or self::td][1][{$this->starts($this->t('Seat:'))}]//text()[normalize-space()]",
            $root);

        if (preg_match_all("/\b\d{2}[A-Z]\b/", implode("\n", $seats), $seatMatches)) {
            if (count($seatMatches[0]) > 0) {
                $s->extra()->seats($seatMatches[0]);
            }
        }

        $duration = trim($this->http->FindSingleNode(".//text()[{$this->contains($this->t('Estimated Time:'))}]/following::text()[normalize-space()][1]", $root));

        if (preg_match("/^(.+?)\s+{$this->opt($this->t('Non-stop'))}$/i", $duration, $m)) {
            // 1 hour(s) and 35 minute(s) Non-stop
            $s->extra()->duration($m[1])->stops(0);
        } elseif (!empty($duration)) {
            $s->extra()->duration($duration);
        }

        if (!empty($this->ticketNumber) && !in_array($this->ticketNumber, array_column($f->getTicketNumbers(), 0))) {
            $f->issued()
                ->ticket($this->ticketNumber, false);
        }

        $status = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Status:'))}]/ancestor::tr[1]/descendant::td[2]", $root);

        if (!empty($status)) {
            $f->general()
                ->status($status);
        }

        if ($status == 'Cancelled') {
            $f->general()
                ->cancelled();
        }

        if (empty($s->getAirlineName()) && empty($s->getFlightNumber()) && empty($s->getDepCode()) && empty($s->getArrCode())) {
            // $f->removeSegment($s);
            // $email->removeItinerary($f);
        }
    }

    private function parseRental(Email $email, ?\DOMNode $root, string $baseDate): void
    {
        $this->logger->debug(__FUNCTION__);
        $r = $email->add()->rental();
        $conf = $this->http->FindSingleNode("//text()[{$this->contains($this->t('World Travel Record Locator'))}]",
            null, false, '/\:?\s*([A-Z\d]{5,})(?: [A-Z\d]*)?$/');
        $r->ota()->confirmation($conf);

        $conf = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Confirmation:'))}]/following::text()[normalize-space()][1]",
            $root, false, '/^\s*([A-Z\d]{5,})$/');
        $r->general()->confirmation($conf);

        if (count($this->travellers) > 0) {
            $r->general()
                ->travellers($this->travellers, true);
        }

        $account = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Frequent Traveler ID:'))}]/following::text()[normalize-space()][1]",
            $root, false, '/^\s*([A-Z\d]{5,})$/');
        $r->program()->account($account, false);

        // pickup
        $date = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('PICK UP'))}]/ancestor::tr[1]/following-sibling::tr[string-length(.) > 3][1]", $root);
        $address = join("\n", $this->http->FindNodes(".//text()[{$this->contains($this->t('PICK UP'))}]/ancestor::tr[1]/following-sibling::tr[string-length(.) > 3][2]//text()", $root));
        $r->pickup()->date(strtotime($this->normalizeDate($date), strtotime($baseDate)));
        /*
         1 Jeff Fuqua Boulevard
         Orlando, Florida 32827
        United States
        +1 (407) 825-1800
         */
        if (preg_match('/^(.+?)\s*\n+\s*(\+[\d()\s\-]{5,})/s', $address, $m)) {
            $r->pickup()->location(preg_replace("/\n+/", ', ', trim($m[1])));
            $r->pickup()->phone($m[2]);
        }

        // dropoff
        $date = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('DROP OFF'))}]/ancestor::tr[1]/following-sibling::tr[string-length(.) > 3][1]", $root);
        $address = join("\n", array_filter($this->http->FindNodes(".//text()[{$this->contains($this->t('DROP OFF'))}]/ancestor::tr[1]/following-sibling::tr[string-length(.) > 3][2]//text()", $root)));

        $r->dropoff()->date(strtotime($this->normalizeDate($date), strtotime($baseDate)));

        if (preg_match('/^(.+?)\s*\n+\s*([+\d()\s\-]{5,})/s', $address, $m)) {
            $r->dropoff()->location(preg_replace("/\n+/", ', ', trim($m[1])));
            $r->dropoff()->phone($m[2]);
        } elseif (empty($address)) {
            $r->dropoff()->noLocation();
        }

        $r->car()->type($this->http->FindSingleNode(".//text()[{$this->contains($this->t('Type:'))}]/following::text()[normalize-space()][1]", $root));

        $total = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Total:'))}]/following::text()[normalize-space()][1]");

        if (preg_match('/^(?<currency>\D+)\s*(?<amount>\d[,.\'\d]*)/', preg_replace('/(\d)\s+(\d)/', '$1,$2', $total), $matches)) {
            $r->price()->currency($matches['currency']);
            $r->price()->total($this->normalizeAmount($matches['amount']));
        }
    }

    private function parseRental2(Email $email, ?\DOMNode $root, string $baseDate): void
    {
        $this->logger->debug(__FUNCTION__);
        $r = $email->add()->rental();

        $conf = $this->http->FindSingleNode("//text()[{$this->contains($this->t('World Travel Record Locator'))}]",
            null, false, '/\:?\s*([A-Z\d]{5,})$/');
        $r->ota()->confirmation($conf);

        $conf = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Confirmation:'))}]/following::text()[normalize-space()][1]", $root, false, '/^[A-Z\d\*]{5,35}(?: [A-Z]+)?$/');
        $r->general()->confirmation(str_replace(['*', ' '], '', $conf));

        if (count($this->travellers) > 0) {
            $r->general()
                ->travellers($this->travellers, true);
        }

        $account = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Frequent Traveler ID:'))}]/following::text()[normalize-space()][1]", $root, false, '/^[A-Z\d]{5,}$/');

        if ($account) {
            $r->program()->account($account, false);
        }

        $date = strtotime($baseDate);

        // pickup
        $datePickup = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('PICK UP'))}]/ancestor::tr[1]/following-sibling::tr[string-length()>3][1]/*[1]", $root, false);
        $address = implode(', ', $this->http->FindNodes("descendant::img[contains(@src,'directions_2')][1]/following::a[1]//text()[normalize-space()]", $root));

        if (empty($address)) {
            $address = implode(', ', $this->http->FindNodes("following::img[contains(@src,'directions_2')][1]/following::a[1]//text()[normalize-space()]", $root));
        }

        $phone = $this->http->FindSingleNode("descendant::img[contains(@src,'phone')][1]/following::a[1]", $root)
            ?? $this->http->FindSingleNode("following::img[contains(@src,'phone')][1]/following::a[1]", $root);

        $r->pickup()->date(strtotime($this->normalizeDate($datePickup), $date))->location($address)->phone($phone);

        // dropoff
        $dateDropoff = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('PICK UP'))}]/ancestor::tr[1]/following-sibling::tr[string-length()>3][1]/*[2]", $root, false);
        $r->dropoff()->date(strtotime($this->normalizeDate($dateDropoff), $date))->noLocation();

        $carType = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Type:'))}]/following::text()[normalize-space()][1]", $root);
        $r->car()->type($carType, false, true);

        $total = $this->http->FindSingleNode("./ancestor::table[1]/descendant::text()[contains(normalize-space(), 'Total')]/following::text()[normalize-space()][1]", $root);

        if (preg_match('/^(?<currency>\D+)\s*(?<amount>\d[,.\'\d]*)/', preg_replace('/(\d)\s+(\d)/', '$1,$2', $total), $matches)) {
            $r->price()->currency($matches['currency']);
            $r->price()->total($this->normalizeAmount($matches['amount']));
        }
    }

    private function parseTransfer2(Email $email, ?\DOMNode $root, string $baseDate): void
    {
        $this->logger->debug(__FUNCTION__);
        $t = $email->add()->transfer();
        $conf = $this->http->FindSingleNode("//text()[{$this->contains($this->t('World Travel Record Locator'))}]",
            null, false, '/\:?\s*([A-Z\d]{5,})$/');
        $t->ota()->confirmation($conf);

        $conf = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Confirmation:'))}]/following::text()[normalize-space()][1]",
            $root, false, '/^\s*([A-Z\d\-]{5,})(?:\*|$)/');
        $t->general()->confirmation($conf);

        if (count($this->travellers) > 0) {
            $t->general()
                ->travellers($this->travellers, true);
        }

        $account = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Frequent Traveler ID:'))}]/following::text()[normalize-space()][1]",
            $root, false, '/^\s*([A-Z\d]{5,})$/');

        if (!empty($account)) {
            $t->program()->account($account, false);
        }

        // pickup
        $date = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('PICK UP'))}]/ancestor::tr[1]/following-sibling::tr[string-length(.) > 3][1]/descendant::td[1]", $root);
        $address = preg_replace("/(AIRPORT)\s*ARR\s*[A-Z\d]{2}\d{2,4}/", "$1", $this->http->FindSingleNode("./descendant::text()[normalize-space()][1]", $root));

        $s = $t->addSegment();

        $s->departure()
            ->address($address)
            ->date(strtotime($this->normalizeDate($date), strtotime($baseDate)));

        // dropoff
        $s->arrival()
            ->noDate()
            ->address(preg_replace("/(AIRPORT)\s*ARR\s*[A-Z\d]{2}\d{2,4}/", "$1", $this->http->FindSingleNode("./descendant::text()[normalize-space()][2]", $root)));

        $total = $this->http->FindSingleNode("./ancestor::table[1]/descendant::text()[contains(normalize-space(), 'Total')]/following::text()[normalize-space()][1]", $root);

        if (preg_match('/^(?<currency>\D+)\s*(?<amount>\d[,.\'\d]*)/', preg_replace('/(\d)\s+(\d)/', '$1,$2', $total), $matches)) {
            $t->price()->currency($matches['currency']);
            $t->price()->total($this->normalizeAmount($matches['amount']));
        }
    }

    private function parseHotel(Email $email, ?\DOMNode $root, string $baseDate): void
    {
        $this->logger->debug(__FUNCTION__);
        $h = $email->add()->hotel();

        $conf = $this->http->FindSingleNode("//text()[{$this->contains($this->t('World Travel Record Locator'))}]",
            null, false, '/\:?\s*([A-Z\d]{5,})$/');
        $h->ota()->confirmation($conf);

        $conf = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Confirmation:'))}]/following::text()[normalize-space()][1]",
            $root, false, '/^\s*([A-Z\d]{5,})$/');
        $h->general()->confirmation($conf);

        $cancellation = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Cancellation Policy:'))}]/following::text()[normalize-space()][1]", $root);

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation);
        }

        if (count($this->travellers) > 0) {
            $h->general()
                ->travellers($this->travellers, true);
        }

        $account = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Frequent Traveler ID:'))}]/following::text()[normalize-space()][1]",
            $root, false, '/^\s*([A-Z\d]{5,})$/');

        if ($account) {
            $h->program()->account($account, false);
        }

        $name = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Confirmation:'))}]/ancestor::tr[2]/preceding-sibling::tr[string-length(.) > 3][1]", $root);
        $h->hotel()->name($name);

        // checkIn
        $date = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Check In Date'))}]/ancestor::tr[1]/following-sibling::tr[string-length(.) > 3][1]", $root);
        $address = join("\n", $this->http->FindNodes(".//text()[{$this->contains($this->t('Check In Date'))}]/ancestor::tr[1]/following-sibling::tr[string-length(.) > 3][2]//text()", $root));

        $h->booked()->checkIn(strtotime($date));
        /*
        400 West Livingston St
        Orlando, FL, 32801, US
        +1 (407) 868-8686
         */
        if (preg_match('/^(.+?)\s*\n+\s*([+\d][\d()\s\-]{5,})/s', $address, $m)) {
            $h->hotel()->address(preg_replace("/\n+/", ', ', trim($m[1])));
            $h->hotel()->phone($m[2]);
        }

        // checkOut
        $date = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Check Out Date'))}]/ancestor::tr[1]/following-sibling::tr[string-length(.) > 3][1]", $root);
        $h->booked()->checkOut(strtotime($date));

        $h->booked()->guests($this->http->FindSingleNode(".//text()[{$this->contains($this->t('Persons:'))}]/following::text()[normalize-space()][1]", $root));
        $r = $h->addRoom();

        $roomType = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Rooms / Type:'))}]/following::text()[normalize-space()][1]", $root);

        if (!empty($roomType)) {
            $r->setType($roomType);
        }

        $r->setRate($this->http->FindSingleNode(".//text()[{$this->contains($this->t('Rate:'))}]/following::text()[normalize-space()][1]", $root));

        $total = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Total:'))}]/following::text()[normalize-space()][1]");

        if (preg_match('/^(?<currency>\D+)\s*(?<amount>\d[,.\'\d]*)/', preg_replace('/(\d)\s+(\d)/', '$1,$2', $total), $matches)) {
            $h->price()->currency($matches['currency']);
            $h->price()->total($this->normalizeAmount($matches['amount']));
        }
    }

    private function parseHotel2(Email $email, ?\DOMNode $root, string $baseDate): void
    {
        $this->logger->debug(__FUNCTION__);
        $h = $email->add()->hotel();

        $conf = $this->http->FindSingleNode("//text()[{$this->contains($this->t('World Travel Record Locator'))}]/ancestor::tr[1]",
            null, false, '/\:?\s*([A-Z\d]{5,})$/');
        $h->ota()->confirmation($conf);

        $conf = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Confirmation:'))}]/following::text()[normalize-space()][1]",
            $root, false, '/^\s*([A-Z\d]{5,})$/');
        $h->general()->confirmation($conf);

        $cancellation = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Cancellation Policy:'))}]/following::text()[normalize-space()][1]", $root);

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation);
        }

        if (count($this->travellers) > 0) {
            $h->general()
                ->travellers($this->travellers, true);
        }

        $account = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Frequent Traveler ID:'))}]/following::text()[normalize-space()][1]",
            $root, false, '/^\s*([A-Z\d]{5,})$/');

        if ($account) {
            $h->program()->account($account, false);
        }

        $name = $this->http->FindSingleNode("preceding::table[1]/descendant::text()[normalize-space()][1]", $root, true, "/^(.{2,75}?)(?:\s*[[:upper:]]{3}\s*,.+)?$/u");
        $h->hotel()->name($name);

        $address = implode("\n", $this->http->FindNodes("./descendant::text()[{$this->eq($this->t('ADDRESS:'))}]/ancestor::*[not({$this->eq($this->t('ADDRESS:'))})][{$this->starts($this->t('ADDRESS:'))}][1]/descendant::text()[normalize-space()]", $root));

        if (!empty($address)) {
            $h->hotel()
                ->address(preg_replace("/\s*\n\s*/", ', ',
                    preg_replace("/^\s*{$this->opt($this->t('ADDRESS:'))}\s*([\s\S]+?)\s+(?:{$this->opt($this->t('Phone:'))}[\s\S]*|{$this->opt($this->t('Fax:'))}[\s\S]*)?\s*$/", '$1', $address)));

            if (preg_match("/{$this->opt($this->t('Phone:'))}\s*([\-\(\) \+]*\d[\-\(\) \+\d]{5,})\s*(?:\n|$)/", $address, $m)) {
                $h->hotel()->phone($m[1]);
            }

            if (preg_match("/{$this->opt($this->t('Fax:'))}\s*([\-\(\) \+]*\d[\-\(\) \+\d]{5,})\s*(?:\n|$)/", $address, $m)) {
                $h->hotel()->fax($m[1]);
            }
        } else {
            $address = implode(", ",
                $this->http->FindNodes("./descendant::text()[{$this->eq($this->t('CHECK IN DATE'))}]/ancestor::tr[1]/following::tr[2]/descendant::text()[normalize-space()]", $root));
            $h->hotel()->address($address);
            $phone = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Status:'))}]/ancestor::tr[1]/preceding::tr[1]/ancestor::tr[1]/descendant::td[1]", $root, true, "/^([\+\d\s\-\(\)]+)$/");
            $h->hotel()->phone($phone);
        }

        // checkIn
        $date = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('CHECK IN DATE'))}]/ancestor::tr[1][count(*[normalize-space()]) = 2][.//text()[{$this->eq($this->t('CHECK OUT DATE'))}]]/following::tr[1]/descendant::td[1]", $root);

        if (empty($date)) {
            $date = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('CHECK IN DATE'))}]/following::text()[normalize-space()][1][not({$this->starts($this->t('CHECK OUT DATE'))})]", $root);
        }

        if (!preg_match("/\d{4}/", $date)) {
            // Wednesday, Jan. 17
            $dateT = EmailDateHelper::parseDateRelative($date, strtotime($baseDate));
            $h->booked()->checkIn($dateT);
        } else {
            $h->booked()->checkIn(strtotime($date));
        }

        // checkOut
        $date = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('CHECK OUT DATE'))}]/ancestor::tr[1][count(*[normalize-space()]) = 2][.//text()[{$this->eq($this->t('CHECK IN DATE'))}]]/following::tr[1]/descendant::td[2]", $root);

        if (empty($date)) {
            $date = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('CHECK OUT DATE'))}][preceding::text()[normalize-space()][1][not({$this->starts($this->t('CHECK IN DATE'))})]]/following::text()[normalize-space()][1]", $root);
        }

        if (!preg_match("/\d{4}/", $date)) {
            // Wednesday, Jan. 17
            $dateT = EmailDateHelper::parseDateRelative($date, strtotime($baseDate));
            $h->booked()->checkOut($dateT);
        } else {
            $h->booked()->checkOut(strtotime($date));
        }

        $h->booked()
            ->guests($this->http->FindSingleNode(".//text()[{$this->contains($this->t('Persons:'))}]/following::text()[normalize-space()][1]", $root), true, true)
            ->rooms($this->http->FindSingleNode(".//text()[{$this->contains($this->t('No. Of Rooms:'))}]/following::text()[normalize-space()][1]", $root), true, true)
        ;
        $r = $h->addRoom();
        $roomType = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Rooms / Type:'))}]/following::text()[normalize-space()][1]", $root);

        if (!empty($roomType)) {
            $r->setType($roomType);
        }
        $r->setRate($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Rate:'))}]/ancestor::*[not({$this->eq($this->t('Rate:'))})][1][descendant::text()[normalize-space()][1][{$this->eq($this->t('Rate:'))}]]", $root, true, "/^\s*{$this->opt($this->t('Rate:'))}\s*(.+)/"));

        $status = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Status:'))}]/ancestor::tr[1]/descendant::td[2]", $root);

        if (!empty($status)) {
            $h->general()
                ->status($status);
        }

        if ($status == 'Cancelled') {
            $h->general()
                ->cancelled();
        }

        $this->detectDeadLine($h);
    }

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        return preg_replace([
            '/^(.{2,}?)\s+(?:MSTR|MISS|MRS|MR|MS|DR)[.\s]*$/i',
            '/^([^\/]+?)(?:\s*[\/]+\s*)+([^\/]+)$/',
        ], [
            '$1',
            '$2 $1',
        ], $s);
    }

    private function normalizeDate($str): string
    {
        $in = [
            // 8:00 PM SUN, AUG 9
            '#^(\d+:\d+\s*[AP]M) [A-Z]+, ([A-Z]+ \d+)$#',
            //11:18 AM, Saturday, Mar. 9, 2024
            '#^([\d\:]+\s*A?P?M)\,\s*\w+\,\s*(\w+)\.\s*(\d+)\,\s*(\d{4})$#',
        ];
        $out = [
            "$2, $1",
            "$3 $2 $4, $1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;

        foreach ($head as $word) {
            $currentPosition = mb_strpos($row, $word, $lastpos, 'UTF-8');

            if ($currentPosition > 0) {
                $currentPosition -= 2;
            }
            $pos[] = $currentPosition;
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function assignProvider($headers): bool
    {
        if (preg_match('/[@.]americanexpress\.com$/i', rtrim($headers['from'], '> '))
            || strpos($headers['subject'], 'American Express') !== false
            || $this->http->XPath->query("//text()[{$this->starts(['American Express Record Locator', 'Localizador de registros de American Express'])}]")->length > 0
        ) {
            $this->providerCode = 'amextravel';

            return true;
        }

        return false;
    }

    private function assignLang(): bool
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (
                !empty($dict['its']) && $this->http->XPath->query("//text()[{$this->eq($dict['its'])}]")->length > 0
                || !empty($dict['its2']) && $this->http->XPath->query("//text()[{$this->eq($dict['its2'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

        return $s;
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
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

    private function opt($field): string
    {
        $field = (array) $this->t($field);

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
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
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }

    private function detectDeadLine(Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }
        // you can cancel or modify your booking free of charge by 3PM, 24 hours prior to your arrival
        if (preg_match('/^\s*(?<hours>\d+) HOURS PRIOR OR 1NIGHT FEE CREDIT CARD REQ/',
            $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m['hours'] . ' hours');
        }
    }

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }
}
