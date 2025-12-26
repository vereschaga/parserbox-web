var plugin = {
    flightStatus: {
        url: 'https://www.mobile-int.jal.co.jp/pdaInter/MobileArrivalAndDepartureAreaSearch.do?PRM_SITE=USA&PRM_COUNTRY_CD=US&PRM_LANGUAGE=eng',
        match: /\d+/i,

        start: function () {
            browserAPI.log("start");
            var counter = 0;
            var start = setInterval(function () {
                var form = $('form[name *= "MobileArrAndDepCheckActionForm"]');
                browserAPI.log("waiting... " + start);
                if (form.length > 0) {
                    browserAPI.log("submit form");
                    clearInterval(start);
                    form.find('input[name = "airlineCode"]').val(params.flightNumber);
                    // date
                    var depDateElem = $('select[name = "depDate"] option[value*="' + $.format.date(api.getDepDate(), 'yyyyMMdd') + '"]');
                    var arrDateElem = $('select[name = "arrDate"] option[value*="' + $.format.date(api.getArrDate(), 'yyyyMMdd') + '"]');
                    if (depDateElem.length == 1 && arrDateElem.length == 1) {
                        $('select[name = "depDate"]').val(depDateElem.val());
                        $('select[name = "arrDate"]').val(arrDateElem.val());
                        api.setNextStep('finish', function () {
                            form.find('input[name = "flight"]').get(0).click();
                        });
                    }else{
                        api.errorDate();
                    }
                }
                if (counter > 10) {
                    clearInterval(start);
                    api.error("can't find form");
                }
                counter++;
            }, 500);
        },

        finish: function () {
            browserAPI.log("finish");
            var error = $('td:contains("No arrival / departure information today")');
            if (error.length > 0)
                api.error(error.text().trim());
            else
                api.complete();
        }
    }

    //autologin: {
    //
    //    url: "http://www.ar.jal.com/arl/sp/en/",
    //
    //    start: function () {
    //        browserAPI.log("start");
    //        var counter = 0;
    //        var start = setInterval(function () {
    //            browserAPI.log("waiting... " + start);
    //            if ($('form[id = "mbs-heading-form"]').length > 0 || $('input[name = "btnAccountLogout"]').length > 0) {
    //                clearInterval(start);
    //                plugin.autologin.start2();
    //            }
    //            if (counter > 10) {
    //                clearInterval(start);
    //                api.error("Can't determine state");
    //            }
    //            counter++;
    //        }, 500);
    //    },
    //
    //    start2: function () {
    //        browserAPI.log("start2");
    //        if (this.isLoggedIn()) {
    //            if (this.isSameAccount())
    //                this.finish();
    //            else
    //                this.logout();
    //        }
    //        else
    //            this.login();
    //    },
    //
    //    isLoggedIn: function () {
    //        browserAPI.log("isLoggedIn");
    //        if ($('input[name = "btnAccountLogout"]').length > 0) {
    //            browserAPI.log("LoggedIn");
    //            return true;
    //        }
    //        if ($('form[id = "mbs-heading-form"]').length > 0) {
    //            browserAPI.log('not logged in');
    //            return false;
    //        }
    //        browserAPI.log("Can't determine login state");
    //        api.error("Can't determine login state");
    //        throw "can't determine login state";
    //    },
    //
    //    login: function () {
    //        browserAPI.log("login");
    //        var counter = 0;
    //        var login = setInterval(function () {
    //            var form = $('form[id = "mbs-heading-form"]');
    //            browserAPI.log("waiting... " + login);
    //            if (form.length > 0) {
    //                api.eval("toggleLoginForm()");
    //                browserAPI.log("submitting saved credentials");
    //                form.find('input[name = "efMemberId"]').val(params.login);
    //                form.find('input[name = "efPin"]').val(params.pass);
    //
    //                clearInterval(login);
    //
    //                api.setNextStep('checkLoginErrors', function () {
    //                    form.find('input[name = "btnLoginIdent"]').click();
    //                });
    //            }
    //            if (counter > 10) {
    //                clearInterval(login);
    //                browserAPI.log("can't find login form");
    //                api.error("can't find login form");
    //            }
    //            counter++;
    //        }, 500);
    //    },
    //
    //    isSameAccount: function () {
    //        browserAPI.log("isSameAccount");
    //        return false;
    //        //return (typeof(params.properties) !== 'undefined')
    //        //    && (typeof(params.properties.Number) !== 'undefined')
    //        //    && ($('span:contains("' + params.properties.Number + '")').length > 0);
    //    },
    //
    //    checkLoginErrors: function () {
    //        browserAPI.log("checkLoginErrors");
    //        var counter = 0;
    //        var checkLoginErrors = setInterval(function () {
    //            browserAPI.log("waiting... " + checkLoginErrors);
    //            var error = $('div.msg');
    //            if (error.length > 0) {
    //                clearInterval(checkLoginErrors);
    //                api.error(error.text().trim());
    //            }
    //            if (counter > 3) {
    //                clearInterval(checkLoginErrors);
    //                plugin.autologin.finish();
    //            }
    //            counter++;
    //        }, 500);
    //    },
    //
    //    logout: function () {
    //        browserAPI.log("logout");
    //        api.setNextStep('start', function () {
    //            $('input[name = "btnAccountLogout"]').get(0).click();
    //        });
    //    },
    //
    //    finish: function () {
    //        browserAPI.log("finish");
    //        api.complete();
    //    }
    //}
};