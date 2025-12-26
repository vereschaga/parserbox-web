<?php

namespace AwardWallet\Engine\grab\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourEReceiptJunk extends \TAccountChecker
{
    public $mailFiles = "grab/it-56456749.eml, grab/it-56502984.eml";
    private $lang = '';
    private $reFrom = ['GrabTaxi Holdings', '@grab.com'];
    private $detectLang = [
        'en' => ['Order Details', 'Booking Details'],
        'id' => ['Detail Pesanan'],
        'th' => ['ทานอาหารให้อร่อย!', 'ขอบคุณที่ซื้อสินค้ากับเรา!'],
        'vi' => ['Chúc bạn ngon miệng'],
    ];
    private $reSubject = [
        'Your Grab E-Receipt',
    ];
    private static $dictionary = [
        'en' => [
            'headerValues'   => ['Hope you enjoyed your food!', 'Thanks for shopping with us!'],
            'grabBadRemarks' => ['GrabFood', 'GrabMart'],
        ],
        'id' => [
            'headerValues'   => ['Selamat menikmati makanan Anda!', 'Terima kasih sudah memesan dari kami!'],
            'grabBadRemarks' => ['GrabFood', 'GrabMart'],
        ],
        'vi' => [
            'headerValues'   => ['Chúc bạn ngon miệng!'],
            'grabBadRemarks' => ['Trần Văn Giàu (GB)', 'GrabFood', 'Cước phí giao hàng'],
        ],
        'th' => [
            'headerValues'   => ['ทานอาหารให้อร่อย!', 'ขอบคุณที่ซื้อสินค้ากับเรา!'],
            'grabBadRemarks' => ['GrabFood', 'GrabMart'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }

        if ($this->http->XPath->query("//tr[{$this->eq($this->t('headerValues'))}]")->length === 1
            && $this->http->XPath->query("//text()[{$this->eq($this->t('grabBadRemarks'))}]")->length === 1
        ) {
            $email->setIsJunk(true);
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
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
        if ($this->http->XPath->query("//text()[{$this->contains($this->reFrom)}]")->length === 0) {
            return false;
        }

        if ($this->assignLang() && $this->http->XPath->query("//td[{$this->eq($this->t('headerValues'))}]")->length === 1) {
            return true;
        }

        return false;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $value) {
            if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return $text . '="' . $s . '"';
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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
            } elseif (stripos($haystack, $needles) !== false) {
                return $key;
            }
        }

        return false;
    }
}
