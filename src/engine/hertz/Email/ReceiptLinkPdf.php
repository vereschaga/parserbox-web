<?php

namespace AwardWallet\Engine\hertz\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReceiptLinkPdf extends \TAccountChecker
{
    public $mailFiles = "hertz/it-27221880.eml, hertz/it-45679527.eml, hertz/it-45715556.eml, hertz/it-45719651.eml, hertz/it-47135371.eml";

    public $reFrom = ["HertzNoReply@rentals.hertz.com"];
    public $reBody = [
        'en'  => ['RENTED:', 'RENTAL:'],
        'en2' => ['Rental Location:', 'Rental Time:'],
    ];
    public $reSubject = [
        'Hertz Receipt',
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'travellerEnd'     => ['INITIAL CHARGES', 'Vehicle:'],
            'TOTAL AMOUNT DUE' => ['TOTAL AMOUNT DUE', 'TOTAL ESTIMATED CHARGE'],
            'RENTED:'          => ['RENTED:', 'TRENTED:', 'Rental Location:'], // TRENTED - kostyl
            'RENTAL:'          => ['RENTAL:', 'Rental Time:'],
            'RETURNED:'        => ['RETURNED:', 'Return Location:'],
            'RETURN:'          => ['RETURN:', 'Return Time:'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $parsePDF = [];
        //check first attachments
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null
                    && $this->detectText($text)
                ) {
                    $parsePDF[] = $text;
                }
            }
        }

        if (count($parsePDF) == 0 && null !== ($href = $this->getUrl())) {
            $file = $this->http->DownloadFile($href);
            unlink($file);

            if (($text = \PDF::convertToText($this->http->Response['body'])) !== null
                && $this->detectText($text)
            ) {
                $parsePDF[] = $text;
            }
        }

        foreach ($parsePDF as $text) {
            $this->assignLang($text);

            if (!$this->parseEmailPdf($text, $email)) {
                return $email;
            }
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null
                    && $this->detectText($text)
                ) {
                    return true;
                }
            }
        }

        if (null !== ($href = $this->getUrl())) {
            return true;
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

            if ($flag) {
                foreach ($this->reSubject as $reSubject) {
                    if (stripos($headers["subject"], $reSubject) !== false) {
                        return true;
                    }
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
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmailPdf($textPDF, Email $email)
    {
        $r = $email->add()->rental();

        if (preg_match("/\b(RR|Rental Record ?#)[ ]{3,}(\d{7,})$/m", $textPDF, $m)) {
            $r->general()->confirmation($m[2], 'Rental Record');
        }

        if (preg_match("#\n[ ]+RES[ ]+([A-Z\d]{5,})$#m", $textPDF, $m)) {
            $r->general()->confirmation($m[1], 'Reservition Id', true);
        }

        $r->general()
            ->traveller($this->re("#(?:[ ]+RES[ ]+[A-Z\d]{5,}|\n)\n[ ]*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\n+[ ]*{$this->opt($this->t('travellerEnd'))}#u", $textPDF));

        $r->program()->code('hertz');

        $tot = $this->getTotalCurrency($this->re("#\n *{$this->opt($this->t('TOTAL AMOUNT DUE'))} +(.+)#", $textPDF));
        $r->price()
            ->total($tot['Total'])
            ->currency($tot['Currency']);

        $r->pickup()
            ->location($this->re("#\n *{$this->opt($this->t('RENTED:'))} +(.+)#", $textPDF))
            ->date($this->normalizeDate($this->re("#\n *{$this->opt($this->t('RENTAL:'))} +(.+)#", $textPDF)));
        $r->dropoff()
            ->location($this->re("#\n *{$this->opt($this->t('RETURNED:'))} +(.+)#", $textPDF))
            ->date($this->normalizeDate($this->re("#\n *{$this->opt($this->t('RETURN:'))} +(.+)#", $textPDF)));

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            // 09 / 28 / 18 08 41    |    10 / 20 / 19 at   531 PM    |    10 / 24 / 19 at 10 00 PM
            '/^(\d{1,2})[ ]*\/[ ]*(\d{1,2})[ ]*\/[ ]*(\d{2})(?:[ ]+at)?[ ]+(\d{1,2}) ?(\d{2})([ ]*[AaPp]\.?[Mm]\.?)?$/',
        ];
        $out = [
            '20$3-$1-$2, $4:$5$6',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function detectText($text)
    {
        if (strpos($text, 'WWW.HERTZ.COM/CHARGEEXPLAINED') !== false
            && $this->assignLang($text)
        ) {
            return true;
        }

        return false;
    }

    private function getUrl()
    {
        $stopLabels = ['View Receipt', 'VIEW RECEIPT', 'View Rental Record', 'VIEW RENTAL RECORD'];

        $href = $this->http->FindSingleNode("//a[starts-with(@href,'https://hertzera.bormc.com/images/ereceipt/') and contains(@href,'.pdf') and not({$this->eq($stopLabels)})]/@href");

        if (preg_match("#https:\/\/hertzera\.bormc\.com\/images\/ereceipt\/\d{4}\/\d+\/\d+\/#", $href)) {
            return $href;
        }
        // https://urldefense.proofpoint.com/....
        $href = $this->http->FindSingleNode("//a[contains(@href,'/url?u=https-3A__hertzera.bormc.com_images_ereceipt_') and not({$this->eq($stopLabels)})]/@href");

        if (preg_match("#\/url\?u=https-3A__hertzera\.bormc\.com_images_ereceipt_\d{4}_\d+_\d+_#", $href)) {
            return $href;
        }
        // https://nam04.safelinks.protection.outlook.com/....
        $href = $this->http->FindSingleNode("//a[contains(@href,'/?url=https%3A%2F%2Fhertzera.bormc.com%2Fimages%2Fereceipt%2F') and not({$this->eq($stopLabels)})]/@href");

        if (preg_match("#\/\?url=https%3A%2F%2Fhertzera\.bormc\.com%2Fimages%2Fereceipt%2F\d{4}%2F\d+%2F\d+%2F#",
            $href)) {
            return $href;
        }

        return null;
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
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);

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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }
}
