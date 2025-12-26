var plugin = {
    flightStatus: {
        url: 'http://www.qantas.com.au/mobile-travel/airlines/flight-status/global/en#flight',
        match: /^(?:QF)?\d+/i,

        start: function(){
            var input = $('#flightNo');
            var button = $('#submitFS');
            if (input.length == 1 && button.length == 1) {
                input.val(params.flightNumber.replace(/QF/gi, ''));
                // open calendar
                $('#intDepPopupCalendarIcon').click();

                var counter = 0;
                var day = setInterval(function () {
                    // find by day
                    var date = $('#returnPopup[style *= "block"]').find('td[onclick *= "'+ $.format.date(api.getDepDate(), 'dd') + '"]');
                    browserAPI.log("waiting... " + day);
                    if (date.length > 0) {
                        browserAPI.log("select date");
                        // select date
                        date.click();
                        clearInterval(day);
                        setTimeout(function () {
                            // click "select"
                            $('#h-select').get(0).click();
                        }, 0)
                    }
                    if (counter > 10) {
                        clearInterval(day);
                        api.errorDate();
                    }
                    counter++;
                }, 500);
                // submit form
                counter = 0;
                var form = setInterval(function () {
                    var date = $('#returnPopup[style *= "none"]');
                    browserAPI.log("waiting... " + day);
                    if (date.length > 0) {
                        browserAPI.log("submit form");
                        clearInterval(form);
                        api.setNextStep('finish', function () {
                            button.click();
                        });
                    }
                    if (counter > 10) {
                        clearInterval(form);
                        api.error("can't find form");
                    }
                    counter++;
                }, 500);
            }
        },

        finish: function(){
            setTimeout(function () {
                var results = $('#flight-status-results');
                if(results.length > 0){
                    api.complete();
                } else {
                    api.error($('#important').text().trim());
                }
            }, 2000);
        }
    },

    autologin : {
        url : "http://www.qantas.com/us/en.html",

        start : function() {
            browserAPI.log("start");
            var counter = 0;
            var start   = setInterval(function() {
                browserAPI.log("waiting... " + counter);
                if ($('form[name="LSLLoginForm"],a#logout,span:contains("Log in"):visible').length > 0
                    || $('button[name = "logoutButton"]').length > 0) {
                    clearInterval(start);
                    plugin.autologin.start2();
                }
                if (counter > 10) {
                    clearInterval(start);
                    api.setError(util.errorMessages.unknownLoginState);
                }
                counter++;
            }, 500);
        },

        start2 : function() {
            browserAPI.log("start2");
            setTimeout(function() {
                if (plugin.autologin.isLoggedIn()) {
                    if (plugin.autologin.isSameAccount())
                        plugin.autologin.finish();
                    else
                        plugin.autologin.logout();
                } else
                    plugin.autologin.login();
            }, 3000);
        },

        isLoggedIn : function() {
            browserAPI.log("isLoggedIn");
            if ($('button[name = "logoutButton"]').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
            if ($('form[name="LSLLoginForm"],span:contains("Log in"):visible').length) {
                browserAPI.log('not logged in');
                return false;
            }
            api.setError(util.errorMessages.unknownLoginState);
        },

        login : function() {
            browserAPI.log("login");
            $('.login-ribbon > button', '#header').click();
            setTimeout(function() {
                var counter = 0;
                var login   = setInterval(function() {
                    var form = $('form[name = "LSLLoginForm"]');
                    browserAPI.log("waiting... " + counter);
                    if (form.length > 0) {
                        clearInterval(login);
                        browserAPI.log("submitting saved credentials");

                        try {
                            $('input[name="memberId"]').val(params.login);
                            util.sendEvent(form.find('input[name="memberId"]').get(0), 'input');
                        } catch (e) {
                        }
                        try {
                            $('input[name="lastName"]').val(params.login2);
                            util.sendEvent(form.find('input[name="lastName"]').get(0), 'input');
                        } catch (e) {
                        }
                        try {
                            $('input[name="memberPin"]').val(params.pass);
                            util.sendEvent(form.find('input[name="memberPin"]').get(0), 'input');
                        } catch (e) {
                        }

                        api.setNextStep('checkLoginErrors', function() {
                            form.find('button[type="submit"]').trigger('click');
                        });
                    }
                    if (counter > 10) {
                        clearInterval(login);
                        api.setError(util.errorMessages.loginFormNotFound);
                    }
                    counter++;
                }, 500);
            }, 2000);
        },

        isSameAccount : function() {
            browserAPI.log("isSameAccount");
            var number = util.findRegExp($('div.ql-login-member-details-body strong').text(), /\((\d+)\)/ig);
            browserAPI.log("number: " + number);
            return ((typeof(params.properties) != 'undefined')
                && (typeof(params.properties.Number) != 'undefined')
                && (params.properties.Number != '')
                && (number == params.properties.Number));
        },

        checkLoginErrors : function() {
            browserAPI.log("checkLoginErrors");
            var counter = 0;
            var checkLoginErrors = setInterval(function() {
                browserAPI.log("waiting... " + counter);
                var error = $('#errormsgs');
                if (error.length > 0) {
                    clearInterval(checkLoginErrors);
                    api.error(error.text().trim());
                }
                if (counter > 5) {
                    clearInterval(checkLoginErrors);
                    plugin.autologin.finish();
                }
                counter++;
            }, 500);
        },

        logout : function() {
            browserAPI.log("logout");
            api.setNextStep('start', function() {
                $('button[name = "logoutButton"]').click();
                setTimeout(function () {
                    document.location.href = plugin.autologin.url;
                }, 2000)
            });
        },

        finish : function() {
            browserAPI.log("finish");
            api.complete();
        }
    }
};
