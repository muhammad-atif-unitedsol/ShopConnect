define(["jquery", "owlcarousel"], function ($) {
    "use strict";

    $(document).ready(function () {
        $("#banner_carousel").owlCarousel({
            loop: true,
            margin: 10,
            nav: true,
            dots: true,
            autoplay: true,
            autoplayTimeout: 4000,
            autoplayHoverPause: true,
            items: 1,
            navText: [
                '<span class="prev">&#10094;</span>',
                '<span class="next">&#10095;</span>',
            ],
        });
    });
});
