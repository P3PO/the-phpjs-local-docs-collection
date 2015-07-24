var http = require("http");
var url = require("url");
var Q = require("q");

var httpGet = function (opts) {
    var deferred = Q.defer();
    try {
        http.get(opts, deferred.resolve).on("error", deferred.reject);
    } catch (error) {deferred.reject(error);}
    return deferred.promise;
};

var loadBody = function (res) {
    var deferred = Q.defer();
    var body = "";
    res.on("data", function (chunk) {
        body += chunk;
    });
    res.on("end", function () {
        deferred.resolve(body);
    });
    res.on("close", deferred.reject);
    return deferred.promise;
};


var text = httpGet(url.parse("http://example.biz")).then(function (res) {
    return httpGet(url.parse(res.headers["location"]));
}).then(loadBody).fail(function (error) {
    return "Not Found";
});

var texts = Q.all([
    text,
    text.post("toUpperCase"),
    text.post("toLowerCase"),
]);

texts.spread(function (normal, upper, lower) {
    console.log(normal);
    console.log(upper);
    console.log(lower);
});

