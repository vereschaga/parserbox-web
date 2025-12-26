<?php

class TAccountCheckerTalbots extends TAccountChecker
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        $this->http->GetURL("https://www1.talbots.com/online/myaccount/myinformation.jsp");

        return $this->GetName() !== null;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www1.talbots.com/online/myaccount/login_register.jsp");

        if (!$this->http->ParseForm("loginForm")) {
            return false;
        }
        $this->http->Form["loginEmail"] = $this->AccountFields["Login"];
        $this->http->Form["loginPassword"] = $this->AccountFields["Pass"];
        $this->http->Form["rememberMe"] = "on";
        $this->http->Form["/atg/userprofiling/TalbotsProfileFormHandler.authenticate"] = "SIGN IN";

        return true;
    }

    public function Login()
    {
        $this->http->PostForm();
        $errors = $this->http->FindNodes("//div[@id = 'loginErrors']");

        if (count($errors) > 0) {
            $this->ErrorCode = ACCOUNT_INVALID_PASSWORD;
            $this->ErrorMessage = implode(" ", $errors);

            return false;
        }

        if ($this->http->ParseForm("securityAnswer")) {
            $this->http->Log("security question detected");
            $questions = $this->http->FindNodes("//label[@for = 'secretQuestion']");

            if (count($questions) == 0) {
                return false;
            }
            $this->Question = $questions[0];

            if (isset($this->Answers[$questions[0]])) {
                return $this->ProcessStep("AnswerQuestion");
            }
            $this->ErrorCode = ACCOUNT_QUESTION;
            $this->Step = "AnswerQuestion";

            return false;
        }

        return $this->GetName() !== null;
    }

    public function ProcessStep($step)
    {
        switch ($step) {
            case "AnswerQuestion":
                $this->http->log("answering to question: " . $this->Question);
                $this->http->Form["/atg/userprofiling/TalbotsProfileFormHandler.value.security_answer"] = $this->Answers[$this->Question];
                $this->http->Form["/atg/userprofiling/TalbotsProfileFormHandler.login"] = "LOGIN";
                $this->http->PostForm();

                if (preg_match("/Please enter the correct answer to the security question/ims", $this->http->Response['body'])) {
                    if (!$this->http->ParseForm("securityAnswer")) {
                        return false;
                    }
                    $this->ErrorCode = ACCOUNT_QUESTION;
                    $this->Step = "AnswerQuestion";
                    $this->ErrorMessage = "Please enter the correct answer to the security question";

                    return false;
                }
                $this->http->Log("answered question?");

                return $this->GetName() !== null;

                break;

            default:
                parent::ProcessStep($step);
        }
    }

    public function GetName()
    {
        if (preg_match("/<span class=\"editMyInformation\">EDIT<\/span>\s*<ul>\s*<li>([^<]+)</ims", $this->http->Response['body'], $matches)) {
            return $matches[1];
        } else {
            return null;
        }
    }

    public function Parse()
    {
        $name = $this->GetName();

        if (isset($name)) {
            $this->Properties["Name"] = $name;
            $this->Balance = null;
            $this->ErrorCode = ACCOUNT_CHECKED;
        }
    }
}
