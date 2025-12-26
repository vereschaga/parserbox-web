var plugin = {
    flightStatus: {
        url: "http://www.klm.com/travel/us_en/prepare_for_travel/up_to_date/arrivals_departures/index.htm",
        match: /^(?:KL|AF|DL)?\d+/i,

        start: function () {
            browserAPI.log("start");
            var counter = 0;
            var counter2 = 0;
            var start = setInterval(function () {
                var form = $('iframe#appFrame').contents().find("form[name = 'search']");
                if (form.length == 1) {
                    form.find("a.flightnr").get(0).click();
                    clearInterval(start);
                    var flightnr = setInterval(function () {
                        var flightnrField = form.find('div.search-flightnr[style *= "display: block"]');
                        browserAPI.log("waiting... " + flightnr);
                        if (flightnrField.length > 0) {
                            browserAPI.log("submitting");
                            form.find('input[name = "flightnr"]').val(params.flightNumber).blur();
                            var btn = form.find('a[id = "searchbutton"]');
                            btn.removeClass('g-btn-disabled');

                            clearInterval(flightnr);

                            btn.get(0).click();
                            plugin.flightStatus.finish();
                        }
                        if (counter2 > 10) {
                            clearInterval(flightnr);
                            browserAPI.log("can't find field in search form");
                            provider.setError(util.errorMessages.loginFormNotFound);
                        }
                        counter2++;
                    }, 500);
                }
                if (counter > 10) {
                    clearInterval(start);
                    browserAPI.log("can't find search form");
                    provider.setError(util.errorMessages.loginFormNotFound);
                }
            }, 1500);
        },

        finish: function () {
            browserAPI.log("finish");
            var errors = $('iframe#appFrame').contents().find('p.noresults');
            if (errors.length > 0)
                provider.setError(errors.text().trim());
            else
                api.complete();
        }
    },

    autologin: {
        clearCache: true,
        url: "https://www.klm.com/travel/us_en/apps/myaccount/myahome.htm",

        start: function () {
            browserAPI.log("start");
            var counter = 0;
            var start = setInterval(function () {
                var btn = $('a.js-login-link:contains("Log in"):eq(0)');
                if ($('form#signInForm').length > 0 || $('a:contains("Sign out")').length > 0 || btn.length > 0) {
                    if (btn.length > 0) {
                        /*api.setNextStep('start2', function(){
                            btn.get(0).click();
                        });*/
                    }
                    clearInterval(start);
                    plugin.autologin.start2();
                    return;
                }
                if (counter > 10) {
                    clearInterval(start);
                    provider.setError(util.errorMessages.unknownLoginState);
                    return;
                }
                counter++;
            }, 1500);
        },

        start2: function () {
            browserAPI.log("start2");
            if (this.isLoggedIn())
                if (this.isSameAccount())
                    this.finish();
                else
                    this.logout();
            else
                this.login();
        },

        isLoggedIn: function () {
            browserAPI.log("isLoggedIn");
            if ($('a.js-login-link:contains("Log in")').length > 0) {
                browserAPI.log('not logged in');
                return false;
            }
            if ($('h1.bwc-o-body-variant').text() !== '') {
                browserAPI.log("LoggedIn");
                return true;
            }
            provider.setError(util.errorMessages.unknownLoginState);
            throw "can't determine login state";
        },

        isSameAccount: function () {
            browserAPI.log("isSameAccount");
            var number = $('.bw-fb-membership-card__image--front img');
            if (number.length > 0)
                number = util.findRegExp(number.attr('alt'), /Flying Blue number\s+(\d+)/);
            browserAPI.log("number: " + number);
            return ((typeof(account.properties) !== 'undefined')
            && (typeof(account.properties.Number) !== 'undefined')
            && (account.properties.Number != '')
            && (number == account.properties.Number));
        },

        login: function () {
            browserAPI.log("login");
            var counter = 0;
            var login = setInterval(function () {
                var form = $('form#signInForm');
                browserAPI.log("waiting... " + login);
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    form.find('input[name = "emailorfbnumber"]').val(params.login);
                    util.setInputValue(form.find('input[name = "passwordpincode"]'), params.pass);

                    clearInterval(login);
                    provider.setNextStep('checkLoginErrors', function () {
                        setTimeout(function () {
                            if ($('iframe[src*="/recaptcha/"]:visible').length) {
                                form.find('button[type = "submit"]:contains("Log in")').click();
                                //api.command('show', function () {
                                //    api.reCaptchaMessage();
                                //});
                            } else
                                form.find('button[type = "submit"]:contains("Log in")').click();
                        }, 1000);
                    });
                } else if (counter > 20) {
                    clearInterval(login);
                    provider.setError(util.errorMessages.loginFormNotFound);
                }
                counter++;
            }, 1500);
        },

        logout: function () {
            browserAPI.log("Logout");
            provider.setNextStep('toLoginPage', function () {
                util.waitFor({
                    selector: '.bwc-logo-header__initials-button',
                    success: function (elem) {
                        $('.bwc-logo-header__initials-button').get(0).click();
                        setTimeout(function () {
                            $('a[href*="/en/profile/logout"]').get(0).click();
                        }, 500);
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

        toLoginPage: function (){
            browserAPI.log("toLoginPage");
            provider.setNextStep('login', function () {
                document.location.href = plugin.autologin.url;
            });
        },

        checkLoginErrors: function () {
            browserAPI.log("checkLoginErrors");
            var error = $('div.onError:visible, .g-notification-error:visible');
            var response = $('body').text();
            if (-1 != response.indexOf('accountType') && -1 != response.indexOf('firstName')) {
                document.location.href = plugin.autologin.url;
                return this.finish();
            }
            if (error.length == 0)
                error = $('div#errorList > span');
            if (error.length > 0 && error.text() != "")
                provider.setError(error.text().trim());
            else
                this.finish();
        },

        finish: function () {
            browserAPI.log("finish");
            provider.complete();
        }
    }
};
