// public/js/ra-public.js
window.RAPublicUtils = {
  // Message type here for optimization
  VALID_TYPES: Object.freeze({
    SUCCESS: "success",
    ERROR: "error",
    WARNING: "warning",
    INFO: "info",
  }),
  // show overlays with this object: RAPublicUtils.showOverlay("message", "success", 1000)
  showOverlay: function (message, messageType = "info", duration = 1500) {
    const overlay = document.createElement("div");
    overlay.className = "ra-overlay";

    const messageDiv = document.createElement("div");
    messageDiv.className = "ra-message";

    if (messageType && Object.values(this.VALID_TYPES).includes(messageType)) {
      messageDiv.classList.add(`ra-message--${messageType}`);
    }

    messageDiv.textContent = message;

    overlay.appendChild(messageDiv);
    document.body.appendChild(overlay);

    requestAnimationFrame(() => (overlay.style.opacity = "1"));
    setTimeout(() => {
      overlay.style.opacity = "0";
      setTimeout(() => overlay.remove(), 500);
    }, duration);
  },

  initCollapsible: function () {
    document
      .querySelectorAll(".ra-collapsible-title")
      .forEach(function (title) {
        title.addEventListener("click", function () {
          const passageId = this.dataset.passageId;
          if (!passageId) return;

          // Close other passages
          document
            .querySelectorAll(".ra-collapsible-title")
            .forEach(function (otherTitle) {
              if (otherTitle !== title) {
                otherTitle.classList.remove("ra-collapsible-title--active");
                const otherContent = document.getElementById(
                  "passage-" + otherTitle.dataset.passageId
                );
                if (otherContent)
                  otherContent.classList.remove(
                    "ra-collapsible-content--active"
                  );
              }
            });

          // Toggle current
          const content = document.getElementById("passage-" + passageId);
          if (content) {
            content.classList.toggle("ra-collapsible-content--active");
            this.classList.toggle("ra-collapsible-title--active");
          }

          // Update recorder
          const currentPassageInput =
            document.getElementById("current-passage-id");
          if (currentPassageInput) {
            currentPassageInput.value = passageId;

            // Reset UI elements
            const warning = document.querySelector(".ra-warning");
            if (warning) warning.style.display = "none";

            const controls = document.querySelector(".ra-controls");
            if (controls) controls.classList.remove("ra-controls--disabled");

            const status = document.getElementById("status");
            if (status)
              status.textContent = "Klicka på 'Spela in' för att börja.";

            // Reset buttons
            [
              "start-recording",
              "stop-recording",
              "upload-recording",
              "playback",
              "trim-audio",
            ].forEach((id) => {
              const btn = document.getElementById(id);
              if (btn) btn.disabled = id !== "start-recording";
            });

            const waveform = document.getElementById("waveform");
            if (waveform) {
              const existingWavesurfer = window.currentWavesurfer;
              if (existingWavesurfer) {
                existingWavesurfer.destroy();
                window.currentWavesurfer = null;
              }
            }

            const questionsSection =
              document.getElementById("questions-section");
            if (questionsSection) {
              questionsSection.style.display = "none";
              questionsSection.innerHTML = "";
            }

            // Init new recorder
            const recorderContainer =
              document.querySelector(".ra-audio-recorder");
            if (recorderContainer && typeof initializeRecorder === "function") {
              recorderContainer.removeAttribute("data-initialized");
              initializeRecorder(recorderContainer);
            }
          }
        });
      });
  },
};

document.addEventListener("DOMContentLoaded", function () {
  RAPublicUtils.initCollapsible();

  // Handle login message
  if (new URLSearchParams(window.location.search).get("login") === "success") {
    RAPublicUtils.showOverlay("Du är inloggad.", "success");
  }
});
