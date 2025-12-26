<?php

namespace AwardWallet\Engine\loews\Email;

use AwardWallet\Schema\Parser\Email\Email;

class StayComingUp extends \TAccountChecker
{
    public $mailFiles = "loews/it-157628334.eml, loews/it-158650046.eml";

    public $detectBody = [
        'en' => [
            'we are looking forward to seeing you at',
            'we look forward to seeing you at ',
        ],
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'we are looking forward to seeing you at' => ['we are looking forward to seeing you at', 'we look forward to seeing you at'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@loewshotels.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Your stay at Loews') !== false
            && stripos($headers['subject'], ' is coming up') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"www.loewshotels.com") or contains(@href,".loewshotels.com/") or contains(@href,"//twitter.com/loews_hotels")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"visit LoewsHotels.com") or contains(.,"@loewshotels.com")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHotel(Email $email): void
    {
        $h = $email->add()->hotel();

        // General
        $conf = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('CONFIRMATION #:'))}])[1]/following::text()[normalize-space()][1]", null, true, '/^\s*(\w+\d\w+)\s*$/u');
        if (empty($conf) && !empty($this->http->FindSingleNode("(//text()[{$this->eq($this->t('CONFIRMATION #:'))}])[1]/following::text()[normalize-space()][1][{$this->eq($this->t('NAME:'))}]"))) {
            $h->general()
                ->noConfirmation();
        } else {
            $h->general()
                ->confirmation($conf);
        }
        $h->general()
            ->traveller($this->http->FindSingleNode("(//text()[{$this->eq($this->t('NAME:'))}])[1]/following::text()[normalize-space()][1]", null, true, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u'));

        // Hotel
        $name = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('we are looking forward to seeing you at'))}])[1]/ancestor::td[1]",
            null, true, "/{$this->opt($this->t('we are looking forward to seeing you at'))}\s*(Loews.+?)\./u");
        $h->hotel()
            ->name($name);
        if (!empty($name)) {
            $hotelInfo = implode("\n", $this->http->FindNodes("//text()[contains(., 'Copyright')]/following::text()[normalize-space()][position() < 8][contains(., 'Phone')]/ancestor::*[contains(., 'Copyright')][1]//text()[normalize-space()]"));
//            $this->logger->debug('$hotelInfo = '.print_r( $hotelInfo,true));
            if (preg_match("/Copyright\s*[^\s\w]\s*\d{4}\s*Loews.*\n([\s\S]+)Phone:\s*([\d\-\+\(\)\. ]{5,})\n/u", $hotelInfo, $m)) {
                $h->hotel()
                    ->address($m[1])
                    ->phone($m[2]);
            }
            if (preg_match("/Fax:\s*([\d\-\+\(\)\. ]{5,})\n/u", $hotelInfo, $m)) {
                $h->hotel()
                    ->fax($m[1]);
            }
        }

        // Booked
        $ciTime = preg_replace("/^\s*(\d{1,2})\s*([AP]M)\s*$/i", '$1:00 $2',
            $this->http->FindSingleNode("(//text()[{$this->eq($this->t('CHECK-IN TIME:'))}])[1]/following::text()[normalize-space()][1]"));

        $h->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("(//text()[{$this->eq($this->t('CHECK-IN:'))}])[1]/following::text()[normalize-space()][1]")
            . ((!empty($ciTime)? ', ' . $ciTime : ''))))
            ->checkOut(strtotime($this->http->FindSingleNode("(//text()[{$this->eq($this->t('CHECK-OUT:'))}])[1]/following::text()[normalize-space()][1]")))
        ;
    }

    private function detectBody(): bool
    {
        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
