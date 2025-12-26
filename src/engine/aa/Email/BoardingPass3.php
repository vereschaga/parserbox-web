<?php

namespace AwardWallet\Engine\aa\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BoardingPass3 extends \TAccountChecker
{
	public $mailFiles = "aa/it-869741073.eml";

    public $subjects = [
        ' Boarding Pass -',
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'Boarding Pass' => 'Boarding Pass',
            'Confirmation code' => 'Confirmation code',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'info.email.aa.com') !== false) {
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
        if (stripos($parser->getHeader('from'), 'info.email.aa.com') === false
            && $this->http->XPath->query("//img/src[{$this->contains(['aa.com'])}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['American Airlines, Inc.'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Boarding Pass']) && $this->http->XPath->query("//*[{$this->contains($dict['Boarding Pass'])}]")->length > 0
                && !empty($dict['Confirmation code']) && $this->http->XPath->query("//*[{$this->contains($dict['Confirmation code'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]info\.email\.aa\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $img = $parser->searchAttachmentByName('barcode\.png');

        $this->parsePass($email, $img);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    private function parsePass(Email $email, $img)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//tr[./td[normalize-space()][1][{$this->starts($this->t('Confirmation code'))}]]/following-sibling::tr[normalize-space()][1]/descendant::td[normalize-space()][1]", null, false, "/^([A-Z\d]{5,7})$/"));

        $f->addTraveller($travellerName = $this->http->FindSingleNode("//tr[./td[normalize-space()][1][{$this->starts($this->t('Terminal'))}]]/preceding-sibling::tr[normalize-space()][1]", null, false, "/^([[:alpha:]][-.\''[:alpha:] ]*[[:alpha:]])$/"), true);

        $s = $f->addSegment();

        $airInfo = $this->http->FindSingleNode("//tr[./td[normalize-space()][1][{$this->eq($this->t('Flight'))}]]/following-sibling::tr[1]/descendant::td[normalize-space()][1]", null, false, "/^((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]+\b\d{1,})$/");

        if (preg_match("/^((?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))[ ]+\b(\d{1,})$/", $airInfo, $info)){
            $s->airline()
                ->name($info[1])
                ->number($info[2]);
        }

        $flightCodes = $this->http->FindSingleNode("//tr[./td[normalize-space()][1][{$this->starts($this->t('Terminal'))}]]/preceding-sibling::tr[normalize-space()][last()]", null, false, "/^([A-Z]{3}[ ]+{$this->opt($this->t('to'))}[ ]+[A-Z]{3})$/");

        if (preg_match("/^([A-Z]{3})[ ]+{$this->opt($this->t('to'))}[ ]+([A-Z]{3})$/", $flightCodes, $code)){
            $s->departure()
                ->code($code[1]);
            $s->arrival()
                ->code($code[2]);
        }

        $flightDate = $this->http->FindSingleNode("//tr[./td[normalize-space()][1][{$this->eq($flightCodes)}]]/following-sibling::tr[normalize-space()][1]", null, false, "/^([[:alpha:]]+[ ]+[[:alpha:]]+[ ]+\d{1,2}\,[ ]+\d{4})$/");

        $depTime = $this->http->FindSingleNode("//tr[./td[normalize-space()][2][{$this->eq($this->t('Departs'))}]]/following-sibling::tr[1]/descendant::td[normalize-space()][2]", null, false, "/^(\d{1,2}\:\d{2}[ ]*A?P?M?)$/");

        if ($flightDate !== null && $depTime !== null) {
            $s->departure()
                ->date(strtotime($flightDate . ' ' . $depTime));
        }

        $depTerminal = $this->http->FindSingleNode("//tr[./td[normalize-space()][1][{$this->eq($this->t('Terminal'))}]]/following-sibling::tr[1]/descendant::td[normalize-space()][1]", null, false, "/^([A-Z0-9]+)$/");

        if ($depTerminal !== null) {
            $s->departure()
                ->terminal($depTerminal);
        }

        $seatInfo = $this->http->FindSingleNode("//tr[./td[normalize-space()][3][{$this->eq($this->t('Seat'))}]]/following-sibling::tr[1]/descendant::td[normalize-space()][3]", null, false, "/^([0-9]+[A-Z]+)$/");

        if ($seatInfo !== null) {
            $s->extra()
                ->seat($seatInfo, false, false, $travellerName);
        }

        $s->arrival()
            ->noDate();

        if (!empty($img) && count($img) == 1) {
            $bp = $email->add()->bpass();

            $bp
                ->setDepCode($s->getDepCode())
                ->setDepDate($s->getDepDate())
                ->setFlightNumber($s->getAirlineName() . ' ' . $s->getFlightNumber())
                ->setAttachmentName("barcode.png")
                ->setTraveller($f->getTravellers()[0][0])
                ->setRecordLocator($f->getConfirmationNumbers()[0][0]);
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
                return str_replace(' ', '\s+', preg_quote($s));
            }, $field)) . ')';
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
                return 'contains(' . $text . ',"' . $s . '")';
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
                return 'starts-with(normalize-space(.),"' . $s . '")';
            }, $field)) . ')';
    }
}
