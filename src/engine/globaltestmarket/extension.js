var plugin = {

    hideOnStart: true,
    // keepTabOpen: true,//todo
    hosts: {'www.lifepointspanel.com': true},

    getStartingUrl: function (params) {
        return 'https://www.lifepointspanel.com/member/account';
    },

    getFocusTab: function (account, params) {
        return true;
    },

    start: function (params) {
        browserAPI.log("start");
        browserAPI.log("UserAgent -> " + navigator.userAgent);
        browserAPI.log("UserAgent -> " + JSON.stringify(util.detectBrowser()));
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loadAccount(params);
                    else
                        plugin.logout(params);
                }
                else
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

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var firstName = $('input[name = "first_name"]');
        var lastName = $('input[name = "last_name"]');
        var name = null;
        if (firstName.length && lastName.length) {
            name = util.filter(firstName.attr('value') + ' ' + lastName.attr('value'));
        } else
            browserAPI.log("Name is not found");
        browserAPI.log("name: " + name);
        return ((typeof (account.properties) !== 'undefined')
                && (typeof (account.properties.Name) !== 'undefined')
                && name
                && (account.properties.Name !== '')
                && (name.toLowerCase() === account.properties.Name.toLowerCase()));
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form#lp-login-form:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *= "logout"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://www.lifepointspanel.com/logout';
        });
    },

    loadLoginForm: function () {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form#lp-login-form:visible');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "contact_email"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);

            provider.setNextStep('checkLoginErrors', function () {
                var loginBtn = form.find('#edit-submit-button');

                setTimeout(function () {
                    var captcha = form.find('div.captcha:visible');
                    if (captcha && captcha.length > 0) {
                        browserAPI.log("login waiting...");
                        if (!provider.isMobile) {
                            provider.reCaptchaMessage();
                            var counter = 0;
                            var login = setInterval(function () {
                                browserAPI.log("waiting captcha... " + counter);
                                if (counter > 120) {
                                    clearInterval(login);
                                    provider.setError(util.errorMessages.captchaErrorMessage, true);
                                    return;
                                }
                                counter++;
                            }, 1000);
                        } else {
                            browserAPI.log(">>> mobile");
                            provider.command('show', function () {
                                provider.reCaptchaMessage();
                                var fakeButton = loginBtn.removeClass("disabled").prop('disabled').clone();
                                form.find('div:has([id=submitGroup])').append(fakeButton);
                                loginBtn.hide();
                                fakeButton.unbind('click mousedown mouseup tap tapend');
                                fakeButton.bind('click', function (event) {
                                    event.preventDefault();
                                    event.stopPropagation();
                                    if (params.autologin) {
                                        browserAPI.log("captcha entered by user");
                                        provider.setNextStep('checkLoginErrors', function () {
                                            loginBtn.get(0).click();
                                        });
                                    } else {
                                        provider.command('hide', function () {
                                            browserAPI.log("captcha entered by user");
                                            provider.setNextStep('checkLoginErrors', function () {
                                                loginBtn.get(0).click();
                                            });
                                        });
                                    }
                                });
                            });
                        }
                    }// if (captcha.length > 0)
                    else {
                        browserAPI.log("captcha is not found");
                        provider.logBody("captchaNotFoundPage");
                        loginBtn.get(0).click();
                    }
                }, 2000);
            });
        } else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        provider.logBody("checkLoginErrors");
        let errors = $('div.alert-danger:visible');
        if (errors && util.filter(errors.text()) !== '') {
            errors = util.findRegExp(util.filter(errors.text()), /(?:Error message|)\s*([^<]+)/i);
            browserAPI.log("Error => " + errors);
            if (
                /The answer entered for the CAPTCHA is not correct/i.test(errors)
                || /The answer you entered for the CAPTCHA was not correct\./i.test(errors)
                || /We are unable to process the request at this moment, please try again later.\./i.test(errors)
            ) {
                provider.setError(util.errorMessages.captchaErrorMessage, true);
            } else if (
                /Sorry, your membership is temporarily unavailable. Please contact our Help Center for support\./i.test(errors)
                || /Sorry, your membership is temporarily unavailable. Please contact our Help Centre for support\./i.test(errors)
                || /Membership not verified\. When you first registered we sent an email containing a link to verify this email address\./i.test(errors)
                || /We are unable to process the request at this moment, please try again later\./i.test(errors)
                || /Désolé, votre compte n’est pas disponible pour le moment\./i.test(errors)
                || /We are sorry to inform you that you have not qualified for our community\./i.test(errors)
                || /Sorry, your membership is temporarily unavailable\. Please contact our Help Center for support\./i.test(errors)
            ) {
                provider.setError([errors, util.errorCodes.providerError], true);
            }
            else {
                provider.setError(errors, true);
            }
        }
        else {
            var url = 'https://www.lifepointspanel.com/member/account';
            if (document.location.href !== url) {
                provider.setNextStep('loadAccount', function () {
                    document.location.href = url;
                });
            }// if (document.location.href != url)
            else
                plugin.loadAccount(params);
        }
    },

    loadAccount: function (params) {
        browserAPI.log("loadAccount");
        provider.logBody("loadAccount");
        if (params.autologin) {
            provider.complete();
            return;
        }
        browserAPI.log("Loading account");
        plugin.parse(params);
    },

    parse: function (params) {
        browserAPI.log("parse");
        provider.updateAccountMessage();
        browserAPI.log("[Current URL]: " + document.location.href);
        provider.logBody("parsePage");
        var data = {};
        // Name
        var firstName = $('input[name = "first_name"]');
        var lastName = $('input[name = "last_name"]');
        if (firstName.length && lastName.length) {
            var name = util.beautifulName(util.trim(firstName.attr('value') + ' ' + lastName.attr('value')));
            browserAPI.log("Name: " + name);
            params.data.Name = name;
        } else
            browserAPI.log("Name is not found");

        params.data.properties = data;
        provider.saveTemp(params.data);

        // Last Transaction Date
        provider.setNextStep('parseLastActivity', function () {
            document.location.href = 'https://www.lifepointspanel.com/member/activity';
        });
    },

    parseLastActivity: function (params) {
        browserAPI.log("parseLastActivity");
        // Balance
        var balance = $('div.numberCircle-large:visible');
        if (balance.length > 0) {
            browserAPI.log("Balance: " + balance.text());
            balance = util.findRegExp(util.filter(balance.text()), /([\d\.\,\s]+)/i);
            browserAPI.log("Balance: " + balance);
            params.data.properties.Balance = balance;
        } else {
            browserAPI.log("Balance is not found");
        }
        var lastAccountActivity = util.findRegExp($('table#my-activity-list tr:last > td:eq(2)').text(), /(\d+\/\d+\/\d+)/i);
        browserAPI.log("Last Activity: " + lastAccountActivity);
        // Last Activity
        if (lastAccountActivity && lastAccountActivity.length > 0) {
            params.data.properties.LastActivity = lastAccountActivity;
            if ((typeof (params.data.properties.LastActivity) != 'undefined') && (params.data.properties.LastActivity != '')) {
                var date = new Date(params.data.properties.LastActivity + ' UTC');
                date.setFullYear(date.getFullYear() + 1);
                var unixtime = date / 1000;
                if (date != 'NaN') {
                    browserAPI.log("ExpirationDate = lastActivity + 1 year");
                    browserAPI.log("Expiration Date: " + date + " Unixtime: " + util.trim(unixtime));
                    params.data.properties.AccountExpirationDate = unixtime;
                }// if ( date != 'NaN' )
            }
        } else
            browserAPI.log("Last Activity not found");

        params.account.properties = params.data.properties;
        provider.saveProperties(params.account.properties);
        provider.complete();
    }

};
