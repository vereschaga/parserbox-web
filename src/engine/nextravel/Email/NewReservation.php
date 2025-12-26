<?php

namespace AwardWallet\Engine\nextravel\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class NewReservation extends \TAccountChecker
{
    public $mailFiles = "nextravel/it-45903096.eml, nextravel/it-46465916.eml, nextravel/it-46466067.eml, nextravel/it-46466259.eml, nextravel/it-46466296.eml, nextravel/it-47250275.eml, nextravel/it-47297804.eml, nextravel/it-47350445.eml, nextravel/it-47634765.eml, nextravel/it-47808035.eml, nextravel/it-48034783.eml, nextravel/it-48200771.eml, nextravel/it-48502796.eml";

    public $reFrom = ["nextravel.com"];
    public $reBody = [
        'en' => [
            'This is an automated notification that',
            'If you did not cancel the order, some possible',
            'You can view the updated itinerary on',
            'Your order has been confirmed',
            'Here is the summary for your order',
        ],
    ];
    public $reBodyPdf = [
        'en' => ['TRAVEL TYPE:'],
    ];
    public $reSubject = [
        '/has made a new reservation. Order # [A-Z\d]+$/',
        '/CANCELLED - Your order has been cancelled. Order # [A-Z\d]+$/',
        '/The itinerary has changed for Order # [A-Z\d]+$/',
        '/Your order is confirmed\! Order # [A-Z\d]+ \(Trip ID \d+\-\d+\)$/',
        '/Order Pending. Order # [A-Z\d]+$/',
    ];
    public $lang = '';
    public $pdf;
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'Order Number'       => 'Order Number',
            'Travelers'          => 'Travelers',
            'Hotel Confirmation' => 'Hotel Confirmation',
            // Pdf
            'ORDER #'     => 'ORDER #',
            'RECEIPT #'   => 'RECEIPT #',
            'endTraveler' => ['CATEGORY:', 'DIVISION:'],
        ],
    ];
    private $keywordProv = 'NexTravel';
    private $cancelledSubject = [
        'CANCELLED - Your order has been cancelled',
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (preg_match("/{$this->opt($this->cancelledSubject)}/", $parser->getSubject())) {
            $cancelledSubject = true;
        }

        $type = '';
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->assignLangText($text) && $this->detectBody($text)) {
                        $this->parseEmailPdf($text, $email);
                        $type = "Pdf";

                        if (isset($cancelledSubject) && count($email->getItineraries()) > 0) {
                            // break the parsing
                            $this->logger->alert('new format: pdf cancelled');
                            $email->add()->flight();

                            break;
                        }
                    }
                }
            }
        }

        if (count($email->getItineraries()) == 0) {
            $type = "Html";

            if (!$this->assignLang()) {
                $this->logger->debug('can\'t determine a language');

                return $email;
            }
            $this->parseEmail($email);
        }
        $tripId = $this->http->FindSingleNode("//text()[{$this->starts($this->t('NexTravel Trip ID:'))}]", null, false,
            "/{$this->opt($this->t('NexTravel Trip ID:'))}\s*(.+)/");

        if (!empty($tripId)) {
            $email->ota()
                ->confirmation($tripId, trim($this->t('NexTravel Trip ID:'), ": "));
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($type) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'.nextravel.com')] | //a[contains(@href,'.nextravel.com')]")->length > 0) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return $this->assignLang();
                }
            }
        }
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ((preg_match("#\b{$this->opt($this->keywordProv)}\b#i", $text) > 0)
                && $this->assignLangText($text)
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

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || preg_match("#\b{$this->opt($this->keywordProv)}\b#i", $headers["subject"]) > 0)
                    && preg_match($reSubject, $headers["subject"]) > 0
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
        $types = 3; // flight | rental | hotel
        $formats = 2; // pdf | html
        $cnt = $types * $formats * count(self::$dict);

        return $cnt;
    }

    private function parseEmailPdf(string $textPDF, Email $email)
    {
        $this->logger->notice(__METHOD__);

        $checkType = strstr($textPDF, $this->t('TRAVEL TYPE:'));
        $rows = explode("\n", $checkType);

        if (!isset($rows[1])) {
            $this->logger->debug('other format');

            return false;
        }

        //del Logo TA-info
        $textPDF = preg_replace("/(\n[ ]*NexTravel, Inc(?:[ ]{3,}Customer Support[^\n]*)?\s+1411 5th St.+\s+.+)/", '',
            $textPDF);

        $type = $this->re("/^(.+?)[ ]{2,}/", $rows[1]);

        if (in_array($type, (array) $this->t('Air'))) {
            return $this->parseFlightPdf($textPDF, $email);
        } elseif (in_array($type, (array) $this->t('Car'))) {
            return $this->parseRentalPdf($textPDF, $email);
        } elseif (in_array($type, (array) $this->t('Hotel'))) {
            return $this->parseHotelPdf($textPDF, $email);
        }
        $this->logger->debug('unknown travel type');

        return false;
    }

    private function parseFlightPdf(string $textPDF, Email $email)
    {
        $this->logger->notice(__METHOD__);

        $mainBlock = strstr($textPDF, $this->t('RECEIPT #'), true);
        $detailsBlock = strstr($textPDF, $this->t('RECEIPT #'));
        $r = $email->add()->flight();
        $r->ota()
            ->confirmation($this->re("/^\s*{$this->opt($this->t('ORDER #'))}[ ]*([\w\-]+)/", $mainBlock),
                $this->t('ORDER #'))
            ->confirmation($this->re("/^\s*{$this->opt($this->t('RECEIPT #'))}[ ]*([\w\-]+)/", $detailsBlock),
                $this->t('RECEIPT #'));

        $segments = $this->splitter("/\n[ ]*({$this->opt($this->t('FLIGHT'))}[ ]*\d+:)/", $mainBlock);

        foreach ($segments as $i => $seg) {
            $resultSegments = [];

            if (preg_match_all("/\([A-Z]{3}\)/", $seg, $m, PREG_SET_ORDER) && count($m) > 2) {
                $resultSegments = $this->splitter("/\n([ ]*.+?\b\d{4}[ ]{3,}\d+:\d+)/", $seg);
            } else {
                $resultSegments[] = $this->re("/[^\n]+\n\s*(.+)/s", $seg);
            }

            foreach ($resultSegments as $j => $segment) {
                $table = $segment;
                $table = $this->splitCols($table, $this->colsPos($this->re("/(.+)/", $table)));

                if (count($table) !== 3) {
                    $this->logger->debug('other formet (' . $i . '-' . $j . ') segment');

                    return false;
                }
                $s = $r->addSegment();
                $date = $this->normalizeDate(preg_replace("/\s+/", ' ', $table[0]));

                $rows = explode("\n", $table[1]);

                if (count($rows) >= 3) {
                    if (preg_match("/(.+?)[ ]*[\-–][ ]*(.+)/u", $rows[0], $m)) {
                        $s->departure()->date(strtotime($m[1], $date));
                        $s->arrival()->date(strtotime($m[2], $date));
                    }

                    if (preg_match("/(.+?) \(([A-Z]{3})\)[ ]*[\-–][ ]*[^\w]*(.+?) \(([A-Z]{3})\)/", $rows[1], $m)) {
                        $s->departure()
                            ->name($m[1])
                            ->code($m[2]);
                        $s->arrival()
                            ->name($m[3])
                            ->code($m[4]);
                    }
                    $rows = implode(' ', array_slice($rows, 2));

                    if (preg_match("/(?<duration>.+?)[ ]*•[ ]*(?<airline>.+?)[ ]*•[ ]*(?<iata>[A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]*(?<number>\d+)[ ]*\((?<bcode>[A-Z]{1,2})\)[ ]*•[ ]*(?<aircraft>.+)/",
                        $rows, $m)) {
                        $s->airline()
                            ->name($m['iata'])
                            ->number($m['number']);
                        $s->extra()
                            ->duration($m['duration'])
                            ->bookingCode($m['bcode'])
                            ->aircraft($m['aircraft']);
                    }
                }

                if (preg_match("/{$this->opt($this->t('Seat'))}:\s*(\d+[A-z])\b/", $table[2], $m)) {
                    $s->extra()->seat($m[1]);
                }
            }
        }
        $table = $this->re("/\n([ ]*{$this->t('TRAVEL TYPE:')}.+?)\n[ ]*{$this->t('ITEM')}/s", $detailsBlock);
        //$table = $this->splitCols($table, $this->colsPos($table));
        $table = $this->splitCols($table, $this->colsPos($this->re("/(.+)/", $table)));

        if (count($table) !== 4) {
            $this->logger->debug('other format RECEIPT-part');

            return false;
        }

        $confNumber = ($this->re("/{$this->t('CONFIRMATION:')}\s+([\w\-]+)\n/", $table[1]));

        if (empty($confNumber) && preg_match("/{$this->t('CONFIRMATION:')}/i", $detailsBlock) == 0) {
            // FE: it-48502796
            $r->general()->noConfirmation();
            $r->general()
                ->traveller(preg_replace('/\s+/', ' ',
                    $this->re("/{$this->t('TRAVELER:')}\s+(.+?)(?:\n\n|{$this->opt($this->t('endTraveler'))}|$)/s",
                        $table[2])), true);
        } else {
            $r->general()
                ->confirmation($this->re("/{$this->t('CONFIRMATION:')}\s+([\w\-]+)\n/", $table[1]),
                    trim($this->t('CONFIRMATION:'), ':'))
                ->traveller(preg_replace('/\s+/', ' ',
                    $this->re("/{$this->t('TRAVELER:')}\s+(.+?)(?:\n\n|{$this->opt($this->t('endTraveler'))}|$)/s",
                    $table[3])), true);
        }

        $ticket = $this->re("/{$this->t('TICKET NUMBER:')}\s+([\d ]+)\n/", $table[2]);

        if (!empty($ticket)) {
            $r->issued()->ticket($ticket, false);
        }

        $textForFees = strstr($detailsBlock, $this->t('Total:'), true);

        $textForFees = $this->re("/\n[ ]*{$this->t('ITEM')}[ ]{2,}[^\n]+\n(.+)/s", $textForFees);

        if (preg_match_all("/^(.+?)[ ]{3,}.+[ ]{3,}(\d.+)$/m", $textForFees, $m, PREG_SET_ORDER)) {
            foreach ($m as $v) {
                $sum = $this->getTotalCurrency($v[2]);

                if ($v[1] === $this->t('Base')) {
                    $r->price()
                        ->cost($sum['Total']);
                } else {
                    $r->price()
                        ->fee($v[1], $sum['Total']);
                }
            }
        }
        $sum = $this->re("/\n[ ]{3,}{$this->t('Total:')}[ ]+(\d.+)\n/", $detailsBlock);
        $sum = $this->getTotalCurrency($sum);
        $r->price()
            ->total($sum['Total'])
            ->currency($sum['Currency']);

        return true;
    }

    private function parseRentalPdf(string $textPDF, Email $email)
    {
        $this->logger->notice(__METHOD__);
        $mainBlock = strstr($textPDF, $this->t('RECEIPT #'), true);
        $detailsBlock = strstr($textPDF, $this->t('RECEIPT #'));
        $r = $email->add()->rental();
        $r->ota()
            ->confirmation($this->re("/^\s*{$this->opt($this->t('ORDER #'))}[ ]*([\w\-]+)/", $mainBlock),
                $this->t('ORDER #'))
            ->confirmation($this->re("/^\s*{$this->opt($this->t('RECEIPT #'))}[ ]*([\w\-]+)/", $detailsBlock),
                $this->t('RECEIPT #'));

        $parts = $this->splitter("/\n([ ]*\d+\/\d+\/\d+)/", $mainBlock);

        if (count($parts) !== 2) {
            $this->logger->debug('other format rental');

            return false;
        }

        // pickup
        $table = $this->splitCols($parts[0], $this->colsPos($parts[0]));

        if (count($table) !== 3) {
            $this->logger->debug('other format rental pickup info');

            return false;
        }
        $date = $this->normalizeDate(preg_replace("/\s+/", ' ', $table[0]));

        if (preg_match("/{$this->opt($this->t('PICKUP'))}[ ]+(\d+:[^\n]+)\n([^\n]+)\n(.+?)\n([ \d\-\+]+)$/s", $table[1],
            $m)) {
            $r->pickup()
                ->date(strtotime($m[1], $date))
                ->location(preg_replace("/\s+/", ' ', $m[3]))
                ->phone($m[4]);
        }
        $rows = explode("\n", trim($table[2]));

        if (count($rows) === 2) {
            $r->car()->type($rows[0])->model($rows[1]);
        } else {
            $r->car()->type(preg_replace("/\s+/", ' ', $table[2]));
        }

        // dropoff
        $table = $this->splitCols($parts[1], $this->colsPos($parts[1]));

        if (count($table) !== 2) {
            $this->logger->debug('other format rental dropoff info');

            return false;
        }
        $date = $this->normalizeDate(preg_replace("/\s+/", ' ', $table[0]));

        if (preg_match("/{$this->opt($this->t('DROPOFF'))}[ ]+(\d+:[^\n]+)\n([^\n]+)\n(.+?)\n([ \d\-\+]+)$/s",
            trim($table[1]),
            $m)) {
            $r->dropoff()
                ->date(strtotime($m[1], $date))
                ->location(preg_replace("/\s+/", ' ', $m[3]))
                ->phone($m[4]);
        }

        // details
        $table = $this->re("/\n([ ]*{$this->t('TRAVEL TYPE:')}.+?)\n[ ]*{$this->t('ITEM')}/s", $detailsBlock);
        $table = $this->splitCols($table, $this->colsPos($table));

        if (count($table) !== 4) {
            $this->logger->debug('other format RECEIPT-part');

            return false;
        }
        $r->general()
            ->confirmation($this->re("/{$this->t('CONFIRMATION:')}\s+([\w\-]+)\n/", $table[1]),
                trim($this->t('CONFIRMATION:'), ':'))
            ->traveller(preg_replace('/\s+/', ' ',
                $this->re("/{$this->t('TRAVELER:')}\s+(.+?)\s+(?:\n\n|{$this->opt($this->t('endTraveler'))}|$)/s",
                    $table[2])), true);

        $textForFees = strstr($detailsBlock, $this->t('Total:'), true);

        $textForFees = $this->re("/\n[ ]*{$this->t('ITEM')}[ ]{2,}[^\n]+\n(.+)/s", $textForFees);

        if (preg_match_all("/^(.+?)[ ]{3,}.+[ ]{3,}(\d.+)$/m", $textForFees, $m, PREG_SET_ORDER)) {
            foreach ($m as $v) {
                $sum = $this->getTotalCurrency($v[2]);

                if ($v[1] === $this->t('Base')) {
                    $r->price()
                        ->cost($sum['Total']);
                } else {
                    $r->price()
                        ->fee($v[1], $sum['Total']);
                }
            }
        }
        $sum = $this->re("/\n[ ]{3,}\*?{$this->t('Total:')}[ ]+(\d.+)\n/", $detailsBlock);
        $sum = $this->getTotalCurrency($sum);
        $r->price()
            ->total($sum['Total'])
            ->currency($sum['Currency']);

        return true;
    }

    private function parseHotelPdf(string $textPDF, Email $email)
    {
        $this->logger->notice(__METHOD__);
        $mainBlock = strstr($textPDF, $this->t('RECEIPT #'), true);
        $detailsBlock = strstr($textPDF, $this->t('RECEIPT #'));
        $r = $email->add()->hotel();
        $r->ota()
            ->confirmation($this->re("/^\s*{$this->opt($this->t('ORDER #'))}[ ]*([\w\-]+)/", $mainBlock),
                $this->t('ORDER #'))
            ->confirmation($this->re("/^\s*{$this->opt($this->t('RECEIPT #'))}[ ]*([\w\-]+)/", $detailsBlock),
                $this->t('RECEIPT #'));

        $parts = $this->splitter("/\n([ ]*\d+\/\d+\/\d+)/", $mainBlock);

        if (count($parts) !== 2) {
            $this->logger->debug('other format rental');

            return false;
        }

        // in
        $table = $this->splitCols($parts[0], $this->colsPos($parts[0]));

        if (count($table) !== 3) {
            $this->logger->debug('other format hotel checkin info');

            return false;
        }
        $date = $this->normalizeDate(preg_replace("/\s+/", ' ', $table[0]));

        if (preg_match("/{$this->opt($this->t('ARRIVE'))}[ ]+(\d+:[^\n]+)\n([^\n]+)\n(.+?)(?:\n([ \+\-\d]+))?\s*$/s",
            $table[1],
            $m)) {
            $r->booked()
                ->checkIn(strtotime($m[1], $date));
            $r->hotel()
                ->name($m[2])
                ->address(preg_replace("/\s+/", ' ', $m[3]));

            if (isset($m[4]) && !empty(trim($m[4]))) {
                $r->hotel()->phone($m[4]);
            }
        }
        $r->booked()
            ->guests($this->re("/(\d+)\s+{$this->opt($this->t('Adult'))}/", $table[2]))
            ->rooms($this->re("/(\d+)\s+{$this->opt($this->t('Room'))}/", $table[2]));

        // out
        $table = $this->splitCols($parts[1], $this->colsPos($parts[1]));

        if (count($table) !== 2) {
            $this->logger->debug('other format hotel checkout info');

            return false;
        }
        $date = $this->normalizeDate(preg_replace("/\s+/", ' ', $table[0]));

        if (preg_match("/{$this->opt($this->t('DEPART'))}[ ]+(\d+:[^\n]+)\n([^\n]+)\s*$/s", trim($table[1]),
            $m)) {
            $r->booked()
                ->checkOut(strtotime($m[1], $date));
        }

        // details
        $table = $this->re("/\n([ ]*{$this->t('TRAVEL TYPE:')}.+?)\n[ ]*{$this->t('ITEM')}/s", $detailsBlock);
        $table = $this->splitCols($table, $this->colsPos($table));

        if (count($table) !== 4) {
            $this->logger->debug('other format RECEIPT-part');

            return false;
        }
        $r->general()
            ->confirmation($this->re("/{$this->t('CONFIRMATION:')}\s+([\w\-]+)\n/", $table[1]),
                trim($this->t('CONFIRMATION:'), ':'));

        $r->general()
            ->traveller(preg_replace('/\s+/', ' ',
                $this->re("/{$this->t('TRAVELER:')}\s+(.+?)\s+(?:\n\n|{$this->opt($this->t('endTraveler'))}|$)/s",
                    $table[2])),
                true);

        $textForFees = strstr($detailsBlock, $this->t('Total:'), true);

        $textForFees = $this->re("/\n[ ]*{$this->t('ITEM')}[ ]{2,}[^\n]+\n(.+)/s", $textForFees);

        if (preg_match_all("/^(.+?)[ ]{3,}.+[ ]{3,}(\d.+)$/m", $textForFees, $m, PREG_SET_ORDER)) {
            foreach ($m as $v) {
                $sum = $this->getTotalCurrency($v[2]);
                $r->price()
                    ->fee($v[1], $sum['Total']);
            }
        }
        $sum = $this->re("/\n[ ]{3,}{$this->t('Total:')}[ ]+(\d.+)\n/", $detailsBlock);
        $sum = $this->getTotalCurrency($sum);
        $r->price()
            ->total($sum['Total'])
            ->currency($sum['Currency']);

        return true;
    }

    private function parseEmail(Email $email)
    {
        $this->logger->notice(__METHOD__);

        // flights
        $xpath = "//text()[{$this->starts($this->t('Flight Details'))}]/ancestor::*[{$this->contains($this->t('Order Number'))}][1]";
        $nodes = $this->http->XPath->query($xpath);
        $this->logger->debug("[xpath-flight]: " . $xpath);

        if ($nodes->length > 0) {
            $this->parseFlight($nodes, $email);
        }

        // rental
        $xpath = "//text()[{$this->starts($this->t('Pick-Up at'))}]/ancestor::table[./following-sibling::table[normalize-space()][position()<=2][{$this->contains($this->t('Drop-Off at'))}]]";
        $nodes = $this->http->XPath->query($xpath);
        $this->logger->debug("[xpath-rental]: " . $xpath);

        if ($nodes->length > 0) {
            $this->parseRental($nodes, $email);
        }

        // hotel
        $xpath = "//text()[{$this->starts($this->t('Hotel Confirmation'))}]/ancestor::table[{$this->contains($this->t('Order Number'))}][1][{$this->starts($this->t('Arrive'))}]";
        $nodes = $this->http->XPath->query($xpath);
        $this->logger->debug("[xpath-hotel]: " . $xpath);

        if ($nodes->length > 0) {
            $this->parseHotel($nodes, $email);
        }

        return true;
    }

    private function parseFlight(\DOMNodeList $roots, Email $email)
    {
        $airs = [];
        $mainStatus = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your order is'))}]/following::text()[normalize-space()!=''][1]");

        if ($cancelledOrder =
            $this->http->FindSingleNode("//text()[({$this->starts($this->t('Order #'))}) and ({$this->contains($this->t('has been cancelled'))})]",
                null, false,
                "/{$this->opt($this->t('Order #'))}\s*([A-Z\d]+)\s+{$this->opt($this->t('has been cancelled'))}/")
        ) {
            foreach ($roots as $root) {
                $pnr = $this->http->FindSingleNode("./descendant::td[{$this->eq($this->t('Airline Confirmation'))}]/following-sibling::td[normalize-space()!=''][1]",
                    $root);
                $order = $this->http->FindSingleNode("./descendant::td[{$this->eq($this->t('Order Number'))}]/following-sibling::td[1]",
                    $root);

                if ($order !== $cancelledOrder) {
                    if (!empty($mainStatus)) {
                        $airs[$mainStatus][$pnr][] = $root;
                    } else {
                        $airs['other'][$pnr][] = $root;
                    }
                } else {
                    $airs['cancelled'][$pnr][] = $root;
                }
            }
        } else {
            if (!empty($this->http->FindNodes("//text()[({$this->starts($this->t('Order #'))}) and ({$this->contains($this->t('has been cancelled'))})]"))) {
                // break the parsing
                $email->add()->flight();
                $this->logger->debug("check format");

                return false;
            }

            foreach ($roots as $root) {
                $pnr = $this->http->FindSingleNode("./descendant::td[{$this->eq($this->t('Airline Confirmation'))}]/following-sibling::td[normalize-space()!=''][1]",
                    $root);

                if (!empty($mainStatus)) {
                    $airs[$mainStatus][$pnr][] = $root;
                } else {
                    $airs['other'][$pnr][] = $root;
                }
            }
        }

        foreach ($airs as $status => $flights) {
            foreach ($flights as $pnr => $roots) {
                $r = $email->add()->flight();
                $r->general()
                    ->confirmation($pnr);

                if ($status === 'cancelled') {
                    $r->general()
                        ->status($status)
                        ->cancelled();
                } elseif ($status !== 'other') {
                    $r->general()->status($status);
                }
                $travellers = [];
                $tickets = [];
                $orders = [];
                $lastDate = null;

                foreach ($roots as $root) {
                    $date = strtotime($this->http->FindSingleNode("./preceding::table[normalize-space()!=''][1][count(.//text()[normalize-space()!=''])=1]",
                        $root));

                    if (!$date && $lastDate) {
                        $date = $lastDate;
                    } else {
                        $lastDate = $date;
                    }

                    $travellers = array_merge($travellers,
                        $this->http->FindNodes("./descendant::td[{$this->eq($this->t('Travelers'))}]/following-sibling::td[1]/*[normalize-space()!='']",
                            $root));
                    $tickets = array_merge($tickets,
                        $this->http->FindNodes("./descendant::td[{$this->eq($this->t('Travelers'))}]/following-sibling::td[1]/*[normalize-space()!='']/following::text()[normalize-space()!=''][{$this->starts($this->t('Ticket'))}]",
                            $root, "/{$this->opt($this->t('Ticket'))}\s*(\d+)$/"));
                    $orders[] =
                        $this->http->FindSingleNode("./descendant::td[{$this->eq($this->t('Order Number'))}]/following-sibling::td[1]",
                            $root);

                    $s = $r->addSegment();

                    $time = $this->http->FindSingleNode("./descendant::td[{$this->starts($this->t('Depart'))}]/preceding-sibling::td[1]",
                        $root);
                    $s->departure()->date(strtotime($time, $date));
                    $node = $this->http->FindSingleNode("./descendant::td[{$this->starts($this->t('Depart'))}]",
                        $root);

                    if (preg_match("/{$this->opt($this->t('Depart'))}\s+(.+)\s+\(([A-Z]{3})\)$/", $node, $m)) {
                        $s->departure()
                            ->name($m[1])
                            ->code($m[2]);
                    }

                    $time = $this->http->FindSingleNode("./descendant::td[{$this->starts($this->t('Arrive'))}]/preceding-sibling::td[1]",
                        $root);
                    $s->arrival()->date(strtotime($time, $date));
                    $node = $this->http->FindSingleNode("./descendant::td[{$this->starts($this->t('Arrive'))}]",
                        $root);

                    if (preg_match("/{$this->opt($this->t('Arrive'))}\s+(.+)\s+\(([A-Z]{3})\)$/", $node, $m)) {
                        $s->arrival()
                            ->name($m[1])
                            ->code($m[2]);
                    }

                    $node = $this->http->FindSingleNode("./descendant::td[{$this->eq($this->t('Flight Details'))}]/following-sibling::td[1]/descendant::text()[1]",
                        $root);

                    if (preg_match("/.+? ([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$/", $node, $m)) {
                        $s->airline()
                            ->name($m[1])
                            ->number($m[2]);
                    }

                    $bclass = $this->http->FindSingleNode("./descendant::td[{$this->eq($this->t('Flight Details'))}]/following-sibling::td[1]/descendant::text()[{$this->starts($this->t('Booking Class'))}]",
                        $root, false, "/{$this->opt($this->t('Booking Class'))}\s*\-\s*([A-Z]{1,2})$/");
                    $aircraft = $this->http->FindSingleNode("./descendant::td[{$this->eq($this->t('Flight Details'))}]/following-sibling::td[1]/descendant::text()[{$this->starts($this->t('Plane Type'))}]",
                        $root, false, "/{$this->opt($this->t('Plane Type'))}\s*\-\s*(.+)$/");
                    $s->extra()
                        ->bookingCode($bclass)
                        ->aircraft($aircraft);

                    $seats = array_filter($this->http->FindNodes("./descendant::td[{$this->eq($this->t('Travelers'))}]/following-sibling::td[1]/descendant::text()[{$this->contains($this->t('Seat'))}]",
                        $root, "/{$this->opt($this->t('Seat'))}\s*(\d+[A-z])$/"));

                    if (!empty($seats)) {
                        $s->extra()->seats($seats);
                    }
                }
                $travellers = array_filter(array_unique($travellers));

                if (!empty($travellers)) {
                    $r->general()->travellers($travellers, true);
                }
                $tickets = array_filter(array_unique($tickets));

                if (!empty($tickets)) {
                    $r->issued()->tickets($tickets, false);
                }
                $orders = array_filter(array_unique($orders));

                if (!empty($orders)) {
                    foreach ($orders as $order) {
                        $r->ota()->confirmation($order);
                    }
                }
            }
        }
    }

    private function parseRental(\DOMNodeList $roots, Email $email)
    {
        $rentals = [];
        $mainStatus = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your order is'))}]/following::text()[normalize-space()!=''][1]");

        if ($cancelledOrder =
            $this->http->FindSingleNode("//text()[({$this->starts($this->t('Order #'))}) and ({$this->contains($this->t('has been cancelled'))})]",
                null, false,
                "/{$this->opt($this->t('Order #'))}\s*([A-Z\d]+)\s+{$this->opt($this->t('has been cancelled'))}/")
        ) {
            foreach ($roots as $root) {
                $order = $this->http->FindSingleNode("./descendant::td[{$this->eq($this->t('Order Number'))}]/following-sibling::td[1]",
                    $root);

                if ($order !== $cancelledOrder) {
                    if (!empty($mainStatus)) {
                        $rentals[$mainStatus][] = $root;
                    } else {
                        $rentals['other'][] = $root;
                    }
                } else {
                    $rentals['cancelled'][] = $root;
                }
            }
        } else {
            if (!empty($this->http->FindNodes("//text()[({$this->starts($this->t('Order #'))}) and ({$this->contains($this->t('has been cancelled'))})]"))) {
                // break the parsing
                $email->add()->rental();
                $this->logger->debug("check format");

                return false;
            }

            foreach ($roots as $root) {
                if (!empty($mainStatus)) {
                    $rentals[$mainStatus][] = $root;
                } else {
                    $rentals['other'][] = $root;
                }
            }
        }

        foreach ($rentals as $status => $roots) {
            foreach ($roots as $root) {
                // pickup
                $date = strtotime($this->http->FindSingleNode("./preceding::table[normalize-space()!=''][1][count(.//text()[normalize-space()!=''])=1]",
                    $root));
                $r = $email->add()->rental();

                if ($status === 'cancelled') {
                    $r->general()
                        ->status($status)
                        ->cancelled();
                } elseif ($status !== 'other') {
                    $r->general()->status($status);
                }

                $r->general()
                    ->confirmation($confNo = $this->http->FindSingleNode("./descendant::td[{$this->eq($this->t('Car Confirmation'))}]/following-sibling::td[normalize-space()!=''][1]",
                        $root));
                $travellers = $this->http->FindNodes("./descendant::td[{$this->eq($this->t('Travelers'))}]/following-sibling::td[normalize-space()!=''][1]/descendant::text()[normalize-space()!='']",
                    $root);

                if (!empty($travellers)) {
                    $r->general()->travellers($travellers);
                }

                $r->ota()
                    ->confirmation($this->http->FindSingleNode("./descendant::td[{$this->eq($this->t('Order Number'))}]/following-sibling::td[normalize-space()!=''][1]",
                        $root));

                $time = $this->http->FindSingleNode("./descendant::tr[{$this->starts($this->t('Pick-Up at'))}]/following-sibling::tr[1]/td[normalize-space()!=''][1]",
                    $root);
                $r->pickup()
                    ->date(strtotime($time, $date))
                    ->location($this->http->FindSingleNode("./descendant::tr[{$this->starts($this->t('Pick-Up at'))}]/following-sibling::tr[1]/td[normalize-space()!=''][2]/descendant::text()[normalize-space()!=''][2]",
                        $root))
                    ->phone($this->http->FindSingleNode("./descendant::tr[{$this->starts($this->t('Pick-Up at'))}]/following-sibling::tr[1]/td[normalize-space()!=''][2]/descendant::text()[normalize-space()!=''][3]",
                        $root));

                // dropoff
                $rootDrop = $this->http->XPath->query("./following-sibling::table[normalize-space()!=''][position()<=2][./descendant::td[normalize-space()='{$confNo}']]",
                    $root);

                if ($rootDrop->length !== 1) {
                    $this->logger->debug("can't find drop-off block");

                    return false;
                }
                $rootDrop = $rootDrop->item(0);

                $dateDrop = strtotime($this->http->FindSingleNode("./preceding::table[normalize-space()!=''][1][count(.//text()[normalize-space()!=''])=1]",
                    $rootDrop));

                if ($dateDrop) {
                    $date = $dateDrop;
                }

                $time = $this->http->FindSingleNode("./descendant::tr[{$this->starts($this->t('Drop-Off at'))}]/following-sibling::tr[1]/td[normalize-space()!=''][1]",
                    $rootDrop);
                $r->dropoff()
                    ->date(strtotime($time, $date))
                    ->location($this->http->FindSingleNode("./descendant::tr[{$this->starts($this->t('Drop-Off at'))}]/following-sibling::tr[1]/td[normalize-space()!=''][2]/descendant::text()[normalize-space()!=''][2]",
                        $rootDrop))
                    ->phone($this->http->FindSingleNode("./descendant::tr[{$this->starts($this->t('Drop-Off at'))}]/following-sibling::tr[1]/td[normalize-space()!=''][2]/descendant::text()[normalize-space()!=''][3]",
                        $rootDrop));
            }
        }

        return true;
    }

    private function parseHotel(\DOMNodeList $roots, Email $email)
    {
        $hotels = [];
        $mainStatus = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your order is'))}]/following::text()[normalize-space()!=''][1]");

        if ($cancelledOrder =
            $this->http->FindSingleNode("//text()[({$this->starts($this->t('Order #'))}) and ({$this->contains($this->t('has been cancelled'))})]",
                null, false,
                "/{$this->opt($this->t('Order #'))}\s*([A-Z\d]+)\s+{$this->opt($this->t('has been cancelled'))}/")
        ) {
            foreach ($roots as $root) {
                $order = $this->http->FindSingleNode("./descendant::td[{$this->eq($this->t('Order Number'))}]/following-sibling::td[1]",
                    $root);

                if ($order !== $cancelledOrder) {
                    if (!empty($mainStatus)) {
                        $hotels[$mainStatus][] = $root;
                    } else {
                        $hotels['other'][] = $root;
                    }
                } else {
                    $hotels['cancelled'][] = $root;
                }
            }
        } else {
            if (!empty($this->http->FindNodes("//text()[({$this->starts($this->t('Order #'))}) and ({$this->contains($this->t('has been cancelled'))})]"))) {
                // break the parsing
                $email->add()->hotel();
                $this->logger->debug("check format");

                return false;
            }

            foreach ($roots as $root) {
                if (!empty($mainStatus)) {
                    $hotels[$mainStatus][] = $root;
                } else {
                    $hotels['other'][] = $root;
                }
            }
        }

        foreach ($hotels as $status => $roots) {
            foreach ($roots as $root) {
                // in
                $date = strtotime($this->http->FindSingleNode("./preceding::table[normalize-space()!=''][1][count(.//text()[normalize-space()!=''])=1]",
                    $root));
                $r = $email->add()->hotel();

                if ($status === 'cancelled') {
                    $r->general()
                        ->status($status)
                        ->cancelled();
                } elseif ($status !== 'other') {
                    $r->general()->status($status);
                }

                $r->general()
                    ->confirmation($confNo = $this->http->FindSingleNode("./descendant::td[{$this->eq($this->t('Hotel Confirmation'))}]/following-sibling::td[normalize-space()!=''][1]",
                        $root));

                $travellers = $this->http->FindNodes("./descendant::td[{$this->eq($this->t('Travelers'))}]/following-sibling::td[normalize-space()!=''][1]/descendant::text()[normalize-space()!='']",
                    $root);

                if (!empty($travellers)) {
                    $r->general()->travellers($travellers);
                }

                $r->ota()
                    ->confirmation($this->http->FindSingleNode("./descendant::td[{$this->eq($this->t('Order Number'))}]/following-sibling::td[normalize-space()!=''][1]",
                        $root));

                $time = $this->http->FindSingleNode("./descendant::tr[{$this->starts($this->t('Arrive'))}]/following-sibling::tr[1]/td[normalize-space()!=''][1]",
                    $root);
                $r->booked()
                    ->checkIn(strtotime($time, $date));
                $r->hotel()
                    ->name($this->http->FindSingleNode("./descendant::tr[{$this->starts($this->t('Arrive'))}]/following-sibling::tr[1]/td[normalize-space()!=''][2]/descendant::text()[normalize-space()!=''][1]",
                        $root))
                    ->address($this->http->FindSingleNode("./descendant::tr[{$this->starts($this->t('Arrive'))}]/following-sibling::tr[1]/td[normalize-space()!=''][2]/descendant::text()[normalize-space()!=''][2]",
                        $root))
                    ->phone($this->http->FindSingleNode("./descendant::tr[{$this->starts($this->t('Arrive'))}]/following-sibling::tr[1]/td[normalize-space()!=''][2]/descendant::text()[normalize-space()!=''][3]",
                        $root), false, true);

                // out
                $rootOut = $this->http->XPath->query("./following-sibling::table[normalize-space()!=''][2][./descendant::td[normalize-space()='{$confNo}']]",
                    $root);

                if ($rootOut->length !== 1) {
                    $this->logger->debug("can't find checkout block");

                    return false;
                }
                $rootOut = $rootOut->item(0);

                $date = strtotime($this->http->FindSingleNode("./preceding::table[normalize-space()!=''][1][count(.//text()[normalize-space()!=''])=1]",
                    $rootOut));

                $time = $this->http->FindSingleNode("./descendant::tr[{$this->starts($this->t('Depart'))}]/following-sibling::tr[1]/td[normalize-space()!=''][1]",
                    $rootOut);
                $r->booked()
                    ->checkOut(strtotime($time, $date));
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
//        $year = date('Y', $this->date);
        $in = [
            //10/11/2019 Fri Oct 11   |   10/11/2019 Friday, Oct 11
            '/^(\d+)\/(\d+)\/(\d{4}) ([\w\-]+),? (\w+) (\d+)\s*$/u',
        ];
        $out = [
            '$6 $5 $3',
        ];
        $outWeek = [
            '$4',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody($body)
    {
        if (isset($this->reBodyPdf)) {
            foreach ($this->reBodyPdf as $lang => $reBody) {
                $reBody = (array) $reBody;

                foreach ($reBody as $re) {
                    if (stripos($body, $re) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Order Number'], $words['Travelers'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Order Number'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Travelers'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
            // cancelled|changed without travelers
            if (isset($words['Order Number'], $words['Hotel Confirmation'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Order Number'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Hotel Confirmation'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLangText($body)
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['ORDER #'], $words['RECEIPT #'])) {
                if (stripos($body, $words['ORDER #']) !== false && stripos($body, $words['RECEIPT #']) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('/[[:alpha:]]+/iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("/$monthNameOriginal/i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace(["€", "£", "$", "₹"], ["EUR", "GBP", "USD", "INR"], $node);
        $tot = '';
        $cur = '';

        if (preg_match("/(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)/", $node, $m)
            || preg_match("/(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})/", $node, $m)
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
            return str_replace(' ', '\s+', preg_quote($s));
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
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function colsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (!isset($prev) || $prev < 0) {
                $prev = $i - 1;
            }

            if (isset($pos[$i], $pos[$prev])) {
                if ($pos[$i] - $pos[$prev] < $correct) {
                    unset($pos[$i]);
                } else {
                    $prev = $i;
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }
}
