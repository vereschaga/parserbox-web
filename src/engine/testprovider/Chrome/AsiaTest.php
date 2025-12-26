<?php

namespace AwardWallet\Engine\testprovider\Chrome;

use AwardWallet\Engine\testprovider\TestHelper;

class AsiaTest extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use TestHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->InitSeleniumBrowser();
        $this->useChromePuppeteer();
        $this->usePacFile(false);
        $this->ArchiveLogs = true;
    }

    public function LoadLoginForm()
    {
        $this->http->GetURL("https://www.cathaypacific.com/cx/en_US/sign-in.html?switch=Y");

        return true;
    }

    public function Login()
    {
        $typeAuth = 'Sign in with email';
        $typeLogin = 'email';

        $btn = $this->waitForElement(\WebDriverBy::xpath("//button[contains(normalize-space(),'{$typeAuth}')]"), 10);
        $btn->click();

        $login = $this->waitForElement(\WebDriverBy::xpath("//input[@name='{$typeLogin}']"), 2);
        $pwd = $this->waitForElement(\WebDriverBy::xpath("//input[@name='password']"), 0);
        $login->sendKeys("some@email.com");
        $pwd->sendKeys("somepass");

        if (
            "some@email.com" === $this->driver->executeScript("return document.getElementById('Email address').value")
            && "somepass" === $this->driver->executeScript("return document.getElementById('Password').value")
        ) {
            return true;
        }

        return false;
    }

    public function Parse()
    {
        $this->SetBalance(1);
    }
}
