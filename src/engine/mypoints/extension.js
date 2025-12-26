var plugin = {

    hideOnStart: true,
    clearCache: true,
    // keepTabOpen: true,//todo
    hosts: {'www.mypoints.com': true, '.mypoints.com': true},

    rewardsPageURL: 'https://www.mypoints.com/account-ledger',

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return plugin.rewardsPageURL;
    },

    getFocusTab: function (account, params) {
        return true;
    },

    start: function (params) {
        browserAPI.log("start");
        browserAPI.log('Location: ' + document.location.href);
        browserAPI.log("UserAgent -> " + navigator.userAgent);
        browserAPI.log("UserAgent -> " + JSON.stringify(util.detectBrowser()));

        provider.setNextStep('start2', function () {
            document.location.href = "https://www.mypoints.com/login";
        });
    },

    start2: function (params) {
        browserAPI.log("start2");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("start waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loadAccount(params);
                    else
                        plugin.logout();
                }
                else {
                    setTimeout(function() {
                        plugin.login(params);
                    }, 1000);
                }
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 15) {
                clearInterval(start);
                provider.logBody("lastPage");
                // maintenance
                let error = $('h3:contains("We are busy making the MyPoints website even better."):visible');
                if (error.length === 0)
                    error = $('p:contains("The MyPoints web site is currently unavailable."):visible');
                if (error.length > 0) {
                    provider.setError([error.text(), util.errorCodes.providerError], true);
                    return;
                }
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 15)
            counter++;
        }, 500);
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let name = util.beautifulName(util.trim($('a:has(span[class = "caret orange"]):visible').text()));
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) !== 'undefined')
            && (typeof(account.properties.Name) !== 'undefined')
            && (account.properties.Name !== '')
            && (name.toLowerCase() === account.properties.Name.toLowerCase() ));
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if (
            $('form#signinForm:visible').length > 0
            && $('#login-page-title:contains("Account Authentication")').length == 0
        ) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[onclick *= "logout()"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function(){
            provider.eval("$('a[onclick *= \"logout()\"]').click();");
            // $('a[onclick *= "logout()"]').get(0).click();
        });
    },

    loadLoginForm: function () {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function(){
            document.location.href = plugin.getStartingUrl();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form#signinForm:visible');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            var email = form.find('#email');
            email.val(params.account.login);
            email.focus().change().blur();
            util.sendEvent(email.get(0), 'input');
            var password = form.find('#password');
            password.val(params.account.password);
            password.focus().change().blur();
            util.sendEvent(password.get(0), 'input');
            provider.setNextStep('checkLoginErrors', function(){
                var loginBtn = form.find('#loginBtn');

                function waiting() {
                    browserAPI.log("waiting...");
                    var waitingCounter = 0;
                    var login = setInterval(function () {
                        browserAPI.log("waiting... " + waitingCounter);
                        var errors = $('div#errTxt');
                        if (errors.length == 0)
                            errors = $('div.info:contains("We are unable to complete your request.")');
                        if (errors.length == 0)
                            errors = $('div[class *= "alert alert-danger"]:visible');
                        if (errors.length > 0 && util.trim(errors.text()) !== '') {
                            var form = $('form#signinForm');
                            if (form.length > 0 && /A Captcha is required to proceed/i.test(errors.text())) {
                                clearInterval(login);
                                form.find('#email').val(params.account.login);
                                form.find('#password').val(params.account.password);
                            }
                            else
                                provider.setError(util.filter(errors.text()), true);
                        }
                        // if the page completely loaded
                        var balance = $('li.nav-pts:visible');
                        if (balance.length > 0 && util.filter(balance.text() !== '')) {
                            browserAPI.log("balance -> '" + balance.text() + "'");
                            clearInterval(login);
                            plugin.checkLoginErrors(params);
                        }
                        if (waitingCounter > 120) {
                            clearInterval(login);
                            provider.setError(util.filter(errors.text()), true);
                        }
                        waitingCounter++;
                    }, 500);
                }

                //loginBtn.removeClass("disabled").prop('disabled', false);
                setTimeout(function() {
                    var captcha = form.find('iframe[src *= "https://www.google.com/recaptcha/api2/anchor"]:not([src *= "size=invisible"])');
                    if (captcha && captcha.length > 0) {
                        browserAPI.log("login waiting...");
                        if (!provider.isMobile) {
                            provider.reCaptchaMessage();
                            var counter = 0;
                            var login = setInterval(function () {
                                browserAPI.log("waiting captcha... " + counter);
                                if($('#recaptcha-login').closest('.form-group.validate.is-valid').length) {
                                    browserAPI.log("login is valid, submit...");
                                    clearInterval(login);
                                    setTimeout(function() {
                                        form.find('button.submit-login').click();
                                    }, 1000);
                                    return;
                                }
                                if (counter > 120) {
                                    clearInterval(login);
                                    provider.setError(util.errorMessages.captchaErrorMessage, true);
                                    return;
                                }
                                counter++;
                            }, 1000);
                            $(document).on('click', 'button.submit-login', function () {
                                clearInterval(login);
                                setTimeout(function() {
                                    plugin.checkLoginErrors(params);
                                }, 7000);
                            });
                        }else{
                            browserAPI.log(">>> mobile");
                            provider.command('show', function(){
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
                                    }
                                    else {
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
                        provider.setTimeout(function () {
                            waiting();
                        }, 0);
                    }
                }, 3000);
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        provider.logBody("checkLoginErrors");
        var errors = $('div#errTxt');
        if (errors.length === 0)
            errors = $('div.info:contains("We are unable to complete your request.")');
        if (errors.length === 0)
            errors = $('div[class *= "alert alert-danger"]:visible');
        if (errors.length === 0 && $('p:contains("We closed your account after 12 months of inactivity."):visible').length > 0) {
            provider.setError("We closed your account after 12 months of inactivity.", true);
            return;
        }
        if (errors.length > 0 && util.trim(errors.text()) !== '') {
            var form = $('form#signinForm');
            if (form.length > 0 && /A Captcha is required to proceed/i.test(errors.text())) {
                provider.reCaptchaMessage();
                form.find('#email').val(params.account.login);
                form.find('#password').val(params.account.password);
            }
            else
                provider.setError(util.filter(errors.text()), true);
        }
        else {
            const url = plugin.rewardsPageURL;
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
        let counter = 0;
        let myTimeout = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            // if the page completely loaded
            let balance = $('li.nav-pts:visible');
            let lifetime = $('var[id *= "__lifetime"]:visible');
            if (
                balance.length > 0 && util.filter(balance.text() !== '')
                && lifetime.length > 0 && util.filter(lifetime.text() !== '')
            ) {
                browserAPI.log("balance -> '" + balance.text() + "'");
                browserAPI.log("lifetime -> '" + lifetime.text() + "'");
                clearInterval(myTimeout);
                plugin.parse(params);
            }
            if (counter > 25) {
                clearInterval(myTimeout);
                plugin.parse(params);
            }
            counter++;
        }, 500);
    },

    parse: function (params) {
        browserAPI.log("parse");
        provider.updateAccountMessage();
        browserAPI.log("[Current URL]: " + document.location.href);
        provider.logBody("parsePage");
        var data = {};
        // Balance
        var balance = $('li.nav-pts:visible');
        if (balance.length > 0) {
            browserAPI.log("Balance: " + balance.text());
            balance = util.findRegExp(util.filter(balance.text()), /([\d\.\,\s]+)/i);
            browserAPI.log("Balance: " + balance);
            data.Balance = balance;
        } else {
            browserAPI.log("Balance is not found");
            var providerError = $('div:contains("We are unable to complete your request.")');
            if (providerError.length > 0)
                provider.setError(providerError.text(), true);
        }
        // Name
        var firstName = $('a:has(span[class = "caret orange"]):visible');
        if (firstName) {
            data.Name = util.beautifulName(util.trim(firstName.text()));
            browserAPI.log("Name: " + data.Name);
        } else
            browserAPI.log("Name is not found");
        // Pending Points
        let pendingPoints = $('var[id *= "__pendingList"]:visible');
        if (pendingPoints.length > 0) {
            data.Pending = util.findRegExp(pendingPoints.text(), /([\d\.\,\s]+)Point/i);
            browserAPI.log("Pending Points: " + data.Pending);
        } else
            browserAPI.log("Pending Points are not found");
        // Lifetime Points
        let lifetimeEarned = $('var[id *= "__lifetime"]:visible');
        if (lifetimeEarned.length > 0) {
            data.LifetimeEarned = util.findRegExp(lifetimeEarned.text(), /([\d\.\,\s]+)/i);
            browserAPI.log("Lifetime Earned: " + data.LifetimeEarned);
        } else
            browserAPI.log("Lifetime Earned is not found");
        /* only in json
        // Member Since
        var memberSince = $('small:contains("Member Since")');
        if (memberSince.length > 0) {
            data.MemberSince = util.findRegExp( memberSince.text(), /Member\s*Since\s*([^<]+)/i );
            browserAPI.log("Member Since: " + data.MemberSince);
        } else
            browserAPI.log("Member Since is not found");
        */

        params.data.properties = data;
        provider.saveTemp(params.data);

        // Parsing Name
        provider.setNextStep('parseName', function(){
            document.location.href = 'https://www.mypoints.com/emp/u/editProfile.do';
        });
    },

    parseName: function (params) {
        browserAPI.log("parseName");
        var counter = 0;
        var parseName = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            // Name
            var firstName = $('#firstName');
            var lastName = $('#lastName');
            if (firstName.length > 0 && lastName.length > 0 || counter > 10) {
                clearInterval(parseName);
                if (firstName.length && lastName.length) {
                    var name = util.beautifulName(util.trim(firstName.attr('value') + ' ' + lastName.attr('value')));
                    browserAPI.log("Name: " + name);
                    params.data.properties.Name = name;
                } else
                    browserAPI.log("Name is not found");

                params.account.properties = params.data.properties;
                provider.saveProperties(params.account.properties);
                provider.complete();
            }
            counter++;
        }, 500);
    }

};
