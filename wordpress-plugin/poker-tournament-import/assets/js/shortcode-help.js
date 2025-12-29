/**
 * Shortcode Help Admin JavaScript
 *
 * @package Poker Tournament Import
 */

jQuery(document).ready(function($) {
    /**
     * Smooth scroll navigation
     */
    $(".help-nav a").click(function(e) {
        e.preventDefault();
        var target = $(this).attr("href");

        $(".help-nav a").removeClass("nav-active");
        $(this).addClass("nav-active");

        $("html, body").animate({
            scrollTop: $(target).offset().top - 100
        }, 500);
    });

    /**
     * Update active nav on scroll
     */
    $(window).scroll(function() {
        var scrollTop = $(window).scrollTop() + 150;

        $(".help-section").each(function() {
            var sectionTop = $(this).offset().top;
            var sectionBottom = sectionTop + $(this).outerHeight();

            if (scrollTop >= sectionTop && scrollTop < sectionBottom) {
                var sectionId = "#" + $(this).attr("id");
                $(".help-nav a").removeClass("nav-active");
                $(".help-nav a[href=\"" + sectionId + "\"]").addClass("nav-active");
            }
        });
    });
});
