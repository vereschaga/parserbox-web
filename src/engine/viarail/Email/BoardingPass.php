<?php

namespace AwardWallet\Engine\viarail\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "viarail/it-2307427.eml, viarail/it-2307542.eml, viarail/it-30181810.eml, viarail/it-607656064.eml, viarail/it-79559396.eml";

    public static $dict = [
        'en' => [
            'PASSENGER:'      => ['PASSENGER:', 'PASSENGER :'],
            'Confirmation #:' => ['Confirmation #:', 'Confirmation # :'],
            'Date:'           => ['Date:', 'Date :'],
            'Departure:'      => ['Departure:', 'Departure :'],
            'Arrival:'        => ['Arrival:', 'Arrival :'],
            'BOARDING PASS'   => 'BOARDING PASS',
            'Train #'         => 'Train #',
            'Class'           => 'Class',
            'Car'             => 'Car',
            'Seat'            => 'Seat',
            'Ticket Type:'    => 'Ticket Type:',
            'Open ticket'     => ['Open ticket', 'Open Ticket'],
        ],
        'fr' => [
            'PASSENGER:'      => ['PASSAGER:', 'PASSAGER :'],
            'Confirmation #:' => ['N° confirmation:', 'N° confirmation :'],
            'Date:'           => ['Date:', 'Date :'],
            'Departure:'      => ['Départ:', 'Départ :'],
            'Arrival:'        => ['Arrivée:', 'Arrivée :'],
            'BOARDING PASS'   => 'CARTE D\'EMBARQUEMENT',
            'Train #'         => 'N° Train',
            'Class'           => 'Classe',
            'Car'             => 'Voiture',
            'Seat'            => 'Siège',
            'Ticket Type:'    => 'Type de billet:',
            'Open ticket'     => 'Billet ouvert',
        ],
    ];

    public $lang = '';

    private $langDetectorsHtml = [
        'en' => ['Arrival:', 'Arrival :'],
        'fr' => ['Arrival:', 'Arrival :'],
    ];
    private $langDetectorsPdf = [
        'en' => ['Arrival:', 'Arrival :'],
        'fr' => ['Arrivée:', 'Arrivée :'],
    ];
    /** @var \HttpBrowser */
    private $pdf;
    private $pdfFileNames = [];
    private $jpgFileNames = [];

    private $patterns = [
        'time' => '\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?',
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'VIA Rail Canada') !== false
            || stripos($from, '@viarail.ca') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return stripos($headers['subject'], 'Boarding pass') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $detectProvider = false;

        // Detect Provider (HTML)
        if ($this->http->XPath->query('//node()[contains(normalize-space(.),"VIA Rail Canada") or contains(.,"@viarail.ca")]')->length > 0) {
            $detectProvider = true;
        }

        // Detect Language (HTML)
        $detectLanguage = $this->assignLangHtml();

        if ($detectProvider && $detectLanguage) {
            return true;
        }

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs)) {
            $this->pdf = clone $this->http;
        }

        foreach ($pdfs as $pdf) {
            $htmlPdf = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);

            if (empty($htmlPdf)) {
                continue;
            }
            $htmlPdf = str_replace([' ', '&#160;', '&nbsp;', '  '], ' ', $htmlPdf);
            $this->pdf->SetEmailBody($htmlPdf);

            // Detect Provider (PDF)
            if (!$detectProvider) {
                $detectProvider = $this->assignProviderPdf();
            }

            // Detect Language (PDF)
            if (!$detectLanguage) {
                $detectLanguage = $this->assignLangPdf();
            }

            if ($detectProvider && $detectLanguage) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        // Detect Language (HTML)
        $detectLanguage = $this->assignLangHtml();

        if ($detectLanguage) {
            $jpgs = $parser->searchAttachmentByName('.*jpg');

            foreach ($jpgs as $jpg) {
                $this->jpgFileNames[] = $this->getAttachmentNameJpg($parser, $jpg);
            }
        }

        $htmlPdfFull = '';
        $this->pdf = clone $this->http;
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $htmlPdf = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);

            if (empty($htmlPdf)) {
                continue;
            }
            $htmlPdf = str_replace([' ', '&#160;', '&nbsp;', '  '], ' ', $htmlPdf);
            $this->pdf->SetEmailBody($htmlPdf);

            // Detect Language (PDF)
            if ($this->assignLangPdf()) {
                $detectLanguage = true;
                $htmlPdfFull .= $htmlPdf;
                $this->pdfFileNames[] = $this->getAttachmentName($parser, $pdf);
            }
        }

        if (!$detectLanguage) {
            $this->logger->debug('Can\'t determine a language!');

            return $email;
        }

        $type = 'Html';
        $result = $this->parseHtml($email);

        if ($result !== true && $htmlPdfFull) {
            $this->pdf->SetEmailBody($htmlPdfFull);
            $this->parsePdf($email);
            $type = 'Pdf';
        }

        $email->setType('BoardingPass' . $type . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $types = 2; //pdf + html
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    private function parseHtml(Email $email)
    {
        $this->logger->debug(__METHOD__);

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Train #'))}]")->length > 0) {
            $this->parseTrainHtml($email);
        } elseif ($this->http->XPath->query("//text()[{$this->contains($this->t('Bus #'))}]")->length > 0) {
            $this->parseBusHtml($email);
        }

        return true;
    }

    private function parsePdf(Email $email)
    {
        $this->logger->debug(__METHOD__);
        $boardingPasses = $this->pdf->XPath->query('//p[' . $this->starts($this->t('Confirmation #:')) . ']');

        foreach ($boardingPasses as $key => $root) {
            $t = $email->add()->train();

            // travellers
            $passenger = $this->pdf->FindSingleNode('./preceding::p[position()<10][' . $this->starts($this->t('PASSENGER:')) . ']/following::p[normalize-space(.)][1]', $root);

            if ($passenger) {
                $passenger = preg_replace('/^(.+),\s*(?:Adult|Adulte|Child|Infant)$/isu', '$1', $passenger);
            }
            $t->addTraveller($passenger);

            // accountNumbers
            $acc = $this->pdf->FindSingleNode("preceding::p[position()<10][{$this->starts($this->t('VIA PRÉFÉRENCE'))}]/following::p[normalize-space()][1]", $root, false, '/^\d+\*+\d+$/');

            if (!empty($acc)) {
                $t->program()->account($acc, true);
            }

            // confirmationNumber
            $confirmationTitle = $this->pdf->FindSingleNode('.', $root, true, '/^(' . $this->opt($this->t('Confirmation #:')) . ')/');
            $confirmation = $this->pdf->FindSingleNode('.', $root, true, '/^' . $this->opt($this->t('Confirmation #:')) . '\s*([A-Z\d]{5,})$/u');

            if (empty($confirmation)) {
                $confirmation = $this->pdf->FindSingleNode('(following::p)[1]', $root, true, '/^[A-Z\d]{5,}$/');
            }

            if ($confirmation) {
                $t->general()->confirmation($confirmation, str_replace(':', '', $confirmationTitle));
            }

            $s = $t->addSegment();

            $xpathFragmentDep1 = './following::p[position()<10][' . $this->starts($this->t('Departure:')) . ']';
            $xpathFragmentDep2 = $xpathFragmentDep1 . '/preceding::p[position()<5][' . $this->starts($this->t('Date:')) . ']';

            // depDate
            $dateDep = $this->pdf->FindSingleNode($xpathFragmentDep2 . '/following::p[normalize-space(.)][1]', $root);
            $timeDep = $this->pdf->FindSingleNode($xpathFragmentDep1 . '/following::p[normalize-space(.)][1]', $root, true, '/^(' . $this->patterns['time'] . ')$/');

            if ($dateDep && $timeDep) {
                $dateTimeDep = strtotime($this->normalizeTime($timeDep), $this->normalizeDate($dateDep));
                $s->departure()->date($dateTimeDep);
            }

            // depName
            $depName = $this->pdf->FindSingleNode($xpathFragmentDep2 . '/preceding::p[normalize-space(.)][1][ ./descendant::b[normalize-space(.)] ]', $root);

            if (empty($depName)) {
                $depName = $this->pdf->FindSingleNode($xpathFragmentDep2 . '/preceding::p[normalize-space(.)][1]', $root);
            }

            $s->departure()
                ->name($depName)
                ->geoTip('ca');

            $xpathFragmentArr1 = './following::p[position()<20][' . $this->starts($this->t('Arrival:')) . ']';
            $xpathFragmentArr2 = $xpathFragmentArr1 . '/preceding::p[position()<5][' . $this->starts($this->t('Date:')) . ']';

            // arrDate
            $dateArr = $this->pdf->FindSingleNode($xpathFragmentArr2 . '/following::p[normalize-space(.)][1]', $root);
            $timeArr = $this->pdf->FindSingleNode($xpathFragmentArr1 . '/following::p[normalize-space(.)][1]', $root, true, '/^(' . $this->patterns['time'] . ')$/');

            if ($dateArr && $timeArr) {
                $dateTimeArr = strtotime($this->normalizeTime($timeArr), $this->normalizeDate($dateArr));
                $s->arrival()->date($dateTimeArr);
            }

            // arrName
            $arrName = $this->pdf->FindSingleNode($xpathFragmentArr2 . '/preceding::p[normalize-space(.)][1][ ./descendant::b[normalize-space(.)] ]', $root);

            if (empty($arrName)) {
                $arrName = $this->pdf->FindSingleNode($xpathFragmentArr2 . '/preceding::p[normalize-space(.)][1]', $root);
            }

            $s->arrival()
                ->name($arrName)
                ->geoTip('ca');

            $xpathFragment1 = './following::p[position()<30]';

            // number
            $s->extra()->number($this->pdf->FindSingleNode($xpathFragment1 . '[' . $this->starts($this->t('Train #')) . ']/following::p[normalize-space(.)][1]', $root, true, '/^(\d+)$/'));

            // cabin
            $s->extra()->cabin($this->pdf->FindSingleNode($xpathFragment1 . '[' . $this->eq($this->t('Class')) . ']/following::p[normalize-space(.)][1]', $root, true, '/^[\s\t]*([\w ]+)[\s\t]*(?:[­-]+|$)/i'), false, true);

            // carNumber
            $s->extra()->car($this->pdf->FindSingleNode($xpathFragment1 . '[' . $this->eq($this->t('Car')) . ']/following::p[normalize-space(.)][1]', $root, true, '/^(\d+)$/'), false, true);

            // seats
            $seat = $this->pdf->FindSingleNode($xpathFragment1 . '[' . $this->eq($this->t('Seat')) . ']/following::p[normalize-space(.)][1]', $root, true, '/^(\d{1,3}[A-Z])\b/');

            if ($seat) {
                $s->extra()->seat($seat);
            }

            // Boarding Pass
            /*if (!empty($this->pdfFileNames[$key])) {
                $bp = $email->createBoardingPass();
                $bp->setAttachmentName($this->pdfFileNames[$key]);

                if (!empty($t->getTravellers()[0])) {
                    $bp->setTraveller($t->getTravellers()[0][0]);
                }

                if (!empty($t->getConfirmationNumbers()[0])) {
                    $bp->setRecordLocator($t->getConfirmationNumbers()[0][0]);
                }
                $bp->setDepDate($s->getDepDate());
                $bp->setFlightNumber($s->getNumber());
            }*/
        }
    }

    private function parseTrainHtml(Email $email)
    {
        $xpath = "//text()[{$this->eq($this->t('BOARDING PASS'))}]/ancestor::table[{$this->contains($this->t('Departure:'))}][1][count(./descendant::text()[{$this->eq($this->t('BOARDING PASS'))}])=1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            return false;
        } else {
            $this->logger->debug("[XPATH]: " . $xpath);

            foreach ($nodes as $root) {
                if (!empty($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Ticket Type:'))}]/following::text()[normalize-space()][1][" . $this->contains($this->t('Open ticket')) . "]", $root))) {
                    continue;
                }

                $t = $email->add()->train();

                // travellers
                $passenger = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('PASSENGER:'))}]/following::text()[normalize-space()!=''][1]",
                    $root);
                $t->addTraveller(trim($passenger, ' :,'));
                $acc = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('VIA PRÉFÉRENCE'))}]/following::text()[normalize-space()!=''][1]",
                    $root, false, "/^(\d+\*+\d+)$/");

                if (!empty($acc)) {
                    $t->program()
                        ->account($acc, true);
                }

                // confirmationNumber
                $confirmationTitle = trim($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Confirmation #:'))}]",
                    $root), " :");
                $confirmation = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Confirmation #:'))}]/following::text()[normalize-space()!=''][1]",
                    $root, false, '/^([A-Z\d]{5,})$/');

                if ($confirmation) {
                    $t->general()->confirmation($confirmation, str_replace(':', '', $confirmationTitle));
                }

                $s = $t->addSegment();

                // depDate
                $dateDep = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Departure:'))}]/preceding::text()[normalize-space()!=''][1]",
                    $root);
                $timeDep = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Departure:'))}]/following::text()[normalize-space()!=''][1]",
                    $root, false, '/^(' . $this->patterns['time'] . ')$/');

                if ($dateDep && $timeDep) {
                    $dateTimeDep = strtotime($this->normalizeTime($timeDep), $this->normalizeDate($dateDep));
                    $s->departure()
                        ->date($dateTimeDep)
                        ->geoTip('ca');
                }

                // depName
                $s->departure()
                    ->name($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Departure:'))}]/preceding::text()[normalize-space()!=''][3]", $root) . ', Canada')
                    ->geoTip('ca');

                // arrDate
                $dateArr = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Arrival:'))}]/preceding::text()[normalize-space()!=''][1]",
                    $root);
                $timeArr = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Arrival:'))}]/following::text()[normalize-space()!=''][1]",
                    $root, true, '/^(' . $this->patterns['time'] . ')$/');

                if ($dateArr && $timeArr) {
                    $dateTimeArr = strtotime($this->normalizeTime($timeArr), $this->normalizeDate($dateArr));
                    $s->arrival()->date($dateTimeArr);
                }

                // arrName
                $s->arrival()
                    ->name($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Arrival:'))}]/preceding::text()[normalize-space()!=''][3]", $root) . ', Canada')
                    ->geoTip('ca');

                // number
                $s->extra()->number($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Train #'))}]/following::text()[normalize-space(.)!=''][1]",
                    $root, true, '/^(\d+)$/'));

                // cabin
                $s->extra()->cabin($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Class'))}]/following::text()[normalize-space(.)!=''][1]",
                    $root, true, '/^[\s\t]*(.+?)\s*(?:\-|$)/i'), false, true);

                // carNumber
                $s->extra()->car($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Car'))}]/following::text()[normalize-space(.)!=''][1]",
                    $root, true, '/^(\d+)$/'), false, true);

                // seats
                $seat = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Seat'))}]/following::text()[normalize-space(.)!=''][1]",
                    $root, true, '/^(\d{1,3}[A-Z])\b/');

                if ($seat) {
                    $s->extra()->seat($seat);
                }
                $src = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('BOARDING PASS'))}]/preceding::img[1]/@src",
                    $root);
                $srcOnError = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('BOARDING PASS'))}]/preceding::img[1]/@onerror",
                    $root, false, "/this.src=[\"']([^\"']+)[\"']$/");
                // Boarding Pass
                if (!empty($src) || !empty($srcOnError)) {
                    if (!empty($srcOnError) && preg_match("/^https?:\/\/\S+$/", $srcOnError) and stripos($srcOnError,
                            'barcode') !== false
                    ) {
                        $url = $srcOnError;
                    } elseif (preg_match("/^cid:(.+\.jpg)(?:@.+)?$/", $src, $m) && in_array($m[1],
                            $this->jpgFileNames)
                    ) {
                        $attach = $m[1];
                    } elseif (preg_match("/^https?:\/\/\S+$/", $src) and stripos($src, 'barcode') !== false) {
                        $url = $src;
                    } else {
                        $noBP = true;
                    }

                    /*if (!isset($noBP)) {
                        $bp = $email->createBoardingPass();

                        if (isset($url)) {
                            $bp->setUrl($url);
                        } elseif (isset($attach)) {
                            $bp->setAttachmentName($attach);
                        }

                        if (!empty($t->getTravellers()[0])) {
                            $bp->setTraveller($t->getTravellers()[0][0]);
                        }

                        if (!empty($t->getConfirmationNumbers()[0])) {
                            $bp->setRecordLocator($t->getConfirmationNumbers()[0][0]);
                        }
                        $bp->setDepDate($s->getDepDate());
                        $bp->setFlightNumber($s->getNumber());
                    }*/
                }
            }
        }
    }

    private function parseBusHtml(Email $email)
    {
        $xpath = "//text()[{$this->eq($this->t('BOARDING PASS'))}]/ancestor::table[{$this->contains($this->t('Departure:'))}][1][count(./descendant::text()[{$this->eq($this->t('BOARDING PASS'))}])=1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            return false;
        } else {
            $this->logger->debug("[XPATH]: " . $xpath);

            foreach ($nodes as $root) {
                if (!empty($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Ticket Type:'))}]/following::text()[normalize-space()][1][" . $this->contains($this->t('Open ticket')) . "]", $root))) {
                    continue;
                }

                $t = $email->add()->bus();

                // travellers
                $passenger = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('PASSENGER:'))}]/following::text()[normalize-space()!=''][1]",
                    $root);
                $t->addTraveller(trim($passenger, ' :,'));
                $acc = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('VIA PRÉFÉRENCE'))}]/following::text()[normalize-space()!=''][1]",
                    $root, false, "/^(\d+\*+\d+)$/");

                if (!empty($acc)) {
                    $t->program()
                        ->account($acc, true);
                }

                // confirmationNumber
                $confirmationTitle = trim($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Confirmation #:'))}]",
                    $root), " :");
                $confirmation = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Confirmation #:'))}]/following::text()[normalize-space()!=''][1]",
                    $root, false, '/^([A-Z\d]{5,})$/');

                if ($confirmation) {
                    $t->general()->confirmation($confirmation, str_replace(':', '', $confirmationTitle));
                }

                $s = $t->addSegment();

                // depDate
                $dateDep = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Departure:'))}]/preceding::text()[normalize-space()!=''][1]",
                    $root);
                $timeDep = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Departure:'))}]/following::text()[normalize-space()!=''][1]",
                    $root, false, '/^(' . $this->patterns['time'] . ')$/');

                if ($dateDep && $timeDep) {
                    $dateTimeDep = strtotime($this->normalizeTime($timeDep), $this->normalizeDate($dateDep));
                    $s->departure()
                        ->date($dateTimeDep);
                }

                // depName
                $s->departure()
                    ->name($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Departure:'))}]/preceding::text()[normalize-space()!=''][3]", $root) . ', Canada');

                // arrDate
                $dateArr = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Arrival:'))}]/preceding::text()[normalize-space()!=''][1]",
                    $root);
                $timeArr = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Arrival:'))}]/following::text()[normalize-space()!=''][1]",
                    $root, true, '/^(' . $this->patterns['time'] . ')$/');

                if ($dateArr && $timeArr) {
                    $dateTimeArr = strtotime($this->normalizeTime($timeArr), $this->normalizeDate($dateArr));
                    $s->arrival()->date($dateTimeArr);
                }

                // arrName
                $s->arrival()
                    ->name($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Arrival:'))}]/preceding::text()[normalize-space()!=''][3]", $root) . ', Canada');

                // number
                $s->extra()->number($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Bus #'))}]/following::text()[normalize-space(.)!=''][1]",
                    $root, true, '/^(\d+)$/'));

                // cabin
                $s->extra()->cabin($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Class'))}]/following::text()[normalize-space(.)!=''][1]",
                    $root, true, '/^[\s\t]*(.+?)\s*(?:\-|$)/i'), false, true);

                // seats
                $seat = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Seat'))}]/following::text()[normalize-space(.)!=''][1]",
                    $root, true, '/^(\d{1,3}[A-Z])\b/');

                if ($seat) {
                    $s->extra()->seat($seat);
                }
                $src = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('BOARDING PASS'))}]/preceding::img[1]/@src",
                    $root);
                $srcOnError = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('BOARDING PASS'))}]/preceding::img[1]/@onerror",
                    $root, false, "/this.src=[\"']([^\"']+)[\"']$/");
                // Boarding Pass
                if (!empty($src) || !empty($srcOnError)) {
                    if (!empty($srcOnError) && preg_match("/^https?:\/\/\S+$/", $srcOnError) and stripos($srcOnError,
                            'barcode') !== false
                    ) {
                        $url = $srcOnError;
                    } elseif (preg_match("/^cid:(.+\.jpg)(?:@.+)?$/", $src, $m) && in_array($m[1],
                            $this->jpgFileNames)
                    ) {
                        $attach = $m[1];
                    } elseif (preg_match("/^https?:\/\/\S+$/", $src) and stripos($src, 'barcode') !== false) {
                        $url = $src;
                    } else {
                        $noBP = true;
                    }

                    /*if (!isset($noBP)) {
                        $bp = $email->createBoardingPass();

                        if (isset($url)) {
                            $bp->setUrl($url);
                        } elseif (isset($attach)) {
                            $bp->setAttachmentName($attach);
                        }

                        if (!empty($t->getTravellers()[0])) {
                            $bp->setTraveller($t->getTravellers()[0][0]);
                        }

                        if (!empty($t->getConfirmationNumbers()[0])) {
                            $bp->setRecordLocator($t->getConfirmationNumbers()[0][0]);
                        }
                        $bp->setDepDate($s->getDepDate());
                        $bp->setFlightNumber($s->getNumber());
                    }*/
                }
            }
        }
    }

    private function normalizeTime(string $string): string
    {
        if (preg_match('/^((\d{1,2})[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', $string, $m) && (int) $m[2] > 12) {
            $string = $m[1];
        } // 21:51 PM    ->    21:51
        $string = preg_replace('/^(0{1,2}[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', '$1', $string); // 00:25 AM    ->    00:25

        return $string;
    }

    private function normalizeDate($date)
    {
        $in = [
            // Wed. Jan 7, 2015
            '/^([A-z]{3})\.\s([A-z]{3})\s(\d{1,2}),\s(\d{4})$/u',
            // Ven. 31 janv. 2020
            '/^([A-z]{3})\.\s(\d{1,2})\s(...).+(\d{4})$/u',
        ];
        $out = [
            '$3 $2 $4',
            '$2 $3 $4',
        ];

        $str = preg_replace($in, $out, $date);

        if (preg_match("/\d{1,2}\s(...)\s\d{4}/u", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                return strtotime(str_replace($m[1], $en, $str));
            }
        }

        return false;
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function getAttachmentName(\PlancakeEmailParser $parser, $pdf)
    {
        $header = $parser->getAttachmentHeader($pdf, 'Content-Type');

        if (preg_match('/name=[\"\']*(.+\.pdf)[\'\"]*/i', $header, $matches)) {
            return $matches[1];
        }

        return false;
    }

    private function getAttachmentNameJpg(\PlancakeEmailParser $parser, $jpg)
    {
        $header = $parser->getAttachmentHeader($jpg, 'Content-Type');

        if (preg_match('/name=[\"\']*(.+\.jpg)[\'\"]*/i', $header, $matches)) {
            return $matches[1];
        }

        return false;
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignProviderPdf(): bool
    {
        if ($this->pdf->XPath->query('//node()[contains(normalize-space(),"You can be notified of the VIA train") or contains(normalize-space(),"Join VIA Préférence") or contains(normalize-space(),"To thank you for making a reservation with VIA Rail") or contains(.,"reservia.viarail.ca")]')->length > 0
            || $this->pdf->XPath->query('//a[contains(@href,"//www.viapreference.com") or contains(@href,"//www.viarail.ca") or contains(@href,"//reservia.viarail.ca")]')->length > 0
        ) {
            return true;
        }

        return false;
    }

    private function assignLangHtml(): bool
    {
        foreach ($this->langDetectorsHtml as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLangPdf(): bool
    {
        foreach ($this->langDetectorsPdf as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->pdf->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
