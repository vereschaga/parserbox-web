var params = {};
var plugin = {
    clearCache: true,
    flightStatus: {
        fakeflightStatus: true,
        url: 'https://www.kqzyfj.com/click-8125108-13787272?sid=AWREFCODE',
        match: /.*/i,
        baseUrl: 'https://www.kayak.com/in?a=awardwallet&url=/flights/',

        start: function () {
            var codes = '';
            var formatDDMMYYYY = function (d) {
                var date = new Date(d), month = date.getMonth() + 1;
                return [date.getFullYear(), month <= 9 ? '0' + month : month, date.getDate() <= 9 ? '0' + date.getDate() : date.getDate()].join('-');
            };
            api.setNextStep('finish', function () {
                if (typeof(params.depCode) !== 'undefined') {
                    codes = [params.depCode, params.arrCode].join('-');
                    //one way http://www.kayak.com/flights/BOS-PEE/2015-05-18
                    document.location.href = plugin.flightStatus.baseUrl + codes + '/' + formatDDMMYYYY(api.getDepDate());
                } else if (typeof(params.Trip) !== 'undefined') {
                    codes = [params.Trip[0], params.Trip[1]].join('-');
                    if (3 == params.Trip.length) {
                        //round trip http://www.kayak.com/flights/BOS-PEE/2015-05-18/2015-05-19
                        document.location.href = plugin.flightStatus.baseUrl + codes + '/' + formatDDMMYYYY(params.Dates[0] * 1000) + '/' + formatDDMMYYYY(params.Dates[1] * 1000);
                    } else {
                        //one-way
                        document.location.href = plugin.flightStatus.baseUrl + codes + '/' + formatDDMMYYYY(params.Dates * 1000);
                    }
                }
            });
        },
        finish: function () {
            api.complete();
        }
    }
};
