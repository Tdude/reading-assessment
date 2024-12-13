(function ($) {
  "use strict";

  // Main utilities object in ReadingAssessment
  window.RAUtils = {
    // Basic utilities
    confirm: function (message) {
      return confirm(message || "Är du säker?");
    },

    scrollTo: function ($element, offset = 50) {
      $("html, body").animate(
        {
          scrollTop: $element.offset().top - offset,
        },
        500
      );
    },

    // AJAX wrapper
    ajaxRequest: function (action, data, successCallback, errorCallback) {
      // Use data.nonce if provided, otherwise fall back to default nonce
      const nonce = data.nonce || raStrings.nonce;

      // Remove nonce from data if it exists to avoid sending it twice
      const { nonce: _, ...cleanData } = data;

      $.ajax({
        url: raStrings.ajaxurl,
        type: "POST",
        data: {
          action: action,
          nonce: nonce,
          ...cleanData,
        },
        success: function (response) {
          if (response.success) {
            if (successCallback) successCallback(response.data);
          } else {
            if (errorCallback) {
              errorCallback(response.data?.message);
            } else {
              alert(response.data?.message || "Ett fel uppstod");
            }
          }
        },
        error: function (xhr, status, error) {
          console.error("AJAX Error:", error);
          if (errorCallback) {
            errorCallback("Ett fel uppstod vid kommunikation med servern");
          }
        },
      });
    },

    // Form handling
    resetForm: function (formId, options = {}) {
      const $form = $(`#${formId}`);
      $form[0].reset();

      if (options.editor) {
        // Handle visual editor
        if (typeof tinyMCE !== "undefined") {
          const editor = tinyMCE.get(options.editor);
          if (editor) editor.setContent("");
        }
        // Handle text editor
        $(`#${options.editor}`).val("");
      }
      if (options.titleElement) {
        $(options.titleElement).text(options.defaultTitle || "");
      }

      if (options.cancelButton) {
        $(options.cancelButton).hide();
      }

      if (options.hiddenFields) {
        options.hiddenFields.forEach((field) => {
          $(`#${field}`).val("");
        });
      }
    },

    // Passage handling
    passages: {
      load: function () {
        RAUtils.passages.initActions();
      },

      edit: function (passageId) {
        RAUtils.ajaxRequest(
          "ra_get_passage",
          { passage_id: passageId },
          function (passage) {
            $("#passage_id").val(passage.id);
            $("#title").val(passage.title);
            $("#difficulty_level").val(passage.difficulty_level);
            if (typeof tinyMCE !== "undefined" && tinyMCE.get("content")) {
              tinyMCE.get("content").setContent(passage.content);
            }
            $("#time_limit").val(passage.time_limit);

            $("#ra-form-title").text("Ändra text");
            $("#ra-cancel-edit").show();
            RAUtils.scrollTo($("#ra-passage-form"));
          }
        );
      },

      initActions: function () {
        $(".ra-edit-passage").on("click", function (e) {
          e.preventDefault();
          RAUtils.passages.edit($(this).data("id"));
        });

        $(".ra-delete-passage").on("click", function (e) {
          e.preventDefault();
          RAUtils.passages.delete($(this).data("id"), $(this));
        });
      },

      delete: function (passageId, $button) {
        if (
          RAUtils.confirm(
            "Är du säker på att du vill radera denna text? Den försvinner helt."
          )
        ) {
          $button.prop("disabled", true);
          RAUtils.ajaxRequest(
            "ra_delete_passage",
            { passage_id: passageId },
            function () {
              $button.closest("tr").fadeOut(400, function () {
                $(this).remove();
              });
            },
            null,
            function () {
              $button.prop("disabled", false);
            }
          );
        }
      },

      initActions: function () {
        $(".ra-edit-passage").on("click", function (e) {
          e.preventDefault();
          RAUtils.passages.edit($(this).data("id"));
        });
      },
    },

    // Question handling
    questions: {
      load: function () {
        if ($("#ra-questions-table-container").length) {
          RAUtils.ajaxRequest("ra_get_questions", {}, function (response) {
            $("#ra-questions-table-container").html(response);
            RAUtils.questions.initActions();
          });
        }
      },

      edit: function (questionData) {
        $("#question_id").val(questionData.id);
        $("#question_text").val(questionData.question);
        $("#correct_answer").val(questionData.answer);
        $("#weight").val(questionData.weight);

        $("#ra-form-title").text("Ändra fråga");
        $("#ra-cancel-edit").show();
        RAUtils.scrollTo($("#ra-question-form"));
      },

      delete: function (questionId) {
        if (RAUtils.confirm("Är du säker på att du vill radera denna fråga?")) {
          RAUtils.ajaxRequest(
            "ra_delete_question",
            { question_id: questionId },
            function () {
              RAUtils.questions.load();
            }
          );
        }
      },

      initActions: function () {
        $(".ra-edit-question").on("click", function (e) {
          e.preventDefault();
          const $button = $(this);
          RAUtils.questions.edit({
            id: $button.data("id"),
            question: $button.data("question"),
            answer: $button.data("answer"),
            weight: $button.data("weight"),
          });
        });

        $(".ra-delete-question").on("click", function (e) {
          e.preventDefault();
          RAUtils.questions.delete($(this).data("id"));
        });
      },
    },

    // Assignment handling
    assignments: {
      delete: function (assignmentId) {
        if (
          RAUtils.confirm(
            "Är du säker på att du vill ta bort denna tilldelning?"
          )
        ) {
          RAUtils.ajaxRequest(
            "ra_delete_assignment",
            { assignment_id: assignmentId },
            function () {
              location.reload(); // Reload page to show updated list
            }
          );
        }
      },

      initActions: function () {
        $(".ra-delete-assignment").on("click", function (e) {
          e.preventDefault();
          RAUtils.assignments.delete($(this).data("id"));
        });
      },
    },

    // Recording handling
    recordings: {
      delete: function (recordingId, $button) {
        if (RAUtils.confirm(raStrings.confirmDelete)) {
          $button.prop("disabled", true);
          RAUtils.ajaxRequest(
            "ra_delete_recording",
            {
              recording_id: recordingId,
            },
            function (response) {
              console.log("Delete success:", response); // Debug line
              $button.closest("tr").fadeOut(400, function () {
                $(this).remove();
              });
            },
            function (errorMessage) {
              console.error("Delete error:", errorMessage); // Debug line
              alert(errorMessage);
              $button.prop("disabled", false);
            }
          );
        }
      },
      initActions: function () {
        $(".delete-recording").on("click", function (e) {
          e.preventDefault();
          console.log("Delete button clicked"); // Debug line
          RAUtils.recordings.delete($(this).data("recording-id"), $(this));
        });
      },
    },
  };

  // Initialize when document is ready
  $(document).ready(function () {
    // Instructions toggle handler
    const $toggleButton = $("#toggle-instructions");
    const $instructionsContent = $("#instructions-content");

    if ($toggleButton.length && $instructionsContent.length) {
      const isVisible = localStorage.getItem("instructionsVisible") === "true";
      if (isVisible) $instructionsContent.addClass("show");

      $toggleButton.on("click", function () {
        $instructionsContent.toggleClass("show");
        localStorage.setItem(
          "instructionsVisible",
          $instructionsContent.hasClass("show")
        );
      });
    }

    // Initialize both passages and questions if their containers exist
    RAUtils.passages.load();
    RAUtils.questions.load();

    // Form cancel handlers
    $("#ra-cancel-edit").on("click", function () {
      const formType = $(this).closest("form").attr("id");
      if (formType === "ra-passage-form") {
        RAUtils.resetForm("ra-passage-form", {
          editor: "content",
          titleElement: "#ra-form-title",
          defaultTitle: "Lägg till ny text",
          cancelButton: "#ra-cancel-edit",
          hiddenFields: ["passage_id"],
        });
      } else if (formType === "ra-question-form") {
        RAUtils.resetForm("ra-question-form", {
          titleElement: "#ra-form-title",
          defaultTitle: "Lägg till ny fråga",
          cancelButton: "#ra-cancel-edit",
          hiddenFields: ["question_id"],
        });
      }
    });

    // Assessment scoring of the soundbytes
    const modal = $("#assessment-modal");

    $(".add-assessment").click(function () {
      const recordingId = $(this).data("recording-id");
      $("#assessment-recording-id").val(recordingId);
      modal.show();
    });

    // Close on 'x' button
    $(".ra-modal-close").click(function () {
      modal.hide();
    });

    // Close when clicking outside the modal
    $(".ra-modal").click(function (e) {
      // If the click was directly on the modal background (not its children)
      if (e.target === this) {
        modal.hide();
      }
    });

    // Close on ESC key
    $(document).keydown(function (e) {
      if (e.key === "Escape" && modal.is(":visible")) {
        modal.hide();
      }
    });

    $("#assessment-form").submit(function (e) {
      e.preventDefault();

      const formData = {
        action: "ra_save_assessment",
        recording_id: $("#assessment-recording-id").val(),
        score: $("#assessment-score").val(),
      };

      RAUtils.ajaxRequest(
        "ra_save_assessment",
        formData,
        function () {
          modal.hide();
          location.reload();
        },
        function (errorMessage) {
          alert(errorMessage || "Ett fel uppstod");
        }
      );
    });

    // Initialize recordings if we're on the dashboard
    if ($(".delete-recording").length) {
      RAUtils.recordings.initActions();
    }

    // SHow admin interaction stats
    // Only initialize if tracking is enabled and we're on the dashboard
    if (!$(".ra-stats-section").length) {
      return;
    }

    let lastActivity = Date.now();
    let clickCount = 0;
    let isActive = false;
    const INACTIVE_TIMEOUT = 10000; // 10 seconds
    const SAVE_INTERVAL = 60000; // 1 minute

    function handleActivity() {
      lastActivity = Date.now();
      isActive = true;
    }

    function handleClick() {
      handleActivity();
      clickCount++;
      console.log("Click detected, total clicks:", clickCount);
    }

    // Separate click handler from other activity handlers
    $(document).on("click", handleClick);
    $(document).on("mousemove keypress scroll", handleActivity);

    function saveInteractions() {
      const currentTime = Date.now();
      const activeTime = isActive ? (currentTime - lastActivity) / 1000 : 0;
      const idleTime = (SAVE_INTERVAL - activeTime * 1000) / 1000;

      console.log("Saving interactions:", {
        clicks: clickCount,
        activeTime: Math.round(activeTime),
        idleTime: Math.round(idleTime),
      });

      RAUtils.ajaxRequest(
        "ra_save_interactions",
        {
          clicks: clickCount,
          active_time: Math.round(activeTime),
          idle_time: Math.round(idleTime),
        },
        function (response) {
          console.log("Save response:", response);
          clickCount = 0;
          location.reload();
        },
        function (error) {
          console.error("Save error:", error);
        }
      );
    }

    // Bind events
    $(document).on("click mousemove keypress scroll", handleActivity);

    // Check for inactivity
    setInterval(function () {
      if (Date.now() - lastActivity > INACTIVE_TIMEOUT) {
        isActive = false;
      }
    }, INACTIVE_TIMEOUT);

    // Save periodically
    setInterval(saveInteractions, SAVE_INTERVAL);
  });
})(jQuery);
