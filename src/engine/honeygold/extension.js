var plugin = {

    // keepTabOpen: true, // todo
    hosts: {
        'www.joinhoney.com': true
    },

    errorSelector: 'div[class ^= inputHintText]:not([class *= invis]), div.alert:visible:eq(0)',

    cashbackLink: '',

    startFromCashback: function (params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getFocusTab: function (account, params) {
        return true;
    },

    getStartingUrl: function (params) {
        return 'https://www.joinhoney.com/settings';
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn(params);
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                } else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.logBody("lastPage");
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");
        if ($('#header-log-in').length) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('input[id = "Settings:Profile:Email:Input"]').length) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let email = $('input[id = "Settings:Profile:Email:Input"]');
        if (email.length === 0) {
            browserAPI.log("email not found");
            return  false;
        }
        browserAPI.log("email: " + email.attr("value"));
        return (
            (typeof (account.properties) !== 'undefined')
            && (typeof (account.login) !== 'undefined')
            && (account.login !== '')
            && (email.length > 0)
            && (email.attr("value").toLowerCase() === account.login.toLowerCase())
        );
    },

    logout: function (params) {
        browserAPI.log("logout");
        $('div[class ^= userProfile][role="button"]').click();
        provider.setNextStep('start', function () {
            setTimeout(function () {
                $('span:contains("Log Out")').click();
            }, 500);
        });
        setTimeout(function () {
            plugin.start(params);
        }, 1500);
    },

    login: function (params) {
        browserAPI.log("login");
        $('#header-log-in').click();

        util.waitFor({
            timeout: 5,
            selector: '#auth-login-modal',
            success: function(loginFormBrn) {
                loginFormBrn.click();
            },
        });

        util.waitFor({
            timeout: 5,
            selector: 'form',
            success: function(form) {
                auth(form);
            },
            fail: function(form) {
                auth(form);
            }
        });

        function auth(form) {
            if (form.length === 0) {
                provider.setError(util.errorMessages.loginFormNotFound);
                return;
            }

            browserAPI.log("submitting saved credentials");
            // reactjs
            provider.eval(
                "function triggerInput(enteredName, enteredValue) {\n" +
                "  const input = document.getElementById(enteredName);\n" +
                "  const lastValue = input.value;\n" +
                "  input.value = enteredValue;\n" +
                "  const event = new Event(\"input\", { bubbles: true });\n" +
                "  const tracker = input._valueTracker;\n" +
                "  if (tracker) {\n" +
                "    tracker.setValue(lastValue);\n" +
                "  }\n" +
                "  input.dispatchEvent(event);\n" +
                "}\n" +
                "triggerInput('email-auth-modal', '" + params.account.login + "');" +
                "triggerInput('pwd-auth-modal', '" + params.account.password + "')"
            );

            provider.setNextStep('checkLoginErrors');
            form.find('button[id = "auth-login-modal"]').click();
            let counter = 0;
            let login = setInterval(function () {
                browserAPI.log("waiting... " + counter);
                if (counter === 5 && $('#auth-view-signup').length > 0) {
                    provider.reCaptchaMessage();
                }
                let success = $('button#auth-login-modal[class *= "disabledStatus"]').length;
                let funCaptcha = $('iframe[title="arkose-enforcement"].active:visible');
                let error = $(plugin.errorSelector).text();
                if (counter > 10 && (success === 0 || error !== '') && funCaptcha.length === 0) {
                    clearInterval(login);
                    browserAPI.log("success");
                    form.find('button[id = "auth-login-modal"]').get(0).click();
                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 3000);
                }
                if (counter > 120) {
                    clearInterval(login);
                    provider.setError(util.errorMessages.captchaErrorMessage, true);
                }
                counter++;
            }, 500);
        }
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $(plugin.errorSelector);
        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            let message = util.filter(errors.text());

            if (
                message === "Incorrect email and/or password."
                || message === "Please enter a valid email."
            ) {
                provider.setError([message, util.errorCodes.invalidPassword], true);
                return;
            }

            if (/Too many invalid attempts made \/ proxy detected\./.test(message)) {
                provider.setError(["Too many invalid attempts made / proxy detected.", util.errorCodes.providerError], true);
                return;
            }

            if (/There's been an unexpected error\. We're on it so check back soon\./.test(message)) {
                provider.setError([message, util.errorCodes.providerError], true);
                return;
            }

            if (/You've reached the max number of tries. Check back later/.test(message)) {
                provider.setError(["You've reached the max number of tries. Check back later.", util.errorCodes.providerError], true);
                return;
            }

            browserAPI.log(message);
            provider.complete();
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");

        let token = localStorage.getItem('hckey').replace(/"/g, '');
        browserAPI.log(">>> token -> " + token);

        provider.setNextStep('finalRedirectComplete', function (params) {
            document.location.href = 'https://www.joinhoney.com/honeygold/overview';
        });
    },

    finalRedirectComplete: function (params) {
        browserAPI.log("finalRedirectComplete");
        if (params.autologin) {
            provider.complete();
            return;
        }

        if (provider.isMobile) {
            browserAPI.log(">>> hide site");
            provider.command('hide', function () { });
        }

        plugin.parse(params);
    },

    parse: async function (params) {
        browserAPI.log("parse");
        browserAPI.log("[Current URL]: " + document.location.href);
        provider.updateAccountMessage();
        provider.logBody("parsePage");

        let token = localStorage.getItem('hckey').replace(/"/g, '');
        browserAPI.log(">>> token -> " + token);

        if (!token) {
            browserAPI.log(">>> token-access not found");
            return null;
        }

        let data = {};

        await fetch("https://d.joinhoney.com/v3?operationName=web_getUserById", {
            "headers": {
                "Accept": "*/*",
                "Content-Type": "application/json",
                "Service-Name": "honey-website",
                "Service-Version": "40.4.1",
                "Csrf-Token": token,
            },
            mode: 'cors',
            cache: 'no-cache',
            credentials: "include",
            referrerPolicy: "strict-origin",
        }).then((response) => {
            response
            .clone()
            .json()
            // .then(body => localStorage.setItem("profileData", JSON.stringify(body)));
            .then(body => {
                browserAPI.log('success');
                response = $(body)[0];
                // console.log(response);
                // Balance - (available_points)
                // Name
                if (
                    typeof (response.data.getUserById.firstName) != 'undefined'
                    && typeof (response.data.getUserById.lastName) != 'undefined'
                ) {
                    let name = util.beautifulName(util.trim(response.data.getUserById.firstName + ' ' + response.data.getUserById.lastName));
                    browserAPI.log("Name: " + name);
                    data.Name = name;
                } else {
                    browserAPI.log("Name is not found");
                }
                let total = 0;

                if (typeof (response.data.getUserById.points) != 'undefined' && response.data.getUserById.points != null) {
                    if (typeof (response.data.getUserById.points.pointsAvailable) != 'undefined') {
                        data.Balance = response.data.getUserById.points.pointsAvailable;
                        total = response.data.getUserById.points.pointsAvailable;
                        browserAPI.log("Balance: " + data.Balance);
                    } else {
                        browserAPI.log("Balance is not found");
                    }
                    // Lifetime PayPal Honey Savings
                    if (typeof (response.data.getUserById.lifetimeSaving) != 'undefined') {
                        if (response.data.getUserById.lifetimeSaving === null) {
                            data.LifetimePayPal = '$0.00';
                        }
                        else if (typeof (response.data.getUserById.lifetimeSaving.lifetimeSavingInUSD) != 'undefined') {
                            data.LifetimePayPal = '$' + response.data.getUserById.lifetimeSaving.lifetimeSavingInUSD;
                        }
                        browserAPI.log("Lifetime PayPal Honey Savings: " + data.LifetimePayPal);
                    } else {
                        browserAPI.log("Lifetime PayPal Honey Savings is not found");
                    }
                    // Pending
                    if (typeof (response.data.getUserById.points.pointsPendingDeposit) != 'undefined') {
                        data.Pending = response.data.getUserById.points.pointsPendingDeposit;
                        total = total + data.Pending;
                        browserAPI.log("Pending: " + data.Pending);
                    } else {
                        browserAPI.log("Pending is not found");
                    }
                    // Lifetime Points worths
                    if (typeof (response.data.getUserById.points.pointsRedeemed) != 'undefined') {
                        total = total + response.data.getUserById.points.pointsRedeemed;
                    } else {
                        browserAPI.log("pointsRedeemed is not found");
                    }
                    data.Total = '$' + (total / 100);
                    browserAPI.log("Lifetime Points worths: " + data.Total);
                    // Lifetime Points
                    data.LifetimePoints = total;
                    browserAPI.log("Lifetime Points: " + data.LifetimePoints);
                } else {
                    if (typeof (response.data.getUserById.points) != 'undefined' && response.data.getUserById.points == null) {
                        data.Balance = "0";
                        browserAPI.log("Balance: " + data.Balance);
                    }
                }
                // ReferralEarned
                if (typeof (response.data.getUserById.onboarding.referralPoints) != 'undefined') {
                    data.ReferralEarned = response.data.getUserById.onboarding.referralPoints;
                    browserAPI.log("ReferralEarned: " + data.ReferralEarned);
                } else {
                    browserAPI.log("ReferralEarned is not found");
                }

                params.account.properties = data;
                provider.saveProperties(params.account.properties);
                provider.complete();
            });
        });
    },

    getXMLHttp: function () {
        if (typeof content !== 'undefined' && content && content.XMLHttpRequest) {
            return new content.XMLHttpRequest();
        }
        return new XMLHttpRequest();
    },
};