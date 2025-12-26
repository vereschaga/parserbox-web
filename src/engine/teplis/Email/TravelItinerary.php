<?php

namespace AwardWallet\Engine\teplis\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TravelItinerary extends \TAccountChecker
{
    public $mailFiles = "teplis/it-45417367.eml, teplis/it-45417385.eml, teplis/it-45417400.eml, teplis/it-45417402.eml, teplis/it-45476933.eml";

    public $reFrom = ["teplis.com"];
    public $reSubject = [
        'Travel Itinerary/Invoice for',
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'PASSENGER INFORMATION' => 'PASSENGER INFORMATION',
            'First Name'            => 'First Name',
            'AIR TICKETS'           => ['AIR TICKETS', 'AIR TICKET'],
            'Seat Number'           => ['Seat Number', 'Seat'],
            'fees'                  => ['TAXES AND CARRIER IMPOSED FEES:'],
            'Agency Booking Ref #'  => ['Agency Booking Ref #', 'Agency Ref #'],
            'Estimated Rental Cost' => ['Estimated Rental Cost', 'Est. Rental Cost'],
            'DEPART TERMINAL'       => ['DEPART TERMINAL', 'DEPARTURE TERMINAL'],
            'ARRIVE TERMINAL'       => ['ARRIVE TERMINAL', 'ARRIVAL TERMINAL'],
        ],
    ];
    private $dateRes;
    private $pax;
    private $agentRef;
    private $invoice;
    private $defaultCurrency = 'USD'; // TODO: for teplis only!!!
    private $generalInfo;
    private $paymentInfo;
    private $rentalKeywords = [
        'national' => [
            'NATIONAL',
        ],
        'avis' => [
            'AVIS RENT A CAR',
        ],
        'rentacar' => [
            'ENTERPRISE',
        ],
        'alamo' => [
            'ALAMO',
        ],
    ];
    private $providerCode = '';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $type = '';

        if ($this->assignLang($this->http->Response['body'])) {
            $this->assignProvider($parser->getHeaders());
            $this->parseEmail($email);
            $type = 'Html';
        }

        if (count($email->getItineraries()) === 0) {
            $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

            if (isset($pdfs) && count($pdfs) > 0) {
                foreach ($pdfs as $pdf) {
                    if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null
                        && $this->assignLang($text)
                    ) {
                        $this->assignProvider($parser->getHeaders(), $text);
                        $this->parseEmailPdf($text, $email);
                        $type = 'Pdf';
                    }
                }
            }
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($type) . ucfirst($this->lang));
        $email->setProviderCode($this->providerCode);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->assignProvider($parser->getHeaders())
            && $this->assignLang($this->http->Response['body'])
        ) {
            return true;
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($text)) {
                continue;
            }

            if ($this->assignProvider($parser->getHeaders(), $text)
                && $this->assignLang($text)
            ) {
                return true;
            }
        }

        return false;
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
                if (($fromProv || preg_match("#\b{$this->opt(['TEPLIS'])}\b#i", $headers["subject"]) > 0)
                    && stripos($headers["subject"], $reSubject) !== false
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
        $types = 3; //flight | hotel |rental;
        $formats = 2; //html | pdf
        $cnt = $types * $formats * count(self::$dict);

        return $cnt;
    }

    public static function getEmailProviders()
    {
        return ['teplis', 'tleaders'];
    }

    private function parseEmailPdf($textPDF, Email $email)
    {
        $this->logger->notice(__METHOD__);

        $mainInfo = $this->strstrArr($textPDF, $this->t('INVOICE INFORMATION'), true);

        if (empty($mainInfo)) {
            $this->logger->debug('other format pdf');

            return false;
        }
        $paxInfo = $this->re("#{$this->t('PASSENGER INFORMATION')}[ ]*\n(.+?{$this->t('Last Name')}.+?)\n\n#s",
            $mainInfo);
        $pos[] = 0;
        $pos[] = strpos($paxInfo, $this->t('Company Number'));
        $paxInfo = $this->splitCols($paxInfo, $pos);

        if (preg_match_all('/First\s+Name\s+[:]\s+(\S+)/is', $paxInfo[0], $nodesFirstName, PREG_SET_ORDER)) {
            foreach ($nodesFirstName as $nodeFirstName) {
                $firstName[] = $nodeFirstName[1];
            }
        }

        if (preg_match_all('/Last\s+Name\s+[:]\s+(\S+)/is', $paxInfo[1], $nodesLastName, PREG_SET_ORDER)) {
            foreach ($nodesLastName as $nodeLastName) {
                $lastName[] = $nodeLastName[1];
            }
        }

        if (count($firstName) === count($lastName)) {
            for ($i = 0; $i <= count($firstName); $i++) {
                if (!empty($firstName[$i]) && !empty($lastName[$i])) {
                    $this->pax[] = $firstName[$i] . ' ' . $lastName[$i];
                }
            }
        }

        $this->agentRef = $this->re("#{$this->opt($this->t('Agency Booking Ref #'))}[: ]+(.+)#", $paxInfo[1]);
        $this->dateRes = $this->normalizeDate($this->re("#{$this->opt($this->t('Date Issued'))}[: ]+(.+)#",
            $paxInfo[0]));
        $this->invoice = $this->re("#{$this->opt($this->t('Invoice Number'))}[: ]+(.+)#", $paxInfo[1]);

        $paymentInfo = $this->strstrArr($textPDF, $this->t('INVOICE INFORMATION'));

        if (!empty($str = $this->strstrArr($paymentInfo, $this->t('PAYMENT INFORMATION'), true))) {
            $paymentInfo = $str;
        }

        $generalInfo = $this->strstrArr($textPDF, $this->t('GENERAL INFORMATION'));

        if (isset($generalInfo) && !empty($str = $this->strstrArr($generalInfo, $this->t('REMARKS'), true))) {
            $generalInfo = $str;
        }
        $this->generalInfo = $generalInfo;
        $this->paymentInfo = $paymentInfo;

        $regExp = "[ ]*(?:{$this->t('FLIGHT')}.+?, \d{4}|{$this->t('HOTEL')}\s+{$this->t('Check In')}|{$this->t('CAR')}\s+{$this->t('Pick Up Date')}|{$this->t('MISCELLANEOUS')})";
        $nodes = $this->splitter("#\n({$regExp})#", $mainInfo);

        $flights = $hotels = $cars = [];

        foreach ($nodes as $root) {
            if (preg_match("#^\s*{$this->t('MISCELLANEOUS')}#", $root)) {
                continue;
            } elseif (preg_match("#^\s*{$this->t('FLIGHT')}#", $root)) {
                $flights[] = $root;
            } elseif (preg_match("#^\s*{$this->t('HOTEL')}#", $root)) {
                $hotels[] = $root;
            } elseif (preg_match("#^\s*{$this->t('CAR')}#", $root)) {
                $cars[] = $root;
            }
        }

        if (!empty($flights)) {
            $this->parseFlightsPdf($email, $flights);
        }

        if (!empty($hotels)) {
            $this->parseHotelsPdf($email, $hotels);
        }

        if (!empty($cars)) {
            $this->parseCarsPdf($email, $cars);
        }

        $node = $this->re("#\n[ ]*{$this->opt($this->t('TOTAL:'))}[ :]+(.+)#", $this->paymentInfo);

        if (!empty($node)) {
            $email->price()
                ->total(PriceHelper::cost($node))
                ->currency($this->defaultCurrency);
        }
        $node = $this->re("#\n[ ]*{$this->opt($this->t('SERVICE FEE:'))}[ :]+(.+)#", $this->paymentInfo);

        if (!empty($node)) {
            $email->price()
                ->fee(trim($this->t('SERVICE FEE:'), ':'), $node);
        }

        return true;
    }

    private function parseFlightsPdf(Email $email, array $roots)
    {
        $this->logger->notice(__METHOD__);

        $r = $email->add()->flight();
        $r->general()
            ->noConfirmation()
            ->date($this->dateRes)
            ->travellers($this->pax);
        $r->ota()
            ->confirmation($this->agentRef, ((array) $this->t('Agency Booking Ref #'))[0], true)
//            ->confirmation($this->invoice, $this->t('Invoice Number'))
        ;

        $airlines = [];

        foreach ($roots as $root) {
            $pos = [];
            $s = $r->addSegment();
            $date = $this->normalizeDate($this->re("#{$this->t('FLIGHT')}\s+(.+? \d{4})#", $root));

            $segmentInfo = $this->re("#\n([ ]*{$this->t('Air Vendor')}.+?{$this->opt($this->t('Seat Number'))}[ ]+:[^\n]+)(?:\n|$)#s",
                $root);

            if (empty($segmentInfo)) {
                $segmentInfo = $this->re("#\n([ ]*{$this->t('Air Vendor')}.+?{$this->opt($this->t('Ticket Confirmation'))}[^\n]+)(?:\n|$)#s",
                    $root);
            }
            $pos[] = 0;
            $pos1 = strpos($segmentInfo, $this->t('Flight Number'));
            $pos2 = mb_strlen($this->re("#\n(.+?){$this->t('Departs')}#", $segmentInfo));
            $pos[] = min([$pos1, $pos2]);
            $segmentInfo = $this->splitCols($segmentInfo, $pos);
            // airline
            $s->airline()
                ->name($this->re("#{$this->t('Air Vendor')}[ :]+.+?\(\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\)#",
                    $segmentInfo[0]))
                ->confirmation($this->re("#{$this->t('Ticket Confirmation')}[ :]+([A-Z\d]{5,6})#", $segmentInfo[1]))
                ->number($this->re("#{$this->t('Flight Number')}[ :]+(\d+)#", $segmentInfo[1]));
            $operator = $this->nice($this->re("#{$this->t('Operated By')}[ :]+(.+?)(?: DBA|{$this->opt($this->t('Passenger Name'))}|{$this->opt($this->t('Seat Number'))}|$)#s",
                $segmentInfo[0]));

            if (!empty($operator)) {
                $s->airline()->operator($operator);
            }
            $airlines[] = $s->getAirlineName();

            $notes = $this->nice($this->re("#{$this->opt($this->t('Seat Number'))}[ ]+:[^\n]+\n(.+)#s", $root));
            // departure
            $node = $this->nice($this->re("#{$this->t('From')}[ :]+(.+?)\s+{$this->t('To')}#s", $segmentInfo[0]));

            if (preg_match("#(.+)\s*\(\s*([A-Z]{3})\s*\)#", $node, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->code($m[2]);
            }

            if (preg_match("#{$this->opt($this->t('DEPART TERMINAL'))} ([^\|]+)#", $notes, $m)) {
                $s->departure()->terminal($m[1]);
            }
            $node = $this->re("#{$this->t('Departs')}[ :]+(.+)#", $segmentInfo[1]);

            if (preg_match("#^(\d+:\d+\s*(?:[ap]m)?)[\s\-]*(?:TERMINAL[ ]+(.+))?$#i", $node, $m)) {
                $s->departure()->date(strtotime($m[1], $date));

                if (isset($m[2])) {
                    $s->departure()->terminal($m[2]);
                }
            } else {
                $s->departure()->date(strtotime($node, $date));
            }

            // arrival
            $node = $this->re("#{$this->t('To')}[ :]+(.+?)\s*{$this->t('Aircraft')}#s", $segmentInfo[0]);

            if (preg_match("#(.+)\s*\(\s*([A-Z]{3})\s*\)#", $node, $m)) {
                $s->arrival()
                    ->name($m[1])
                    ->code($m[2]);
            }

            if (preg_match("#{$this->opt($this->t('ARRIVE TERMINAL'))} ([^\|]+)#", $notes, $m)) {
                $s->arrival()->terminal($m[1]);
            }
            $node = $this->re("#{$this->t('Arrives')}[ :]+(.+)#", $segmentInfo[1]);

            if (preg_match("#^(\d+:\d+\s*(?:[ap]m)?)[\s\-]*(?:TERMINAL[ ]+(.+))?$#i", $node, $m)) {
                $s->arrival()->date(strtotime($m[1], $date));

                if (isset($m[2])) {
                    $s->arrival()->terminal($m[2]);
                }
            } elseif (preg_match("#^(.+ \d+:\d+\s*(?:[ap]m)?)[\s\-]*(?:TERMINAL[ ]+(.+))?$#i", $node, $m)) {
                $s->arrival()->date($this->normalizeDate($m[1]));

                if (isset($m[2])) {
                    $s->arrival()->terminal($m[2]);
                }
            } else {
                $s->arrival()->date(strtotime($node, $date));
            }

            // extra
            $node = $this->re("#{$this->t('Class of Service')}[ :]+(.+)#", $segmentInfo[1]);

            if (preg_match("#^(.+?)\s*(?:\[([A-Z]{1,2})\])?$#", $node, $m)) {
                $s->extra()->cabin($m[1]);

                if (isset($m[2]) && !empty($m[2])) {
                    $s->extra()->bookingCode($m[2]);
                }
            }
            $node = $this->re("#{$this->opt($this->t('Seat Number'))}[ :]+(\d+[\-]*[A-z])#", $root);

            if (!empty($node)) {
                $s->extra()->seat(str_replace('-', '', $node));
            }

            $s->extra()
                ->aircraft($this->re("#{$this->t('Aircraft')}[ :]+(.+)#", $segmentInfo[0]))
                ->duration($this->re("#{$this->t('Flight Duration')}[ :]+(.+)#", $segmentInfo[0]))
                ->miles($this->re("#{$this->t('Miles')}[ :]+(.+)#", $segmentInfo[1]));
        }
        $airlines = array_unique($airlines);

        if (!empty($airlines)) {
            $node = $this->re("#{$this->opt($this->t('FREQUENT FLYER NUMBERS'))}\s+(.+)#s", $this->generalInfo);

            if (preg_match_all("#[\-\w]+\/[\-\w+].*? ({$this->opt($airlines)}[\-\/\w]+)$#m", $node, $m)) {
                $r->program()
                    ->accounts($m[1], false);
            }
        }

        if (preg_match_all("#{$this->opt($this->t('AIR TICKET'))} ((?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*[\d\-\/]+)#",
            $this->generalInfo, $m)) {
            $r->issued()->tickets($m[1], false);
        }

        $fees = (array) $this->t('fees');

        foreach ($fees as $fee) {
            $node = $this->re("#{$this->opt($fee)}[ ]+(.+)#", $this->paymentInfo);

            if (!empty($node)) {
                $r->price()->fee(trim($fee, ':'), PriceHelper::cost($node));
            }
        }
        $r->price()
            ->cost(PriceHelper::cost($this->re("#\n[ ]*{$this->opt($this->t('AIR FARE:'))}[ ]+(.+)#",
                $this->paymentInfo)))
            ->total(PriceHelper::cost($this->re("#\n[ ]*{$this->opt($this->t('TOTAL AIR FARE:'))}[ ]+(.+)#",
                $this->paymentInfo)), true, true)
            ->currency($this->defaultCurrency);

        return true;
    }

    private function parseHotelsPdf(Email $email, array $roots)
    {
        $this->logger->notice(__METHOD__);

        foreach ($roots as $root) {
            $pos = [];
            $r = $email->add()->hotel();
            $r->general()
                ->confirmation($this->re("#{$this->opt($this->t('Confirmation #'))}[ :]+([\w\-]+)#", $root))
                ->date($this->dateRes)
                ->travellers($this->pax);
            $r->ota()
                ->confirmation($this->agentRef, ((array) $this->t('Agency Booking Ref #'))[0], true)
//                ->confirmation($this->invoice, $this->t('Invoice Number'))
            ;

            $segmentInfo = $this->re("#\n([ ]*{$this->t('Check In')}.+?)(?:\n\s*[^\n]+?\||$)#s", $root);
            $pos[] = 0;
            $pos[] = strpos($segmentInfo, $this->t('Check Out'));
            $segmentInfo = $this->splitCols($segmentInfo, $pos);

            $r->hotel()
                ->name($this->nice($this->re("#{$this->opt($this->t('Hotel Name'))}[ :]+(.+)?{$this->t('Number of Rooms')}#s",
                    $segmentInfo[0])));

            $node = $this->nice($this->re("#{$this->opt($this->t('Hotel Address'))}[ :]+(.+)#s",
                $segmentInfo[0]));

            if (preg_match("#^(.+?)\s*(?:{$this->t('PHONE')}[\s\-]*(.+?))?[\s\-]*(?:{$this->t('FAX')}[\s\-]*(.+?))?(?:Directions)?[\s\-]*$#",
                $node, $m)) {
                $r->hotel()->address($m[1]);

                if (isset($m[2])) {
                    $r->hotel()->phone($m[2]);
                }

                if (isset($m[3])) {
                    $r->hotel()->fax($m[3]);
                }
            } else {
                $r->hotel()->address($node);
            }
            $r->booked()
                ->checkIn($this->normalizeDate($this->nice($this->re("#{$this->opt($this->t('Check In'))}[ :]+(.+)?{$this->t('Hotel Name')}#s",
                    $segmentInfo[0]))))
                ->checkOut($this->normalizeDate($this->nice($this->re("#{$this->opt($this->t('Check Out'))}[ :]+(.+)?{$this->t('Hotel Vendor')}#s",
                    $segmentInfo[1]))))
                ->rooms($this->re("#{$this->opt($this->t('Number of Rooms'))}[ :]+(\d+)#", $segmentInfo[0]))
                ->guests($this->re("#{$this->opt($this->t('Number of Persons'))}[ :]+(\d+)#", $segmentInfo[0]));
            $room = $r->addRoom();

            $room->setRate($this->re("#\n[ ]*{$this->opt($this->t('Rate'))}[ :]+(.+)#", $segmentInfo[1]));
            $notes = $this->nice($this->re("#\n\s*([^\n]+(?:\||CANCEL).+)#s", $root));

            if (preg_match("#{$this->opt($this->t('TOTAL RATE'))}[\s\-]*([\d\.]+)#", $notes, $m)) {
                $r->price()
                    ->cost(PriceHelper::cost($m[1]))
                    ->currency($this->defaultCurrency);
            }

            if (preg_match("#CANCEL RQRMTS[\s\-]([^\|]+)#", $notes, $m)) {
                $r->general()->cancellation($m[1]);
            } elseif (preg_match("#\|[^\|]* *(CANCEL [^\|]+)#", $notes, $m)) {
                $r->general()->cancellation($m[1]);
            }

            if (preg_match("#FREQUENT GUEST NBR[\s\-]*([^\|]+)#", $notes, $m)) {
                $r->program()->account($m[1], false);
            }

            if (preg_match("#APPROX TOTAL PRICE[\s\-]*([^\|]+)#", $notes, $m)) {
                $r->price()
                    ->cost(PriceHelper::cost($m[1]))
                    ->currency($this->defaultCurrency);
            }
            $this->detectDeadLine($r);
        }

        return true;
    }

    private function parseCarsPdf(Email $email, array $roots)
    {
        $this->logger->notice(__METHOD__);

        foreach ($roots as $root) {
            $pos = [];
            $r = $email->add()->rental();
            $r->general()
                ->confirmation($this->re("#{$this->opt($this->t('Confirmation #'))}[ :]+([\w\-]+)#", $root))
                ->date($this->dateRes)
                ->travellers($this->pax);
            $r->ota()
                ->confirmation($this->agentRef, ((array) $this->t('Agency Booking Ref #'))[0], true)
//                ->confirmation($this->invoice, $this->t('Invoice Number'))
            ;

            $segmentInfo = $this->re("#\n([ ]*{$this->t('Pick Up Date')}.+?)(?:\n\s*[^\n]+?\||$)#s", $root);
            $pos[] = 0;
            $pos[] = strpos($segmentInfo, $this->t('Drop Off Date'));
            $segmentInfo = $this->splitCols($segmentInfo, $pos);

            $r->pickup()
                ->location($this->nice($this->re("#{$this->opt($this->t('Pick Up Location'))}[ :]+(.+?)\s+{$this->t('Car Type')}#s",
                    $segmentInfo[0])))
                ->date(strtotime($this->re("#{$this->opt($this->t('Pick Up At'))}[ :]+(.+)#", $segmentInfo[0]),
                    $this->normalizeDate($this->re("#{$this->opt($this->t('Pick Up Date'))}[ :]+(.+)#",
                        $segmentInfo[0]))));
            $node = $this->nice($this->re("#{$this->opt($this->t('Drop Off Location'))}[ :]+(.+?)\s+{$this->opt($this->t('Estimated Rental Cost'))}#s",
                $segmentInfo[1]));

            if (empty($node)) {
                $r->dropoff()->same();
            } else {
                $r->dropoff()->location($node);
            }
            $r->dropoff()
                ->date(strtotime($this->re("#{$this->opt($this->t('Drop Off By'))}[ :]+(.+)#", $segmentInfo[1]),
                    $this->normalizeDate($this->re("#{$this->opt($this->t('Drop Off Date'))}[ :]+(.+)#",
                        $segmentInfo[1]))));

            $notes = $this->nice($this->re("#\n\s*([^\n]+(?:\||PHONE).+)#s", $root));

            if (preg_match("#{$this->opt($this->t('PHONE'))}[\s\-]*([^\|]+)#", $notes, $m)) {
                $r->pickup()->phone($m[1]);
            }
            $node = $this->re("#{$this->opt($this->t('Estimated Rental Cost'))}[ :]+(.+)#", $segmentInfo[1]);

            if (!empty($node)) {
                $sum = $this->getTotalCurrency($node);

                if (!empty($sum['Total'])) {
                    $r->price()
                        ->cost($sum['Total'])
                        ->currency($sum['Currency']);
                } elseif (preg_match("#^[\d\.]+$#", $node, $m)) {
                    $r->price()
                        ->cost(PriceHelper::cost($node))
                        ->currency($this->defaultCurrency);
                }
            }
            $r->car()
                ->type(
                    $this->nice(
                        $this->re("#{$this->opt($this->t('Car Type'))}[ :]+(.+?)\s+{$this->t('Car Vendor')}#s",
                            $segmentInfo[0])
                    )
                );

            $vendor = $this->nice($this->re("#{$this->opt($this->t('Car Vendor'))}[ :]+(.+?)\s+{$this->opt($this->t('Confirmation #'))}#s",
                $segmentInfo[0]));
            $r->extra()->company($vendor);

            if (preg_match("#(.+)\s+\(#", $vendor, $m)) {
                $keyword = $m[1];
                $rentalProvider = $this->getRentalProviderByKeyword($keyword);

                if (!empty($rentalProvider)) {
                    $r->program()->code($rentalProvider);
                } else {
                    $r->program()->keyword($keyword);
                }
            }
        }

        return true;
    }

    private function parseEmail(Email $email)
    {
        $this->logger->notice(__METHOD__);

        $xpath = "//text()[{$this->eq($this->t('PASSENGER INFORMATION'))}]/ancestor::tr[following-sibling::tr[{$this->contains($this->t('First Name'))}]]";
        $this->logger->debug("[xpath-header]: " . $xpath);
        $head = $this->http->XPath->query($xpath);

        if ($head->length !== 1) {
            $this->logger->debug('check format');

            return false;
        }
        $root = $head->item(0);

        $nodesFirstName = $this->http->FindNodes("./following-sibling::tr[normalize-space()!=''][1]{$this->nextTdXPathStr($this->t('First Name'))}", $root);
        $nodesLastName = $this->http->FindNodes("./following-sibling::tr[normalize-space()!=''][1]{$this->nextTdXPathStr($this->t('Last Name'))}", $root);

        if (count($nodesFirstName) === count($nodesLastName)) {
            for ($i = 0; $i <= count($nodesFirstName); $i++) {
                if (!empty($nodesFirstName[$i]) && !empty($nodesLastName[$i])) {
                    $this->pax[] = $nodesFirstName[$i] . ' ' . $nodesLastName[$i];
                }
            }
        }

        $this->agentRef = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]{$this->nextTdXPathStr($this->t('Agency Booking Ref #'))}",
            $root);
        $this->dateRes = $this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]{$this->nextTdXPathStr($this->t('Date Issued'))}",
            $root));
        $this->invoice = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]{$this->nextTdXPathStr($this->t('Invoice Number'))}",
            $root);

        $xpath = "//text()[{$this->eq($this->t('FLIGHT'))}]/ancestor::tr[following-sibling::tr[{$this->contains($this->t('From'))}]]";
        $flights = $this->http->XPath->query($xpath);

        if ($flights->length > 0) {
            $this->logger->debug("[XPATH-flight]: " . $xpath);
            $this->parseFlights($email, $flights);
        }

        $xpath = "//text()[{$this->eq($this->t('HOTEL'))}]/ancestor::tr[following-sibling::tr[{$this->contains($this->t('Hotel Name'))}]]";
        $hotels = $this->http->XPath->query($xpath);

        if ($hotels->length > 0) {
            $this->logger->debug("[XPATH-hotel]: " . $xpath);
            $this->parseHotels($email, $hotels);
        }

        $xpath = "//text()[{$this->eq($this->t('CAR'))}]/ancestor::tr[following-sibling::tr[{$this->contains($this->t('Pick Up Location'))}]]";
        $cars = $this->http->XPath->query($xpath);

        if ($cars->length > 0) {
            $this->logger->debug("[XPATH-car]: " . $xpath);
            $this->parseCars($email, $cars);
        }

        $node = $this->http->FindSingleNode("//text()[{$this->eq($this->t('TOTAL:'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]");

        if (!empty($node)) {
            $email->price()
                ->total(PriceHelper::cost($node))
                ->currency($this->defaultCurrency);
        }
        $node = $this->http->FindSingleNode("//text()[{$this->eq($this->t('SERVICE FEE:'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]");

        if (!empty($node)) {
            $email->price()
                ->fee(trim($this->t('SERVICE FEE:'), ':'), $node);
        }

        return true;
    }

    private function parseFlights(Email $email, \DOMNodeList $roots)
    {
        $this->logger->notice(__METHOD__);

        $r = $email->add()->flight();
        $r->general()
            ->noConfirmation()
            ->date($this->dateRes)
            ->travellers($this->pax);
        $r->ota()
            ->confirmation($this->agentRef, ((array) $this->t('Agency Booking Ref #'))[0], true)
//            ->confirmation($this->invoice, $this->t('Invoice Number'))
        ;

        $airlines = [];

        foreach ($roots as $root) {
            $s = $r->addSegment();
            $date = $this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]",
                $root));

            // airline
            $s->airline()
                ->name($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][2]{$this->nextTdXPathStr($this->t('Air Vendor'))}",
                    $root, false, "#\(\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\)\s*$#"))
                ->confirmation(
                    $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][2]{$this->nextTdXPathStr($this->t('Ticket Confirmation'))}",
                        $root))
                ->number($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][2]{$this->nextTdXPathStr($this->t('Flight Number'))}",
                    $root));
            $operator = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][2]{$this->nextTdXPathStr($this->t('Operated By'))}",
                $root, false, "#^(.+?)(?: DBA |$)#");

            if (!empty($operator)) {
                $s->airline()->operator($operator);
            }
            $airlines[] = $s->getAirlineName();

            $notes = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][2]/descendant::text()[{$this->eq($this->t('Ticket Confirmation'))}]/ancestor::tr[1]/following::tr[normalize-space()!=''][1][contains(.,'TERMINAL')]",
                $root);
            // departure
            $node = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][2]{$this->nextTdXPathStr($this->t('From'))}",
                $root);

            if (preg_match("#(.+)\s*\(([A-Z]{3})\)#", $node, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->code($m[2]);
            }

            if (preg_match("#{$this->opt($this->t('DEPART TERMINAL'))} ([^\|]+)#", $notes, $m)) {
                $s->departure()->terminal($m[1]);
            }
            $node = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][2]{$this->nextTdXPathStr($this->t('Departs'))}",
                $root);

            if (preg_match("#^(\d+:\d+\s*(?:[ap]m)?)[\s\-]*(?:TERMINAL[ ]+(.+))?$#i", $node, $m)) {
                $s->departure()->date(strtotime($m[1], $date));

                if (isset($m[2])) {
                    $s->departure()->terminal($m[2]);
                }
            } else {
                $s->departure()->date(strtotime($node, $date));
            }

            // arrival
            $node = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][2]{$this->nextTdXPathStr($this->t('To'))}",
                $root);

            if (preg_match("#(.+)\s*\(([A-Z]{3})\)#", $node, $m)) {
                $s->arrival()
                    ->name($m[1])
                    ->code($m[2]);
            }

            if (preg_match("#{$this->opt($this->t('ARRIVE TERMINAL'))} ([^\|]+)#", $notes, $m)) {
                $s->arrival()->terminal($m[1]);
            }
            $node = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][2]{$this->nextTdXPathStr($this->t('Arrives'))}",
                $root);

            if (preg_match("#^(\d+:\d+\s*(?:[ap]m)?)[\s\-]*(?:TERMINAL[ ]+(.+))?$#i", $node, $m)) {
                $s->arrival()->date(strtotime($m[1], $date));

                if (isset($m[2])) {
                    $s->arrival()->terminal($m[2]);
                }
            } elseif (preg_match("#^(.+ \d+:\d+\s*(?:[ap]m)?)[\s\-]*(?:TERMINAL[ ]+(.+))?$#i", $node, $m)) {
                $s->arrival()->date($this->normalizeDate($m[1]));

                if (isset($m[2])) {
                    $s->arrival()->terminal($m[2]);
                }
            } else {
                $s->arrival()->date(strtotime($node, $date));
            }

            // extra
            $node = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][2]{$this->nextTdXPathStr($this->t('Class of Service'))}",
                $root);

            if (preg_match("#^(.+?)\s*(?:\[([A-Z]{1,2})\])?$#", $node, $m)) {
                $s->extra()->cabin($m[1]);

                if (isset($m[2]) && !empty($m[2])) {
                    $s->extra()->bookingCode($m[2]);
                }
            }
            $node = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][2]{$this->nextTdXPathStr($this->t('Seat Number'))}",
                $root, false, "#(\d+[\-]*[A-z])#");

            if (!empty($node)) {
                $s->extra()->seat(str_replace('-', '', $node));
            }

            $s->extra()
                ->aircraft($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][2]{$this->nextTdXPathStr($this->t('Aircraft'))}",
                    $root), true, true)
                ->duration($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][2]{$this->nextTdXPathStr($this->t('Flight Duration'))}",
                    $root), true, true)
                ->miles($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][2]{$this->nextTdXPathStr($this->t('Miles'))}",
                    $root), true, true);
        }
        $airlines = array_unique($airlines);

        if (!empty($airlines)) {
            foreach ($airlines as $air) {
                // HR==H1 so comment it-45417400
//                $tickets = array_filter(array_unique($this->http->FindNodes("//text()[({$this->starts($this->t('AIR TICKETS'))}) and contains(normalize-space(),' {$air}')]",
//                    null, "# ({$air}\s*[\d\-\/]+)#")));
//                if (!empty($tickets)) {
//                    $r->issued()->tickets($tickets, false);
//                }
                $ff = array_filter(array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('FREQUENT FLYER NUMBERS'))}]/ancestor::tr[1]/following-sibling::tr[contains(.,'/') and contains(normalize-space(),' {$air}')]",
                    null, "# ({$air}\s*[\w\-\/]+)#")));

                if (!empty($ff)) {
                    $r->program()->accounts($ff, false);
                }
            }
        }
        $tickets = array_filter(array_unique($this->http->FindNodes("//text()[{$this->starts($this->t('AIR TICKETS'))}]",
            null, "#{$this->opt($this->t('AIR TICKET'))} ((?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*[\d\-\/]+)#")));

        if (!empty($tickets)) {
            $r->issued()->tickets($tickets, false);
        }

        $fees = (array) $this->t('fees');

        foreach ($fees as $fee) {
            $node = $this->http->FindSingleNode("//text()[{$this->eq($fee)}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]");

            if (!empty($node)) {
                $r->price()->fee(trim($fee, ':'), $node);
            }
        }

        $totalFare = $this->http->FindSingleNode("//text()[{$this->eq($this->t('TOTAL AIR FARE:'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true, '/^\d[,.\'\d ]*$/');

        if ($totalFare !== null) {
            $r->price()->total(PriceHelper::cost($totalFare))->currency($this->defaultCurrency);
            $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('AIR FARE:'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");

            if ($cost !== null) {
                $r->price()->cost(PriceHelper::cost($cost));
            }
        }

        return true;
    }

    private function parseHotels(Email $email, \DOMNodeList $roots)
    {
        $this->logger->notice(__METHOD__);

        foreach ($roots as $root) {
            $r = $email->add()->hotel();
            $r->general()
                ->confirmation($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]{$this->nextTdXPathStr($this->t('Confirmation #'))}",
                    $root))
                ->date($this->dateRes)
                ->travellers($this->pax);
            $r->ota()
                ->confirmation($this->agentRef, ((array) $this->t('Agency Booking Ref #'))[0], true)
//                ->confirmation($this->invoice, $this->t('Invoice Number'))
            ;

            $r->hotel()
                ->name($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]{$this->nextTdXPathStr($this->t('Hotel Name'))}",
                    $root));

            $node = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]{$this->nextTdXPathStr($this->t('Hotel Address'))}",
                $root);

            if (preg_match("#^(.+?)\s*(?:{$this->t('PHONE')}[\s\-]*(.+?))?[\s\-]*(?:{$this->t('FAX')}[\s\-]*(.+?))?(?:Directions)?[\s\-]*$#s",
                $node, $m)) {
                $r->hotel()->address($m[1]);

                if (isset($m[2])) {
                    $r->hotel()->phone($m[2]);
                }

                if (isset($m[3])) {
                    $r->hotel()->fax($m[3]);
                }
            } else {
                $r->hotel()->address($node);
            }
            $r->booked()
                ->checkIn($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]{$this->nextTdXPathStr($this->t('Check In'))}",
                    $root)))
                ->checkOut($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]{$this->nextTdXPathStr($this->t('Check Out'))}",
                    $root)))
                ->rooms($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]{$this->nextTdXPathStr($this->t('Number of Rooms'))}",
                    $root))
                ->guests($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]{$this->nextTdXPathStr($this->t('Number of Persons'))}",
                    $root));
            $room = $r->addRoom();

            $room->setRate($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]{$this->nextTdXPathStr($this->t('Rate'))}",
                $root));
            $notes = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]/descendant::text()[contains(.,'CANCEL')]",
                $root);

            if (preg_match("#{$this->opt($this->t('TOTAL RATE'))}[\s\-]*([\d\.]+)#", $notes, $m)) {
                $r->price()
                    ->cost(PriceHelper::cost($m[1]))
                    ->currency($this->defaultCurrency);
            }

            if (preg_match("#CANCEL RQRMTS[\s\-]([^\|]+)#", $notes, $m)) {
                $r->general()->cancellation($m[1]);
            } elseif (preg_match("#\|[^\|]* *(CANCEL [^\|]+)#", $notes, $m)) {
                $r->general()->cancellation($m[1]);
            }

            if (preg_match("#FREQUENT GUEST NBR[\s\-]*([^\|]+)#", $notes, $m)) {
                $r->program()->account($m[1], false);
            }

            if (preg_match("#APPROX TOTAL PRICE[\s\-]*([^\|]+)#", $notes, $m)) {
                $r->price()
                    ->cost(PriceHelper::cost($m[1]))
                    ->currency($this->defaultCurrency);
            }
            $this->detectDeadLine($r);
        }

        return true;
    }

    private function parseCars(Email $email, \DOMNodeList $roots)
    {
        $this->logger->notice(__METHOD__);

        foreach ($roots as $root) {
            $r = $email->add()->rental();
            $r->general()
                ->confirmation($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]{$this->nextTdXPathStr($this->t('Confirmation #'))}",
                    $root))
                ->date($this->dateRes)
                ->travellers($this->pax);
            $r->ota()
                ->confirmation($this->agentRef, ((array) $this->t('Agency Booking Ref #'))[0], true)
//                ->confirmation($this->invoice, $this->t('Invoice Number'))
            ;

            $r->pickup()
                ->location($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]{$this->nextTdXPathStr($this->t('Pick Up Location'))}",
                    $root))
                ->date(strtotime($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]{$this->nextTdXPathStr($this->t('Pick Up At'))}",
                    $root),
                    $this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]{$this->nextTdXPathStr($this->t('Pick Up Date'))}",
                        $root))));
            $node = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]{$this->nextTdXPathStr($this->t('Drop Off Location'))}",
                $root);

            if (empty($node)) {
                $r->dropoff()->same();
            } else {
                $r->dropoff()->location($node);
            }
            $r->dropoff()
                ->date(strtotime($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]{$this->nextTdXPathStr($this->t('Drop Off By'))}",
                    $root),
                    $this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]{$this->nextTdXPathStr($this->t('Drop Off Date'))}",
                        $root))));

            $notes = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]/descendant::text()[contains(.,'PHONE')]",
                $root);

            if (preg_match("#{$this->opt($this->t('PHONE'))}[\s\-]*([^\|]+)#", $notes, $m)) {
                $r->pickup()->phone($m[1]);
            }
            $node = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]{$this->nextTdXPathStr($this->t('Estimated Rental Cost'))}",
                $root);

            if (!empty($node)) {
                $sum = $this->getTotalCurrency($node);

                if (!empty($sum['Total'])) {
                    $r->price()
                        ->cost($sum['Total'])
                        ->currency($sum['Currency']);
                } elseif (preg_match("#^[\d\.]+$#", $node, $m)) {
                    $r->price()
                        ->cost(PriceHelper::cost($node))
                        ->currency($this->defaultCurrency);
                }
            }
            $r->car()->type($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]{$this->nextTdXPathStr($this->t('Car Type'))}",
                $root));

            $vendor = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]{$this->nextTdXPathStr($this->t('Car Vendor'))}",
                $root);
            $r->extra()->company($vendor);

            if (preg_match("#(.+)\s+\(#", $vendor, $m)) {
                $keyword = $m[1];
                $rentalProvider = $this->getRentalProviderByKeyword($keyword);

                if (!empty($rentalProvider)) {
                    $r->program()->code($rentalProvider);
                } else {
                    $r->program()->keyword($keyword);
                }
            }
        }

        return true;
    }

    private function nextTdXPathStr($field)
    {
        return "/descendant::text()[{$this->eq($field)}]/ancestor::td[1]/following-sibling::td[normalize-space()!=':'][1]";
    }

    private function normalizeDate($date)
    {
        $in = [
            //2019-09-19 10:14:00
            '#^(\d{4}\-\d+\-\d+ \d+:\d+):\d{2}$#u',
            //Thursday, November 7, 2019
            '#^\s*[\w\-]+, (\w+) (\d+), (\d{4})\s*$#iu',
            //2019-10-12 4:15 PM
            '#^(\d{4}\-\d+\-\d+ \d+:\d+\s*(?:[ap]m)?)\s*$#u',
            //Sat. Oct. 12, 2019 4:15 PM
            '#^\s*[\w\-]+\. (\w+)\. (\d+), (\d{4}) (\d+:\d+\s*(?:[ap]m)?)\s*$#iu',
        ];
        $out = [
            '$1',
            '$2 $1 $3',
            '$1',
            '$2 $1 $3, $4',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/^CANCEL (?<priorDays>\d+) DAYS? PRIOR TO ARRIVAL/i", $cancellationText, $m)
            || preg_match("/^(?<priorDays>\d+) DAY CANCELLATION REQUIRED$/i", $cancellationText, $m)
            || preg_match("/^CANCEL PERMITTED UP TO (?<priorDays>\d+) DAYS? BEFORE ARRIVAL/i", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m['priorDays'] . ' days');

            return;
        } elseif (preg_match("/^CANCEL BY (?<time>\d+ [AP]M) DAY OF ARRIVAL/i", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative('0 days', $m['time']);

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

    private function assignProvider($headers, $text = ''): bool
    {
        if ($this->http->XPath->query('//a[contains(@href,".travelleaders.com/") or contains(@href,"www.shakopeetravel.com")]')->length > 0
            || $this->http->XPath->query('//node()[contains(normalize-space(),"independent affiliate of Shakopee Travel") or contains(normalize-space(),"16731 Hwy 13 South, Suite 108A")]')->length > 0
            || stripos($text, 'Travel Leaders - Shakopee Travel') !== false
            || stripos($text, '16731 Hwy 13 South, Suite 108A') !== false
        ) {
            $this->providerCode = 'tleaders';

            return true;
        }

        if (stripos($headers['from'], '@teplis.com') !== false
            || $this->http->XPath->query('//node()[contains(.,"@teplis.com") or contains(normalize-space(),"400 Perimeter Center Terrace")]')->length > 0
            || $this->http->XPath->query('//a[contains(@href,".teplis.com/") or contains(@href,"www.teplis.com")]')->length > 0
            || stripos($text, 'TEPLIS') !== false
            // other providers
            || $this->http->XPath->query('//node()[contains(.,"@etravelomaha.com") or contains(.,"@ETRAVELOMAHA.COM")]')->length > 0
            || $this->http->XPath->query('//a[contains(@href,".etravelomaha.com/") or contains(@href,"www.etravelomaha.com")]')->length > 0
        ) {
            $this->providerCode = 'teplis';

            return true;
        }

        if (stripos($headers['from'], '@ACENDAS.COM ') !== false
            || $this->http->XPath->query('//node()[contains(.,"@ACENDAS.COM")]')->length > 0
            || $this->http->XPath->query('//node()[contains(.,"Acendas Agency Information")]')->length > 0
        ) {
            $this->providerCode = 'bcd';

            return true;
        }

        return false;
    }

    private function assignLang($body): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['PASSENGER INFORMATION'], $words['First Name'])) {
                if ($this->striposArr($body, $words['PASSENGER INFORMATION']) && $this->striposArr($body,
                        $words['First Name'])
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node): array
    {
        $node = str_replace(["€", "£", "$", "₹"], ["EUR", "GBP", "USD", "INR"], $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function striposArr($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '#'));
        }, $field)) . ')';
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function rowColsPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function strstrArr(string $haystack, $needle, bool $before_needle = false): ?string
    {
        $needles = (array) $needle;

        foreach ($needles as $needle) {
            $str = strstr($haystack, $needle, $before_needle);

            if (!empty($str)) {
                return $str;
            }
        }

        return null;
    }

    private function nice($str)
    {
        return trim(preg_replace("#\s+#", ' ', $str));
    }

    private function getRentalProviderByKeyword(string $keyword)
    {
        if (!empty($keyword)) {
            foreach ($this->rentalKeywords as $code => $kws) {
                if (in_array($keyword, $kws)) {
                    return $code;
                }
            }
        }

        return null;
    }
}
