<?php

namespace AwardWallet\Engine\moshtix\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingAndTickets extends \TAccountChecker
{
    public $mailFiles = "moshtix/it-774674459.eml";
    public $subjects = [
        'Your Booking Confirmation And Tickets - Order #',
    ];

    public $lang = 'en';

    public $pdfData = [];

    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@moshtix.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Moshtix')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('OPEN IN MAPS'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your Order Details'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]moshtix\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (count($pdfs) > 0) {
            $this->pdfData['guestCount'] = count($pdfs);
        }

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (preg_match("/{$this->opt($this->t('NAME:'))} +(?<pax>[[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])(?: {3,}|\n)/", $text, $m)) {
                $this->pdfData['travellers'][] = $m['pax'];
            }

            if (preg_match("/{$this->opt($this->t('ENTRY DATE:'))}\s+(?<date>\d+\s+\w+\s+\d{4}\s+[\d\:]+\s*A?P?M)\s/", $text, $m)) {
                $this->pdfData['startDateTime'] = $m['date'];
            }

            if (!isset($this->pdfData['eventInfo'])) {
                $textPart = $this->re("/\n^([ ]+EVENT:.+EVENT ID:)/ms", $text);
                $spaceCount = strlen($this->re("/^([ ]+)EVENT:/", $textPart));

                if ($spaceCount > 50) {
                    $table = $this->SplitCols($textPart, [0, $spaceCount - 1]);
                    $this->pdfData['eventInfo'] = $table[1];
                } else {
                    $table = $this->SplitCols($textPart, [0, 80]);
                    $this->pdfData['eventInfo'] = $table[0];
                }
            }
        }

        $this->Event($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Event(Email $email)
    {
        $e = $email->add()->event();

        $e->setEventType(EVENT_SHOW);

        $e->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Order Number:']/ancestor::tr[1]", null, true, "/^{$this->opt($this->t('Order Number:'))}\s+(\d{5,})$/"));

        $dateRes = $this->http->FindSingleNode("//text()[normalize-space()='Your Order Details']/following::text()[normalize-space()='Order Date:'][1]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Order Date:'))}\s+(.+)/");

        if (!empty($dateRes)) {
            $e->general()
                ->date(strtotime(str_replace('/', ' ', $dateRes)));
        }

        $price = $this->http->FindSingleNode("//text()[normalize-space()='TOTAL INCL. GST']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('TOTAL INCL. GST'))}\s*(\D{1,3}\s*[\d\.\,\']+)$/");

        if (preg_match("/(?<currency>\D{1,3})\s*(?<total>[\d\.\,\']+)/", $price, $m)) {
            $e->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);

            $tax = $this->http->FindSingleNode("//text()[normalize-space()='GST']/ancestor::tr[1]", null, true, "/^{$this->opt($this->t('GST'))}\s*\D{1,3}\s*([\d\.\,\']+)$/");

            if ($tax !== null) {
                $e->price()
                    ->tax(PriceHelper::parse($tax, $m['currency']));
            }
        }

        $status = $this->http->FindSingleNode("//text()[normalize-space()='OPEN IN MAPS']/preceding::text()[contains(normalize-space(), 'Your booking is')][1]", null, true, "/{$this->opt($this->t('Your booking is'))}\s*(\w+)\./");

        if (!empty($status)) {
            $e->setStatus($status);
        }

        $addressText = implode("\n", $this->http->FindNodes("//text()[normalize-space()='View My Tickets']/preceding::table[contains(normalize-space(), 'OPEN IN MAPS')][1]/ancestor::table[2]/descendant::text()[normalize-space()]"));

        if (empty($addressText)) {
            $addressText = implode("\n", $this->http->FindNodes("//text()[contains(normalize-space(), 'OPEN IN MAPS')][1]/ancestor::table[3]/descendant::text()[normalize-space()]"));
        }

        if (preg_match("/^(?<name>.+)\n(?<date>\d+\s*\w+\s*\d{4}\,\s*[\d\:]+\s*a?p?m)?\n*.+\nOPEN IN MAPS/", $addressText, $m)) {
            $e->setName($m['name']);

            if (isset($this->pdfData['startDateTime'])) {
                $e->setStartDate(strtotime($this->pdfData['startDateTime']));
            } elseif (isset($m['date']) && !empty($m['date'])) {
                $e->setStartDate(strtotime($m['date']));
            }
        } elseif (preg_match("/^(?<name>.+)\n(?<date>\d+\s*\w+\s*\d{4})\n*.+\n(?<time>\d+\:\d+\s*A?P?M)\n(?:.+\n)?OPEN IN MAPS$/", $addressText, $m)) {
            $e->setName($m['name']);

            if (isset($this->pdfData['startDateTime'])) {
                $e->setStartDate(strtotime($this->pdfData['startDateTime']));
            } elseif (isset($m['date']) && !empty($m['date'])) {
                $e->setStartDate(strtotime($m['date'] . ', ' . $m['time']));
            }
        } else {
            $name = $this->http->FindSingleNode("//img[contains(@src, 'location-icon')]/following::text()[normalize-space()][1]/following::table[1]/descendant::table[2]/descendant::text()[normalize-space()][1]");

            if (!empty($name)) {
                $e->setName($name);
            }
        }

        $e->setNoEndDate(true);

        if (isset($this->pdfData['travellers'])) {
            $this->pdfData['travellers'] = array_filter(preg_replace("/(ADMIT)/", "", $this->pdfData['travellers']));

            if (count($this->pdfData['travellers']) > 0) {
                $e->general()
                    ->travellers(array_unique($this->pdfData['travellers']));
            }
        } else {
            $traveller = $this->http->FindSingleNode("//text()[normalize-space()='Customer Name:']/following::text()[normalize-space()][1]");

            if (!empty($traveller)) {
                $e->general()
                    ->traveller($traveller);
            }
        }

        if (isset($this->pdfData['guestCount'])) {
            $e->setGuestCount($this->pdfData['guestCount']);
        }

        if (isset($this->pdfData['eventInfo'])
            && preg_match("/^([ ]*)EVENT:\s+(?<name>.+)\n+(?:.*\w+\s+\d{4}.*\n+)?(?<address>(?:.+\n){1,10})\s*EVENT ID\:/", $this->pdfData['eventInfo'], $m)) {
            $e->setAddress(preg_replace("/[ ]{2,}/", " ", str_replace("\n", " ", $m['address'])));
        } else {
            $googleLink = $this->http->FindSingleNode("//text()[normalize-space()='OPEN IN MAPS']/ancestor::a/@href");
            $http2 = clone $this->http;
            $http2->GetURL($googleLink);
            $currentURL = $http2->currentUrl();

            $address = '';

            if (preg_match("/query[=](.+)/", $currentURL, $m)) {
                $address = preg_replace("/(\,)(\S)/", "$1 $2", str_replace(['%20', '+'], ' ', $m[1]));
            }

            if (!empty($address)) {
                $e->setAddress($address);
            }
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function TableHeadPos($row)
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

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
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
}
