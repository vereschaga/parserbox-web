<?php

namespace AwardWallet\Engine\thrifty\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class RentalReceipt extends \TAccountChecker
{
    public $mailFiles = "thrifty/it-98617164.eml";
    public $subjects = [
        '/Thrifty Updated Rental Receipt/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Here’s your Thrifty Rental Car Receipt' => [
                'Here’s your Thrifty Rental Car Receipt',
                'Here’s your Updated Thrifty Rental Car Receipt',
            ],
            'VIEW RECEIPT' => ['VIEW RECEIPT', 'VIEW UPDATED RECEIPT'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@rentals.thrifty.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Thrifty')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Here’s your Thrifty Rental Car Receipt'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('VIEW RECEIPT'))}]")->length > 0) {
            $href = $this->http->FindSingleNode("//a[{$this->contains($this->t('VIEW RECEIPT'))}]/@href");
            $this->http->NormalizeURL($href);

            if (!empty($href) && $this->http->FindPreg("#pdf#i", false, $href)) {
                $file = $this->http->DownloadFile($href);
                unlink($file);
                $text = \PDF::convertToText($this->http->Response['body']);

                if (!empty($text) && stripos($text, "INITIAL CHARGES") !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]rentals\.thrifty\.com$/', $from) > 0;
    }

    public function ParseRental(Email $email, $text)
    {
        $r = $email->add()->rental();
        $r->general()
            ->confirmation($this->re("/RES\s*([A-Z\d]{10,})\s+/s", $text), 'RES');

        $travellersText = $this->re("/RES\s*[A-Z\d]{10,}\s+(\D+)\s+INITIAL CHARGES/s", $text);
        $travellers = array_unique(array_filter(explode("\n", $travellersText)));
        $r->general()
            ->travellers($travellers, true);

        $r->car()
            ->model($this->re("/VEHICLE:\s*\d+\s*\/\s*\d+\s*(.+)\s+LICENSE/s", $text));

        $r->pickup()
            ->date($this->normalizeDate($this->re("/RENTAL:\s+(\d+\s*\/\s*\d+\s*\/\s*\d{2}\s*\d+\s*\d+)/", $text)))
            ->location($this->re("/RENTED:\s*(.+)\s+RENTAL/s", $text));

        $r->dropoff()
            ->date($this->normalizeDate($this->re("/RETURN:\s+(\d+\s*\/\s*\d+\s*\/\s*\d{2}\s*\d+\s*\d+)/", $text)))
            ->location($this->re("/RETURNED:\s*(.+)\s+COMPLETED BY:/s", $text));

        if (preg_match("/TOTAL AMOUNT DUE\s*(\D)\s+([\d\.]+)\s*/su", $text, $m)) {
            $r->price()
                ->currency($m[1])
                ->total($m[2]);
        }

        if (stripos($text, 'Thrifty') !== false) {
            $r->setCompany('Thrifty');
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $href = $this->http->FindSingleNode("//a[contains(text(), 'VIEW RECEIPT')]/@href");
        $this->http->NormalizeURL($href);

        if (!empty($href) && $this->http->FindPreg("#pdf#i", false, $href)) {
            $file = $this->http->DownloadFile($href);
            unlink($file);
            $text = \PDF::convertToText($this->http->Response['body']);
            $this->ParseRental($email, $text);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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
            return str_replace(' ', '\s+', preg_quote($s));
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

    private function normalizeDate($date)
    {
        $in = [
            '#^(\d+)\s*\/\s*(\d+)\s*\/\s*(\d{2})\s*(\d+)\s*(\d+)$#', //06 / 05 / 21 14 29
        ];
        $out = [
            '$2.$1.20$3, $4:$5',
        ];
        $str = preg_replace($in, $out, $date);

        return strtotime($str);
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
}
