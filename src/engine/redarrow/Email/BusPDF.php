<?php

namespace AwardWallet\Engine\redarrow\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BusPDF extends \TAccountChecker
{
    public $mailFiles = "redarrow/it-803767762.eml, redarrow/it-803812740.eml, redarrow/it-806989607.eml, redarrow/it-809543775.eml, redarrow/it-875020730.eml";

    public $subjects = [
        'Purchase Confirmation [Transaction:',
    ];

    public $pdfNamePattern = "ticket_.*pdf";

    public $lang = 'en';
    public $pdfCurrency;

    public static $dictionary = [
        'en' => [],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'pwtmail.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectHtml() === true) {
            return true;
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) === true) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]pwtmail\.com$/', $from) > 0;
    }

    public function getSegmentsXpath(): \DOMNodeList
    {
        return $this->http->XPath->query("//text()[{$this->starts($this->t('TICKET:'))}]/ancestor::div[1][.//tr[*[1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Passenger:'))}]][*[2]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Price:'))}]]]");
    }

    public function detectHtml()
    {
        if ($this->http->XPath->query("//a/@href[{$this->contains('.betterez.com/')}]")->length === 0) {
            return false;
        }

        if ($this->getSegmentsXpath()->length > 0) {
            return true;
        }

        return false;
    }

    public function detectPdf($text)
    {
        if ($this->containsText($text, $this->t('Passenger')) !== false
            && $this->containsText($text, $this->t('Travel Date / Time')) !== false
            && $this->containsText($text, $this->t('Arrival date/time')) !== false
        ) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $type = '';

        if ($this->detectHtml() === true) {
            $seatText = '';
            $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

            foreach ($pdfs as $pdf) {
                $t = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (preg_match("/{$this->opt($this->t('Fare:'))} +[^\d\n]{1,3} +\d[\d,. ]+? ([A-Z]{3})(?: {3,}|\n)/", $t, $m)) {
                    $this->pdfCurrency = $m[1];
                }

                if ($this->containsText($t, $this->t('Section:')) === true) {
                    $seatText .= "\n" . $t;
                }
            }
            $pdfSegments = $this->split("/\n({$this->opt($this->t('Passenger'))} +{$this->opt($this->t('Travel Date / Time'))}\n)/u", $seatText);
            $this->BusHtml($email, $pdfSegments);
            $type = 'Html';
        }

        if (count($email->getItineraries()) === 0) {
            $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if ($this->detectPdf($text) === true) {
                    $this->BusPDF($email, $text);
                    $type = 'Pdf';
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type . ucfirst($this->lang));

        return $email;
    }

    public function BusHtml(Email $email, $pdfSegments)
    {
        $b = $email->add()->bus();

        // General
        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Transaction:'))}]/following::text()[1]", null, false, "/^[\dA-Z]{8}$/");

        $b->general()
            ->confirmation($confirmation);

        // Price
        $totalPrice = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total paid'))}]/following::text()[1]");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<total>\d[\d\.\,\']*)$/", $totalPrice, $m)
            || preg_match("/^(?<total>\d[\d\.\,\']*)\s*(?<currency>\D{1,3})$/", $totalPrice, $m)
        ) {
            $currency = $this->pdfCurrency ?? $m['currency'];

            $b->price()
                ->currency($currency)
                ->total(PriceHelper::parse($m['total'], $currency));
        }

        $segmentNodes = $this->getSegmentsXpath();

        $passengersArray = [];
        $costArray = [];
        $discountArray = [];

        foreach ($segmentNodes as $segment) {
            $s = $b->addSegment();

            $passenger = $this->http->FindSingleNode("./descendant::table[2]/descendant::text()[{$this->eq($this->t('Passenger:'))}]/following::text()[1]", $segment, false, "/^([[:alpha:]][-.\/\n\'’[:alpha:] ]*[[:alpha:]])$/");

            array_push($passengersArray, $passenger);

            $ticket = $this->http->FindSingleNode("./descendant::table[1]", $segment, false, "/^{$this->t('TICKET')}\s*\:\s*([\d\D]{6})$/");

            $b->addTicketNumber($ticket, false, $passenger);

            $depDate = $this->http->FindSingleNode("./descendant::table[2]/descendant::text()[{$this->eq($this->t('Departs:'))}]/following::text()[1]", $segment, false, "/^\w+\s*(\w+\s*\d+\s*\,\s*\d{4}\s*[\d\:]+\s*A?P?M?)$/");

            $s->departure()
                ->date(strtotime($depDate))
                ->name($this->fixName(implode(" ", $this->http->FindNodes("./descendant::table[2]/descendant::text()[{$this->eq($this->t('From:'))}]/following::text()[1]", $segment))));

            $arrDate = $this->http->FindSingleNode("./descendant::table[2]/descendant::text()[{$this->eq($this->t('Arrives:'))}]/following::text()[1]", $segment, false, "/^\w+\s*(\w+\s*\d+\s*\,\s*\d{4}\s*[\d\:]+\s*A?P?M?)$/");

            $s->arrival()
                ->date(strtotime($arrDate))
                ->name($this->fixName(implode(" ", $this->http->FindNodes("./descendant::table[2]/descendant::text()[{$this->eq($this->t('To:'))}]/following::text()[1]", $segment))));

            $brand = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Brand:'))}]/following::text()[normalize-space()][1]", $segment);

            switch ($brand) {
                case 'Red Arrow AB':
                case 'Ontario Northland':
                    $s->departure()->geoTip('ca');
                    $s->arrival()->geoTip('ca');
            }

            $cabin = $this->http->FindSingleNode("./descendant::table[2]/descendant::text()[{$this->eq($this->t('Class:'))}]/following::text()[1]", $segment);

            if ($cabin !== null) {
                $s->setCabin($cabin);
            }

            if ($currency !== null) {
                $costArray[] = PriceHelper::parse($this->http->FindSingleNode("./descendant::table[2]/descendant::td[2]/descendant::tr[{$this->starts($this->t('Price'))}]/descendant::td[2]", $segment, false, "/^\D{1,3}\s*(\d[\d\.\,\']*)$/"), $currency);

                $feesNodes = $this->http->FindNodes("./descendant::table[2]/descendant::td[2]/descendant::tr[position() > 2 and position() < last()]", $segment);

                foreach ($feesNodes as $fee) {
                    if (preg_match("/^(?<feeName>.+)\:\s*\D{1,3}(?<feeValue>\d[\d\.\,\']*)$/", $fee, $m)) {
                        $b->price()
                            ->fee($m['feeName'], PriceHelper::parse($m['feeValue'], $currency));
                    }
                }

                $discount = $this->http->FindSingleNode("./descendant::table[2]/descendant::td[2]/descendant::tr[{$this->starts($this->t('Discounts'))}]/descendant::td[2]", $segment, false, '/^\D{1,3}\s*(\d[\d\.\,\']*)$/');

                if ($discount !== null) {
                    $discountArray[] = PriceHelper::parse($discount, $currency);
                }
            }

            if (!empty($pdfSegments)) {
                foreach ($pdfSegments as $pSegments) {
                    if ($ticket !== null && $depDate !== null && $confirmation !== null
                        && preg_match("/" . preg_quote($depDate, '/') . ".+? $ticket *\- *$confirmation/s", $pSegments)
                    ) {
                        $seatNumber = $this->re("/{$this->opt($this->t('Section'))} *\: *.*(?: |\b)( [A-Z\d]{1,4})(?: {2,}|\n)/",
                            $pSegments);

                        if ($seatNumber !== null && $passenger !== null) {
                            $s->addSeat($seatNumber, false, false, $passenger);
                        }
                    }
                }
            }
        }

        $transactionFees = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Transaction fees:'))}]/following::text()[1]", null, false, '/^\D{1,3}\s*(\d[\d\.\,\']*)$/');

        if ($transactionFees !== null) {
            $b->price()
                ->fee("Transaction fees", PriceHelper::parse($transactionFees, $currency));
        }

        if (!empty($costArray)) {
            $b->price()
                ->cost(array_sum($costArray));
        }

        if (!empty($discountArray)) {
            $b->price()
                ->discount(array_sum($discountArray));
        }

        $b->setTravellers(array_unique($passengersArray), true);
    }

    public function fixName($name)
    {
        if (stripos($name, 'Calgary Downtown Ticket Office') === 0) {
            return $name . ', Calgary';
        }

        return $name;
    }

    public function BusPDF(Email $email, $text)
    {
        $b = $email->add()->bus();

        $b->general()
            ->date(strtotime($this->re("/{$this->opt($this->t('Issued'))}\s*\:\s*\w+\s*(\w+\s*\d+\s*\,\s*\d{4}\s*[\d\:]+)/", $text)));

        $tripNodes = $this->split("/(?:^|\n)(.+\n\s*{$this->opt($this->t('Passenger'))} + {$this->opt($this->t('Travel Date / Time'))})/", $text);

        $passengersArray = [];
        $totalArray = [];
        $costArray = [];
        $confNumbers = [];

        foreach ($tripNodes as $node) {
            $s = $b->addSegment();

            $journeyNames = $this->re("/{$this->opt($this->t('From'))}\n(.+)\n{$this->opt($this->t('Arrival date/time'))}/s", $node);

            if (preg_match("/^(?<depAddr>.+)\n{$this->opt($this->t('To'))}\n+(?<arrAddr>.+)$/s", preg_replace("/ {30,}\S.+/m", '', $journeyNames), $m)) {
                $s->departure()
                    ->name(preg_replace('/\n/', ' ', $m['depAddr']));

                $s->arrival()
                    ->name(preg_replace('/\n/', ' ', $m['arrAddr']));
            }

            $passenger = $this->re("/{$this->opt($this->t('Passenger'))}\s*{$this->opt($this->t('Travel Date / Time'))}\s*[\n\s]*([[:alpha:]][-.\/\n\'’[:alpha:] ]*[[:alpha:]]) {3,}\S.+\s*/", $node);

            array_push($passengersArray, $passenger);

            $b->addTicketNumber($this->re("/(?:^|\s)([\d\D]{6})[\s\n]*{$this->opt($this->t('Passenger'))}/", $node), false, $passenger);

            array_push($confNumbers, $this->re("/{$this->re("/(?:^|\s)([\d\D]{6})[\s\n]*{$this->opt($this->t('Passenger'))}/", $node)}\-([\dA-Z]{8}\n)/", $node));

            $depDate = $this->re("/{$this->opt($this->t('Passenger'))}\s*{$this->opt($this->t('Travel Date / Time'))}\s*[\n\s]*[[:alpha:]][-.\/\n\'’[:alpha:] ]*[[:alpha:]] {3,}\s*\w+\s*(\w+\s*\d+\s*\,\s*\d{4}\s*[\d\:]+ *A?P?M?)/", $node);

            if ($depDate !== null) {
                $s->departure()
                    ->date(strtotime($depDate));
            }

            $arrDate = $this->re("/{$this->opt($this->t('Arrival date/time'))}[\:\s]*\w+\s*(\w+\s*\d+\s*\,\s*\d{4}\s*[\d\:]+ *A?P?M?)/", $node);

            $s->arrival()
                ->date(strtotime($arrDate));

            $seatNumber = $this->re("/{$this->opt($this->t('Section'))} *\: *.*(?: |\b)( [A-Z\d]{1,4})(?: {2,}|\n)/", $node);

            if ($seatNumber !== null) {
                $s->addSeat($seatNumber, false, false, $passenger);
            }

            $pricesInfo = $this->re("/\n( *{$this->opt($this->t('Fare:'))}[\s\S]+?)\n *({$this->opt($this->t('Payments:'))})/", $node);

            if ($totalInfo = $this->re("/{$this->opt($this->t('Taxes'))}[\s\:\n]*{$this->opt($this->t('Total'))}[\s\:\n]*[\D\n]*(\d[\d\.\,\']*\s*\D{1,3}\n*\s*\d[\d\.\,\']*\s*\D{1,3})/", $pricesInfo)) {
                if (preg_match("/^(?<tax>\d[\d\.\,\']*)\s*(?<currency>\D{1,3})\n*\s*(?<total>\d[\d\.\,\']*)\s*\D{1,3}/", $totalInfo, $m)) {
                    $currency = $m['currency'];

                    $totalArray[] = PriceHelper::parse($m['total'], $currency);

                    $b->price()
                        ->currency($currency)
                        ->tax(PriceHelper::parse($m['tax'], $currency));

                    $pricesInfo2 = $this->re("/({$this->opt($this->t('Fare'))}[\: ]*\D{1,3} *\d[\d\.\,\']*\s*\D{1,3}[\n\s]*{$this->opt($this->t('Fees'))}[\:\s]*\D{1,3}\s*\d[\d\.\,\']*\s*\D{1,3})/", $pricesInfo);

                    if (preg_match("/{$this->opt($this->t('Fare'))}[\:\s]*\D{1,3}\s*(?<fare>\d[\d\.\,\']*)\s*\D{1,3}[\n\s]*{$this->opt($this->t('Fees'))}[\:\s]*\D{1,3}\s*(?<fees>\d[\d\.\,\']*)\s*\D{1,3}/", $pricesInfo2, $m)) {
                        $costArray[] = PriceHelper::parse($m['fare'], $currency);

                        $b->price()
                            ->fee('Fees', PriceHelper::parse($m['fees'], $currency));
                    }
                }
            } elseif ($totalInfo = $this->re("/{$this->opt($this->t('Total'))} *\: *\D{1,3} *(\d[\d\.\,\']* *\D{1,3})/", $pricesInfo)) {
                if (preg_match("/^(?<total>\d[\d\.\,\']*) *(?<currency>\D{1,3})$/", $totalInfo, $m)) {
                    $currency = $m['currency'];

                    $totalArray[] = PriceHelper::parse($m['total'], $currency);

                    $b->price()
                        ->currency($currency);

                    $costInfo = $this->re("/{$this->opt($this->t('Fare'))} *\: *\D{1,3} *(\d[\d\.\,\']* *\D{1,3})/", $pricesInfo);

                    if (preg_match("/^(?<fare>\d[\d\.\,\']*) *(?<currency>\D{1,3})$/", $costInfo, $m)) {
                        $costArray[] = PriceHelper::parse($m['fare'], $currency);
                    }

                    $feesInfo = $this->re("/{$this->opt($this->t('Other fees'))} *\: *\D{1,3} *(\d[\d\.\,\']* *\D{1,3})/", $pricesInfo);

                    if (preg_match("/^(?<fees>\d[\d\.\,\']*) *(?<currency>\D{1,3})$/", $feesInfo, $m)) {
                        $b->price()
                            ->fee('Other fees', PriceHelper::parse($m['fees'], $currency));
                    }

                    $taxesInfo = $this->re("/{$this->opt($this->t('Taxes'))} *\: *\D{1,3} *(\d[\d\.\,\']* *\D{1,3})/", $pricesInfo);

                    if (preg_match("/^(?<taxes>\d[\d\.\,\']*) *(?<currency>\D{1,3})$/", $taxesInfo, $m)) {
                        $b->price()
                            ->tax(PriceHelper::parse($m['taxes'], $currency));
                    }
                }
            }
        }

        foreach (array_unique($confNumbers) as $number) {
            $b->general()
                ->confirmation($number);
        }

        $b->setTravellers(array_unique($passengersArray), true);

        if (!empty($totalArray) && !empty($costArray)) {
            $b->price()
                ->cost(array_sum($costArray))
                ->total(array_sum($totalArray));
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (strpos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && strpos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
