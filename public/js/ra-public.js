// ra-public.js
window.RAPublicUtils = {
  VALID_TYPES: Object.freeze({
    SUCCESS: "success",
    ERROR: "error",
    WARNING: "warning",
    INFO: "info",
  }),

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

  waitForRecorder: function () {
    return new Promise((resolve) => {
      if (window.RecorderManager && window.RecorderManager.isReady()) {
        console.log("Recorder already ready");
        resolve(window.RecorderManager.getInstance());
        return;
      }

      console.log("Waiting for recorder to be ready...");
      const readyHandler = () => {
        console.log("Recorder ready event received");
        window.removeEventListener("recorderReady", readyHandler);
        resolve(window.RecorderManager.getInstance());
      };
      window.addEventListener("recorderReady", readyHandler);
    });
  },

  async updateRecorderState(passageId) {
    try {
      console.log("Waiting for recorder before updating state");
      const recorder = await this.waitForRecorder();

      if (recorder) {
        console.log("Updating recorder for passage:", passageId);
        recorder.handlePassageChange(passageId);
      } else {
        console.error("Recorder not available after waiting");
      }
    } catch (error) {
      console.error("Error updating recorder state:", error);
    }
  },

  initCollapsible: function () {
    document.querySelectorAll(".ra-collapsible-title").forEach((title) => {
      title.addEventListener("click", async (event) => {
        event.stopPropagation();

        const passageId = title.dataset.passageId;
        if (!passageId) {
          console.log("No passage ID found");
          return;
        }

        console.log("Handling click for passage:", passageId);

        // Close other passages
        document
          .querySelectorAll(".ra-collapsible-title")
          .forEach((otherTitle) => {
            if (otherTitle !== title) {
              otherTitle.classList.remove("ra-collapsible-title--active");
              const otherContent = document.getElementById(
                "passage-" + otherTitle.dataset.passageId
              );
              if (otherContent) {
                otherContent.classList.remove("ra-collapsible-content--active");
              }
            }
          });

        // Toggle current
        const content = document.getElementById("passage-" + passageId);
        if (content) {
          content.classList.toggle("ra-collapsible-content--active");
          title.classList.toggle("ra-collapsible-title--active");
        }

        // Update hidden input and recorder state
        const currentPassageInput =
          document.getElementById("current-passage-id");
        if (currentPassageInput) {
          const oldValue = currentPassageInput.value;
          currentPassageInput.value = passageId;

          if (oldValue !== passageId) {
            console.log("Passage changed from", oldValue, "to", passageId);
            await this.updateRecorderState(passageId);
          }
        }
      });
    });
  },
};

document.addEventListener("DOMContentLoaded", function () {
  console.log("Initializing RAPublicUtils");
  RAPublicUtils.initCollapsible();

  if (new URLSearchParams(window.location.search).get("login") === "success") {
    RAPublicUtils.showOverlay("Du är inloggad.", "success");
  }
});
