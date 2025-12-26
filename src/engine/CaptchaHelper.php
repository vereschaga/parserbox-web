<?php

namespace AwardWallet\Engine;

use CaptchaException;
use CheckRetryNeededException;
use Exception;
use MouseMover;
use RemoteWebDriver;
use SeleniumCheckerHelper;
use UnexpectedAlertOpenException;
use WebDriverBy;
use WebDriverException;

// TODO: should always be paired with SeleniumCheckerHelper (use in it only)
trait CaptchaHelper
{
    /* *
     * Pass "Choose Image" Captcha (this method should replace passChooseImageReCaptcha)
     * @link https://rucaptcha.com/api-recaptcha
     *
     * @param RemoteWebElement $elem iFrame with captcha
     * @param int $attemptsCount attempts to solve captcha
     * @param int $recognizeTimeout - max time recognition
     * @return true|false
     *
     * @deprecated - Don't use this method! It's too expensive because too many images needed solve
     * /

    protected function clickCaptcha ($elem, $attemptsCount = 10, $recognizeTimeout = 180) {
        $this->http->Log(__METHOD__);
        $startTimer = microtime(true);
        if (!$elem) {
            $this->http->Log('iFrame for captcha is not defined', LOG_LEVEL_ERROR);
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = $recognizeTimeout;

        $passed = false;

        for ($attempt = 0; $attempt < $attemptsCount; $attempt++) {
            $this->http->Log("Attempt # {$attempt}", LOG_LEVEL_ERROR);
            $this->driver->switchTo()->frame($elem);
            $this->saveResponse();
            $image = $this->waitForElement(WebDriverBy::xpath($this->googleIMG));
            $this->saveResponse();
            $this->driver->switchTo()->defaultContent();
            if (!$image) {
                $this->http->Log('Captcha images loading failed', LOG_LEVEL_ERROR);
                break;
            }
            // captcha screenshot
            $this->http->Log("take screenshot");
            $pathToScreenshot = $this->takeScreenshotOfElement($elem);
            if (!$pathToScreenshot) {
                $this->http->Log('Failed to get screenshot of iFrame with captcha', LOG_LEVEL_ERROR);
                break;
            }
//			$this->http->Log('Path to captcha screenshot '.$pathToScreenshot);
            $result = false;
            try {
                $this->driver->switchTo()->frame($elem);
                $this->http->Log("Doing captcha recognition...", LOG_LEVEL_ERROR);
                // Get images' coordinates
                try {
                    $parameters = ['coordinatescaptcha' => '1'];
                    // expanded parameters
                    if (!empty($previousID))
                        $parameters = array_merge($parameters, ["can_no_answer" => "1"]);
//                        $parameters = array_merge($parameters, ["previousID" => $previousID]);
                    unset($previousID);

//                    $this->http->Log("Set parameters: " . var_export($parameters, true));
                    $captcha = $recognizer->recognizeFile($pathToScreenshot, $parameters);
                }
                catch (CaptchaException $e) {
                    if (preg_match('#timelimit.*?hit#i', $e->getMessage()))
                        $this->http->Log('Timelimit reached');
                    throw $e;
                }// catch (CaptchaException $e)
                $this->http->Log('-----------------------------------------------------------------------------');
                $this->http->Log('[Response]: '.$captcha);
                // Report an incorrectly solved CAPTCHA.
                if (trim($captcha) === 'coordinate:') {
                    $recognizer->reportIncorrectlySolvedCAPTCHA();
                    continue;
                }
                $captcha = str_replace('coordinates:', '', $captcha);
                $this->http->Log('[Response]: '.$captcha);
                $this->http->Log('-----------------------------------------------------------------------------');
                $coordinates = explode(';', $captcha);
                if ($captcha != $this->noMatchingImages) {
                    if (!$coordinates) {
                        $this->http->Log('Could not split captcha response to image coordinates');
                        break;
                    }
                    // Click on coordinates
                    foreach ($coordinates as $coordinate) {
                        $point = explode(',', $coordinate);
                        if (count($point) != 2) {
                            $this->http->Log("Bad coordinates: {$coordinate}", LOG_LEVEL_ERROR);
                            continue;
                        }// if (count($point) != 2)
                        $x = explode('=', $point[0]);
                        $y = explode('=', $point[1]);

                        if (!isset($x[1]) || !isset($y[1])) {
                            $this->http->Log("Bad coordinates: x = {$point[0]}, y = {$point[1]}", LOG_LEVEL_ERROR);
                            continue;
                        }// if (!isset($x[1]) || !isset($y[1]))

                        $this->http->Log("Click on: x = {$x[1]}, y = {$y[1]}", LOG_LEVEL_ERROR);

                        $html = $this->driver->findElement(WebDriverBy::xpath('html'));
                        $coords = $html->getCoordinates();
                        $mouse = $this->driver->getMouse();
                        $mouse->mouseMove($coords, (int)$x[1], intval($y[1]));
                        $mouse->click();

                        usleep(random_int(400000, 1300000));
                    }// foreach ($selectedIndices as $i)
                }// if ($captcha != $this->noMatchingImages)

                // get captcha ID
                $previousID = $recognizer->getCaptchaID();

                sleep(1);
                // Unselect images, if not selected all the correct images (standard captcha)
                $imageSelected = $this->driver->findElements(WebDriverBy::xpath("//*[@class = 'rc-imageselect-tileselected']"));
                // there is no needed images
                if ($captcha == $this->noMatchingImages || $imageSelected) {
                    $this->http->Log("Click 'Verify' button");
                    $this->driver->findElement(WebDriverBy::id('recaptcha-verify-button'))->click();

                    // Handle errors
                    $errorsXpath = '//*[(@class="rc-imageselect-incorrect-response" or @class="rc-imageselect-error-select-one" or @class="rc-imageselect-error-select-more" or @class = "rc-imageselect-error-dynamic-more") and not(contains(@style, "display:none"))]';
                    if ($this->waitForElement(WebDriverBy::xpath($errorsXpath), 3)) {
                        $errors = [];
                        foreach ($this->driver->findElements(WebDriverBy::xpath($errorsXpath)) as $e)
                            $errors[] = $e->getText();
                        $error = implode($errors);
                        $this->http->Log("[Returned error]: " . $error, LOG_LEVEL_ERROR);
                        $this->saveResponse();


                        if ($imageSelected
                            && (strpos($error, 'Please select all matching images') !== false
                                || strpos($error, 'Выбраны не все подходящие изображения') !== false)
                        ) {
                            $this->http->Log("[Captcha type]: standard captcha, unselect images and send report an incorrectly solved CAPTCHA");
                            foreach ($coordinates as $coordinate) {
                                $point = explode(',', $coordinate);
                                if (count($point) != 2) {
                                    $this->http->Log("Bad coordinates: 'coordinates'", LOG_LEVEL_ERROR);
                                    continue;
                                }
                                $x = explode('=', $point[0]);
                                $y = explode('=', $point[1]);
                                $this->http->Log("Click on: x = {$x[1]}, y = {$y[1]}", LOG_LEVEL_ERROR);

                                $html = $this->driver->findElement(WebDriverBy::xpath('html'));
                                $coords = $html->getCoordinates();
                                $mouse = $this->driver->getMouse();
                                $mouse->mouseMove($coords, (int)$x[1], intval($y[1]));
                                $mouse->click();

                                usleep(random_int(400000, 1300000));
                            }// foreach ($selectedIndices as $i)
                        }// if ($images = $this->driver->findElements(WebDriverBy::xpath($this->googleIMG)))
                        elseif (!$imageSelected
                                && (strpos($error, 'Please select all matching images') !== false
                                    || strpos($error, 'Выбраны не все подходящие изображения') !== false)
                        ) {
                                unset($previousID);
                                $this->http->Log("[Captcha type]: difficult captcha with changing images, do not send report");
                        }

                        // Report an incorrectly solved CAPTCHA.
                        if (strstr($error, 'Please solve more to be verified') || strstr($error, 'Пройдите проверку ещё раз')
                            // Do not send report for difficult captcha with changing images
                            || (!$imageSelected
                                && (strstr($error, 'Please select all matching images') || strstr($error, 'Выбраны не все подходящие изображения')))
                            // Thai
                            || strstr($error, 'ต้องการคำตอบที่ถูกต้องหลายรายการ')
                            // Malaysian
                            || strstr($error, 'Sila pilih semua imej yang sepadan.')
                            // Arabic
                            || strstr($error, ' يُرجى حل المزيد'))
                            $recognizer->reportIncorrectlySolvedCAPTCHA();
                        else
                            $this->http->Log("[Unknown error]: " . $error);
                    }// if ($this->waitForElement(WebDriverBy::xpath($errorsXpath), 3))
                    elseif ($captcha === $this->noMatchingImages)
                        $result = true;
                }// if ($captcha == $this->noMatchingImages)
                $this->saveResponse();
            }
            catch (Exception $e) {
                $this->http->Log("[Captcha error]: ".$e->getMessage(), LOG_LEVEL_ERROR);
                break;
            }
            catch (UnexpectedAlertOpenException $e) {
                $this->http->Log("[Captcha error]: ".$e->getMessage(), LOG_LEVEL_ERROR);
                break;
            }
            finally {
                $this->driver->switchTo()->defaultContent();
                unlink($pathToScreenshot);
            }

            // success
            if ($result) {
                $this->http->Log('[Result]: Captcha passing attempt succeeded');
                $passed = true;
                break;
            }// if ($result)

            // fail
            $this->http->Log('[Result]: Captcha passing attempt failed', LOG_LEVEL_ERROR);
            if ($attempt == $attemptsCount - 1) {
                $this->http->Log("Failed to pass captcha after $attemptsCount attempts", LOG_LEVEL_ERROR);
                break;
            }// if ($attempt == $attemptsCount - 1)
            else {
                $this->http->Log('Trying to pass captcha again (attempt '.($attempt + 1).' of '.$attemptsCount.')', LOG_LEVEL_ERROR);
                continue;
            }
        }// for ($attempt = 0; $attempt < $attemptsCount; $attempt++)

        if ($passed)
            $this->http->Log('Captcha passed');
        else
            $this->http->Log('Captcha passing failed', LOG_LEVEL_ERROR);

        $this->http->Log("[Time recognizing: " . (microtime(true) - $startTimer) . "]");

        return $passed;
    }
    */

    protected function clickCloudFlareCheckbox($selenium, $tabCount = 4)
    {
        $this->logger->notice(__METHOD__);
        $isSeleniumMainEngine = true;

        if (!$selenium) {
            $selenium = $this;
            $isSeleniumMainEngine = false;
        }

        $this->logger->debug("[Tab count]: {$tabCount}");

        for ($n = 0; $n < $tabCount; $n++) {
            $selenium->driver->getKeyboard()->sendKeys("Tab");
            usleep(300000);
        }

        $this->logger->debug("clicking Verify you are human button");
        $this->saveLogs($isSeleniumMainEngine);
        $selenium->driver->getKeyboard()->sendKeys(" ");
    }

    protected function clickCloudFlareCheckboxByMouse(
        $selenium,
        $captchaElemXpath = '//div[@id and @style="display: grid;"] | //div[@id="ulp-auth0-v2-captcha"] | //div[@id="cf-turnstile"]',
        $xOffset = 35,
        $yOffset = 35
    ) {
        $this->logger->notice(__METHOD__);
        $isSeleniumMainEngine = true;

        if (!$selenium) {
            $selenium = $this;
            $isSeleniumMainEngine = false;
        }

        $this->logger->debug("xOffset: {$xOffset} / yOffset: {$yOffset}");

        $mover = new MouseMover($selenium->driver);
        $mover->logger = $selenium->logger;
        $mouse = $selenium->driver->getMouse();
        $mover->enableCursor();

        $captchaElem = $selenium->waitForElement(WebDriverBy::xpath($captchaElemXpath), 0);
        $selenium->saveLogs($isSeleniumMainEngine);

        if (!$captchaElem) {
            $this->logger->error("captchaElem not found");
            return false;
        }

        $mouse->mouseMove($captchaElem->getCoordinates());
        $selenium->saveLogs($isSeleniumMainEngine);

        if ($this->driver instanceof RemoteWebDriver) {
            // unsupported in new versions of webdriver
            $captchaCoords = $captchaElem->getCoordinates()->inViewPort();
        } else {
            $captchaCoords = $captchaElem->getLocation();
        }
        $x = $captchaCoords->getX();
        $y = $captchaCoords->getY();
        $this->logger->info(var_export([
            'x' => $x,
            'y' => $y,
        ], true), ['pre' => true]);

        $x = (int) ($x + $xOffset);
        $y = (int) ($y + $yOffset);
        $mover->moveToCoordinates(['x' => $x, 'y' => $y], ['x' => 0, 'y' => 0]);
        $selenium->saveLogs($isSeleniumMainEngine);
        $this->logger->debug("clicking Verify you are human button");

        try {
            $mouse->click();
        } catch (WebDriverException $e) {
            $this->logger->error("click failed");
            $this->logger->error("WebDriverException: " . $e->getMessage(), ['HtmlEncode' => true]);
        }

        return true;
    }

    protected function clickCaptchaCheckboxByMouseV2(
        $selenium,
        $captchaElemXpath = '//div[@id and @style="display: grid;"] | //div[@id="ulp-auth0-v2-captcha"] | //div[@id="cf-turnstile"]',
        $xOffset = 35,
        $yOffset = 35
    ) {
        $this->logger->notice(__METHOD__);
        $isSeleniumMainEngine = true;

        if (!$selenium) {
            $selenium = $this;
            $isSeleniumMainEngine = false;
        }

        $this->logger->debug("xOffset: {$xOffset} / yOffset: {$yOffset}");

        $mover = new MouseMover($selenium->driver);
        $mover->logger = $selenium->logger;
        $mouse = $selenium->driver->getMouse();
        $mover->enableCursor();

        $captchaElem = $selenium->waitForElement(WebDriverBy::xpath($captchaElemXpath), 0);
        $selenium->saveLogs($isSeleniumMainEngine);

        if (!$captchaElem) {
            $this->logger->error("captchaElem not found");
            return false;
        }

        $mouse->mouseMove(null, $captchaElem->getLocation()->getX() + $xOffset,
            $captchaElem->getLocation()->getY() + $yOffset);
        $selenium->saveLogs($isSeleniumMainEngine);

        try {
            $mouse->click();
        } catch (WebDriverException $e) {
            $this->logger->error("click failed");
            $this->logger->error("WebDriverException: " . $e->getMessage(), ['HtmlEncode' => true]);
        }

        return true;
    }

    /**
     * @deprecated
     * not working because shadow elements
     */
    protected function cloudFlareWorkaround($selenium = null)
    {
        $this->logger->notice(__METHOD__);

        $isSeleniumMainEngine = true;

        if (!$selenium) {
            $selenium = $this;
            $isSeleniumMainEngine = false;
        }

        $res = false;

        if ($verify = $selenium->waitForElement(WebDriverBy::xpath("//input[@value = 'Verify you are human']"), 0)) {
            $verify->click();
            $res = true;
        }

        if ($iframe = $selenium->waitForElement(WebDriverBy::xpath("//div[@id = 'turnstile-wrapper' or @id = 'ulp-auth0-v2-captcha']//iframe"), 5)) {
            $selenium->driver->switchTo()->frame($iframe);
            $this->saveLogs($isSeleniumMainEngine);

            if ($captcha = $selenium->waitForElement(WebDriverBy::xpath("//label[@class = 'ctp-checkbox-label']/map/img | //label[@class = 'cb-lb' and input[@type = 'checkbox']]"), 10)) {
                $this->saveLogs($isSeleniumMainEngine);
                $captcha->click();
                // TODO: place for improvements, sleep should be deleted
                $this->logger->debug("delay -> 15 sec");
                $this->saveLogs($isSeleniumMainEngine);
                sleep(15);

                $selenium->driver->switchTo()->defaultContent();
                $this->saveLogs($isSeleniumMainEngine);
                $res = true;
            }
        }

        return $res;
    }

    protected function parseCoordinates($text)
    {
        $this->logger->notice(__METHOD__);
        $x = [];
        $y = [];
        preg_match_all('/x=(\d+)/', $text, $m);
        $x = $m[1];
        preg_match_all('/y=(\d+)/', $text, $m);
        $y = $m[1];

        if (count($x) !== count($y)) {
            $this->logger->info('invalid coordinates in the text');

            return false;
        }
        $coords = [];

        for ($i = 0, $iMax = count($x); $i < $iMax; $i++) {
            $coords[] = ['x' => $x[$i], 'y' => $y[$i]];
        }
        $this->logger->info('parsed coords:');
        $this->logger->info(var_export($coords, true));

        return $coords;
    }

    protected function clickCaptchaCtrip($selenium = null, $attemptCount = 5, $recognizeTimeout = 180, $increaseTimeLimit = null)
    {
        $this->logger->notice(__METHOD__);

        if (!$selenium) {
            $selenium = $this;
        }

        $submit = null;

        for ($attempt = 0; $attempt < $attemptCount; $attempt++) {
            $this->logger->info(sprintf('solving attempt #%s', $attempt));
            $chooser = $selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "cpt-choose-box")] | //div[contains(@class, "slider")]/div[contains(@class, "container")]'), 5);

            if ($chooser) {
                $pathToScreenshot = $this->takeScreenshotOfElement($chooser, $selenium);
            } else {
                $this->logger->info('chooser not found');

                return false;
            }

            $data = [
                'coordinatescaptcha' => '1',
                'textinstructions'   => 'select the text from the top picture in correct order on the bottom picture / выберите текст из картинки вверху в правильном порядке на картинке внизу',
            ];

            $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
            $recognizer->RecognizeTimeout = $recognizeTimeout;

            try {
                $captcha = $recognizer->recognizeFile($pathToScreenshot, $data);
            } catch (CaptchaException $e) {
                $this->logger->warning("exception: " . $e->getMessage());
                // always solvable for ctrip
                if ($e->getMessage() === 'server returned error: ERROR_CAPTCHA_UNSOLVABLE') {
                    $recognizer->reportIncorrectlySolvedCAPTCHA();

                    continue;
                } else {
                    return false;
                }
            } finally {
                unlink($pathToScreenshot);
            }

            if ($increaseTimeLimit) {
                $this->increaseTimeLimit($increaseTimeLimit);
            }

            $letterCoords = $this->parseCoordinates($captcha);

            if (!$letterCoords) {
                continue;
            }

            if (count($letterCoords) == 1) {
                $recognizer->reportIncorrectlySolvedCAPTCHA();

                continue;
            }

            $html = $selenium->driver->findElement(WebDriverBy::xpath('//body'));
            $bodyCoords = $html->getCoordinates();

            $coords = $chooser->getCoordinates()->inViewPort();
            $chooserCoords = ['x' => $coords->getX(), 'y' => $coords->getY()];

            $mouse = $selenium->driver->getMouse();
            $mover = new MouseMover($selenium->driver);
            $mover->moveToCoordinates($chooserCoords);

            foreach ($letterCoords as $point) {
                $x = (int) ($chooserCoords['x'] + $point['x']);
                $y = (int) ($chooserCoords['y'] + $point['y']);
                $mover->moveToCoordinates(['x' => $x, 'y' => $y]);
                $mouse->mouseMove($bodyCoords, $x, $y);
                $mover->click();
            }

            $submit = $selenium->waitForElement(WebDriverBy::xpath('//a[contains(@class, "cpt-choose-submit")] | //span[contains(@class, "cpt-submit-text")]'), 3);
            $selenium->http->SaveResponse();

            if ($submit) {
                $submit->click();
                $this->logger->info('sleeping for a bit after submit click');
                sleep(5);
            } else {
                $this->logger->info(sprintf('could not click captcha submit'));
            }

            // If submit hasn't disappeared then captcha was not solved correctly
            $submit = $selenium->waitForElement(WebDriverBy::xpath('//a[contains(@class, "cpt-choose-submit")] | //span[contains(@class, "cpt-submit-text")]'), 3);
            $selenium->http->SaveResponse();

            if ($submit) {
                $recognizer->reportIncorrectlySolvedCAPTCHA();
            } else {
                $this->logger->info('successfully solved select captcha');

                break;
            }
        }

        if ($submit) {
            $this->logger->error('failed to solve select captcha');

            return false;
        }

        $infoBoard = $selenium->waitForElement(WebDriverBy::cssSelector('span.cpt-info-board'), 5);

        if ($infoBoard && $this->http->FindPreg('/Verification failed/i', $infoBoard->getText())) {
            throw new CheckRetryNeededException(3, 0);
        }

        return true;
    }

    protected function saveLogs($isSeleniumMainEngine = true)
    {
        if ($isSeleniumMainEngine) {
            $this->saveResponse();

            return;
        }

        $this->savePageToLogs($this);
    }
}
