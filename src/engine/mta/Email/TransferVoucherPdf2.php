<?php

namespace AwardWallet\Engine\mta\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TransferVoucherPdf2 extends \TAccountChecker
{
    public $mailFiles = "mta/it-512781134.eml, mta/it-512881678.eml";

    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
        ],
    ];

    public $provs = [
        'mta' => ['MTA Travel -'],
    ];

    private static $providers = [
        'mta' => [
            'from' => ['@mtatravel.com'],
            'subj' => [
                'en'  => 'Transfer Booking Confirmation',
            ],
            'body' => [
                'MTA Travel -',
            ],
        ],
    ];

    private static $companies = [
        'Holiday Taxis' => [
            'from' => ['@whiteluxtravel.com.au'],
            'subj' => [
                'en'  => 'Transfer Booking Confirmation',
            ],
            'body' => [
                'This booking has been supplied by Holiday Taxis.',
            ],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$providers as $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                return true;
            }
        }

        foreach (self::$companies as $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $this->logger->debug('Subject:YES');
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (null === $this->getProvider($parser, $text) && null === $this->getCompanies($parser, $text)) {
                return false;
            }

            if (stripos($text, 'From:') !== false
                && (stripos($text, 'Pickup Time:') !== false || stripos($text, 'Arrival Time:') !== false)
                && stripos($text, 'Vehicle Type:') !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$providers as $detects) {
            foreach ($detects['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        foreach (self::$companies as $detects) {
            foreach ($detects['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!empty($this->code = $this->getProvider($parser, $text))) {
                $email->setProviderCode($this->code);
            }

            $this->ParseTransfer($email, $text);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseTransfer(Email $email, string $text)
    {
        $t = $email->add()->transfer();

        $t->general()
            ->confirmation($this->re("/{$this->opt($this->t('Your Reference No:'))}\s*([A-Z\d]{6})/", $text))
            ->traveller($this->re("/{$this->opt($this->t('Thank you'))}\s*([[:alpha:]][-.\'[:alpha:]\s]*[[:alpha:]])\!/", $text));

        $s = $t->addSegment();

        $s->departure()
            ->name($this->re("/{$this->opt($this->t('From:'))}\s*(.+)/", $text));

        $s->arrival()
            ->name($this->re("/{$this->opt($this->t('To:'))}\s*(.+)/", $text));

        $s->setCarType($this->re("/{$this->opt($this->t('Vehicle Type:'))}\s*(.+)/", $text));
        $s->setDuration($this->re("/{$this->opt($this->t('Transfer Time:'))}\s*(.+)/", $text));

        if (preg_match("/{$this->opt($this->t('Arrival Time:'))}/iu", $text)) {
            $s->departure()
                ->date($this->normalizeDate($this->re("/{$this->opt($this->t('Arrival Time:'))}\s*(.+)/", $text)));
            $s->arrival()
                ->date(strtotime($s->getDuration(), $s->getDepDate()));
        } else {
            $s->departure()
                ->date($this->normalizeDate($this->re("/{$this->opt($this->t('Departure Time:'))}\s*(.+)/", $text)));
            $s->arrival()
                ->date(strtotime($s->getDuration(), $s->getDepDate()));
        }

        $s->setAdults($this->re("/{$this->opt($this->t('Number of Passengers:'))}\s*(\d+)/", $text));

        $price = $this->http->FindSingleNode("//text()[normalize-space()='Payment Details:']/ancestor::tr[1]/descendant::td[normalize-space()][2]", null, true, "/^(\D{1,3}[\d\.\,]+)\s+/");

        if (empty($price)) {
            $price = $this->http->FindSingleNode("//text()[normalize-space()='Payment Details:']/ancestor::tr[1]/descendant::td[normalize-space()][2]", null, true, "/(?:^|\s)(\D{1,3}[\d\.\,]+)\s+/");
        }

        if (preg_match("/^(?<currency>\D+)(?<total>[\d\.\,]+)$/", $price, $m)) {
            $t->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        $earnedPoints = $this->http->FindSingleNode("//text()[normalize-space()='You Earned:']/ancestor::tr[1]/descendant::td[normalize-space()][2]");

        if (!empty($earnedPoints)) {
            $t->setEarnedAwards($earnedPoints);
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

    public static function getEmailCompanies()
    {
        return [
            'Holiday Taxis' => 5, //OTHER TYPE
        ];
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    private function getProvider(PlancakeEmailParser $parser, $textBody): ?string
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            return $this->code;
        }

        foreach (self::$providers as $code => $arr) {
            $criteria = $arr['body'];

            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if (strpos($textBody, $search) !== false) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function getCompanies(PlancakeEmailParser $parser, $textBody): ?string
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        foreach (self::$companies as $company => $arr) {
            $criteria = $arr['body'];

            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if (strpos($textBody, $search) !== false) {
                        return $company;
                    }
                }
            }
        }

        return null;
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
            return preg_quote($s);
        }, $field)) . ')';
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
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

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+)\-(\w+)\-(\d{4})\s*at\s*([\d\:]+)$#u", //23-Oct-2023 at 11:15
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
