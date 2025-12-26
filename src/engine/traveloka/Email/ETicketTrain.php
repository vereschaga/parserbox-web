<?php

namespace AwardWallet\Engine\traveloka\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ETicketTrain extends \TAccountChecker
{
    public $mailFiles = "traveloka/it-26638817.eml, traveloka/it-27353587.eml";

    public $reFrom = ["booking@traveloka.combooking@traveloka.com"];
    public $reBody = [
        'en' => ['e-ticket', 'Departure Train'],
        'id' => ['e-tiket', 'Kereta Pergi'],
    ];
    public $reSubject = [
        '#\[Traveloka\] Your Train E-ticket – Booking ID \d+#',
        '#\[Traveloka\] E-tiket Kereta Api Anda – No. Pesanan \d+#',
    ];
    public $lang = '';
    public $pdfNamePattern = "TRAIN_(?:AWAY|RETURN)_e-ticket.pdf";
    public static $dict = [
        'en' => [
            //pdf
            'paxReg' => ' *No\. +Passenger\(s\) +Type',
        ],
        'id' => [
            //pdf
            'paxReg'                                              => ' *No\. +Penumpang +Jenis',
            'Use this e-ticket to print the boarding pass at the' => 'Gunakan e-tiket ini untuk mencetak boarding pass di',
            'Traveloka Booking ID'                                => 'No. Pesanan Traveloka',
            'IMPORTANT PRE-TRAVEL INFO'                           => 'HAL PENTING TERKAIT KEBERANGKATAN',
            'E-ticket'                                            => 'E-tiket',
            'Booking Code'                                        => 'Kode Booking',
            'Seat Number'                                         => 'Nomor Kursi',
            'Seat'                                                => 'Kursi',
            'You pay'                                             => 'Anda Bayar',
            //html
            'Need help'                                    => 'Butuh bantuan',
            'Your train reservation has been successfully' => 'Pemesanan tiket kereta api Anda telah sukses',
            'Departure'                                    => 'Berangkat',
            'Duration'                                     => 'Durasi',
            'Direct'                                       => 'Langsung',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $parsed = false;
        $type = 'Pdf';
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $i => $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->assignLang($text)) {
                        $result = $this->parseEmailPdf($text, $email);

                        if ($result === null) {
                            $this->logger->debug('can\'t parse attach-' . $i);

                            continue;
                        } elseif ($result === false) {
                            return false;
                        } else {
                            $parsed = true;
                        }
                    } else {
                        $this->logger->debug('can\'t determine a language by attach-' . $i);
                    }
                }
            }
        }

        if (!$parsed) {
            if (!$this->assignLang($this->http->Response['body'])) {
                $this->logger->debug('can\'t determine a language by Body');
            } else {
                $this->parseEmail($email);
                $type = 'Html';
            }
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . $type . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(., 'traveloka.com')]")->length > 0) {
            if ($this->assignLang($this->http->Response['body'])) {
                return true;
            }
        }
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ((stripos($text, 'traveloka') !== false)
                && $this->assignLang($text)
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
     * @param $textPDF
     * @param $email
     *
     * @return bool|null If parsed then true, if parsed failed then false, if can't determine format then null
     */
    private function parseEmailPdf($textPDF, Email $email)
    {
        //check format
        if (strpos($textPDF, $this->t('Traveloka Booking ID')) == false || strpos($textPDF,
                $this->t('Use this e-ticket to print the boarding pass at the')) == false
        ) {
            return null;
        }
        $infoBlock = strstr($textPDF, $this->t('IMPORTANT PRE-TRAVEL INFO'), true);

        if (empty($infoBlock)) {
            return null;
        }

        $dateStr = $this->re("#{$this->opt($this->t('E-ticket'))} \([^\n]+\s+.+? {3,}(.+)#", $infoBlock);
        $date = strtotime($this->dateStringToEnglish($this->re("#.+?, (.+)#", $dateStr)));

        if (!$date) {
            $this->logger->debug('other format: date');

            return false;
        }
        $infoBlock = strstr(str_replace($dateStr, '', $infoBlock), "\n");
        $table = $this->splitCols($infoBlock, $this->colsPos($infoBlock));

        if (count($table) !== 4 && count($table) !== 4) {
            $table = $this->splitCols($infoBlock, [0, 40, 54, 95]);
        }

        $confNos = [];

        if (null !== $email->getTravelAgency()) {
            $otaConfNo = $email->getTravelAgency()->getConfirmationNumbers();

            foreach ($otaConfNo as $cn) {
                $confNos[] = $cn[0];
            }
        }
        $confNo = $this->re("#{$this->opt($this->t('Traveloka Booking ID'))}\s+([^\n]+)#", $table[3]);

        if (!in_array($confNo, $confNos)) {
            $email->ota()->confirmation($confNo, $this->t('Traveloka Booking ID'));
        }

        $r = $email->add()->train();

        $s = $r->addSegment();

        if (preg_match("#(.+?)\n([^\n]+)\s*\(([A-Z]{1,2})\)#s", trim($table[0]), $m)) {
            $train = $this->nice($m[1]);

            if (preg_match("#^(.+?) (\d[A-z\d]+)$#", $train, $v)) {
                $s->extra()
                    ->type($v[1])
                    ->number($v[2]);
            } else {
                $s->extra()
                    ->type($train[1])
                    ->noNumber();
            }
            $s->extra()
                ->cabin($m[2])
                ->bookingCode($m[3]);
        }

        if (preg_match("#(\d+:\d+)\s+(\d+ \w+)\s+([^\n]+)\s+(\d+:\d+)\s+(\d+ \w+)$#", trim($table[1]), $m)) {
            $year = date('Y', $date);
            $s->departure()->date(strtotime($this->dateStringToEnglish($m[2]) . ' ' . $year . ', ' . $m[1]));
            $s->arrival()->date(strtotime($this->dateStringToEnglish($m[5]) . ' ' . $year . ', ' . $m[4]));
            $s->extra()->duration($m[3]);
        }

        if (preg_match("#(.+?)\n\n\n(.+)#s", trim($table[2]), $m)) {
            $s->departure()
                ->name($this->nice($m[1]));
            $s->arrival()
                ->name($this->nice($m[2]));
        }

        if (isset($table[3])) {
            $r->general()
                ->confirmation(
                    $this->re("#{$this->opt($this->t('Booking Code'))}\s+([A-Z\d]+)#",
                        $table[3]));
        }

        $phones = [];

        if (null !== $email->getTravelAgency()) {
            $otaPhones = $email->getTravelAgency()->getProviderPhones();

            foreach ($otaPhones as $ph) {
                $phones[] = $ph[0];
            }
        }

        if (preg_match("#\n([\d\-\+\(\) ]+?) {5,}{$this->opt('cs@traveloka.com')}#s", $textPDF, $matches)) {
            $phs = array_filter(array_map("trim", preg_split("# {3,}#", $matches[1])));

            foreach ($phs as $ph) {
                if (!in_array(trim($ph), $phones)) {
                    $email->ota()->phone($ph, 'Customer Service');
                }
            }
        }

        $paxBlock = $this->re("#({$this->t('paxReg')}.+?)\n *(?:Customer Service|For any questions, visit Traveloka Help)#s", $textPDF);

        if (strpos($paxBlock, $this->t('Seat Number')) !== false) {
            $table = $this->re("#{$this->opt($this->t('Seat Number'))} *\n(.+)#s", $paxBlock);
            $table = $this->splitCols($table, $this->colsPos($table));

            if (count($table) !== 5) {
                $this->logger->debug('other format pax-table');

                return false;
            }
            $r->general()->travellers(preg_replace("/^(?:Mrs|Mr|Miss)/", "", array_filter(array_map("trim", explode("\n", $table[1])))));
            $s->extra()->seats(
                array_filter(array_map(function ($s) {
                    return $this->re("#{$this->opt($this->t('Seat'))}\s+(\d+[A-Z]*)#", $s);
                }, explode("\n", $table[4])))
            );
        } else {
            $this->logger->debug('other format pax-table; without seats');

            return false;
        }
        $sum = $this->getTotalCurrency($this->re("#{$this->opt($this->t('You pay'))} +(.+)#", $textPDF));

        if (!empty($sum['Total']) && !empty($sum['Currency'])) {
            $r->price()
                ->total($sum['Total'])
                ->currency($sum['Currency']);
        }

        return true;
    }

    private function parseEmail(Email $email)
    {
        $ta = $email->ota();
        $ta->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Traveloka Booking ID'))}]/following::text()[normalize-space()!=''][1]"),
            $this->t('Traveloka Booking ID'))
            ->phone($this->http->FindSingleNode("//text()[{$this->eq('Call')}]/following::text()[normalize-space()!=''][1]"),
                $this->t('Need help'));

        $status = $this->http->FindSingleNode(
            "//text()[{$this->starts($this->t('Your train reservation has been successfully'))}]",
            null,
            false,
            "#{$this->opt($this->t('Your train reservation has been successfully'))}\s*(.+?)(?:\.|$)#"
        );

        $xpath = "//text()[{$this->eq($this->t('Departure'))}]/ancestor::table[1][{$this->contains($this->t('Duration'))}]";
        $this->logger->debug("[XPATH]: " . $xpath);
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            if ($this->http->XPath->query("./descendant::text()[{$this->eq($this->t('Direct'))}]",
                    $root)->length === 0
            ) {
                $this->logger->debug('need check format');

                return false;
            }

            $r = $email->add()->train();
            $r->general()
                ->confirmation(
                    $this->http->FindSingleNode("./following-sibling::table[1]/descendant::text()[{$this->eq($this->t('Booking Code'))}]/following::text()[normalize-space()!=''][1]",
                        $root));

            if (!empty($status)) {
                $r->general()->status($status);
            }

            $s = $r->addSegment();

            $s->extra()
                ->duration($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Duration'))}]/following::text()[normalize-space()!=''][1]",
                    $root))
                ->stops(0); //while direct

            $node = implode("\n",
                $this->http->FindNodes("./following-sibling::table[1]/descendant::text()[{$this->eq($this->t('Booking Code'))}]/preceding::text()[normalize-space()!=''][1]/ancestor::td[1]//text()[normalize-space()!='']",
                    $root));

            if (preg_match("#(.+?)\n([^\n]+)\s*\(([A-Z]{1,2})\)#s", $node, $m)) {
                $train = $this->nice($m[1]);

                if (preg_match("#^(.+?) (\d[A-z\d]+)$#", $train, $v)) {
                    $s->extra()
                        ->type($v[1])
                        ->number($v[2]);
                } else {
                    $s->extra()
                        ->type($train[1])
                        ->noNumber();
                }
                $s->extra()
                    ->cabin($m[2])
                    ->bookingCode($m[3]);
            }
            $segRoot = $this->http->XPath->query("./following-sibling::table[1]/descendant::table[1][count(./descendant::tr[normalize-space()!=''])=2]",
                $root);

            if ($segRoot->length === 1) {
                $segRoot = $segRoot->item(0);
            } else {
                $this->logger->debug('other format: segment');

                return false;
            }

            $depTime = $this->http->FindSingleNode("./descendant::tr[normalize-space()!=''][1]/descendant::td[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][1]",
                $segRoot);
            $arrTime = $this->http->FindSingleNode("./descendant::tr[normalize-space()!=''][2]/descendant::td[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][1]",
                $segRoot);

            $depDateStr = $this->http->FindSingleNode("./descendant::tr[normalize-space()!=''][1]/descendant::td[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][2]",
                $segRoot);
            $depDate = strtotime($this->dateStringToEnglish($this->re("#.+?, (.+)#", $depDateStr)));

            $arrDateStr = $this->http->FindSingleNode("./descendant::tr[normalize-space()!=''][2]/descendant::td[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][2]",
                $segRoot);
            $arrDate = strtotime($this->dateStringToEnglish($this->re("#.+?, (.+)#", $arrDateStr)));

            $s->departure()->date(strtotime($depTime, $depDate));
            $s->arrival()->date(strtotime($arrTime, $arrDate));

            $depName = implode(', ',
                $this->http->FindNodes("./descendant::tr[normalize-space()!=''][1]/descendant::td[normalize-space()!=''][2]/descendant::text()[normalize-space()!='']",
                    $segRoot));
            $arrName = implode(', ',
                $this->http->FindNodes("./descendant::tr[normalize-space()!=''][2]/descendant::td[normalize-space()!=''][2]/descendant::text()[normalize-space()!='']",
                    $segRoot));
            $s->departure()->name($depName);
            $s->arrival()->name($arrName);
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body)
    {
        foreach ($this->reBody as $lang => $reBody) {
            if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                $this->lang = $lang;

                return true;
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

    private function getTotalCurrency($node)
    {
        $node = str_replace("Rp", "IDR", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t'], '.', ',');
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
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
        return trim(preg_replace("#\s+#", ' ', $str), ' .-');
    }
}
