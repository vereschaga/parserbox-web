var plugin = {
    flightStatus: {
        url: 'http://www.westjet.com/guest/en/flights/flight-status.shtml?mrd=0',
        match: /^(?:WS)?\s*\d+/i,

        start: function () {
            var input = $('#flightnumber');
            var form = $('#flightstatus-byflight');
            if (input.length == 1 && form.length == 1) {
                input.val(params.flightNumber.replace(/WS/gi, '').trim());

                var dateInput = $('#flightstatus-byflight select[name="date"]');

                var tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                var dates = [];
                dates['1'] = tomorrow.getTime();
                dates['0'] = tomorrow.setDate(tomorrow.getDate() - 1);
                dates['-1'] = tomorrow.setDate(tomorrow.getDate() - 1);
                var depDate = api.getDepDate().getDate();
                var depDateName = '';
                for (var key in dates) {
                    if (dates.hasOwnProperty(key) && depDate == (new Date(dates[key])).getDate()) {
                        depDateName = key;
                    }
                }
                if (depDateName != '') {
                    dateInput.val(depDateName);
                    api.setNextStep('finish', function () {
                        form.submit();
                    });
                } else {
                    api.errorDate();
                }

            }
        },

        finish: function () {
            if ($('.flight-number').length > 0)
                api.complete();
            else {
                api.error($('span:contains("unable to find any flight")').text().trim());
            }
        }
    },

    autologin: {
        url: "https://www.mywestjet.com",

        start: function () {
            browserAPI.log("start");
            var counter = 0;
            var start = setInterval(function () {
                browserAPI.log("waiting... " + start);
                if ($('form[id = "signInForm"]').length > 0 || $('span#guestCartWestJetId').length > 0) {
                    clearInterval(start);
                    plugin.autologin.start2();
                }
                if (counter > 10) {
                    clearInterval(start);
                    api.error("Can't determine state");
                }
                counter++;
            }, 500);
        },

        start2: function () {
            browserAPI.log("start2");
            if (this.isLoggedIn()) {
                if (this.isSameAccount())
                    this.finish();
                else
                    this.logout();
            }
            else
                this.login();
        },

        isLoggedIn: function () {
            browserAPI.log("isLoggedIn");
            if ($('span#guestCartWestJetId').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
            if ($('form[id = "signInForm"]').length > 0) {
                browserAPI.log('not logged in');
                return false;
            }
            browserAPI.log("Can't determine login state");
            api.error("Can't determine login state");
            throw "can't determine login state";
        },

        login: function () {
            browserAPI.log("login");
            var counter = 0;
            var login = setInterval(function () {
                var form = $('form[id = "signInForm"]');
                browserAPI.log("waiting... " + login);
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    form.find('input[name = "username"]').val(params.login);
                    form.find('input[name = "password"]').val(params.pass);

                    clearInterval(login);

                    api.setNextStep('checkLoginErrors', function () {
                        form.find('a#signInSubmitLink').get(0).click();
                    });

                    setTimeout(function() {
                        plugin.autologin.checkLoginErrors();
                    }, 3000);
                }
                if (counter > 10) {
                    clearInterval(login);
                    browserAPI.log("can't find login form");
                    api.error("can't find login form");
                }
                counter++;
            }, 500);
        },

        isSameAccount: function () {
            browserAPI.log("isSameAccount");
            return (typeof(params.properties) !== 'undefined')
                && (typeof(params.properties.AccountNumber) !== 'undefined')
                && ($('span#guestCartWestJetId:contains("' + params.properties.AccountNumber + '")').length > 0);
        },

        checkLoginErrors: function () {
            browserAPI.log("checkLoginErrors");
            var error = $('div.error:visible');
            if (error.length == 0)
                error = $('div.error-login:visible');
            if (error.length > 0)
                api.error(error.text().trim());
            else
                plugin.autologin.finish();
        },

        logout: function () {
            browserAPI.log("logout");
            api.setNextStep('start', function () {
                $('a:contains("Sign out")').get(0).click();
            });
        },

        finish: function () {
            browserAPI.log("finish");
            api.complete();
        }
    }
};