var plugin = {
    // keepTabOpen: true, //todo
    //hideOnStart: true,
    //clearCache: true,
    blockImages: /Chrome/.test(navigator.userAgent) && /Google Inc/.test(navigator.vendor),
    sha256HashReservations: '4e3f2e0b0621bc3b51fde95314745feb4fd1f9c10cf174542ab79d36c9dd0fb2',
    sha256HashReservation: 'a34269e9d3764f407ea0fafcec98e24ba90ba7a51d69d633cae81b46e677bdcb',
    captchaSelector: 'span[class = "asfc-svg-captcha"]:visible',
    otpSelector: '.login-form-converse-stmt-greeting:contains("Get your one-time PIN code"):visible, .login-form-converse-stmt-greeting:contains("We’ve sent the PIN code"):visible',

    hosts: {
        '/\\w+\\.klm\\.\\w+/': true,
        '/\\w+\\.klm\\.\\w+\\.\\w+/': true
        //'www.klm.com': true,
        //'login.klm.com': true,
        //'wwws.klm.us': true,
        //'wwws.klm.nl': true,
        //'mobile.klm.us': true
    },

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function(params) {
        return 'https://www.klm.com/';
    },

    getFocusTab: function (account, params) {
        return true;
    },

    // for Cashback auto-login
    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        setTimeout(function () {
            provider.setNextStep('start', function () {
                document.location.href = plugin.getStartingUrl(params);
            });
        }, 2000);
    },
    getCookie: function (name) {
        var matches = document.cookie.match(new RegExp(
            "(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
        ));
        return matches ? decodeURIComponent(matches[1]) : undefined;
    },
    setCookie: function (cookieName, cookieValue) {
        var myDate = new Date();
        myDate.setMonth(myDate.getMonth() + 12);
        document.cookie = cookieName + "=" + cookieValue + ";expires=" + myDate + ";domain=.klm.com;path=/";
    },

    start: function(params) {
        browserAPI.log("start");
        browserAPI.log("UserAgent -> " + navigator.userAgent);
        browserAPI.log("UserAgent -> " + JSON.stringify(util.detectBrowser()));
        browserAPI.log("[Current URL] -> " + document.location.href);


        let switchCountry = plugin.getCookie('bw_switch_country');
        browserAPI.log("bw_switch_country -> " + switchCountry);
        if (!switchCountry) {
            plugin.setCookie('bw_switch_country', 'us');
            provider.setNextStep('start', function () {
                document.location.href = 'https://www.klm.com/profile';
            });
            return;
        }

        setTimeout(() => {
            provider.logBody("startPage");
            var counter = 0;
            var start = setInterval(function () {
                browserAPI.log("waiting... " + counter);
                var isLoggedIn = plugin.isLoggedIn();
                if (isLoggedIn !== null) {
                    clearInterval(start);
                    if (isLoggedIn) {
                        if (plugin.isSameAccount(params.account))
                            plugin.loginComplete(params);
                        else
                            plugin.logout(params);
                    }
                    else {
                        setTimeout(function () {
                            let loginBtn = $('button.bwc-logo-header__login-button:visible');

                            /*
                            if (!(balance.length > 0)) {
                                loginBtn = $('button.bwc-logo-header__auth-menu-button');
                            };
                            */
                            
                            browserAPI.log("login button " + loginBtn.length);
                            if (loginBtn.length > 0) {
                                provider.setNextStep('login', function () {
                                    loginBtn.get(0).click();
                                });
                            } else {
                                //plugin.login(params);
                                provider.setNextStep('login', function () {
                                    document.location.href = 'https://www.klm.com/profile';
                                });
                            }
                        }, 3000);
                    }
                    return;
                }// if (isLoggedIn !== null)
                if (isLoggedIn === null && counter > 20) {
                    clearInterval(start);
                    provider.logBody("lastPage");
                    let error = $('p:contains("Access to this web site was blocked by an IT URL Access Control policy."):visible');
                    if (error.length > 0) {
                        provider.setError([error.text(), util.errorCodes.engineError], true);
                        return;
                    }
    
                    error = $('h1:contains("503 Service Unavailable"):visible');
    
                    if (error.length > 0) {
                        provider.setError(['Due to technical issues, you may experience difficulties on our website. We\'re doing our best to resole these as soon as possible. Please note: the app is still working.', util.errorCodes.providerError]);
                        return;
                    }
    
                    provider.setError(util.errorMessages.unknownLoginState, true);
                    return;
                }// if (isLoggedIn === null && counter > 20)
                counter++;
            }, 500);
        }, 3000);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if (
            $('.bwc-logo-header__user-profile-type-image:visible').length > 0
            || $('span.header__user__identity').text() !== ''
            || $('span.bwc-logo-header__user-name').text() !== ''
            || $('button.bwc-logo-header__user-profile-info:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('div.login-form-container:visible, button.bwc-logo-header__login-button:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('button.bwc-logo-header__auth-menu-button:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = $('.bw-fb-membership-card__number-text > strong');
        if (number.length > 0) {
            number = util.filter(number.text());
        }
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) !== 'undefined')
            && (typeof(account.properties.Number) !== 'undefined')
            && (account.properties.Number !== '')
            && number
            && (number === account.properties.Number));
    },

    logout: function (params) {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm', function () {
            util.waitFor({
                selector: '.bwc-logo-header__user-profile-info:visible',
                success: function (elem) {
                    elem.get(0).click();
                    setTimeout(function () {
                        $('button.bwc-multi-list__button:visible:contains("out"), button.bwc-multi-list-item__button:visible:contains("out"), a[data-mya-test = "logoutLink"]').get(0).click();
                    }, 500);
                },
                fail: function (elem) {
                    browserAPI.log("timeout");
                    if (provider.isMobile) {
                        $('.bwc-logo-header__user-profile-info:visible').get(0).click();
                        setTimeout(function () {
                            $('button.bwc-multi-list__button:visible:contains("out"), a[data-mya-test = "logoutLink"]').get(0).click();
                        }, 500);
                    }
                },
                timeout: 7
            });

            var logoutCounter = 0;
            var logout = setInterval(function () {
                browserAPI.log("Logout waiting... " + document.location.href);
                if (logoutCounter > 30)
                    clearInterval(logout);
                logoutCounter++;
            }, 500);
        });
    },

    login: function(params) {
        browserAPI.log("login");

        if (
            typeof (params.account.itineraryAutologin) == "boolean" &&
            params.account.itineraryAutologin &&
            params.account.accountId === 0
        ) {
            provider.setNextStep('getConfNoItinerary', function() {
                document.location.href = 'https://www.klm.com/trip';
            });
            return;
        }

        const passwordForm = $('a[aria-label="Log in with your password instead?"]:visible');

        if (passwordForm.length > 0) {
            browserAPI.log("click on 'with password' btn");
            passwordForm.get(0).click();
        }

        const agree = $('button#accept_cookies_btn:visible');
        if (agree.length > 0) {
            browserAPI.log("click on cookies btn");
            agree.get(0).click();
        }

        util.waitFor({
            selector: 'div.login-form-container:visible',
            success: function(form) {
                browserAPI.log("submitting saved credentials");
                provider.eval(`
                function triggerInput(selector, value) {
                    const input = document.querySelector(selector);
                    input.dispatchEvent(new Event('focus'));
                    input.dispatchEvent(new KeyboardEvent('keypress',{'key':'a'}));
                    const nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
                    nativeInputValueSetter.call(input, value);
                    const inputEvent = new Event('input', { bubbles: true });
                    input.dispatchEvent(inputEvent);
                }
                triggerInput('input[formcontrolname = "loginId"]', '${params.account.login}');
                triggerInput('input[formcontrolname = "password"]', '${params.account.password}');
            `);

                setTimeout(function() {
                    var captcha = util.findRegExp( form.find('iframe[src *= "https://www.google.com/recaptcha/api2/anchor"]').attr('src'), /k=([^&]+)/i);
                    if (!captcha)
                        captcha = util.findRegExp( $('div.grecaptcha-badge:visible').find('iframe[src *= "https://www.google.com/recaptcha/api2/anchor"]').attr('src'), /k=([^&]+)/i);
                    var submitButton = form.find('button.login-form-continue-btn');
                    //browserAPI.log("waiting captcha -> " + captcha);
                    if (captcha && captcha.length > 0) {
                        browserAPI.log("waiting...");
                        provider.reCaptchaMessage();
                        if (form.find('iframe[src *= "https://www.google.com/recaptcha/api2/anchor"][src *= "invisible"]').length > 0 || $('div.grecaptcha-badge:visible').length) {
                            browserAPI.log("invisible captcha workaround");
                            // submitButton.get(0).click();
                            provider.eval("document.querySelector('button.login-form-continue-btn').click()");
                        }
                        browserAPI.log("waiting...");
                        provider.setNextStep('checkLoginErrors', function() {
                            let counter = 0;
                            let messageShown = false;
                            let login = setInterval(function () {
                                browserAPI.log("waiting... " + counter);
                                if (
                                    (
                                        $('input[placeholder *= "Please enter the characters displayed above."], input[data-placeholder *= "Please enter the characters displayed above."]').length > 0
                                        || counter > 8 && $('button.login-form-continue-btn[disabled]').length > 0
                                    )
                                    && !messageShown
                                ) {
                                    browserAPI.log('messageShown = true');
                                    messageShown = true;
                                    if (provider.isMobile) {
                                        provider.command('show', function () {
                                            provider.reCaptchaMessage();
                                        });
                                    } else {
                                        provider.reCaptchaMessage();
                                    }
                                }

                                // Get your one-time PIN code
                                /*if ($('.otp-field-assist').length) {
                                    if (!isNaN(plugin.captchaErrorSetter)) {
                                        clearTimeout(plugin.captchaErrorSetter);
                                    }
                                    if (!provider.isMobile) {
                                        if (params.autologin)
                                            provider.setError(['It seems that KLM (Flying Blue) needs to identify this computer before you can log in. Please follow the instructions on the new tab (the one that shows your Airfrance authentication options) to get this computer authorized and then please try to auto-login again.', util.errorCodes.providerError], true);
                                        else {
                                            provider.setError(['It seems that KLM (Flying Blue) needs to identify this computer before you can update this account. Please follow the instructions on the new tab (the one that shows your Airfrance authentication options) to get this computer authorized and then please try to update this account again.', util.errorCodes.providerError], true);
                                        }

                                        return;
                                    }

                                    provider.command('show', function () {
                                        provider.showFader('Message from AwardWallet: In order to log in into this account please identify this device and click the “Continue” button. Once logged in, sit back and relax, we will do the rest.', true);
                                        provider.setNextStep('profileTimeout', function () {
                                            browserAPI.log("waiting answers...");
                                            let counter = 0;
                                            let waitingAnswers = () => {
                                                browserAPI.log("waiting... " + counter);
                                                if ($('.otp-field-assist, input[id^=otp_], input.login-captcha').length === 0) {
                                                    provider.setNextStep('profileTimeout', () => { document.location.href = 'https://www.klm.com/profile/flying-blue/dashboard' });
                                                    return;
                                                }
                                                if (counter > 180) {
                                                    provider.setError(['Message from AwardWallet: In order to log in into this account please identify this device and click the “Continue” button. Once logged in, sit back and relax, we will do the rest.', util.errorCodes.providerError], true);
                                                    return;
                                                }
                                                counter++;
                                                setTimeout(waitingAnswers, 500)
                                            }
                                            waitingAnswers();
                                        });
                                    });

                                    return;
                                }*/

                                var errors = $('div[class *= "error-message"] li:visible');
                                if ($('a.js-mya-logout').length > 0
                                    || errors.length > 0
                                    || $('h1:contains("New password"):visible').length > 0
                                    || $('h1:contains("Your Flying Blue account has been blocked"):visible').length > 0
                                    || $('#password-label-error:visible').length > 0
                                    || $('span[awl-i18n^="form.login.errors."]:visible').length > 0
                                    || $('asfc-form-error > .login-field-assist.bwc-form-errors:visible').length > 0
                                ) {
                                    clearInterval(login);
                                    plugin.checkLoginErrors(params);
                                }// if (errors.length > 0)
                                if (counter > 120) {
                                    clearInterval(login);
                                    // IE not working properly
                                    if (!!navigator.userAgent.match(/Trident\/\d\./)) {
                                        document.location.reload();
                                        return;
                                    }
                                    provider.setError(util.errorMessages.captchaErrorMessage, true);
                                }
                                counter++;
                            }, 500);
                        });
                        //}
                    }// if (captcha && captcha.length > 0)
                    else {
                        browserAPI.log("captcha is not found");
                        // IE not working properly
                        if (!!navigator.userAgent.match(/Trident\/\d\./) && $('div#google-recaptcha-login iframe').length == 0) {
                            document.location.reload();
                            return;
                        }
                        provider.setNextStep('checkLoginErrors', function () {
                            util.waitFor({
                                timeout: 2,
                                selector: plugin.captchaSelector,
                                success: function() {
                                    provider.reCaptchaMessage();
                                    waitResult(120);
                                },
                                fail: function() {
                                }
                            });
                            // submitButton.get(0).click();
                            provider.eval("document.querySelector('button.login-form-continue-btn').click()");

                            util.waitFor({
                                timeout: 5,
                                selector: plugin.otpSelector,
                                success: function() {
                                    plugin.loginComplete(params);
                                },
                                fail: function() {
                                    util.waitFor({
                                        timeout: 2,
                                        selector: plugin.captchaSelector,
                                        success: function() {
                                            provider.reCaptchaMessage();
                                            waitResult(120);
                                        },
                                        fail: function() {
                                            waitResult();
                                        }
                                    });
                                }
                            });

                            function waitResult(timeoutValue = 3) {
                                util.waitFor({
                                    timeout: timeoutValue,
                                    selector: 'a.js-mya-logout, div[class *= "error-message"] li:visible, h1:contains("Create a new password"):visible, h1:contains("Your Flying Blue account has been blocked"):visible, h2:contains("Sorry, your account has been blocked."):visible, div.bwc-form-errors:visible > span, .otp-field-assist',
                                    success: function() {
                                        plugin.checkLoginErrors(params);
                                    },
                                    fail: function() {
                                        plugin.checkLoginErrors(params);
                                    }
                                });
                            }
                        });
                    }
                }, 2000);
            },
            fail: function(form) {
                plugin.checkLoginErrors(params);

                if (form.length === 0) {
                    provider.setError(util.errorMessages.loginFormNotFound);
                }
            }
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        provider.logBody("checkLoginErrorsPage");
        provider.hideFader();

        provider.setNextStep('loginComplete');//prevent loops

        let captchaErrorSetter;
        setTimeout(function () {
            if ($(plugin.captchaSelector).length) {
                plugin.captchaErrorSetter = setTimeout(() => provider.setError(util.errorMessages.captchaErrorMessage, true), 5000);
                return false;
            }

            if (isNaN(captchaErrorSetter) && $(plugin.captchaSelector).length > 0) {
                browserAPI.log('created timeout for captcha error');
                captchaErrorSetter = setTimeout(() => provider.setError(util.errorMessages.captchaErrorMessage, true), 5000);
                return false;
            }

            let errors = $('span[awl-i18n^="form.login.errors."]:visible');
            if (errors.length === 0) {
                errors = $('div.bwc-form-errors:visible > span');
            }
            if (errors.length === 0)
                errors = $('.bwc-form-error--pattern:visible');
            if (errors.length === 0)
                errors = $('h1:contains("Your Flying Blue account has been blocked"):visible, h2:contains("Sorry, your account has been blocked."):visible');
            if (errors.length === 0)
                errors = $('#password-label-error:contains("Please enter your password or PIN code."):visible');
            // Create a new password
            if (errors.length === 0 && $('h1:contains("New password"):visible, div:contains("Update your password"):visible').length > 0) {
                provider.setError(["KLM (Flying Blue) website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", util.errorCodes.providerError], true);
                return;
            }
            if (errors.length > 0) {
                browserAPI.log("[Error]: " + errors.text());
                // Your Flying Blue account has been blocked
                if (
                    errors.text().indexOf(" account has been blocked") !== -1
                    || errors.text().indexOf(" account is blocked.") !== -1
                    || errors.text().indexOf("Your account is blocked. Please wait 24 hours before") !== -1
                ) {
                    provider.setError([errors.text(), util.errorCodes.lockout], true);
                    return;
                }
                // We're sorry, an unexpected technical error occurred. Please try again later or contact the KLM Service Centre.
                // Sorry, an unexpected technical error occurred. Please try again later or contact the KLM Customer Contact Centre.
                if (
                    errors.text().indexOf("orry, an unexpected technical error occurred") !== -1
                    || errors.text().indexOf("Sorry, our system fell asleep. Please restart your login.") !== -1
                    || errors.text().indexOf("Communication email is invalid") !== -1
                    || errors.text().indexOf("Sorry, we cannot verify your password due to a technical issue") !== -1
                ) {
                    provider.setError([errors.text(), util.errorCodes.providerError], true);
                    return;
                }

                provider.setError(errors.text(), true);
                return;
            }// if (errors.length > 0)

            plugin.loginComplete(params);
        }, 3000);
    },

    toItineraries: function(params) {
        browserAPI.log("toItineraries");
        setTimeout(function() {
            var confNo = params.account.properties.confirmationNumber;
            var link = $('p:contains("' + confNo + '")').closest('div').next('div').find('a.g-btn');
            if (link.length > 0) {
                provider.setNextStep('itLoginComplete', function() {
                    link.get(0).click();
                });
            }// if (link.length > 0)
            else
                provider.setError(util.errorMessages.itineraryNotFound);
        }, 2000);
    },

    loginComplete: function(params) {
        browserAPI.log('loginComplete');
        if (    typeof(params.account.itineraryAutologin) == "boolean" &&
                params.account.itineraryAutologin && params.account.accountId > 0   ) {
            provider.setNextStep('toItineraries', function() {
                document.location.href = 'https://www.klm.com/trip/overview';
            });
            return;
        }
        // autologin complete
        if (params.autologin) {
            plugin.itLoginComplete(params);
            return;
        }

        let otpUrl = 'https://login.klm.com/login/otp';
        if (document.location.href === otpUrl) {
            provider.setNextStep('profileTimeout', function () {
                if ($('.login-form-converse-stmt-greeting:contains("Get your one-time PIN code"):visible, .login-form-converse-stmt-greeting:contains("We’ve sent the PIN code"):visible').length > 0) {
                    if (params.autologin)
                        provider.setError(['It seems that KLM (Flying Blue) needs to identify this computer before you can log in. Please follow the instructions on the new tab (the one that shows your KLM authentication options) to get this computer authorized and then please try to auto-login again.', util.errorCodes.providerError], true);
                    else {
                        //provider.setError(['It seems that Air France (Flying Blue) needs to identify this computer before you can update this account. Please follow the instructions on the new tab (the one that shows your Airfrance authentication options) to get this computer authorized and then please try to update this account again.', util.errorCodes.providerError], true);
                        provider.showFader('It seems that KLM (Flying Blue) needs to identify this computer before you can update this account. Please follow the instructions on the new tab (the one that shows your KLM authentication options) to get this computer authorized and then please try to update this account again.');
                        plugin.profileTimeout(params);
                    }
                }
            });
            return;
        }

        plugin.loadProfileTimeout(params);
    },

    itLoginComplete: function(params) {
        browserAPI.log('itLoginComplete');
        provider.complete();
    },

    loadProfileTimeout: function (params) {
        browserAPI.log("loadProfileTimeout");
        let myAccountUrl = 'https://www.klm.com/profile/flying-blue/dashboard';
        browserAPI.log("[Current URL] -> " + document.location.href);
        if (document.location.href !== myAccountUrl) {
            provider.setNextStep('profileTimeout', function () {
                document.location.href = myAccountUrl;
            });
        } else {
            plugin.profileTimeout(params);
        }
    },

    profileTimeout: function (params, isRecursion) {
        browserAPI.log("profileTimeout");
        var counter = 0;
        var profileTimeout = setInterval(function () {
            browserAPI.log("waiting... " + counter);

            // Your profile has changed
            if ($('div.bwc-o-display-2:contains("Your profile has changed"):visible').length) {
                browserAPI.log("Your profile has changed");
                clearInterval(profileTimeout);
                if(!isRecursion)
                    $('button[mitagclick="profile.cutover_page.continue_new"]:contains("Got it"):visible').get(0).click();
                plugin.profileTimeout(params, true);
            } else if (document.location.href.length < 25) {
                plugin.loadProfileTimeout(params);
            } else {
                // if the page completely loaded
                if (
                    $('.bwc-logo-header__user-profile-type-image:visible').length > 0
                    || $('span.header__user__identity').text() !== ''
                    || $('span.bwc-logo-header__user-name').text() !== ''
                    || $('button:contains("Join Flying Blue"):visible, button:contains("Become a Flying Blue member"):visible').length > 0
                )
                {
                    clearInterval(profileTimeout);
                    plugin.parse(params);
                }
                if ( counter > 180 ) {
                    clearInterval(profileTimeout);
                    // Please enter the PIN code below.
                    /*if (params.autologin)
                        provider.setError(['It seems that KLM (Flying Blue) needs to identify this computer before you can log in. Please follow the instructions on the new tab (the one that shows your Airfrance authentication options) to get this computer authorized and then please try to auto-login again.', util.errorCodes.providerError], true);
                    else {
                        provider.setError(['It seems that KLM (Flying Blue) needs to identify this computer before you can update this account. Please follow the instructions on the new tab (the one that shows your Airfrance authentication options) to get this computer authorized and then please try to update this account again.', util.errorCodes.providerError], true);
                    }*/
                }
            }
            counter++;
        }, 1000);
    },

    parse: function (params) {
        browserAPI.log("parse");
        browserAPI.log("[Current URL] -> " + document.location.href);
        provider.logBody("parse");
        provider.updateAccountMessage();
        var data = {};

        // Name
        var name = $('span.bwc-logo-header__user-name');
        if (name.length > 0) {
            name = util.beautifulName(util.trim(name.text()));
            browserAPI.log("Name: " + name);
            data.Name = name;
        } else {
            // mobile
            if (typeof (params.data.Name) != 'undefined') {
                data.Name = params.data.Name;
                browserAPI.log("Name from data: " + data.Name);
            }
            else
                browserAPI.log("Name not found");
        }
        // Balance - Award Miles balance
        let balance = $('.bw-profile-recognition-box__info--amount');

        if (!(balance.length > 0  && balance.text() !== '')) {
            // Balance - Award Miles balance
            balance = $('#bw-fb__miles-overview-miles.bwc-typo-headline-s');
        };
        if (balance.length > 0  && balance.text() !== '') {
            data.Balance = util.findRegExp(balance.text(), /([-\d.,\s]+)/);
            browserAPI.log("Balance: " + balance.text());
        }// if (balance.length > 0)
        else {
            browserAPI.log("Balance not found");
            if (
                $('button:contains("Join Flying Blue"):visible, button:contains("Become a Flying Blue member"):visible').length > 0
            ) {
                let notMember = 'You are not a member of this loyalty program.';
                browserAPI.log(notMember);
                provider.setWarning(notMember);

                params.account.properties = params.data.properties;
                // console.log(params.account.properties);//todo
                provider.saveProperties(params.account.properties);

                if (typeof(params.account.parseItineraries) == 'boolean' && params.account.parseItineraries) {
                    provider.setNextStep('waitItineraryList', function () {
                        document.location.href = 'https://www.klm.com/trip/overview';
                    });
                }// if (typeof(params.account.parseItineraries) == 'boolean' && params.account.parseItineraries)
                else {
                    browserAPI.log(">>> complete");
                    provider.complete();
                }

                return;
            }// if ($('a:contains("Join Flying Blue"):visible').length > 0)
        }
        // Status
        let status = $('img[class *= "bwc-logo--flyingblue"][alt *= "Flying Blue"]:eq(0)');

        if (!(status.length > 0)) {
            status = $('span.bw-profile-flyingblue-status__step-label')
        };
        if (status.length > 0) {
            status = util.findRegExp(status.attr('alt'), /Flying Blue\s+(.+)/);
            if (status.length > 0) {
                var level = util.filter(util.beautifulName(status));
                browserAPI.log("Status: " + level);
                data.Status = level;
            }
            else browserAPI.log("Status not found 2");
        } else browserAPI.log("Status not found");
        // Card number
        var number = $('.bw-fb-membership-card__number-text > strong');
        if (number.length > 0) {
            number = util.filter(number.text());
            browserAPI.log("Card number: " + number);
            data.Number = number;
        }
        else
            browserAPI.log("Card number not found");
        // 164,690 Miles, 84 XP
        var experiencePoints = $('.bw-profile-recognition-box__info--amount');

        if (!(experiencePoints.length > 0)) {
            experiencePoints = $('span.bw-profile-flyingblue-status__step__current-label')
        };
        if (experiencePoints.length > 0) {
            experiencePoints = util.findRegExp( experiencePoints.text(), /Miles, ([\d.,]+) XP/i);
            browserAPI.log("Experience Points: " + experiencePoints);
            data.ExperiencePoints = experiencePoints;
        }
        else
            browserAPI.log("Experience Points not found");

        if (typeof (data.Number) != 'undefined')
        $.ajax({
            url: "https://www.klm.com/gql/v1?bookingFlow=LEISURE",
            async: false,
            data: JSON.stringify({"operationName":"ProfileFlyingBlueBenefitsQuery","variables":{"fbNumber":data.Number},"extensions":{"persistedQuery":{"version":1,"sha256Hash":"ee0498f9ac6236f86f09013c8621ab2894e36e17dd0d0d8fb80b856514b23379"}}}),
            type: 'POST',
            contentType: "application/json",
            beforeSend: function(request) {
                request.setRequestHeader("accept-language", 'en');
                request.setRequestHeader("afkl-travel-country", 'us');
                request.setRequestHeader("afkl-travel-host", 'KL');
                request.setRequestHeader("country", 'us');
                request.setRequestHeader("language", 'en');
                request.setRequestHeader("x-xsrf-token", plugin.getCookie('XSRF-TOKEN'));
            },
            dataType: "json",
            success: function (response) {
                browserAPI.log("parse Benefits");
                if (typeof response.data === 'undefined' && typeof response.errors !== 'undefined')
                    return;
                response = response.data.flyingBlueBenefits.currentBenefits;
                //console.log("---------------- data ----------------");
                //console.log(response);
                //console.log("---------------- data ----------------");
                if (typeof response !== 'undefined') {
                    for (var i in response) {
                        if (response.hasOwnProperty(i)) {
                            var row = {};
                            if (typeof(response[i].label) !== 'undefined' && response[i].label == "Flying Blue Petroleum") {
                                browserAPI.log("Experience Points: Yes");
                                data.PetroleumMembership = 'Yes';
                                break;
                            }// if (typeof(response[i].label) !== 'undefined' && response[i].label == "Flying Blue Petroleum")
                        }// if (response[0].statements[0].expirations.hasOwnProperty(expiration))
                    }// for (var expiration in response[0].statements[0].expirations)
                }// if (typeof (response[0].statements[0].expirations) != 'undefined')
            }// success: function (response)
        });// $.ajax({

        params.data.properties = data;
        params.data.properties.HistoryRows = [];
        params.data.endHistory = false;
        provider.saveTemp(params.data);
        // console.log(params.data);//todo

        // Activity overview
        var milesOverview = $('.mat-list-item.ng-star-inserted:contains("Activity overview") button, #bw-fb__miles-overview-title');
        provider.setNextStep('parseExpDate', function () {
            milesOverview.get(0).click();
            setTimeout(function () {
                if ($('h1:contains("Activity overview"):visible').length > 0) {
                    plugin.parseExpDate(params);
                }
            }, 3000);
        });
    },



    parseExpDate: function(params) {
        browserAPI.log("parseExpDate");
        // Expiration Date  // refs #4692
        // AccountId, 2686816
        if (params.data.properties.Balance != 0) {
            if (typeof params.data.properties.Status !== undefined && params.data.properties.Status === 'Explorer') {
                var milesExpiryInformation = $('h5.bw-fb-miles-overview__totals-label.bwc-o-body:contains("valid until")');
                if (milesExpiryInformation.length > 0) {
                    var result = [];
                    $.each(milesExpiryInformation, function (key, value) {
                        browserAPI.log('Expiration String: ' + $(value).text());
                        var until = $(value).text().match(/([\d.,]+)\s+valid until\s+(.+)/);
                        if (until !== null) {
                            var milesAmount = parseFloat(until[1].replace(new RegExp(',', 'g'), ''));
                            var date = new Date(until[2] + ' UTC');
                            var unixtime = date / 1000;
                            if (!isNaN(unixtime)) {
                                browserAPI.log('Expiration Time: ' + date + ' / ' + unixtime);
                                var resultKey = '_' + unixtime;
                                if (typeof result[resultKey] !== 'undefined') {
                                    var item = result[resultKey];
                                    item['unixtime'] = unixtime;
                                    item['milesAmount'] += milesAmount;
                                    result[resultKey] = item;
                                } else
                                    result[resultKey] = {'unixtime': unixtime, 'milesAmount': milesAmount};
                            } else
                                browserAPI.log("Each " + key + " Expiration date not found");
                        }
                    });
                    result = result.sort(function (a, b) {
                        if (a.unixtime < b.unixtime)
                            return -1;
                        if (a.unixtime > b.unixtime)
                            return 1;
                        return 0;
                    });
                    result = result[Object.keys(result)[0]];
                    // console.log(result);

                    if (typeof result.unixtime !== 'undefined' && !isNaN(result.unixtime)) {
                        browserAPI.log("Expiration Date: " + result.unixtime);
                        browserAPI.log("Expiring Balance: " + result.milesAmount);
                        params.data.properties.AccountExpirationDate = result.unixtime;
                        params.data.properties.ExpiringBalance = result.milesAmount;
                    }
                } else
                    browserAPI.log("Your Award Miles are valid until not found");
            } else if (typeof params.data.properties.Status !== undefined
                && ['Silver', 'Gold', 'Platinum', 'Ultimate', 'Platinum For Life', 'Ultimate Club 2000'].includes(params.data.properties.Status)) {
                browserAPI.log("expiration date set to never");
                params.data.properties.AccountExpirationDate = 'false';
                params.data.properties.AccountExpirationWarning = 'do not expire with elite status';
                browserAPI.log("clear the old expiration date");
                params.data.properties.ClearExpirationDate = 'Y';
            }
        }

        //console.log(params.data);//todo
        provider.saveTemp(params.data);
        plugin.parseHistory(params);
    },

    parseHistory: function(params) {
        browserAPI.log("parseHistory");
        provider.updateAccountMessage();
        var startDate = params.account.historyStartDate;
        browserAPI.log("historyStartDate: " + startDate);
        // History
        $.ajax({
            url: "https://www.klm.com/gql/v1?bookingFlow=LEISURE",
            data: JSON.stringify({"operationName":"ProfileFlyingBlueTransactionHistoryQuery","variables":{"size":100,"offset":1,"fbNumber":params.data.properties.Number},"extensions":{"persistedQuery":{"version":1,"sha256Hash":"a4da5deea24960ece439deda2d3eac6c755e88ecfe1dfc15711615a87943fba7"}}}),
            type: 'POST',
            contentType: "application/json",
            beforeSend: function(request) {
                request.setRequestHeader("accept-language", 'en');
                request.setRequestHeader("afkl-travel-country", 'us');
                request.setRequestHeader("afkl-travel-host", 'KL');
                request.setRequestHeader("country", 'us');
                request.setRequestHeader("language", 'en');
                request.setRequestHeader("x-xsrf-token", plugin.getCookie('XSRF-TOKEN'));
            },
            dataType: "json",
            success: function (response) {
                browserAPI.log("parse History");
                if (response.data.flyingBlueTransactionHistory.transactions)
                    response = response.data.flyingBlueTransactionHistory.transactions.transactionsList;
                else response = null;
                //console.log("---------------- data ----------------");
                //console.log(response);
                //console.log("---------------- data ----------------");

                params.data.properties.HistoryRows = [];

                if (response) {
                    for (var i in response) {
                        if (response.hasOwnProperty(i)) {
                            var row = {};

                            var dateStr = util.filter(response[i].transactionDate);
                            var postDate = plugin.dateFormatUTC(dateStr);

                            if (startDate > 0 && postDate < startDate) {
                                browserAPI.log("break at date " + dateStr + " " + postDate);
                                break;
                            }
                            if (postDate === null) {
                                browserAPI.log("Skip bad node");
                                continue;
                            }

                            // description: "My Trip to {#/transactions/transactionsList[0]/finalDestination}"
                            var description = util.filter(response[i].description);
                            var transaction;
                            var key;
                            if (response[i].finalDestination !== null) {
                                transaction = description.replace(/\{.+?finalDestination\}/i, response[i].finalDestination);
                            }
                            // 'description' => 'Car & Taxi - {#/transactions/transactionsList[35]/complementaryDescriptionData[0]}',
                            else if (response[i].complementaryDescriptionData !== null) {
                                for (var j = 0; j < response[i].complementaryDescriptionData.length; j++) {
                                    var re = new RegExp('\\{.+?complementaryDescriptionData\\[' + j + '\\]\\}', 'i');
                                    transaction = description.replace(re, util.trim(response[i].complementaryDescriptionData[j]));
                                }
                            } else
                                transaction = description.replace(/\{.+?\}/i, '');

                            if (typeof (response[i].details) != 'undefined') {
                                for (var detail in response[i].details) {
                                    if (!response[i].details.hasOwnProperty(detail)) {
                                        continue;
                                    }
                                    var complementaryDescription = response[i].details[detail].description;
                                    var complementaryDetailDescriptionData = response[i].details[detail].complementaryDetailDescriptionData;
                                    var ancillaryLabelCategory = response[i].details[detail].ancillaryLabelCategory;
                                    if (
                                        complementaryDescription
                                        && (
                                            complementaryDetailDescriptionData
                                            || ancillaryLabelCategory
                                        )
                                    ) {
                                        if (response[i].details[detail].complementaryDetailDescriptionData) {
                                            for (var j = 0; j < response[i].details[detail].complementaryDetailDescriptionData.length; j++) {
                                                var re = new RegExp('\\{.+?complementaryDetailDescriptionData\\[' + j + '\\]\\}', 'i');
                                                complementaryDescription = complementaryDescription.replace(re, util.trim(response[i].details[detail].complementaryDetailDescriptionData[j]));
                                            }
                                        }

                                        if (/^My Trip to/i.test(description)) {

                                            var transactionDesc = transaction + "; " + complementaryDescription;

                                            if (/Bonus/i.test(transactionDesc))
                                                key = 'Bonus Miles';
                                            else
                                                key = 'Award Miles';

                                            row = {
                                                'Date': postDate,
                                                'Transaction': transactionDesc,
                                                'Travel Date': plugin.dateFormatUTC(response[i].details[detail].activityDate)
                                            };
                                            row[key] = response[i].details[detail].milesAmount;

                                            if (typeof (response[i].details[detail].xpAmount) !== 'undefined' && response[i].details[detail].xpAmount !== null) {
                                                row['Experience Points'] = response[i].details[detail].xpAmount;
                                            }

                                            params.data.properties.HistoryRows.push(row);

                                            continue;
                                        }

                                        transaction += "; " + complementaryDescription;
                                    }
                                    else if (
                                        transaction === ''
                                        && complementaryDescription
                                        && (typeof (complementaryDetailDescriptionData) == 'undefined' || complementaryDetailDescriptionData == null)
                                    ) {
                                        transaction = complementaryDescription;
                                    }
                                }
                            }

                            if (/^My Trip to/i.test(description)) {
                                continue;
                            }

                            if (/Bonus/i.test(transaction))
                                key = 'Bonus Miles';
                            else
                                key = 'Award Miles';

                            row = {
                                'Date': postDate,
                                'Transaction': transaction
                            };
                            row[key] = response[i].milesAmount;

                            if (typeof (response[i].xpAmount) !== 'undefined' && response[i].xpAmount !== null) {
                                row['Experience Points'] = response[i].xpAmount;
                            }

                            //console.log(row);//todo
                            params.data.properties.HistoryRows.push(row);
                        }// if (response.hasOwnProperty(i))
                    }// for (var i in response)
                }// if (response)

                params.account.properties = params.data.properties;
                // console.log(params.account.properties);//todo
                provider.saveProperties(params.account.properties);

                if (typeof(params.account.parseItineraries) == 'boolean' && params.account.parseItineraries) {
                    provider.setNextStep('waitItineraryList', function () {
                        document.location.href = 'https://www.klm.com/trip/overview';
                    });
                }// if (typeof(params.account.parseItineraries) == 'boolean' && params.account.parseItineraries)
                else {
                    browserAPI.log(">>> complete");
                    provider.complete();
                }
            }// success: function (response)
        });// $.ajax({
    },

    dateFormatUTC: function (dateStr, isObjectOrUnix) {
        browserAPI.log('dateFormatUTC');
        // 2018-02-14
        var date = dateStr.match(/(\d{4})-(\d+)-(\d+)/);
        if(date === null)
            return null;
        var year = date[1], month = date[2], day = date[3];
        var dateObject = new Date(Date.UTC(year, month - 1, day, 0, 0, 0, 0));
        var unixTime = dateObject.getTime() / 1000;
        if (!isNaN(unixTime)) {
            browserAPI.log('Date: ' + dateObject + ' UnixTime: ' + unixTime);
            return isObjectOrUnix ? dateObject : unixTime;
        }
        return null;
    },


    // ==== Itineraries ====

    waitItineraryList: function (params) {
        browserAPI.log('waitItineraryList');
        util.waitFor({
            selector: 'a > span:contains("My Trip")',
            success: function (_) {
                plugin.parseItineraries(params);
            },
            fail: function (_) {
                plugin.parseItineraries(params);
            },
        });
    },

    getReservationsJson: function (collback) {
        browserAPI.log('getReservationsJson');
        let data = {
            'operationName': 'TripReservationsQuery',
            'variables': {
                'daysBack': 180
            },
            'extensions': {
                'persistedQuery': {
                    'version': 1,
                    'sha256Hash': plugin.sha256HashReservations
                }
            }
        };
        let result = null;
        $.ajax({
            url: 'https://www.klm.com/gql/v1?bookingFlow=LEISURE',
            async: false,
            data: JSON.stringify(data),
            type: 'POST',
            contentType: 'application/json',
            beforeSend: function (request) {
                request.setRequestHeader('accept-language', 'en');
                request.setRequestHeader('country', 'us');
                request.setRequestHeader('language', 'en');
                request.setRequestHeader('x-xsrf-token', plugin.getCookie('XSRF-TOKEN'));
            },
            dataType: 'json',
            success: function (response) {
                if (
                    response.errors !== undefined
                    && response.errors[0] !== undefined
                    && response.errors[0].message !== undefined
                ) {
                    browserAPI.log(`[error]: ${response.errors[0].message}`);
                }
                if (
                    response.data === undefined
                    || response.data.reservations === undefined
                    || response.data.reservations.trips === undefined
                ) {
                    browserAPI.log('[error]: failed to get trips');
                } else {
                    result = response.data.reservations.trips;
                }
            },
        });
        return result;
    },

    getReservationJson: function (conf, lastName, isRetry) {
        browserAPI.log('getReservationJson');
        if (!conf || !lastName) {
            return null;
        }
        let result = null;
        let data = {
            'operationName': 'TripReservationQuery',
            'variables': {
                'bookingCode': conf,
                'lastName': lastName
            },
            'extensions': {
                'persistedQuery': {
                    'version': 1,
                    'sha256Hash': plugin.sha256HashReservation
                }
            }
        };
        if (isRetry) {
            data = {
                'operationName': 'TripReservationQuery',
                'variables': {
                    'bookingCode': conf,
                    'lastName': lastName
                },
                'extensions': {
                    'persistedQuery': {
                        'version': 1,
                        'sha256Hash': plugin.sha256HashReservation
                    }
                }
            };
        }
        $.ajax({
            url: 'https://www.klm.com/gql/v1?bookingFlow=LEISURE',
            async: false,
            data: JSON.stringify(data),
            type: 'POST',
            contentType: 'application/json',
            beforeSend: function (request) {
                request.setRequestHeader('accept-language', 'en');
                request.setRequestHeader('country', 'us');
                request.setRequestHeader('language', 'en');
                request.setRequestHeader('x-xsrf-token', plugin.getCookie('XSRF-TOKEN'));
            },
            dataType: 'json',
            success: function (response) {
                if (
                    response.errors !== undefined
                    && response.errors[0] !== undefined
                    && response.errors[0].message !== undefined
                    && (
                        response.data === undefined
                        || response.data.reservation === undefined
                        || response.data.reservation === null
                    )
                ) {
                    browserAPI.log(`[error]: ${response.errors[0].message}`);
                    result = response.errors[0].message;
                    return;
                }
                if (
                    response.errors !== undefined
                    && response.errors[0] !== undefined
                    && response.errors[0].message === 'Internal server error'
                    && response.errors[0].code === 'INTERNAL_SERVER_ERROR'
                ) {
                    browserAPI.log(`[error]: ${response.errors[0].message}`);
                    result = response.errors[0].message;
                    return;
                }
                if (
                    response.data !== undefined
                    && response.data.reservation !== undefined
                    && response.data.reservation.messages !== undefined
                    && response.data.reservation.messages[0] !== undefined
                    && response.data.reservation.messages[0].name !== undefined
                ) {
                    let message = response.data.reservation.messages[0].name;
                    browserAPI.log(`[error]: ${message}`);
                    if (message === 'A travel voucher has been requested for this reservation.'
                        || message === 'Refund Eligibility'
                        || message === 'PersistedQueryNotFound') {
                        result = message;
                        return;
                    }
                } else {
                    result = response.data.reservation;
                    return;
                }
            }
        });
        return result;
    },

    parseItineraries: function (params) {
        browserAPI.log('parseItineraries');
        // no Itineraries
        if (
            $('span:contains("You have no current reservations"):visible').length > 0
            || $('h3:contains("You have no upcoming trips"):visible').length > 0
        ) {
            browserAPI.log("no Itineraries");
            params.account.properties.Itineraries = [{NoItineraries: true}];
            provider.saveProperties(params.account.properties);
            provider.complete();
            return;
        }
        let itineraries = [];
        let trips = plugin.getReservationsJson();
        if (trips instanceof Array) {
            if (trips === []) {
                itineraries = [{NoItineraries: true}];
            } else {
                let cntSkipped = 0;
                for (let trip of trips) {
                    let scheduledDate = new Date(`${trip.scheduledReturn}+00:00`);
                    let isPast = scheduledDate < new Date();
                    if (isPast || trip.historical === true) {
                        cntSkipped++;
                        browserAPI.log(`Skipping booking ${trip.bookingCode}: past itinerary`);
                        continue;
                    }
                    let reservation = plugin.getReservationJson(trip.bookingCode, trip.lastName, false);
                    if (reservation === 'PersistedQueryNotFound') {
                        reservation = plugin.getReservationJson(trip.bookingCode, trip.lastName, true);
                    }
                    if (
                        typeof reservation === 'string'
                        || reservation === null
                    ) {
                        continue;
                    }
                    let parsedTrips = plugin.parseReservationJson(trip.bookingCode, reservation);
                    $.each(parsedTrips, function (index, parsedTrip) {
                        itineraries.push(parsedTrip);
                    });
                }
                if (trips.length === cntSkipped && itineraries.length === 0) {
                    itineraries = [{NoItineraries: true}];
                }
            }
        }
        params.account.properties.Itineraries = itineraries;
        provider.saveProperties(params.account.properties);
        provider.complete();
    },

    parseReservationJson: function (conf, reservation, fromTrip) {
        browserAPI.log('parseReservationJson');
        fromTrip = fromTrip ? true : false;
        let res = {};
        let resTrain = {};
        // RecordLocator
        res.RecordLocator = conf;
        resTrain.RecordLocator = conf;
        resTrain.TripCategory = 3;
        browserAPI.log(`res.RecordLocator = ${res.RecordLocator}`);
        // Passengers, TicketNumbers, AccountNumbers, EarnedAwards
        let passengers = [];
        let ticketNumbers = [];
        let accountNumbers = [];
        let totalMiles = 0;
        for (let passenger of reservation.passengers || []) {
            if (passenger.type !== 'INFANT') {
                let traveller = util.beautifulName(`${passenger.firstName || ''} ${passenger.lastName || ''}`).trim();
                if (traveller) {
                    passengers.push(traveller);
                }
            }
            if (!fromTrip) {
                for (let ticketNumber of passenger.ticketNumber || []) {
                    ticketNumbers.push(ticketNumber);
                }
                for (let membership of passenger.memberships || []) {
                    accountNumbers.push(membership.number);
                }
                if (
                    passenger.earnQuote
                    && passenger.earnQuote.totalMiles !== undefined
                ) {
                    totalMiles += passenger.earnQuote.totalMiles;
                }
            }
        }
        res.Passengers = plugin.arrayUnique(passengers);
        resTrain.Passengers = plugin.arrayUnique(passengers);
        browserAPI.log(`res.Passengers = ${res.Passengers}`);
        res.TicketNumbers = plugin.arrayUnique(ticketNumbers);
        browserAPI.log(`res.TicketNumbers = ${res.TicketNumbers}`);
        res.AccountNumbers = plugin.arrayUnique(accountNumbers);
        browserAPI.log(`res.AccountNumbers = ${res.AccountNumbers}`);
        if (totalMiles) {
            res.EarnedAwards = totalMiles;
            browserAPI.log(`res.EarnedAwards = ${res.EarnedAwards}`);
        }

        // SpentAwards
        // TotalCharge and Currency
        let totalPrice = [];
        if (typeof reservation.ticketInfo.totalPrice !== 'undefined')
            totalPrice = reservation.ticketInfo.totalPrice;

        for (let item of totalPrice) {
            let currencyCode = item.currencyCode;
            //let currency = this.currency(currencyCode);
            if (currencyCode === 'MLS') {
                res.SpentAwards = item.amount;
                browserAPI.log(`res.SpentAwards = ${res.SpentAwards}`);
                continue;
            }
            if (typeof res.TotalCharge === 'undefined' || item.amount > res.TotalCharge) {
                res.TotalCharge = item.amount;
                browserAPI.log(`res.TotalCharge = ${res.TotalCharge}`);
                res.Currency = currencyCode;
                browserAPI.log(`res.Currency = ${res.Currency}`);
            }
        }

        // TripSegments
        let connections = [];
        if (
            reservation.itinerary !== undefined
            && reservation.itinerary.connections !== undefined
        ) {
            connections = reservation.itinerary.connections;
        }
        res.TripSegments = [];
        resTrain.TripSegments = [];
        for (let connection of connections) {
            for (let segment of connection.segments || []) {
                if (
                    segment.flight.flightNumber === undefined
                    && segment.isCancelled
                ) {
                    continue;
                }
                let ts = {};
                // DepCode
                ts.DepCode = segment.origin.airportCode;
                browserAPI.log(`ts.DepCode = ${ts.DepCode}`);
                // DepName
                ts.DepName = segment.origin.airportName;
                browserAPI.log(`ts.DepName = ${ts.DepName}`);
                // DepDate
                let depDate;

                if (typeof (segment.flight.newDepartureDate) != 'undefined' && segment.flight.newDepartureDate !== null) {
                    depDate = new Date(`${segment.flight.newDepartureDate}+00:00`);
                } else {
                    depDate = new Date(`${segment.flight.departureDate}+00:00`);
                }

                if (isNaN(depDate.getTime())) {
                    ts.DepDate = null;
                    if (segment.flight.departureDate === null) {
                        ts.DepDate = -1;
                    }
                } else {
                    ts.DepDate = depDate.getTime() / 1000;
                }
                browserAPI.log(`ts.DepDate = ${ts.DepDate} (${depDate})`);
                // ArrCode
                ts.ArrCode = segment.destination.airportCode;
                browserAPI.log(`ts.ArrCode = ${ts.ArrCode}`);
                // ArrName
                ts.ArrName = segment.destination.airportName;
                browserAPI.log(`ts.ArrName = ${ts.ArrName}`);
                // ArrDate
                let arrDate;

                if (typeof (segment.flight.newArrivalDate) != 'undefined' && segment.flight.newArrivalDate !== null) {
                    arrDate = new Date(`${segment.flight.newArrivalDate}+00:00`);
                } else {
                    arrDate = new Date(`${segment.flight.arrivalDate}+00:00`);
                }

                if (isNaN(arrDate.getTime())) {
                    ts.ArrDate = null;
                    if (segment.flight.arrivalDate === null) {
                        ts.ArrDate = -1;
                    }
                } else {
                    ts.ArrDate = arrDate.getTime() / 1000;
                }
                browserAPI.log(`ts.ArrDate = ${ts.ArrDate} (${arrDate})`);
                // AirlineName
                ts.AirlineName = segment.flight.carrierCode;
                browserAPI.log(`ts.AirlineName = ${ts.AirlineName}`);
                // FlightNumber
                if (segment.flight.flightNumber === undefined) {
                    ts.FlightNumber = 'UnknownFlightNumber';
                } else {
                    ts.FlightNumber = segment.flight.flightNumber;
                }
                browserAPI.log(`ts.FlightNumber = ${ts.FlightNumber}`);
                // Duration
                let duration = `${Math.floor(segment.flight.duration / 60)}h ${Math.floor(segment.flight.duration % 60)}m`;
                ts.Duration = duration;
                browserAPI.log(`ts.Duration = ${ts.Duration}`);
                // Aircraft
                ts.Aircraft = null;
                if (
                    segment.flight.equipment
                ) {
                    ts.Aircraft = segment.flight.equipment.name || null;
                }
                browserAPI.log(`ts.Aircraft = ${ts.Aircraft}`);
                // Cabin
                ts.Cabin = segment.flight.cabinClass || null;
                browserAPI.log(`ts.Cabin = ${ts.Cabin}`);

                // Cancelled
                if (segment.isCancelled) {
                    ts.Cancelled = true;
                    browserAPI.log(`ts.Cancelled = ${ts.Cancelled} segment`);
                }

                // Seats
                ts.Seats = [];
                if (
                    segment.ancillaries !== undefined
                ) {
                    for (let seat of segment.ancillaries.seats || []) {
                        if (seat.seatNumbers) {
                            for (let seatNumber of seat.seatNumbers) {
                                if (/^[A-Z\d\-\/]{1,7}$/.test(seatNumber))
                                    ts.Seats.push(seatNumber);
                            }
                        }
                    }
                    ts.Seats = plugin.arrayUnique(ts.Seats);
                    browserAPI.log(`ts.Seats = ${ts.Seats}`);
                }
                if ((segment.flight.equipment && segment.flight.equipment.code === 'TRN')
                    || ts.DepName.indexOf('Railway Station') !== -1
                    || ts.DepName.indexOf('railway Station') !== -1
                    || ts.ArrName.indexOf('Railway Station') !== -1
                    || ts.ArrName.indexOf('railway Station') !== -1
                ) {
                    ts.Aircraft = null;
                    resTrain.TripSegments.push(ts);
                } else {
                    res.TripSegments.push(ts);
                }
            }
        }

        // Taxes
        let data = {
            "operationName": "TripReservationTicketPriceBreakdownQuery",
            "variables": {"id": reservation.id},
            "extensions": {
                "persistedQuery": {
                    "version": 1,
                    "sha256Hash": '2645ba4eec72a02650ae63c2bd78d14a3f0025dddfca698f570b96a630667fe0',
                },
            },
        };
        $.ajax({
            url: 'https://www.klm.com/gql/v1?bookingFlow=LEISURE',
            async: false,
            data: JSON.stringify(data),
            type: 'POST',
            contentType: 'application/json',
            beforeSend: function (request) {
                request.setRequestHeader('accept-language', 'en');
                request.setRequestHeader('country', 'us');
                request.setRequestHeader('language', 'en');
                //request.setRequestHeader('x-xsrf-token', plugin.getCookie('XSRF-TOKEN'));
            },
            dataType: 'json',
            success: function (data) {
                if (data.data && data.data.reservation && data.data.reservation.ticketInformation
                    && data.data.reservation.ticketInformation.passengersTicketInformation) {
                    let taxes = [];
                    for  (const information of data.data.reservation.ticketInformation.passengersTicketInformation) {
                        if (information.taxes && information.taxes.totalPrice && information.taxes.totalPrice.amount)
                            taxes.push(information.taxes.totalPrice.amount);
                    }
                    if (taxes.length) {
                        res.TotalTaxAmount = taxes.reduce((a, b) => a + b, 0);
                        browserAPI.log(`res.TotalTaxAmount = ${res.TotalTaxAmount}`);
                    }
                }
            }
        });

        if (resTrain.TripSegments.length > 0) {
            return [res, resTrain];
        }
        return [res];
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        util.waitFor({
            selector: 'form.ng-pristine',
            success: function(form) {
                form.find('input[name = "bookingCode"]').val(properties.ConfNo);
                util.sendEvent(form.find('input[name = "bookingCode"]').get(0), 'input');
                form.find('input[name = "lastName"]').val(properties.LastName);
                util.sendEvent(form.find('input[name = "lastName"]').get(0), 'input');
                provider.setNextStep('itLoginComplete', function() {
                    setTimeout(function() {
                        form.find('button[bwmitagclick="pnrlastname.click"]:contains("Search")').get(0).click();
                    }, 2000);
                });
            },
            fail: function() {
                provider.setError(util.errorMessages.itineraryFormNotFound);
            }
        })
    },

    unionArray: function (elem, separator, unique) {
        // $.map not working in IE 8, so iterating through items
        var result = [];
        for (var i = 0; i < elem.length; i++) {
            var text = util.trim(elem.eq(i).text());
            if (text != "" && (!unique || result.indexOf(text) == -1))
                result.push(text);
        }
        return result.join(separator);
    },

    arrayUnique: function (array) {
        var unique = [];
        for (var i = 0; i < array.length; ++i) {
            if (unique.indexOf(array[i]) == -1)
                unique.push(array[i]);
        }
        return unique;
    }

};
