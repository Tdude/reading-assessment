(function ($) {
  "use strict";
  // admin/js/ra-admin.js
  // Debug raAdmin global at script start
  // console.log("raAdmin at script start:", typeof raAdmin, raAdmin);

  if (typeof $ === "undefined") {
    console.error("jQuery is required for ReadingAssessment admin");
    return;
  }

  if (typeof raAdmin === "undefined") {
    console.error("raAdmin configuration object is missing");
    return;
  }

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
      // Map old action names to new ones
      const actionMap = {
        ra_get_progress_data: "ra_admin_get_progress_data",
        ra_get_passage: "ra_admin_get_passage",
        ra_get_passages: "ra_admin_get_passages",
        ra_delete_passage: "ra_admin_delete_passage",
        ra_get_questions: "ra_admin_get_questions",
        ra_delete_question: "ra_admin_delete_question",
        ra_get_results: "ra_admin_get_results",
        ra_create_assignment: "ra_admin_create_assignment",
        ra_delete_assignment: "ra_admin_delete_assignment",
        ra_save_assessment: "ra_admin_save_assessment",
        ra_delete_recording: "ra_admin_delete_recording",
        ra_save_interactions: "ra_admin_save_interactions",
      };

      // Use mapped action name if it exists, otherwise use original
      const mappedAction = actionMap[action] || action;
      console.log("Making AJAX request:", mappedAction, data);

      // Use default admin nonce
      const nonce = $("#ra_admin_nonce").val();

      $.ajax({
        url: raAdmin.ajaxurl,
        type: "POST",
        data: {
          action: mappedAction,
          nonce: raAdmin.nonce,
          ...data,
        },
        success: function (response) {
          console.log("AJAX response:", response);
          if (response.success) {
            if (successCallback) successCallback(response.data);
          } else {
            const message = response.data?.message || "Ett fel uppstod";
            console.error("Server reported error:", message);
            if (errorCallback) {
              errorCallback(message);
            } else {
              alert(message);
            }
          }
        },
        error: function (xhr, status, error) {
          console.error("AJAX Error details:", {
            status: status,
            error: error,
            responseText: xhr.responseText.substring(0, 250) + "...", // First 250 chars
          });
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
        if (typeof tinyMCE === "undefined") {
          console.warn("TinyMCE is not loaded but was requested");
        } else {
          const editor = tinyMCE.get(options.editor);
          if (editor) editor.setContent("");
        }
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

    // AI Eval tabs nav, "Visa" transcription modal and audio
    aiEvaluations: {
      init: function () {
        // Initialize audio player buttons
        console.log("Initialize audio player buttons");
        $(document).on("click", ".audio-lazy-button", function () {
          const containerId = $(this).data("container");
          const audioUrl = $(this).data("url");

          const container = document.getElementById(containerId);
          if (!container) return;

          const audio = document.createElement("audio");
          audio.controls = true;
          audio.style.width = "100%";
          audio.style.height = "40px";

          const source = document.createElement("source");
          source.src = audioUrl;
          source.type = "audio/webm";

          audio.appendChild(source);
          container.innerHTML = "";
          container.appendChild(audio);

          audio.play().catch((err) => console.warn("Autoplay prevented:", err));
        });

        $(document).on("click", ".show-transcription", function () {
          const transcription = $(this).data("transcription");
          const $modal = $("#transcription-modal");
          $modal.find(".transcription-text").text(transcription);
          $modal.show();
        });

        // Close modal handlers
        $(document).on(
          "click",
          ".ra-modal-close, .ra-modal-background",
          function () {
            $("#transcription-modal").hide();
          }
        );

        // Close on ESC
        $(document).keydown(function (e) {
          if (e.key === "Escape") {
            $("#transcription-modal").hide();
          }
        });
      },
    },

    dashboard: {
      init: function () {
        // Check for required elements and data
        // console.log("Chart.js available:", typeof Chart !== "undefined");
        // console.log("raAdmin data:", raAdmin);

        const $container = $(".ra-dashboard-widgets");
        if (!$container.length) {
          console.error("Dashboard widgets container not found");
          return;
        }

        // Initialize chart if canvas exists
        if ($("#progressChart").length) {
          this.initProgressChart();
        }

        // Initialize other dashboard components
        this.initEventHandlers();
      },

      initProgressChart: function () {
        const chartData = raAdmin?.progressData;
        // console.log("Raw chart data:", JSON.stringify(chartData, null, 2)); // Pretty print the data

        if (!chartData || !Array.isArray(chartData)) {
          console.error("Invalid chart data:", chartData);
          return;
        }

        // Log the actual values
        const chartLabels = chartData.map((item) => item.period_label);
        const avgData = chartData.map(
          (item) => parseFloat(item.avg_grade) || 0
        );
        const minData = chartData.map(
          (item) => parseFloat(item.min_grade) || 0
        );
        const maxData = chartData.map(
          (item) => parseFloat(item.max_grade) || 0
        );

        /* Some debugging of (the likeable) Chart.js
        console.log("Mapped data details:", {
          labels: JSON.stringify(chartLabels),
          averages: JSON.stringify(avgData),
          minimums: JSON.stringify(minData),
          maximums: JSON.stringify(maxData),
        });

        // Verify data is numeric
        console.log("Sample value checks:", {
          firstAvg: typeof avgData[0],
          firstMin: typeof minData[0],
          firstMax: typeof maxData[0],
          avgSample: avgData[0],
          minSample: minData[0],
          maxSample: maxData[0],
        });

        console.log("Chart data mapping:", {
          labels: chartLabels,
          averages: avgData,
          minimums: minData,
          maximums: maxData,
        });
        */

        try {
          const ctx = $("#progressChart")[0].getContext("2d");

          if (this.progressChart) {
            this.progressChart.destroy();
          }

          this.progressChart = new Chart(ctx, {
            type: "line",
            data: {
              labels: chartLabels,
              datasets: [
                {
                  label: "Genomsnittlig bedömning",
                  data: avgData,
                  borderColor: "#0088FE",
                  backgroundColor: "#0088FE",
                  fill: false,
                  tension: 0,
                  pointRadius: 6,
                  pointHoverRadius: 8,
                },
                {
                  label: "Min. bedömning",
                  data: minData,
                  borderColor: "#FFBB28",
                  backgroundColor: "#FFBB28",
                  fill: false,
                  borderDash: [5, 5],
                  tension: 0,
                  pointRadius: 6,
                  pointHoverRadius: 8,
                },
                {
                  label: "Max. bedömning",
                  data: maxData,
                  borderColor: "#00C49F",
                  backgroundColor: "#00C49F",
                  fill: false,
                  borderDash: [5, 5],
                  tension: 0,
                  pointRadius: 6,
                  pointHoverRadius: 8,
                },
              ],
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              scales: {
                y: {
                  beginAtZero: true,
                  min: 0,
                  max: 20,
                  ticks: {
                    stepSize: 2,
                    font: {
                      size: 12,
                    },
                  },
                  grid: {
                    color: "rgba(0,0,0,0.1)",
                  },
                },
                x: {
                  grid: {
                    color: "rgba(0,0,0,0.1)",
                  },
                  ticks: {
                    font: {
                      size: 12,
                    },
                  },
                },
              },
              plugins: {
                legend: {
                  position: "top",
                  labels: {
                    padding: 20,
                    font: {
                      size: 12,
                    },
                  },
                },
                tooltip: {
                  mode: "index",
                  intersect: false,
                  padding: 10,
                  backgroundColor: "rgba(255,255,255,0.9)",
                  titleColor: "#000",
                  bodyColor: "#000",
                  borderColor: "#ddd",
                  borderWidth: 1,
                },
              },
              interaction: {
                mode: "nearest",
                axis: "x",
                intersect: false,
              },
              layout: {
                padding: {
                  top: 20,
                  right: 20,
                  bottom: 20,
                  left: 20,
                },
              },
            },
          });
        } catch (error) {
          console.error("Error creating chart:", error);
          console.error(error);
        }
      },

      updateProgressChart: function (data) {
        console.log("Updating chart with new data:", data);

        if (!data || !Array.isArray(data)) {
          console.error("Invalid progress data:", data);
          return;
        }

        // Map the new data
        const chartLabels = data.map((item) => item.period_label);
        const avgData = data.map((item) => parseFloat(item.avg_grade) || 0);
        const minData = data.map((item) => parseFloat(item.min_grade) || 0);
        const maxData = data.map((item) => parseFloat(item.max_grade) || 0);

        // Update the chart data
        if (this.progressChart) {
          this.progressChart.data.labels = chartLabels;
          this.progressChart.data.datasets[0].data = avgData;
          this.progressChart.data.datasets[1].data = minData;
          this.progressChart.data.datasets[2].data = maxData;
          this.progressChart.update();
        } else {
          console.error("Chart not initialized");
        }
      },

      initEventHandlers: function () {
        // Create a function to fetch data
        const fetchProgressData = () => {
          const period = $("#progress-period").val();
          const userId = $("#progress-user").val();

          // console.log("Fetching progress data:", { period, userId });

          RAUtils.ajaxRequest(
            "ra_get_progress_data",
            { period, user_id: userId },
            (response) => {
              // console.log("Got progress data response:", response);
              this.updateProgressChart(response);
            },
            (error) => {
              console.error("Progress data error:", error);
              alert("Det gick inte att hämta data: " + error);
            }
          );
        };
        // Listen for changes on both dropdowns
        $("#progress-period, #progress-user").on("change", fetchProgressData);
      },
    },
    // Textpassage related Question handling
    questions: {
      initActions: function () {
        const $container = $(".wrap"); // Container for both form and questions list

        $container.on("click", "[data-action]", function (e) {
          e.preventDefault();
          const action = $(this).data("action");
          const id = $(this).data("id");

          // console.log("Question Action:", action, "ID:", id);

          switch (action) {
            case "new-question":
              RAUtils.questions.resetForm();
              break;
            case "edit":
              RAUtils.questions.edit(id);
              break;
            case "delete":
              RAUtils.questions.delete(id, $(this));
              break;
          }
        });

        // Handle passage selection change
        $("#passage_id").on("change", function () {
          const passageId = $(this).val();
          window.location.href =
            window.location.pathname +
            "?page=reading-assessment-questions&passage_id=" +
            passageId;
        });
      },

      loadQuestionsForPassage: function (passageId) {
        RAUtils.ajaxRequest(
          "ra_get_questions",
          { passage_id: passageId },
          function (response) {
            $(".ra-questions-list").html(response);
          },
          function (errorMessage) {
            console.error("Failed to load questions:", errorMessage);
            alert(errorMessage || "Kunde inte ladda frågorna");
          }
        );
      },

      resetForm: function () {
        RAUtils.resetForm("ra-question-form", {
          titleElement: "#ra-form-title",
          defaultTitle: "Lägg till ny fråga",
          cancelButton: "#ra-cancel-edit",
          hiddenFields: ["question_id"],
        });
        $("#submit").val("Spara fråga");
        RAUtils.scrollTo($("#ra-question-form"));
      },

      edit: function (questionId) {
        RAUtils.ajaxRequest(
          "ra_get_question",
          { question_id: questionId },
          function (question) {
            $("#question_id").val(question.id);
            $("#question_text").val(question.question_text);
            $("#correct_answer").val(question.correct_answer);
            $("#weight").val(question.weight);
            $("#passage_id").val(question.passage_id);

            $("#ra-form-title").text("Ändra fråga");
            $("#ra-cancel-edit").show();
            $("#submit").val("Uppdatera fråga");

            RAUtils.scrollTo($("#ra-question-form"));
          },
          function (errorMessage) {
            alert(errorMessage || "Kunde inte ladda frågan");
          }
        );
      },

      delete: function (questionId, $button) {
        if (RAUtils.confirm("Är du säker på att du vill radera denna fråga?")) {
          $button.prop("disabled", true);

          RAUtils.ajaxRequest(
            "ra_delete_question",
            { question_id: questionId },
            function (response) {
              $button.closest("tr").fadeOut(400, function () {
                $(this).remove();
                if ($(".ra-questions-list tbody tr").length === 0) {
                  $(".ra-questions-list table").replaceWith(
                    "<p>" + raAdmin.i18n.no_questions + "</p>"
                  );
                }
              });
            },
            function (errorMessage) {
              console.error("Delete error:", errorMessage);
              alert(errorMessage || "Kunde inte radera frågan");
              $button.prop("disabled", false);
            }
          );
        }
      },
    },

    passages: {
      initActions: function () {
        const $container = $(".wrap");

        // First, remove any existing handlers
        $container.off("click.passageActions", "[data-action]");

        // Then add the new handler with a namespace
        $container.on("click.passageActions", "[data-action]", function (e) {
          e.preventDefault();
          e.stopPropagation(); // Prevent event bubbling

          const action = $(this).data("action");
          const id = $(this).data("id");

          // console.log("Action:", action, "ID:", id);

          switch (action) {
            case "new-passage":
              RAUtils.passages.resetForm();
              break;
            case "edit":
              RAUtils.passages.edit(id);
              break;
            case "delete":
              RAUtils.passages.delete(id, $(this));
              break;
          }
        });
      },

      resetForm: function () {
        RAUtils.resetForm("ra-passage-form", {
          editor: "content",
          titleElement: "#ra-form-title",
          defaultTitle: "Lägg till ny text",
          cancelButton: "#ra-cancel-edit",
          hiddenFields: ["passage_id"],
        });

        // Scroll to the form
        RAUtils.scrollTo($("#ra-passage-form"));
      },

      edit: function (passageId) {
        RAUtils.ajaxRequest(
          "ra_get_passage",
          { passage_id: passageId },
          function (passage) {
            $("#passage_id").val(passage.id);
            $("#title").val(passage.title);
            $("#difficulty_level").val(passage.difficulty_level);
            $("#time_limit").val(passage.time_limit);

            if (typeof wp !== 'undefined' && wp.editor && typeof wp.editor.setContent === 'function') {
                wp.editor.setContent('content', passage.content);
            } else if (typeof tinyMCE !== 'undefined') {
                const editor = tinyMCE.get('content');
                if (editor) {
                    editor.setContent(passage.content);
                    if (editor.isHidden()) { // If in text mode (visual editor is hidden)
                        editor.save(); // Ensure the textarea is updated with the new content
                    }
                } else {
                    // Fallback if TinyMCE editor instance not found but tinyMCE is defined
                    $('#content').val(passage.content);
                }
            } else {
                // Fallback if neither wp.editor nor TinyMCE are available
                $('#content').val(passage.content);
            }

            $("#ra-form-title").text("Ändra text");
            $("#ra-cancel-edit").show();
            RAUtils.scrollTo($("#ra-passage-form"));
          },
          function (errorMessage) {
            alert(errorMessage || "Kunde inte ladda texten");
          }
        );
      },

      delete: function (passageId, $button) {
        if (RAUtils.confirm("Är du säker på att du vill radera denna text?")) {
          $button.prop("disabled", true);

          RAUtils.ajaxRequest(
            "ra_delete_passage",
            {
              passage_id: passageId,
            },
            function (response) {
              console.log("Delete success:", response);
              $button.closest("tr").fadeOut(400, function () {
                $(this).remove();
              });
            },
            function (errorMessage) {
              console.error("Delete error:", errorMessage);
              alert(errorMessage || "Kunde inte radera texten");
              $button.prop("disabled", false);
            }
          );
        }
      },
    },

    assignments: {
      initialized: false,
      initActions: function () {
        if (this.initialized) return;
        const $container = $(".wrap");
        const $form = $("#ra-assignment-form");
        // Remove existing handlers
        $container.off("click.assignmentActions", "[data-action]");
        $form.off("submit.assignmentForm");

        // Add delete handler
        $container.on("click.assignmentActions", "[data-action]", function (e) {
          e.preventDefault();
          e.stopPropagation();

          const action = $(this).data("action");
          const id = $(this).data("id");

          // console.log("Assignment Action:", action, "ID:", id);

          switch (action) {
            case "delete":
              RAUtils.assignments.delete(id, $(this));
              break;
          }
        });

        // Add form submission handler with namespace
        $form.on("submit.assignmentForm", function (e) {
          e.preventDefault();
          RAUtils.assignments.create($(this));
        });

        this.initialized = true;
      },

      create: function ($form) {
        const formData = {
          user_id: $("#user_id").val(),
          passage_id: $("#passage_id").val(),
          due_date: $("#due_date").val(),
        };

        RAUtils.ajaxRequest(
          "ra_create_assignment",
          formData,
          function (response) {
            location.reload();
          },
          function (errorMessage) {
            alert(errorMessage || "Kunde inte skapa tilldelningen");
          }
        );
      },

      delete: function (assignmentId, $button) {
        if (
          RAUtils.confirm(
            "Är du säker på att du vill ta bort denna tilldelning?"
          )
        ) {
          $button.prop("disabled", true);

          RAUtils.ajaxRequest(
            "ra_delete_assignment",
            { assignment_id: assignmentId },
            function (response) {
              $button.closest("tr").fadeOut(400, function () {
                $(this).remove();
                // Check if this was the last row
                if ($(".ra-assignments-list tbody tr").length === 0) {
                  $(".ra-assignments-list table").replaceWith(
                    "<p>" + raAdmin.i18n.no_assignments + "</p>"
                  );
                }
              });
            },
            function (errorMessage) {
              console.error("Delete error:", errorMessage);
              alert(errorMessage || "Kunde inte ta bort tilldelningen");
              $button.prop("disabled", false);
            }
          );
        }
      },
    },

    modals: {
      showAssessment: function (recordingId) {
        $("#assessment-recording-id").val(recordingId);
        $("#assessment-modal").show();
      },

      init: function () {
        const $modal = $("#assessment-modal");

        // Modal close handlers
        $(document).on(
          "click",
          ".ra-modal-close, .ra-modal-background",
          function () {
            $modal.hide();
          }
        );

        $modal.on("click", function (e) {
          if (e.target === this) {
            $(this).hide();
          }
        });

        // ESC key handler
        $(document).on("keydown", function (e) {
          if (e.key === "Escape" && $modal.is(":visible")) {
            $modal.hide();
          }
        });

        // Assessment form submission
        $("#assessment-form").on("submit.dashboard", function (e) {
          e.preventDefault();
          RAUtils.recordings.evaluate({
            recording_id: $("#assessment-recording-id").val(),
            score: $("#assessment-score").val(),
          });
        });
      },
    },

    recordings: {
      delete: function (recordingId, $button) {
        if (
          RAUtils.confirm("Är du säker på att du vill radera denna inspelning?")
        ) {
          $button.prop("disabled", true);

          RAUtils.ajaxRequest(
            "ra_admin_delete_recording",
            { recording_id: recordingId },
            function (response) {
              console.log("Delete success:", response);
              $button.closest("tr").fadeOut(400, function () {
                $(this).remove();
                // Check if this was the last row
                if ($(".wp-list-table tbody tr").length === 0) {
                  location.reload();
                }
              });
            },
            function (errorMessage) {
              console.error("Delete error:", errorMessage);
              alert(errorMessage || "Kunde inte radera inspelningen");
              $button.prop("disabled", false);
            }
          );
        }
      },
      // AI checking sound files
      // Update the checkProcessingStatus function:
      checkProcessingStatus: function (recordingId) {
        console.log("Checking status for recording:", recordingId);

        RAUtils.ajaxRequest(
          "ra_admin_check_processing_status",
          { recording_id: recordingId },
          function (response) {
            console.log("Raw response:", response);
            console.log("Response type:", typeof response);
            console.log("Response keys:", Object.keys(response));

            // Try to parse if string
            let data = response;
            if (typeof response === "string") {
              try {
                data = JSON.parse(response);
                console.log("Parsed data:", data);
              } catch (e) {
                console.error("Could not parse response:", e);
              }
            }

            // Get actual data object
            const statusData = data.data || data;
            console.log("Status data:", statusData);

            // Create status element
            const statusDiv = document.createElement("div");
            statusDiv.className = "processing-status";

            // Build inner content
            const content = `
                    <p>Transkription: ${
                      statusData.has_transcription ? "✓" : "..."
                    }</p>
                    <p>Utvärdering: ${
                      statusData.has_evaluation ? "✓" : "..."
                    }</p>
                    ${
                      statusData.next_cron
                        ? `<p>Nästa schemalagda bearbetning: ${new Date(
                            statusData.next_cron * 1000
                          ).toLocaleString()}</p>`
                        : ""
                    }
                `;

            console.log("Generated content, should be HTML: ", content);

            // Set content
            statusDiv.innerHTML = content;

            // Add button if needed
            if (!statusData.has_transcription || !statusData.has_evaluation) {
              const button = document.createElement("button");
              button.className = "button trigger-processing";
              button.dataset.recordingId = recordingId;
              button.textContent = "Bearbeta nu (Test)";
              statusDiv.appendChild(button);
            }

            // Get results container
            const $results = document.getElementById("ai-evaluation-results");
            console.log("Results element:", $results);

            // Clear and append new content
            if ($results) {
              while ($results.firstChild) {
                $results.removeChild($results.firstChild);
              }
              $results.appendChild(statusDiv);
              console.log("Final HTML:", $results.innerHTML);
            } else {
              console.error("Results element not found!");
            }
          },
          function (errorMessage) {
            console.error("Error in status check:", errorMessage);
            const $results = document.getElementById("ai-evaluation-results");
            if ($results) {
              console.log("Final HTML in $results: ", $results.innerHTML);
              $results.innerHTML = `
                        <div class="ai-score-display error">
                            <p>${
                              errorMessage || "Kunde inte kontrollera status"
                            }</p>
                        </div>
                    `;
            }
          }
        );
      },

      // Trigger manual evaluation of AI ($$$)
      triggerProcessing: function (recordingId) {
        const $results = $("#ai-evaluation-results");

        // Show loading state
        $results.html(`
            <div class="ai-score-display">
                <p>Startar bearbetning...</p>
                <div class="spinner"></div>
            </div>
        `);

        RAUtils.ajaxRequest(
          "ra_admin_trigger_processing",
          { recording_id: recordingId },
          function (response) {
            console.log("Processing triggered:", response);

            // Start checking status
            RAUtils.recordings.checkProcessingStatus(recordingId);

            // Schedule periodic status checks
            const checkInterval = setInterval(() => {
              RAUtils.recordings.checkProcessingStatus(recordingId);
            }, 5000);

            // Store interval ID
            $results.data("checkInterval", checkInterval);

            // Stop checking after 2 minutes
            setTimeout(() => {
              const interval = $results.data("checkInterval");
              if (interval) {
                clearInterval(interval);
                $results.removeData("checkInterval");
              }
              // Final status check
              RAUtils.recordings.checkProcessingStatus(recordingId);
            }, 120000);
          },
          function (errorMessage) {
            console.error("Processing error:", errorMessage);
            $results.html(`
                    <div class="ai-score-display error">
                        <p>${errorMessage}</p>
                        <button class="button trigger-processing" data-recording-id="${recordingId}">
                            Försök igen
                        </button>
                    </div>
                `);
          }
        );
      },

      evaluateWithAI: function (recordingId) {
        console.log("Starting AI evaluation for recording:", recordingId);
        // Remove previous event handlers to prevent duplicates
        $(document).off("click.processButton", ".trigger-processing");

        // Start the evaluation process
        this.checkProcessingStatus(recordingId);
      },

      showAIEvaluation: function (recordingId) {
        // Stop event propagation to prevent other handlers
        event.stopPropagation();

        const $modal = $("#ai-evaluation-modal");
        const $results = $("#ai-evaluation-results");

        // Clear any existing intervals and content
        if ($results.data("checkInterval")) {
          clearInterval($results.data("checkInterval"));
        }

        // Clear and show loading state
        $results
          .html(
            `
            <div class="ai-score-display">
                <p>Kontrollerar status...</p>
                <div class="spinner"></div>
            </div>
        `
          )
          .show();

        // Show modal
        $modal.show();

        // Start checking status
        this.checkProcessingStatus(recordingId);

        // Prevent modal from being hidden by other handlers
        return false;
      },

      initAIEvaluation: function () {
        console.log("Initializing AI evaluation handlers");

        // Clean up any existing handlers
        $(document).off(".aiEvaluation");

        // Button handler
        $(document).on("click.aiEvaluation", ".ai-evaluate-btn", function (e) {
          e.preventDefault();
          e.stopPropagation();
          const recordingId = this.dataset.recordingId;
          console.log("AI evaluate button clicked for recording:", recordingId);
          RAUtils.recordings.showAIEvaluation(recordingId);
        });

        // Modal handlers
        $(document).on("click.aiEvaluation", ".ra-modal-close", function (e) {
          e.preventDefault();
          e.stopPropagation();
          $("#ai-evaluation-modal").hide();
        });

        // Process button handler using event delegation
        $(document).on(
          "click.aiEvaluation",
          ".trigger-processing",
          function (e) {
            e.preventDefault();
            e.stopPropagation();
            const recordingId = this.dataset.recordingId;
            RAUtils.recordings.triggerProcessing(recordingId);
          }
        );

        console.log("AI evaluation handlers initialized");
      },

      displayEvaluationDetails: function (evaluationData) {
        const $details = $(".ai-eval-details");
        let html = '<div class="eval-metrics">';

        // Format each aspect
        Object.entries(evaluationData.metrics).forEach(([aspect, data]) => {
          html += `
                <div class="metric-group">
                    <h3>${aspect}</h3>
                    <div class="score-bar">
                        <div class="bar" style="width:${data.score}%"></div>
                        <span>${data.score}%</span>
                    </div>
                    <ul class="observations">
                        ${data.observations
                          .map((obs) => `<li>${obs}</li>`)
                          .join("")}
                    </ul>
                    ${
                      data.examples
                        ? `
                        <div class="examples">
                            <h4>Examples</h4>
                            <ul>${data.examples
                              .map((ex) => `<li>${ex}</li>`)
                              .join("")}</ul>
                        </div>
                    `
                        : ""
                    }
                </div>
            `;
        });

        // Add statistics
        if (evaluationData.statistics) {
          html += `
                <div class="statistics">
                    <h3>Statistics</h3>
                    <ul>
                        ${Object.entries(evaluationData.statistics)
                          .map(
                            ([key, value]) =>
                              `<li><strong>${key}:</strong> ${value}</li>`
                          )
                          .join("")}
                    </ul>
                </div>
            `;
        }

        html += "</div>";
        $details.html(html).removeClass("hidden");
      },

      evaluate: function (formData) {
        if (this.isSubmitting) return;

        this.isSubmitting = true;

        RAUtils.ajaxRequest(
          "ra_admin_save_assessment",
          {
            recording_id: formData.recording_id,
            score: formData.score,
            ai_score: $("#ai-evaluation-results").data("ai-score"),
          },
          function (response) {
            this.isSubmitting = false;
            $(".ra-modal").hide();
            location.reload();
          }.bind(this),
          function (errorMessage) {
            this.isSubmitting = false;
            alert(errorMessage || "Kunde inte spara bedömningen");
          }.bind(this)
        );
        if (response.data.has_evaluation) {
          console.log("Evaluation already completed. Skipping re-evaluation.");
          return; // Stop further processing
        }
      },

      // Dashboard specific functionality
      initDashboard: function () {
        const $container = $(".ra-dashboard-widgets");
        // console.log("Dashboard container found:", $container.length);

        // Remove any existing handlers
        $container.off("click.dashboardRecordings", "[data-action]");

        $container.on(
          "click.dashboardRecordings",
          "[data-action]",
          function (e) {
            e.preventDefault();
            e.stopPropagation();

            const $button = $(this);
            const action = $button.data("action");
            const id = $button.data("id");

            switch (action) {
              case "evaluate":
                RAUtils.recordings.showEvaluationModal(id);
                break;
              case "delete":
                RAUtils.recordings.delete(id, $button);
                break;
            }
          }
        );
      },

      // Recordings management page specific functionality
      managementInitialized: false,

      initManagement: function () {
        if (this.managementInitialized) return;
        const $container = $(".wrap");

        // Remove any existing handlers first
        $container.off("click.recordingsManagement", "[data-action='delete']");
        $container.find("audio").off("error.recordingsManagement");

        // Initialize bulk actions
        this.initBulkActions($container);

        // Individual delete handler with namespaced event
        $container.on(
          "click.recordingsManagement",
          "[data-action='delete']",
          function (e) {
            e.preventDefault();
            e.stopPropagation(); // Prevent event bubbling
            const id = $(this).data("id");
            RAUtils.recordings.delete(id, $(this));
          }
        );

        // Handle audio loading errors with namespaced event
        $container.find("audio").on("error.recordingsManagement", function (e) {
          console.log("Audio loading error:", e);
          $(this).replaceWith(
            '<span class="error-text">' +
              (raAdmin.strings.audioLoadError || "Kunde inte ladda ljudfil") +
              "</span>"
          );
        });
        this.initAIEvaluation();
        this.managementInitialized = true; // Mark as initialized
      },

      initBulkActions: function ($container) {
        // Bulk action handlers
        $("#bulk-action-selector-top").on("change", function () {
          const action = $(this).val();
          $("#bulk-passage-id").toggle(action === "assign");
        });

        $("#bulk-apply").on("click", function () {
          const action = $("#bulk-action-selector-top").val();
          const selectedIds = $('input[name="recording_ids[]"]:checked')
            .map(function () {
              return $(this).val();
            })
            .get();

          if (!selectedIds.length) {
            alert(
              raAdmin.strings.noRecordingsSelected || "Välj minst en inspelning"
            );
            return;
          }

          switch (action) {
            case "delete":
              if (
                RAUtils.confirm(
                  raAdmin.strings.confirmBulkDelete ||
                    "Är du säker på att du vill radera dessa inspelningar?"
                )
              ) {
                RAUtils.recordings.bulkDelete(selectedIds);
              }
              break;
            case "assign":
              const passageId = $("#bulk-passage-id").val();
              if (!passageId) {
                alert(raAdmin.strings.selectPassage || "Välj en text först");
                return;
              }
              RAUtils.recordings.bulkAssign(selectedIds, passageId);
              break;
          }
        });

        // Handle "select all" checkbox
        $("#cb-select-all-1").on("change", function () {
          $('input[name="recording_ids[]"]').prop(
            "checked",
            $(this).prop("checked")
          );
        });
      },

      showEvaluationModal: function (recordingId) {
        console.log("=== showEvaluationModal Debug ===");
        console.log("Called with recordingId:", recordingId);
        console.log("Modal element exists:", $("#assessment-modal").length);
        console.log(
          "Recording ID input exists:",
          $("#assessment-recording-id").length
        );

        $("#assessment-recording-id").val(recordingId);
        console.log(
          "After setting value - Recording ID input value:",
          $("#assessment-recording-id").val()
        );

        $("#assessment-modal").show();
        console.log(
          "Modal display state:",
          $("#assessment-modal").css("display")
        );
      },

      bulkDelete: function (recordingIds) {
        const totalRecordings = recordingIds.length;
        let processed = 0;
        let errors = 0;

        // Disable all delete buttons during bulk operation
        $("[data-action='delete']").prop("disabled", true);
        $("#bulk-apply").prop("disabled", true);

        recordingIds.forEach(function (id) {
          RAUtils.ajaxRequest(
            "ra_admin_delete_recording",
            { recording_id: id },
            function () {
              $(`tr:has(button[data-id="${id}"])`).fadeOut();
              processed++;
              if (processed === totalRecordings) {
                if (errors > 0) {
                  alert(`${errors} inspelningar kunde inte raderas.`);
                }
                location.reload();
              }
            },
            function () {
              errors++;
              processed++;
              if (processed === totalRecordings) {
                location.reload();
              }
            }
          );
        });
      },

      bulkAssign: function (recordingIds, passageId) {
        const totalRecordings = recordingIds.length;
        let processed = 0;
        let errors = 0;

        $("#bulk-apply").prop("disabled", true);

        recordingIds.forEach(function (id) {
          RAUtils.ajaxRequest(
            "ra_admin_assign_recording",
            {
              recording_id: id,
              passage_id: passageId,
            },
            function () {
              processed++;
              if (processed === totalRecordings) {
                location.reload();
              }
            },
            function () {
              errors++;
              processed++;
              if (processed === totalRecordings) {
                alert(`${errors} tilldelningar misslyckades.`);
                location.reload();
              }
            }
          );
        });
      },
    },

    init: function () {
      try {
        console.log("Starting RAUtils initialization");

        // First initialize base functionality that all pages need
        this.initModals();
        this.initFormHandlers();
        this.initInstructionToggles();

        // Get page information
        const $dashboardWrap = $(".ra-dashboard-widgets");
        const isDashboard = $dashboardWrap.length > 0;
        const currentPage = $(".wrap").data("page");

        console.log("Page context:", { isDashboard, currentPage });

        // Handle stats tracking if needed
        if ($(".ra-stats-section").length) {
          this.initStatsTracking();
        }

        // Initialize AI features if on relevant pages
        if (isDashboard || currentPage === "recordings-management") {
          console.log("Initializing AI features");
          this.recordings.initAIEvaluation();
        }

        // AI evaluation tabs
        if ($(".ra-evaluations-content").length) {
          this.aiEvaluations.init();
        }

        // Initialize dashboard-specific features
        if (isDashboard) {
          console.log("Initializing dashboard features");
          this.dashboard.init();
          this.recordings.initDashboard();
        }

        // Handle other page-specific initializations
        if (!isDashboard) {
          console.log("Initializing page-specific features:", currentPage);

          switch (currentPage) {
            case "assignments":
              if ($(".ra-assignments-list").length) {
                this.assignments.initActions();
              }
              break;

            case "recordings":
              if ($(".ra-recordings-list").length) {
                this.recordings.initActions();
              }
              break;

            case "recordings-management":
              if ($(".wrap").find(".wp-list-table").length) {
                this.recordings.initManagement();
              }
              break;

            case "passages":
              if ($(".ra-passages-list").length && !this.passages.initialized) {
                this.passages.initActions();
                this.passages.initialized = true;
              }
              break;

            case "ai-evaluations":
              console.log("Initializing AI evaluations features");
              this.aiEvaluations.init();
              break;

            case "questions":
              if ($(".ra-questions-list").length) {
                this.questions.initActions();
              }
              if ($(".ra-questions-table-container").length) {
                this.questions.initActions();
                this.questions.load();
              }
              break;

            default:
              // Fallback initialization for pages without specific data-page attribute
              this.initializeFallbackFeatures();
              break;
          }
        }

        this.checkDuplicatePassages();
        console.log("RAUtils initialization completed");
      } catch (error) {
        console.error("Error in RAUtils init:", error);
      }
    },

    // Helper method for fallback initializations
    initializeFallbackFeatures: function () {
      if ($(".wrap").find(".wp-list-table").length) {
        this.recordings.initManagement();
      }
      if ($(".ra-passages-list").length && !this.passages.initialized) {
        this.passages.initActions();
        this.passages.initialized = true;
      }
      if ($(".ra-questions-list").length) {
        this.questions.initActions();
      }
      if ($(".ra-assignments-list").length) {
        this.assignments.initActions();
      }
      if ($(".ra-recordings-list").length) {
        this.recordings.initActions();
      }
      if ($(".ra-questions-table-container").length) {
        this.questions.initActions();
        this.questions.load();
      }
    },

    initInstructionToggles: function () {
      console.log("Initializing instruction toggles");

      // First unbind any existing handlers to prevent duplicates
      $("#toggle-instructions").off("click");

      // Direct binding with namespace to avoid conflicts
      $("#toggle-instructions").on("click.raInstructions", function (e) {
        console.log("Toggle button clicked");
        e.preventDefault();

        const $instructionsContent = $("#instructions-content");
        console.log("Instructions content found:", $instructionsContent.length);

        $instructionsContent.toggleClass("show");

        // Store state in localStorage
        const isVisible = $instructionsContent.hasClass("show");
        localStorage.setItem("instructionsVisible", isVisible);
        console.log("Visibility state saved:", isVisible);
      });

      // Set initial state
      const $instructionsContent = $("#instructions-content");
      if ($instructionsContent.length) {
        const isVisible =
          localStorage.getItem("instructionsVisible") === "true";
        if (isVisible) {
          $instructionsContent.addClass("show");
        }
      }
    },

    initModals: function () {
      // Assessment modal
      const $assessmentModal = $("#assessment-modal");
      // AI evaluation modal
      const $aiModal = $("#ai-evaluation-modal");

      // Clear existing handlers
      $(document).off(".modalHandlers");

      // Handle assessment modal
      $(document).on(
        "click.modalHandlers",
        "#assessment-modal .ra-modal-close",
        function () {
          $assessmentModal.hide();
        }
      );

      // Handle AI evaluation modal separately
      $(document).on(
        "click.modalHandlers",
        "#ai-evaluation-modal .ra-modal-close",
        function () {
          $aiModal.hide();
        }
      );

      // ESC key handler
      $(document).on("keydown.modalHandlers", function (e) {
        if (e.key === "Escape") {
          $assessmentModal.hide();
          $aiModal.hide();
        }
      });
    },

    initFormHandlers: function () {
      // Form cancel handler for passage and question forms
      $(document).on("click", "#ra-cancel-edit", function () {
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

      // Delegate assessment form handling to recordings module
      $(document).on("submit", "#assessment-form", function (e) {
        e.preventDefault();
        RAUtils.recordings.evaluate({
          recording_id: $("#assessment-recording-id").val(),
          score: $("#assessment-score").val(),
        });
      });

      // Make Wordpress labels clickable
      $(".form-table tr").each(function () {
        var input = $(this).find("input");
        var description = $(this).find(".description");
        if (input.length && description.length) {
          description.css("cursor", "pointer");
          description.on("click", function () {
            input.trigger("click").focus();
          });
        }
      });
    },

    initStatsTracking: function () {
      let lastActivity = Date.now();
      let clickCount = 0;
      let isActive = false;
      const INACTIVE_TIMEOUT = 10000;
      const SAVE_INTERVAL = 60000;

      const handleActivity = () => {
        lastActivity = Date.now();
        isActive = true;
      };

      const handleClick = () => {
        handleActivity();
        clickCount++;
      };

      $(document).on({
        click: handleClick,
        mousemove: handleActivity,
        keypress: handleActivity,
        scroll: handleActivity,
      });

      setInterval(() => {
        if (Date.now() - lastActivity > INACTIVE_TIMEOUT) {
          isActive = false;
        }
      }, INACTIVE_TIMEOUT);

      setInterval(() => {
        const currentTime = Date.now();
        const activeTime = isActive ? (currentTime - lastActivity) / 1000 : 0;
        const idleTime = (SAVE_INTERVAL - activeTime * 1000) / 1000;

        RAUtils.ajaxRequest(
          "ra_save_interactions",
          {
            clicks: clickCount,
            active_time: Math.round(activeTime),
            idle_time: Math.round(idleTime),
          },
          function () {
            clickCount = 0;
          },
          function (error) {
            console.error("Save error:", error);
          }
        );
      }, SAVE_INTERVAL);
    },

    checkDuplicatePassages: function () {
      const passages = document.querySelectorAll(".passage-item");
      const passageIds = new Set();

      passages.forEach((passage) => {
        const id = passage.dataset.passageId;
        if (passageIds.has(id)) {
          console.warn(`Duplicate passage ID found: ${id}`);
        }
        passageIds.add(id);
      });
    },

    handleAudioLazyLoad: function (containerId, audioUrl) {
      const container = document.getElementById(containerId);
      if (!container) return;

      const audio = document.createElement("audio");
      audio.controls = true;
      audio.style.width = "100%";
      audio.style.height = "40px";

      const source = document.createElement("source");
      source.src = audioUrl;
      source.type = "audio/webm";

      audio.appendChild(source);
      container.innerHTML = "";
      container.appendChild(audio);

      audio.play().catch((err) => console.warn("Autoplay prevented"));
    },
  };

  // Single initialization point
  $(document).ready(function () {
    RAUtils.init();
  });
})(jQuery);
