var config = {
    paths: {
        jquery: "jquery", // This is the key change!
        owlcarousel: "js/owl.carousel.min",
    },
    shim: {
        owlcarousel: {
            deps: ["jquery"],
        },
    },
};
