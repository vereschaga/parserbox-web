<?php

namespace AwardWallet\Engine\marriott\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class YourAccountUpdate extends \TAccountChecker
{
    public $mailFiles = "marriott/it-108334029.eml, marriott/it-109069353.eml, marriott/statements/it-62812090.eml, marriott/statements/it-62919390.eml, marriott/statements/it-62938723.eml, marriott/statements/it-64401491.eml, marriott/statements/it-64415901.eml, marriott/statements/it-76376494.eml, marriott/statements/it-76377408.eml";

    private $subjects = [
        'en' => ['Your Password Request Has Been Received', 'Your Temporary Code Request', 'Account Update:'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[-.@]marriott\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Marriott') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,"email-marriott.com/")] | //node()[contains(normalize-space(),"unsubscribe from Marriott") or contains(normalize-space(),"www.marriott.com")]')->length === 0
        ) {
            return false;
        }

        return $this->isMembership($parser->getPlainBody()) || $this->findRoot1()->length === 1
            || $this->findRoot2()->length === 1 || $this->findRoot3()->length === 1 || $this->findRoot4()->length === 1 || $this->findRoot5()->length === 1;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (stripos($parser->getSubject(), 'Your Temporary Code Request') !== false) {
            $code = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'code on your screen:')]", null, true, "/code on your screen\:\s*(\d+)/");

            if (empty($code)) {
                $code = $this->re("/code on your screen\:\s*(\d+)/", $parser->getPlainBody());
            }

            if (!empty($code)) {
                $otс = $email->add()->oneTimeCode();
                $otс->setCode($code);
            }
        }

        $st = $email->add()->statement();

        if ($this->isMembership($parser->getPlainBody())) {
            // it-62919390.eml, it-62938723.eml
            $st->setMembership(true);

            return $email;
        }

        $status = $name = $balance = $number = null;

        // it-62812090.eml, it-76377408.eml, it-76376494.eml
        $roots1 = $this->findRoot1();

        if ($roots1->length === 1) {
            $this->logger->debug('Found root1.');
            $root1 = $roots1->item(0);
            $headerText = $this->http->FindSingleNode('.', $root1);

            if (preg_match("/^([^|]{3,}?)\s*\|\s*([- ]*\d[,.\'\d ]*?)\s*point/i", $headerText, $m)) {
                // Silver  |  359320 Points    or    Silver Elite  |  -47,455 Points
                $status = $m[1];
                $balance = $m[2];
            }
        }

        // it-64401491.eml
        $roots2 = $this->findRoot2();

        if ($roots2->length === 1) {
            $this->logger->debug('Found root2.');
            $root2 = $roots2->item(0);

            if ($roots1->length === 0) {
                $st->setNoBalance(true);
            }

            $name = $this->http->FindSingleNode('tr[1]', $root2, true, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');

            $number = $this->http->FindSingleNode('tr[2]', $root2, true, '/^([X\d]{5,})\s*|.+$/i');
        }

        // it-64415901.eml
        $roots3 = $this->findRoot3();

        if ($roots3->length === 1) {
            $this->logger->debug('Found root3.');
            $root3 = $roots3->item(0);

            $name = implode(' ', $this->http->FindNodes("ancestor::*[ preceding-sibling::*[normalize-space()] ][1]/preceding-sibling::*[normalize-space()]/descendant::node()[normalize-space()='member name']/following::tr[normalize-space()][1]/descendant::text()[normalize-space()]", $root3));

            if (!$name) {
                $name = implode(' ', $this->http->FindNodes("ancestor::*[ preceding-sibling::*[normalize-space()] ][1]/preceding-sibling::*[normalize-space()][last()]/descendant::tr[not(.//tr) and normalize-space()][1]/descendant::text()[normalize-space()]", $root3));
            }

            if (!preg_match('/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u', $name)) {
                $name = null;
            }

            $number = $this->http->FindSingleNode("*[1]/descendant::tr[ *[1][normalize-space()='ACCOUNT'] ]/*[position()>1]", $root3, true, '/^[X\d]{5,}$/');

            $balance = $this->http->FindSingleNode("*[1]/descendant::tr[ *[1][normalize-space()='POINTS'] ]/*[position()>1]", $root3, true, '/^[- ]*\d[,.\'\d ]*$/');

            if ($balance === null) {
                $st->setNoBalance(true);
            }

            $status = $this->http->FindSingleNode("*[2]/descendant::tr[ *[1][normalize-space()='STATUS'] ]/*[position()>1]", $root3);
        }

        // it-109069353.eml
        $roots4 = $this->findRoot4();
//        0 points                                      Member                                   XXXXX6097
//        Tormund Reed                                                                        0
//        You’re 10 nights from Marriott Bonvoy® Silver Elite status.                   Nights This Year
//        » My benefits                                                             » Book your first stay
        if ($roots4->length === 1) {
            $this->logger->debug('Found root4.');
            $root4 = $roots4->item(0);

            $name = $this->http->FindSingleNode("following::td[not(.//td)][normalize-space()][1][following::text()[normalize-space()][1][starts-with(normalize-space(), 'You’re')]]", $root4);

            if (!preg_match('/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u', $name)) {
                $name = null;
            }

            $number = $this->http->FindSingleNode("*[3]", $root4, true, '/^[X\d]{5,}$/');

            $balance = $this->http->FindSingleNode("*[1]", $root4, true, '/^\s*([\d, ]+) points?\s*$/');

            $status = trim($this->http->FindSingleNode("*[2]", $root4), '|');

            $nights = $this->http->FindSingleNode("following::text()[normalize-space() = 'Nights This Year' or normalize-space() = 'Night This Year'][1]/preceding::text()[normalize-space()][1]", $root4, true, "/^\s*(\d+)\s*$/");
            $st->addProperty('Nights', $nights);
        }

        // it-108334029.eml
        $roots5 = $this->findRoot5();
//              Tormund Reed
//      Member | 11018 POINTS | 3 NIGHTS
//      » view ACTIVITY   » SEE BENEFITS
        if ($roots5->length === 1) {
            $this->logger->debug('Found root5.');
            $root5 = $roots5->item(0);

            $name = $this->http->FindSingleNode("preceding::text()[normalize-space()][1]", $root5);

            if (!preg_match('/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u', $name)) {
                $name = null;
            }

            $info = $this->http->FindSingleNode("self::text()", $root5);
            if (preg_match("/^\s*([[:alpha:] ]+?)\s*\|\s*(\d+) POINTS?\s*\|\s*(\d+) NIGHTS?\s*$/", $info, $m)) {

                $isMembership = true;
                $balance = $m[2];
                $status = $m[1];
                $st->addProperty('Nights', $m[3]);
            }
        }

        if (preg_match('/^[X]{3,}(\d+)$/i', $number, $m)) {
            // XXXXX6297
            $st->setNumber($m[1])->masked()
                ->setLogin($m[1])->masked();
        } elseif ($roots2->length === 1 || $roots3->length === 1) {
            // 143926297
            $st->setNumber($number)
                ->setLogin($number);
        } elseif (isset($isMembership) && $isMembership === true) {
            $st->setMembership(true);
        }

        if ($name !== null) {
            $st->addProperty('Name', $name);
        }

        if ($status !== null) {
            $st->addProperty('Level', $status);
        }

        if ($balance !== null) {
            if (substr($balance, 0, 1) === '-') {
                // it-76376494.eml
                $st->setNoBalance(true);
            } else {
                $st->setBalance($this->normalizeAmount($balance));
            }
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function isMembership(?string $text = ''): bool
    {
        $phrases = [
            'You recently requested a temporary code in order to complete an Account transaction. To authorize and proceed with your transaction, please enter the following code on your screen',
            'password request has been received. Please reset your password immediately using this secure link',
        ];

        foreach ($phrases as $phrase) {
            if (!empty($text) && stripos($text, $phrase) !== false
                || $this->http->XPath->query('//node()[contains(normalize-space(),"' . $phrase . '")]')->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    private function findRoot1(): \DOMNodeList
    {
        return $this->http->XPath->query("//tr[ descendant::text()[normalize-space()='My Account'] ]/following-sibling::tr[normalize-space()][1][contains(.,'|')]");
    }

    private function findRoot2(): \DOMNodeList
    {
        return $this->http->XPath->query("//*[ tr[1] and tr[2]/descendant::text()[starts-with(normalize-space(),'My Benefits')] ]");
    }

    private function findRoot3(): \DOMNodeList
    {
        return $this->http->XPath->query("//tr[ count(*)=2 and *[1]/descendant::text()[normalize-space()='ACCOUNT'] and *[2]/descendant::text()[normalize-space()='STATUS'] ]");
    }

    private function findRoot4(): \DOMNodeList
    {
        return $this->http->XPath->query("//tr[count(*) = 3 and not(.//tr)][*[1][contains(., 'point')] and *[3][starts-with(normalize-space(), 'XXXX')]]");
    }
    private function findRoot5(): \DOMNodeList
    {
        return $this->http->XPath->query("//text()[contains(., 'POINTS') and contains(., 'NIGHTS') and contains(., '|')]");
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
