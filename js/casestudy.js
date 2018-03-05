(function($) {
    $(document).ready(function() {

        // Quiz Result Share
        $('.share-your-result-btn').hover(function(){
            $('.share-your-result-btn').hide();
            //$('.social-icons').show();
        });
        $('.social-icons').mouseout(function(){
            //$('.social-icons').hide();
            $('.share-your-result-btn').show();
        });

        if ($(".custom-casestudy-page .casestudy-left-navigation-exist").length > 0) {
            //console.log( ($(".webform-client-form").outerHeight() - $(".casestudy-left-nav-menu-header").height()));
            if ($(".casestudy-left-navigation-menu").height() < $(".webform-client-form").height()) {
                $(".casestudy-left-nav-menu-body ul").css("width", "216px");
                $(".casestudy-left-nav-menu-body").css("overflow-y", "hidden");
            }

            if ($(".webform-client-form").outerHeight() > 300) {
                $(".casestudy-left-nav-menu-body").height($(".webform-client-form").outerHeight() - 30);

            } else {

                $(".webform-client-form").height(300);
                $(".casestudy-left-nav-menu-body").height(270);
                $("#casestudy-tab-intro #edit-actions").css('top', $("#casestudy-tab-intro").outerHeight() + 86 +'px');

            }
        }

        $("#legend").popover({
            html: true,
            placement: 'right',
            trigger: 'hover',
            title: '',
            container: 'body',
            content: function() {
                return $(".cs-left-nav-menu-tooltip").html()
            }
        });


        //if( $('.casestudy-left-nav-menu-body').height() > $('.casestudy-webform-content').height() ){
        //   $('.webform-client-form').height($('.casestudy-left-nav-menu-body').height());
        //}
        //  $('.casestudy-left-nav-menu-body').height($('.casestudy-webform-content').height() - 65);



//            $('#leg').popover({
//                html: true,
//                content: function(){return $(".cs-left-nav-menu-tooltip").html();}
//            });

        if ($("#casestudy-accordion .gallery-slides .overlay-inner").length > 0) {
            $("#casestudy-accordion .gallery-slides .overlay-inner").addClass('hidden-phone');
        }


        if ($(".custom-casestudy-page .webform-client-form .casestudy-question-html").length > 0) {
            $(".custom-casestudy-page .webform-client-form .form-item.webform-component.webform-component-radios").addClass('whitebg');
        }



        // putting a class in webform div

        // show welcome back message
        if ($(".custom-casestudy-page .top-msg-box").length > 0) {
            if ($(".custom-casestudy-page #casestudy-tabs .top-msg-box").length > 0) {
                $('body').prepend($(".custom-casestudy-page #casestudy-tabs .top-msg-box"));
            }

            if ($("#casestudy-tab-intro .top-msg-box").length > 0) {
                $('body').prepend($("#casestudy-tab-intro .top-msg-box"));
            }



            $(".custom-casestudy-page .top-msg-box").show();
            $(".custom-casestudy-page #casestudy-accordion .top-msg-box").hide();
        }






        $("#webform-component-intro-page .gallery-slides").css("width", "78%");
        $("#webform-component-intro-page .gallery-thumbs").css("width", "70%");

        $("#casestudy-tabs").tabs({
            activate: function(event, ui) {
                //console.log(getGroundedRow(ui.index))

                var grounded_row = getGroundedRow(ui.newTab);
                groundedTabs(grounded_row);
                if (ui.newTab.attr('class').indexOf('castudytabs-intro')> -1) {
                    $("#casestudy-tabs .castudytabs-intro").removeClass('extra-color');
                    $("#casestudy-tabs .castudytabs-intro").find("a").text(Drupal.settings.case_study_tab_name);
                    $("#casestudy-tabs .castudytabs-intro").find("a").css("line-height", "35px");

                } else {
                    $("#casestudy-tabs .castudytabs-intro").addClass('extra-color');
                    $("#casestudy-tabs .castudytabs-intro").find("a").text("Return to " + Drupal.settings.case_study_tab_name);
//                    $("#casestudy-tabs .castudytabs-intro").find("a").css("line-height", "17px");
                    //$("#casestudy-tabs .castudytabs-intro").css("max-width","300px");
//                    if ($("#casestudy-tabs .castudytabs-intro").width() <= 182) {
//                        $("#casestudy-tabs .castudytabs-intro").find("a").css("line-height", "17px");
//                    } else {
//                        $("#casestudy-tabs .castudytabs-intro").find("a").css("line-height", "35px");
//                    }

                    jQuery('#casestudy-tabs ul li').each(function(i){
                        if($(this).find('a').outerHeight() > $(this).outerHeight())
                            $(this).find("a").css("line-height", "17px");
                    });
                    //console.log($("#casestudy-tabs .castudytabs-intro").width());
                }

            }
        });


        $("#casestudy-accordion").accordion({
            collapsible: true,
            autoHeight: true,
            clearStyle: true,
            active: false
        });
        $("#casestudy-accordion").accordion('option', 'active', 0);
        $('#casestudy-accordion .case-study-slideshow *').unbind();


        casetusy_mobile_slides_height();

        checkaccordionPN();

        if ($("#casestudy-accordion .gallery-frame ul").length > 0) {
            $("#casestudy-accordion .gallery-frame ul").attr("id", "cycle-mobile-slideshow");

            if ($("#casestudy-accordion .gallery-frame ul li").length > 1) {
                $('#cycle-mobile-slideshow').cycle({
                    fx: 'fade',
                    speed: 'fast',
                    timeout: 0,
                    next: '.casestudy-custom-next',
                    prev: '.casestudy-custom-prev',
                    slideResize: true,
                    containerResize: false,
                    width: '100%',
                    fit: 1
                });
            }

            $("#casestudy-accordion .gallery-frame ul").css('width', '100%');
        }


        $(window).resize(function(e) {
            updateUI();

        });

        rearrangeJqueryTabs();
        groundedTabs(0);
        fixingheightProblem();

    });


    function fixingheightProblem() {
        $("#casestudy-tabs > ul li a").each(function() {
            if ($(this).height() <= 18 && $(this).closest("li").hasClass("multiple-word")) {
                $(this).css("line-height", "35px");
            }
        });
    }

    function updateUI() {
        if ($("#casestudy-tabs").is(":visible")) {
            if ($("#casestudy-tabs .case-study-slideshow").length > 0) {
                Drupal.galleryformatter.prepare($("#casestudy-tabs .case-study-slideshow"));
                $("#casestudy-tabs .gallery-slides").css("height", "407px");

                var counter = 0;
                $("#casestudy-tabs .gallery-slides .prev-slide").each(function() {
                    if (counter > 0)
                        $(this).remove();
                    counter = counter + 1;
                })

                var counter = 0;
                $("#casestudy-tabs .gallery-slides .next-slide").each(function() {
                    if (counter > 0)
                        $(this).remove();
                    counter = counter + 1;
                })
            }

        } else {
            casetusy_mobile_slides_height();
            checkaccordionPN();
        }
    }

    function casetusy_mobile_slides_height() {
        if ($("#casestudy-accordion").is(":visible")) {
            var height = 0;
            $("#casestudy-accordion .gallery-frame ul li").each(function() {
                if ($(this).height() > height)
                    height = $(this).height();
            });

            if (height > 0)
                $("#casestudy-accordion .gallery-slides").height(height);
        }
    }


    function checkaccordionPN() {

        if ($("#casestudy-accordion .gallery-frame ul li").length > 1) {
            if ($("#casestudy-accordion .gallery-slides .prev-slide").length <= 0) {
                $("#casestudy-accordion .gallery-slides").append('<a title="Previous image" class="prev-slide slide-button casestudy-custom-prev">&lt;</a>');
            }

            if ($("#casestudy-accordion .gallery-slides .next-slide").length <= 0) {
                $("#casestudy-accordion .gallery-slides").append('<a title="Next image" class="next-slide slide-button casestudy-custom-next">&gt;</a>');
            }
        }
    }

    function rearrangeJqueryTabs() {
        // get total width
        var total_width = parseInt($("#casestudy-tabs").width());
        // calculated total width
        var cal_total_width = 0;

        // items
        var items = [];

        // row counter
        var counter = 0;

        // currently occupied with
        var occup_total_width = [];

        // item count
        var item_counter = 0;

        $("#casestudy-tabs > ul li").each(function() {

            var current_item_width = parseInt($(this).width());
            if ($("html").hasClass("ie8")) {
                current_item_width = current_item_width + 4;
            }
            // if total width less than the calculated width
            if (total_width > cal_total_width + current_item_width) {
                // add the row number class
                $(this).addClass('casestudy-row-' + counter);
                // calculated width
                cal_total_width = cal_total_width + current_item_width;
                items.push({
                    index: $(this).index(),
                    column: counter,
                    width: current_item_width
                });
                item_counter = item_counter + 1;
            } else {
                $("#casestudy-tabs > ul li.casestudy-row-" + counter + ":last").css("margin-right", "0px");
                occup_total_width.push({
                    width: cal_total_width,
                    total_item: item_counter
                });
                item_counter = 0;
                counter = counter + 1;
                $(this).addClass('casestudy-row-' + counter);
                cal_total_width = 0;
                cal_total_width = cal_total_width + current_item_width;
                items.push({
                    index: $(this).index(),
                    column: counter,
                    width: current_item_width
                });
                item_counter = item_counter + 1;

            }
            if ($(this).index() + 1 == $("#casestudy-tabs > ul li").length) {
                $("#casestudy-tabs > ul li.casestudy-row-" + counter + ":last").css("margin-right", "0px");
                occup_total_width.push({
                    width: cal_total_width,
                    total_item: item_counter
                });
            }


        });

        $.each(items, function(key, value) {
            var total_increase_width = total_width - occup_total_width[value.column].width - (4 * occup_total_width[value.column].total_item) + 4
            var this_item = (total_increase_width / occup_total_width[value.column].width) * value.width;
            $("#casestudy-tabs > ul li:eq(" + key + ")").css("max-width", value.width + this_item).css("width", value.width + this_item);

            $("#casestudy-tabs > ul li:eq(" + key + ")").find("a").css('width', value.width + this_item - 38);


        });
    }


    function groundedTabs(grounded_row) {
        /*var maximum_row = 5;
         var grounded_row = 0;
         for( i= 0; i < maximum_row; i++){
         if( $("#casestudy-tabs ul li.ui-tabs-selected.ui-state-active").hasClass("casestudy-row-"+i)){
         grounded_row = i;
         break;
         }
         
         } */
        $("#casestudy-tabs > ul").append($("#casestudy-tabs > ul li.casestudy-row-" + grounded_row));
    }


    function getGroundedRow(index) {
        var maximum_row = 5;
        for (i = 0; i < maximum_row; i++) {
            if ($(index).closest("li").hasClass("casestudy-row-" + i)) {
                return i;
            }

        }
    }

    if(jQuery('.form-step-1').length > 0){
        jQuery('.support-blocks').show();
    } else {
        jQuery('.support-blocks').hide();

    }


})(jQuery);

/*
 jQuery(window).load(function(){
 (function($) {
 if($("#casestudy-tabs .ui-tabs-nav").length > 0){
 if(casestudy_increase_px > 0){
 casestudy_increase_px = 174 + casestudy_increase_px;
 $(".custom-casestudy-page .ui-tabs .ui-tabs-nav li").css('max-width', casestudy_increase_px );
 }
 }
 })(jQuery);
 }); */
