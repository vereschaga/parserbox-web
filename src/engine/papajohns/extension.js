var plugin = {
    // keepTabOpen: true,// TODO
    hideOnStart: true,
    hosts: {'www.papajohns.com': true, 'www.papajohns.co.uk': true},

    getFocusTab: function (account, params) {
        return true;
    },

    getStartingUrl: function (params) {
        if (params.account.login2 === 'UK') {
            return 'http://www.papajohns.co.uk/my-papa-rewards.aspx';
        }

        return 'https://www.papajohns.com/order/account/my-account';
    },

    start: function (params) {
        browserAPI.log("start");
        browserAPI.log('Current URL: ' + document.location.href);
        browserAPI.log("UserAgent -> " + navigator.userAgent);
        browserAPI.log("UserAgent -> " + JSON.stringify(util.detectBrowser()));
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
                }
                else
                    plugin.loadLoginForm(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 15) {
                clearInterval(start);
                provider.logBody("lastPage");
                const error = $('h5:contains("John\'s Online Ordering can not take your order at this time due to technical difficulties."):visible');

                if (error.length > 0) {
                    provider.setError([error.text(), util.errorCodes.providerError], true);
                    return;
                }

                provider.setError(util.errorMessages.unknownLoginState, true);
                return;
            }// if (isLoggedIn === null && counter > 15)
            counter++;
        }, 500);
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        let url;
        if (params.account.login2 === 'UK') {
            browserAPI.log("Region => UK");
            url = plugin.getStartingUrl(params);
        }
        else {
            browserAPI.log("Region => USA");
            url = 'https://www.papajohns.com/order/sign-in';
        }
        provider.setNextStep('login', function () {
            document.location.href = url;
        });
    },

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");
        if (params.account.login2 === 'UK') {
            browserAPI.log("Region => UK");
            if ($('a#ctl00__objHeader_lbLoginRegisterItem').find('span:contains("Sign in")').length > 0) {
                browserAPI.log("not LoggedIn");
                return false;
            }
            if ($('a[href *= "sign-out"]').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
            error = $('p:contains("Sorry for the inconvenience but we\'re in the process"):visible');
            if (error.length > 0) {
                provider.setError([error.text(), util.errorCodes.providerError], true);
                return null;
            }
        }
        else {
            browserAPI.log("Region => USA");
            if ($('a[href *= "signout"], a#signoutbutton, a#signoutbutton-header-nav-utility-mobile').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
            if ($('form[id = "header-account-sign-in-form"]').length > 0) {
                browserAPI.log("not LoggedIn");
                return false;
            }
            var error = $('h5:contains("s Online Ordering can not take your order at this time due to technical difficulties."),' +
                ' h2:contains("s Online Ordering can not take your order at this time due to technical difficulties.")');
            if (error.length > 0) {
                provider.setError([error.text(), util.errorCodes.providerError], true);
                return null;
            }
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let name;
        if (account.login2 === 'UK') {
            browserAPI.log("Region => UK");
            name = util.beautifulName(util.findRegExp($('div#ctl00__objHeader_pnlLoggedInUserTitle > span > span').text(), /Hi\s*([^\!<]+)/i));
        }
        else {
            browserAPI.log("Region => USA");
            name = $('article.contact > p > strong');
            if (name.length === 0)
                name = $('div.extra-padding-flyout-content h3.heading-3');
            if (name.length > 0)
                name = util.beautifulName(name.text());
        }
        browserAPI.log("name: " + name);
        return typeof(account.properties) !== 'undefined'
            && typeof(account.properties.Name) !== 'undefined'
            && account.properties.Name !== ''
            && name === account.properties.Name;
    },

    logout: function (params) {
        browserAPI.log("logout");

        if (params.account.login2 === 'UK') {
            browserAPI.log("Region => UK");
            provider.setNextStep('start', function () {
                document.location.href = 'https://www.papajohns.co.uk/sign-out.aspx';
            });
            return;
        }

        browserAPI.log("Region => USA");
        provider.setNextStep('start', function () {
            $('a#signoutbutton-header-nav-utility').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        let form;
        if (params.account.login2 === 'UK') {
            browserAPI.log("Region => UK");
            // open login form
            var openForm = $('a#ctl00__objHeader_lbLoginRegisterItem');
            if (openForm.length)
                openForm.get(0).click();
            // wait login form
            var counter = 0;
            var login = setInterval(function () {
                form = $('form#aspnetForm');
                var loginField = form.find('input[name = "ctl00$_objHeader$txtEmail1"]');
                browserAPI.log("waiting... " + login);
                if (form.length > 0 && loginField.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    clearInterval(login);
                    loginField.val(params.account.login);
                    form.find('input[name = "ctl00$_objHeader$txtPassword"]').val(params.account.password);
                    provider.setNextStep('checkLoginErrors', function () {
                        var captcha = form.find('iframe[src *= "https://www.google.com/recaptcha/api2/anchor"]:visible');
                        if (captcha.length > 0) {
                            provider.reCaptchaMessage();
                            var counter = 0;
                            var login = setInterval(function () {
                                browserAPI.log("waiting... " + counter);
                                if (counter > 120) {
                                    clearInterval(login);
                                    provider.setError(util.errorMessages.captchaErrorMessage, true);
                                    return;
                                }
                                counter++;
                            }, 1000);
                            form.find('#ctl00__objHeader_lbSignIn').click(function () {
                                clearInterval(login);
                                setTimeout(function () {
                                    plugin.checkLoginErrors(params);
                                }, 5000);
                            });
                        } else {
                            browserAPI.log("captcha is not found");
                            form.find('#ctl00__objHeader_lbSignIn').get(0).click();
                            setTimeout(function () {
                                plugin.checkLoginErrors(params);
                            }, 7000);
                        }
                    });
                }
                if (counter > 10) {
                    clearInterval(login);
                    provider.setError(util.errorMessages.loginFormNotFound);
                }
                counter++;
            }, 500);
        }
        else {
            browserAPI.log("Region => USA");
            // open popup
            var popup = $('a[aria-controls="popup-login"]');
            if (popup.length > 0) {
                browserAPI.log("click");
                popup.get(0).click();
            }
            else
                browserAPI.log("link not found");

            form = $('form[id = "header-account-sign-in-form"]');
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                form.find('input[name = "user"]').val(params.account.login);
                form.find('input[name = "pass"]').val(params.account.password);

                provider.setNextStep('checkLoginErrors', function () {
                    setTimeout(function () {
                        var captcha = form.find('iframe[src *= "https://www.google.com/recaptcha/api2/anchor"]:visible');
                        if (captcha.length > 0) {
                            provider.reCaptchaMessage();
                            var counter = 0;
                            var login = setInterval(function () {
                                browserAPI.log("waiting... " + counter);
                                if (counter > 120) {
                                    clearInterval(login);
                                    provider.setError(util.errorMessages.captchaErrorMessage, true);
                                    return;
                                }
                                counter++;
                            }, 1000);
                            form.find('input[value = "Log In"]').click(function () {
                                clearInterval(login);
                                setTimeout(function () {
                                    plugin.checkLoginErrors(params);
                                }, 5000);
                            });
                        } else {
                            browserAPI.log("captcha is not found");
                            form.find('input[value = "Log In"]').get(0).click();
                            setTimeout(function () {
                               plugin.checkLoginErrors(params);
                            }, 7000)
                        }
                    }, 1000);
                });
            }
            else
                provider.setError(util.errorMessages.loginFormNotFound);
        }
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors;

        if(document.location.href.indexOf('/password-reset') !== -1 && $('h3:contains("Create a New Password")').length) {
            provider.setError(['Please change your password to continue.', util.errorCodes.providerError]);
            return;
        }

        var counter = 0;
        var complete = setInterval(function () {
            browserAPI.log("Complete waiting... " + counter);

            errors = $('div.captchaLoginFormHolder:visible');
            if (errors.length > 0) {
                clearInterval(complete);
                provider.setError('Invalid credentials', true);
                return;
            }
            if (errors.length === 0)
                errors = $('div#ctl00__objHeader_pnlLoginError:visible');
            if (errors.length === 0)
                errors = $('span#omnibar-recaptcha_error_msg:visible');
            if (errors.length === 0)
                errors = $('span#header-recaptcha_error_msg:visible');
            if (errors.length === 0)
                errors = $('#header-account-sign-in-email-error:visible');
            if (errors.length > 0 && util.filter(errors.text()) !== '') {
                clearInterval(complete);
                provider.setError(util.filter(errors.text()), true);
                return;
            }
            if (counter > 10
                // US
                || util.filter(util.findRegExp($('a[aria-controls="popup-user"]').text(), /Hi,\s*(.+)/i)).length > 0
                // UK
                || $('#ctl00__objHeader_pnlLoggedInUserTitle').length) {
                clearInterval(complete);
                plugin.loginComplete(params);
                return;
            }
            counter++;
        }, 1000);

    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.logBody("loginCompletePage");
        if (params.autologin) {
            provider.complete();
            return;
        }
        browserAPI.log("Loading account");
        var url;
        if (params.account.login2 === 'UK') {
            browserAPI.log("Region => UK");
            url = 'http://www.papajohns.co.uk/my-papa-rewards.aspx';
        }
        else {
            browserAPI.log("Region => USA");
            url = plugin.getStartingUrl(params);
        }
        if (document.location.href !== url) {
            provider.setNextStep('parse', function () {
                document.location.href = url;
            });

            return;
        }

        plugin.parse(params);
    },

    parseUK: function (params) {
        var data = {};
        // Balance - table "Your Reward History" -> first row -> field "Balance"
        var balance = util.findRegExp($('span#ctl00_cphBody_rptPoints_ctl00_lblPointsTotal').text(), /([\d\.\,]+)/i);
        if (balance && balance.length > 0) {
            browserAPI.log("Balance: " + balance);
            data.Balance = balance;
        } else {
            browserAPI.log("Balance not found");
            if ($('table.nutritionalTable tr').length == 2)
                data.Balance = "null";
        }
        // Name
        var name = util.findRegExp($('div#ctl00__objHeader_pnlLoggedInUserTitle > span > span').text(), /Hi\s*([^\!<]+)/i);
        if (name && name.length > 0) {
            name = util.beautifulName(name);
            browserAPI.log("Name: " + name);
            data.Name = name;
        } else
            browserAPI.log("Name not found");

        // save data
        params.data.properties = data;
        provider.saveTemp(params.data);

        provider.setNextStep('parseExpDateUK', function () {
            document.location.href = 'https://www.papajohns.co.uk/my-previous-orders.aspx';
        });
    },

    parseExpDateUK: function (params) {
        var data = params.data.properties;
        var nodes = $('#ctl00_cphBody_divPreviousOrders table tr');
        var maxDate = 0;
        $.each(nodes, function (key, value) {
            var expDate = $(value).find('td.orderDate');
            var lastActivity = new Date(expDate.text() + ' UTC');
            if (lastActivity && lastActivity.getTime() > maxDate) {
                maxDate = lastActivity.getTime();
                var exp = lastActivity;
                exp.setMonth(exp.getMonth() + 6);
                var unixtime = exp / 1000;
                if (!isNaN(unixtime) ) {
                    browserAPI.log("ExpirationDate = lastActivity + 6 month");
                    browserAPI.log("Expiration Date: " + lastActivity + " Unixtime: " + unixtime);
                    data.AccountExpirationDate = unixtime;
                    data.LastActivity = expDate.text();
                    data.AccountExpirationWarning = "Papa John's Pizza (Papa Rewards) state the following on their website: <a target=\"_blank\" href=\"https://www.papajohns.co.uk/terms-and-conditions/papa-rewards.aspx\">Points will expire 6 months after the customers last order date</a>."
                    + "<br><br>We determined that last time you had account activity with Papa John's Pizza on " + data.LastActivity + ", so the expiration date was calculated by adding 6 months to this date.";
                }
            }
        });
        // Save properties
        params.account.properties = data;
        //console.log(params.account.properties);
        provider.saveProperties(params.account.properties);
        provider.complete();
    },

    parse: function (params) {
        browserAPI.log("parse");
        provider.updateAccountMessage();
        browserAPI.log('Current URL: ' + document.location.href);

        if (params.account.login2 === 'UK') {
            plugin.parseUK(params);
            return;
        }

        var data = {};
        // Name
        var name = $('article.contact > p > strong');
        if (name.length === 0)
            name = $('div.extra-padding-flyout-content h3.heading-3');
        if (name.length > 0) {
            name = util.beautifulName(name.text());
            browserAPI.log("Name: " + name);
            data.Name = name;
        } else
            browserAPI.log("Name is not found");

        // save data
        params.data.properties = data;
        provider.saveTemp(params.data);

        provider.setNextStep('parseBalance', function () {
            document.location.href = 'https://www.papajohns.com/order/account/my-papa-rewards';
        });
    },

    parseBalance: function (params) {
        browserAPI.log("parseBalance");
        provider.updateAccountMessage();
        browserAPI.log('Current URL: ' + document.location.href);
        let data = params.data.properties;
        // Balance - 0/75 Points
        const balance = util.findRegExp($('div#popup-user p:contains("Points") + strong').text(), /([\d\.\,]+)\/[\d\.\,]+/i);
        if (balance && balance.length > 0) {
            browserAPI.log("Balance: " + balance);
            data.Balance = balance;
        } else {
            browserAPI.log("Balance not found");
            const message = $('p:contains("We are having trouble getting your rewards information. Try again later."):visible');

            if (message.length === 1) {
                provider.setWarning(util.filter(message.text()));
            }
        }
        // 27 more to get $10.00 of Papa Dough
        const pointsGoal = util.findRegExp($('span.points-to-go').text(), /([\d]+)\s*more to get/i);

        if (pointsGoal && pointsGoal.length > 0) {
            data.PointsNextReward = pointsGoal;
            browserAPI.log("PointsNextReward: " + data.PointsNextReward);
        } else
            browserAPI.log("PointsNextReward not found");

        // My Papa Dough
        const myPapaDough = util.findRegExp($('div#popup-user p:contains("My Papa Dough") + strong').text(), /(\$[\d\,\.\-\s]+)/i);
        let subAccounts = [];
        if (myPapaDough && myPapaDough.length > 0) {
            browserAPI.log("My Papa Dough: " + myPapaDough);
            subAccounts.push({
                "Code": 'papajohnsUSAMyPapaDough',
                "DisplayName": 'My Papa Dough',
                "Balance": myPapaDough
            });
        } else
            browserAPI.log("My Papa Dough is not found");

        data.SubAccounts = subAccounts;
        data.CombineSubAccounts = 'false';

        // save data
        params.data.properties = data;
        provider.saveTemp(params.data);

        provider.setNextStep('parseExpDateUSA', function () {
            document.location.href = 'https://www.papajohns.com/order/account/my-papa-rewards/reward-history';
        });
    },

    parseExpDateUSA: function (params) {
        browserAPI.log("parseExpDateUSA");
        provider.updateAccountMessage();

        util.waitFor({
            selector: 'p.reward-point__date:eq(0)',
            success: function(elem) {
                // Expiration Date
                const lastActivity = elem.text();

                if (lastActivity) {
                    let date = new Date(lastActivity + ' UTC');
                    let exp = date;
                    exp.setMonth(exp.getMonth() + 12);
                    const unixtime = exp / 1000;
                    if (!isNaN(unixtime) ) {
                        browserAPI.log("ExpirationDate = lastActivity + 12 month");
                        browserAPI.log("Expiration Date: " + lastActivity + " Unixtime: " + unixtime);
                        params.data.properties.AccountExpirationDate = unixtime;
                        params.data.properties.LastActivity = lastActivity;
                    }
                }

                saveProperties();
            },
            fail: function() {
                saveProperties();
            },
            timeout: 10
        });

        // Save properties
        function saveProperties () {
            params.account.properties = params.data.properties;
            // console.log(params.account.properties);// TODO
            provider.saveProperties(params.account.properties);
            provider.complete();
        }
    }

};
