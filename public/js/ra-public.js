// ra-public.js
window.RAPublicUtils = {
  initCollapsible: function () {
    document
      .querySelectorAll(".ra-collapsible-title")
      .forEach(function (title) {
        title.addEventListener("click", function () {
          const passageId = this.dataset.passageId;
          if (!passageId) return;

          // Close all other passages
          document
            .querySelectorAll(".ra-collapsible-title")
            .forEach(function (otherTitle) {
              if (otherTitle !== title) {
                otherTitle.classList.remove("active");
                const otherContent = document.getElementById(
                  "passage-" + otherTitle.dataset.passageId
                );
                if (otherContent) {
                  otherContent.classList.remove("show");
                }
              }
            });

          // Toggle current passage
          const contentId = "passage-" + passageId;
          const content = document.getElementById(contentId);
          if (content) {
            content.classList.toggle("show");
            this.classList.toggle("active");
          }

          // Update recorder buttons
          const currentPassageInput =
            document.getElementById("current-passage-id");
          if (currentPassageInput) {
            currentPassageInput.value = passageId;
            console.log("Selected passage ID:", passageId);

            // Reset recorder UI
            const warning = document.querySelector(".ra-warning");
            if (warning) warning.style.display = "none";

            const controls = document.querySelector(".ra-controls");
            if (controls) controls.classList.remove("ra-controls-disabled");

            const status = document.getElementById("status");
            if (status)
              status.textContent = "Klicka på 'Spela in' för att börja.";

            // Reset buttons
            const startBtn = document.getElementById("start-recording");
            const stopBtn = document.getElementById("stop-recording");
            const uploadBtn = document.getElementById("upload-recording");
            const playbackBtn = document.getElementById("playback");
            const trimBtn = document.getElementById("trim-audio");

            if (startBtn) startBtn.disabled = false;
            if (stopBtn) stopBtn.disabled = true;
            if (uploadBtn) uploadBtn.disabled = true;
            if (playbackBtn) playbackBtn.disabled = true;
            if (trimBtn) trimBtn.disabled = true;

            // Clean up previous recorder state
            const waveform = document.getElementById("waveform");
            if (waveform) waveform.innerHTML = "";

            const questionsSection =
              document.getElementById("questions-section");
            if (questionsSection) {
              questionsSection.style.display = "none";
              questionsSection.innerHTML = "";
            }

            // Initialize new recorder
            const recorderContainer =
              document.querySelector(".ra-audio-recorder");
            if (recorderContainer && typeof initializeRecorder === "function") {
              initializeRecorder(recorderContainer);
            }
          }
        });
      });
  },
};

document.addEventListener("DOMContentLoaded", function () {
  RAPublicUtils.initCollapsible();
});
