/**
 * Javascript for organizeSurvey.js
 * @author Denis Chenu <https://sondages.pro
 * @license magnet:?xt=urn:btih:c80d50af7d3db9be66a4d0a86db0286e4fd33292&dn=bsd-3-clause.txt BSD 3 Clause
 * @version 0.6.4
 */
var organizeSurvey = {
    init : function (options) {
        this.setOrderOnGroups();
        this.setOrderOnQuestions();
        this.sortableOnGroups();
        this.sortableOnQuestions();
        this.setTooltips();
        this.setActionAddrandomGroup();
        this.setActionSetCountQuestion();
        this.setCollapseExpandActionButtons();
    },
    /* Set the tooltips via sr-only */
    setTooltips : function() {
        $('[data-sr-tooltip]').tooltip({
            html : true,
            title : function() {
                return $(this).find(".sr-only").first().html();
            }
        });
    },
    /* Set sortableJS on question groups/pages */
    sortableOnGroups: function() {
        $(".organizer-group-list").each(function(){
            var sortable = Sortable.create($(this).get(0), {
                handle: '.organizer-handle',
                ghostClass: 'panel-warning',
                onSort: function (evt) {
                    organizeSurvey.setOrderOnGroups();
                },
            });
        });
    },
    /* Set sortableJS on questions and random questions */
    sortableOnQuestions: function() {
        $(".organizer-group-list .organizer-question-list").each(function(){
            organizeSurvey.sortableOnQuestion($(this).get(0));
        });
    },
    /* Set sotbale question on a specific element */
    sortableOnQuestion: function(element) {
        new Sortable.create(element, {
            group: 'questions',
            handle: '.organizer-handle',
            ghostClass: 'list-group-item-warning',
            onMove : function (evt) {
                if($(evt.dragged).data("randomgroup-name") && $(evt.to).data("randomgroup-id")) {
                    return false;
                }
                if($(evt.from).data("randomgroup-id") && $(evt.to).data("randomgroup-id")) {
                    if($(evt.from).data("randomgroup-id") == $(evt.to).data("randomgroup-id")) {
                        // can not move to same randomgroup
                        return false;
                    }
                    // Disable currently
                    return false;
                }
                if($(evt.item).data("randomgroup-name") && $(evt.to).data("randomgroup-name")) {
                    // Disable random group inside random group
                    return false;
                }
            },
            onEnd: function (evt) {
                if($(evt.from).data("randomgroup-id") && $(evt.to).data("randomgroup-id")) {
                    // Broken currently
                    var baseItem = $("#question-item-"+$(evt.item).data("question-id"));
                    var fromItem = $(evt.from).closest(".list-item");
                    var oldIndex = $(fromItem).closest("ol").find(".list-ite").index();
                    organizeSurvey.removeQuestionFromRandom(evt.item,$(evt.from).data("randomgroup-id"));
                    organizeSurvey.addQuestionToRandom($(baseItem).get(0),$(evt.to).data("randomgroup-id"),$(fromItem).get(0),oldIndex);
                    //~ organizeSurvey.setOrderOnQuestions();
                    return false;
                }
                if($(evt.from).data("randomgroup-id")) {
                    organizeSurvey.removeQuestionFromRandom(evt.item,$(evt.from).data("randomgroup-id"));
                }
                if($(evt.to).data("randomgroup-id")) {
                    organizeSurvey.addQuestionToRandom(evt.item,$(evt.to).data("randomgroup-id"),evt.from,evt.oldIndex);
                }
                organizeSurvey.setOrderOnQuestions();
            },
        });
    },
    /* Order on question, with fixed part for random groups */
    setOrderOnQuestions :  function() {
        $(".organizer-group-list .organizer-question-list").each(function() {
            var gid = $(this).data("group-id");
            var currentOrder = 0;
            $(this).children(".list-item").each(function() {
                $(this).find("[data-type='question_gid']").val(gid);
                $(this).find("[data-type='question_order']").val(currentOrder);
                if($(this).attr("data-related-groupid")) {
                    var questionInRandom =$("#"+$(this).attr("data-related-groupid")).find(".organizer-question:not(.order-done)").first();
                    $(questionInRandom).find("[data-type='question_gid']").val(gid);
                    $(questionInRandom).find("[data-type='question_order']").val(currentOrder);
                    $(questionInRandom).addClass("order-done");
                }
                currentOrder++;
            });
        });
        $(".organizer-question").removeClass("order-done");
        organizeSurvey.fixSecondRandomGroup();
    },
    fixSecondRandomGroup : function () {
        var randomGroups = [];
        $(".organizer-random-group").each(function(){
            var currentGroup = $(this).data("randomgroup-name");
            var currentGroupId = $(this).data("related-groupid");
            var countQuestion = $("#list-question-inrandom-"+currentGroupId+" > .list-item").length;
             $("#organizer-random-group-count-question-inrandom-"+currentGroupId).find(".count-question-in-random-group").text(countQuestion);
            if(randomGroups.indexOf(currentGroup) < 0) {
                randomGroups.push(currentGroup);
                $("#button-question-inrandom-"+currentGroupId).appendTo($(this));
                $("#organizer-random-group-count-question-inrandom-"+currentGroupId).appendTo($(this));
                $("#list-question-inrandom-"+currentGroupId).appendTo($(this));
                $(this).removeClass("random-group-next");
            } else {
                $(this).addClass("random-group-next");
                //~ // $(this).find(".btn-collapse-random-group").prop("aria-expanded",false);
                //~ // $(this).find(".organizer-random-question-list").addClass("collapse");
            }
        });
        organizeSurvey.hideSecondRandomGroupByGroup();
    },
    /* Hide random group in same group */
    hideSecondRandomGroupByGroup : function () {
        $(".show-question-in-random-group").each(function() {
            var groupid = $(this).data("count-groupid");

            $(".organizer-group").each(function() {
                $(this).find("[data-related-groupid='"+groupid+"']").slice(1).addClass("hidden");
            });

            var max = parseInt($(this).text());
            if(!max) {
                return;
            }
            $(".organizer-group-list").find("[data-related-groupid='"+groupid+"']").slice(max).addClass("hidden-random-group");
        });
    },
    /* Simple order on question page/group */
    setOrderOnGroups :  function() {
        var currentOrder = 0;
        $("[data-type='group_order']").each(function() {
            $(this).val(currentOrder);
            currentOrder++;
        });
    },
    /* Action when adding a question from a randomGroup */
    addQuestionToRandom : function(questionItem,randomgroup,from,oldIndex) {
        //Insert as previous place
        var newRandGroup = $("#"+randomgroup).clone().removeAttr('id');
        $(newRandGroup).find(".organizer-random-question-list").remove();
        $(newRandGroup).insertAfter($(from).children('.list-item').eq(oldIndex-1));
        organizeSurvey.sortableOnQuestion($(newRandGroup).get(0));
        var randomgroupName = $("#"+randomgroup).data('randomgroup-name');
        $(questionItem).find("[data-type='random_group']").val(randomgroupName);
        $(questionItem).appendTo($("#"+randomgroup+" .organizer-question-list"));
        organizeSurvey.setRandomgroupHtml(randomgroup);
    },
    /* Action when removing a question from a randomGroup */
    removeQuestionFromRandom : function(questionItem,randomgroup) {
        var questionId = "question-item-"+$(questionItem).data("question-id");
        $("#"+questionId).insertAfter($(questionItem));
        $("#"+questionId).find("[data-type='random_group']").val("");
        $(questionItem).remove();
        var countQuestion = $("#"+randomgroup+" .organizer-question-list .organizer-question").length;
        /* Remove extra group item */
        $(".organizer-group-list [data-related-groupid='"+randomgroup+"']").slice(countQuestion).remove();
        // TODO : Manage when last is removed, not needed, current js work. Need an alert ?
        organizeSurvey.setRandomgroupHtml(randomgroup);
    },
    /* Do the HTML for data-related-groupid */
    setRandomgroupHtml : function(randomgroup) {
        $("#list-question-inrandom-"+randomgroup).html("");
        $("#"+randomgroup+" .organizer-question-list .organizer-question").each(function() {
            var thisId = $(this).attr("id");
            var htmlElement = $(this).clone().removeAttr("id").attr("data-question-id",thisId.replace("question-item-",""));
            $(htmlElement).find(".input-js").remove();
            $(htmlElement).appendTo("#list-question-inrandom-"+randomgroup);
        });
    },
    setActionAddrandomGroup : function () {
        $('.organizer-addinrandomgroup').on('click',function() {
            $("#add-randomgroup-modal").data("inputtoupdate",$(this).data("relatedinput"));
            $('#add-randomgroup-modal').modal('show');
            $('#new-randomgroup-name').focus();
        });

        $("#organizer-form").on("submit", function() {
            if ($("#add-randomgroup-modal").is(":visible")) {
                $("#new-randomgroup-name").val($("#new-randomgroup-name").val().trim());
                if(!$("#new-randomgroup-name").val()) {
                    $('#new-randomgroup-name').focus();
                    return false;
                }
                $("#"+$("#add-randomgroup-modal").data("inputtoupdate")).val($("#new-randomgroup-name").val());
            }
        });
    },
    setActionSetCountQuestion: function () {
        $(".organizer-random-group-count").on('click',function() {
            var groupName = $(this).closest('.organizer-random-group').data('randomgroup-name');
            var groupCountShown = parseInt($(this).find('.show-question-in-random-group').text());
            var groupCountMax = $(this).find('.show-question-in-random-group').text();
            if(groupCountMax < 1) {
                groupCountMax = 1;
            }
            if(groupCountShown < 1) {
                groupCountShown = groupCountMax;
            }
            $("#count-randomgroup-name-input").val(groupName);
            $("#count-randomgroup-count-input").val(groupCountShown);
            $("#count-randomgroup-modal-name").text(groupName);
            $('#count-randomgroup-modal').modal('show');
            $('#count-randomgroup-count-input').focus();
        });
        $(document).on("hide.bs.modal","#count-randomgroup-count-input",function(e) {
            $("#count-randomgroup-name-input").val("");
            $("#count-randomgroup-count-input").val("");
            $("#count-randomgroup-modal-name").text("");
        });
    },
    setCollapseExpandActionButtons: function() {
        $(document).on("click","#organizer-collapse-all",function() {
            $(".organizer-group-list .collapse").collapse('hide');
        });
        $(document).on("click","#organizer-expand-all",function() {
            $(".organizer-group-list .collapse").collapse('show');
        });
        $(document).on("click","#organizer-expand-groups",function() {
            $(".organizer-group-list .organizer-group > .collapse").collapse('show');
        });
    }
};
