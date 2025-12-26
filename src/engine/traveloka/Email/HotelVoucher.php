<?php

namespace AwardWallet\Engine\traveloka\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class HotelVoucher extends \TAccountChecker
{
    public $mailFiles = "traveloka/it-27492223.eml, traveloka/it-49794256.eml, traveloka/it-63453969.eml, traveloka/it-63506328.eml, traveloka/it-65560465.eml, traveloka/it-67930981.eml, traveloka/it-69027134.eml, traveloka/it-70474872.eml";

    public $reFrom = ["booking@traveloka.combooking@traveloka.com"];
    public $reBody = [
        'en'  => ['Booking details', 'Check-in'],
        'en2' => ['Stay Details', 'Check-in'],
        'id'  => ['Detail Pesanan', 'Check-in'],
        'id2' => ['DETAIL PEMBAYARAN', 'Check-in'],
        'ms'  => ['Butiran tempahan', 'Daftar masuk'],
        'ms2' => ['Butiran Pelanggan', 'JUMLAH BAYARAN'], //pdf
    ];
    public $reSubject = [
        '#voucher from Traveloka#',
        '#\[Traveloka\] Your Voucher at .+? - Booking ID \d+#',
    ];
    public $lang = '';
    public $otaConfirmationFlag = false;
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            // COMMON
            // 'Phone'                => '',
            // 'Check-in'             => '',
            // 'Check-out'            => '',
            // 'Total'                => '',
            // 'ADMINISTRATIVE COSTS' => '',

            // HTML
            'Booking details' => ['Booking details', 'Stay Details', 'Booking Details'],
            'Booking ID:'     => ['Booking ID:', 'Itinerary ID:'],
            'Guest per room'  => ['Guest per room', 'Guest(s) per Room', 'Guest(s) per Room'],
            'Number of rooms' => ['Number of rooms', 'Number of Rooms', 'No. of Rooms'],
            'Room type'       => ['Room type', 'Room Type', 'Room Name', 'Unit details'],

            // PDF
            'Hotel cancellation policy' => ['Hotel cancellation policy', 'Property Cancellation Policy'],
            //            'Price Details' => '',
            //            'BOOKING ID' => '',
            'P.O. NUMBER'    => 'P.O. NUMBER',
            'PAYMENT AMOUNT' => 'PAYMENT AMOUNT',
            //            'Room Name' => '', // type room in one row
            //            'Guest(s)' => '', // type room in one row
        ],
        'id' => [
            // COMMON
            'Phone'                => 'Telepon',
            'Check-in'             => 'Check-in',
            'Check-out'            => 'Check-out',
            'Total'                => 'TOTAL',
            'ADMINISTRATIVE COSTS' => 'BIAYA ADMINISTRASI',

            // HTML
            'Traveloka Booking ID'                         => 'No. Pesanan Traveloka',
            'Booking details'                              => ['Detail Pesanan'],
            'Your hotel reservation has been successfully' => 'Reservasi hotel Anda telah sukses',
            'Guest Name:'                                  => 'Nama Tamu:',
            'Booking ID:'                                  => 'No. Pesanan:',
            'Room Capacity'                                => ['Kapasitas Kamar', 'Tamu per Kamar', 'Tamu per Unit'],
            'Guest per room'                               => ['Tamu per kamar', 'tamu/kamar', 'Dewasa'],
            'Number of rooms'                              => ['Jumlah kamar', 'Jumlah Kamar', 'Jumlah Unit'],
            'Room'                                         => ['Kamar', 'Unit'],
            'Room type'                                    => ['Tipe kamar', 'Tipe Kamar', 'Nama Kamar', 'Nama Unit'],

            // PDF
            'Order Number Traveloka'    => ['No. Pesanan'],
            'Guest'                     => ['Tamu'],
            'Hotel cancellation policy' => 'Kebijakan Pembatalan Hotel',
            'Includes'                  => 'Termasuk',
            'Customer Service'          => 'UNTUK PERTANYAAN APA PUN, KUNJUNGI TRAVELOKA HELP CENTER',
            //            'Price Details' => '',
            'BOOKING ID'     => 'NO. PESANAN',
            'P.O. NUMBER'    => 'P.O. NUMBER',
            'Total'          => 'Total',
            'PAYMENT AMOUNT' => 'JUMLAH PEMBAYARAN',
            'Room Name'      => 'Nama Kamar', // type room in one row
            'Guest(s)'       => 'Tamu', // type room in one row
        ],
        'ms' => [
            // COMMON
            'Phone'                => 'Telefon',
            'Check-in'             => 'Daftar masuk',
            'Check-out'            => 'Daftar keluar',
            'Total'                => 'JUMLAH',
            'ADMINISTRATIVE COSTS' => 'JUMLAH BAYARAN',

            // HTML
            'Traveloka Booking ID'                         => 'ID Tempahan Traveloka',
            'Booking details'                              => ['Butiran tempahan'],
            'Your hotel reservation has been successfully' => 'Tempahan hotel anda telah berjaya disahkan.',
            'Guest Name:'                                  => 'Nama Tetamu:',
            'Booking ID:'                                  => 'ID Tempahan:',
            'Room Capacity'                                => ['Tetamu SeBilik'],
            'Guest per room'                               => ['Dewasa'],
            'Number of rooms'                              => ['Bilangan Bilik'],
            'Room'                                         => 'Bilik',
            'Room type'                                    => ['Nama Bilik'],

            // PDF
            'Itinerary ID'           => ['Itinerary ID', 'ID Jadual Perjalanan'],
            'Order Number Traveloka' => ['ID TEMPAHAN TRAVELOKA'],
            'Hotel Voucher'          => ['Baucar Hotel'],
            //'Guest' => ['Tamu'],
            'Hotel cancellation policy' => 'Polisi pembatalan hotel',
            'Includes'                  => 'Termasuk',
            'Customer Service'          => 'KHIDMAT PELANGGAN',
            //            'Price Details' => '',
            'BOOKING ID'     => 'ID TEMPAHAN TRAVELOKA',
            'P.O. NUMBER'    => 'NOMBOR P.O.',
            'Total'          => 'Jumlah',
            'PAYMENT AMOUNT' => 'JUMLAH BAYARAN',

            'Room Name' => 'Nama Bilik', // type room in one row
            'Guest(s)'  => 'Tetamu', // type room in one row
        ],
    ];
    private $pdfText = '';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parseAir($email);
        $parsed = false;
        $type = 'Pdf';
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $i => $pdf) {
                $receiptDetect = [];

                foreach (self::$dict as $lang => $d) {
                    $receiptDetect[$lang] = array_filter([$d["P.O. NUMBER"] ?? null, $d["PAYMENT AMOUNT"] ?? null]);
                }

                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->assignLang($text, $this->reBody) && empty($this->strstrArray($text, $this->t('PAYMENT AMOUNT')))) {
                        $this->pdfText .= $text;
                        $result = $this->parseEmailPdf($text, $email);

                        if ($result === null) {
                            $this->logger->debug('can\'t parse attach-' . $i);

                            continue;
                        } elseif ($result === false) {
                            $email->clearItineraries();
                            $email->removeTravelAgency();

                            break;
                        } else {
                            $parsed = true;
                        }
                    } elseif (
                        (!empty($this->lang) && stripos($text, 'P.O. NUMBER') !== false && stripos($text, 'PAYMENT AMOUNT') !== false)
                        || $this->assignLang($text, $receiptDetect)
                    ) {
                        $receiptText = $text;
                    } else {
                        $this->logger->debug('can\'t determine a language by attach-' . $i);
                    }
                }
            }
        }

        if (!$parsed) {
            if (!$this->assignLang($this->http->Response['body'], $this->reBody)) {
                $this->logger->debug('can\'t determine a language by Body');
            } else {
                $this->parseEmail($email);
                $type = 'Html';
            }
        }

        if (!empty($receiptText) && $email->getTravelAgency()) {
            $confs = $email->getTravelAgency()->getConfirmationNumbers();

            if (!empty($confs) && preg_match("#" . $this->opt($this->t('P.O. NUMBER')) . "[ ]*:[ ]*" . $this->opt(array_column($confs, 0)) . "\b#", $receiptText)) {
                $total = $this->re("#\n[ ]{30,}{$this->opt($this->t("PAYMENT AMOUNT"))}[ ]*(\d[\d., ]*)\n#", $receiptText)
                    ?? str_replace('.', '', end(preg_split('/\s{2,}/', $this->re("/{$this->opt($this->t('Flight Ticket'))}.+\s+\d+\.\d{3}\s+(\d+\.\d{3})/i", $receiptText))))
                ;
                $currency = $this->currency($this->re("#\n[ ]*\S+.*[ ]{3,}{$this->opt($this->t("Total"))}[ ]*([^\d\s]{1,5})\n#", $receiptText));
                $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;

                if (empty($currencyCode)) {
                    $currency = $this->currency($this->re("/{$this->opt($this->t('Price per unit'))}\s*(\S{3})\s+/", $receiptText));
                    $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                }

                if ($total !== null) {
                    $email->price()->total(PriceHelper::parse($total, $currencyCode))->currency($currency);
                }
            }
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . $type . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, 'traveloka.com')]")->length > 0) {
            if ($this->assignLang($this->http->Response['body'], $this->reBody)) {
                return true;
            }
        }
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ((stripos($text, 'traveloka') !== false)
                && $this->assignLang($text, $this->reBody)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            $flag = false;

            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers['from'], $reFrom) !== false) {
                    $flag = true;
                }
            }

            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"]) && ($flag || stripos($reSubject,
                            'traveloka') !== false)
                ) {
                    return true;
                }
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

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $formats = 2; // html | pdf
        $cnt = $formats * count(self::$dict);

        return $cnt;
    }

    /**
     * @return bool|null If parsed then true, if parsed failed then false, if can't determine format then null
     */
    private function parseEmailPdf($textPDF, Email $email): ?bool
    {
        $this->logger->debug('Used method: ' . __FUNCTION__);

        $infoBlock = '';
        $bookingDetailsTrans = (array) $this->t('Booking details');

        foreach ($bookingDetailsTrans as $bdt) {
            $bktext = stristr($textPDF, $bdt, true);

            if (empty($bktext)) {
                continue;
            }

            $beforePrice = $this->strstrArray($bktext, $this->t('Price Details'), true);

            if (!empty($beforePrice)) {
                $bktext = $beforePrice;
            }

            $bookingDetailsLable = $bdt;
            $infoBlock = $this->strstrArray($bktext, $this->t('Itinerary ID'));

            if (!empty($infoBlock)) {
                break;
            }
        }

        if (empty($infoBlock)) {
            return null;
        }
        $detailsBlock = stristr($textPDF, $bookingDetailsLable);
        $detailsBlock = preg_replace("/\n *" . $this->opt($this->t('Customer Service')) . ".+[\s\S]+\n *" . $this->opt($this->t("Hotel Voucher")) . "\s*\n/", '', $detailsBlock);

        if (empty($detailsBlock)) {
            return null;
        }

        $email->obtainTravelAgency();
        $conf = $this->re("#cs@traveloka.com {3,}(\d+)\n#", $textPDF)
            ?? $this->re("#[ ]{3,}{$this->opt($this->t('BOOKING ID'))}\s*\n.{50,}[ ]{3,}(\d+)\n#", $textPDF)
            ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('Traveloka Booking ID'))}]/following::text()[normalize-space()][1]", null, true, '/^[-A-Z\d]{5,}$/')
            ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t('Traveloka Booking ID'))}]", null, true, "/^{$this->opt($this->t('Traveloka Booking ID'))}[:\s]+([-A-Z\d]{5,})$/")
        ;

        if (!empty($conf)) {
            $email->ota()->confirmation($conf);
            $this->otaConfirmationFlag = true;
        }

        $table = $this->strstrArray($infoBlock, $this->t('Check-in'), true);
        $table = $this->splitCols($table, $this->colsPos($table));

        if (count($table) !== 2) {
            $this->logger->debug('other format pdf: hotel info');

            return false;
        }

        $r = $email->add()->hotel();
        $confirmation = $this->re("#^[ ]*{$this->opt($this->t('Itinerary ID'))}\b[\s\S]*?\n+[ ]*([A-Z\d]*\d[A-Z\d]*)$#m", $table[0])
            ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking ID:'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]*\d[A-Z\d]*$/')
        ;

        if (!empty($confirmation) && strlen($confirmation) > 2) {
            $r->general()
                ->confirmation($confirmation);
        }

        $hotelName = $address = $phone = null;

        if (preg_match("#^\s*(?<name>.{3,})(?<address>(?:\n+.{2,}){1,6}?)\n+.*{$this->opt($this->t("Phone"))}.*?(?<phone>[+(\d][-+. \d)(]{5,}[\d)])(?:[ ]*\/|\n|$)#", $table[1], $m)) {
            $hotelName = preg_replace('/^(.{2,}?)[ ]*\/[ ]*.{2,}$/', '$1', $m['name']);
            $m['address'] = preg_replace("/^\s*((\S{2,}) [\s\S]*)\n+[ ]*\\2[\s\S]*$/", '$1', $m['address']); // it-27492223.eml
            $address = preg_replace('/\s+/', ' ', trim($m['address']));
            $phone = $m['phone'];
        }

        $table = $this->re("#( +{$this->opt($this->t('Check-in'))}.+)#s", $infoBlock);
        $table = $this->splitCols($table, $this->colsPos($this->inOneRow($table)));

        if (count($table) !== 2 && count($table) !== 3) {
            $this->logger->debug('other format pdf: check-in info');

            return false;
        }

        if (count($table) === 3 && $this->re("#({$this->opt($this->t('Check-in'))})#", $table[1])) {
            unset($table[0]);
            $table = array_values($table);
        } elseif (count($table) === 3) {
            $this->logger->debug('other format pdf: check-in info 2');

            return false;
        }

        if (preg_match("#{$this->opt($this->t('Check-in'))}.*\n\s*(.+?\d{4}).*\n+(([\d\:]+\s*A?a?P?p?M?m?))#", $table[0], $m)
                && ($d = strtotime($m[1] . ', ' . $m[2]))) {
            $r->booked()
                ->checkIn($d);
        } else {
            $this->logger->debug('empty check-in date');

            return false;
        }

        if (preg_match("#{$this->opt($this->t('Check-out'))}.*\n\s*(.+?\d{4}).*\n+(([\d\:]+\s*A?a?P?p?M?m?))#", $table[1], $m)) {
            $r->booked()
                ->checkOut(strtotime($m[1] . ', ' . $m[2]));
        }

        if ($passengers = $this->http->FindNodes("//h3[normalize-space(.)='Traveler(s)']/following-sibling::*[1]/li/text()[normalize-space(.)][1]")) {
            $r->general()
                ->travellers($passengers);
        } else {
            if (!empty($passenger = $this->re("#\n +{$this->opt($this->t('Guest'))}(?:\(s\))? (?:\/.*?)? {3,}(.+)#", $detailsBlock))) {
                $r->general()
                    ->traveller($passenger);
            }
        }

        if (preg_match("/{$this->opt($this->t('Booking details'))}\b(?:\D*)?\s*\n\s*No[. ]+\D+/isu", $detailsBlock)) {
            // remove garbage
            $detailsBlock = preg_replace("/\n[ ]*{$this->opt($this->t('Includes'))}(?:[ ]*\/.+|[ ]{2}.+|\n).*/s", '', $detailsBlock);
            $detailsBlock = preg_replace("/\n[ ]*{$this->opt($this->t('Number of rooms'))}(?:[ ]*\/.+|[ ]{2}.+|\n).*/s", '', $detailsBlock);

            // remove footnotes
            $detailsBlock = preg_replace("/\n\n[ ]{0,8}[*]+.+/s", '', $detailsBlock);

            // correcting headers
            $detailsBlock = preg_replace("/(\n[ ]*No)[. ](\D+?)[ ]{2}/", '$1  $2 ', $detailsBlock);

            $roomsText = $this->split("/\n([ ]{0,5}\d+ +)/", $detailsBlock);
            $detailsTableHeadPos = $this->rowColsPos($this->re("#\n([ ]*No[. ]+\D+)#", $detailsBlock));

            $travellers = [];
            $guests = $kids = null;

            foreach ($roomsText as $roomText) {
                $roomTable = $this->splitCols($roomText, $detailsTableHeadPos);

                if (!empty($roomTable[1])) {
                    $room = $r->addRoom();
                    $room->setType($roomTable[1]);

                    if (preg_match("#^{$this->opt(trim($roomTable[1]))}\s+(.{3,})$#u", $address, $m)) {
                        $address = $m[1];
                    }
                }

                $travellers[] = $roomTable[2] ?? null;
                $guests = preg_match("/^\s*(\d+)\s+/", $roomTable[3], $m) ? $guests + (int) $m[1] : null;

                if (preg_match("/^\s*\d+\s+\D+,\s+(\d+)\s+/", $roomTable[3], $m)) {
                    $kids += $m[1];
                }
            }
            $r->general()
                    ->travellers(array_filter(array_unique($travellers)));

            $r->booked()
                    ->rooms(count($roomsText))
                    ->guests($guests)
                    ->kids($kids, false, true)
                ;
        } else {
            $guests = preg_match("#{$this->opt($this->t('Guest per room'))}(?:[ ]+\/[ ]+\D+?)?[ ]{2,}(\d{1,3})\b#i", $detailsBlock, $m)
                ? ($r->getRoomsCount() ?? 1) * $m[1] : null
            ;
            $r->booked()
                ->rooms($this->re("#{$this->opt($this->t('Number of rooms'))}(?:[ ]+\/[ ]+\D+?)?[ ]{2,}(\d{1,3})\b#i", $detailsBlock))
                ->guests($guests, false, true)
            ;

            $roomType = $this->re("#{$this->opt($this->t('Room type'))}(?:[ ]+\/[ ]+\D+?)?[ ]{2,}(.{2,}?)(?:[ ]+\/[ ]+.{2,})?(?:\n|$)#", $detailsBlock);

            $room = $r->addRoom();
            $room->setType($roomType)
//                ->setDescription($this->re("#{$this->opt($this->t('Special request'))}[ ]+(.+)#", $detailsBlock), true, true)
            ;
        }

        $r->hotel()->name($hotelName)->address($address)->phone($phone);

        $r->general()->cancellation($this->nice($this->re("#{$this->opt($this->t("Hotel cancellation policy"))}(?:[ ]+\/[ ]+[^\n]{2,})?\n+[ ]*(.+?)\n\n#is", $textPDF)), true);

        if (!empty($node = $r->getCancellation())) {
            $this->detectDeadLine($r, $node);
        }

        return true;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText)
    {
        //Here we describe various variations in the definition of dates deadLine
        if (
            preg_match("#Free cancellation before (\d+)\-(\D+)\-(\d{4}) (\d+:\d+)\.#i", $cancellationText, $m)
            || preg_match("#Gratis biaya pembatalan sebelum tanggal (\d+)\-(\D+)\-(\d{4}) (\d+:\d+)\.#ui", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline(strtotime($this->normalizeDate($m[1] . ' ' . $m[2] . ' ' . $m[3] . ', ' . $m[4])));
        }

        if (
            preg_match("#Free cancellation before (\d+)\s+(\w+)\s+(\d{4})\s+([\d\:]+)\.#i", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline2($m[1] . ' ' . $m[2] . ' ' . $m[3] . ', ' . $m[4]);
        }

        if (
               preg_match("#This reservation is non-refundable#i", $cancellationText, $m)
            || preg_match("#Tempahan ini tidak boleh dibayar balik\.#i", $cancellationText, $m)
            || preg_match("#Pemesanan ini tidak bisa di-refund\.#i", $cancellationText, $m)
        ) {
            $h->booked()
                ->nonRefundable();
        }
    }

    private function parseEmail(Email $email): void
    {
        $this->logger->debug('Used method: ' . __FUNCTION__);
//        $this->logger->alert('here');

        $email->obtainTravelAgency();

        $otaConf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Traveloka Booking ID'))}]/following::text()[normalize-space()!=''][1]");

        if (empty($otaConf)) {
            $otaConf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Traveloka Booking ID'))}]", null, true, "/{$this->opt($this->t('Traveloka Booking ID'))}\:?\s*([\d]+)/");
        }

        if (!empty($otaConf)) {
            $email->ota()
                ->confirmation($otaConf, $this->t('Traveloka Booking ID'));
            $this->otaConfirmationFlag = true;
        }

        $phones = [];
        $nodes = $this->http->XPath->query("//text()[{$this->contains($this->t('Customer Service'))}]//ancestor::tr[1]");

        foreach ($nodes as $root) {
            $node = $this->http->FindSingleNode(".", $root);
            $phone = $this->http->FindSingleNode("./following-sibling::tr[1]", $root);

            if (preg_match("#{$this->opt($this->t('Customer Service'))}#", $node) && !in_array($phone, $phones)) {
                $email->ota()->phone($phone, $node);
                $phones[] = $phone;
            }
        }
        $r = $email->add()->hotel();
        $status = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your hotel reservation has been successfully'))}]",
            null, false, "#{$this->opt($this->t('Your hotel reservation has been successfully'))}\s+(.+)#");

        if (!empty($status)) {
            $r->general()->status(trim($status, '.'));
        }

        if ($passengers = $this->http->FindNodes("//h3[normalize-space(.)='Traveler(s)']/following-sibling::*[1]/li/text()[normalize-space(.)][1]")) {
            $r->general()
                ->travellers($passengers);
        } elseif ($name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest Name:'))}]/following::text()[normalize-space()!=''][1]")) {
            $r->general()
                ->traveller($name);
        }

        $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking ID:'))}]/following::text()[normalize-space()!=''][1]");

        if (!empty($conf)) {
            $r->general()
                ->confirmation($conf);
        } elseif (empty($this->http->FindSingleNode("(//text()[{$this->contains($this->t('Booking ID:'))}])[1]"))) {
            $r->general()
                ->noConfirmation();
        }
        $info = implode("\n",
            $this->http->FindNodes("//text()[{$this->eq($this->t('Booking details'))}]/following::table[1]/descendant::text()[normalize-space()!='']"));
        $r->hotel()
            ->name($this->re("/^(.+)/", $info))
            ->address(preg_replace("/\s+/", ' ', $this->re("/[^\n]+\n(.+?)(?:\n[ ]*{$this->opt($this->t('Phone'))}|$)/s", $info)))
            ->phone($this->re("/{$this->opt($this->t('Phone'))}[\s:]+(.+)/", $info), true, true);

        $inDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in'))}]/following::text()[normalize-space()!=''][1]");
        $inTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in'))}]/following::text()[normalize-space()!=''][2]",
            null, false, "#(\d+:\d+)#");
        $outDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-out'))}]/following::text()[normalize-space()!=''][1]");
        $outTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-out'))}]/following::text()[normalize-space()!=''][2]",
            null, false, "#(\d+:\d+)#");
        $r->booked()
            ->checkIn(strtotime($this->normalizeDate($inDate . ', ' . $inTime)))
            ->checkOut(strtotime($this->normalizeDate($outDate . ', ' . $outTime)))
            ->rooms($this->http->FindSingleNode("//text()[{$this->eq($this->t('Number of rooms'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]",
                null, false, "#(\d+) {$this->opt($this->t('Room'))}#"));

        /*$guests = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Capacity'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]",
            null, false, "#(\d+) {$this->opt($this->t('Guest per room'))}#");*/
        /*if (empty($guests)) {
            $guests = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest(s) per Room'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]",
                null, false, "#(\d+) {$this->opt($this->t('Adult'))}#");
        }*/
        /*if (!empty($guests)) {
            $r->booked()->guests($guests);
        }*/

        $room = $r->addRoom();

        $room->setType($this->http->FindSingleNode("//text()[{$this->eq($this->t('Room type'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]"))
//            ->setDescription($this->http->FindSingleNode("//text()[{$this->eq($this->t('Special request'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]"))
        ;

        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Price'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]");

        if (preg_match("/^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d., ]*)\s*$/", $total, $m)
            || preg_match("/^\s*(?<amount>\d[\d., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$/", $total, $m)
        ) {
            $currency = $this->currency($m['curr']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $email->price()->currency($currency)->total(PriceHelper::parse($m['amount'], $currencyCode));
        }
    }

    private function parseAir(Email $email): ?Email
    {
        $xpath = "//h3[(contains(., 'Departure') or contains(., 'Return')) and contains(., 'Flight')]/following-sibling::table[1]";
        $roots = $this->http->XPath->query($xpath);

        if (0 === $roots->length) {
            $this->logger->debug("Segments did not found by xpath: {$xpath}");

            return null;
        }

        $f = $email->add()->flight();

        if ($passengers = $this->http->FindNodes("//h3[normalize-space(.)='Traveler(s)']/following-sibling::*[1]/li/text()[normalize-space(.)][1]")) {
            $f->general()
                ->travellers($passengers);
        }

        $confs = [];

        foreach ($roots as $root) {
            $s = $f->addSegment();

            if (preg_match('/(\d{1,2}:\d{2})\s*(\w+, \d{1,2} \w+ \d{2,4})/', $this->getNode($root, 'Depart'), $m)) {
                $s->departure()
                    ->date(strtotime($m[2] . ', ' . $m[1]));
            }

            if (preg_match('/(\d{1,2}:\d{2})\s*(\w+, \d{1,2} \w+ \d{2,4})/', $this->getNode($root, 'Arrive'), $m)) {
                $s->arrival()
                    ->date(strtotime($m[2] . ', ' . $m[1]));
            }

            if ($dur = $this->getNode($root, 'Duration', '/(\d{1,2}h[ ]*\d{1,2}m)/')) {
                $s->extra()
                    ->duration($dur);
            }

            $xp = "following-sibling::table[1]/descendant::tr[contains(normalize-space(.), 'Booking Code')][last()]";

            if (preg_match('/([A-Z\d]{2})\-(\d+)/', $this->http->FindSingleNode($xp . '/td[1]', $root), $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            $confs[] = $this->http->FindSingleNode($xp . '/td[2]', $root, true, '/Booking Code \(PNR\)[ ]*(\w+)/');

            $dep = $this->http->FindSingleNode($xp . '/following-sibling::tr[1]/td[last()]', $root);

            if (preg_match('/(.+)[ ]*\(([A-Z]{3})\)/', $dep, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->code($m[2]);
            }

            if (preg_match('/Terminal (\w+)/', $dep, $m)) {
                $s->departure()
                    ->terminal($m[1]);
            }

            $arr = $this->http->FindSingleNode($xp . '/following-sibling::tr[2]/td[last()]', $root);

            if (preg_match('/(.+)[ ]*\(([A-Z]{3})\)/', $arr, $m)) {
                $s->arrival()
                    ->name($m[1])
                    ->code($m[2]);
            }

            if (preg_match('/Terminal (\w+)/', $arr, $m)) {
                $s->arrival()
                    ->terminal($m[1]);
            }
        }

        $confs = array_filter(array_unique($confs));

        foreach ($confs as $conf) {
            $f->general()
                ->confirmation($conf);
        }

        return $email;
    }

    private function getNode(\DOMNode $root, string $str, ?string $re = null): ?string
    {
        return $this->http->FindSingleNode("descendant::td[{$this->starts($str)}][1]", $root, true, $re);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body, $detectBody): bool
    {
        foreach ($detectBody as $lang => $dBody) {
            if (count($dBody) == 2 && mb_stripos($body, $dBody[0]) !== false && mb_stripos($body, $dBody[1]) !== false) {
                $this->lang = substr($lang, 0, 2);

                return true;
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
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
            if (isset($pos[$i], $pos[$i - 1])) {
                if ($pos[$i] - $pos[$i - 1] < $correct) {
                    unset($pos[$i]);
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function nice($str)
    {
        return trim(preg_replace("#\s+#", ' ', $str));
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug("Date: {$str}");
        $in = [
            "#^(\d+)[ ]*([^\s\d]+)[ ]*(\d{4}),?\s+(\d+:\d+)$#", //19 Aug 2016, 15:30
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€'   => 'EUR',
            'RM'  => 'MYR',
            '$'   => 'USD',
            '£'   => 'GBP',
            'S$'  => 'SGD',
            'Rp'  => 'IDR',
            'AU$' => 'AUD',
            'US$' => 'USD',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function strstrArray($haystack, $needle, $before_needle = null)
    {
        $needle = (array) $needle;

        foreach ($needle as $ndl) {
            $text = stristr($haystack, $ndl, $before_needle);

            if (!empty($text)) {
                return $text;
            }
        }

        return false;
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));

        if (empty($textRows)) {
            return '';
        }
        $pos = [];
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }
}
